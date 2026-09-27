'use client';

import { useId } from 'react';
import { Checkbox } from '@/shared/components/ui/checkbox';
import { cn } from '@/shared/lib/class-name.util';

type ExistingCommentsOptionProps = {
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
  className?: string;
};

/** Post do Instagram: ao iniciar, classificar também os comentários que o post já tinha. */
export function ExistingCommentsOption({ checked, onChange, disabled, className }: ExistingCommentsOptionProps) {
  const id = useId();

  return (
    <div className={cn('flex items-start gap-3', className)}>
      <Checkbox
        id={id}
        checked={checked}
        onCheckedChange={(value) => onChange(value === true)}
        disabled={disabled}
        className="mt-0.5"
      />
      <label htmlFor={id} className={cn('space-y-0.5', disabled ? 'cursor-not-allowed opacity-70' : 'cursor-pointer')}>
        <span className="block text-sm font-medium">Importar os comentários existentes</span>
        <span className="block text-xs text-muted-foreground">
          Ao iniciar, carrega os comentários que o post já tem e classifica todos, além dos novos. Iniciar de novo
          não duplica os que já foram importados. Respostas a comentários não entram.
        </span>
      </label>
    </div>
  );
}
