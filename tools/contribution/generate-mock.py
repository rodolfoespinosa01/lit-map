#!/usr/bin/env python3
"""Synthetic private fixture. Never generate names or non-reserved email domains."""
import argparse, json
from pathlib import Path
from datetime import datetime, timedelta, timezone
root=Path(__file__).resolve().parents[2]
refs=json.loads((root/'web/modules/custom/lit_map/data/survey-statewide-references.json').read_text())
langs=['en','es','ko','pt','zh','ar','hi','ase']
towns=refs['municipalities']
rows=[]
for i in range(64):
    town=next(t for t in towns if t['id']=='m3400300700') if i<24 else towns[(i*37)%len(towns)]
    profiles={}
    for j,lang in enumerate(langs):
        if i>=24 and (i+j)%7==0: continue
        yes=(i+j)%5 < (4-j%3)
        profiles[lang]={'uses_language':'yes' if yes else 'no', 'speaking':('not_applicable' if lang=='ase' else ['basic','conversational','advanced','fluent'][i%4]) if yes else 'none',
            'signing':('conversational' if yes else 'none') if lang=='ase' else 'not_applicable',
            'home_use':'yes' if yes and i%2==0 else 'no', 'other_settings':['work','friends'] if yes else ['none'],
            'frequency':['daily','weekly','monthly','less_often'][i%4] if yes else 'never', 'brokering':['never','occasionally','often'][i%3]}
    stamp=(datetime(2026,11,10,9,tzinfo=timezone(timedelta(hours=-5)))+timedelta(minutes=i*7)).isoformat()
    email=f'person-{i+1:03}@example.org'
    if i==60:email=' Shared@Example.org '
    if i==61:email='shared@example.org'
    if i==62:email='shared+one@example.org'
    if i==63:email='s.hared@example.org'
    rows.append({'schema_version':1,'survey_id':'SYN_LIT_PROVISIONAL_V1','response_id':f'R_DEMO_{i+1:03}','submitted_at':stamp,'updated_at':stamp,'email':email,
      'answers':{'county_geoid':town['county'],'municipality':None if i%11==0 else town['id'],'first_languages':[langs[i%len(langs)]],
       'languages':profiles,'aggregate_permission':'no' if i in [9,19,29,39,49,59] else 'yes'}})
content=json.dumps({'schema_version':1,'synthetic':True,'submissions':rows},indent=2)+'\n'
p=root/'fixtures/lit-contribution/submissions.v1.json'
a=argparse.ArgumentParser();a.add_argument('--check',action='store_true');a.add_argument('--large',action='store_true');args=a.parse_args()
if args.large:
    from large_demo import generate
    generate(root,args.check)
    raise SystemExit(0)
if args.check:assert p.read_text()==content
else:
    if p.exists():raise SystemExit('Fixture already exists; use --check. Preserve local changes.')
    p.parent.mkdir(parents=True,exist_ok=True);p.write_text(content)
print('64 fictional submissions, 8 provisional languages; reserved-domain emails and explicit offsets.')
