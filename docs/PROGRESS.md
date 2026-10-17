# Project progress and handover

Last updated: **17 Oct 2026**. Read this first when picking the project back up. It records what has
been done, how changes are shipped, and what is still open. For how the system itself works, see
[SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md).

> This repository is **public**. Keep attendee names, phone numbers, emails, API keys and the server
> address out of this file and out of commits.

---

## 1. Where things stand

| | |
|---|---|
| Production | Running `main` at `8dbe2b9` (merge of PR #14), deployed 15 Oct 2026 |
| Open work | None in progress. Follow-ups are in [section 6](#6-open-items-and-next-steps) |
| Open PRs | Dependabot only: #3, #4, #5, #7, #12 (not reviewed yet) |
| Tests | 567 passing. CI green on `main` |

---

## 2. Environment at a glance

- **App:** Laravel 13, Livewire 4 / Alpine, Pest 4, Maatwebsite Excel, Tailwind, built with Vite.
- **Local:** `/Users/itofficer/attendance-app-git`, PHP 8.5, SQLite. The local `.env` has
  `ARKESEL_ENABLED=false` and **no Arkesel API key**, so real SMS work can't be done from this machine.
- **Production:** a single AWS Lightsail Ubuntu server.
  - App folder: `/var/www/attendance-app`, PHP 8.4 (`php8.4-fpm`), **PostgreSQL**.
  - Queue: supervisor group `attendance-worker:*`, running `--queue=badges,default`.
  - SSH user `ubuntu`, key `~/.ssh/lightsail_attendance`. The server address is in your Lightsail
    console and in `~/.ssh/known_hosts`; it is not written here because the repo is public.
  - Production secrets (Arkesel key, mail keys) live only in the server's `.env`.
- **SMS:** Arkesel. Only Ghana numbers are sent SMS (`PhoneNumberService::toArkeselFormat`).
  Recipients with foreign numbers get email instead.
- **GitHub:** `Asah-cloud/Attendance-app`. Pull requests go into `main`.

Tip: to make SSH shorter, add this to `~/.ssh/config` on your Mac:

```
Host attendance-prod
    HostName <server address>
    User ubuntu
    IdentityFile ~/.ssh/lightsail_attendance
```

Then `ssh attendance-prod` works everywhere below.

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

- Plain `php artisan test` **crashes partway through on this Mac** with "Allowed memory size of
  134217728 bytes exhausted" in an export test. That is a local PHP memory limit, not a test failure,
  and is why the `-d memory_limit=1G` form is used.
- Every behaviour change gets a test. For a bug, first write a test that fails without the fix.

### 3.3 Commit, push and open a PR

Commit messages are a short summary line, a blank line, then a few lines explaining why. Commits made
with Claude Code end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

```bash
git add -A
git commit -m "Summary line

Why the change was needed and what it does."
git push -u origin <branch>
gh pr create --base main --title "..." --body "..."
```

### 3.4 Wait for CI, then merge

```bash
gh pr checks <number> --watch     # CI: composer audit, tests, pint, npm audit, build
gh pr merge <number> --merge      # merge commits, not squash
```

CI also runs `composer audit`. A newly published security advisory can fail a PR that changed
nothing related, as happened on PR #11. The fix is to update just that package
(`composer update <package> --with-dependencies`), run the tests, and commit the `composer.lock` change.

### 3.5 Deploy

Deploying means running `deploy.sh` on the server. It **always deploys `main`**, so merge first.

1. **Check that no messages are still sending.** The deploy restarts the queue workers, so don't
   deploy while a bulk SMS or email send is going out:
   ```bash
   ssh -i ~/.ssh/lightsail_attendance ubuntu@<server> 'cd /var/www/attendance-app && php artisan tinker --execute="echo DB::table(\"jobs\")->count();"'
   ```
   `0` means it's safe.
2. **Run the deploy:**
   ```bash
   ssh -i ~/.ssh/lightsail_attendance ubuntu@<server> 'bash /var/www/attendance-app/deploy.sh'
   ```
   It pulls `main`, runs `composer install --no-dev`, `npm ci && npm run build`,
   `php artisan migrate --force` and clears the caches. It then fixes permissions, restarts the
   workers and reloads PHP-FPM. The line `ERROR The [public/storage] link already exists` is
   harmless.
3. **Check it landed:**
   ```bash
   ssh ... 'cd /var/www/attendance-app && git log --oneline -1'
   ```

### 3.6 Running a one-off script on production (data fixes)

Write the script as a file on your Mac and send it to tinker with `--execute="$(cat)"`:

```bash
ssh -i ~/.ssh/lightsail_attendance ubuntu@<server> \
  'cd /var/www/attendance-app && php artisan tinker --execute="$(cat)"' < my-script.php
```

What we learned doing this:
- Piping a file straight into `php artisan tinker` (without `--execute`) prints nothing and **runs
  nothing**.
- Don't put `use App\Models\...;` lines in the script. Tinker already loads those names, and the
  `use` lines cause a fatal error. Write out full names instead (`\App\Models\Participant::...`).
- Wrap the changes in `\Illuminate\Support\Facades\DB::transaction(...)`. Before changing anything,
  check the data is what you expect, and throw an exception if it isn't, so nothing is half-applied.
- Back up the rows you are about to change first, by saving them as JSON from a read-only query.
- Prefer the app's own services (`CustomMessageSender`, `ParticipantMergeService`, ...) over raw
  updates, so the usual history and side effects happen.

### 3.7 Working with Claude Code on this project

- `.claude/settings.local.json` (git-ignored through `.git/info/exclude`) allows `gh pr merge`
  without a prompt.
- In **auto mode**, Claude can read production but is blocked from writing to it, for example data
  fixes. For those, either run the command yourself in your **Mac** terminal (not one already
  logged into the server), or press Shift+Tab to leave auto mode and approve the command. In the
  Claude Code prompt, start a command with `!` to run it on your Mac.
- To let Claude SSH without prompts, add `Bash(ssh -i ~/.ssh/lightsail_attendance ubuntu@<server>:*)`
  under Allow in `/permissions`. Claude can't add this itself.

---

## 4. What has been done

### PR #8: Get CI passing (13 Oct)
CI made green so that every later PR is checked.

### PR #9: Ghana / Foreign / No-phone filter in the message recipient picker (13 Oct)
Buttons on the compose page to pick recipients by phone type. "Ghana" uses the same check as SMS
sending (`PhoneNumberService::isGhanaNumber`).

### PR #10: `npm ci` in deploy (13 Oct)
`deploy.sh` used `npm install`, which rewrote `package-lock.json` on the server. Switched to `npm ci`.

### PR #11: Filter message recipients by country (15 Oct)
- `PhoneNumberService::countryOf()` works out a phone number's country from its dialling code
  (`DIAL_CODES`, about 110 countries: all of Africa plus common ones elsewhere). Ghana reuses
  `isGhanaNumber()`. Nigerian local numbers `0[7-9][01]XXXXXXXX` are recognised. Anything else
  without a country code is shown as "Unrecognised country". +1 is labelled "USA / Canada".
- The compose page has a **"By country…"** dropdown with a count for each country.
- The same PR updated `symfony/http-foundation` 8.1.6 → 8.1.9 (CVE-2026-108082), because CI's
  `composer audit` started failing.

### PR #13: Stop an import row number from renaming a different person (15 Oct)
**The incident:** column A of an import becomes the participant's member ID
(`<company>:event<id>:<A>`). A second list for the same event, numbered from 1 again, matched the
first list's people by number whenever its phone or email matched nobody. That renamed them and kept
their old phone and email. On the AWC 2026 event (event 10) this overwrote 6 registrants from the
1 Oct list. Messages for the newcomers then went to the original people's phones.

**The fix** (`ParticipantRegistrationService::findExistingUser`): a member-ID match only counts when
the name also agrees, ignoring case, spacing and punctuation. Otherwise the row is a new person.
A matching phone or email still comes first.

Trade-off: re-importing a row with a corrected name and no phone or email creates a new record.
Correct names in the app instead.

### Data repair on production, event 10 (15 Oct, done by script)
- Restored the 6 overwritten records (participant #3552–#3557) to their 1 Oct details, taken from
  the "Extract" sheet of the 1 Oct registration file and from the message history.
- Added the 6 newcomers as their own records (#4065–#4070), using the details from the 15 Oct import
  files. One newcomer shares a phone with an existing participant of a different gender, which is
  intended.
- Every row of the three import files was checked against the event afterwards: 65 of 65 match.
- Re-sent the registration confirmation (original message #24) to the 6 newcomers as **message #28**.
  All were delivered: 5 by SMS, and 1 by email because of a foreign number.
- Merged a duplicate participant (#3978 into #840) with `ParticipantMergeService`.
- The 6 original people received message #24 under another person's name. **No correction was sent.**

### PR #14: Review rows that would rename someone, before importing (15 Oct)
- An upload is first run as a **trial** inside a transaction that is always rolled back
  (`EventController::runImport`). The trial skips room allocation, because that can message
  attendees straight away.
- If any row's phone or email belongs to someone registered under a different name, a pop-up on the
  attendance page lists those rows: **Update / New person / Skip** per row, with **New person** as
  the default, plus buttons to set every row at once. Rows that collide within the same file are
  caught too.
- Nothing is saved until **Import with these choices** (`events.import.confirm`) is pressed.
  **Cancel import** (`events.import.cancel`) discards the upload. Uploads are kept in
  `storage/app/private/participant-imports/` until then.
- Lists with no such rows import straight away as before. The success message now gives counts
  (added, updated, skipped).
- Tests: `tests/Feature/ParticipantImportReviewTest.php`.

### SMS cost check (14 Oct, no code change)
For the accommodation-form SMS to 409 people:
- The en dash (–) in "– AWC 2026 Secretariat" is not a GSM character. It switched the message to
  Unicode, so each part held only 67 characters: 5 parts, about 2,045 credits.
- With a normal hyphen and the trailing spaces removed, it would be 2 parts, about 818–1,227
  credits, depending on name length.
- `{name}` inserts the **full name**. `{first_name}` keeps messages shorter.
- Check the balance on the Arkesel dashboard, or from the server:
  `curl -s -H "api-key: <key>" https://sms.arkesel.com/api/v2/clients/balance-details`.
  The app does not check the balance before sending. It only emails admins after Arkesel starts
  rejecting messages (`ArkeselBalanceAlerter`).

---

## 5. Useful facts and gotchas

- **Import column order:** A ID, B Name (required), C Gender, D Room group, E Category, F Phone,
  G Email. The first row is a header.
- **How an imported row is matched to an existing person:** email, then phone (in the same company;
  someone with the same phone but a different gender counts as a separate person), then member ID
  **plus the same name**. See `ParticipantRegistrationService`.
- **Phones are stored normalised:** without the leading 0 and without 233 for Ghana
  (`0241234567` → `241234567`). Other countries keep their code (`+234803…` → `234803…`).
- **Merge-field tokens** in messages: `{name}` (full name), `{first_name}`, and the others in
  `App\Support\MergeFields`. The compose page's JavaScript mirrors this logic, so keep the two in step.
- **`participant_audit_logs`** only records edits made by hand in the app, not changes from imports.
  The recipient snapshots in the message history (`custom_message_recipients`) are useful for
  recovering old names and phone numbers.
- **`ParticipantMergeService`** doesn't move the duplicate's message-history rows. They stay, but are
  no longer linked to any participant.
- **The "By country" filter only affects who you can pick.** Who gets SMS and who gets email is
  unchanged.

---

## 6. Open items and next steps

1. **`package-lock.json` has uncommitted changes on the server**, left over from the old
   `npm install` deploys. A future PR that changes `package-lock.json` will make `git pull` in
   `deploy.sh` fail. Fix once on the server:
   `cd /var/www/attendance-app && git checkout -- package-lock.json`.
2. **Dependabot PRs #3, #4, #5, #7, #12** are open and unreviewed. #4 (Tailwind 3 → 4) and #5
   (spatie/laravel-permission 7 → 8) are major upgrades and need care.
3. **Optional correction SMS** to the 6 people who got message #24 under someone else's name.
4. **Check the import review pop-up in a browser** on the next real import. It is covered by tests,
   but nobody has looked at it yet.
5. **Possible improvement:** show the SMS character count, number of parts, and a GSM/Unicode
   warning (for example for "–", "’" or emoji) on the compose page, so costs like the 5-part
   message are visible before sending.
6. **Possible improvement:** a pre-send SMS balance check using Arkesel's `balance-details` endpoint.
