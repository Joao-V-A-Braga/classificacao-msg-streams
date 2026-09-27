"""A página que um monitoramento vigia, em uma aba do Chrome.

Toda página tem a mesma interface:
- `read()` devolve o que está na tela agora: uma lista de {id, author, text}, ou None enquanto o chat
  ou os comentários não aparecem (carregando, navegando, chat desativado);
- `prepare()` ajusta a página antes de começar a vigiar;
- `close()` fecha a aba;
- `poll_interval` diz a cada quantos segundos reler, e `watching_detail`, o que mostrar no painel.
"""

from __future__ import annotations

from src.modules.sources.errors import WatchError
from src.modules.sources.instagram import InstagramPostPage
from src.modules.sources.youtube import YoutubeLivePage

PAGES = {"youtube_live": YoutubeLivePage, "instagram_post": InstagramPostPage}


def open_page(monitor: dict):
    """Abre uma aba nova para o monitoramento.

    Cada monitoramento tem a sua aba, mesmo quando dois vigiam a mesma live ou post: pausar, iniciar
    ou uma falha em um nunca afeta os outros.
    """
    page_class = PAGES.get(monitor["sourceType"])
    if page_class is None:
        raise WatchError(f"Tipo de monitoramento não suportado: {monitor['sourceType']}")
    return page_class(monitor["url"])
