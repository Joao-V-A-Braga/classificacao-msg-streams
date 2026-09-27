<?php

declare(strict_types=1);

namespace App\Tests\Modules\MessageClassification;

use App\Modules\MessageClassification\LayaMessageClassifierProvider;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LayaMessageClassifierProviderTest extends TestCase
{
    private const PROBABILITIES = ['pergunta' => 0.7, 'elogio' => 0.2, 'spam' => 0.1];

    /**
     * @return MonitorTag[]
     */
    private function tags(): array
    {
        return [new MonitorTag('pergunta', 'a question'), new MonitorTag('elogio'), new MonitorTag('spam')];
    }

    public function testSendsTheMonitorTagsAndReturnsTheLabelAndProbabilities(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            self::assertSame('POST', $method);
            self::assertSame('http://laya.test/classify', $url);
            self::assertSame([
                'text' => 'Quanto custa?',
                'labels' => [
                    ['name' => 'pergunta', 'description' => 'a question'],
                    ['name' => 'elogio', 'description' => null],
                    ['name' => 'spam', 'description' => null],
                ],
                'translate' => true,
            ], json_decode($options['body'], true));

            return new MockResponse(json_encode([
                'label' => 'pergunta',
                'probabilities' => self::PROBABILITIES,
                'confidence' => 0.6,
            ]));
        });

        $provider = new LayaMessageClassifierProvider($httpClient, 'http://laya.test');
        $classification = $provider->classify('Quanto custa?', $this->tags());

        self::assertSame('pergunta', $classification->label);
        self::assertSame(self::PROBABILITIES, $classification->probabilities);
    }

    public function testKeepsProbabilitiesInTagOrderAndFillsMissingOnesWithZero(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(json_encode([
            'label' => 'spam',
            'probabilities' => ['spam' => 0.9, 'pergunta' => 0.1, 'unknown' => 0.5],
        ])));

        $provider = new LayaMessageClassifierProvider($httpClient, 'http://laya.test');

        self::assertSame(
            ['pergunta' => 0.1, 'elogio' => 0.0, 'spam' => 0.9],
            $provider->classify('promo!!!', $this->tags())->probabilities,
        );
    }

    public function testRejectsALabelThatIsNotATag(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(json_encode(['label' => 'duvida', 'probabilities' => []])));

        $provider = new LayaMessageClassifierProvider($httpClient, 'http://laya.test');

        $this->expectException(\UnexpectedValueException::class);
        $provider->classify('texto qualquer', $this->tags());
    }

    public function testKeepsTheEnglishVersionTheClassifierRead(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse(json_encode([
            'label' => 'pergunta',
            'probabilities' => ['pergunta' => 1.0],
            'translatedText' => 'How much is it?',
        ])));

        $provider = new LayaMessageClassifierProvider($httpClient, 'http://laya.test');

        self::assertSame('How much is it?', $provider->classify('Quanto custa?', $this->tags())->translatedText);
    }

    public function testAsksForNoTranslationWhenTheMonitorTurnedItOff(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            self::assertFalse(json_decode($options['body'], true)['translate']);

            return new MockResponse(json_encode(['label' => 'elogio', 'probabilities' => ['elogio' => 1.0]]));
        });

        $provider = new LayaMessageClassifierProvider($httpClient, 'http://laya.test');

        self::assertNull($provider->classify('Amei!', $this->tags(), translate: false)->translatedText);
    }

    public function testNormalizesTrailingSlashOnBaseUrl(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            self::assertSame('http://laya.test/classify', $url);

            return new MockResponse(json_encode(['label' => 'elogio', 'probabilities' => ['elogio' => 1.0]]));
        });

        $provider = new LayaMessageClassifierProvider($httpClient, 'http://laya.test/');

        self::assertSame('elogio', $provider->classify('oi', $this->tags())->label);
    }
}
