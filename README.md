# espace

A casual place to catch up, share something, and play chess or ludo together. Built with Laravel 13 and Livewire 4 polling. PHP 8.3+ and Composer are required. Assets are local; no Node build or queue worker is required.

## Run locally

This workspace has dependencies, an application key, and a migrated SQLite database:

```powershell
composer run dev
```

Open http://127.0.0.1:8000. Use separate browsers or an incognito window for two accounts. Add a friend by their registered email. Email addresses are not displayed in conversation details or the sidebar.

For a fresh checkout, run `composer run setup` first. The development command sets PHP's upload limit to 5 MB and the request body limit to 8 MB. SQLite is the local default; MySQL and PostgreSQL can be configured with the usual `DB_*` settings.

## In the moment and the saved shelf

- **M1 is the default.** Messages and attachments disappear from the displayed conversation five seconds after sending. Database records and stored files remain.
- **Open M2 by clicking the espace logo three times in quick succession.** This reveals Your shelf. The default screen has no M2 button or history tabs.
- Your shelf includes **Conversation**, **Media & files**, **Links**, and **Pinned**. Items are paginated in groups of 50. Existing messages containing HTTP/HTTPS links are indexed by the migration, and new links are collected when a message is sent.
- Either participant can pin or unpin a message; pins are shared between them.
- **Back to now**, Escape, or a page reload returns to M1. The other participant chooses their own view independently. Escape also closes an open game.
- The logo gesture controls presentation. The actual access boundary remains authentication and conversation membership.

Configure disappearance and nudge cooldown in `config/chat.php` or `.env`:

```dotenv
CHAT_DISAPPEAR_AFTER_SECONDS=5
CHAT_NUDGE_COOLDOWN_SECONDS=60
```

Message polling runs once per second; game polling runs every two seconds. Background browser tabs can throttle polling. Alpine hides expiring messages between requests.

## Uploads

Attach one file per message, up to **5 MB (5,120 KB)**, with or without a caption. Supported types: JPEG, PNG, GIF, WebP, PDF, TXT, CSV, ZIP, Word, Excel, PowerPoint, MP3, WAV, M4A, MP4, WebM, and MOV. The configured allowlist is in `config/chat.php`.

Files are stored on the local disk under `storage/app/private/attachments`. Downloads and media previews use an authenticated route which checks conversation membership. Images, supported video, and audio have inline previews; other types download as files. No public storage symlink is needed. File names and message content are escaped before display.

## Play corner

Open a friend from Your circle, then choose a game. The first person to start chess plays white; the first to start ludo plays coral. Game state is stored in the database and shared through polling.

- **Chess:** legal moves, captures, castling, en passant, selectable pawn promotion, check, checkmate, stalemate, repetition and draw detection are handled by the server using the MIT-licensed p-chess/chess engine. Select a piece and then its destination. Legal destinations are marked. Each player sees the board from their own side.
- **Ludo:** a two-player variant with four pieces per player, six to enter, safe stars, captures, exact rolls to finish, bonus turns on a six/capture/finish, and turn forfeiture after three consecutive sixes. Same-colour pieces can share squares; blockades are not used. Dice are generated on the server.
- An active game can be resumed. Resign ends the round; a new round can then be started. Starting an active game again does not erase its progress.

## Nudge email

Nudge sends immediately over SMTP during the button request. No worker, cron or Supervisor is needed. It confirms success after the mail call completes; SMTP acceptance does not guarantee inbox placement. Failures show a generic error, log diagnostic details, and release the cooldown for a manual retry.

The email uses a promotional layout and the subject **Take a little break with espace**, with chess/ludo cards and a **Step into espace** button. It contains no chat text or sender-attention wording. The button opens the authenticated space. HTML and plain-text templates are under `resources/views/emails/`.

Use `MAIL_MAILER=log` for local mail logging or configure `MAIL_MAILER=smtp` with your provider's host, port, scheme, username, password, and from address. `MAIL_TIMEOUT=15` controls the SMTP socket timeout. An earlier MilesWeb SMTP test was accepted and receipt confirmed by the user; the revised email template has been tested without sending another real email.

## MilesWeb shared hosting deployment

Upload the updated application, assets, views, configuration, migrations, and Composer files. Keep the server's own `.env`, application key, database, uploaded files, and SMTP credentials.

Set these values in production `.env`, retaining your existing SMTP account settings:

```dotenv
APP_NAME=espace
APP_URL=https://your-production-domain
MAIL_MAILER=smtp
MAIL_FROM_NAME="espace"
MAIL_TIMEOUT=15
QUEUE_CONNECTION=sync
```

From the application directory:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

The Composer update includes the new chess engine. Deploy the dependencies too if Composer is not available on the host. The three new migrations add attachment/link/pin fields, create the game state table, and index links in existing messages. They do not remove existing messages.

The web root must point to `public/`. Ensure `storage/` and `bootstrap/cache/` are writable. Shared hosting must allow `upload_max_filesize=5M` or higher and `post_max_size=8M` or higher. A `public/.user.ini` supplies these values where per-directory PHP configuration is supported; otherwise use the hosting PHP settings panel. The application still enforces the 5 MB limit if the host accepts larger requests.

Check the production recipient's actual registered email before testing a nudge. Local demo account changes are not transferred by deploying code.

## Verification

```powershell
php artisan test --compact
php vendor/laravel/pint/builds/pint --format agent
composer validate --strict
```

Tests cover authentication and participant access, disappearance and persistence, hidden M2 access, shelf filters and shared pins, link extraction, upload sizes and file types, attachment download/preview permissions, immediate nudge delivery and failure/retry behavior, and chess/ludo rules and turn enforcement. Browser checks cover triple-click access, a real 5 MB upload, shelf tabs, shared chess moves, and ludo.

## Scope

Account recovery, email ownership verification, AI game opponents, game clocks, four-player ludo, and end-to-end encryption are not included. The casual interface preserves participant-only access to messages, files, and games.
