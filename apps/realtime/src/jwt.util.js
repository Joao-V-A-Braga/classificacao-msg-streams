import { createHmac, timingSafeEqual } from 'node:crypto';

/**
 * Verifies an HS256 JWT issued by the backend (FirebaseJwtProvider) and returns its payload, or null
 * when the token is malformed, signed with another secret, not HS256 or expired.
 */
export function verifyJwt(token, secret, nowSeconds = Math.floor(Date.now() / 1000)) {
  if (typeof token !== 'string' || !secret) return null;

  const parts = token.split('.');
  if (parts.length !== 3) return null;
  const [encodedHeader, encodedPayload, signature] = parts;

  let header;
  let payload;
  try {
    header = JSON.parse(Buffer.from(encodedHeader, 'base64url').toString('utf8'));
    payload = JSON.parse(Buffer.from(encodedPayload, 'base64url').toString('utf8'));
  } catch {
    return null;
  }

  if (header?.alg !== 'HS256') return null;

  const expected = createHmac('sha256', secret).update(`${encodedHeader}.${encodedPayload}`).digest();
  const provided = Buffer.from(signature, 'base64url');
  if (provided.length !== expected.length || !timingSafeEqual(provided, expected)) return null;

  if (typeof payload?.exp === 'number' && payload.exp <= nowSeconds) return null;
  if (typeof payload?.sub !== 'string' || payload.sub === '') return null;

  return payload;
}

/** Test helper: signs a payload the same way the backend does. */
export function signJwt(payload, secret) {
  const encode = (value) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const unsigned = `${encode({ typ: 'JWT', alg: 'HS256' })}.${encode(payload)}`;
  return `${unsigned}.${createHmac('sha256', secret).update(unsigned).digest('base64url')}`;
}
