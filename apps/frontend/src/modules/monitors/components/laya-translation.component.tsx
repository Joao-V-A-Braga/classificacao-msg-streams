import { Languages } from 'lucide-react';
import { cn } from '@/shared/lib/class-name.util';

/** A versão em inglês que o Laya classificou, discreta, abaixo da mensagem original. */
export function LayaTranslation({ text, className }: { text: string | null; className?: string }) {
  if (!text) return null;

  return (
    <p
      className={cn('flex items-start gap-1 text-xs italic leading-snug text-muted-foreground/70', className)}
      title="Tradução para o inglês usada pelo Laya na classificação"
    >
      <Languages className="mt-0.5 size-3 shrink-0" aria-hidden />
      <span className="break-words">{text}</span>
    </p>
  );
}
