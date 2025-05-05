<?php

namespace lameco\blitz;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\CancelableEvent;
use craft\helpers\App;
use craft\services\Plugins;
use lameco\blitz\models\Settings;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\generators\HttpGenerator;
use putyourlightson\blitz\drivers\generators\LocalGenerator;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheRequestService;

/**
 * Craft Blitz plugin
 *
 * @method static Plugin getInstance()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        $this->_registerEvents();
    }

    private function _registerEvents(): void
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS, function() {
            Blitz::$plugin->settings->cachingEnabled = App::env('BLITZ_ENABLED') ?? false;
            Blitz::$plugin->settings->debug = App::env('BLITZ_DEBUG') ?? false;
            Blitz::$plugin->settings->includedUriPatterns = [['siteId' => '', 'uriPattern' => '.*']];
            Blitz::$plugin->settings->cacheGeneratorType = 'LOCAL' === App::env('BLITZ_GENERATOR') ? LocalGenerator::class : HttpGenerator::class;
            Blitz::$plugin->settings->refreshMode = SettingsModel::REFRESH_MODE_EXPIRE;
            Blitz::$plugin->settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE;
            Blitz::$plugin->settings->includedQueryStringParams = [];
            Blitz::$plugin->settings->excludedQueryStringParams = [];
            Blitz::$plugin->settings->cacheGeneratorSettings['concurrency'] = 1;
            Blitz::$plugin->settings->cacheStorageSettings['compressCachedValues'] = true;
        });

        Event::on(CacheRequestService::class, CacheRequestService::EVENT_IS_CACHEABLE_REQUEST, function (CancelableEvent $event) {
            $request = Craft::$app->getRequest();

            if (!$request->getIsSiteRequest() || $request->getIsConsoleRequest()) {
                return;
            }

            $entry = Craft::$app->getUrlManager()->getMatchedElement();

            if (!$entry instanceof Entry) {
                return;
            }

            $sectionQueryStringParamsMap = Plugin::getInstance()->getSettings()->sectionQueryStringParams ?? [];

            foreach ($sectionQueryStringParamsMap as $sectionSetting) {
                if ($entry->type->id === (int)$sectionSetting['section']) {
                    $excludedParams = array_map('trim', explode(',', $sectionSetting['queryStringParams'] ?? ''));

                    foreach ($excludedParams as $param) {
                        if ($request->getQueryParam($param)) {
                            $event->isValid = false; // Prevent caching for query param
                            return;
                        }
                    }
                }
            }
        });
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
