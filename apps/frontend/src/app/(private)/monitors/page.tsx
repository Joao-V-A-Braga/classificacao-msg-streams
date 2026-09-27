'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { MessageSquareText, Pause, Play, Plus, Radio } from 'lucide-react';
import { Button } from '@/shared/components/ui/button';
import { EmptyListState } from '@/shared/components/ui/empty-list-state';
import { useAuthContext } from '@/modules/auth';
import {
  CreateMonitorDialog,
  IngestionOfflineAlert,
  useIngestionOnline,
  MIN_TAGS_TO_START,
  MonitorStatusBadge,
  SOURCE_TYPE_LABEL,
  monitorsApi,
  notifyRequestError,
  tagTone,
  useRealtime,
  type Monitor,
  type RealtimeEvent,
} from '@/modules/monitors';
import { cn } from '@/shared/lib/class-name.util';

export default function MonitorsPage() {
  const router = useRouter();
  const { token } = useAuthContext();
  const [monitors, setMonitors] = useState<Monitor[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    monitorsApi
      .list(token)
      .then((result) => {
        if (!cancelled) setMonitors(result.items);
      })
      .catch((error: unknown) => {
        if (!cancelled) notifyRequestError(error);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [token, reloadKey]);

  const handleEvent = useCallback((event: RealtimeEvent) => {
    if (event.event === 'monitor.updated') {
      const updated = event.data.monitor;
      setMonitors((current) =>
        current.some((monitor) => monitor.id === updated.id)
          ? current.map((monitor) => (monitor.id === updated.id ? updated : monitor))
          : [updated, ...current],
      );
    } else if (event.event === 'monitor.deleted') {
      setMonitors((current) => current.filter((monitor) => monitor.id !== event.data.monitorId));
    } else if (event.event === 'message.created') {
      const { monitorId } = event.data.message;
      setMonitors((current) =>
        current.map((monitor) =>
          monitor.id === monitorId && monitor.stats
            ? { ...monitor, stats: { ...monitor.stats, total: monitor.stats.total + 1 } }
            : monitor,
        ),
      );
    }
  }, []);

  const ingestionOnline = useIngestionOnline(monitors.some((monitor) => monitor.status === 'running'));

  useRealtime(handleEvent,{ onReconnect: () => setReloadKey((key) => key + 1) });

  async function toggle(monitor: Monitor) {
    if (!token) return;
    setBusyId(monitor.id);
    try {
      const updated =
        monitor.status === 'running'
          ? await monitorsApi.pause(token, monitor.id)
          : await monitorsApi.start(token, monitor.id);
      setMonitors((current) => current.map((item) => (item.id === updated.id ? updated : item)));
    } catch (error) {
      notifyRequestError(error);
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-black tracking-tight text-foreground">Monitoramentos</h1>
          <p className="text-sm text-muted-foreground">
            Cada live ou post é uma pipeline separada. Ao iniciar, só as mensagens novas são lidas e classificadas
            pelo Laya com as tags do monitoramento.
          </p>
        </div>
        <Button onClick={() => setIsCreating(true)} className="shrink-0">
          <Plus className="size-4" /> Novo monitoramento
        </Button>
      </div>

      {ingestionOnline === false ? <IngestionOfflineAlert /> : null}

      {!isLoading && monitors.length === 0 ? (
        <div className="rounded-lg border border-border bg-card py-10">
          <EmptyListState
            title="Nenhum monitoramento ainda"
            subtitle="Crie um monitoramento para uma live do YouTube ou um post do Instagram."
          />
        </div>
      ) : null}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {monitors.map((monitor) => {
          const Icon = monitor.sourceType === 'youtube_live' ? Radio : MessageSquareText;
          const canStart = monitor.tags.length >= MIN_TAGS_TO_START;
          return (
            <div
              key={monitor.id}
              className="group flex flex-col gap-4 rounded-lg border border-border bg-card p-5 transition hover:border-white/25"
            >
              <Link href={`/monitors/${monitor.id}`} className="flex flex-1 flex-col gap-3">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 space-y-1">
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                      <Icon className="size-3.5" /> {SOURCE_TYPE_LABEL[monitor.sourceType]}
                    </p>
                    <h2 className="truncate text-lg font-bold group-hover:text-primary">{monitor.name}</h2>
                  </div>
                  <MonitorStatusBadge monitor={monitor} className="shrink-0" />
                </div>
                <p className="truncate text-xs text-muted-foreground" title={monitor.url}>
                  {monitor.url}
                </p>
                <div className="flex flex-wrap gap-1.5">
                  {monitor.tags.map((tag) => (
                    <span
                      key={tag.name}
                      className={cn('rounded-full border px-2 py-0.5 text-[11px] font-medium', tagTone(monitor.tags, tag.name).chip)}
                    >
                      {tag.name}
                      {monitor.stats ? ` · ${monitor.stats.byLabel[tag.name] ?? 0}` : null}
                    </span>
                  ))}
                </div>
              </Link>
              <div className="flex items-center justify-between border-t border-white/10 pt-3">
                <span className="text-sm text-muted-foreground">
                  <span className="font-semibold text-foreground">{monitor.stats?.total ?? 0}</span> mensagens
                </span>
                <Button
                  size="sm"
                  variant={monitor.status === 'running' ? 'secondary' : 'default'}
                  onClick={() => void toggle(monitor)}
                  disabled={busyId === monitor.id || (monitor.status === 'paused' && !canStart)}
                  title={!canStart ? 'Adicione ao menos 2 tags para iniciar' : undefined}
                >
                  {monitor.status === 'running' ? <Pause className="size-4" /> : <Play className="size-4" />}
                  {monitor.status === 'running' ? 'Pausar' : 'Iniciar'}
                </Button>
              </div>
            </div>
          );
        })}
      </div>

      <CreateMonitorDialog
        open={isCreating}
        onOpenChange={setIsCreating}
        onCreated={(monitor) => {
          setIsCreating(false);
          router.push(`/monitors/${monitor.id}`);
        }}
      />
    </div>
  );
}
