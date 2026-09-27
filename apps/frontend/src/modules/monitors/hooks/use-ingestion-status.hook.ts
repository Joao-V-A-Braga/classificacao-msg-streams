'use client';

import { useEffect, useState } from 'react';
import { useAuthContext } from '@/modules/auth';
import { monitorsApi } from '../lib/monitors-api.util';

const POLL_MS = 10_000;

/**
 * Se o serviço de ingestão (apps/ingestion) está consultando o backend. Sem ele, um monitoramento
 * iniciado fica esperando para sempre — a tela usa isto para avisar. `null` enquanto não sabe.
 * Só consulta quando `enabled` (há algum monitoramento rodando).
 */
export function useIngestionOnline(enabled: boolean): boolean | null {
  const { token } = useAuthContext();
  const [online, setOnline] = useState<boolean | null>(null);

  useEffect(() => {
    if (!token || !enabled) return;
    let cancelled = false;

    const check = () =>
      monitorsApi
        .ingestionStatus(token)
        .then((status) => {
          if (!cancelled) setOnline(status.online);
        })
        .catch(() => undefined);

    void check();
    const timer = setInterval(check, POLL_MS);
    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [token, enabled]);

  return enabled ? online : null;
}
