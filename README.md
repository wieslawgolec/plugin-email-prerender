# Mautic Email Pre-Render Plugin

**Plugin directory name (required):** `MauticEmailPreRenderBundle`

A Mautic 7 plugin that pre-compiles and caches fully rendered email payloads (HTML, subject, plain text) in advance. On the real `email.send` event the plugin can inject the cached payload late in the listener chain, eliminating most of the per-contact rendering cost for high-volume campaigns (100k–500k+ emails).

Designed to **coexist** with third-party renderers such as the Logicify Advanced Templates Bundle (`{% TWIG_BLOCK %}`), other Twig enhancers, core dynamic content, tokens, preheaders, tracking pixels, etc.

## How it works

1. **Pre-generation** (`bin/console mautic:email:prerender`)
   - Runs the **full** normal generation pipeline (`MailHelper` + `EmailEvents::EMAIL_ON_SEND`).
   - All listeners (Advanced Templates, core, other plugins) execute normally.
   - Stores the **final** HTML / subject / plain text in a dedicated cache table.

2. **Send time** (listener priority `-100`)
   - Runs **after** every other listener.
   - On cache hit → replaces content with the pre-rendered version.
   - On cache miss → falls back to normal generation (no breakage).

## Compatibility goals

- Does **not** short-circuit the generation path during pre-render.
- Does **not** re-implement Twig / token replacement.
- Uses a composite cache key so template changes and contact field changes invalidate correctly.
- Skips internal / test sends.

## Requirements

- Mautic 7.x
- PHP 8.2+

## Installation

1. Clone or copy this repository into your Mautic `plugins` directory **and rename the folder**:

   ```bash
   cd /path/to/mautic/plugins
   git clone https://github.com/wieslawgolec/plugin-email-prerender.git MauticEmailPreRenderBundle
   # OR if you already cloned elsewhere:
   # mv plugin-email-prerender MauticEmailPreRenderBundle
   ```

   The folder **must** be named `MauticEmailPreRenderBundle` (case-sensitive).

2. Clear cache and install/reload plugins:

   ```bash
   php bin/console cache:clear
   php bin/console mautic:plugins:reload
   # or from the UI: Settings → Plugins → Install/Upgrade Plugins
   ```

3. Run database migrations (creates `email_prerender_cache` table):

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   # or, if the migration is not auto-discovered yet:
   php bin/console doctrine:schema:update --force
   ```

4. (Optional) Enable the plugin in **Settings → Plugins** if it is not enabled automatically.

## Usage

### Pre-generate emails

```bash
# Pre-render for a specific email ID and a segment
php bin/console mautic:email:prerender --email=123 --segment=45 --batch=200

# Pre-render for an email and an explicit list of contact IDs
php bin/console mautic:email:prerender --email=123 --contacts=1,2,3,4,5

# Limit how many contacts to process in this run
php bin/console mautic:email:prerender --email=123 --segment=45 --limit=5000
```

### Clear cache

```bash
php bin/console mautic:email:prerender:clear --email=123
php bin/console mautic:email:prerender:clear --all
```

## Cache key strategy

| Component        | Purpose                                      |
|------------------|----------------------------------------------|
| `email_id`       | Which email template                         |
| `contact_id`     | Which contact                                |
| `content_hash`   | Email content / revision fingerprint         |
| `contact_hash`   | Hash of relevant contact field values        |

When the email is edited or relevant contact fields change, the hash changes → cache miss → normal generation runs.

## Architecture overview

```
MauticEmailPreRenderBundle/
├── Config/config.php
├── MauticEmailPreRenderBundle.php
├── Entity/EmailPrerenderCache.php
├── Entity/EmailPrerenderCacheRepository.php
├── EventListener/EmailPrerenderSubscriber.php
├── Command/PrerenderEmailCommand.php
├── Command/ClearPrerenderCacheCommand.php
├── Model/PrerenderModel.php
├── Migrations/Version20261005120000.php
└── Resources/...
```

## Important notes / limitations (skeleton)

- This is a **working skeleton**. The pre-generation command currently contains the structure and hooks; you may need to refine how `MailHelper` is fully driven for your exact Mautic 7 minor version and installed plugins.
- Always test thoroughly with emails that use Advanced Templates (`{% TWIG_BLOCK %}`), Dynamic Content, owner signatures, and tracking tokens before production use at scale.
- Storage of 500k fully rendered HTML bodies requires adequate database capacity and cleanup policy (`expires_at` + clear command).

## License

MIT (see LICENSE file).

## Author

Wiesław Golec – https://github.com/wieslawgolec
