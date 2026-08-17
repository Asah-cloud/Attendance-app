# Attendance App

A multi-company event attendance system built with Laravel 13, Livewire 4, PostgreSQL, Tailwind CSS, and Laravel Excel.

## Features

- Company-scoped administrators, managers, members, and events
- QR-based public event check-in using signed URLs
- Manual attendance management and walk-in registration
- Multi-day event reports with Excel and CSV export
- Spreadsheet participant imports
- Company activation, subscription expiry, and event limits

## Requirements

- PHP 8.3 or newer with the extensions required by Laravel and PHPSpreadsheet
- Composer
- Node.js 22 or newer and npm
- PostgreSQL (SQLite is used by the automated tests)

## Local setup

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Configure the database and mail settings in `.env`, then run:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Set `ADMIN_PASSWORD` before running the seeders. Never use the local fallback password in a deployed environment.

For development with the web server, queue worker, and Vite running together:

```bash
composer dev
```

## Participant import format

The importer expects the following zero-based spreadsheet layout:

| Column | Value |
|---|---|
| A | External/member ID |
| B | Name |
| E | Category (optional) |
| F | Phone (optional) |

The first row may contain headings. Imported accounts receive a random unusable password and are assigned to the selected event and its company.

## Roles and tenancy

- `admin` is the cross-company super-admin.
- `manager` can manage records only within their own company.
- `regular` can view only their assigned event.

Spatie Laravel Permission is authoritative. The legacy `users.role` field remains synchronized for compatibility with existing views and data.

## Quality checks

```bash
composer test
vendor/bin/pint --test
composer audit --locked
npm audit --omit=dev
npm run build
```

CI runs all of these checks for pushes and pull requests.

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a correct HTTPS `APP_URL`.
- Configure a strong `ADMIN_PASSWORD` before seeding.
- Configure trusted proxies explicitly for the actual load balancer, if required.
- Run `php artisan migrate --force` during deployment.
- Run a supervised queue worker and scheduler.
- Cache configuration, routes, and views with `php artisan optimize`.
- Back up the database and test restores regularly.
- Generate new QR codes if `APP_KEY` changes because signed check-in URLs will become invalid.

## Notifications

Lifecycle email is delivered through Resend and SMS through Arkesel. Both are queued. Configure:

```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxx
MAIL_FROM_ADDRESS=events@example.com

ARKESEL_ENABLED=true
ARKESEL_API_KEY=xxxxx
ARKESEL_SENDER_ID=Attendance
ARKESEL_SANDBOX=false
```

Keep `ARKESEL_SANDBOX=true` until the sender ID and message flow have been tested. Run a supervised `php artisan queue:work` process and invoke `php artisan schedule:run` every minute in production. Event reminders are sent one day before an event; subscription warnings are sent seven days before expiry.
