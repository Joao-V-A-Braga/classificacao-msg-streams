# classificacao-msg-streams

Monitora lives do YouTube e posts do Instagram, classifica cada mensagem nova com o **Laya** usando as
tags que você define para cada monitoramento e mostra tudo em tempo real no painel web.

## Pré-requisitos (Windows)

Docker Desktop, Node.js, [uv](https://docs.astral.sh/uv/) e Google Chrome.

## Rodar

Na raiz do projeto, no PowerShell:

```powershell
.\start.ps1
```

Sobe o backend (API + Postgres + servidor de tempo real via WebSocket), o frontend e, sem janela, o
supervisor de ingestão. Na primeira vez também instala as dependências e gera as chaves locais.

A ingestão (Laya + leitura das lives/posts) **liga sozinha** quando um monitoramento é iniciado no app e
**desliga sozinha** 2 minutos depois que nenhum está rodando; se cair, é reiniciada e o erro aparece no
monitoramento. Nada de terminal para isso — logs em `apps/ingestion/logs/`.

No Chrome, abra `chrome://inspect/#remote-debugging` e marque **"Allow remote debugging for this browser
instance"** — é por ele que o serviço de ingestão abre as lives/posts (uma aba em segundo plano por
monitoramento).

Acesse http://localhost:3000, entre em `/join` e vá em **Monitoramentos**.

## Monitoramentos

- **Novo monitoramento**: nome, tipo (live do YouTube ou post do Instagram), URL e as tags de
  classificação (vêm sugeridas; a descrição de cada tag é a dica que o Laya usa — em inglês funciona melhor).
- **Iniciar**: a partir desse momento só as mensagens novas são lidas e classificadas; o que já estava
  no chat/post é ignorado. Cada novo início observa a live de novo.
- **Pausar**: fecha a aba e para a ingestão. Tags só podem ser adicionadas/removidas com o
  monitoramento pausado (é preciso ter pelo menos 2 para iniciar). **Remover uma tag exclui também as
  mensagens classificadas com ela** (o app pede confirmação mostrando quantas).
- **Importar os comentários existentes** (só post do Instagram): marcando a opção (na criação ou com o
  monitoramento pausado), ao iniciar os comentários que o post já tinha também são carregados e
  classificados, com a data real de cada um. Iniciar de novo não duplica os já importados.
- **Excluir**: se estiver rodando, o monitoramento é parado primeiro (a aba dele fecha); depois é
  removido junto com as mensagens.
- Cada monitoramento é **independente**, com a sua própria aba no Chrome, mesmo que dois observem a
  mesma live ou post: iniciar, pausar, excluir ou um erro em um nunca afeta os outros.
- **Tradução para o Laya**: antes de classificar, cada mensagem é traduzida para o inglês pelo modelo
  de texto (`TEXT_MODEL`, padrão `llama3.2:3b` no Ollama, que o `start.ps1` sobe). O Laya é treinado em
  inglês e acerta bem mais assim; a tradução aparece em itálico abaixo da mensagem. Se o Ollama não
  estiver no ar, classifica o texto original. Cada monitoramento tem a opção **"Traduzir para o inglês
  antes do Laya"** (ligada por padrão), que pode ser desligada a qualquer momento, sem pausar: vale a
  partir da próxima mensagem. `TRANSLATE_FOR_LAYA=false` em `apps/ingestion/.env` desliga para todos.
- Lives aparecem como um chat em tempo real; posts, como uma tabela de comentários. As mensagens entram
  na tela na hora (WebSocket) e recebem a tag assim que o Laya classifica.

## Parar

```powershell
.\stop.ps1
```
