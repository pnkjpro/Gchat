# Gchat

Private one-to-one messaging with Laravel 13 and Livewire 4 polling. PHP 8.3+ and Composer are required. The interface uses local CSS and Livewire's bundled Alpine; no Node build is required.

## Run locally

This workspace already has dependencies, an application key, and a migrated SQLite database. Start the app:

```powershell
composer run dev
```

Open http://127.0.0.1:8000. Register two accounts (use separate browsers or an incognito window), then enter the other account's registered email to start a conversation. A conversation is visible only to its two participants.

For a fresh checkout, run `composer run setup` first. SQLite is the local default. To use MySQL or PostgreSQL, set the corresponding `DB_*` values in `.env` before running `php artisan migrate`.

## Message modes

- **M1 — In the moment (default):** messages disappear from the frontend five seconds after their send time. They remain in the database. Messages that expired while you were away are available in M2.
- **M2 — Saved history:** shows every saved message, including messages sent while either participant was in M1. History is paginated, 50 messages per page.
- Modes affect only the current viewer. The other participant chooses independently. Reloading starts in M1.
- Livewire polls once per second. Alpine hides messages at their expiry time between polls. Polling may be throttled by Livewire or the browser when a tab is in the background.

Configure `config/chat.php`, or set:

```dotenv
CHAT_DISAPPEAR_AFTER_SECONDS=5
CHAT_NUDGE_COOLDOWN_SECONDS=60
```

After changing cached configuration, run `php artisan config:clear` locally or `php artisan optimize` when deploying to production.

## Nudge notifications

Nudge sends a Laravel mail notification immediately during the button request, addressed only to the other participant, with a link back to the authenticated conversation. Message text is excluded from email. Each sender can nudge a given conversation once per configured cooldown. No queue worker, Supervisor, or cron job is required.

Local `MAIL_MAILER=log` writes mail to `storage/logs/laravel.log`. For real delivery, configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, and `MAIL_FROM_ADDRESS` for your provider. Set `APP_URL` to the address recipients will use. The configurable SMTP socket timeout is `MAIL_TIMEOUT=15` seconds by default.

The button displays "Sending…" while SMTP runs and confirms success after the mail call completes. SMTP acceptance does not guarantee inbox placement. A failed send displays an error, records diagnostic details in the application log, and releases the cooldown so the user can retry. There are no automatic background retries.

## MilesWeb shared hosting

Upload the updated application code, including `app/Notifications/ConversationNudge.php`, `app/Livewire/Chat.php`, `resources/views/livewire/chat.blade.php`, `config/mail.php`, and `config/queue.php`. Keep the hosting account's SMTP settings and credentials in its own `.env` file:

```dotenv
MAIL_MAILER=smtp
MAIL_TIMEOUT=15
QUEUE_CONNECTION=sync
```

Then run `php artisan optimize` from the production application directory. Nudge uses immediate notification delivery even if an existing deployment still has `QUEUE_CONNECTION=database`. Remove any worker process or queue cron entry previously configured for this app. Existing queue tables can remain; no database migration or table deletion is required for this change. Previously queued notifications are not automatically replayed.

Check that the other participant's registered email in the production database is a real inbox. Local demo account changes do not transfer through `.env` or code deployment.

## Verification

```powershell
php artisan test --compact
php vendor/laravel/pint/builds/pint --format agent
```

Tests cover authentication, private conversation access, both participants' messages, configurable expiry and exact timing, database retention, M2 history pagination, input validation, escaped message content, immediate nudge delivery without queued jobs, SMTP failure handling, retry after failure, and cooldown enforcement.

Browser checks verified registration, sign-in/out, a private conversation, incoming messages through polling, M1 disappearance, M2 recovery, and the nudge button. An earlier SMTP nudge test was accepted by MilesWeb and receipt was confirmed by the user. The immediate delivery change is covered by automated tests using the in-memory mail transport. Two local demo accounts and sample messages were created during browser checks.

## Scope

This version includes registration, login, and logout. Account recovery, email ownership verification, attachments, and end-to-end encryption are not implemented. Disappearing messages are a display preference; participants can always view the stored history in M2.
