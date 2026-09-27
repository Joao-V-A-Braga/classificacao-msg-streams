'use client';

import { useEffect, useRef, useState } from 'react';
import { useAuthContext } from '@/modules/auth';
import type { RealtimeEvent } from '../types/monitor.type';

const REALTIME_URL = process.env.NEXT_PUBLIC_REALTIME_URL ?? 'ws://localhost:8200';
const KEEP_ALIVE_MS = 25_000;
const MAX_RETRY_MS = 10_000;

export type RealtimeStatus = 'connecting' | 'open' | 'closed';

/**
 * WebSocket com o hub de tempo real (apps/realtime), autenticado pelo JWT do usuário. Reconecta
 * sozinho com backoff; `onReconnect` avisa quando uma conexão volta, para a tela recarregar o que
 * pode ter perdido enquanto estava desconectada.
 */
export function useRealtime(
  onEvent: (event: RealtimeEvent) => void,
  options: { onReconnect?: () => void } = {},
): RealtimeStatus {
  const { token } = useAuthContext();
  const [status, setStatus] = useState<RealtimeStatus>('connecting');
  const onEventRef = useRef(onEvent);
  const onReconnectRef = useRef(options.onReconnect);

  useEffect(() => {
    onEventRef.current = onEvent;
    onReconnectRef.current = options.onReconnect;
  });

  useEffect(() => {
    if (!token) return;

    let socket: WebSocket | null = null;
    let retryTimer: ReturnType<typeof setTimeout> | undefined;
    let keepAlive: ReturnType<typeof setInterval> | undefined;
    let attempts = 0;
    let hasConnectedBefore = false;
    let disposed = false;

    const connect = () => {
      setStatus('connecting');
      socket = new WebSocket(`${REALTIME_URL}/ws?token=${encodeURIComponent(token)}`);

      socket.onopen = () => {
        attempts = 0;
        setStatus('open');
        if (hasConnectedBefore) onReconnectRef.current?.();
        hasConnectedBefore = true;
        keepAlive = setInterval(() => socket?.send(JSON.stringify({ type: 'ping' })), KEEP_ALIVE_MS);
      };

      socket.onmessage = (message) => {
        try {
          onEventRef.current(JSON.parse(String(message.data)) as RealtimeEvent);
        } catch {
          // Mensagem malformada: ignora.
        }
      };

      socket.onclose = () => {
        clearInterval(keepAlive);
        if (disposed) return;
        setStatus('closed');
        attempts += 1;
        retryTimer = setTimeout(connect, Math.min(MAX_RETRY_MS, 500 * 2 ** attempts));
      };
    };

    connect();

    return () => {
      disposed = true;
      clearTimeout(retryTimer);
      clearInterval(keepAlive);
      socket?.close();
    };
  }, [token]);

  return status;
}
