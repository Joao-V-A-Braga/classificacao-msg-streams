import { notifyApiErrorBody } from '@/shared/util/api-error.util';
import type { Monitor, MonitorMessage, MonitorSourceType, MonitorTag, PageResponse } from '../types/monitor.type';

const API_URL = process.env.NEXT_PUBLIC_API_URL;

/** Erro HTTP do backend, com o corpo já lido (para traduzir os códigos i18n). */
export class ApiRequestError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: unknown,
  ) {
    super(`HTTP ${status}`);
  }
}

async function request<T>(token: string, path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Authorization: `Bearer ${token}`,
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      ...init.headers,
    },
  });

  if (!response.ok) {
    let body: unknown = null;
    try {
      body = await response.json();
    } catch {
      body = null;
    }
    throw new ApiRequestError(response.status, body);
  }

  return (response.status === 204 ? null : await response.json()) as T;
}

/** Mostra o erro de uma chamada ao usuário (toasts traduzidos). */
export function notifyRequestError(error: unknown): void {
  notifyApiErrorBody(error instanceof ApiRequestError ? error.body : null);
}

export type CreateMonitorInput = {
  name: string;
  sourceType: MonitorSourceType;
  url: string;
  tags: MonitorTag[];
  includeExistingComments: boolean;
  translateForLaya: boolean;
};

export type IngestionStatus = { online: boolean; lastSeenAt: string | null };

export const monitorsApi = {
  ingestionStatus: (token: string) => request<IngestionStatus>(token, '/ingestion/status'),

  list: (token: string) => request<PageResponse<Monitor>>(token, '/monitors?perPage=100'),

  get: async (token: string, id: string) =>
    (await request<{ monitor: Monitor }>(token, `/monitors/${encodeURIComponent(id)}`)).monitor,

  create: async (token: string, input: CreateMonitorInput) =>
    (await request<{ monitor: Monitor }>(token, '/monitors', { method: 'POST', body: JSON.stringify(input) })).monitor,

  remove: (token: string, id: string) =>
    request<null>(token, `/monitors/${encodeURIComponent(id)}`, { method: 'DELETE' }),

  configure: async (token: string, id: string, settings: { includeExistingComments?: boolean; translateForLaya?: boolean }) =>
    (
      await request<{ monitor: Monitor }>(token, `/monitors/${encodeURIComponent(id)}/settings`, {
        method: 'PATCH',
        body: JSON.stringify(settings),
      })
    ).monitor,

  start: async (token: string, id: string) =>
    (await request<{ monitor: Monitor }>(token, `/monitors/${encodeURIComponent(id)}/start`, { method: 'POST' })).monitor,

  pause: async (token: string, id: string) =>
    (await request<{ monitor: Monitor }>(token, `/monitors/${encodeURIComponent(id)}/pause`, { method: 'POST' })).monitor,

  addTag: async (token: string, id: string, tag: MonitorTag) =>
    (
      await request<{ monitor: Monitor }>(token, `/monitors/${encodeURIComponent(id)}/tags`, {
        method: 'POST',
        body: JSON.stringify(tag),
      })
    ).monitor,

  removeTag: async (token: string, id: string, name: string) =>
    (
      await request<{ monitor: Monitor }>(
        token,
        `/monitors/${encodeURIComponent(id)}/tags/${encodeURIComponent(name)}`,
        { method: 'DELETE' },
      )
    ).monitor,

  /** `labels`: mensagens de qualquer uma destas tags (vazio = todas). */
  messages: (token: string, id: string, params: { page?: number; perPage?: number; labels?: string[] } = {}) => {
    const query = new URLSearchParams({
      page: String(params.page ?? 1),
      perPage: String(params.perPage ?? 50),
    });
    for (const label of params.labels ?? []) query.append('label[]', label);
    return request<PageResponse<MonitorMessage>>(token, `/monitors/${encodeURIComponent(id)}/messages?${query}`);
  },
};
