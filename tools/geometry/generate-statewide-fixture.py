#!/usr/bin/env python3
"""Reproducible fictional responses. Geography supplies references, never counts."""
import argparse,hashlib,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];DATA=ROOT/'web/modules/custom/lit_map/data'
SEED='rutgers-lit-m4-2026-11-13'
refs=json.loads((DATA/'survey-statewide-references.json').read_text())
def number(key):return int.from_bytes(hashlib.sha256((SEED+'|'+key).encode()).digest()[:8],'big')
records=[];missing=[];zeroes=[]
for town in refs['municipalities']:
 if number(town['id'])%29==0:missing.append(town['id']);continue
 for lang in refs['languages']:
  value=number(town['id']+'|'+lang['id'])
  if value%5==0:continue
  count=0 if value%13==0 else 1+value%350
  records.append({'county_geoid':town['county'],'municipality':town['id'],'language':lang['id'],'response_count':count})
  if count==0:zeroes.append([town['id'],lang['id']])
for county in sorted({m['county'] for m in refs['municipalities']}):
 for lang in refs['languages']:
  n=number(county+'|unspecified|'+lang['id'])
  records.append({'county_geoid':county,'municipality':None,'language':lang['id'],'response_count':n%41})
fixture={'schema_version':2,'synthetic':True,'records':records}
totals={};unspecified={}
for r in records:
 c,l=r['county_geoid'],r['language'];totals.setdefault(c,{});totals[c][l]=totals[c].get(l,0)+r['response_count']
 if r['municipality'] is None:unspecified.setdefault(c,{})[l]=r['response_count']
report={'seed':SEED,'algorithm':'SHA-256 UTF-8 seed|key, first 8 bytes big-endian; see script for deterministic modulus rules','records':len(records),
 'municipalitiesWithNoRecords':missing,'explicitZeroExamples':zeroes,'countyTotals':totals,'unspecifiedCounts':unspecified,
 'notice':'All values fabricated; no actual prevalence or unique-person inference.'}
outputs={DATA/'survey.statewide.v2.json':fixture,ROOT/'docs/bubbles/fixture-report.json':report}
p=argparse.ArgumentParser();p.add_argument('--check',action='store_true');args=p.parse_args()
for path,data in outputs.items():
 content=(json.dumps(data,indent=2)+'\n').encode()
 if args.check:assert path.read_bytes()==content
 else:path.parent.mkdir(exist_ok=True,parents=True);path.write_bytes(content)
print(f'{len(records)} fabricated records; {len(missing)} deliberately unrecorded municipalities; {len(zeroes)} explicit municipality zeroes; 21 counties.')
