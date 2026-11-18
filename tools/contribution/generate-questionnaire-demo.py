"""Generate NEW fictional questionnaire data only; never overwrite old datasets."""
import json, random, sys
from datetime import datetime, timedelta, timezone
from pathlib import Path
root=Path(__file__).resolve().parents[2];folder=Path(sys.argv[1]) if len(sys.argv)>1 else root/'private/lit-questionnaire-10000-v2'
if (folder/'submissions.json').exists():
    print('Questionnaire dataset already exists; preserved.');sys.exit(0)
rng=random.Random(20261114);anchor=datetime(2026,11,14,12,0,tzinfo=timezone.utc)
refs=json.loads((root/'web/modules/custom/lit_map/data/survey-statewide-references.json').read_text())
counties={c['geoid']:c['name'] for c in json.loads((root/'web/modules/custom/lit_map/data/nj-counties.json').read_text())['counties']}
targets={c:40 for c in counties};targets.update({'34013':2200,'34017':1800,'34003':1600,'34027':1200,'34039':1200,'34023':1400})
cat=json.loads((root/'web/modules/custom/lit_contribution/data/questionnaire.v2.json').read_text())['questions']
langs=['English','Spanish','Korean','Portuguese','Mandarin','Arabic','Hindi','American Sign Language','Cantonese','French']
rows=[];nb=next(t for t in refs['municipalities'] if t['name']=='New Brunswick city');i=0
for county,total in sorted(targets.items()):
    towns=[t for t in refs['municipalities'] if t['county']==county and t['id']!=nb['id']]
    for n in range(total):
        i+=1;town=nb if county==nb['county'] and n<700 else (rng.choice(towns) if rng.random()<.8 else None)
        q={}
        for f in cat:
            typ=f['type'];opts=list(f.get('options',{}));key=f['key']
            q[key]=({'choice':None,'text':''} if typ=='gender' else {'name':'','email':''} if typ=='contact' else None)
            if typ=='choice':q[key]=rng.choice(opts+[None])
            if typ=='multiple':q[key]=rng.sample(opts,rng.randint(1,min(3,len(opts)))) if rng.random()<.8 else None
            if typ=='proficiency':q[key]={s:rng.choice(opts+[None]) for s in ['speaking','reading','writing']}
            if typ=='support':q[key]={s:rng.choice(opts+[None]) for s in ['work_school','healthcare','government','community']}
        q['q01']='yes' if town and town['name']=='Newark city' else 'no'
        q['q02']=(town['name'].rsplit(' ',1)[0]+', New Jersey') if town and q['q01']=='no' else None;q['q03']=counties[county]
        q['q04']=rng.randint(18,85);q['q18']=rng.randint(5,18) if q['q15']=='yes' else None
        chosen=(['English'] if rng.random()<.88 else [])
        chosen+=rng.sample([l for l in langs if l not in chosen],rng.randint(1,3-len(chosen)))
        q['q07']=[{'text':l,'age':rng.randint(0,18)} for l in chosen]+[{'text':'','age':None}]*(3-len(chosen))
        q['q08']=[rng.choice(['rarely','few_year','few_month','weekly','daily',None]) if j<len(chosen) else None for j in range(3)]
        for j in [1,2]:
            if j>=len(chosen):
                q['q'+str(10+j)]={s:None for s in ['speaking','reading','writing']}
                q['q'+str(12+j)]={s:None for s in ['work_school','healthcare','government','community']}
        q['q22']=rng.choice(list(range(0,101,10))+[None]);q['q26']=rng.randint(1,12) if q['q25']=='yes' else None;q['q27']=q['q27'] if q['q25']=='yes' else None
        q['q29']=f'interview-demo-{i}@example.org' if q['q28']=='yes' else None
        stamp=(anchor-timedelta(days=rng.randrange(500),seconds=rng.randrange(86400))).isoformat(timespec='seconds')
        rows.append(dict(schema_version=2,survey_id='SYN_LIT_QUESTIONNAIRE_V2',response_id=f'R_QV2_{i:05}',email=f'questionnaire-demo-{i}@example.org',submitted_at=stamp,updated_at=stamp,answers={'consent':True,'map_permission':'yes','questions':q,'resolutions':{'municipality':town['id'] if town else None}}))
assert len(rows)==10000
folder.mkdir(mode=0o700,parents=True,exist_ok=True)
p=folder/'submissions.json';p.write_text(json.dumps({'schema_version':2,'synthetic':True,'submissions':rows},ensure_ascii=False));p.chmod(0o600)
p=folder/'manifest.json';p.write_text(json.dumps(dict(synthetic=True,seed=20261114,anchor=anchor.isoformat(),counties=targets,new_brunswick=nb['id'],new_brunswick_count=700,count=len(rows)),indent=2));p.chmod(0o600)
print('Created 10,000 new synthetic questionnaire responses; no database or publication changed.')
