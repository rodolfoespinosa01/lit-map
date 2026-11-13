#!/usr/bin/env python3
"""Build M4 static SVG assets and interior anchors; leaves M2 geometry untouched."""
import argparse, collections, csv, hashlib, io, json, sys, zipfile
from pathlib import Path
import xml.etree.ElementTree as ET
import shapefile, pyproj, shapely
from shapely.geometry import shape, Point
from shapely.ops import transform, polylabel

ROOT=Path(__file__).resolve().parents[2]
MODULE=ROOT/'web/modules/custom/lit_map'
SOURCES={
 'cb_2025_us_county_500k':'aa976c00b181939755d0da757f4c7c2dc0103c3b3b4530fb2a91c2bb62fc777c',
 'cb_2025_34_cousub_500k':'02eac39c75a27cead005918498ac046c43f3e6302508d94bc0b00c207bbe4d9c',
 'cb_2025_us_state_5m':'8a45692bc532dbd38938a1924f445850cef2682ea67d750d7fd2f19cfe836903'}
EXPECTED=[23,70,40,36,16,14,22,24,12,26,12,25,53,39,33,16,15,21,24,21,22]
NS='http://www.w3.org/2000/svg'; ET.register_namespace('',NS)
def tag(n):return '{'+NS+'}'+n
def sha(b):return hashlib.sha256(b).hexdigest()
def js(v):return (json.dumps(v,indent=2)+'\n').encode()
outputs={}
def source(stem):
 b=(Path(__file__).parent/'source'/f'{stem}.zip').read_bytes();assert sha(b)==SOURCES[stem]
 z=zipfile.ZipFile(io.BytesIO(b))
 if 'county_500k' not in stem:
  outputs[ROOT/'docs/bubbles'/f'{stem}.metadata.xml']=z.read(stem+'.shp.iso.xml')
 r=shapefile.Reader(**{e:io.BytesIO(z.read(stem+'.'+e)) for e in ('shp','shx','dbf')})
 return list(r.iterShapeRecords())
def largest(g):return max(g.geoms,key=lambda p:p.area) if g.geom_type=='MultiPolygon' else g
def anchor(g):
 p=polylabel(largest(g),tolerance=5)
 assert g.contains(p)
 return p
project=pyproj.Transformer.from_crs(4269,32111,always_xy=True).transform
counties=sorted([r for r in source('cb_2025_us_county_500k') if r.record.STATEFP=='34'],key=lambda r:r.record.GEOID)
countygeom={r.record.GEOID:transform(project,shape(r.shape.__geo_interface__)) for r in counties}
minx=min(g.bounds[0] for g in countygeom.values());maxy=max(g.bounds[3] for g in countygeom.values())
scale=760/(maxy-min(g.bounds[1] for g in countygeom.values()))
def screen(x,y):return 32+(x-minx)*scale,32+(maxy-y)*scale
def metadata(g):
 p=anchor(g);x,y=screen(p.x,p.y);a,b,c,d=g.bounds
 # Rounded anchors are also verified strictly interior.
 xx,yy=round(x,4),round(y,4)
 assert g.contains(Point(minx+(xx-32)/scale,maxy-(yy-32)/scale))
 return {'anchor':[xx,yy],'clearance':round(p.distance(g.boundary)*scale,4),
         'bbox':[round(32+(a-minx)*scale,4),round(32+(maxy-d)*scale,4),round((c-a)*scale,4),round((d-b)*scale,4)]}
def path_for(r,projector,screenfn):
 offsets=list(r.shape.parts)+[len(r.shape.points)];parts=[]
 for a,b in zip(offsets,offsets[1:]):
  pts=r.shape.points[a:b];assert pts[0]==pts[-1]
  parts.append('M'+' L'.join(f'{x:.3f},{y:.3f}' for x,y in [screenfn(*projector(x,y)) for x,y in pts])+' Z')
 return ' '.join(parts)
def svgroot(vb,label):
 root=ET.Element(tag('svg'),{'viewBox':vb,'preserveAspectRatio':'xMidYMid meet','role':'img','aria-label':label})
 ET.SubElement(root,tag('title')).text=label
 return root
def encode_svg(root):
 ET.indent(root);b=ET.tostring(root,encoding='utf-8')+b'\n'
 for e in root.iter():
  assert e.tag in {tag(x) for x in ['svg','g','path','title','desc','text','rect']}
  assert not any(k.lower().startswith('on') or 'href' in k for k in e.attrib)
 return b
meta={'vintage':2025,'projection':'EPSG:32111','viewBox':[0,0,464.231,824],
      'counties':{k:metadata(g) for k,g in countygeom.items()},'municipalities':{},'assets':{}}
municipalities=sorted(source('cb_2025_34_cousub_500k'),key=lambda r:r.record.GEOID)
assert len(municipalities)==len({r.record.GEOID for r in municipalities})==564
counts=collections.Counter('34'+r.record.COUNTYFP for r in municipalities)
assert list(dict(sorted(counts.items())).values())==EXPECTED
rows=[];refs=[];svgbytes={};rings=vertices=0
for county in countygeom:
 root=svgroot('0 0 464.231 824',f'2025 NJ municipal boundaries in county {county}; U.S. Census Bureau')
 for r in municipalities:
  if r.record.GEOID[:5]!=county:continue
  assert r.record.GEOID==r.record.STATEFP+r.record.COUNTYFP+r.record.COUSUBFP
  assert r.record.STATEFP=='34' and r.record.COUSUBFP!='00000'
  g=transform(project,shape(r.shape.__geo_interface__));assert g.is_valid and not g.is_empty
  p=anchor(g)
  # Generalized boundary files may differ slightly at their edges, but interior anchors must match the named county.
  assert countygeom[county].covers(p),(r.record.GEOID,r.record.NAME)
  id='m'+r.record.GEOID
  meta['municipalities'][id]={**metadata(g),'geoid':r.record.GEOID,'county':county,'name':r.record.NAMELSAD}
  refs.append({'id':id,'name':r.record.NAMELSAD,'county':county})
  d=path_for(r,project,screen)
  e=ET.SubElement(root,tag('path'),{'data-municipality':id,'data-geoid':r.record.GEOID,'data-county':county,'fill-rule':'evenodd','d':d})
  ET.SubElement(e,tag('title')).text=r.record.NAMELSAD
  rows.append({'id':id,'geoid':r.record.GEOID,'county':county,'name':r.record.NAMELSAD,'rings':len(r.shape.parts),'vertices':len(r.shape.points)})
  rings+=len(r.shape.parts);vertices+=len(r.shape.points)
 b=encode_svg(root);name=f'municipalities/{county}.{sha(b)[:12]}.svg'
 outputs[MODULE/'assets'/name]=b;meta['assets'][county]=name;svgbytes[county]=len(b)
# USA: lower 48 + DC in EPSG:5070; separate rescaled Alaska/Hawaii insets.
states=[r for r in source('cb_2025_us_state_5m') if int(r.record.STATEFP)<60]
assert len(states)==51
usa=svgroot('0 0 1000 760','USA state overview — 50 states and DC; Alaska and Hawaii are separate rescaled insets')
state_rows=[]
for key,items,crs,box in [
 ('contiguous',[r for r in states if r.record.STATEFP not in ['02','15']],5070,[20,25,960,550]),
 ('alaska',[r for r in states if r.record.STATEFP=='02'],3338,[30,590,280,145]),
 ('hawaii',[r for r in states if r.record.STATEFP=='15'],'+proj=aea +lat_1=8 +lat_2=18 +lat_0=13 +lon_0=-157 +datum=NAD83 +units=m +no_defs',[350,590,250,145])]:
 proj=pyproj.Transformer.from_crs(4269,crs,always_xy=True).transform
 geoms=[transform(proj,shape(r.shape.__geo_interface__)) for r in items]
 bounds=[min(g.bounds[0] for g in geoms),min(g.bounds[1] for g in geoms),max(g.bounds[2] for g in geoms),max(g.bounds[3] for g in geoms)]
 x0,y0,w,h=box;s=min(w/(bounds[2]-bounds[0]),h/(bounds[3]-bounds[1]))
 def fit(x,y):return x0+(x-bounds[0])*s,y0+(bounds[3]-y)*s
 layer=ET.SubElement(usa,tag('g'),{'data-region':key})
 for r,g in zip(items,geoms):
  d=path_for(r,proj,fit);e=ET.SubElement(layer,tag('path'),{'data-state':r.record.STUSPS,'data-geoid':r.record.GEOID,'fill-rule':'evenodd','d':d})
  ET.SubElement(e,tag('title')).text=r.record.NAME
  pt=largest(g).representative_point();x,y=fit(pt.x,pt.y)
  # NJ gets its own external labeled control; small northeastern state labels use accessible titles.
  if key=='contiguous' and r.record.STUSPS not in ['NJ','RI','DE','DC','CT','MA','MD']:
   ET.SubElement(layer,tag('text'),{'x':f'{x:.2f}','y':f'{y:.2f}','text-anchor':'middle'}).text=r.record.STUSPS
  state_rows.append({'geoid':r.record.GEOID,'name':r.record.NAME,'abbreviation':r.record.STUSPS,'panel':key,'rings':len(r.shape.parts)})
 if key!='contiguous':
  ET.SubElement(usa,tag('text'),{'x':str(x0),'y':str(y0-10)}).text=('Alaska' if key=='alaska' else 'Hawaii')+' · inset, separate scale'
b=encode_svg(usa);usaname=f'us-states-2025.{sha(b)[:12]}.svg';outputs[MODULE/'assets'/usaname]=b;meta['usaAsset']=usaname
outputs[MODULE/'data/bubble-geography.json']=js(meta)
languages=json.loads((MODULE/'data/survey-references.json').read_text())['languages']
outputs[MODULE/'data/survey-statewide-references.json']=js({'languages':languages,'municipalities':refs})
for name,data in [('municipality-identifiers.csv',rows),('state-identifiers.csv',sorted(state_rows,key=lambda r:r['geoid']))]:
 out=io.StringIO();writer=csv.DictWriter(out,fieldnames=list(data[0]),lineterminator='\n');writer.writeheader();writer.writerows(data);outputs[ROOT/'docs/bubbles'/name]=out.getvalue().encode()
report={'sources':{stem:{'url':f'https://www2.census.gov/geo/tiger/GENZ2025/shp/{stem}.zip','sha256':digest} for stem,digest in SOURCES.items()},
 'tools':{'python':sys.version.split()[0],'pyshp':shapefile.__version__,'pyproj':pyproj.__version__,'PROJ':pyproj.proj_version_str,'shapely':shapely.__version__},
 'countyCount':21,'municipalityCount':564,'municipalitiesPerCounty':dict(sorted(counts.items())), 'municipalityRings':rings,'municipalityVertices':vertices,
 'statesIncludingDC':51,'allAnchorsInterior':True,'allMunicipalAnchorsInsideNamedCounty':True,'extraSimplification':False,
 'municipalitySvgBytes':svgbytes,'usaSvgBytes':len(b),'geometryMetadataBytes':len(outputs[MODULE/'data/bubble-geography.json'])}
outputs[ROOT/'docs/bubbles/geography-report.json']=js(report)
args=argparse.ArgumentParser();args.add_argument('--check',action='store_true');opts=args.parse_args()
for path,content in outputs.items():
 if opts.check:assert path.read_bytes()==content,str(path)
 else:path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(content)
print(('Verified' if opts.check else 'Generated')+': 564 municipalities, 21 county relationships, 51 states/DC, all interior anchors, reproducible SVGs.')
