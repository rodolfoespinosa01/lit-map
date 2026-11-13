# Proposed survey JSON contract — version 1

Milestone 1 documentation only: no parser, importer, endpoint, or map exists.
`examples/survey.synthetic.json` contains invented aggregate counts, not Rutgers
survey results. Keep real Qualtrics exports outside the public web root and Git.
Qualtrics JSON/CSV exports will eventually be transformed into this normalized
format outside Drupal entities; this is not a native Qualtrics export format.

The root object contains `schema_version` (integer, currently 1), `synthetic`
(boolean), `source` (description), and `observations` (array).
Each observation contains:

- `county_fips`: five-character US county FIPS string, preserving leading zeros.
- `language_code`: BCP 47 language identifier; labeling rules remain future work.
- `respondent_count`: nonnegative integer for this county/language combination.
- `recording_urls`: array of absolute HTTPS external recording URLs; empty when
  absent. Future examples may use Vimeo. These are references, not downloaded media.

One observation per county/language pair. Counts are aggregate language mentions;
people may report multiple languages, so counts must not be summed as unique people.
No names, emails, response IDs, or individual survey responses belong here.
Production privacy, suppression, consent, and aggregation rules are not defined
by this synthetic example and must be agreed before using real data.

SVG geometry remains a separate asset, joined by county FIPS. No geometry belongs
in survey JSON. Drupal's database hosts Drupal only; no separate survey database,
metric content type, or response-to-entity import is planned. County/language
taxonomy labels and Drupal Media references are future options, not implemented.
The eventual component uses Twig, SVG, plain CSS, vanilla JavaScript, and Drupal
Behaviors. Filter persistence uses URL parameters; AJAX is only for necessary
asynchronous loading. No frontend build pipeline is introduced.
