<?php

namespace lameco\blitz\services;

use Craft;
use craft\elements\Entry;
use lameco\blitz\Plugin;
use putyourlightson\blitz\Blitz;

class BlitzService
{
    public function setupEntryQueryStringParams(): void
    {
        $request = Craft::$app->getRequest();

        // Return early if this is not a site request or if it's a console request
        if (!$request->getIsSiteRequest() || $request->getIsConsoleRequest()) {
            return;
        }

        $entry = Craft::$app->getUrlManager()->getMatchedElement();

        // Return early if matched element is not an entry
        if (!$entry instanceof Entry) {
            return;
        }

        Blitz::$plugin->settings->includedQueryStringParams = array_map(static function(string $queryStringParam) {
            return ['siteId' => '', 'queryStringParam' => $queryStringParam];
        }, $this->getEntryQueryStringParamsArray($entry));
    }

    private function getEntryQueryStringParamsArray(Entry $entry): array
    {
        $sectionQueryStringParamsMap = Plugin::getInstance()->getSettings()->sectionQueryStringParams ?? [];

        $entryQueryStringParams = null;

        foreach ($sectionQueryStringParamsMap as $sectionQueryStringParams) {
            if ($entry->type->id === (int)$sectionQueryStringParams['section']) {
                $entryQueryStringParams = $sectionQueryStringParams['queryStringParams'];
                break;
            }
        }

        return explode(',', $entryQueryStringParams);
    }
}
