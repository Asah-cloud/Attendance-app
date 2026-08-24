# Attendance App — System Documentation

| | |
|---|---|
| **Document type** | System / architecture reference |
| **Status** | Living document — update alongside significant changes |
| **Owner** | Engineering |
| **Last updated** | 2026-08-21 |

## 1. Purpose and scope

The Attendance App is a multi-tenant SaaS platform for organizations ("companies") to run public event registration, QR-based check-in, manual attendance tracking, and post-event reporting. It replaces manual sign-in sheets and spreadsheet reconciliation with a single system that a company's managers and ushers can operate per event, while a cross-company super-admin manages tenancy, billing state, and platform-wide oversight.

This document describes the system as it exists in the codebase today: architecture, domain model, authorization model, feature-by-feature behavior, integrations, operational procedures, and the engineering practices (testing, CI, deployment) that govern changes to it. It is derived directly from the source in this repository, not from design intent — where the two diverge, the code is authoritative and this document should be corrected.

## 2. Technology stack

| Layer | Technology |
|---|---|
| Backend framework | Laravel 13 (PHP 8.3+) |
| Interactive UI | Livewire 4 |
| Database | PostgreSQL (production); SQLite (automated tests) |
| Styling | Tailwind CSS |
| Spreadsheet import/export | Maatwebsite Laravel Excel (PHPSpreadsheet) |
| PDF generation | barryvdh/laravel-dompdf |
| QR codes | simplesoftwareio/simple-qrcode |
| Authorization | spatie/laravel-permission (roles/policies) |
| Email | Resend (`resend/resend-laravel`), queued |
| SMS | Arkesel, via a custom notification channel, queued |
| Testing | Pest 4 (`pestphp/pest`, `pest-plugin-laravel`) |
| Code style | Laravel Pint |
| CI | GitHub Actions (`.github/workflows/ci.yml`) |

## 3. Architecture overview

The application is a monolithic Laravel app (server-rendered Blade + Livewire islands for interactive components such as attendance search and walk-in registration). There is no separate SPA/API layer — all routes in [routes/web.php](../routes/web.php) serve HTML, redirects, file downloads, or JSON responses consumed by Livewire.

```
Browser
  │
  ├─ Public routes (no auth): landing, pricing, event registration,
  │  registration confirmation/cancel, QR check-in, hard-copy confirm
  │
  ├─ Authenticated routes (auth + verified + company.active middleware):
  │  dashboard, events, attendance, reports, admin, billing, organization
  │  branding, participant de-duplication
  │
  └─ Super-admin routes (role:admin): company CRUD, company history
         │
         ▼
   Controllers ──▶ Services (business logic) ──▶ Eloquent models ──▶ PostgreSQL
         │                  │
         │                  └─▶ Notifications (queued) ──▶ Resend (email) / Arkesel (SMS)
         │
         └─▶ Policies (per-model authorization) + role middleware
```

Key architectural decisions:

- **Multi-tenancy is row-scoped, not schema- or database-per-tenant.** Every tenant-owned row (`events`, `participants`, etc.) carries a `company_id`, and authorization policies — not global query scopes — are responsible for preventing cross-company access. See [§5](#5-authorization-model).
- **Business logic that spans a transaction or has side effects beyond a single model lives in `app/Services`**, not in controllers or model events, e.g. `RegistrationLifecycleService` (approve/reject/cancel/waitlist promotion), `ParticipantMergeService` (deduplication), `ParticipantRegistrationService`, `ConfirmationReminderSender`, `ArkeselBalanceAlerter`, `ApplicationCache`.
- **Participants are decoupled from user accounts.** A `Participant` (an attendee) is a separate model from a `User` (someone who can log in). A participant may optionally be linked to a user account (`linked_user_id`) but does not require one — this is what allows public registration and hard-copy/offline attendees to be tracked without provisioning login credentials for every attendee.
- **Notifications are channel-abstracted.** `App\Notifications\Concerns\NotifiesPerChannel` and `UsesAttendanceChannels` decide per-notifiable whether to send email, SMS (via the custom `ArkeselChannel`), or both, based on what contact info is available.

## 4. Domain model

### 4.1 Core entities

```
Company (tenant)
  ├─ has many Users (admin / manager / usher)
  ├─ has many Events
  ├─ has many Participants
  └─ has many SubscriptionPayments

Event (belongs to Company)
  ├─ has many Attendances
  ├─ has many EventRegistrations ──▶ belongsToMany Participants (pivot: event_registrations)
  ├─ has many EventRegistrationFields (custom registration form fields)
  └─ belongsToMany Users via event_staff (usher assignment)

Participant (belongs to Company; optionally linked to a User)
  ├─ has many EventRegistrations
  ├─ has many Attendances
  └─ has many ParticipantAuditLogs

Attendance (belongs to Participant, belongs to Event)
  — one row per participant, per event, per day

EventRegistration (belongs to Event, belongs to Participant)
  — the record of a participant's registration/RSVP lifecycle for one event
```

### 4.2 Notable model behavior

- **`Event::getStatusAttribute()`** derives `upcoming` / `active` / `closed` / `cancelled` from `event_date`, `end_date`, and `cancelled_at` rather than storing a status column — status is always computed, never stale.
- **`Event::canMarkAttendanceForDay()`** only allows marking attendance for a day that has arrived and while the event is `active`, preventing back-dated or future check-ins outside the run of the event.
- **Multi-day events**: `event_date` and `end_date` define the span; `Attendance.day` (1-indexed) records which day of the event a check-in belongs to. `totalDays()` / `currentDay()` compute the day count and the current day from today's date.
- **`EventRegistration`** is a state machine over: `pending → confirmed → cancelled/rejected`, plus `waitlisted` and `awaiting_confirmation` (used for the hard-copy/offline confirmation flow). A random 40-character `registration_code` is generated on creation and is the public, unguessable identifier used in confirmation/cancel/check-in URLs (see [§5.2](#52-signed-and-token-based-public-access)).
- **`Company` subscription fields** (`subscription_ends_at`, `subscription_auto_renews`, `plan_key`, `plan_price_minor`, etc.) drive the billing/access-gating logic in `EnsureCompanyIsActive` — when the subscription date changes, the model's `booted()` hook clears previously-sent expiry-warning/expired-notice timestamps so the lifecycle notices re-fire correctly for the new date.
- **Soft deletes**: `Company` uses `SoftDeletes` so archiving a tenant (§6.9) preserves its events, participants, and history instead of destroying them.

### 4.3 Schema evolution

The schema went through several deliberate phases visible in `database/migrations`, most notably:

1. Single-tenant core (`events`, `attendances`, `event_user`) with `role` on `users`.
2. Multi-tenancy retrofit: `companies` table, `company_id` added to `users`.
3. Registration/billing overhaul (2026-08-14 batch): `event_registrations`, `event_registration_fields`, `subscription_payments`; `users.event_id` (a legacy one-user-one-event assignment) removed in favor of the `event_registrations` pivot.
4. **Participant/user separation** (`2026_08_14_000008_separate_participants_from_user_accounts.php`): attendees became first-class `participants` rows instead of `users` rows, enabling registration without an account.
5. Data-integrity hardening (2026-08-13/08-20 batch): `harden_attendance_schema`, `remove_redundant_attendance_columns`, unique constraints, and `participant_audit_logs` for change tracking.

Because of step 3/4, a one-off backfill command exists — see [§8.4](#84-one-off-data-migration-command).

## 5. Authorization model

### 5.1 Roles

Roles are managed by Spatie Laravel Permission and mirrored onto the legacy `users.role` column for backward compatibility with older views/queries. Three roles exist:

| Role | Scope | Summary |
|---|---|---|
| `admin` | Platform-wide | Super-admin. Bypasses all policy checks (`before()` hook returns `true`). Manages companies (create/edit/archive/restore/permanently delete), sees platform-wide dashboard stats. |
| `manager` | Own company only | Full control over their company's events, users, participants, registration forms, billing, branding, and de-duplication tooling. Cannot see other companies' data. |
| `usher` | Assigned events only | Can view and scan attendance only for events they are explicitly assigned to via the `event_staff` pivot. |

### 5.2 Signed and token-based public access

Public-facing flows do not require authentication and instead rely on unguessable tokens/signed routes rather than login:

- **Event registration** (`/events/{event}/register`) is public by design — anyone with the link can register if `Event::registrationIsOpen()`.
- **Registration confirmation/cancellation** (`/registrations/{code}/confirmation`, `/registrations/{code}/cancel`) uses the random 40-character `registration_code`, not the numeric ID, as the lookup key.
- **QR check-in** (`/check-in/{code}`) and **hard-copy confirmation** (`/confirm/{code}`) work the same way — the code is embedded in a generated QR image/link.
- All public POST endpoints are rate-limited (`throttle:10,1` for registration/cancel/confirm actions, `throttle:30,1` for check-in, `throttle:60,1` for the authenticated scanner) to blunt brute-force or abuse attempts against these unauthenticated endpoints.

**Operational implication**: rotating `APP_KEY` invalidates any signed URLs in circulation and requires regenerating and reprinting/re-distributing QR codes (documented in the README production checklist).

### 5.3 Policies and middleware

- **`EventPolicy`** is the authorization source of truth for event access: `admin` bypasses everything; `manager` may act on events only within their own `company_id`; `usher` may only `view`/`scanAttendance` on events they're explicitly staffed on.
- **`UserPolicy`** governs admin-user-management actions similarly (company-scoped for managers).
- **`EnsureCompanyIsActive`** middleware (applied to `/dashboard` and the main authenticated route group) blocks access for suspended companies (`is_active = false`, 403) and redirects to billing when the subscription has lapsed — except for `admin`, who is exempt.
- Route-level `role:` middleware (from Spatie) gates entire route groups (e.g., billing and organization branding are `role:manager`; company CRUD is `role:admin`).

## 6. Features

### 6.1 Multi-tenant company management (super-admin)

Admins create/edit companies (`SuperAdmin\CompanyController`), optionally uploading a logo, and set plan/subscription details. Archiving a company soft-deletes it (`4d4fb47`) rather than destroying its data; `CompanyHistoryController` lets an admin browse an archived company's events/attendees, restore it, or permanently delete it. The platform-wide dashboard excludes archived companies' data from aggregate stats (`90e0b07`).

### 6.2 Events and multi-day attendance

Managers/admins create events (`EventController`) with a date range (`event_date`/`end_date`), optional logo and flyer, and description/location. Attendance is tracked per participant, per event, **per day** — `Attendance.day` plus a unique constraint prevents double-marking the same participant on the same day. Attendance can only be marked for days that have arrived and while the event is active (`Event::canMarkAttendanceForDay`).

### 6.3 Public event registration

If a manager enables registration on an event (with optional open/close window, capacity, and approval requirement), the public registration page (`PublicEventRegistrationController`, backed by `ParticipantRegistrationService`) accepts sign-ups, matching or creating a `Participant`. Registrations start `pending` (if approval is required) or `confirmed`; `EventRegistrationField` lets a manager add custom fields (beyond the built-in name/email/phone/etc.) to the form, stored as `custom_answers` (JSON) on the registration.

`RegistrationLifecycleService` owns all state transitions:
- **Approve**: capacity-checked under a row lock (`lockForUpdate`) inside a transaction to prevent overselling under concurrent approvals.
- **Reject / Cancel**: if the freed registration was `confirmed`, automatically promotes the oldest `waitlisted` registration into its place (`fillAvailablePlaces`), also lock-protected.
- **Event changed / cancelled**: bulk-notifies all active registrants in chunks of 100 to avoid loading the full registrant list into memory.

Every transition triggers a `RegistrationLifecycleNotification` (email/SMS per §7).

### 6.4 QR-based check-in

Each participant's registration produces a unique QR code (their `registration_code`) that resolves to `/check-in/{code}`, letting staff scan a phone/badge to check someone in without manual lookup. A dedicated in-app **scanner** view (`/events/{event}/scanner`) posts scans to a throttled endpoint (`AttendanceController::scan`).

### 6.5 Manual attendance and walk-ins

For attendees without a prior registration, staff can search existing participants (`AttendanceSearch` Livewire component) or add a walk-in on the spot (`AddWalkInModal` Livewire component), then mark/unmark attendance manually (`AttendanceController::store` / `destroy`).

### 6.6 Hard-copy / offline attendee confirmation

For organizations that collect attendee lists outside the system (e.g., a physical sign-up sheet or an existing membership spreadsheet), `AttendanceConfirmationController` lets a manager import a contact list (`HardCopyContactsImport`), customize a welcome/confirmation message, and bulk-send a confirmation request (email/SMS) containing a personal link (`/confirm/{code}`) the attendee uses to confirm their own attendance online — bringing offline attendees into the same digital record without requiring them to fill out the full registration form. `ConfirmationReminderSender` (run daily at 09:00, §8.3) follows up on confirmations that haven't been actioned.

### 6.7 Participant import and de-duplication

- **Import**: `EventController::import` (spreadsheet, via `UsersImport`) bulk-registers participants into an event from a fixed column layout (documented in the README): column A = external/member ID, B = name, E = category, F = phone.
- **De-duplication**: `ParticipantMergeController` + `ParticipantMergeService` let a manager compare two participant records and merge one into the other. The surviving record keeps its own data but back-fills blank fields from the duplicate; registrations/attendances move over unless the survivor already has one for the same event/day (in which case the duplicate's is discarded to respect the unique constraint); every merge is recorded in `ParticipantAuditLog`.

### 6.8 Reporting and exports

`ReportController` and `SummaryReportController` provide per-event and per-day attendance views with export to **Excel** (`AttendanceExport`), **CSV**, and **PDF** (dompdf). `8c31dc0` added gender/category breakdowns to reporting. Printable attendee badges (`4759873`) are generated from `EventRegistrationFormController::badges`.

### 6.9 Billing and subscriptions

`BillingController` handles plan selection, a test-payment checkout flow, contact updates, and cancel/resume of auto-renewal. `SubscriptionPayment` records payment history per company. Subscription expiry is enforced by `EnsureCompanyIsActive`, and two scheduled jobs (§8.3) proactively warn managers 7 days before expiry and notify them once expired.

### 6.10 Organization branding

Managers can upload a company logo and, per event, an event logo/flyer (`OrganizationBrandingController`, `b3f23d0`–`f66052b`); the flyer can be set as the background of the public confirmation page (`fde82c2`). Logos are forced to absolute URLs when embedded in emails (`bbdf347`) since relative paths don't resolve in an email client.

### 6.11 Notification delivery observability

`resend/resend-laravel`'s signed webhook (`POST /resend/webhook`, secured by `RESEND_WEBHOOK_SECRET`) reports bounce/complaint/failure/delay events, logged by `App\Listeners\LogResendEmailEvents` (`4d220e6`) so delivery problems are visible in the configured log channel rather than silently disappearing. `ArkeselBalanceAlerter` / `ArkeselBalanceLow` similarly warn when the SMS provider's balance runs low.

### 6.12 SEO and public-page hygiene

`a21e5b5` added `sitemap.xml` and locked down `robots.txt`/`noindex` so that tenant-specific and transactional pages (registration forms, confirmation pages, etc.) aren't indexed by search engines while the marketing pages remain discoverable.

## 7. Notifications architecture

| Notification | Trigger |
|---|---|
| `RegistrationLifecycleNotification` | Registration approved / rejected / cancelled / promoted from waitlist / event changed / event cancelled / day-before reminder |
| `EventRegistrationSubmitted` | Sent to the registrant on initial sign-up |
| `AttendanceConfirmationRequest` | Hard-copy attendee confirmation request and reminder |
| `CompanySubscriptionNotification` | 7-day expiry warning and post-expiry notice, sent to the company's managers |
| `ArkeselBalanceLow` | Internal/ops alert when the SMS provider balance is low |

All notifications are queued (`php artisan queue:work` must run in production) and channel selection is delegated to `NotifiesPerChannel` / `UsesAttendanceChannels`, which send email via Resend, SMS via the custom `ArkeselChannel`, or both depending on what contact data the notifiable (a `Participant` or `User`) has. Both models implement `routeNotificationForArkesel()`, which normalizes phone numbers to the `233…` (Ghana) international format expected by Arkesel.

## 8. Scheduled jobs

Defined in [routes/console.php](../routes/console.php), all guarded with `withoutOverlapping()`:

| Job | Schedule | Purpose |
|---|---|---|
| `send-event-reminders` | Hourly | Notifies confirmed registrants for events happening tomorrow (once per registration, tracked via `reminder_sent_at`) |
| `send-subscription-lifecycle-notices` | Daily at 08:00 | Sends the 7-day expiry warning and the post-expiry notice to company managers |
| `send-confirmation-reminders` | Daily at 09:00 | Follows up on unactioned hard-copy attendance confirmation requests (`ConfirmationReminderSender`) |

Requires `php artisan schedule:run` invoked every minute in production (typically via cron or a supervised process), plus a running queue worker since the notifications themselves are queued.

### 8.4 One-off data migration command

`php artisan participants:migrate-registrations` (`App\Console\Commands\MigrateParticipantRegistrations`) was written to backfill `event_registrations` from the legacy `users.event_id` assignment column and from existing `attendances` rows, during the Phase 3 schema migration (§4.3). It supports `--dry-run` (audit only), `--rollback` (remove only registrations it created, tagged by `source`), and `--report=` (custom JSON report path), and self-detects completion (exits immediately once `users.event_id` no longer exists). It is retained in the codebase as a reference/audit tool and safety net, not part of the normal deploy path.

## 9. Testing and quality assurance

The Pest test suite (`tests/Feature`, `tests/Unit`) covers, among others: authentication flows, multi-tenant authorization boundaries (`TenantAuthorizationTest`), event management, public and admin registration flows, QR/personal check-in, hard-copy confirmation, participant merging, the legacy data-migration command, reports/exports, billing, company archive/history, dashboards, notification lifecycle, the Resend webhook, and SEO output — 30+ feature test files in total.

```bash
composer test           # php artisan test (config cache cleared first)
vendor/bin/pint --test  # code style check, no changes
composer audit --locked # PHP dependency vulnerability audit
npm audit --omit=dev    # JS dependency vulnerability audit
npm run build            # asset build must succeed
```

**CI** (`.github/workflows/ci.yml`) runs on every push and pull request against PHP 8.4 / Node 22: `composer install`, `composer audit --locked`, the full Pest suite, `pint --test`, `npm ci`, `npm audit --omit=dev`, and `npm run build`. All of these must pass before a change is considered mergeable — there is no separate staging gate beyond CI today.

## 10. Deployment and operations

### 10.1 Deployment

Production deployment targets a single Lightsail server and is scripted in [deploy.sh](../deploy.sh): pull `main`, `composer install --no-dev --optimize-autoloader`, install/build frontend assets, `php artisan migrate --force`, clear config/route/view caches, ensure the storage symlink, fix ownership/permissions for `www-data`, restart the supervised queue-worker group, and reload PHP-FPM (to clear opcache). This is a manual/SSH-invoked script, not yet a CI/CD pipeline step.

### 10.2 Production checklist (see README for the authoritative copy)

- `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL`.
- Strong `ADMIN_PASSWORD` set before seeding (never rely on the local fallback).
- Trusted proxies configured for the real load balancer if applicable.
- Migrations run with `--force`; config/routes/views cached via `php artisan optimize`.
- Supervised queue worker and cron-driven scheduler running.
- Regular database backups with tested restores.
- Regenerate/redistribute QR codes if `APP_KEY` ever changes (signed URLs break otherwise).

### 10.3 Configuration reference

```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxx
MAIL_FROM_ADDRESS=events@example.com
RESEND_WEBHOOK_SECRET=whsec_xxxxx   # required wherever the webhook URL is reachable

ARKESEL_ENABLED=true
ARKESEL_API_KEY=xxxxx
ARKESEL_SENDER_ID=Attendance
ARKESEL_SANDBOX=false               # keep true until sender ID/flow are verified
```

## 11. Local development setup

```bash
composer install
copy .env.example .env
php artisan key:generate
# configure DB/mail in .env, set ADMIN_PASSWORD
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Or, to run the app server, queue worker, and Vite dev server together:

```bash
composer dev
```

## 12. Development history summary

Grouped by theme, from the git log (oldest → newest):

1. **Foundation** — initial Laravel app, dependency/config baseline.
2. **Multi-tenancy core** — companies, roles, authorization policies, tenancy-aware schema.
3. **Attendance core rework** — multi-day events and participants.
4. **Public registration** — self-service event sign-up flow.
5. **Billing & onboarding** — plans, checkout, self-service onboarding.
6. **Notifications** — email/SMS lifecycle framework.
7. **Branding & superadmin tooling** — company management, organization branding, refreshed dashboard/marketing UI.
8. **Hardening & data-model cleanup** — participant/user separation, attendance schema hardening, feature-test expansion, Resend webhook logging.
9. **Growth features** — company logos, event logos/flyers, imported-participant confirmation emails, deploy tooling.
10. **SEO & platform polish** — sitemap/robots, archived-company exclusion from stats, company archive/history/restore.
11. **Editing & reporting depth** — attendee editing, gender/category reporting, hard-copy attendees online.
12. **Reliability & UX finishing** — reliability/data-cleanup/reporting improvements, redesigned printable attendee badges (current HEAD).

## 13. Known gaps / areas for follow-up

These are observations from reading the current code, not commitments — flag before treating as a backlog:

- **Deployment is a single-server SSH script**, not an automated CI/CD pipeline; there's no documented rollback procedure beyond re-running `deploy.sh` against a prior commit.
- **`MigrateParticipantRegistrations`** is a one-time backfill command left in the codebase; once confirmed fully obsolete in all environments it could be removed, but should not be removed while any environment might still have the legacy `users.event_id` column.
- **Signed-URL/QR invalidation on `APP_KEY` rotation** is an operational trap noted in the README but not automated or alerted on — nothing currently detects or warns if this happens.
- No API layer exists for third-party/headless integration; all functionality is delivered through server-rendered routes.
