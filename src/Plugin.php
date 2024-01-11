<?php

namespace lameco\blitz;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\helpers\App;
use lameco\blitz\models\Settings;
use lameco\blitz\services\BlitzService;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\generators\HttpGenerator;
use putyourlightson\blitz\drivers\generators\LocalGenerator;
use putyourlightson\blitz\models\SettingsModel;

/**
 * Craft Blitz plugin
 *
 * @method static Plugin getInstance()
 * @property-read BlitzService $blitzService
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'blitzService' => BlitzService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function() {
            $this->_registerSettings();
            $this->_registerEvents();
        });
    }

    private function _registerSettings(): void
    {
        Blitz::$plugin->settings->cachingEnabled = App::env('BLITZ_ENABLED') ?? false;
        Blitz::$plugin->settings->debug = App::env('BLITZ_DEBUG') ?? false;
        Blitz::$plugin->settings->cacheGeneratorType = 'LOCAL' === App::env('BLITZ_GENERATOR') ? LocalGenerator::class : HttpGenerator::class;
        Blitz::$plugin->settings->refreshMode = SettingsModel::REFRESH_MODE_EXPIRE;
        Blitz::$plugin->settings->includedUriPatterns = [['siteId' => '', 'uriPattern' => '.*']];
        Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_UNIQUE_PAGES;
    }

    private function _registerEvents(): void
    {
        $this->blitzService->setupEntryQueryStringParams();
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('_craft-blitz/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
}
