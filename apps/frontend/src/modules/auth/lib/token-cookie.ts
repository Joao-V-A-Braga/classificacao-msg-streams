import Cookies from 'js-cookie';

const TOKEN_COOKIE_NAME = 'auth_token';

export function readTokenCookie(): string | null {
  return Cookies.get(TOKEN_COOKIE_NAME) ?? null;
}

export function writeTokenCookie(token: string): void {
  Cookies.set(TOKEN_COOKIE_NAME, token, {
    expires: 7,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
  });
}

export function removeTokenCookie(): void {
  Cookies.remove(TOKEN_COOKIE_NAME);
}
