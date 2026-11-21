# Laméco Blitz

Laméco defaults for [Blitz](https://putyourlightson.com/plugins/blitz): it forces one set of Blitz settings on every site and lets each site name the query string parameters that must bypass the cache.

## Requirements

- PHP 8.2 or higher
- Craft CMS 5
- Blitz 5.14.6 or later, installed and enabled (5.14.5 fixes a path traversal in file storage, 5.14.6 the local generator's cached includes)

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
| Storage | compressed cached values in `@root/blitz-cache`, outside the web root |
| Refresh mode | expire the cache, unless `BLITZ_REFRESH_MODE` says otherwise |
| Query string caching | cache URLs with query strings as the same page |
| Included / excluded query string params | none |
| SSI | on while caching is on, unless `BLITZ_SSI=0` |
| ESI | off |

`BLITZ_GENERATOR=LOCAL` is for local development only, where Blitz's HTTP generator can't reach Valet's `.test` domains. Servers use `HTTP`, which renders each page through the web server the way a visitor's request does.

## Refresh mode

By default an edit expires the cached pages that show it: visitors keep getting the old page, and a visit queues its regeneration. No queue job rebuilds the whole site after an edit, which keeps the load on a shared server low. The catch: the page of a deleted or unpublished entry stays up, since regenerating it returns a 404 and Blitz leaves the file alone.

`BLITZ_REFRESH_MODE` picks another of Blitz's [refresh modes](https://putyourlightson.com/plugins/blitz#refresh-mode) for a site that needs it:

| `BLITZ_REFRESH_MODE` | Blitz refresh mode |
|---|---|
| `expire` (default) | Expire the cache, regenerate manually or organically |
| `clear` | Clear the cache, regenerate manually or organically |
| `expire-and-generate` | Expire the cache and regenerate in a queue job |
| `clear-and-generate` | Clear the cache and regenerate in a queue job |

`clear` removes a changed or deleted page at once; the next visitor waits for a fresh render instead of getting the old page. The `-and-generate` modes regenerate every affected page in a queue job, after a global save the whole site. An unknown value keeps `expire` and logs a warning.

## Cached includes and SSI

With SSI on, nginx puts each `craft.blitz.includeCached()` fragment into the page before it leaves the server, so a cached header or footer is part of the HTML. Without SSI, Blitz falls back to an AJAX request: the page arrives with an empty placeholder and JavaScript fills it in after `DOMContentLoaded`.

The Rootnet servers and Valet's stock nginx run with `ssi on`; the Blitz settings page in the CP shows "SSI was detected on the web server" when the server processes SSI. On a server that doesn't, set `BLITZ_SSI=0`: the raw `<!--#include virtual="…" -->` comment would otherwise reach the browser and the include would be missing.

Blitz outputs SSI tags for cached includes whether or not caching is on, so SSI is only turned on together with `BLITZ_ENABLED`. With caching off, the AJAX fallback keeps the header and footer working on any server, including `php craft serve` and test runs.

ESI stays off: Blitz checks SSI first, and nothing in front of our servers processes ESI tags.

Blitz fires `afterBlitzInjectAll` only on a page that still has an AJAX include, such as `craft.blitz.includeDynamic()`. Front-end code that waits for that event keeps working as long as the layout renders at least one dynamic include on every page; the starter kit's admin bar does.

Only pages that resolve to an entry are cached, plus the fragments of `craft.blitz.includeCached()`.

With `BLITZ_ENABLED=0`, Blitz neither caches nor serves cached pages. Blitz itself only stops caching new pages and keeps serving the ones it cached earlier, so a site could not be taken off the cache without clearing it.

Cached pages live in `@root/blitz-cache`, inside the release but outside `web/`. In Blitz's default `@webroot/cache/blitz`, every cached page is also a public file that the web server sends without Craft or Blitz deciding anything, which is why Blitz's docs advise disallowing `/cache/` in `robots.txt`. Outside `web/`, cached pages only reach visitors through Craft. Blitz only needs the web root when the files should be public, for server rewrites (its Folder Path setting says so); this plugin serves cached pages through Craft and uses no rewrites. Add `/blitz-cache` to the site's `.gitignore`.

## Translations

With [Enupal Translate](https://plugins.craftcms.com/enupal-translate) installed, a changed site translation expires only the cached pages and `craft.blitz.includeCached()` fragments that show it, as Blitz recommends for content it can't track itself ([craft-blitz#649](https://github.com/putyourlightson/craft-blitz/issues/649)):

- While Blitz caches a page or fragment, the plugin tags it with every site translation (`|t`, `Craft::t('site', …)`) it renders.
- Enupal writes each save, import or provider translation to the translation file and queues its "Syncing translations with DB" job. Just before that job runs, the plugin compares Enupal's database copy with the files, and afterwards expires the pages tagged with a changed message. A change in `translations/nl/` also covers the regions that fall back to it, such as `nl-NL`.
- If Enupal's database copy is behind the files, for example when translations were changed in git, the next sync treats those messages as changed too.

This needs a queue runner, and only happens while Blitz caching is enabled (`BLITZ_ENABLED`). In the default expire mode, as with any expired page, the first visit still gets the old text and queues the regeneration; the visits after that get the new text. In a clear mode, the next visit gets the new text. A site that configures its own message source for the `site` category isn't tagged; there, a sync expires the whole cache. Edits to plugin translations through Enupal are not tracked.

Pages cached before 6.1.0 carry no translation tags, so clear the Blitz cache once after updating.

## Query string exclusions

Because every URL with a query string is cached as the same page, a parameter that changes the output has to be listed, or its render ends up in the cache under the plain URL for every visitor. A listed parameter makes the request bypass the cache whenever it is present, whatever its value (`?q=0`, `?q=` and `?q` included). Blitz neither serves such a request from the cache nor stores it.

- **Global query string params**: a comma-separated list that applies to every page, for example `q`.

The page parameter of query string pagination (`pageTrigger('?page')` in `config/general.php`) always bypasses the cache, without being listed: every page of a listing shares one URI, so `?page=2` would otherwise get the cached first page. Pagination in the path (`/news/p2`, Craft's default) gives each page its own URI and is cached normally.
- **Per section and entry type**: rows of a section/entry type and its comma-separated parameters.

Write array parameters with or without brackets: `filters[]` matches `?filters[]=1` and `?filters[0]=1`. Both settings can also be set in `config/_craft-blitz.php`, which then overrides the CP.

## Upgrading

- **From 1.x:** require `^6.0`, run `php craft up`, then clear the Blitz cache, since pages cached under the old rules stay in the cache otherwise.
- **From 6.1.x or earlier:** SSI is now on. Clear the Blitz cache once, since pages cached before it keep their AJAX placeholders until they are regenerated.
- **From 5.0.x:** the same. The migration converts the 5.0.x rows, which stored a section ID, into one row per entry type of that section, and logs a warning for rows whose section no longer exists. The listed parameters now bypass the cache instead of being cached as separate pages. Run the migration in the environment where project config is edited and commit the YAML; other environments pick it up when project config is applied.
