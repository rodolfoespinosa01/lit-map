# Informational map component and editor guide

`lit_map` owns the Paragraph renderer, taxonomy references, geography, normalized
file dataset, questionnaire aggregates, and Drupal-native resources. It has no
runtime dependency on the retired contribution module. Existing geography,
SVG/Twig, Drupal libraries, Behaviors/core once, cache metadata and per-instance
state are retained.

## Editor configuration

Use Drupal's standard `/user/login` and node editing forms. The local normal page
is `/node/3`, with two independent map Paragraphs. Each Paragraph supports:

- Heading and introduction.
- County and language term references (empty means all published accessible terms).
- Default language references; these must belong to any restricted selection.
- Municipality controls and initial breakdown setting.
- Selected resource-node references (empty uses the available catalogue).
- **Show external Qualtrics survey link** and **External Qualtrics survey URL**.

To configure the invitation, edit the page, expand its LiT Map Paragraph, enter
the actual survey HTTPS URL, check the show box and save. The link reads
“Share your language experience” with explanatory text that the survey opens on
Qualtrics. No URL is preconfigured. An absent URL or unchecked box hides it;
non-HTTPS URLs, credentials in URLs and unsafe characters are rejected. Drupal
does not embed the survey, close it, receive answers or detect completion.

The retained `field_lit_source` values `informational` and `published` both select
the active file dataset; the latter is a compatibility value for old revisions,
not an approval workflow. The page editor uses ordinary Drupal permissions.
Upload datasets at `/admin/content/lit-map-dataset`; see [format and exact
commands](../dataset.md).

## Taxonomy and resources

`lit_counties` owns county labels, stable GEOIDs and SVG keys; `lit_languages`
owns labels, stable language identifiers and aliases. Stable identifiers cannot
be changed or deleted after use; unpublish a term to hide it. County, language,
municipality and community matching remain reference-based. Chinese is not
silently treated as Mandarin or Cantonese.

`lit_resource` nodes reference Counties, Languages and optional Drupal Media.
Their external recording/resource and transcript links require HTTPS. Normal
node publication and anonymous access checks apply; Media must also be published
and have an accessible canonical route. Standard Drupal resource editing and
Media access controls are preserved. Test resources are labeled as such.
Taxonomy/node/Media cache tags and access contexts propagate to the component.
The dataset pointer invalidates `lit_map:dataset` after successful activation.

Multiple Paragraphs have distinct DOM IDs and namespaced URL parameters. Attach
is idempotent; detach clears listeners and transient state. Language unions count
each matching response once per place and date range; overlapping language totals
must not be added. Accessible selectors, keyboard pan/zoom, results and a no-JS
table accompany the SVG. Source-date bounds exclude undated records.

## Installation and upgrade

Module dependencies include Paragraphs/entity-reference revisions, taxonomy,
node, text, options, link and Media. No whole-site configuration import is needed.
Install `lit_map`, seed reference terms explicitly, supply a dataset, and add a
LiT Map Paragraph to the receiving site's normal page component field. The
local `setup-lit-component-demo.php` provides a demonstration page type with
`field_lit_components`; it is never run by portable module installation.

Existing installations run `ddev drush updatedb -y` for additive field creation.
Existing content, revisions, term IDs and resource references are retained.
The explicit local front-page helper is separate from all portable updates.
Sites still running the former contribution module must follow the staged
[migration procedure](../informational-migration.md) before removing its code.
Do not uninstall `lit_map` to perform this upgrade: its owned field/bundle
configuration is intentionally retained.

Geometry sources, conversion tools and licenses remain in [geometry](../geometry/README.md).
Current evidence is in [verification](verification.md).
