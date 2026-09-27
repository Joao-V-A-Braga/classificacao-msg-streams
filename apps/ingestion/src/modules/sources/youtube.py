"""Chat de lives do YouTube: como achar o vídeo pela URL, abrir o chat e ler as mensagens.

Em vez da página do vídeo, abrimos o chat em janela separada (`/live_chat?v=<id>`). Assim a aba tem
só o chat: nenhum vídeo tocando em segundo plano e nenhum painel para abrir. Mesmo assim, a leitura
também procura dentro do `#chatframe`, caso a aba seja a página do vídeo com o chat aberto.
"""

from __future__ import annotations

import re
import time
from urllib.parse import parse_qs, urlparse

from src.modules.sources.errors import SourceGone
from src.shared.browser.tabs import close_browser, navigate, open_browser, read_safely

VIDEO_ID = re.compile(r"^[A-Za-z0-9_-]{11}$")

# Devolve `null` quando a página não tem a lista do chat (ainda carregando, chat desativado, não é
# uma live) e `[]` quando o chat existe mas ainda não tem mensagens. A lista na tela só guarda as
# mensagens mais recentes, então relê-la toda vez é barato; o Python descarta as já vistas pelo id.
EXTRACT_CHAT_MESSAGES_JS = """
(() => {
  const frame = document.querySelector('#chatframe');
  const docs = [document, frame && frame.contentDocument].filter(Boolean);
  for (const doc of docs) {
    const items = doc.querySelector('yt-live-chat-item-list-renderer #items') || doc.querySelector('#chat #items');
    if (!items) continue;
    return [...items.querySelectorAll('yt-live-chat-text-message-renderer')].map((el) => ({
      id: el.id,
      author: el.querySelector('#author-name')?.textContent?.trim() ?? '',
      text: el.querySelector('#message')?.textContent?.trim() ?? '',
    })).filter((item) => item.id && item.text);
  }
  return null;
})()
"""

# Por padrão o chat mostra só os "Principais" e esconde parte das mensagens: troca para "Chat ao vivo"
# (todas). Se o YouTube mudar esse menu, continuamos lendo os "Principais".
SWITCH_TO_ALL_MESSAGES_JS = """
(() => {
  const menu = document.querySelector('#live-chat-view-selector-sub-menu, yt-sort-filter-sub-menu-renderer');
  const options = menu ? menu.querySelectorAll('tp-yt-paper-listbox a') : [];
  if (options.length < 2) return false;
  options[options.length - 1].click();
  return true;
})()
"""


def video_id(url: str) -> str | None:
    """O id do vídeo em links watch, live, shorts, embed e youtu.be.

    None para links de canal, como youtube.com/@canal/live, que não trazem o id.
    """
    parsed = urlparse(url)
    host = (parsed.hostname or "").lower()
    candidate = None
    if host == "youtu.be" or host.endswith(".youtu.be"):
        candidate = parsed.path.strip("/").split("/")[0]
    elif host == "youtube.com" or host.endswith(".youtube.com"):
        query = parse_qs(parsed.query)
        if query.get("v"):
            candidate = query["v"][0]
        else:
            parts = [part for part in parsed.path.split("/") if part]
            if len(parts) >= 2 and parts[0] in {"live", "shorts", "embed", "v"}:
                candidate = parts[1]
    return candidate if candidate and VIDEO_ID.match(candidate) else None


def live_chat_url(video: str) -> str:
    return f"https://www.youtube.com/live_chat?is_popout=1&v={video}"


def read_chat_messages(browser) -> list[dict] | None:
    """Mensagens do chat que estão na tela agora, cada uma como {id, author, text}.

    `browser` é qualquer objeto com `.evaluate(javascript)`, normalmente um `shared.browser.Browser`.
    """
    result = browser.evaluate(EXTRACT_CHAT_MESSAGES_JS)
    return result if isinstance(result, list) else None


class YoutubeLivePage:
    """A aba com o chat de uma live do YouTube. O chat atualiza sozinho, então basta reler a tela."""

    poll_interval = 1.0
    watching_detail = "Monitorando o chat da live. Só mensagens novas são classificadas."

    def __init__(self, url: str, opener=open_browser) -> None:
        video = video_id(url)
        if video:
            self.browser = opener(live_chat_url(video))
            return

        # Links de canal (youtube.com/@canal/live) redirecionam para a página da live atual: abrimos,
        # esperamos o redirecionamento e pegamos o id do vídeo na URL final.
        self.browser = opener(url)
        deadline = time.monotonic() + 10
        while not video and time.monotonic() < deadline:
            video = video_id(str(self.browser.evaluate("location.href") or ""))
            if not video:
                time.sleep(0.5)
        if not video:
            self.close()
            raise SourceGone("Não encontrei uma live nessa URL. Use o link do vídeo da live (youtube.com/watch?v=...).")
        navigate(self.browser, live_chat_url(video))

    def prepare(self) -> None:
        try:
            if self.browser.evaluate(SWITCH_TO_ALL_MESSAGES_JS):
                time.sleep(1)
        except ValueError:
            pass

    def read(self) -> list[dict] | None:
        return read_safely(read_chat_messages, self.browser)

    def close(self, discard: bool = False) -> None:
        close_browser(self.browser)
