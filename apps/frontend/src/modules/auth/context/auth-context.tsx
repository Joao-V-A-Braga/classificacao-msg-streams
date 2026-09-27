'use client';

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { decodeJwtPayload, isJwtExpired } from '../lib/jwt';
import { readTokenCookie, removeTokenCookie, writeTokenCookie } from '../lib/token-cookie';

export type AuthUser = {
  id: string;
  name: string;
  email: string;
};

type AuthStatus = 'loading' | 'authenticated' | 'unauthenticated';

export type LoginResult = { ok: true } | { ok: false; status: number; body: unknown };

type AuthContextValue = {
  status: AuthStatus;
  user: AuthUser | null;
  token: string | null;
  login: (email: string, password: string) => Promise<LoginResult>;
  logout: () => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export function AuthProvider({ children }: { children: ReactNode }) {
  // Sempre nasce "loading" — igual no server (sem cookie) e no client
  // (primeiro paint), pra não divergir na hidratação. A transição pra
  // "authenticated"/"unauthenticated" só acontece depois do mount, no
  // effect abaixo.
  const [status, setStatus] = useState<AuthStatus>('loading');
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(null);

  const clearSession = useCallback(() => {
    removeTokenCookie();
    setToken(null);
    setUser(null);
    setStatus('unauthenticated');
  }, []);

  useEffect(() => {
    let cancelled = false;

    async function hydrateFromCookie() {
      const cookieToken = readTokenCookie();
      if (!cookieToken || isJwtExpired(decodeJwtPayload(cookieToken))) {
        if (!cancelled) clearSession();
        return;
      }

      // O payload do token só carrega `sub`/`email` (ver backend real,
      // Base\Auth\User\UseCase\LoginUser) — os dados completos do usuário
      // (nome incluso) vêm de /auth/me antes de hidratar a sessão.
      try {
        const response = await fetch(`${API_URL}/auth/me`, {
          headers: { Authorization: `Bearer ${cookieToken}` },
        });
        if (cancelled) return;

        if (!response.ok) {
          clearSession();
          return;
        }

        const body = await response.json();
        if (cancelled) return;
        setUser(body.user as AuthUser);
        setToken(cookieToken);
        setStatus('authenticated');
      } catch {
        if (!cancelled) clearSession();
      }
    }

    void hydrateFromCookie();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const login = useCallback(async (email: string, password: string): Promise<LoginResult> => {
    const response = await fetch(`${API_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });

    if (!response.ok) {
      let body: unknown = null;
      try {
        body = await response.json();
      } catch {
        body = null;
      }
      return { ok: false, status: response.status, body };
    }

    const body = await response.json();
    writeTokenCookie(body.token);
    setToken(body.token);
    setUser(body.user as AuthUser);
    setStatus('authenticated');
    return { ok: true };
  }, []);

  const logout = useCallback(() => {
    clearSession();
  }, [clearSession]);

  const value = useMemo<AuthContextValue>(
    () => ({ status, user, token, login, logout }),
    [status, user, token, login, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuthContext(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuthContext deve ser usado dentro de <AuthProvider>.');
  }
  return context;
}
