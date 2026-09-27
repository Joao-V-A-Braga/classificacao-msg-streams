export type MonitorSourceType = 'youtube_live' | 'instagram_post';

export type MonitorStatus = 'paused' | 'running';

/** What the ingestion runner reports while the monitor runs. */
export type MonitorRunnerState = 'starting' | 'watching' | 'error';

export type MonitorTag = {
  name: string;
  description: string | null;
};

export type MonitorStats = {
  total: number;
  unclassified: number;
  byLabel: Record<string, number>;
};

export type Monitor = {
  id: string;
  name: string;
  sourceType: MonitorSourceType;
  url: string;
  status: MonitorStatus;
  tags: MonitorTag[];
  startedAt: string | null;
  /** Só post do Instagram: ao iniciar, classifica também os comentários que já existiam. */
  includeExistingComments: boolean;
  /** Traduzir cada mensagem para o inglês antes do Laya (padrão: sim). */
  translateForLaya: boolean;
  runnerState: MonitorRunnerState | null;
  runnerDetail: string | null;
  createdAt: string;
  updatedAt: string;
  stats?: MonitorStats;
};

export type MonitorMessage = {
  id: string;
  monitorId: string;
  externalId: string | null;
  authorName: string;
  text: string;
  receivedAt: string;
  label: string | null;
  labelProbabilities: Record<string, number> | null;
  /** Versão em inglês que o Laya classificou (traduzida pelo modelo de texto); null = o original. */
  translatedText: string | null;
  classifiedAt: string | null;
};

export type PageResponse<T> = {
  items: T[];
  page: number;
  perPage: number;
  total: number;
};

/** Events pushed by the realtime hub (apps/realtime), published by the backend. */
export type RealtimeEvent =
  | { event: 'connected'; data: { userId: string } }
  | { event: 'pong'; data: null }
  | { event: 'monitor.updated'; data: { monitor: Monitor } }
  | { event: 'monitor.deleted'; data: { monitorId: string } }
  | { event: 'message.created'; data: { message: MonitorMessage } }
  | { event: 'message.classified'; data: { message: MonitorMessage } };
