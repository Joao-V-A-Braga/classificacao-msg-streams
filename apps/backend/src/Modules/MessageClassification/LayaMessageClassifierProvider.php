<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification;

use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageClassification;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageClassifierProviderInterface;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Classifies through the Laya classifier of the ingestion service (apps/ingestion), which answers
 * POST /classify {text, labels: [{name, description}]} with {label, probabilities: {name: p}, confidence}.
 */
final class LayaMessageClassifierProvider implements MessageClassifierProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function classify(string $text, array $tags, bool $translate = true): MessageClassification
    {
        $names = array_map(static fn (MonitorTag $tag): string => $tag->name, $tags);

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/classify', [
            'json' => [
                'text' => $text,
                'labels' => array_map(static fn (MonitorTag $tag): array => $tag->toArray(), $tags),
                'translate' => $translate,
            ],
            'timeout' => 30,
        ]);

        $body = $response->toArray();
        $label = $body['label'] ?? null;

        if (!in_array($label, $names, true)) {
            throw new \UnexpectedValueException(sprintf('Laya classifier returned an unknown label: %s', json_encode($label)));
        }

        $probabilities = [];
        foreach ($names as $candidate) {
            $probabilities[$candidate] = (float) ($body['probabilities'][$candidate] ?? 0.0);
        }

        $translated = $body['translatedText'] ?? null;

        return new MessageClassification($label, $probabilities, is_string($translated) && trim($translated) !== '' ? $translated : null);
    }
}
