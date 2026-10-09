# Mautic Email Pre-Render Plugin

**Install directory name (required):** `MauticEmailPreRenderBundle`

A Mautic 7 plugin that **generates each personalized email once**, stores the finished payload, and reuses it on the real `email.send` path when the cache entry is still valid.

## Features

1. **CLI pre-render** – `mautic:email:prerender` (batched segment load)
2. **Campaign action** – **Pre-render email** (same email picker UI as **Send email**; delay/schedule via standard campaign event options)
3. **Send-time reuse** – early `EMAIL_ON_SEND` inject + optional short-circuit
4. **Auto-invalidation** – clears cache when the email template / related dynamic content changes

## Campaign builder usage

1. Add action **Pre-render email** (under Actions).
2. Choose the **same email** you will send later (form is core `EmailSendType`).
3. Set **execution timing** on the event the same way as any other campaign action (immediate, delay interval, specific date/time, contact preferred time, etc.). Those controls are provided by the campaign event shell, not a custom form.
4. Connect **Send email** after it (same email).

When the contact reaches the pre-render action, the plugin runs the full generation pipeline once and stores the payload. When **Send email** runs, a cache hit skips re-rendering (if enabled / short-circuit on).

Disable the campaign action registration:

```php
'emailprerender.campaign_action_enabled' => false,
```

## Configuration parameters

| Parameter | Default | Meaning |
|-----------|---------|--------|
| `emailprerender.enabled` | `true` | Send-time cache reuse |
| `emailprerender.short_circuit` | `true` | On hit, `stopPropagation()` on `EMAIL_ON_SEND` |
| `emailprerender.auto_invalidate` | `true` | Clear cache on email/DWC content changes |
| `emailprerender.campaign_action_enabled` | `true` | Show **Pre-render email** in campaign builder |
| `emailprerender.default_ttl_hours` | `72` | TTL applied by campaign action (0 = no expiry) |

## Auto-invalidation

When `emailprerender.auto_invalidate` is true:

| Trigger | Behaviour |
|---------|-----------|
| **Email saved** (`EMAIL_POST_SAVE`) | If content-related fields changed (`customHtml`, `subject`, `plainText`, `dynamicContent`, preheader, from/headers, template, revision, …) → delete all cache rows for that `email_id` |
| **Dynamic Content saved** | Best-effort: find emails whose `custom_html` / `dynamic_content` reference the DWC id/name/slot → clear those email caches |

After invalidation, the next send falls back to normal generation until pre-render runs again (CLI or campaign action).

## Single-generation design

| Phase | Behaviour |
|-------|-----------|
| Pre-render (CLI or campaign) | Full `MailHelper::dispatchSendEvent()` once; store HTML, subject, plain text, tokens, hashes |
| Real send | Cache lookup → inject; optional short-circuit so listeners do not re-process |

See earlier README sections for Advanced Templates compatibility and `idHash` tracking notes.

## Installation

```bash
cd /path/to/mautic/plugins
git clone https://github.com/wieslawgolec/plugin-email-prerender.git MauticEmailPreRenderBundle

php bin/console cache:clear
php bin/console mautic:plugins:reload
php bin/console doctrine:migrations:migrate --no-interaction
```

## CLI

```bash
php bin/console mautic:email:prerender --email=123 --segment=45 --batch=200
php bin/console mautic:email:prerender:clear --email=123
php bin/console mautic:email:prerender:clear --expired
php bin/console mautic:email:prerender:clear --all
```

## Support the project

If this plugin saves you time, you can support development:

- **GitHub Sponsors:** [github.com/sponsors/wieslawgolec](https://github.com/sponsors/wieslawgolec)
- **Buy Me a Coffee:** [buymeacoffee.com/wieslawgolec](https://buymeacoffee.com/wieslawgolec)

## License

MIT — Wiesław Golec
