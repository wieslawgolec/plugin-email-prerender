# Mautic Email Pre-Render Plugin

**Install directory name (required):** `MauticEmailPreRenderBundle`

A Mautic 7 plugin that **generates each personalized email once**, stores the finished payload (HTML, subject, plain text, tokens, hashes), and on the real `email.send` path **reuses that payload** when the cache entry is still valid.

Goal: avoid running the full rendering pipeline (tokens, Dynamic Content, Advanced Templates / Twig, etc.) again for every contact during high-volume dispatch (100k–500k+).

## Design (important)

### Single generation

| Phase | What runs |
|-------|-----------|
| **Pre-render** (`mautic:email:prerender`) | Full pipeline once: `MailHelper` + `EMAIL_ON_SEND` listeners (core + Advanced Templates + other plugins). Result is stored. |
| **Real send** | If cache hit and feature enabled → inject stored HTML/subject/plain text/tokens. Optionally **short-circuit** remaining listeners (`stopPropagation`). |

There is **no** “generate fully, then overwrite late” path by default. Late overwrite would still pay for a full generation.

### Configurable short-circuit

Early reuse can conflict with third-party plugins that **must** run on every real send (custom headers, ESP routing, last-mile token injection, compliance hooks).

| Parameter | Default | Meaning |
|-----------|---------|--------|
| `emailprerender.enabled` | `true` | Master switch. When `false`, the send-time listener does nothing. |
| `emailprerender.short_circuit` | `true` | On cache hit, inject payload and call `stopPropagation()` so later `EMAIL_ON_SEND` listeners do not run. Set to `false` if a plugin must still run after content is set (they will see the cached HTML). |

Configure in Mautic local config (e.g. `config/local.php`) or parameters:

```php
'emailprerender.enabled' => true,
'emailprerender.short_circuit' => true, // set false if 3rd-party send listeners must always run
```

### Cache validity

An entry is used only when **all** of the following match:

- `email_id` + `contact_id`
- `content_hash` (email template / revision / subject / HTML fingerprint)
- `contact_hash` (hash of contact profile fields used for personalization)
- `expires_at` is null or in the future

Stored fields: `subject`, `html`, `plain_text`, `tokens` (JSON), hashes, timestamps.

### Compatibility notes

- **Advanced Templates / Twig plugins**: Work at **pre-render** time (full `EMAIL_ON_SEND`). On a short-circuited real send they do not run again (content is already final).
- If you need a plugin to run on every real send, set `emailprerender.short_circuit` to `false` or disable the plugin for that campaign workflow.
- Internal / test sends never use the cache.

## Requirements

- Mautic 7.x
- PHP 8.2+

## Installation

```bash
cd /path/to/mautic/plugins
git clone https://github.com/wieslawgolec/plugin-email-prerender.git MauticEmailPreRenderBundle
# folder MUST be named MauticEmailPreRenderBundle

php bin/console cache:clear
php bin/console mautic:plugins:reload
php bin/console doctrine:migrations:migrate --no-interaction
# fallback: php bin/console doctrine:schema:update --force
```

Enable under **Settings → Plugins** if needed.

## Usage

### Pre-generate (full pipeline once per contact)

```bash
# Segment: contacts are loaded in DB batches (cursor on lead_lists_leads), not all at once
php bin/console mautic:email:prerender --email=123 --segment=45 --batch=200

# Explicit contact IDs
php bin/console mautic:email:prerender --email=123 --contacts=1,2,3,4,5

# Cap this run
php bin/console mautic:email:prerender --email=123 --segment=45 --limit=5000 --ttl=72
```

### Clear cache

```bash
php bin/console mautic:email:prerender:clear --email=123
php bin/console mautic:email:prerender:clear --expired
php bin/console mautic:email:prerender:clear --all
```

## Architecture

```
MauticEmailPreRenderBundle/
├── Config/config.php              # services + default parameters
├── MauticEmailPreRenderBundle.php
├── Entity/EmailPrerenderCache.php # payload + tokens JSON + hashes
├── Entity/EmailPrerenderCacheRepository.php
├── EventListener/EmailPrerenderSubscriber.php  # high priority; configurable short-circuit
├── Model/PrerenderModel.php       # Mautic 7 MailHelper::dispatchSendEvent path
├── Command/PrerenderEmailCommand.php           # batched segment loading
├── Command/ClearPrerenderCacheCommand.php
└── Migrations/Version20261005120000.php
```

### Pre-render path (Mautic 7)

`PrerenderModel::prerenderForContact()`:

1. `MailHelper::reset()`
2. `setEmail()` / `setLead(profileFields)` / `setIdHash()` / `setSource()`
3. Seed body/subject from the Email entity
4. **`dispatchSendEvent()`** — runs all `EMAIL_ON_SEND` listeners (no transport delivery)
5. Persist final subject, HTML, plain text, tokens, hashes

### Send path

`EmailPrerenderSubscriber` on `EMAIL_ON_SEND` at **priority 255** (runs early):

1. Skip if disabled, internal send, or missing email/lead
2. Lookup valid cache
3. On hit → `setContent` / `setSubject` / `setPlainText` / `addTokens`
4. If `short_circuit` → `stopPropagation()`

## Operational caveats

- **Storage**: Fully rendered HTML for 500k contacts is large; plan disk/DB size and TTL / clear policy.
- **Invalidation**: Editing the email or changing contact fields changes hashes → cache miss → normal generation (unless you re-run prerender).
- **Segment membership**: Pre-render only contacts currently in the segment table (`manually_removed = 0`). Rebuild segments before large prerender runs if needed.
- **idHash / tracking**: A new `idHash` is still created on the real send by core; cached HTML may already contain links from the pre-render idHash. For strict tracking parity, validate open/click behaviour on a pilot segment before full volume.
- Always pilot with Advanced Templates / DWC emails before production scale.

## License

MIT

## Author

Wiesław Golec – https://github.com/wieslawgolec
