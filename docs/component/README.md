# Reusable LiT Drupal component

The `lit_map` module owns the reusable map, canonical taxonomy, native resource
content, preprocessing, SVG/CSS/JavaScript, and reference/metric services.
`lit_contribution` is optional: it supplies the approved aggregate publication,
questionnaire panels and existing administration workflows. No Rutgers theme
changes are required. The component uses the existing design and geometry.

Local normal content page: https://rutgers-lit-m1.ddev.site/node/3

Edit: https://rutgers-lit-m1.ddev.site/node/3/edit

These URLs belong to this local host. IDs are generated independently on receiving
sites; setup prints the actual URLs. `/lit` remains the front page. Existing
preview routes remain available and use the shared `ComponentBuilder`.

## Requirements and current status

| Requirement | Status | Implementation / evidence |
|---|---|---|
| Counties and Languages taxonomy | Implemented | `lit_counties`, `lit_languages`; 21 county terms and 11 historical language keys seeded |
| Stable IDs, aliases, ambiguous mappings | Implemented | `ReferenceProvider`, `LitStableIdentifier` constraint, explicit seed command; historical `zh` remains ambiguous |
| LiT Map Paragraph | Implemented | `lit_map` bundle, native taxonomy/resource references and editor settings |
| Entity preprocessing and Twig | Implemented | `lit_map_preprocess_paragraph()`, `ComponentBuilder`, module template suggestion |
| SVG / plain CSS / vanilla JS / Behaviors / once | Implemented | Existing assets and interaction model retained; component attach/detach added |
| Validated JSON/CSV metrics separate from labels | Implemented | Existing `SurveyNormalizer` and contribution `NormalizedExportMapper`; editorial labels now taxonomy-backed |
| External resources and Media references | Implemented | `lit_resource` node type, native link/Media fields, anonymous access-checked canonical links |
| Normal-page editing and rendering | Implemented | Separate demo setup; browser editor create/save/view/edit exercised |
| Multiple instances and lifecycle | Implemented | UUID URL namespace; scoped DOM/settings; abort/disconnect cleanup and reattachment checks |
| Cache invalidation | Implemented | Entity/list/publication tags; access/content-language contexts; HTTP term-edit invalidation verified |
| Additive and fresh installation | Implemented | Install config, update hooks, explicit idempotent term/resource commands; both paths tested on 10.6 and 11 |
| Drupal 11 runtime testing | Implemented | Isolated Drupal 11.4.13/PHP 8.4 host; working host remains 10.6.21 |
| Accessibility conformance | Partial verification | Keyboard, focus, mobile layout, reduced-motion code, text fallback; no formal screen-reader/conformance audit |
| Real interviews / receiving-site rollout | Not supplied | Explicit local test document only; real editorial approval/assets and deployment are outside this task |

## Exact machine names

Core term `name` provides the human-readable label. No metric/count fields exist
on these vocabularies.

| Vocabulary | Fields |
|---|---|
| `lit_counties` | `name`, `field_lit_geoid` (text), `field_lit_map_key` (text) |
| `lit_languages` | `name`, `field_lit_language_key`, `field_lit_standard_code`, `field_lit_aliases` (multiple), `field_lit_ambiguous` |

The Paragraph bundle is `lit_map`:

| Field | Purpose |
|---|---|
| `field_lit_heading` | Heading |
| `field_lit_intro` | Formatted introduction; Drupal text formats remain in effect |
| `field_lit_counties` | References restricted to `lit_counties` |
| `field_lit_languages` | References restricted to `lit_languages` |
| `field_lit_defaults` | Initially selected language term references |
| `field_lit_municipalities` | Enable municipality controls |
| `field_lit_breakdown` | Show municipality breakdown when a county is selected |
| `field_lit_source` | `informational` or `published`; published requires optional contribution module |
| `field_lit_resources` | References restricted to `lit_resource` nodes |

The resource node type is `lit_resource`. It uses core `title` and `status`, plus
`field_lit_resource_key`, `field_lit_kind` (`audio`, `video`, `resource`),
`field_lit_description_long`, `field_lit_external` (HTTPS link), `field_lit_media`
(actual Media reference), `field_lit_transcript` (HTTPS link), `field_lit_counties`,
`field_lit_languages`, `field_lit_municipality`, and `field_lit_test`.
`field_lit_description` is retained as a hidden legacy short field; the additive
update copies its values to the long-text field without changing/dropping storage.

The separate demo host uses node type `lit_demo_page` and
`field_lit_components` (Entity Reference Revisions → Paragraphs, limited to
`lit_map`). Optional demo Media type: `lit_test_document`, with
`field_lit_test_file`. The test asset is a plain-text document explicitly labeled
as a test, not a recording or interview.

## Editorial references versus survey metrics

`ReferenceProvider` loads taxonomy entities for runtime county/language labels,
keys, and language aliases. Operational contribution validation, resolution forms,
and question-panel labels use this provider. Aliases are case/whitespace folded;
ambiguous collisions remain unresolved. Unknown language responses remain flagged
and excluded pending review. Chinese (`zh`), Mandarin (`cmn`), and Cantonese
(`yue`) remain distinct. `zh` retains historical aggregates but is marked ambiguous
and is not offered as an automatic/manual resolution for new questionnaire text.
Other terms can also be marked ambiguous; this behavior is not a hardcoded list.

Identifiers are validated for syntax, uniqueness within the owning bundle and
immutability by `LitStableIdentifier`. Programmatic saves also validate the key.
Historical taxonomy terms cannot be deleted through entity APIs; unpublish them
when they must disappear from public components. Editorial term labels and
aliases remain editable. Normal Drupal field/entity storage permissions apply.
Concurrent custom integrations should use normal Drupal entity validation and
serialize imports; uniqueness is application validation, not a custom SQL index.

County JSON is a seed/conversion input, not a runtime editorial label source.
`reference-seed.json` reconciles all eleven former PHP/JSON language definitions.
The old reference JSONs retain municipality membership and test/conversion inputs;
their language labels are replaced by taxonomy at runtime. Municipality geometry
and community matching remain separate fixed assets. Stable survey keys are
unchanged, so existing publications and submitted records retain their meaning.

Numeric JSON/CSV-derived metrics retain the existing validation, missing/zero
semantics, disjoint language-combination union counts, and New York date filters.
No term ID becomes a survey key. No counts are appended to taxonomy labels.

## Reference and resource behavior

Empty county/language references mean all supported, anonymously viewable,
published terms. Explicit references constrain selectors, metric buckets, language
combination keys, URL restoration, community matching and resource matching.
Questionnaire requests include the component's county restriction. These are
editorial presentation restrictions, not access controls for the public dataset.
Municipality controls can be disabled without excluding municipality responses
from county totals. The default breakdown is applied when a county is selected.
Default languages must belong to an explicitly restricted language list.

Empty Paragraph resource references mean **no editorial resources**. Select the
resources intended for that component. Preview routes show published accessible
resources from the native catalogue. Resources with no county are global; others
match the selected county and optionally municipality. Language matching uses ANY
selected language. Resources never change counts.

A published resource must contain exactly one external HTTPS link or Media
reference. Draft placeholders may have neither. Inaccessible/unpublished Media,
missing entities and disabled standalone Media URLs are omitted. The check is
against an anonymous account even when an administrator views the map. No direct
file URL bypass, player, autoplay, or automatic publication of interview-interest
responses is introduced. Transcript links are optional HTTPS links.

The contribution resources administration route now links to native resource
editing. Its existing configured owner retains access to create/edit/publish
`lit_resource` content through narrowly scoped access hooks, without changing role
permissions or granting administration of other node types. Other editorial roles
use normal Drupal content permissions. The portable module changes no
authentication or role permissions.

## Installation and additive migration

Back up the database/configuration and private publication/import files first.
Do not import a whole-site configuration export.

For an already installed site, deploy the module files then run:

```sh
ddev drush updatedb -y
ddev drush cr
ddev drush php:script scripts/seed-lit-references.php
ddev drush php:script scripts/migrate-lit-resources.php
```

The update hooks add only namespaced component definitions. They reject
incompatible field types, cardinality/target bundles, or foreign-owned bundle
machine names. They preserve existing compatible configuration rather than
resetting editorial changes. The post-update also covers old installations that
had no install file. Seed/setup operations are explicit because taxonomy terms
and resources are **content**: configuration exports do not transport them.

The seed command finds terms by stable key, never a numeric term ID. An explicit
one-time alias seed upgrade retains original language spellings; later reruns
preserve edited labels, aliases and publication state. County normalization also
recognizes the immutable geometry key, so renaming a county does not invalidate
its historical spelling. Resource migration finds existing
nodes by `field_lit_resource_key`, preserves them, and retains the original
`lit_contribution.resources:catalog_json` as a migration archive. Approved real
entries preserve their status; placeholders become unpublished drafts. Invalid
or unresolved references stop migration with an explicit error; the archive is
not discarded. No survey answers are converted into resources.

For a fresh site, install Composer dependencies (Paragraphs/Entity Reference
Revisions), enable `lit_map`, then run the seed command. Core Taxonomy, Media,
Node, Link, Options and Text are module dependencies. `lit_contribution` is not.
To use approved publications, enable/configure the existing contribution module
and choose `published`; this does not itself publish anything.

Explicit local demo setup:

```sh
ddev drush php:script scripts/setup-lit-component-demo.php
# Optional public test document and intentional demo Media access setup:
ddev drush php:script scripts/setup-lit-media-demo.php
```

Only the second command enables Media standalone URLs and grants anonymous
`view media` on the demo host. Do not run it on a receiving site without choosing
that site's Media access policy. It creates no account, changes no credential,
and does not grant access to unpublished Media.

The demo setup prints the node/edit URLs. In Drupal, add a **LiT demonstration
page**, add a **LiT Map**, choose terms, source, settings and resources, save and
view anonymously. Existing sites can attach the same Paragraph bundle to their
own Entity Reference Revisions field. No front-page or theme change is required.

The existing reset tool remains scoped to contribution tables/private directories.
Canonical taxonomy, Paragraph/resource content and component configuration are
outside its reset scope. Publication cache invalidation was added to its existing
cache cleanup. Reset/restore is tested only in disposable hosts, never on the
working site's application data.

## Rendering, lifecycle and caching

`lit_map_preprocess_paragraph()` prepares `lit_component` with `ComponentBuilder`.
The module registers `paragraph__lit_map` and supplies
`templates/paragraph--lit-map.html.twig`. A receiving theme can override that file
or `lit-map-bubble-preview.html.twig`; render `lit_component` rather than repeating
queries or normalization. Introductory text uses Drupal's field rendering and text
format filtering. Editorial text/link render arrays preserve Drupal escaping.
Only the fixed, module-owned SVG is converted to safe markup in PHP.

The shared builder is also used by geometry, interaction, bubble and contribution
preview controllers. It resolves references/access and attaches libraries and
public arrays under `drupalSettings.litMapBubbles[instance_id]`. It never attaches
private responses, emails, credentials or review metadata.

Each Paragraph gets a UUID-based URL namespace (`p<uuid>_lit_*`) and instance DOM
IDs. Legacy preview URLs retain their existing `lit_*` keys. JavaScript scopes
controls and SVG IDs, uses `once`, and aborts listeners/fetches and disconnects
observers on `detach(..., 'unload')`. Reattachment restores a clean component
without changing another map's URL parameters. Motion preferences are namespaced
for Paragraphs. A no-JavaScript county/language response table remains available.

Published components have a 300-second render max-age and cache tags for the
Paragraph, referenced terms/resources/Media, taxonomy/resource lists,
`lit_map:publication`, Media access configuration and contribution settings.
Contexts include permissions, node grants and content language; access results
also contribute their metadata. Publication and reset invalidate the publication
tag. Term/resource/Paragraph saves invalidate entity/list tags normally.
Client-side query filters do not vary server markup, so they do not add blanket
URL-query cache contexts. The informational source is explicitly a development
fixture and remains uncached to preserve file-change testing. Preview routes retain
their development no-cache policy; published Paragraph pages are cacheable.

## Portable versus local files

Portable: `web/modules/custom/lit_map/`, including install config, update hooks,
services, validation constraints, Twig, CSS/JS, geometry and public synthetic
fixtures. Optional integration: the focused `lit_contribution` reference/provider,
controller, resource-editor and cache-invalidation changes.

Content transfer/setup: `scripts/seed-lit-references.php` and
`scripts/migrate-lit-resources.php`. Local host only:
`scripts/setup-lit-component-demo.php`, `scripts/setup-lit-media-demo.php`, DDEV
configuration, ignored backups/private state and disposable test environments.
Verification: `tests/lit_map/component-*`, existing model/normalization tests and
[verification record](verification.md).
