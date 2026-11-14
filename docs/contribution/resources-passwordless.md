> Authentication update: use [password access](password-access.md). Email-link
> login instructions below are historical and superseded.

# Passwordless administration, editorial resources and floating bubbles

Implemented locally on `contribution`, November 13, 2026. No applicable AGENTS.md
was found. Existing uncommitted changes, `informational`, the 10,000-response
dataset, review decisions and aggregate publication were preserved. No commit,
push, deployment, dependency installation or Media module enablement occurred.

## Passwordless application access

Verified before changes: **ree32, UID 4**, active, sole application owner, email
**ree32@scarletmail.rutgers.edu**. UID 1 remains emergency Drupal administration.
`scripts/lit-owner.php` now records this project's explicitly supplied deployment
identity. `setup-contribution-owner.php` uses those defaults, still rejects a
different existing owner or a conflicting email, and leaves an unchanged owner
untouched. Environment overrides are for an explicitly confirmed deployment
identity; do not assume UID 4 identifies the same person on another Drupal site.
Ownership is bootstrapped server-side, never inferred from a public email entry.

LiT authorization uses Drupal user records and dedicated roles internally. Adding
an administrator authorizes its email; the system may create a backing account
with an unshared random credential. It never asks the person to create/enter a
password. The owner manages authorization through **Manage administrator emails**,
not core account/password forms. Other administrators review/approve/reject;
publishing, settings, resource editing and administrator management remain owner-only.
Approval remains separate from publication.

The inspected custom flow is `/lit/sign-in` → captured email →
`/lit/sign-in/confirm` → owner dashboard or pending reviews. It calls normal Drupal
session finalization directly after successful token consumption. It never calls
Drupal's password reset controller. Generic responses, 15-minute token expiry,
single use, hashed token storage, fragment removal, request/redemption throttles,
CSRF protections, fixed destinations and session/token revocation are retained.
Typing an authorized email alone does not authenticate.

**Why a password screen could appear:** the previously documented
`ddev drush user:login` command uses core `user.reset.login`. Inspection of
`UserController::resetPassLogin()` confirms its “set your new password” message
and redirect to `entity.user.edit_form`. That is an emergency core workflow, not
the custom LiT sign-in. Use the email flow below for normal application access.

The core account menu is suppressed only on LiT flow routes. LiT account navigation
shows signed-in identity, dashboard/pending reviews and logout. Ordinary receiving-
site account routes, permissions, password forms and emergency access remain intact.
No anonymous or general authenticated role receives administrative permissions.

## Local URLs and exact walkthrough

| Purpose | URL |
|---|---|
| Public map | <https://rutgers-lit-m1.ddev.site/lit-map/contribution-preview> |
| Email sign-in | <https://rutgers-lit-m1.ddev.site/lit/sign-in> |
| Owner dashboard | <https://rutgers-lit-m1.ddev.site/admin/lit> |
| Administrator emails | <https://rutgers-lit-m1.ddev.site/admin/people/lit-administrators> |
| Pending reviews | <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions/pending> |
| Resources editor | <https://rutgers-lit-m1.ddev.site/admin/config/lit-contributions/resources> |
| Publish survey aggregates | <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions/publish> |
| Invitation settings | <https://rutgers-lit-m1.ddev.site/admin/config/lit-contributions> |
| Reconciliation | <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions/reconciliation> |
| Mailpit | <https://rutgers-lit-m1.ddev.site:8026> |

1. Run `ddev start`, open **Email sign-in**, enter
   `ree32@scarletmail.rutgers.edu`, and choose **Email sign-in link**.
2. In Mailpit, open the newest “LiT local administrator sign-in” message to that
   address. Follow the link and choose **Confirm sign-in**. No password is needed;
   the owner dashboard opens.
3. Choose **Manage administrator emails**, enter an administrator's email, and
   choose **Add administrator**. For local QA use a fictional `example.org` address.
4. In a separate browser/private window, that administrator requests its own email
   link, retrieves it in Mailpit and confirms. It opens **pending reviews**, with
   no password step. Review actions require a reason; only the owner can publish.
5. Use **Log out** and confirm when finished. Deactivation ends the administrator's
   active sessions and revokes unused links; reactivation requires a new link.

**Mail is captured locally, not delivered to the actual Rutgers mailbox.** DDEV's
Mailpit URL is obtained from `ddev describe -j`, not assumed. No production email
or live Qualtrics connection is configured. Invitation remains OFF with no URL.

Optional local recovery that stays in the same passwordless flow:

```sh
ddev drush --uri=https://rutgers-lit-m1.ddev.site php:script scripts/request-owner-signin.php
```

This verifies the configured owner and requests its ordinary Mailpit link. It
prints no token and does not bypass request limits. The browser sign-in is still
the preferred route. Core `drush user:login --uid=1` remains a separate emergency
tool; the receiving site's normal account management is not replaced.

## Editorial resource catalogue

No prior recording/story interface existed. Resources now live in separate Drupal
configuration, `lit_contribution.resources:catalog_json`, **not in survey responses,
review tables, aggregate groups, language counts or geometry**. The owner dashboard
links to a Drupal Form API JSON editor. Saving uses normal access/CSRF protection,
validates the entire catalogue and records a resource-catalogue access audit event.
It does not synchronize, review or publish survey submissions.

Until explicitly configured, the module-owned
`web/modules/custom/lit_contribution/data/resources.demo.v1.json` supplies two
clearly marked placeholders. They contain no playback/transcript links. There are
no real LiT recordings in this repository. An empty `items` list disables all cards.

Schema version 1 has `schema_version` and `items`. Each item has exactly:

```json
{
  "id": "editorial-example",
  "approved": false,
  "title": "Replace with the approved recording title",
  "description": "Replace with an accurate editorial description.",
  "languages": ["en", "es"],
  "county": "34003",
  "municipality": null,
  "kind": "audio",
  "target": "external",
  "url": "https://example.org/replace-with-approved-recording",
  "media_id": null,
  "transcript_url": null,
  "test_only": false
}
```

This is an **unapproved editing template**, not a working recording or interview.
Replace the URL/content before approval. Preserve the root object and its list.
Use stable lowercase resource IDs, language IDs from the map, county Census GEOIDs,
and municipality IDs from `lit_map/data/survey-statewide-references.json`. A town
must belong to its county. Titles/descriptions are plain text, never trusted HTML.

- `kind`: `audio`, `video`, or `resource`. Normal actions say **Listen to interview**,
  **Watch interview**, or **Open resource**, followed by the title.
- `target: external`: supply an HTTPS URL (Vimeo supported), leave `media_id` null.
  URLs with credentials, unsafe schemes, controls or backslashes are rejected.
  No iframe, player, autoplay, remote metadata fetch or prefetch is added to the map.
- `target: media`: supply a positive existing Drupal Media ID, set `url` null.
  Core Media is optional; no contributed media module is assumed or installed.
  The item must be approved, the entity must be **published**, anonymous entity
  `view` access must allow it, and its canonical URL must be accessible anonymously.
  Drupal `toLink()->toRenderable()` generates the link. No source field/file URL
  is extracted to bypass access. These checks apply even when an owner views the
  public map. Missing/unpublished/inaccessible Media is omitted entirely.
  The receiving site must configure core Media standalone canonical pages and
  public access intentionally; this component grants no Media permissions.
- `transcript_url`: optional HTTPS transcript/caption link, rendered descriptively.
- `target: placeholder`: no URLs or Media ID, `test_only: true`. It displays an
  explicit demonstration notice and no misleading action.
- `test_only: true` on an external test resource labels it **not a LiT interview**
  and changes the action to “Watch/Listen to test recording.”
- `approved: false` omits the entire card and its metadata from public HTML/settings.

Choose **Save resource catalogue**, then refresh the map. Resources are revalidated
on every uncached public render. The browser receives only approved/access-checked
card markup and matching metadata (ID, languages, county, municipality). All links
use Drupal render arrays; JavaScript clones those rendered cards and never inserts
raw editorial HTML. Malformed runtime configuration logs an error category and
omits resources while leaving the map available.

## Predictable matching and panel behavior

Select a bubble, geometry, selector or result-list place. Hover/focus shows a
separate count tooltip and never changes the selected resource panel.

- No place selected: prompt to select a place; no resource cards are listed.
- Selected languages: a resource matches if it shares **any** selected language.
  With no languages selected, show all approved resource languages at that place.
- County selected: include global (`county: null`), county-wide, and town-specific
  resources within that county. Other counties are excluded.
- Town selected: include global, county-wide and that exact town's resources.
  “Municipality unspecified” includes only global/county-wide resources.
- Cities off clears the town filter and restores county matching immediately.
  URL refresh and Back/Forward use the same matching function. USA view has no cards.

The panel order is **place → matching response count → individual language
breakdown → recordings/resources**. With a town selected, the county context and
full town breakdown remain in expandable sections below. Cards and links support
native keyboard focus, Enter, click and tap. No resource action depends on hover.

## Bubble presentation and count integrity

The existing schema-3 union model is unchanged. It still counts each eligible
approved response once per geographic area when it reports ANY selected language.
The display now says **matching survey responses**, never verified people or
population. Individual language counts overlap; segments remain equal categorical
indicators, not shares. More than six positive languages keeps the neutral `+`
fallback and full textual language breakdown.

Before: opaque discs and a rail of crowded numeric callouts. After: soft translucent
segments, outlines, restrained highlights, dark count text with a white halo,
selected rings and mostly internal counts. A deterministic screen-space spacing
pass can move a crowded bubble at most **84 px** from its true geographic anchor;
dot/line connectors identify displaced anchors. Geometry and source coordinates
are unchanged. Only actual remaining text collisions trigger county callouts.
The results list and selectors remain equivalent ways to select dense places.

For positive counts, radius is `clamp(14, sqrt(count) × factor, 38)` CSS pixels,
with factor **0.7 for counties**, **1.6 for towns**. Area is proportional to count
between the clamps; the documented minimum/maximum intentionally limit that
relationship at the extremes. Zero is a 4 px hollow ring; missing data has no
bubble. The size legend uses the same function and identifies the current scale.
Earlier milestone previews retain their old scale/presentation.

Gentle anchored motion stays within 1.8 px, pauses on hover/focus/selection and
offscreen, can be switched off, and is disabled for reduced motion. Zoom, pan,
municipality navigation, URL state, reset and explicit language clearing remain.

Reconciliation baseline remains English **8,937**, English + Spanish **9,558**,
English + Spanish + Korean **9,679**, all eight **10,000**. New Brunswick remains
631 / 670 / 678 / 700 respectively. All county/town buckets were compared to source.
Publication pointer remains
`e29b3b40207ad44904ccf3457accda092d878c5c6fd9396370e85a358bd1a0cb`.

## Tests and limits

Reproducible checks:

```sh
ddev drush php:script tests/lit_contribution/resources-test.php
node tests/lit_contribution/resources-model-test.cjs
ddev drush php:script tests/lit_contribution/union-live-check.php
node tests/lit_contribution/union-browser-test.cjs
LIT_ACCESS_FOCUSED=1 node tests/lit_contribution/access-browser-test.cjs
node tests/lit_contribution/presentation-check.cjs
```

The access browser test temporarily adds explicitly labelled resources through the
owner editor, runs `resources-browser-test.cjs`, then restores the placeholder
catalogue in `finally`. It creates a reserved-domain QA administrator and leaves
it inactive. It exercises real Mailpit requests and request limits; avoid repeated
runs within the throttle windows. Focused mode sends just two emails and tests the
current owner/admin UX, permissions, single use, safe redirects, CSRF and logout.
Omit `LIT_ACCESS_FOCUSED` to run the retained full expiry/throttle/revocation suite
after limits have naturally reset; those unchanged controls were fully exercised
in the prior milestone. Request limits were not cleared or relaxed for this work.
Actual review approval/publication is tested in
isolated storage, preserving live demonstration decisions. Generated screenshots
and logs remain ignored under `docs/contribution/screenshots/` and `private/`.

Completed browser workflow: **50** focused access checks and **26** resource,
external-link, floating-bubble, touch/keyboard and motion checks. The owner added
a fictional administrator, who requested/confirmed a Mailpit link and reached
pending reviews without any password step. That administrator was denied resource
editing and other owner actions. Core Drupal's separate password login remained
available outside LiT. The temporary catalogue was restored and QA account left
inactive. The deliberately caught sole-owner guard exception is an expected test
result. Isolated approval/publication exercised the existing 17-check workflow.

Completed validation/regressions: **31** resource validation/rendering checks,
**18** matching/area checks, **37** union browser checks, **49** legacy bubble
browser checks, **20** deterministic union checks, **12** union state checks,
**6** owner guards and **9** isolated invitation checks. **12** final desktop/mobile presentation checks; 27 contribution PHP files and all owner helper scripts passed lint. Final screenshots were visually inspected after the contrast correction: unselected town count text stays fully opaque while only its fill is softened.

External-link verification uses the official
[W3C HTML5 video demonstration](https://www.w3.org/2010/05/video/mediaevents.html),
clearly labelled as a test, not a LiT interview. Keyboard activation reaches its
actual video page; its player has no autoplay and loads media after an explicit
Load action. The LiT map makes no external request before activation. No Vimeo
recording was configured/contacted; Vimeo HTTPS URL validation was exercised. Audio action text and transcript/caption links were rendered in isolated tests; no real audio or transcript resource was available to open.

**Media limitation:** core Media is not enabled on this test host. Missing-Media
fail-closed behavior was tested. Actual published/unpublished Media entities,
canonical-page access and rendering were code-reviewed against installed Drupal
core APIs but not integration-tested on a Media-enabled site. No production Media
compatibility claim is made. Test those receiving-site permissions/display settings
before enabling real Media resources. No real interviews, production email, live
Qualtrics integration, physical phone or screen-reader session was tested.
The optional CLI Mailpit helper was linted and reviewed; complete sign-in was
exercised through the browser rather than sending another helper-generated email.


## Final preservation check and changed files

Final reconciliation is exact: 10,000 approved/published, 100 pending, no new review
changes, no unsynchronized records. The review/audit fingerprint remains
`7a499f8c9d0bb14e742d97827333d60dd5d0de821a8420f097035ce4cbf069f6`.
The original publication pointer/time is unchanged. The final catalogue contains
only `demo-language-stories` and `demo-bergen-stories`, both link-free placeholders.
No actual invitation configuration was changed.

Changed areas: project owner configuration/bootstrap and Mailpit recovery helper;
LiT-scoped account navigation/menu handling and administrator explanatory text;
resource validation/render service, owner form, route/config schema/defaults and
dashboard link; public-map resource attachments/Twig; scoped bubble styling,
bounded spacing, panel organization and pure resource-matching/area functions;
new/updated validation, browser and presentation checks; README and milestone
notes. Existing generated screenshots/logs remain ignored. No survey schema,
aggregate/union algorithm, geometry, Composer dependency or database engine changed.
