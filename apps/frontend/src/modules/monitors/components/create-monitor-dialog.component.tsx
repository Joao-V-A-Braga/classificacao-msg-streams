'use client';

import { useState, type FormEvent } from 'react';
import { MessageSquareText, Radio } from 'lucide-react';
import { Button } from '@/shared/components/ui/button';
import { Dialog } from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { StandardDialogContent } from '@/shared/components/ui/standard-dialog-content';
import { cn } from '@/shared/lib/class-name.util';
import { useAuthContext } from '@/modules/auth';
import { monitorsApi, notifyRequestError } from '../lib/monitors-api.util';
import { DEFAULT_TAGS, SOURCE_TYPE_LABEL, URL_PLACEHOLDER } from '../lib/monitor-display.util';
import type { Monitor, MonitorSourceType, MonitorTag } from '../types/monitor.type';
import { ExistingCommentsOption } from './existing-comments-option.component';
import { TranslationOption } from './translation-option.component';
import { TagListEditor } from './tag-list-editor.component';

type CreateMonitorDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated: (monitor: Monitor) => void;
};

const SOURCE_OPTIONS: Array<{ value: MonitorSourceType; icon: typeof Radio; hint: string }> = [
  { value: 'youtube_live', icon: Radio, hint: 'Chat da live em tempo real' },
  { value: 'instagram_post', icon: MessageSquareText, hint: 'Comentários novos do post' },
];

/** Novo monitoramento: nasce pausado, com as tags que o Laya vai usar para classificar. */
export function CreateMonitorDialog({ open, onOpenChange, onCreated }: CreateMonitorDialogProps) {
  const { token } = useAuthContext();
  const [name, setName] = useState('');
  const [sourceType, setSourceType] = useState<MonitorSourceType>('youtube_live');
  const [url, setUrl] = useState('');
  const [tags, setTags] = useState<MonitorTag[]>(DEFAULT_TAGS);
  const [includeExistingComments, setIncludeExistingComments] = useState(false);
  const [translateForLaya, setTranslateForLaya] = useState(true);
  const [isSaving, setIsSaving] = useState(false);

  function reset() {
    setName('');
    setSourceType('youtube_live');
    setUrl('');
    setTags(DEFAULT_TAGS);
    setIncludeExistingComments(false);
    setTranslateForLaya(true);
  }

  function handleOpenChange(next: boolean) {
    if (!next && !isSaving) reset();
    onOpenChange(next);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token) return;

    setIsSaving(true);
    try {
      const monitor = await monitorsApi.create(token, {
        name: name.trim(),
        sourceType,
        url: url.trim(),
        tags,
        includeExistingComments: sourceType === 'instagram_post' && includeExistingComments,
        translateForLaya,
      });
      reset();
      onCreated(monitor);
    } catch (error) {
      notifyRequestError(error);
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <StandardDialogContent
        title="Novo monitoramento"
        description="Cada monitoramento é uma pipeline separada: uma live ou um post, com as suas próprias tags."
        className="max-w-2xl"
        bodyClassName="max-h-[75vh] overflow-y-auto"
      >
        <form id="create-monitor-form" onSubmit={handleSubmit} className="space-y-5">
          <div className="space-y-2">
            <Label htmlFor="monitor-name">Nome</Label>
            <Input
              id="monitor-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="Ex.: Live de lançamento do curso"
              maxLength={120}
              required
              autoFocus
            />
          </div>

          <div className="space-y-2">
            <Label>O que monitorar</Label>
            <div className="grid gap-2 sm:grid-cols-2">
              {SOURCE_OPTIONS.map(({ value, icon: Icon, hint }) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setSourceType(value)}
                  className={cn(
                    'flex items-start gap-3 rounded-lg border p-3 text-left transition',
                    sourceType === value
                      ? 'border-primary/70 bg-primary/10'
                      : 'border-white/10 bg-white/[0.02] hover:border-white/25',
                  )}
                  aria-pressed={sourceType === value}
                >
                  <Icon className={cn('mt-0.5 size-5', sourceType === value ? 'text-primary' : 'text-muted-foreground')} />
                  <span>
                    <span className="block text-sm font-semibold">{SOURCE_TYPE_LABEL[value]}</span>
                    <span className="block text-xs text-muted-foreground">{hint}</span>
                  </span>
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="monitor-url">URL</Label>
            <Input
              id="monitor-url"
              type="url"
              value={url}
              onChange={(event) => setUrl(event.target.value)}
              placeholder={URL_PLACEHOLDER[sourceType]}
              required
            />
          </div>

          {sourceType === 'instagram_post' ? (
            <ExistingCommentsOption checked={includeExistingComments} onChange={setIncludeExistingComments} />
          ) : null}

          <TranslationOption checked={translateForLaya} onChange={setTranslateForLaya} />
        </form>

        <div className="space-y-2">
          <Label>Tags de classificação</Label>
          <p className="text-xs text-muted-foreground">
            O Laya escolhe uma destas tags para cada mensagem. Dá para mudar depois, com o monitoramento pausado.
          </p>
          <TagListEditor
            tags={tags}
            onAdd={(tag) => {
              setTags((current) => [...current, tag]);
              return true;
            }}
            onRemove={(tag) => setTags((current) => current.filter((item) => item.name !== tag.name))}
            busy={isSaving}
          />
        </div>

        <div className="flex justify-end gap-2 border-t border-white/10 pt-4">
          <Button type="button" variant="secondary" onClick={() => handleOpenChange(false)} disabled={isSaving}>
            Cancelar
          </Button>
          <Button type="submit" form="create-monitor-form" disabled={isSaving}>
            {isSaving ? 'Criando...' : 'Criar monitoramento'}
          </Button>
        </div>
      </StandardDialogContent>
    </Dialog>
  );
}
