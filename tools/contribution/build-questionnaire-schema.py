"""Document the input contract from the versioned transcription catalogue."""
import json
from pathlib import Path
root=Path(__file__).resolve().parents[2];cat=json.loads((root/'web/modules/custom/lit_contribution/data/questionnaire.v2.json').read_text())['questions']
text={'type':['string','null'],'maxLength':254};age={'type':['integer','null'],'minimum':0,'maximum':120};props={}
for f in cat:
 t=f['type'];choices={'enum':[None]+list(f.get('options',{}))}
 if t=='choice':s=choices
 elif t=='multiple':s={'anyOf':[{'type':'null'},{'type':'array','items':{'enum':list(f['options'])},'uniqueItems':True}]}
 elif t in ['age','years']:s=dict(age)
 elif t=='slider':s=dict(age,maximum=100)
 elif t=='languages':s={'type':'array','minItems':3,'maxItems':3,'items':{'type':'object','additionalProperties':False,'required':['text','age'],'properties':{'text':{'type':'string','maxLength':100},'age':age}}}
 elif t=='frequency':s={'type':'array','minItems':3,'maxItems':3,'items':choices}
 elif t in ['proficiency','support']:
  keys=['speaking','reading','writing'] if t=='proficiency' else ['work_school','healthcare','government','community'];s={'type':'object','additionalProperties':False,'required':keys,'properties':{k:choices for k in keys}}
 elif t=='gender':s={'type':'object','additionalProperties':False,'required':['choice','text'],'properties':{'choice':choices,'text':{'type':'string','maxLength':200}}}
 elif t=='contact':s={'type':'object','additionalProperties':False,'required':['name','email'],'properties':{'name':{'type':'string','maxLength':200},'email':{'type':'string','maxLength':254}}}
 else:s=dict(text,maxLength=2000 if t=='reflection' else 254)
 s['title']=f['label'];props[f['key']]=s
record={'type':'object','additionalProperties':False,'required':['schema_version','survey_id','response_id','email','submitted_at','updated_at','answers'],'properties':{'schema_version':{'const':2},'survey_id':{'type':'string','pattern':'^[A-Za-z0-9_-]{1,96}$'},'response_id':{'type':'string','pattern':'^[A-Za-z0-9_-]{1,96}$'},'email':{'type':'string','format':'email','maxLength':254},'submitted_at':{'type':'string','format':'date-time','pattern':'[+-][0-9]{2}:[0-9]{2}$'},'updated_at':{'type':'string','format':'date-time','pattern':'[+-][0-9]{2}:[0-9]{2}$'},'answers':{'type':'object','additionalProperties':False,'required':['consent','map_permission','questions'],'properties':{'consent':{'const':True},'map_permission':{'enum':['yes','no','unknown']},'questions':{'type':'object','additionalProperties':False,'properties':props},'resolutions':{'type':'object','description':'Private reviewer mapping, not original survey questions. Runtime validates canonical references.'}}}}}
schema={'$schema':'https://json-schema.org/draft/2020-12/schema','title':'LiT transcription-aligned normalized questionnaire export v2 — not a native Qualtrics mapping','type':'object','additionalProperties':False,'required':['export_version','submissions'],'properties':{'export_version':{'const':2},'submissions':{'type':'array','minItems':1,'maxItems':1000,'items':record}}}
(root/'docs/contribution/questionnaire-v2.schema.json').write_text(json.dumps(schema,indent=2,ensure_ascii=False)+'\n')
lines=['# Questionnaire question-key mapping','', 'Content source: the team printout transcription supplied in this conversation. No PDF is required or retained. Required flags and some display logic remain unconfirmed.','', '| Key | Transcribed question | Stored type |','| --- | --- | --- |']
lines += [f"| `{f['key']}` | {f['label']} | {f['type']} |" for f in cat]
lines += ['', 'The complete answer options and matrix descriptions are retained in `web/modules/custom/lit_contribution/data/questionnaire.v2.json`. The input schema is `questionnaire-v2.schema.json`. Runtime validation also checks geography, aliases, timestamps and consent/18+ eligibility. See `questionnaire-v2.md` for assumptions, privacy and operations.','']
(root/'docs/contribution/questionnaire-mapping.md').write_text('\n'.join(lines))
