# Superadmin-managed password access

This is the current contribution-branch authentication guide. It supersedes the
email-link instructions in earlier milestone reports. No email or Mailpit step
is needed for LiT login; shared Drupal mail infrastructure remains installed.

## Private initial setup

The verified existing sole owner is **ree32, UID 4**, with email
**ree32@scarletmail.rutgers.edu**. UID 1 remains emergency Drupal administration.
The owner's real password has not been changed by this implementation.

Edit `/Users/rodolfoespinosanunez/Documents/Rutgers/LIT/lit-map/.env` privately
in your editor. Its email is already configured; fill in
`LIT_SUPERADMIN_INITIAL_PASSWORD` yourself. Never send that password in chat,
put it in a command argument, or commit it. The file has mode 0600 and is ignored,
as are root `.env*` backups; `.env.example` contains placeholders only.

Passwords must be 16–128 bytes, include letters and digits, and have either
three character classes or four distinct words separated by spaces/hyphens.
Avoid leading/trailing whitespace. Then run from the project directory:

```sh
ddev start
ddev drush php:script scripts/bootstrap-superadmin.php
```

This explicit command reads only the two allowlisted keys from the mounted
`/var/www/html/.env` in local DDEV CLI mode. It does not evaluate shell syntax,
interpolate variables, or inject credentials into web requests. Real process
environment values take precedence. No DDEV restart is needed after editing the
file: the next command reads it again. Outside DDEV, deployment must inject the
two environment values; no local-file fallback is used.

Bootstrap reuses the matching account and rejects conflicting ownership or
ambiguous emails. After successful bootstrap, rerunning it does not reset the
password, even if the initial-password value changes. Remove that value from
`.env` after success. Editing `.env` alone never changes credentials.

For an intentional owner password reset, privately set the same keys and run:

```sh
ddev drush php:script scripts/reset-superadmin-password.php
```

This explicitly changes the password and revokes that account's sessions. The
normal bootstrap cannot transfer ownership. Server-only recovery of the verified
UID 4, if its designation/activation needs repair, is:

```sh
ddev exec env LIT_CONFIRM_OWNER_RECOVERY=yes LIT_OWNER_UID=4 vendor/bin/drush php:script scripts/recover-contribution-owner.php
```

Then use the explicit password-reset command. Do not use recovery to silently
merge identities. UID 1 retains normal core emergency access.

## Local browser walkthrough and URLs

All paths below use `https://rutgers-lit-m1.ddev.site`:

| Purpose | Path |
| --- | --- |
| Three-choice landing | `/lit` |
| Public map | `/lit-map/contribution-preview` |
| Superadmin email/password login | `/lit/superadmin/login` |
| Administrator email/password login | `/lit/administrator/login` |
| Owner dashboard | `/admin/lit` |
| Manage administrators | `/admin/people/lit-administrators` |
| Pending reviews | `/admin/content/lit-contributions/pending` |
| Publish | `/admin/content/lit-contributions/publish` |
| Invitation settings | `/admin/config/lit-contributions` |
| Resources | `/admin/config/lit-contributions/resources` |
| Reconciliation | `/admin/content/lit-contributions/reconciliation` |

After private bootstrap, sign in on the superadmin page with the configured
email and your password. The dashboard links to all owner actions. On Manage
administrators, enter a new administrator email and assign a password, or use
Generate strong password. Copy it privately before Save; it is not retrievable
later. Existing email identities are rejected rather than silently promoted or
merged. Reset password assigns a new value and revokes existing sessions.
Deactivate also revokes sessions; Reactivate restores access with the assigned
password. Newly generated passwords use Web Crypto: ten distinct random words
plus six digits, comfortably longer than sixteen characters. They appear only
in the current creation/reset field, never in stored configuration or audit rows.

In a separate browser session, the administrator signs in with assigned email
and password and lands on pending reviews. They can approve/reject; publication,
settings, resources and account management remain owner-only. Approval does not
publish. Signed-in identity and logout are visible. Anonymous visitors retain
map access and receive no administration permissions.

## Security and migration

Passwords are hashed through Drupal's user APIs. Internal Drupal user records
implement application authorization; there is no public LiT registration or
email-ownership verification. Generic failures and IP/email throttles protect
login. Forms use Drupal protection, passwords are not form-cached, redirects are
fixed by role, and password parameters are marked sensitive. The generator's
public word list contains no credentials.

Old custom email-link requests/confirmation are disabled, old tokens were
revoked, and existing managed-account sessions were invalidated by the explicit
`scripts/disable-lit-email-links.php` migration. Managed accounts cannot use core
email-reset login links. Administrators cannot edit their own account password.
UID 1 and unrelated receiving-site accounts keep normal Drupal account and mail
behavior. No Mailpit service was removed. CLI session revocation explicitly
deletes only the affected UID's sessions because Drupal's session-manager
`delete()` intentionally does nothing in CLI mode.

## Verification

Run:

```sh
ddev drush php:script tests/lit_contribution/password-services-test.php
node tests/lit_contribution/password-browser-test.cjs
```

The service suite uses transactions and isolated state, and verifies that the
real owner hash/designation remain unchanged. Browser QA uses UID 1 emergency
access privately to create a fictional administrator, tests login/reset/access,
and leaves the disposable account blocked. Credentials/tokens are never printed.
Older passwordless browser scripts document a superseded flow and are not the
current authentication regression suite.

The real owner's first password login remains dependent on the private `.env`
entry and bootstrap above. No permanent password was selected for the owner.
No live Qualtrics integration or production email delivery is claimed. Map union
aggregation, source records, review decisions and published data are unchanged.

Verified locally on 2026-11-13:

- 30 isolated environment/bootstrap/password/role checks passed; actual owner
  remained UID 4, active, with its existing password unchanged.
- 49 browser checks passed: generated assigned passwords, login, pending review
  access, owner-only denials, reset/old-password rejection/session revocation,
  deactivate/reactivate, protected POSTs, logout and disabled email-link bypasses.
  UID 1 performed management during QA; the real owner's first login awaits setup.
- The browser suite ran 17 isolated approval/publication workflow checks using its
  disposable administrator. It did not submit changes to the live review dataset.
- 37 map browser regressions and 12 shared union-model checks passed, including
  city filters, refresh, history, clear/reset and mobile layout. Counts remain
  English 8,937; English-or-Spanish 9,558; English/Spanish/Korean 9,679; all eight
  languages 10,000. Publication hash/time remain unchanged.
- 31 resource checks passed. Actual Drupal Media rendering remains untested
  because core Media is disabled. PHP syntax checks and `git diff --check` passed.
- Mobile login and desktop emergency-owner dashboard screenshots were visually
  inspected; fields and navigation fit. Screenshots/results remain ignored.
  Browser coverage uses local headless Chrome; no production-host test occurred.

Two integration defects found during verification were corrected: CLI session
revocation (described above), and account-toggle buttons requiring the unrelated
new-password field. Toggle buttons now have explicit scoped submit handlers and
skip that field's validation while retaining form protection and owner checks.

Files added/updated for this change: root `.gitignore`, `.env.example` and README;
ignored local `.env`; this guide and supersession notices in earlier contribution
docs; bootstrap/reset/migration scripts; contribution routing/services/module
hooks; entry/login/admin-password forms and owner dashboard; managed-account
reset guard; password generator JS/library/word list; and password service/browser
tests. Existing map/resource/count work in the working tree was preserved.

## Diagnosing an owner email mismatch

The bootstrap email must match the existing designated owner's email; editing
`.env` does not transfer ownership. On 2026-11-13, diagnosis confirmed UID 4
(`ree32`) already had `ree32@scarletmail.rutgers.edu`, with no conflicting
account. The local email assignment differed, with no injected email override.
Only that assignment was corrected. Bootstrap then reached password validation
and rejected the privately configured value; no password or ownership change
was applied. Supply a policy-compliant value privately and rerun the bootstrap
command above. No DDEV restart or owner-recovery command is required.
