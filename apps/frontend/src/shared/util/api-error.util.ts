import { toast } from 'sonner';
import { getMessage } from '@/shared/i18n';
import type { ApiErrorResponse } from '@/shared/types/api-error.type';

/**
 * Códigos i18n de uma resposta de erro do backend (ver App\Shared\Errors\ApiExceptionListener):
 * validação de vários campos vem em `details[].message`; erros de domínio só em `message`. Textos
 * humanos genéricos (500, corpo malformado) não são chaves i18n — por isso o filtro "algo.algo".
 */
export function extractErrorCodes(body: unknown): string[] {
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

/** Um toast por código de erro; sem código conhecido, o erro genérico. */
export function notifyApiErrorBody(body: unknown): void {
  const codes = extractErrorCodes(body);
  if (codes.length === 0) {
    toast.error(getMessage('DEFAULT_API_ERROR'));
    return;
  }

  codes.forEach((code) => toast.error(getMessage(code)));
}
