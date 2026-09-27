/**
 * Shape real emitido por App\Shared\Errors\ApiExceptionListener (backend
 * Symfony). Validação de múltiplos campos vem em `details` (um item por
 * código, ex.: "user.email.invalid.email"); erros de domínio de campo único
 * (ex.: e-mail já cadastrado) vêm só em `message`. `message` também pode ser
 * texto humano não traduzível (erro HTTP genérico do Symfony, 500).
 */
export type ApiErrorResponse = {
  statusCode: number;
  error: string;
  message: string;
  details?: Array<{ message: string }> | null;
  path?: string | null;
  timestamp: string;
};
