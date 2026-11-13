# Synthetic survey interchange contract — version 2

Milestone 3 implements this vendor-independent JSON contract. It is **not** a
Qualtrics export format. A future adapter may transform approved Qualtrics exports
into it; no adapter, account, API, CSV import, or survey database is implemented.
The original Milestone 1 proposal is preserved in [survey-format-v1.md](survey-format-v1.md)
and `examples/survey.synthetic.json`; those version 1 files are historical and
are not loaded by the preview.

The active fixture is module-owned:
`web/modules/custom/lit_map/data/survey.synthetic.v2.json`.
It contains **33 fabricated aggregate records** and no personal information.
All displayed values mean **survey responses**, not population, speakers, or unique
people. Multiple language totals must never be added as a unique-person total.

```json
{
  "schema_version": 2,
  "synthetic": true,
  "records": [
    {"county_geoid": "34003", "municipality": "hackensack", "language": "es", "response_count": 12},
    {"county_geoid": "34003", "municipality": null, "language": "es", "response_count": 5}
  ]
}
```

The sample above illustrates the contract, not the full fixture. No separately
stored county total is allowed. Each record has:

| Field | Meaning and validation |
| --- | --- |
| `county_geoid` | Required string identifying one of the 21 verified NJ counties |
| `municipality` | Optional reference string, or `null`/omitted for unspecified; empty string is invalid |
| `language` | Required language ID from the module-owned reference catalog |
| `response_count` | Required nonnegative JSON integer, including explicit zero |

Counts and derived totals must fit JavaScript's exact integer range
(0–9,007,199,254,740,991). Strings, booleans, floats, negative values, missing counts,
unknown references, municipality/county mismatches, and duplicate
county/municipality/language combinations are rejected. Omitted and null municipality
are the **same** bucket for duplicate detection. Unknown record/top-level fields
are rejected, including separate county totals and personal-information fields.
The root requires integer `schema_version: 2`, boolean `synthetic: true`, and a
`records` list. Unsupported versions and non-synthetic datasets are rejected.

`SurveyNormalizer` derives each county/language total by adding only that language's
municipality records plus its unspecified bucket. It does not infer absent records.
A recorded `0` survives normalization; an absent key remains absent and displays
“No data available.” An empty records list is valid and means no data.

## Reference catalog

`data/nj-counties.json` remains the unchanged Census-derived county catalog.
`data/survey-references.json` contains four language labels (English `en`, Spanish
`es`, Korean `ko`, Portuguese `pt`) and eight real municipality names. Municipality
IDs are local stable reference keys, not Census municipality GEOIDs:

| County (GEOID) | Municipality IDs and names |
| --- | --- |
| Bergen (34003) | `hackensack` — Hackensack; `fort-lee` — Fort Lee |
| Hudson (34017) | `jersey-city` — Jersey City; `hoboken` — Hoboken |
| Morris (34027) | `morristown` — Morristown; `parsippany-troy-hills` — Parsippany-Troy Hills |
| Essex (34013) | `newark` — Newark; `montclair` — Montclair |

References are not taxonomies, database entities, or a complete municipality
catalog. They describe this fixture only. Real names and county associations were
checked against official sources: [Bergen County municipalities](https://bergencountynj.gov/municipalities/),
[Morris County cities and towns](https://www.morriscountynj.gov/Residents/Community-Information/Cities-and-Towns),
and the county/municipality table in [New Jersey P.L. 2025, Chapter 16](https://pub.njleg.gov/Bills/2024/PL25/16_.PDF)
for Essex and Hudson. These sources support names and geography only; **none of the
response counts comes from those sources**.

## Known fabricated totals

Each cell is `county total (municipality-unspecified count)`. A dash means that no
record exists; it must not be converted into zero.

| County | English | Spanish | Korean | Portuguese |
| --- | --- | --- | --- | --- |
| Bergen | 14 (2) | 20 (5) | 8 (1) | — |
| Hudson | 17 (1) | 28 (6) | 0 (—) | 5 (2) |
| Morris | 16 (3) | 10 (2) | 0 (0) | — |
| Essex | 16 (2) | 26 (4) | — | 9 (3) |

For example, Bergen Spanish is Hackensack 12 + Fort Lee 3 + unspecified 5 = **20**.
Hudson Korean is an explicit Hoboken zero, with no unspecified record. Morris
Korean is an explicit unspecified zero, with no municipality-specific records.
The other 17 counties have no records, not zero responses or zero speakers.

## Service boundary and public output

`SurveyDataProvider` resolves three fixed filenames relative to the installed
`lit_map` module. No visitor parameter supplies a path, URL, county catalog, or
fixture filename. It reads JSON, calls the independent `SurveyNormalizer`, and
logs a generic error category on failure. The browser receives a friendly message,
not a filesystem path, raw JSON, or exception details.

Only normalized public data is attached as `drupalSettings.litMapPreview`:
`schemaVersion`, `synthetic`, county keys/names/GEOIDs, language labels, municipality
references, per-language county `totals`, and per-municipality `breakdown`. The
internal unspecified bucket is `_unspecified`. Raw records, file paths, provenance
XML, geometry metrics, and exception content are not attached. The shipped fixture
is intentionally public synthetic data. Never put raw or private survey exports
in this module's public directory.

See [interaction documentation](interactions/README.md) for state semantics,
cache strategy, tests, and limitations. SVG geometry stays separate and unchanged.
