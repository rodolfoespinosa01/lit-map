# Dataset files

The importer accepts UTF-8 normalized JSON or CSV, maximum 50 MiB and 50,000
records. Native Qualtrics exports have **not** been verified: obtain an actual
export and map its columns explicitly in a replaceable adapter before claiming
compatibility. `src/Source/NormalizedExportMapper.php` is the interchange boundary;
`SubmissionValidator` and `Questionnaire` resolve taxonomy aliases and geographic
references. No native Qualtrics headers are assumed.

## Supply a dataset

1. Sign in at `/user/login` with a normal Drupal account holding `administer site
   configuration` (or UID 1). Visit `/admin/content/lit-map-dataset`.
2. Select a complete normalized `.json` or `.csv` file. Check “synthetic” only for
   demonstration data. Select **Validate and replace dataset**.
3. Validation failure leaves the previous snapshot intact. Refresh the public
   map after success; an already open page retains its loaded snapshot.

CLI equivalent, with the file stored privately inside the DDEV project:

```sh
ddev exec env LIT_DATASET_FILE=/var/www/html/private/full-export.json drush php:script scripts/lit-dataset.php
```

For synthetic input add `LIT_DATASET_SYNTHETIC=yes` to that `env` command.
Do not use a real participant file as a fixture or place it under `web/`.
The upload is processed in memory; raw rows are not persisted by LiT. PHP/DDEV
upload size limits can be lower than 50 MiB; use the CLI for larger files.

## Supported normalized interchange

JSON: `{"export_version":2,"submissions":[...]}` (version 1 is also accepted).
Each record has exactly `schema_version`, `survey_id`, `response_id`,
`submitted_at`, `updated_at`, and `answers`. There is **no email envelope field**.
CSV has exactly that ordered header; `answers` is a quoted JSON object.
See the explicitly synthetic examples in `fixtures/lit-export/`.

Version 1 answers retain the provisional fixed-language profile format.
Version 2 answers use the retained questionnaire definition in
`web/modules/custom/lit_map/data/questionnaire.v2.json`. Optional unanswered values
remain null; no answers are invented. Questionnaire data supports only the
existing allowlisted aggregate questions; contact and free-text fields never
enter stored snapshots or public output. Geographic or language ambiguity must
be resolved in the mapped file, not in a contribution-review interface.

Timestamps are ISO-8601 with explicit offsets, or JSON null if absent. CSV empty
timestamps represent absent dates. Dates are bucketed in America/New_York;
undated records appear in all-time results and are excluded by bounded dates.
Stable `survey_id` + `response_id` identify source records, never unique people.
If a source lacks IDs, the mapping process must assign deterministic record IDs;
this does not establish respondent identity. Identical duplicate IDs collapse;
conflicting duplicates reject the entire file. No email identity check occurs.
The mapped aggregate-permission/consent values determine which rows may be used;
there is no local per-response approval step.

**Full-export contract:** every activation replaces the entire active snapshot.
An incremental file would replace the map with only its records; it is not an
append operation. Merge incremental data into a complete export upstream.
Repeated full-file imports cannot add to the current counts. Validate first,
write an immutable hash-addressed private aggregate, then atomically replace one
Drupal state pointer and invalidate `lit_map:dataset` caches. Failures before
activation retain the last valid pointer. Old aggregate snapshots are retained
privately for recovery; they contain no source records. Concurrent activations
are serialized by Drupal's lock service.

## Synthetic demonstration

`data/demo-10000.v1.json` is the exact aggregate extracted from the existing
10,000-response baseline. It has no source IDs, contacts, free text, accounts or
review records. Reseed explicitly with:

```sh
ddev drush php:script scripts/lit-reseed-demo.php
```

To reproduce from deterministic synthetic records (seed 20261114; original
November 14 12:56 UTC anchor and geographic distribution retained):

```sh
python3 tools/dataset/generate-demo.py
ddev exec env LIT_DATASET_FILE=/var/www/html/private/lit-map-demo-source/submissions.json LIT_DATASET_SYNTHETIC=yes drush php:script scripts/lit-dataset.php
```

This creates only fictional input under ignored `private/`; no accounts or
review rows. The generator preserves the original random sequence, then strips
unused contact values. It refuses to overwrite an existing generated source.
Questionnaire panels retain the existing minimum-cell threshold of 5; this is a
development disclosure rule, not a research-team-approved disclosure policy.
