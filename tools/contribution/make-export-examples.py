"""Recreate equivalent simulated examples from this host's baseline; never import."""
import copy
import csv
import json
from pathlib import Path
root = Path(__file__).resolve().parents[2]
base = json.loads((root / 'private/lit-contribution-demo-10000-v1/submissions.json').read_text())['submissions'][0]
rows = []
for i, languages in [(1, ['en', 'es']), (2, ['en', 'ko']), (3, ['en']), (4, ['en'])]:
    r = copy.deepcopy(base)
    r.update(survey_id='LIT_UPLOAD_SAMPLE_V1', response_id=f'SAMPLE_{i:03}',
             email=f'simulated-upload-{i}@example.org',
             submitted_at=f'2026-11-01T10:0{i}:00-05:00', updated_at=f'2026-11-01T10:0{i}:00-05:00')
    profile = next(iter(r['answers']['languages'].values()))
    r['answers']['languages'] = {l: dict(profile, uses_language='yes') for l in languages}
    r['answers']['first_languages'] = [languages[0]]
    r['answers']['aggregate_permission'] = 'no' if i == 4 else 'yes'
    if i == 3:
        r['email'] = ' SIMULATED-UPLOAD-1@example.org '
    rows.append(r)
rows.append(base)
r = copy.deepcopy(rows[0]); r['response_id'] = 'INVALID_GEOGRAPHY'; r['answers']['county_geoid'] = '99999'; rows.append(r)
r = copy.deepcopy(rows[0]); r['response_id'] = ''; r['email'] = ''; rows.append(r)
folder = root / 'fixtures/lit-export'
folder.mkdir(parents=True, exist_ok=True)
(folder / 'example.json').write_text(json.dumps({'export_version': 1, 'submissions': rows}, indent=2) + '\n')
columns = ['schema_version', 'survey_id', 'response_id', 'submitted_at', 'updated_at', 'email', 'answers']
with (folder / 'example.csv').open('w', newline='') as f:
    w = csv.DictWriter(f, fieldnames=columns); w.writeheader()
    for r in rows:
        w.writerow(dict(r, answers=json.dumps(r['answers'], separators=(',', ':'))))
print('Regenerated synthetic examples only. No imports or review changes.')
