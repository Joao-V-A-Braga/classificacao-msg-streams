'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { AlertTriangle, ArrowLeft, Check, ExternalLink, History, Languages, Lock, Pause, Play, Tags, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/shared/components/ui/button';
import { DeleteConfirmationDialog } from '@/shared/components/ui/delete-confirmation-dialog';
import { cn } from '@/shared/lib/class-name.util';
import { useAuthContext } from '@/modules/auth';
import {
  ExistingCommentsOption,
  TranslationOption,
  IngestionOfflineAlert,
  LiveChatStream,
  useIngestionOnline,
  MIN_TAGS_TO_START,
  MonitorStatusBadge,
  PostCommentsTable,
  countMessages,
  SOURCE_TYPE_LABEL,
  TagListEditor,
  formatDateTime,
  monitorsApi,
  notifyRequestError,
  tagTone,
  useRealtime,
  type Monitor,
  type MonitorMessage,
  type MonitorStats,
  type MonitorTag,
  type RealtimeEvent,
} from '@/modules/monitors';

const LIVE_HISTORY = 200;
const LIVE_MAX_IN_MEMORY = 1000;
const POSTS_PER_PAGE = 20;

function withNewMessage(stats: MonitorStats | undefined): MonitorStats | undefined {
  return stats ? { ...stats, total: stats.total + 1, unclassified: stats.unclassified + 1 } : stats;
}

function withClassifiedMessage(stats: MonitorStats | undefined, label: string): MonitorStats | undefined {
  if (!stats) return stats;
  return {
    ...stats,
    unclassified: Math.max(0, stats.unclassified - 1),
    byLabel: { ...stats.byLabel, [label]: (stats.byLabel[label] ?? 0) + 1 },
  };
}

export default function MonitorDetailPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const { token } = useAuthContext();

  const [monitor, setMonitor] = useState<Monitor | null>(null);
  const [messages, setMessages] = useState<MonitorMessage[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  // Tags selecionadas no filtro (vazio = todas): cada chip liga/desliga a sua.
  const [labelFilters, setLabelFilters] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isBusy, setIsBusy] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [tagToRemove, setTagToRemove] = useState<MonitorTag | null>(null);

  const isLive = monitor?.sourceType === 'youtube_live';
  const viewRef = useRef({ isLive, page, labelFilters });
  useEffect(() => {
    viewRef.current = { isLive, page, labelFilters };
  });

  // Incrementado quando o WebSocket reconecta: recarrega o que pode ter chegado enquanto estava fora.
  const [reloadKey, setReloadKey] = useState(0);
  const sourceType = monitor?.sourceType;
  const postQuery = sourceType === 'youtube_live' ? '' : JSON.stringify([page, labelFilters]);

  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    monitorsApi
      .get(token, id)
      .then((loaded) => {
        if (!cancelled) setMonitor(loaded);
      })
      .catch((error: unknown) => {
        if (cancelled) return;
        notifyRequestError(error);
        router.replace('/monitors');
      });
    return () => {
      cancelled = true;
    };
  }, [token, id, router, reloadKey]);

  useEffect(() => {
    if (!token || !sourceType) return;
    let cancelled = false;
    // Live: histórico recente em ordem cronológica, filtro por tag local. Post: página filtrada no backend.
    const request =
      sourceType === 'youtube_live'
        ? monitorsApi.messages(token, id, { perPage: LIVE_HISTORY }).then((result) => ({
            ...result,
            items: [...result.items].reverse(),
          }))
        : monitorsApi.messages(token, id, { page, perPage: POSTS_PER_PAGE, labels: labelFilters });

    request
      .then((result) => {
        if (cancelled) return;
        setMessages(result.items);
        setTotal(result.total);
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
    // O filtro da live é local: só a paginação/filtro dos posts buscam de novo.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token, id, sourceType, reloadKey, postQuery]);

  const handleEvent = useCallback(
    (event: RealtimeEvent) => {
      const view = viewRef.current;

      if (event.event === 'monitor.updated' && event.data.monitor.id === id) {
        const updated = event.data.monitor;
        setMonitor(updated);
        // Tag removida = mensagens dela excluídas no backend: tira da tela também (vale para outras abas).
        const tagNames = updated.tags.map((tag) => tag.name);
        setMessages((current) => current.filter((message) => !message.label || tagNames.includes(message.label)));
        setLabelFilters((current) => current.filter((label) => tagNames.includes(label)));
        return;
      }

      if (event.event === 'monitor.deleted' && event.data.monitorId === id) {
        toast.info('Este monitoramento foi excluído.');
        router.replace('/monitors');
        return;
      }

      if (event.event !== 'message.created' && event.event !== 'message.classified') return;
      const message = event.data.message;
      if (message.monitorId !== id) return;

      if (event.event === 'message.created') {
        setMonitor((current) => (current ? { ...current, stats: withNewMessage(current.stats) } : current));
      } else if (message.label) {
        const label = message.label;
        setMonitor((current) => (current ? { ...current, stats: withClassifiedMessage(current.stats, label) } : current));
      }

      if (view.isLive) {
        setMessages((current) => {
          const index = current.findIndex((item) => item.id === message.id);
          if (index >= 0) return current.map((item) => (item.id === message.id ? message : item));
          const next = [...current, message];
          return next.length > LIVE_MAX_IN_MEMORY ? next.slice(-LIVE_MAX_IN_MEMORY) : next;
        });
        if (event.event === 'message.created') setTotal((count) => count + 1);
        return;
      }

      // Posts: tabela paginada, mais novos primeiro — novos entram no topo da página 1.
      setMessages((current) => {
        if (current.some((item) => item.id === message.id)) {
          return current.map((item) => (item.id === message.id ? message : item));
        }
        const matchesFilter = view.labelFilters.length === 0 || (!!message.label && view.labelFilters.includes(message.label));
        if (view.page !== 1 || !matchesFilter) return current;
        return [message, ...current].slice(0, POSTS_PER_PAGE);
      });
      if (event.event === 'message.created' && view.labelFilters.length === 0) setTotal((count) => count + 1);
    },
    [id, router],
  );

  const ingestionOnline = useIngestionOnline(monitor?.status === 'running');

  const realtimeStatus = useRealtime(handleEvent, { onReconnect: () => setReloadKey((key) => key + 1) });

  function changePage(next: number) {
    setIsLoading(true);
    setPage(next);
  }

  function changeFilters(labels: string[]) {
    if (!isLive) setIsLoading(true);
    setLabelFilters(labels);
    setPage(1);
  }

  /** Liga/desliga uma tag no filtro, mantendo as outras selecionadas. */
  function toggleFilter(label: string) {
    changeFilters(labelFilters.includes(label) ? labelFilters.filter((item) => item !== label) : [...labelFilters, label]);
  }

  const visibleMessages = useMemo(
    () =>
      isLive && labelFilters.length > 0
        ? messages.filter((message) => message.label !== null && labelFilters.includes(message.label))
        : messages,
    [isLive, labelFilters, messages],
  );

  // Rótulos de tags já removidas continuam nos filtros enquanto houver mensagens com eles.
  const filterLabels = useMemo(() => {
    if (!monitor) return [];
    const names = monitor.tags.map((tag) => tag.name);
    const historic = Object.keys(monitor.stats?.byLabel ?? {}).filter((label) => !names.includes(label));
    return [...names, ...historic];
  }, [monitor]);

  async function run(action: () => Promise<Monitor>): Promise<boolean> {
    setIsBusy(true);
    try {
      setMonitor(await action());
      return true;
    } catch (error) {
      notifyRequestError(error);
      return false;
    } finally {
      setIsBusy(false);
    }
  }

  async function removeTag(tag: MonitorTag) {
    if (!token || !monitor) return;
    const deleted = monitor.stats?.byLabel[tag.name] ?? 0;
    if (!(await run(() => monitorsApi.removeTag(token, monitor.id, tag.name)))) return;

    setMessages((current) => current.filter((message) => message.label !== tag.name));
    if (labelFilters.includes(tag.name)) changeFilters(labelFilters.filter((label) => label !== tag.name));
    if (!isLive) setReloadKey((key) => key + 1);
    toast.success(
      deleted > 0 ? `Tag "${tag.name}" removida com ${countMessages(deleted)}.` : `Tag "${tag.name}" removida.`,
    );
  }

  async function handleDelete() {
    if (!token || !monitor) return;
    setIsDeleting(true);
    try {
      await monitorsApi.remove(token, monitor.id);
      toast.success('Monitoramento excluído.');
      router.replace('/monitors');
    } catch (error) {
      notifyRequestError(error);
      setIsDeleting(false);
    }
  }

  if (!monitor || !token) {
    return <div className="py-20 text-center text-sm text-muted-foreground">Carregando monitoramento...</div>;
  }

  const isRunning = monitor.status === 'running';
  const canStart = monitor.tags.length >= MIN_TAGS_TO_START;

  return (
    <div className="flex flex-col gap-6">
      <div className="space-y-3">
        <Link href="/monitors" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-4" /> Monitoramentos
        </Link>

        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="min-w-0 space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="truncate text-2xl font-black tracking-tight">{monitor.name}</h1>
              <MonitorStatusBadge monitor={monitor} />
            </div>
            <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
              <span>{SOURCE_TYPE_LABEL[monitor.sourceType]}</span>
              <a
                href={monitor.url}
                target="_blank"
                rel="noreferrer"
                className="inline-flex max-w-md items-center gap-1 truncate hover:text-foreground"
              >
                <span className="truncate">{monitor.url}</span> <ExternalLink className="size-3.5 shrink-0" />
              </a>
              {monitor.startedAt ? <span>Último início: {formatDateTime(monitor.startedAt)}</span> : null}
            </p>
          </div>

          <div className="flex shrink-0 items-center gap-2">
            {isRunning ? (
              <Button variant="secondary" disabled={isBusy} onClick={() => void run(() => monitorsApi.pause(token, monitor.id))}>
                <Pause className="size-4" /> Pausar
              </Button>
            ) : (
              <Button
                disabled={isBusy || !canStart}
                title={!canStart ? 'Adicione ao menos 2 tags para iniciar' : undefined}
                onClick={() => void run(() => monitorsApi.start(token, monitor.id))}
              >
                <Play className="size-4" /> Iniciar
              </Button>
            )}
            <Button variant="outline" onClick={() => setIsDeleteOpen(true)} aria-label="Excluir monitoramento">
              <Trash2 className="size-4" /> Excluir
            </Button>
          </div>
        </div>

        {isRunning && ingestionOnline === false ? (
          <IngestionOfflineAlert />
        ) : isRunning && monitor.runnerState === 'error' ? (
          <div className="flex items-start gap-2 rounded-lg border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-100">
            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
            <span>{monitor.runnerDetail ?? 'O serviço de ingestão encontrou um erro.'}</span>
          </div>
        ) : isRunning ? (
          <p className="text-sm text-muted-foreground">
            {monitor.runnerDetail ??
              'Aguardando o serviço de ingestão abrir a página no Chrome (ele precisa estar rodando: .\\start.ps1).'}
          </p>
        ) : monitor.runnerDetail ? (
          // Pausado pelo serviço de ingestão (a live/post sumiu): mostra o motivo até o próximo início.
          <div className="flex items-start gap-2 rounded-lg border border-white/10 bg-white/5 p-3 text-sm text-zinc-200">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-300" />
            <span>{monitor.runnerDetail}</span>
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">
            Pausado. Ao iniciar, o chat/post é observado de novo e só as mensagens que chegarem a partir de então são
            classificadas.
          </p>
        )}
      </div>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div className="min-w-0 space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <FilterChip active={labelFilters.length === 0} onClick={() => changeFilters([])}>
              Todas · {monitor.stats?.total ?? total}
            </FilterChip>
            {filterLabels.map((label) => (
              <FilterChip
                key={label}
                active={labelFilters.includes(label)}
                onClick={() => toggleFilter(label)}
                dotClassName={tagTone(monitor.tags, label).dot}
                toggle
              >
                {label} · {monitor.stats?.byLabel[label] ?? 0}
              </FilterChip>
            ))}
            {labelFilters.length > 1 ? (
              <span className="text-xs text-muted-foreground">
                {labelFilters.length} tags ·{' '}
                {labelFilters.reduce((sum, label) => sum + (monitor.stats?.byLabel[label] ?? 0), 0)} mensagens
              </span>
            ) : null}
            <span
              className={cn(
                'ml-auto inline-flex items-center gap-1.5 text-xs',
                realtimeStatus === 'open' ? 'text-emerald-300' : 'text-amber-300',
              )}
              title="Conexão WebSocket com o servidor de tempo real"
            >
              <span className={cn('size-1.5 rounded-full', realtimeStatus === 'open' ? 'bg-emerald-400' : 'bg-amber-400')} />
              {realtimeStatus === 'open' ? 'Tempo real conectado' : 'Reconectando tempo real...'}
            </span>
          </div>

          {isLive ? (
            <LiveChatStream
              messages={visibleMessages}
              tags={monitor.tags}
              startedAt={isRunning ? monitor.startedAt : null}
              isRunning={isRunning}
              isLoading={isLoading}
            />
          ) : (
            <PostCommentsTable
              messages={messages}
              tags={monitor.tags}
              page={page}
              total={total}
              perPage={POSTS_PER_PAGE}
              isLoading={isLoading}
              onPageChange={changePage}
            />
          )}
        </div>

        <aside className="space-y-4">
          <section className="space-y-3 rounded-lg border border-border bg-card p-4">
            <h2 className="flex items-center gap-2 text-sm font-semibold">
              <Tags className="size-4 text-primary" /> Tags de classificação
            </h2>
            <p className="text-xs text-muted-foreground">
              O Laya escolhe uma destas tags para cada mensagem nova. Remover uma tag também exclui as mensagens
              classificadas com ela.
            </p>
            <TagListEditor
              tags={monitor.tags}
              counts={monitor.stats?.byLabel}
              locked={isRunning}
              busy={isBusy}
              onAdd={(tag: MonitorTag) => run(() => monitorsApi.addTag(token, monitor.id, tag))}
              onRemove={(tag: MonitorTag) => {
                if ((monitor.stats?.byLabel[tag.name] ?? 0) > 0) setTagToRemove(tag);
                else void removeTag(tag);
              }}
            />
          </section>

          <section className="space-y-3 rounded-lg border border-border bg-card p-4">
            <h2 className="flex items-center gap-2 text-sm font-semibold">
              <Languages className="size-4 text-primary" /> Tradução para o Laya
            </h2>
            <TranslationOption
              checked={monitor.translateForLaya}
              disabled={isBusy}
              onChange={(checked) =>
                void run(() => monitorsApi.configure(token, monitor.id, { translateForLaya: checked }))
              }
            />
          </section>

          {monitor.sourceType === 'instagram_post' ? (
            <section className="space-y-3 rounded-lg border border-border bg-card p-4">
              <h2 className="flex items-center gap-2 text-sm font-semibold">
                <History className="size-4 text-primary" /> Comentários existentes
              </h2>
              <ExistingCommentsOption
                checked={monitor.includeExistingComments}
                disabled={isRunning || isBusy}
                onChange={(checked) =>
                  void run(() => monitorsApi.configure(token, monitor.id, { includeExistingComments: checked }))
                }
              />
              {isRunning ? (
                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Lock className="size-3" /> Pause o monitoramento para mudar esta opção.
                </p>
              ) : null}
            </section>
          ) : null}

          {monitor.stats ? (
            <section className="space-y-3 rounded-lg border border-border bg-card p-4">
              <h2 className="text-sm font-semibold">Distribuição</h2>
              <div className="space-y-2">
                {filterLabels.map((label) => {
                  const count = monitor.stats?.byLabel[label] ?? 0;
                  const share = monitor.stats && monitor.stats.total > 0 ? count / monitor.stats.total : 0;
                  return (
                    <div key={label} className="space-y-1">
                      <div className="flex justify-between text-xs">
                        <span>{label}</span>
                        <span className="tabular-nums text-muted-foreground">
                          {count} · {Math.round(share * 100)}%
                        </span>
                      </div>
                      <div className="h-1.5 overflow-hidden rounded-full bg-white/5">
                        <div
                          className={cn('h-full rounded-full transition-all', tagTone(monitor.tags, label).dot)}
                          style={{ width: `${share * 100}%` }}
                        />
                      </div>
                    </div>
                  );
                })}
                {monitor.stats.unclassified > 0 ? (
                  <p className="text-xs text-muted-foreground">{monitor.stats.unclassified} sem classificação</p>
                ) : null}
              </div>
            </section>
          ) : null}
        </aside>
      </div>

      <DeleteConfirmationDialog
        open={tagToRemove !== null}
        onOpenChange={(open) => {
          if (!open) setTagToRemove(null);
        }}
        onConfirm={() => {
          if (tagToRemove) void removeTag(tagToRemove).then(() => setTagToRemove(null));
        }}
        isConfirming={isBusy}
        title="Remover tag"
        description={(() => {
          const count = monitor.stats?.byLabel[tagToRemove?.name ?? ''] ?? 0;
          return `Esta tag tem ${countMessages(count)}; ${count === 1 ? 'ela será excluída' : 'elas serão excluídas'} junto com a tag. Não dá para desfazer.`;
        })()}
        itemLabel="Tag"
        itemValue={tagToRemove?.name}
        confirmLabel="Remover tag e mensagens"
      />

      <DeleteConfirmationDialog
        open={isDeleteOpen}
        onOpenChange={setIsDeleteOpen}
        onConfirm={() => void handleDelete()}
        isConfirming={isDeleting}
        title="Excluir monitoramento"
        description={
          isRunning
            ? 'O monitoramento está rodando: ele será parado (a aba dele no Chrome é fechada) e depois excluído, junto com todas as mensagens que coletou. Não dá para desfazer.'
            : 'Remove o monitoramento e todas as mensagens que ele coletou. Não dá para desfazer.'
        }
        itemLabel="Monitoramento"
        itemValue={monitor.name}
      />
    </div>
  );
}

/** Chip do filtro. Com `toggle`, mostra um check quando a tag está selecionada (várias ao mesmo tempo). */
function FilterChip({
  active,
  onClick,
  dotClassName,
  toggle = false,
  children,
}: {
  active: boolean;
  onClick: () => void;
  dotClassName?: string;
  toggle?: boolean;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
        active ? 'border-primary/70 bg-primary/15 text-foreground' : 'border-white/10 text-muted-foreground hover:border-white/25',
      )}
      aria-pressed={active}
      title={toggle ? (active ? 'Clique para tirar do filtro' : 'Clique para mostrar também esta tag') : undefined}
    >
      {toggle && active ? (
        <Check className="size-3 text-primary" aria-hidden />
      ) : dotClassName ? (
        <span className={cn('size-2 rounded-full', dotClassName)} />
      ) : null}
      {children}
    </button>
  );
}
