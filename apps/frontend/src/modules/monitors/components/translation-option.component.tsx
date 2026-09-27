'use client';

import { useId } from 'react';
import { Checkbox } from '@/shared/components/ui/checkbox';
import { cn } from '@/shared/lib/class-name.util';

type TranslationOptionProps = {
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
  className?: string;
};

/** Traduzir cada mensagem para o inglês antes do Laya classificar (pode mudar a qualquer momento). */
export function TranslationOption({ checked, onChange, disabled, className }: TranslationOptionProps) {
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
        <span className="block text-sm font-medium">Traduzir para o inglês antes do Laya</span>
        <span className="block text-xs text-muted-foreground">
          O Laya é treinado em inglês e acerta bem mais com a tradução. Desligue se o modelo de tradução estiver
          lento: vale a partir da próxima mensagem, sem precisar pausar.
        </span>
      </label>
    </div>
  );
}
