# Gchat

Private one-to-one messaging with Laravel 13 and Livewire 4 polling. PHP 8.3+ and Composer are required. The interface uses local CSS and Livewire's bundled Alpine; no Node build is required.

## Run locally

This workspace already has dependencies, an application key, and a migrated SQLite database. In two terminals:

```powershell
composer run dev
```

```powershell
composer run queue
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

After changing cached configuration, run `php artisan config:clear` and restart the queue worker.

## Nudge notifications

Nudge queues a Laravel mail notification addressed only to the other participant, with a link back to the authenticated conversation. Message text is excluded from email. Each sender can nudge a given conversation once per configured cooldown.

Local `MAIL_MAILER=log` writes mail to `storage/logs/laravel.log`. A worker must be running to process nudges. For real delivery, configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, and `MAIL_FROM_ADDRESS` for your provider. Set `APP_URL` to the address recipients will use. Real SMTP delivery has not been tested.

Notifications retry three times with a 30-second backoff. Inspect failed jobs with `php artisan queue:failed`; after fixing the mail configuration, retry the chosen job with `php artisan queue:retry <id>`.

## Verification

```powershell
php artisan test --compact
php vendor/laravel/pint/builds/pint --format agent
```

Tests cover authentication, private conversation access, both participants' messages, configurable expiry and exact timing, database retention, M2 history pagination, input validation, escaped message content, queued nudges, and cooldown enforcement.

Browser checks also verified registration, sign-in/out, a private conversation, incoming messages through polling, M1 disappearance, M2 recovery, and the nudge action. The local queued nudge was processed successfully using the log mailer. Two local demo accounts and sample messages were created during these checks.

## Scope

This version includes registration, login, and logout. Account recovery, email ownership verification, attachments, and end-to-end encryption are not implemented. Disappearing messages are a display preference; participants can always view the stored history in M2.
