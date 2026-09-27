'use client';

import { useState, type KeyboardEvent } from 'react';
import { Lock, Plus, X } from 'lucide-react';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/shared/components/ui/tooltip';
import { cn } from '@/shared/lib/class-name.util';
import { MAX_TAGS, TAG_DESCRIPTION_MAX_LENGTH, TAG_NAME_MAX_LENGTH, tagTone, validateNewTag } from '../lib/monitor-display.util';
import type { MonitorTag } from '../types/monitor.type';

type TagListEditorProps = {
  tags: MonitorTag[];
  onAdd: (tag: MonitorTag) => Promise<boolean> | boolean;
  onRemove: (tag: MonitorTag) => void;
  /** Com o monitoramento rodando as tags ficam travadas. */
  locked?: boolean;
  lockedMessage?: string;
  busy?: boolean;
  counts?: Record<string, number>;
};

/** Chips das tags + formulário para adicionar uma (nome e descrição opcional para o Laya). */
export function TagListEditor({ tags, onAdd, onRemove, locked, lockedMessage, busy, counts }: TagListEditorProps) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [error, setError] = useState<string | null>(null);

  function submitOnEnter(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Enter') {
      event.preventDefault();
      void handleSubmit();
    }
  }

  async function handleSubmit() {
    const problem = validateNewTag(tags, name, description);
    if (problem) {
      setError(problem);
      return;
    }
    const tag = { name: name.trim().replace(/\s+/g, ' '), description: description.trim() || null };
    if (await onAdd(tag)) {
      setName('');
      setDescription('');
      setError(null);
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-2">
        {tags.length === 0 ? <p className="text-sm text-muted-foreground">Nenhuma tag ainda.</p> : null}
        {tags.map((tag) => (
          <span
            key={tag.name}
            className={cn(
              'group inline-flex max-w-full items-center gap-1.5 rounded-full border py-1 pl-3 pr-1.5 text-sm',
              tagTone(tags, tag.name).chip,
            )}
            title={tag.description ?? undefined}
          >
            <span className="truncate font-semibold">{tag.name}</span>
            {counts ? <span className="text-xs opacity-75">{counts[tag.name] ?? 0}</span> : null}
            {locked ? (
              <Lock className="size-3 opacity-60" aria-label="Tag travada enquanto o monitoramento roda" />
            ) : (
              <button
                type="button"
                onClick={() => onRemove(tag)}
                disabled={busy}
                className="rounded-full p-0.5 opacity-70 transition hover:bg-black/30 hover:opacity-100 disabled:opacity-30"
                aria-label={`Remover a tag ${tag.name}`}
              >
                <X className="size-3.5" />
              </button>
            )}
          </span>
        ))}
      </div>

      {locked ? (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Lock className="size-3" /> {lockedMessage ?? 'Pause o monitoramento para editar as tags.'}
        </p>
      ) : (
        <div className="space-y-2">
          <div className="flex flex-col gap-2">
            <Input
              value={name}
              onChange={(event) => {
                setName(event.target.value);
                setError(null);
              }}
              placeholder="Nova tag (ex.: dúvida sobre preço)"
              maxLength={TAG_NAME_MAX_LENGTH}
              onKeyDown={submitOnEnter}
              disabled={busy || tags.length >= MAX_TAGS}
            />
            <Tooltip>
              <TooltipTrigger asChild>
                <Input
                  value={description}
                  onChange={(event) => setDescription(event.target.value)}
                  placeholder="Descrição para o Laya (opcional, em inglês funciona melhor)"
                  maxLength={TAG_DESCRIPTION_MAX_LENGTH}
                  onKeyDown={submitOnEnter}
                  disabled={busy || tags.length >= MAX_TAGS}
                />
              </TooltipTrigger>
              <TooltipContent className="max-w-xs">
                O Laya escolhe a tag pelo nome e por esta descrição. Ex.: &quot;a question about price or payment&quot;.
              </TooltipContent>
            </Tooltip>
            <Button
              type="button"
              variant="secondary"
              onClick={() => void handleSubmit()}
              disabled={busy || tags.length >= MAX_TAGS}
              className="self-start"
            >
              <Plus className="size-4" /> Adicionar
            </Button>
          </div>
          {error ? <p className="text-xs text-red-300">{error}</p> : null}
          <p className="text-xs text-muted-foreground">
            {tags.length}/{MAX_TAGS} tags · o Laya precisa de pelo menos 2 para classificar.
          </p>
        </div>
      )}
    </div>
  );
}
