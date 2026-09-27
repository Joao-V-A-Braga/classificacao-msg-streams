# apps/ingestion

Serviço de ingestão: um processo Python no host (fora do Docker), porque controla o Chrome real do
usuário via [Browser Harness](https://github.com/browser-use/browser-harness) (CDP). Faz duas coisas:

1. **Classificador Laya** (`POST /classify` em `127.0.0.1:8100`): o backend (Docker, via
   `host.docker.internal`) classifica cada mensagem com as tags do monitoramento dela.

   ```json
   // request
   {"text": "Quanto custa?", "labels": [{"name": "pergunta", "description": "a question"}, {"name": "elogio", "description": null}]}
   // response
   {"label": "pergunta", "probabilities": {"pergunta": 0.81, "elogio": 0.19}, "confidence": 0.62}
   ```

2. **Runner dos monitoramentos**: a cada `RUNNER_INTERVAL_SECONDS` pergunta ao backend
   (`GET /ingestion/monitors`) quais monitoramentos estão rodando e mantém uma aba em segundo plano no
   Chrome para cada um:
   - live do YouTube: abre o chat pop-out (`/live_chat?v=<id>`) e lê o chat a cada 1 s;
   - post do Instagram: abre o post e recarrega a cada 20 s (seletores best-effort, não validados).

   Ao abrir, tudo o que já está na página vira *baseline* e é ignorado: só mensagens novas são
   enviadas (`POST /ingestion/monitors/{id}/messages`). O estado (abrindo/monitorando/erro) é
   reportado em `POST /ingestion/monitors/{id}/state` e aparece no painel. Pausar/excluir no painel
   faz o backend responder 404/409 e a aba é fechada; iniciar de novo muda o `startedAt` e o runner
   abre uma aba nova, com baseline novo.

## Estrutura

Segue a mesma divisão do `apps/backend` (`src/Modules` + `src/Shared`, com `tests/` espelhando):

```
ingestion_service.py         ponto de entrada: classificador Laya + runner
ingestion_supervisor.py      ponto de entrada: liga/desliga o serviço acima
src/
  modules/
    classification/          laya.py (classifica), translator.py (traduz para o inglês), server.py (POST /classify)
    sources/                 youtube.py e instagram.py (abrir e ler cada plataforma), pages.py (qual página abrir)
    monitoring/              runner.py (um watcher por monitoramento), watcher.py (lê a página e envia), dedupe.py
    supervisor/              supervisor.py (decide quando ligar, desligar e religar o serviço)
  shared/
    backend_client.py        cliente da API /ingestion/* do backend
    browser/                 abas do Chrome via Browser Harness (browser.py, tabs.py, tab_registry.py)
tests/                       mesma estrutura de src/
```

## Supervisor (liga/desliga sozinho)

O `start.ps1` sobe, sem janela, o `ingestion_supervisor.py`: um processo leve (sem Laya, instância
única em `127.0.0.1:8101`) que inicia o `ingestion_service.py` quando algum monitoramento é iniciado
no app, encerra depois de `SUPERVISOR_IDLE_STOP_SECONDS` (120) sem nenhum rodando e o reinicia se ele
cair, mostrando o estado no monitoramento. Logs em `logs/supervisor.log` e `logs/ingestion.log`.

## Rodar manualmente (depuração)

Se o serviço for iniciado à mão, o supervisor percebe a porta 8100 ocupada e não sobe outro.

```bash
uv sync
cp .env.example .env   # BACKEND_INGESTION_API_KEY = MESSAGE_INGESTION_API_KEY de apps/backend/.env.local
uv run --env-file .env python ingestion_service.py
```

No Chrome: `chrome://inspect/#remote-debugging` → "Allow remote debugging for this browser instance".
Laya roda em **CPU** e é carregado uma vez na subida (~30–60 s).

## Testes

```bash
uv run pytest
```

Cobre o classificador (tags dinâmicas), o cliente do backend, a extração de chat/comentários, o
baseline + envio do watcher e a reconciliação do runner (com página e backend falsos, sem Chrome).
