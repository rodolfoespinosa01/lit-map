# Simulated export—not yet verified against Qualtrics

The legacy `example` CSV and JSON files contain the same seven fictional records with reserved
example-domain emails. They are normalized interchange examples, not real
Qualtrics column names or actual LiT contributions. Record 5 repeats the local
synthetic baseline's first record so the preview can demonstrate idempotency.
See [format and walkthrough](../../docs/contribution/export-upload.md).

On a newly seeded host with different baseline timestamps, regenerate both using
`python3 tools/contribution/make-export-examples.py` before using the examples.
This updates only synthetic examples, never the source or review database.

Current downloads use `questionnaire-v2.json` and `questionnaire-v2.csv`: five fictional rows covering valid, duplicate, invalid-consent and unresolved-language cases. See [questionnaire mapping and assumptions](../../docs/contribution/questionnaire-v2.md). Both formats contain the same private normalized schema-v2 records; they are not native Qualtrics exports.
