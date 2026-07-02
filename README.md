# iTasker (Oeuvre)

A task management web app with two interfaces:

- **Writer interface** — project root (`/`)
- **Administrator interface** — [`sudo/`](sudo/)

Both interfaces share a single MySQL database, a single Composer `vendor/`
tree, and a common set of helper functions.

## Requirements

- PHP 8.1+ with `mysqli` and `pdo_mysql` extensions
- MySQL / MariaDB
- [Composer](https://getcomposer.org/)
- Apache with `mod_rewrite` (the app relies on `.htaccess` for clean URLs)

## Setup

1. **Install dependencies** (one shared `vendor/` for both interfaces):

   ```bash
   composer install
   ```

2. **Create the database** and import your schema, then run the migrations
   in [`db-migrations/`](db-migrations/) in order, e.g.:

   ```bash
   mysql -u root tasker < db-migrations/2026_07_02_add_password_reset_tokens.sql
   ```

3. **Configure the environment.** Copy the template and fill in real values:

   ```bash
   cp .env.example .env
   ```

   `.env` holds database, SMTP, DigitalOcean Spaces, and app settings for
   *both* interfaces — see [`.env.example`](.env.example) for the full list.
   It is never committed to git; `env.php` loads it at runtime via `env()`.

   Set `APP_URL` to the site's real base URL (used to build links such as
   password-reset emails) and `APP_DEBUG=false` in any environment other
   than local development.

4. **Point your web server** at the project root. The writer app is served
   at `/`, the admin app at `/sudo/`.

5. **Set up the cron job** (reminder/exchange-rate scripts) — see
   [`cron/`](cron/).

## Project layout

```
/                     Writer interface
sudo/                 Administrator interface
assets/, vendors/     Front-end libraries and static assets
vendor/               Composer dependencies (PHPMailer, AWS SDK, TCPDF, Google API client)
shared-functions.php  Helpers used by BOTH interfaces (auth/session helpers,
                       CSRF protection, DB-error handling, upload validation, etc.)
env.php               Loads .env and exposes env()
db-migrations/         SQL migrations, applied manually in filename order
profileimages/, taskfiles/, sudo/uploads/   User-uploaded content (git-ignored)
cron/                 Scheduled scripts (exchange rates, reminders)
```

Files with the same name in the root and in `sudo/` are two independent
copies for each interface (e.g. `login.php`, `settings.php`) — this is a
deliberate split, not duplication to be merged. Logic shared verbatim
between the two interfaces lives in `shared-functions.php` instead.

## Security notes

- Never commit `.env` — it holds live credentials. `.env.example` is the
  template.
- All state-changing forms and AJAX endpoints are protected by CSRF tokens
  (`shared-functions.php`: `csrf_field()` / `csrf_verify()`).
- Uploaded files are served from directories with script execution
  disabled (`.htaccess` in `taskfiles/`, `profileimages/`, `sudo/uploads/`).
- Rotate any credential that was ever committed to this repository before
  it became private/cleaned, including the SMTP password.
