<?php

namespace lameco\blitz;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\CancelableEvent;
use craft\helpers\App;
use craft\i18n\PhpMessageSource;
use craft\services\Plugins;
use lameco\blitz\helpers\EnupalTranslationChanges;
use lameco\blitz\helpers\QueryStringExclusion;
use lameco\blitz\helpers\TranslationRefresh;
use lameco\blitz\helpers\TranslationTags;
use lameco\blitz\i18n\TaggingPhpMessageSource;
use lameco\blitz\models\Settings;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\drivers\generators\HttpGenerator;
use putyourlightson\blitz\drivers\generators\LocalGenerator;
use putyourlightson\blitz\models\SettingsModel;
use putyourlightson\blitz\services\CacheRequestService;
use Throwable;

/**
 * Craft Blitz plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.0.0';
    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        $this->_registerEvents();
    }

    private function _registerEvents(): void
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS, function() {
            // Blitz::$plugin is an uninitialised typed property while Blitz is missing or disabled.
            if (!isset(Blitz::$plugin)) {
                return;
            }

            $settings = Blitz::$plugin->settings;
            $settings->cachingEnabled = App::env('BLITZ_ENABLED') ?? false;
            $settings->debug = App::env('BLITZ_DEBUG') ?? false;
            $settings->includedUriPatterns = [['siteId' => '', 'uriPattern' => '.*']];
            $settings->cacheGeneratorType = 'LOCAL' === App::env('BLITZ_GENERATOR') ? LocalGenerator::class : HttpGenerator::class;
            $settings->refreshMode = SettingsModel::REFRESH_MODE_EXPIRE;
            $settings->queryStringCaching = SettingsModel::QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE;
            $settings->includedQueryStringParams = [];
            $settings->excludedQueryStringParams = [];
            $settings->cacheGeneratorSettings['concurrency'] = 1;
            $settings->cacheStorageSettings['compressCachedValues'] = true;

            $this->_reregisterBlitzDrivers($settings);

            Event::on(CacheRequestService::class, CacheRequestService::EVENT_IS_CACHEABLE_REQUEST, function(CancelableEvent $event) {
                $this->_vetoUncacheableRequest($event);
            });

            if (Craft::$app->getPlugins()->isPluginEnabled('enupal-translate')) {
                $this->_registerTranslationRefresh($this->_tagSiteTranslations());
            }
        });
    }

    /**
     * Blitz builds its storage and generator components from its settings during its own init(),
     * before EVENT_AFTER_LOAD_PLUGINS, so the driver settings above only apply once the components
     * are defined again. The settings come from the site's project config and may belong to another
     * driver (HttpGenerator's basic-auth fields under LocalGenerator); only the target driver's own
     * properties are passed, since Yii refuses unknown ones.
     */
    private function _reregisterBlitzDrivers(SettingsModel $settings): void
    {
        $drivers = [
            'cacheStorage' => [$settings->cacheStorageType, $settings->cacheStorageSettings],
            'cacheGenerator' => [$settings->cacheGeneratorType, $settings->cacheGeneratorSettings],
        ];

        foreach ($drivers as $id => [$class, $driverSettings]) {
            $config = ['class' => $class];

            foreach ($driverSettings as $name => $value) {
                if (is_string($name) && property_exists($class, $name)) {
                    $config[$name] = $value;
                }
            }

            Blitz::$plugin->set($id, $config);
        }
    }

    /**
     * Swaps Craft's site translation source for one that tags cached pages with the messages they use.
     * A site that configured its own source for the category keeps it, and gets whole-cache refreshes.
     */
    private function _tagSiteTranslations(): bool
    {
        $i18n = Craft::$app->getI18n();
        $source = $i18n->translations['site'] ?? null;

        if (!is_array($source) || ($source['class'] ?? null) !== PhpMessageSource::class) {
            return false;
        }

        $source['class'] = TaggingPhpMessageSource::class;
        $i18n->translations['site'] = $source;

        return true;
    }

    /**
     * Translations change no element, so Blitz would keep serving the old text. In expire mode
     * refreshing marks the pages showing a changed translation expired, and they rebuild as visitors
     * arrive.
     */
    private function _registerTranslationRefresh(bool $pagesAreTagged): void
    {
        $refresh = new TranslationRefresh(
            function() use ($pagesAreTagged): ?array {
                if (!$pagesAreTagged) {
                    return null;
                }

                try {
                    return TranslationTags::forChanges(
                        EnupalTranslationChanges::pending(),
                        Craft::$app->getI18n()->getSiteLocaleIds(),
                        'site',
                    );
                } catch (Throwable $exception) {
                    Craft::warning('Could not read the changed translations, so the whole Blitz cache is refreshed: ' . $exception->getMessage(), __METHOD__);

                    return null;
                }
            },
            function(array $tags): void {
                Blitz::$plugin->refreshCache->refreshCacheTags($tags);
                // Blitz queues the refresh at the end of the request, which a long-running queue
                // worker does not reach.
                Blitz::$plugin->refreshCache->refresh();
            },
            fn() => Blitz::$plugin->refreshCache->refreshAll(),
        );

        $refresh->attach();
    }

    private function _vetoUncacheableRequest(CancelableEvent $event): void
    {
        $request = Craft::$app->getRequest();

        if (!$request->getIsSiteRequest() || $request->getIsConsoleRequest()) {
            $event->isValid = false;
            return;
        }

        // A craft.blitz.includeCached() fragment is fetched from its own URL, which matches no
        // entry; the entry check below would leave every such fragment uncached and rendered
        // by PHP on each page view, even when the page around it comes from the cache.
        if (Blitz::$plugin->cacheRequest->getIsCachedInclude()) {
            return;
        }

        $entry = Craft::$app->getUrlManager()->getMatchedElement();

        if (!$entry instanceof Entry) {
            $event->isValid = false;
            return;
        }

        $settings = $this->getSettings();

        // A nested entry with its own URI has no section.
        if (QueryStringExclusion::isExcluded(
            $request->getQueryParams(),
            $settings->globalQueryStringParams,
            $settings->sectionQueryStringParams,
            $entry->getSection()?->handle,
            $entry->getType()->handle,
        )) {
            $event->isValid = false;
        }
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
