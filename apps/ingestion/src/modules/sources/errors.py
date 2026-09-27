"""Erros ao vigiar uma página, com mensagens pensadas para aparecer no painel do monitoramento."""


class WatchError(RuntimeError):
    """Um problema que vale mostrar ao usuário como detalhe do estado do monitoramento."""


class SourceGone(WatchError):
    """A live ou o post não está mais lá (o chat sumiu, a live acabou, não há live na URL).

    Se acontecer várias vezes seguidas, o monitoramento é pausado em vez de ficar tentando para sempre.
    """
