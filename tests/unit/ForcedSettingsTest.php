<?php

namespace lameco\blitz\tests\unit;

use lameco\blitz\Plugin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use putyourlightson\blitz\models\SettingsModel;

class ForcedSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['BLITZ_ENABLED'] = '1';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['BLITZ_ENABLED'], $_SERVER['BLITZ_SSI']);
    }

    public function testSsiIsOnWhenBlitzSsiIsUnset(): void
    {
        $settings = new SettingsModel();

        Plugin::applyForcedSettings($settings);

        $this->assertTrue($settings->ssiEnabled);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function blitzSsiValues(): array
    {
        return [
            'zero turns it off' => ['0', false],
            'false turns it off' => ['false', false],
            'one turns it on' => ['1', true],
            'true turns it on' => ['true', true],
        ];
    }

    #[DataProvider('blitzSsiValues')]
    public function testBlitzSsiDecidesSsi(string $value, bool $expected): void
    {
        $_SERVER['BLITZ_SSI'] = $value;
        $settings = new SettingsModel();
        $settings->ssiEnabled = !$expected;

        Plugin::applyForcedSettings($settings);

        $this->assertSame($expected, $settings->ssiEnabled);
    }

    /**
     * Blitz outputs SSI tags for cached includes whether or not caching is on, so with caching off a
     * server without SSI (php craft serve, a test run) would lose the header and footer.
     */
    public function testSsiStaysOffWhileCachingIsOff(): void
    {
        $_SERVER['BLITZ_ENABLED'] = '0';
        $_SERVER['BLITZ_SSI'] = '1';
        $settings = new SettingsModel();

        Plugin::applyForcedSettings($settings);

        $this->assertFalse($settings->ssiEnabled);
    }

    public function testEsiIsOffWhateverProjectConfigSays(): void
    {
        $settings = new SettingsModel();
        $settings->esiEnabled = true;

        Plugin::applyForcedSettings($settings);

        $this->assertFalse($settings->esiEnabled);
    }
}
