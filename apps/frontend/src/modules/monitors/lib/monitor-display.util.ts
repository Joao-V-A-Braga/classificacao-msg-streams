import type { MonitorSourceType, MonitorTag } from '../types/monitor.type';

export const SOURCE_TYPE_LABEL: Record<MonitorSourceType, string> = {
  youtube_live: 'Live do YouTube',
  instagram_post: 'Post do Instagram',
};

export const URL_PLACEHOLDER: Record<MonitorSourceType, string> = {
  youtube_live: 'https://www.youtube.com/watch?v=...',
  instagram_post: 'https://www.instagram.com/p/...',
};

export const MIN_TAGS_TO_START = 2;
export const MAX_TAGS = 10;
export const TAG_NAME_MAX_LENGTH = 40;
export const TAG_DESCRIPTION_MAX_LENGTH = 120;

/** Sugestão para novos monitoramentos (o Laya entende melhor descrições em inglês). */
export const DEFAULT_TAGS: MonitorTag[] = [
  { name: 'pergunta', description: 'a question asking for information' },
  { name: 'elogio', description: 'praise or a compliment' },
  { name: 'reclamação', description: 'a complaint or criticism' },
  { name: 'spam', description: 'spam, advertising, links or repeated promotional text' },
  { name: 'outro', description: 'anything else: greetings, casual chat, reactions' },
];

// Uma cor por posição da tag no monitoramento; rótulos de tags já removidas ficam neutros.
const TAG_TONES = [
  { chip: 'border-sky-400/40 bg-sky-400/15 text-sky-200', dot: 'bg-sky-400' },
  { chip: 'border-emerald-400/40 bg-emerald-400/15 text-emerald-200', dot: 'bg-emerald-400' },
  { chip: 'border-rose-400/40 bg-rose-400/15 text-rose-200', dot: 'bg-rose-400' },
  { chip: 'border-amber-400/40 bg-amber-400/15 text-amber-200', dot: 'bg-amber-400' },
  { chip: 'border-violet-400/40 bg-violet-400/15 text-violet-200', dot: 'bg-violet-400' },
  { chip: 'border-teal-400/40 bg-teal-400/15 text-teal-200', dot: 'bg-teal-400' },
  { chip: 'border-orange-400/40 bg-orange-400/15 text-orange-200', dot: 'bg-orange-400' },
  { chip: 'border-fuchsia-400/40 bg-fuchsia-400/15 text-fuchsia-200', dot: 'bg-fuchsia-400' },
  { chip: 'border-lime-400/40 bg-lime-400/15 text-lime-200', dot: 'bg-lime-400' },
  { chip: 'border-cyan-400/40 bg-cyan-400/15 text-cyan-200', dot: 'bg-cyan-400' },
];
const NEUTRAL_TONE = { chip: 'border-white/15 bg-white/5 text-zinc-300', dot: 'bg-zinc-400' };

export function tagTone(tags: MonitorTag[], label: string): { chip: string; dot: string } {
  const index = tags.findIndex((tag) => tag.name.toLowerCase() === label.toLowerCase());
  return index >= 0 ? TAG_TONES[index % TAG_TONES.length] : NEUTRAL_TONE;
}

/** "1 mensagem" / "3 mensagens". */
export function countMessages(count: number): string {
  return `${count} ${count === 1 ? 'mensagem' : 'mensagens'}`;
}

export function formatProbability(value: number): string {
  return `${Math.round(value * 100)}%`;
}

export function describeProbabilities(probabilities: Record<string, number>): string {
  return Object.entries(probabilities)
    .sort(([, a], [, b]) => b - a)
    .map(([label, value]) => `${label}: ${formatProbability(value)}`)
    .join(' · ');
}

export function formatTime(value: string): string {
  return new Date(value).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

export function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

/** Validação client-side de uma tag nova contra a lista atual (a regra final é do backend). */
export function validateNewTag(tags: MonitorTag[], name: string, description: string): string | null {
  const normalized = name.trim().replace(/\s+/g, ' ');
  if (!normalized) return 'Informe o nome da tag.';
  if (normalized.length > TAG_NAME_MAX_LENGTH) return `O nome deve ter até ${TAG_NAME_MAX_LENGTH} caracteres.`;
  if (description.trim().length > TAG_DESCRIPTION_MAX_LENGTH) {
    return `A descrição deve ter até ${TAG_DESCRIPTION_MAX_LENGTH} caracteres.`;
  }
  if (tags.some((tag) => tag.name.toLowerCase() === normalized.toLowerCase())) return 'Essa tag já existe.';
  if (tags.length >= MAX_TAGS) return `Use no máximo ${MAX_TAGS} tags.`;
  return null;
}
