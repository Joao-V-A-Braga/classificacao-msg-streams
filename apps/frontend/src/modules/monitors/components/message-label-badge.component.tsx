'use client';

import { useState } from 'react';
import { LoaderCircle } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/shared/components/ui/tooltip';
import { cn } from '@/shared/lib/class-name.util';
import { describeProbabilities, formatProbability, tagTone } from '../lib/monitor-display.util';
import type { MonitorMessage, MonitorTag } from '../types/monitor.type';

type MessageLabelBadgeProps = {
  message: MonitorMessage;
  tags: MonitorTag[];
  className?: string;
};

/** Tag escolhida pelo Laya com a probabilidade; passando o mouse, a probabilidade de todas as tags. */
export function MessageLabelBadge({ message, tags, className }: MessageLabelBadgeProps) {
  // Sem rótulo há pouco tempo = o Laya ainda está classificando; há mais tempo = a classificação falhou.
  const [renderedAt] = useState(() => Date.now());

  if (!message.label) {
    const pending = !message.classifiedAt && renderedAt - new Date(message.receivedAt).getTime() < 60_000;
    return (
      <span
        className={cn(
          'inline-flex items-center gap-1 rounded-full border border-white/10 px-2 py-0.5 text-[11px] text-muted-foreground',
          className,
        )}
      >
        {pending ? <LoaderCircle className="size-3 animate-spin" /> : null}
        {pending ? 'classificando…' : 'não classificada'}
      </span>
    );
  }

  const probability = message.labelProbabilities?.[message.label];
  const badge = (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold',
        tagTone(tags, message.label).chip,
        className,
      )}
    >
      {message.label}
      {probability !== undefined ? <span className="font-normal opacity-80">{formatProbability(probability)}</span> : null}
    </span>
  );

  if (!message.labelProbabilities) return badge;

  return (
    <Tooltip>
      <TooltipTrigger asChild>{badge}</TooltipTrigger>
      <TooltipContent>{describeProbabilities(message.labelProbabilities)}</TooltipContent>
    </Tooltip>
  );
}
