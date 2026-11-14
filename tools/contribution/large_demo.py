"""Seeded extension of generate-mock.py; writes only ignored private demo data."""
import json, random
from pathlib import Path
from datetime import datetime, timedelta, timezone
LANGUAGES=['en','es','ko','pt','zh','ar','hi','ase']
SURVEY='SYN_LIT_10000_V1'
def generate(root, check=False):
    folder=root/'private/lit-contribution-demo-10000-v1'
    path=folder/'submissions.json'; manifest=folder/'manifest.json'
    if manifest.exists(): anchor=datetime.fromisoformat(json.loads(manifest.read_text())['as_of'])
    else: anchor=datetime.now(timezone.utc).replace(microsecond=0)
    rng=random.Random(10000)
    refs=json.loads((root/'web/modules/custom/lit_map/data/survey-statewide-references.json').read_text())
    counties=sorted({t['county'] for t in refs['municipalities']})
    targets={c:40 for c in counties};targets.update({'34013':2200,'34017':1800,'34003':1600,'34027':1200,'34039':1200,'34023':1400})
    nb=next(t for t in refs['municipalities'] if t['county']=='34023' and t['name']=='New Brunswick city')
    rows=[]
    def row(county,town,number,pending=False):
        first=rng.choice(LANGUAGES);profiles={}
        for lang in LANGUAGES:
            yes=lang==first or rng.random()<({'en':.88,'es':.52}.get(lang,.16))
            profiles[lang]={'uses_language':'yes' if yes else 'no',
              'speaking':('not_applicable' if lang=='ase' else rng.choice(['basic','conversational','advanced','fluent'])) if yes else 'none',
              'signing':(rng.choice(['basic','conversational','advanced','fluent']) if yes else 'none') if lang=='ase' else 'not_applicable',
              'home_use':'yes' if yes and (lang==first or rng.random()<.4) else 'no',
              'other_settings':sorted(rng.sample(['work','education','friends','community','online','religious'],rng.randint(1,3))) if yes else ['none'],
              'frequency':rng.choice(['daily','weekly','monthly','less_often']) if yes else 'never',
              'brokering':rng.choice(['never','occasionally','often']) if yes else 'never'}
        stamp=(anchor-timedelta(seconds=rng.randint(1,365*86400-1))).astimezone(timezone(timedelta(hours=-5))).isoformat()
        email=f'demo10000-{number:05}@example.org'
        if pending and number in (10001,10002):email='pending-duplicate@example.org'
        return {'schema_version':1,'survey_id':SURVEY,'response_id':f'R_LARGE_{number:05}','email':email,'submitted_at':stamp,'updated_at':stamp,
          'answers':{'county_geoid':county,'municipality':town,'first_languages':[first],'languages':profiles,'aggregate_permission':'no' if pending and number%10==0 else 'yes'}}
    for county,total in targets.items():
        towns=sorted([t['id'] for t in refs['municipalities'] if t['county']==county and t['id']!=nb['id']])
        for j in range(total):
            town=nb['id'] if county=='34023' and j<700 else towns[j%len(towns)]
            # Leave some honest unspecified responses; never reduce the required 700.
            if town!=nb['id'] and j%29==0:town=None
            rows.append(row(county,town,len(rows)+1))
    for j in range(100):
        county=counties[j%21];town=next(t['id'] for t in refs['municipalities'] if t['county']==county)
        rows.append(row(county,town,len(rows)+1,True))
    content=json.dumps({'schema_version':1,'synthetic':True,'submissions':rows},separators=(',',':'))+'\n'
    meta={'dataset':SURVEY,'seed':10000,'as_of':anchor.isoformat(),'approved_setup_count':10000,'additional_pending':100,'counties':targets,'new_brunswick':nb['id'],'new_brunswick_approved':700}
    if path.exists():
        if path.read_text()!=content:raise SystemExit('Existing dataset differs; refusing overwrite. Preserve decisions and create a new dataset version.')
    elif check:raise SystemExit('Dataset missing; generate it first.')
    else:
        folder.mkdir(parents=True,exist_ok=True);folder.chmod(0o700);path.write_text(content);path.chmod(0o600)
        manifest.write_text(json.dumps(meta,indent=2)+'\n');manifest.chmod(0o600)
    print('SYN_LIT_10000_V1: 10000 eligible candidates + 100 pending candidates; source preserved on reruns.')
