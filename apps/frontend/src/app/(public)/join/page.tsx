'use client';

import { useEffect, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { MessageSquareText } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { getMessage } from '@/shared/i18n';
import type { ApiErrorResponse } from '@/shared/types/api-error.type';
import { useAuthContext } from '@/modules/auth';

const DASHBOARD_ROUTE = '/dashboard';

type Mode = 'register' | 'login';

const API_URL = process.env.NEXT_PUBLIC_API_URL;

/**
 * O backend não devolve uma lista de códigos i18n pronta (ver
 * App\Shared\Errors\ApiExceptionListener): validação de múltiplos campos
 * chega em `details[].message`, erros de domínio de campo único (ex.:
 * e-mail já cadastrado) só em `message`. Mensagens humanas genéricas do
 * Symfony (corpo malformado, 500) também caem em `message`, mas não são
 * chaves i18n — por isso o filtro pelo formato "algo.algo".
 */
function extractErrorCodes(body: unknown): string[] {
  if (!body || typeof body !== 'object') return [];
  const error = body as Partial<ApiErrorResponse>;

  if (Array.isArray(error.details) && error.details.length > 0) {
    return error.details
      .map((item) => item?.message)
      .filter((message): message is string => typeof message === 'string');
  }

  if (typeof error.message === 'string' && /^[a-z]+(\.[a-z_]+)+$/i.test(error.message)) {
    return [error.message];
  }

  return [];
}

function notifyApiErrorBody(body: unknown): void {
  const codes = extractErrorCodes(body);
  if (codes.length === 0) {
    toast.error(getMessage('DEFAULT_API_ERROR'));
    return;
  }

  codes.forEach((code) => toast.error(getMessage(code)));
}

async function notifyApiError(response: Response): Promise<void> {
  let body: unknown = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  notifyApiErrorBody(body);
}

export default function JoinPage() {
  const [mode, setMode] = useState<Mode>('register');
  const { status } = useAuthContext();
  const router = useRouter();

  useEffect(() => {
    if (status === 'authenticated') {
      router.replace(DASHBOARD_ROUTE);
    }
  }, [status, router]);

  if (status === 'loading' || status === 'authenticated') {
    return <div className="min-h-screen bg-black" />;
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-black px-6 text-white">
      <div className="flex w-full max-w-sm flex-col items-center gap-8">
        <div className="flex flex-col items-center gap-3">
          <div className="flex size-14 items-center justify-center rounded-2xl border border-amber-400/30 bg-amber-400/10">
            <MessageSquareText className="size-7 text-amber-400" />
          </div>
          <div className="text-center">
            <h1 className="text-2xl font-black tracking-tight">Message Classifier</h1>
            <p className="mt-1 text-sm text-white/50">
              {mode === 'register' ? 'Crie sua conta para começar' : 'Entre na sua conta para continuar'}
            </p>
          </div>
        </div>

        {mode === 'register' ? <RegisterForm /> : <LoginForm />}

        <button
          type="button"
          onClick={() => setMode(mode === 'register' ? 'login' : 'register')}
          className="text-xs text-white/50 transition-colors hover:text-white/80"
        >
          {mode === 'register' ? 'Já tem conta? Entrar' : 'Não tem conta? Cadastre-se'}
        </button>

        <Link
          href="/"
          className="text-xs text-white/30 transition-colors hover:text-white/60"
        >
          ← Voltar para o início
        </Link>
      </div>
    </div>
  );
}

function RegisterForm() {
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const form = event.currentTarget;
    const formData = new FormData(form);
    const payload = {
      name: String(formData.get('name') ?? ''),
      email: String(formData.get('email') ?? ''),
      password: String(formData.get('password') ?? ''),
    };

    setSubmitting(true);
    try {
      const response = await fetch(`${API_URL}/auth/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (response.status === 201) {
        toast.success('Cadastro realizado com sucesso!');
        form.reset();
        return;
      }

      await notifyApiError(response);
    } catch {
      toast.error(getMessage('DEFAULT_API_ERROR'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex w-full flex-col gap-4">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="register-name">Nome</Label>
        <Input id="register-name" name="name" type="text" required autoComplete="name" />
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="register-email">E-mail</Label>
        <Input id="register-email" name="email" type="email" required autoComplete="email" />
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="register-password">Senha</Label>
        <Input id="register-password" name="password" type="password" required autoComplete="new-password" />
      </div>
      <Button
        type="submit"
        size="lg"
        disabled={submitting}
        className="w-full bg-amber-400 font-bold text-black hover:bg-amber-300"
      >
        {submitting ? 'Cadastrando...' : 'Cadastrar'}
      </Button>
    </form>
  );
}

function LoginForm() {
  const { login } = useAuthContext();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);
    const email = String(formData.get('email') ?? '');
    const password = String(formData.get('password') ?? '');

    setSubmitting(true);
    try {
      const result = await login(email, password);
      if (!result.ok) {
        notifyApiErrorBody(result.body);
        return;
      }
      // Sucesso: o efeito em JoinPage cuida do redirect para o dashboard
      // assim que `status` vira "authenticated".
    } catch {
      toast.error(getMessage('DEFAULT_API_ERROR'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex w-full flex-col gap-4">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="login-email">E-mail</Label>
        <Input id="login-email" name="email" type="email" required autoComplete="email" />
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="login-password">Senha</Label>
        <Input id="login-password" name="password" type="password" required autoComplete="current-password" />
      </div>
      <Button
        type="submit"
        size="lg"
        disabled={submitting}
        className="w-full bg-amber-400 font-bold text-black hover:bg-amber-300"
      >
        {submitting ? 'Entrando...' : 'Entrar'}
      </Button>
    </form>
  );
}
