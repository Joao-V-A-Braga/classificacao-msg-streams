import { AlertTriangle, LoaderCircle, Pause } from 'lucide-react';
import { cn } from '@/shared/lib/class-name.util';
import type { Monitor } from '../types/monitor.type';

type MonitorStatusBadgeProps = {
  monitor: Pick<Monitor, 'status' | 'runnerState'>;
  className?: string;
};

/** Pausado / abrindo no Chrome / ao vivo / com erro. */
export function MonitorStatusBadge({ monitor, className }: MonitorStatusBadgeProps) {
  const base = 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold';

  if (monitor.status === 'paused') {
    return (
      <span className={cn(base, 'border-white/15 bg-white/5 text-zinc-300', className)}>
        <Pause className="size-3" /> Pausado
      </span>
    );
  }

  if (monitor.runnerState === 'error') {
    return (
      <span className={cn(base, 'border-red-400/40 bg-red-500/15 text-red-200', className)}>
        <AlertTriangle className="size-3" /> Com erro
      </span>
    );
  }

  if (monitor.runnerState === 'watching') {
    return (
      <span className={cn(base, 'border-emerald-400/40 bg-emerald-500/15 text-emerald-200', className)}>
        <span className="relative flex size-2">
          <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
          <span className="relative inline-flex size-2 rounded-full bg-emerald-400" />
        </span>
        Monitorando
      </span>
    );
  }

  return (
    <span className={cn(base, 'border-amber-400/40 bg-amber-500/15 text-amber-200', className)}>
      <LoaderCircle className="size-3 animate-spin" /> Iniciando
    </span>
  );
}
