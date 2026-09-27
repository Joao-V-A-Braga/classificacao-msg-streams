'use client';

import { useEffect, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthContext } from '../context/auth-context';

/**
 * Envolve o layout do grupo (private). Enquanto o contexto hidrata a sessão
 * a partir do cookie, renderiza um placeholder neutro (sem flash de
 * conteúdo privado nem da tela de login).
 */
export function AuthGuard({ children }: { children: ReactNode }) {
  const { status } = useAuthContext();
  const router = useRouter();

  useEffect(() => {
    if (status === 'unauthenticated') {
      router.replace('/join');
    }
  }, [status, router]);

  if (status !== 'authenticated') {
    return <div className="min-h-screen bg-black" />;
  }

  return <>{children}</>;
}
