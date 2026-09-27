'use client';

import { useLayoutEffect, useMemo, useRef, useState } from 'react';
import { ArrowDown, MessagesSquare } from 'lucide-react';
import { cn } from '@/shared/lib/class-name.util';
import { countMessages, formatTime } from '../lib/monitor-display.util';
import type { MonitorMessage, MonitorTag } from '../types/monitor.type';
import { LayaTranslation } from './laya-translation.component';
import { MessageLabelBadge } from './message-label-badge.component';

type LiveChatStreamProps = {
  messages: MonitorMessage[];
  tags: MonitorTag[];
  /** Início da execução atual: marca no chat a partir de onde as mensagens são desta execução. */
  startedAt: string | null;
  isRunning: boolean;
  isLoading: boolean;
};

const AVATAR_TONES = ['bg-sky-600', 'bg-emerald-600', 'bg-rose-600', 'bg-amber-600', 'bg-violet-600', 'bg-teal-600'];

function avatarTone(author: string): string {
  let hash = 0;
  for (const char of author) hash = (hash * 31 + char.charCodeAt(0)) | 0;
  return AVATAR_TONES[Math.abs(hash) % AVATAR_TONES.length];
}

/**
 * Chat da live no formato de stream: mensagens em ordem cronológica, as novas entrando embaixo.
 * Rola sozinho enquanto o usuário está no fim; se ele subir para ler, aparece o aviso de novas.
 */
export function LiveChatStream({ messages, tags, startedAt, isRunning, isLoading }: LiveChatStreamProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [isAtBottom, setIsAtBottom] = useState(true);
  const [unseen, setUnseen] = useState(0);
  const previousCount = useRef(messages.length);

  const dividerIndex = useMemo(() => {
    if (!startedAt) return -1;
    const start = new Date(startedAt).getTime();
    const index = messages.findIndex((message) => new Date(message.receivedAt).getTime() >= start);
    return index > 0 ? index : -1;
  }, [messages, startedAt]);

  useLayoutEffect(() => {
    const added = messages.length - previousCount.current;
    previousCount.current = messages.length;
    const container = scrollRef.current;
    if (!container) return;

    if (isAtBottom) {
      container.scrollTop = container.scrollHeight;
    } else if (added > 0) {
      setUnseen((count) => count + added);
    }
  }, [messages, isAtBottom]);

  function handleScroll() {
    const container = scrollRef.current;
    if (!container) return;
    const atBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 40;
    setIsAtBottom(atBottom);
    if (atBottom) setUnseen(0);
  }

  function scrollToBottom() {
    const container = scrollRef.current;
    if (container) container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
  }

  return (
    <div className="relative flex h-[min(70vh,720px)] flex-col overflow-hidden rounded-lg border border-border bg-card">
      <div className="flex items-center justify-between border-b border-white/10 px-4 py-2.5">
        <span className="flex items-center gap-2 text-sm font-semibold">
          <MessagesSquare className="size-4 text-primary" /> Chat da live
        </span>
        <span className="text-xs text-muted-foreground">{countMessages(messages.length)}</span>
      </div>

      <div ref={scrollRef} onScroll={handleScroll} className="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
        {!isLoading && messages.length === 0 ? (
          <div className="flex h-full flex-col items-center justify-center gap-2 px-6 text-center text-sm text-muted-foreground">
            <MessagesSquare className="size-8 opacity-40" />
            {isRunning
              ? 'Aguardando mensagens novas no chat. As que já estavam lá quando você iniciou são ignoradas.'
              : 'Inicie o monitoramento para acompanhar o chat da live aqui, em tempo real.'}
          </div>
        ) : null}

        {messages.map((message, index) => (
          <div key={message.id}>
            {index === dividerIndex ? (
              <div className="my-3 flex items-center gap-3 px-2 text-[11px] uppercase tracking-wider text-primary/80">
                <span className="h-px flex-1 bg-primary/30" />
                Monitoramento iniciado às {formatTime(startedAt as string)}
                <span className="h-px flex-1 bg-primary/30" />
              </div>
            ) : null}
            <div className="group flex items-start gap-2.5 rounded-md px-2 py-1.5 transition-colors hover:bg-white/[0.04] chat-message-enter">
              <span
                className={cn(
                  'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white',
                  avatarTone(message.authorName),
                )}
                aria-hidden
              >
                {message.authorName.replace(/^@/, '').charAt(0).toUpperCase() || '?'}
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span className="text-[11px] tabular-nums text-muted-foreground/70">{formatTime(message.receivedAt)}</span>
                  <span className="text-sm font-semibold text-zinc-300">{message.authorName}</span>
                  <MessageLabelBadge message={message} tags={tags} />
                </div>
                <p className="break-words text-sm leading-relaxed text-foreground">{message.text}</p>
                <LayaTranslation text={message.translatedText} />
              </div>
            </div>
          </div>
        ))}
      </div>

      {!isAtBottom && unseen > 0 ? (
        <button
          type="button"
          onClick={scrollToBottom}
          className="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-lg"
        >
          <ArrowDown className="size-3.5" /> {unseen} {unseen === 1 ? 'mensagem nova' : 'mensagens novas'}
        </button>
      ) : null}
    </div>
  );
}
