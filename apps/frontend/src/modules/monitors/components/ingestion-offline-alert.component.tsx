import { PlugZap } from 'lucide-react';

/**
 * Monitoramento rodando sem ninguém consultando o backend: o supervisor de ingestão (que liga e
 * desliga o serviço sozinho) não está no ar — só acontece se o start.ps1 não foi rodado.
 */
export function IngestionOfflineAlert() {
  return (
    <div className="flex items-start gap-2 rounded-lg border border-amber-400/30 bg-amber-500/10 p-3 text-sm text-amber-100">
      <PlugZap className="mt-0.5 size-4 shrink-0" />
      <span>
        O supervisor de ingestão não está no ar, então os monitoramentos iniciados não estão lendo nada. Rode{' '}
        <code className="rounded bg-black/30 px-1">.\start.ps1</code> uma vez: a partir daí a ingestão liga e desliga
        sozinha conforme você inicia e pausa os monitoramentos.
      </span>
    </div>
  );
}
