"""Comentários de posts e reels do Instagram, lidos da página do post (`/p/<código>/`).

Como a página é montada (conferido num post real em 2026-09-26):
- os comentários ficam dentro de `main` (não há `article`);
- cada comentário tem um link permanente `/p/<código>/c/<id do comentário>/`, um `time[datetime]`,
  o link do perfil do autor e um botão "Responder"/"Reply";
- o id do link permanente não muda entre recarregamentos;
- a legenda do post não tem link permanente, então nunca é confundida com um comentário.

O Instagram muda a página com frequência. Se os comentários pararem de aparecer (o monitoramento
fica em "monitorando" sem nada chegar), comece a investigar por aqui.

Os comentários não atualizam sozinhos: a aba recarrega o post de tempos em tempos e rola a lista para
carregar mais. A data de cada comentário (`at`) permite ignorar os que foram escritos antes de o
monitoramento começar.
"""

from __future__ import annotations

import re
import time
from urllib.parse import urlparse

from src.shared.browser.tabs import close_browser, navigate, open_browser, read_safely

SHORTCODE = re.compile(r"^/(?:[\w.]+/)?(?:p|reel|reels|tv)/([\w-]+)")

EXTRACT_COMMENTS_JS = r"""
(() => {
  const main = document.querySelector('main');
  if (!main) return null;
  const noise = /^(Responder|Reply|Curtir|Like|Ver tradução|See translation|Editado|Edited|Ver todas as \d+ respostas|View all \d+ repl(y|ies)|Ocultar respostas|Hide replies|[\d.,]+\s*(mil|mi|k|m)?\s*(curtidas?|likes?))$/i;
  const seen = new Set();
  const comments = [];
  for (const link of main.querySelectorAll('a[href*="/c/"]')) {
    const match = (link.getAttribute('href') || '').match(/\/c\/(\d+)/);
    if (!match || seen.has(match[1])) continue;
    seen.add(match[1]);
    // Sobe até o bloco deste comentário: o primeiro ancestral que contém o botão de responder.
    let block = link;
    for (let i = 0; i < 12 && block.parentElement; i++) {
      block = block.parentElement;
      if ([...block.querySelectorAll('span, div[role="button"]')].some((el) => /^(Responder|Reply)$/.test(el.textContent.trim()))) break;
    }
    const permalinkText = link.textContent.trim();
    const author = [...block.querySelectorAll('a[href^="/"]')]
      .filter((a) => !/\/c\/\d+/.test(a.getAttribute('href') || ''))
      .map((a) => a.textContent.trim())
      .find((text) => text) || '';
    const text = [...block.querySelectorAll('span[dir="auto"]')]
      .map((el) => el.textContent.trim())
      .find((value) => value && value !== author && value !== permalinkText && !noise.test(value)) || '';
    const at = link.querySelector('time')?.getAttribute('datetime') || block.querySelector('time')?.getAttribute('datetime') || null;
    if (text) comments.push({ id: 'ig-' + match[1], author, text, at });
  }
  return comments;
})()
"""

# Mais comentários carregam quando a lista é rolada até o fim (conferido: de 11 para 40 em 4 rolagens).
# Devolve false quando não há uma lista de comentários rolável.
SCROLL_COMMENTS_JS = r"""
(() => {
  let el = document.querySelector('main a[href*="/c/"]');
  while (el && el !== document.body) {
    const style = getComputedStyle(el);
    if (/(auto|scroll)/.test(style.overflowY) && el.scrollHeight > el.clientHeight + 10) {
      el.scrollTop = el.scrollHeight;
      return true;
    }
    el = el.parentElement;
  }
  return false;
})()
"""


def post_url(url: str) -> str:
    """Converte qualquer link de post ou reel na página do post, que mostra os comentários ao lado da
    mídia.

    Aceita /p/, /reel/, /reels/ e /tv/, com ou sem o perfil na frente e com ou sem parâmetros. Links
    /reel/ abririam o visualizador de Reels, onde os comentários ficam escondidos.
    """
    code = shortcode(url)
    return f"https://www.instagram.com/p/{code}/" if code else url


def shortcode(url: str) -> str | None:
    match = SHORTCODE.match(urlparse(url).path)
    return match.group(1) if match else None


def read_comments(browser) -> list[dict] | None:
    """Comentários que estão na tela agora, cada um como {id, author, text, at}.

    `browser` é qualquer objeto com `.evaluate(javascript)`, normalmente um `shared.browser.Browser`.
    """
    result = browser.evaluate(EXTRACT_COMMENTS_JS)
    if not isinstance(result, list):
        return None
    return [
        {"id": item["id"], "author": item.get("author") or "", "text": item["text"], "at": item.get("at")}
        for item in result
        if isinstance(item, dict) and item.get("id") and item.get("text")
    ]


class InstagramPostPage:
    """A aba com um post do Instagram. Os comentários não atualizam sozinhos: a página é recarregada
    a cada leitura."""

    poll_interval = 20.0
    watching_detail = "Monitorando os comentários do post (a página é relida a cada 20 s)."
    SCROLLS_PER_READ = 3
    # Ao importar os comentários existentes, para quando a lista para de crescer (ou neste limite).
    MAX_LOAD_ALL_SCROLLS = 80
    RENDER_WAIT_SECONDS = 15

    def __init__(self, url: str, opener=open_browser) -> None:
        # Links /reel/ abrem o visualizador de Reels, que esconde os comentários: sempre usamos a
        # página do post.
        self._url = post_url(url)
        self.browser = opener(self._url)
        self._reads = 0

    def prepare(self) -> None:
        pass

    def read(self) -> list[dict] | None:
        # Comentários novos só aparecem depois de recarregar. As duas primeiras leituras (que formam
        # a linha de base) usam a página como ela abriu.
        self._reads += 1
        if self._reads > 2:
            # Navegamos até a página do post em vez de recarregar: o Instagram às vezes troca a URL
            # de um reel para /reels/, e recarregar cairia no visualizador de Reels, sem comentários.
            # Aconteceu numa execução real. Se mesmo assim ele redirecionar, tentamos mais uma vez.
            for _ in range(2):
                navigate(self.browser, self._url)
                time.sleep(3)
                if self._on_post_page():
                    break
            # O Instagram ordena os comentários por relevância e carrega mais ao rolar. Rolamos
            # algumas vezes para achar um comentário novo mesmo que ele não esteja entre os primeiros.
            self._scroll(self.SCROLLS_PER_READ)
        return read_safely(read_comments, self.browser)

    def load_all(self, on_progress=None) -> list[dict]:
        """Todos os comentários que o post mostra, rolando a lista até ela parar de crescer.

        Usado para importar os comentários que o post já tinha antes do monitoramento. As respostas
        ficam recolhidas ("Ver respostas"), então só os comentários principais entram.
        """
        # Logo depois de abrir, a página já carregou mas os comentários ainda não apareceram (numa
        # execução real, a importação achou 0). Damos um tempo antes de concluir que não há nada.
        comments: list[dict] = []
        deadline = time.monotonic() + self.RENDER_WAIT_SECONDS
        while True:
            comments = read_safely(read_comments, self.browser) or []
            if comments or time.monotonic() >= deadline:
                break
            time.sleep(1)
        stalled = 0
        for _ in range(self.MAX_LOAD_ALL_SCROLLS):
            if not self._scroll(1):
                break
            current = read_safely(read_comments, self.browser) or comments
            stalled = stalled + 1 if len(current) <= len(comments) else 0
            comments = current
            if on_progress:
                on_progress(len(comments))
            if stalled >= 3:
                break
        return comments

    def _scroll(self, times: int) -> bool:
        scrolled = False
        for _ in range(times):
            try:
                if not self.browser.evaluate(SCROLL_COMMENTS_JS):
                    break
            except ValueError:
                break
            scrolled = True
            time.sleep(1.5)
        return scrolled

    def _on_post_page(self) -> bool:
        try:
            return str(self.browser.evaluate("location.pathname")).startswith("/p/")
        except ValueError:
            return False

    def close(self, discard: bool = False) -> None:
        close_browser(self.browser)
