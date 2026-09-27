/**
 * Decodifica o payload de um JWT sem validar assinatura (a validação
 * criptográfica é sempre do backend). Usa `TextDecoder('utf-8')` sobre os
 * bytes decodificados em base64url — nunca `atob` cru, que corrompe
 * caracteres acentuados (ex.: "José" viraria "JosÃ©").
 */
export function decodeJwtPayload(token: string): Record<string, unknown> | null {
  const [, payloadSegment] = token.split('.');
  if (!payloadSegment) return null;

  try {
    const base64 = payloadSegment.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=');
    const binary = atob(padded);
    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
    const json = new TextDecoder('utf-8').decode(bytes);

    return JSON.parse(json) as Record<string, unknown>;
  } catch {
    return null;
  }
}

export function isJwtExpired(payload: Record<string, unknown> | null): boolean {
  if (!payload || typeof payload.exp !== 'number') return true;
  return payload.exp * 1000 <= Date.now();
}
