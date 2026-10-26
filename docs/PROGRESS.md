# Project progress and handover

Last updated: **24 Oct 2026**. Read this first when picking the project back up. It records what has
been done, how changes are shipped, and what is still open. For how the system itself works, see
[SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md).

> This repository is **public**. Keep attendee names, phone numbers, emails, API keys and the server
> address out of this file and out of commits.

---

## 1. Where things stand

| | |
|---|---|
| Production | Running `main` with PR #42, deployed 26 Oct 2026. `git log --oneline -1` on the server shows the exact commit |
| Open work | None in progress. Follow-ups are in [section 7](#7-open-items-and-next-steps) |
| Open PRs | Dependabot only: #3, #4, #5, #27, #32 (not reviewed yet) |
| Tests | 658 passing. CI green on `main` |
| Server working copy | Should be clean: `git status --short` on the server prints nothing |

---

## 2. Environment at a glance

- **App:** Laravel 13, Livewire 4 / Alpine, Pest 4, Maatwebsite Excel, Tailwind 3, Vite.
  SortableJS powers the room board.
- **Local:** `/Users/itofficer/attendance-app-git`, PHP 8.5, SQLite. The local `.env` has
  `ARKESEL_ENABLED=false` and **no Arkesel API key**, so real SMS work can't be done from this machine.
- **Production:** a single AWS Lightsail Ubuntu server.
  - App folder: `/var/www/attendance-app`, PHP 8.4 (`php8.4-fpm`), **PostgreSQL**.
  - Queue: supervisor group `attendance-worker:*`, running `--queue=badges,default`.
  - Scheduler: `/etc/cron.d/attendance-app-scheduler` runs `schedule:run` every minute.
  - SSH user `ubuntu`, key `~/.ssh/lightsail_attendance`. The server address is in your Lightsail
    console and in `~/.ssh/known_hosts`; it is not written here because the repo is public.
  - Production secrets (Arkesel key, mail keys) live only in the server's `.env`.
- **SMS:** Arkesel. Only Ghana numbers are sent SMS (`PhoneNumberService::toArkeselFormat`).
  Recipients with foreign numbers get email instead.
- **GitHub:** `Asah-cloud/Attendance-app`. Pull requests go into `main`.

### Server settings changed by hand (not in the repo)

| File | Setting | Value | Why |
|---|---|---|---|
| `/etc/nginx/nginx.conf` | `gzip_vary`, `gzip_proxied`, `gzip_comp_level`, `gzip_types` | uncommented | CSS/JS were sent uncompressed (~490 KB per page) |
| `/etc/nginx/sites-available/attendance-app` | `location /build/ { expires 1y; Cache-Control public, immutable }` | added | Phones re-downloaded assets on every page |
| same file | `client_max_body_size` | 32M (was 20M) | Flyer and list uploads |
| `/etc/php/8.4/fpm/php.ini` | `upload_max_filesize` / `post_max_size` | 12M / 32M (were 2M / 8M) | 5 MB flyers and 10 MB import lists |
| `/etc/php/8.4/fpm/pool.d/www.conf` | `pm.max_children` / `start_servers` / `min_spare` / `max_spare` | 12 / 3 / 2 / 5 (were 5 / 2 / 1 / 3) | Crowds checking in at once |

Each file has a `*.bak-YYYYMMDDHHMM` copy next to it, or in `/root/` for the nginx site file. Rebuild
these settings if the server is ever replaced.

Tip: to make SSH shorter, add this to `~/.ssh/config` on your Mac:

```
Host attendance-prod
    HostName <server address>
    User ubuntu
    IdentityFile ~/.ssh/lightsail_attendance
```

---

## 3. How we commit and deploy

Every change follows the same path. Never commit straight to `main`.

### 3.1 Make the change on a branch

```bash
git checkout main && git pull origin main
git checkout -b feature/<short-name>      # or fix/<short-name>, chore/<short-name>
```

### 3.2 Test locally before committing

```bash
php -d memory_limit=1G vendor/bin/pest     # full suite
vendor/bin/pint --test app tests routes    # code style (CI runs pint --test too)
composer audit --locked                    # CI fails on any security advisory
```

- Plain `php artisan test` **crashes partway through on this Mac** because the 128 MB PHP memory limit
  is too low. It is not a test failure; use the `-d memory_limit=1G` form instead.
- Every behaviour change gets a test. For a bug, first write a test that fails without the fix.

### 3.3 Look at it in a browser (for anything visible)

There is a **local demo** with a **test manager account** (`test-manager@demo.test`). Its data is a
"Demo Church" organisation with a two-day "Demo Retreat" (event id 1) whose 14 attendees have phones from
Ghana, Nigeria, the UK, Mali and Benin (plus one with no phone and one unreadable number) and check-ins on
both days. It lives in a scratch SQLite database, not the project's own, and is used to sign in and
screenshot pages with puppeteer driving the installed Chrome. The folder (`demo/` in a Claude Code scratch
folder under `/private/tmp/claude-501/`) holds `demo.sqlite`, `credentials.txt` (the login; never put it
in the repo), `seed.php` and `browser/` (puppeteer-core 23.11.1).

**macOS clears `/private/tmp`**: the first demo was lost this way on 26 Oct. To rebuild, in an empty
folder: create an empty `demo.sqlite`, run `php artisan migrate` against it, then
`DEMO_DIR=<folder> php artisan tinker --execute="$(cat seed.php)"`. It writes a fresh password to
`credentials.txt`. Keep a copy of `seed.php` outside `/private/tmp` if you want to be safe. Back up
`demo.sqlite` before changing data for a test, and restore it afterwards. It has no rooms set up; add
them when testing accommodation.

```bash
DB_CONNECTION=sqlite DB_DATABASE=<folder>/demo.sqlite APP_URL=http://127.0.0.1:8766 SESSION_DRIVER=file \
  CACHE_STORE=array MAIL_MAILER=log QUEUE_CONNECTION=sync php artisan serve --port=8766
```

- Run `npm run build` first, so the page uses the current CSS and JS.
- Never create test accounts on production. Real attendee data lives there.
- Pages with Livewire refresh themselves every few seconds, so wait for `load`, not for the
  network to go idle.
- SortableJS uses `forceFallback` (pointer events). Simulated mouse drags work with that; native
  HTML5 drag and drop doesn't in headless Chrome.

### 3.4 Commit, push and open a PR

Commit messages are a short summary line, a blank line, then a few lines explaining why. Commits made
with Claude Code end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

```bash
git add -A
git commit -m "Summary line

Why the change was needed and what it does."
git push -u origin <branch>
gh pr create --base main --title "..." --body "..."
```

### 3.5 Wait for CI, then merge

```bash
gh pr checks <number> --watch     # CI: composer audit, tests, pint, npm audit, build
gh pr merge <number> --merge      # merge commits, not squash
```

A newly published security advisory can fail an unrelated PR in `composer audit`. Update just that
package (`composer update <package> --with-dependencies`), test, and commit `composer.lock`.

### 3.6 Deploy

`deploy.sh` **always deploys `main`**, so merge first.

1. **Check no messages are still sending.** The deploy restarts the queue workers.
   ```bash
   ssh ... 'cd /var/www/attendance-app && php artisan tinker --execute="echo DB::table(\"jobs\")->count();"'
   ```
2. **Deploy:** `ssh ... 'bash /var/www/attendance-app/deploy.sh'`. It pulls, installs, runs
   `npm ci && npm run build`, migrates, clears caches, fixes permissions, restarts the workers and
   reloads PHP-FPM. The line `ERROR The [public/storage] link already exists` is harmless.
3. **Check it landed:** `git log --oneline -1` on the server should show the merge, and
   `git status --short` should print nothing.

### 3.7 One-off scripts on production (data fixes)

Write the script as a file on your Mac and send it to tinker with `--execute="$(cat)"`:

```bash
ssh -i ~/.ssh/lightsail_attendance ubuntu@<server> \
  'cd /var/www/attendance-app && php artisan tinker --execute="$(cat)"' < my-script.php
```

- Piping a file straight into `php artisan tinker` (without `--execute`) prints nothing and **runs nothing**.
- Don't put `use App\Models\...;` lines in the script. Tinker already loads those names, so the `use`
  lines cause a fatal error. Write out full names instead.
- Wrap the changes in `DB::transaction`, check the data first, and throw an exception if it isn't
  what you expect, so nothing is half-applied. Save the rows you change as JSON beforehand.
- Prefer the app's services (`CustomMessageSender`, `ParticipantMergeService`, ...) to raw updates.

### 3.8 Working with Claude Code

- `.claude/settings.local.json` (git-ignored through `.git/info/exclude`) allows `gh pr merge` without
  a prompt.
- In **auto mode**, Claude can read production but is blocked from writing to it. Leave auto mode
  (Shift+Tab) and approve the command, or run it yourself in a **Mac** terminal. In the Claude Code
  prompt, a command starting with `!` runs on the Mac.

---

## 4. Pitfalls we have hit (check for these)

- **Blade `@php(...)` followed by a later `@php … @endphp` in the same file** makes Blade swallow
  everything in between as PHP. Use the block form whenever a file has `@endphp` anywhere below.
  It has silently hidden page sections twice.
- **Listener methods named `handle…`** are registered twice, once by Laravel's discovery and once by
  an explicit `subscribe`. `RecordLoginActivity` deliberately uses other names.
  `LogResendEmailEvents` still has the problem, so email bounces are logged twice.
- **Counts from `withCount` are strings on SQLite** (tests) but integers on Postgres. Cast to `int`
  before strict comparisons such as `=== 0`.
- **`->select(...)` after `->withCount(...)` silently drops the count** (it comes back `null`). Call
  `select` first. This made every room look empty to automatic room assignment until it was fixed.
- **A Blade directive glued to a word is printed as text**, e.g. `free@if(...)` or `step-free@endif`.
  Put a space (or a tag) before the `@`. The room picker showed raw `@if(...)` code to attendees this way.
- **Read a room's gender/category with `effectiveGender()` / `effectiveCategory()`**, never
  `$room->gender_restriction ?: $room->floor->block->...`: that skips the floor's setting.
- **Room tests that start with empty rooms hide occupancy bugs.** When testing allocation, put someone
  in a room first.
- **`import` with row numbers in column A:** an ID alone only re-finds someone with the same name.
  A phone or email match with a different name opens the import review pop-up.
- **Spreadsheets with formula cells** are imported as the value Excel shows. If the name is still
  formula text, the row is skipped and counted.
- **How reports count (since #31):** Expected = confirmed attendees on every day. A day counts only once
  it has check-ins (or is today, during the event); empty days are left out of "days attended" and of
  averages. Average daily turnout = mean of each day's present ÷ expected. Live counters and reports
  only count check-ins of people still confirmed (`Attendance::countedFor`).
- **Category and area belong to each event's registration** (`event_registrations.category` /
  `room_group`, since #33). `$registration->participant` and an event's attendee lists show the
  event's values automatically (`Participant::useEventDetails`); the member directory shows the
  person's latest. Save category/area on the registration, never on the person, in event screens.
- **Objects stored in the cache must be listed** in `config/cache.php` → `serializable_classes`
  (Laravel 13). Tests use the array cache, which hides this; see the cache test in `ReportsAndExportsTest`.
- **Maatwebsite Excel drops an empty row (`[]`)** in `FromArray`. Use `['']` for a blank line.
- **Excel turns text that looks like a number into a number**: `+2348091000008` loses its "+" and shows
  as 2.34809E+12. Report sheets use `KeepsTextAsText` (with `WithCustomValueBinder`). And without
  `WithStrictNullComparison` a count of 0 is written as an empty cell.
- **Locally, the full test suite needs more memory** than this Mac's 128 MB PHP default:
  `php -d memory_limit=1G vendor/bin/pest`.
- **The server's `package-lock.json` must stay unmodified.** `deploy.sh` uses `npm ci`. Never run
  `npm install` on the server.

---

## 5. What has been done

Ordered by PR. #8–#14 (13–15 Oct) are covered briefly; everything since in more detail.

| PR | Date | Change |
|---|---|---|
| #8 | 13 Oct | CI made green |
| #9 | 13 Oct | Message recipients: Ghana / Foreign / No-phone filter |
| #10 | 13 Oct | `deploy.sh` uses `npm ci` |
| #11 | 15 Oct | Message recipients: filter by country (`PhoneNumberService::countryOf`); symfony/http-foundation security update |
| #13 | 15 Oct | Import: a row number alone no longer renames a different person |
| #14 | 15 Oct | Import: review pop-up (Update / New person / Skip) for rows that would rename someone |
| #15 | 17 Oct | This handover document |
| #16 | 17 Oct | An event's public link name follows its title; old links redirect (301) via `event_slug_redirects` |
| #17 | 17 Oct | Forms: up to 5 flyers (JPG/PNG/WebP, 5 MB each) |
| #18 | 17 Oct | Public form: two columns, with a flyer slideshow beside the form |
| #19 | 17 Oct | Shared form links preview with the form's first flyer (`og:image`) |
| #20 | 17 Oct | Form pages carry the organiser's logo and name, with only "Powered by Asah Apex Attendance" at the bottom |
| #21 | 17 Oct | Form page background drawn from the form's flyer |
| #22 | 17 Oct | **Response viewers:** share one form's responses by email, view and export only (`form_viewer` role, `ConfineFormViewers`) |
| #23 | 18 Oct | **Admin sign-in bell:** every sign-in and failed attempt logged (90 days), with pop-ups and an alert after 5 failures in 10 minutes |
| #24 | 19 Oct | **Self check-in switch per event:** attendees check in with their phone on the shared link (throttled; recorded as `method = self`) |
| #25 | 21 Oct | **Room plan import:** place a listed group N to a room; registers anyone not yet registered; preview first; spare beds stay open |
| #26 | 21 Oct | **Drag-and-drop room board** (SortableJS); warns before mixing genders |
| #28 | 21 Oct | Import reads formula cells as values; dashboards show "People on events" with total records underneath |
| #29 | 23 Oct | **Accommodation hub of cards** (each area on its own page); **room board redesigned** as a full-screen page in a new tab |
| #30 | 23 Oct | This handover document brought up to date |
| #31 | 24 Oct | **Reports rebuilt:** one counting engine (`AttendanceGrid`) for page, Excel, PDF and CSV; Simple / Detailed / Advanced views; a column per day in downloads; filters carry into downloads; old Summary page folded in |
| #33 | 24 Oct | **Category and area per event:** importing someone into a new event no longer rewrites an earlier event's category or area |
| #34 | 24 Oct | Room board: full rooms fold to a row of initials; All / Has space / Full filter; compact view; choices remembered per browser |
| #35 | 24 Oct | Room board: drop anywhere on a room card; tap a person then a room to place them; green/red highlight while dragging; hold-to-drag on touch screens |
| #36 | 24 Oct | **Tell people their rooms:** shortcut on the room board and Send to attendees; Messages opens pre-filled; placeholders `{room}`, `{building}`, `{floor}`, `{location}`, `{room_link}`; a sent room message marks people as told, a board move marks them not told |
| #37 | 24 Oct | Messages always recognises the room placeholders (it wrongly warned on events with rooms but accommodation switched off) |
| #38 | 24 Oct | **Automatic room assignment and room picker fixed**, see below |
| #39 | 25 Oct | **Gender and category per floor** (new columns on `accommodation_floors`), alongside buildings and rooms; a room's own setting wins, then its floor's, then its building's. Saving a building, floor or room says how many people already there no longer fit (they are not moved). Category boxes suggest the event's categories. The room board shows a floor's gender. Fixed "Buildings &amp;amp; rooms" in the inventory heading |
| #40 | 26 Oct | **Reports by country:** a country filter and "By country" split in the Detailed view, a country summary download and a download per country, a Country column in the attendee sheets, and "By country" in the Excel summary and PDF. Country comes from the phone number. Downloads now keep the "+" on international numbers and write zero counts as 0 |
| #41 | 26 Oct | **Rooms never mix genders when placed by hand** (room board and Guests list): a room with someone of the other gender refuses, with no "anyway" option; an empty room kept for the other gender (by room, floor or building) switches its own setting to the person's gender, and the board badge updates. Category and step-free settings stay overridable (`RoomAllocationService::manualGenderCheck`) |
| #42 | 26 Oct | Vite 8.2.1 → 8.3.5 (`npm audit fix`, lock file only): clears 7 build-tool advisories (Vite, launch-editor) that made the weekly health check report "attention needed" since 19 Oct |

### Room assignment fixes (#38)
Found in a system check on 24 Oct and confirmed with tests and on the local demo.
- **Every room looked empty to the allocator.** `RoomAllocationService::eligibleRooms` called `select()`
  after `withCount()`, which dropped the occupancy count. "Assign rooms now" proposed full rooms, then
  skipped those people, so they stayed unplaced while other rooms had free beds. The same bug hit
  placement on registration (`allocateOne`), the room plan import and the attendee room picker.
  **After deploying, run "Assign rooms now" again on live events** with people still waiting.
- **Mixed genders.** Automatic assignment only respected explicit gender restrictions, so an
  unrestricted room could take men and women. A room's gender now comes from its restriction, else its
  occupants (`roomGender`), as the room plan import already did.
- **Rooms fill before new ones open.** Before, everyone got an empty room first and the rest were
  spread over whatever beds were left. Room groups still go together, and a new group starts a fresh room.
- **"Preview selection" ignored the dropdowns.** It was a link built at page load. It now submits the
  same form (`preview=1`) and the controller redirects to the preview without assigning anyone. After
  a run, the page shows the selection that ran, never a stale preview.
- **Attendee room picker** hides rooms where someone of the other gender is staying, and the claim
  rechecks it under the room lock. Attendees with no gender recorded can still choose any room. The
  picker also printed raw Blade (`free@if(...)`) on every room line; fixed.

### Data fixes on production (done by script)
- **15 Oct, AWC 2026 (event 10):** restored 6 records overwritten by a row-number import
  (#3552–#3557), added the 6 newcomers as their own records (#4065–#4070), resent registration
  confirmation (message #28) to the newcomers, and merged a duplicate participant (#3978 into #840).
- **17 Oct:** the AWC event's link name changed from `book-launch` to
  `apostolic-world-conference-2026`. The old link redirects.
- **19 Oct:** deleted 515 junk participants whose names were spreadsheet formulas (IDs #2002–#2516),
  left over from a 1 Oct import. They had no registrations, attendance or messages. The backup JSON was
  kept outside the repo.

### Other
- **SMS costs:** a single en dash (–) or curly quote switches an SMS to Unicode (67 characters per
  part instead of 153). `{name}` inserts the full name; `{first_name}` keeps messages shorter.
- **TAC Minister's Orientation (19 Oct):** a cleaned registrant CSV and a ministers-and-spouses Excel
  sheet were prepared from a Google Form export (kept on the organiser's machine, not in the repo).

---

## 6. Feature map (where to find things)

| Area | Code |
|---|---|
| Participant import | `UsersImport`, `ParticipantListImporter`, `ParticipantRegistrationService`, `EventController::import/confirmImport` |
| Automatic room assignment | `RoomAllocationService::preview/commit/pickRoom` (bulk), `allocateOne` (on registration), `AccommodationController::allocate`, `accommodation/sections/assign.blade.php` |
| Attendee room picker | `RoomAllocationService::selectableRooms/claim`, `PublicEventRegistrationController::roomSelect/roomClaim`, `registrations/room-select.blade.php` |
| Room plan | `RoomPlanController`, `RoomAllocationService::planBatch/commitPlan`, `accommodation/plan.blade.php` |
| Room messages | `MergeFields` (room placeholders), `CustomMessageController`, `accommodation/_tell-rooms.blade.php` |
| Room board | `RoomBoardController`, `resources/js/room-board.js`, `accommodation/board.blade.php` |
| Accommodation hub | `AccommodationController::index/section`, `accommodation/index.blade.php`, `accommodation/sections/*` |
| Forms | `EventFormController`, `PublicFormController`, `FormViewerController`, `SharedFormController` |
| Check-in | `AttendanceController` (`checkInByPhone`, `selfCheckIn`), `attendance/public-scan.blade.php` |
| Sign-in bell | `RecordLoginActivity`, `SignInActivityController`, `components/sign-in-bell.blade.php` |
| Event links | `Event::uniqueSlug/findBySlug`, `EventSlugRedirect` |
| Attendance reports | `AttendanceGrid` (all counting; `area()` and `country()` for the splits), `GroupAttendanceSummaryExport` (area or country summary), `AttendanceInsights` (Advanced view), `ReportController`, `reports/views/{simple,detailed,advanced}`, `AttendanceWorkbook` + sheets in `app/Exports`, `reports/attendance-pdf.blade.php` |

---

## 7. Open items and next steps

1. **After #38: run "Assign rooms now" again** on live events that have people waiting for a room; the
   old bug may have left them unplaced.
2. **Dependabot PRs #3, #4, #5, #27, #32** are open and unreviewed. #4 (Tailwind 3 → 4) and #5
   (spatie/laravel-permission 7 → 8) are major upgrades and need care.
3. **`LogResendEmailEvents` logs every email event twice** (listener discovery plus `subscribe`).
   Rename its methods the way `RecordLoginActivity` does.
4. **JavaScript errors on every signed-in page** (not caused by any recent change; seen with Vite 8.2
   and 8.3): `Cannot redefine property: $persist`, which usually means Alpine is started twice (the
   app's own `alpinejs` plus the copy Livewire 4 ships), and a malformed SVG icon path
   (`Expected arc flag`). Pages and the room board still work. Check `resources/js/app.js`.
5. **Lighter public pages:** public pages load the whole app bundle (~123 KB compressed), including
   admin and scanner code. A separate small bundle would make first loads on phones faster.
6. **236 older participant records on no event** (from the Sept set-up). Some duplicate registered
   people; tidy them with Merge Duplicates.
7. **Optional:** a correction SMS to the 6 people who got message #24 under someone else's name (15 Oct).
8. **Unused component with a broken link:** `components/qr-print-button.blade.php` points to a route
   `events.print-qr` that doesn't exist. Nothing uses it, or the view `events/print-qr.blade.php`, today;
   delete both or wire them up before using them.
9. **Naming:** the product now covers registration, check-in, meals, rooms, messaging and billing, so
   "event management system" describes it better than "attendance app". The README still says
   "attendance system".
10. **Possible improvements:** an SMS length and Unicode warning on the compose page; a pre-send
    Arkesel balance check; an email digest of new form responses; an "Are you sure?" step before
    "Assign rooms now".
