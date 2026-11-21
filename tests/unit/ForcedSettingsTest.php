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
        unset($_SERVER['BLITZ_ENABLED'], $_SERVER['BLITZ_SSI'], $_SERVER['BLITZ_REFRESH_MODE']);
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

    /**
     * @return array<string, array{?string, int}>
     */
    public static function blitzRefreshModeValues(): array
    {
        return [
            'unset keeps expire' => [null, SettingsModel::REFRESH_MODE_EXPIRE],
            'expire' => ['expire', SettingsModel::REFRESH_MODE_EXPIRE],
            'clear' => ['clear', SettingsModel::REFRESH_MODE_CLEAR],
            'expire-and-generate' => ['expire-and-generate', SettingsModel::REFRESH_MODE_EXPIRE_AND_GENERATE],
            'clear-and-generate' => ['clear-and-generate', SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE],
            'an unknown value keeps expire' => ['clear-generate', SettingsModel::REFRESH_MODE_EXPIRE],
        ];
    }

    #[DataProvider('blitzRefreshModeValues')]
    public function testBlitzRefreshModeDecidesTheRefreshMode(?string $value, int $expected): void
    {
        if ($value !== null) {
            $_SERVER['BLITZ_REFRESH_MODE'] = $value;
        }
        $settings = new SettingsModel();
        $settings->refreshMode = SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE === $expected
            ? SettingsModel::REFRESH_MODE_CLEAR
            : SettingsModel::REFRESH_MODE_CLEAR_AND_GENERATE;

        Plugin::applyForcedSettings($settings);

        $this->assertSame($expected, $settings->refreshMode);
    }

    public function testEsiIsOffWhateverProjectConfigSays(): void
    {
        $settings = new SettingsModel();
        $settings->esiEnabled = true;

        Plugin::applyForcedSettings($settings);

        $this->assertFalse($settings->esiEnabled);
    }
}
