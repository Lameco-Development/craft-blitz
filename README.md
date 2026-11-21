# Laméco Blitz

Laméco defaults for [Blitz](https://putyourlightson.com/plugins/blitz): it forces one set of Blitz settings on every site and lets each site name the query string parameters that must bypass the cache.

## Requirements

- PHP 8.2 or higher
- Craft CMS 5
- Blitz 5, installed and enabled

## Installation

```bash
composer require lameco/craft-blitz:^6.0
php craft plugin/install _craft-blitz
```

The constraint matters: Packagist also carries the tags `4.0.0`, `5.0.0` and `5.0.1` from an older implementation (2024).

## What it forces

The plugin overrides these Blitz settings at runtime, whatever the Blitz CP settings or `config/blitz.php` say:

| Blitz setting | Value |
|---|---|
| Caching enabled | `BLITZ_ENABLED` (default off) |
| Debug | `BLITZ_DEBUG` (default off) |
| Included URI patterns | every URI, every site |
| Generator | `LocalGenerator` when `BLITZ_GENERATOR=LOCAL`, otherwise `HttpGenerator`, with concurrency 1 |
| Storage | compressed cached values |
| Refresh mode | expire the cache |
| Query string caching | cache URLs with query strings as the same page |
| Included / excluded query string params | none |
| SSI | on, unless `BLITZ_SSI=0` |
| ESI | off |

`BLITZ_GENERATOR=LOCAL` is for local development only, where Blitz's HTTP generator can't reach Valet's `.test` domains. Servers use `HTTP`: the local generator can't rebuild `craft.blitz.includeCached()` fragments, so an expired header or footer stays up.

## Cached includes and SSI

With SSI on, nginx puts each `craft.blitz.includeCached()` fragment into the page before it leaves the server, so a cached header or footer is part of the HTML. Without SSI, Blitz falls back to an AJAX request: the page arrives with an empty placeholder and JavaScript fills it in after `DOMContentLoaded`.

The Rootnet servers and Valet's stock nginx run with `ssi on`; the Blitz settings page in the CP shows "SSI was detected on the web server" when the server processes SSI. On a server that doesn't, set `BLITZ_SSI=0`: the raw `<!--#include virtual="…" -->` comment would otherwise reach the browser and the include would be missing.

ESI stays off: Blitz checks SSI first, and nothing in front of our servers processes ESI tags.

Blitz fires `afterBlitzInjectAll` only on a page that still has an AJAX include, such as `craft.blitz.includeDynamic()`. Front-end code that waits for that event keeps working as long as the layout renders at least one dynamic include on every page; the starter kit's admin bar does.

Only pages that resolve to an entry are cached, plus the fragments of `craft.blitz.includeCached()`.

## Translations

With [Enupal Translate](https://plugins.craftcms.com/enupal-translate) installed, a changed site translation expires only the cached pages and `craft.blitz.includeCached()` fragments that show it, as Blitz recommends for content it can't track itself ([craft-blitz#649](https://github.com/putyourlightson/craft-blitz/issues/649)):

- While Blitz caches a page or fragment, the plugin tags it with every site translation (`|t`, `Craft::t('site', …)`) it renders.
- Enupal writes each save, import or provider translation to the translation file and queues its "Syncing translations with DB" job. Just before that job runs, the plugin compares Enupal's database copy with the files, and afterwards expires the pages tagged with a changed message. A change in `translations/nl/` also covers the regions that fall back to it, such as `nl-NL`.
- If Enupal's database copy is behind the files, for example when translations were changed in git, the next sync treats those messages as changed too.

This needs a queue runner, and only happens while Blitz caching is enabled (`BLITZ_ENABLED`). As with any expired page, the first visit still gets the old text and queues the regeneration; the visits after that get the new text. A site that configures its own message source for the `site` category isn't tagged; there, a sync expires the whole cache. Edits to plugin translations through Enupal are not tracked.

Pages cached before 6.1.0 carry no translation tags, so clear the Blitz cache once after updating.

## Query string exclusions

Because every URL with a query string is cached as the same page, a parameter that changes the output has to be listed, or its render ends up in the cache under the plain URL for every visitor. A listed parameter makes the request bypass the cache whenever it is present, whatever its value (`?q=0`, `?q=` and `?q` included). Blitz neither serves such a request from the cache nor stores it.

- **Global query string params**: a comma-separated list that applies to every page, for example `q,page`.
- **Per section and entry type**: rows of a section/entry type and its comma-separated parameters.

Write array parameters with or without brackets: `filters[]` matches `?filters[]=1` and `?filters[0]=1`. Both settings can also be set in `config/_craft-blitz.php`, which then overrides the CP.

## Upgrading

- **From 1.x:** require `^6.0`, run `php craft up`, then clear the Blitz cache, since pages cached under the old rules stay in the cache otherwise.
- **From 6.1.x or earlier:** SSI is now on. Clear the Blitz cache once, since pages cached before it keep their AJAX placeholders until they are regenerated.
- **From 5.0.x:** the same. The migration converts the 5.0.x rows, which stored a section ID, into one row per entry type of that section, and logs a warning for rows whose section no longer exists. The listed parameters now bypass the cache instead of being cached as separate pages. Run the migration in the environment where project config is edited and commit the YAML; other environments pick it up when project config is applied.
