# PRIVATE SYNTHETIC MOCK SOURCE — NOT REAL SURVEY RESPONSES

This tracked fixture contains only fictional reserved-domain email addresses.
It lives outside Drupal's `web/` document root. Only the permission-controlled
review screens may read it through `MockQualtricsSource`. It is not a public asset,
public API, research questionnaire, or live Qualtrics export. Do not place real
responses here. Private publication outputs live under ignored `private/`.

Reproducible generator: `python3 tools/contribution/generate-mock.py --check`.
It checks existing bytes; normal generation refuses to overwrite a fixture.
See `docs/contribution/README.md` and `provisional-survey-v1.schema.json` there.
