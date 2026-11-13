#!/usr/bin/env python3
"""Census shapefile -> projected NJ SVG. No network or Drupal/database access."""
import argparse
import csv
import hashlib
import io
import json
import re
import sys
import zipfile
from pathlib import Path
import xml.etree.ElementTree as ET

import pyproj
import shapefile
import shapely
from shapely.geometry import shape
from shapely.ops import transform

ROOT = Path(__file__).resolve().parents[2]
MODULE = ROOT / 'web/modules/custom/lit_map'
STEM = 'cb_2025_us_county_500k'
SOURCE_SHA256 = 'aa976c00b181939755d0da757f4c7c2dc0103c3b3b4530fb2a91c2bb62fc777c'
SOURCE_URL = f'https://www2.census.gov/geo/tiger/GENZ2025/shp/{STEM}.zip'
SVG = MODULE / 'templates/geometry/nj-counties.svg'
NS = 'http://www.w3.org/2000/svg'
ET.register_namespace('', NS)


def tag(name):
    return f'{{{NS}}}{name}'


def build(archive):
    raw = archive.read_bytes()
    assert hashlib.sha256(raw).hexdigest() == SOURCE_SHA256, 'Source checksum changed; review vintage before proceeding.'
    z = zipfile.ZipFile(io.BytesIO(raw))
    reader = shapefile.Reader(**{ext: io.BytesIO(z.read(f'{STEM}.{ext}')) for ext in ('shp', 'shx', 'dbf')}, encoding='utf-8')
    source_crs = pyproj.CRS.from_wkt(z.read(STEM + '.prj').decode())
    assert source_crs.to_epsg() == 4269
    target_crs = pyproj.CRS.from_epsg(32111)  # NAD83 / New Jersey, metres.
    project = pyproj.Transformer.from_crs(source_crs, target_crs, always_xy=True)
    records = sorted((r for r in reader.iterShapeRecords() if r.record.STATEFP == '34'), key=lambda r: r.record.GEOID)
    assert len(records) == 21
    assert {r.record.GEOID for r in records} == {f'34{i:03}' for i in range(1, 42, 2)}
    projected = []
    for r in records:
        geom = transform(project.transform, shape(r.shape.__geo_interface__))
        assert geom.is_valid and not geom.is_empty, r.record.NAME
        projected.append(geom)
    minx = min(g.bounds[0] for g in projected)
    miny = min(g.bounds[1] for g in projected)
    maxx = max(g.bounds[2] for g in projected)
    maxy = max(g.bounds[3] for g in projected)
    # One scale for both axes: no stretching. SVG y points down, north points up.
    scale = 760 / (maxy - miny)
    width = (maxx - minx) * scale + 64
    height = 824

    def screen(x, y):
        return (32 + (x - minx) * scale, 32 + (maxy - y) * scale)

    svg = ET.Element(tag('svg'), {
        'viewBox': f'0 0 {width:.3f} {height}',
        'preserveAspectRatio': 'xMidYMid meet', 'class': 'lit-map-geometry',
        'role': 'img', 'aria-label': 'New Jersey county boundaries, U.S. Census Bureau, 2025',
    })
    ET.SubElement(svg, tag('title')).text = 'New Jersey — 21 counties'
    ET.SubElement(svg, tag('desc')).text = 'U.S. Census Bureau 2025 cartographic boundaries, 1:500,000. NAD83 / New Jersey projection. Geographic reference only; no survey data.'
    paths = ET.SubElement(svg, tag('g'), {'class': 'lit-map-counties'})
    labels = ET.SubElement(svg, tag('g'), {'class': 'lit-map-labels', 'aria-hidden': 'true'})
    counties = []
    for r, geom in zip(records, projected):
        name, geoid = r.record.NAME, r.record.GEOID
        key = 'nj-' + re.sub(r'[^a-z0-9]+', '-', name.lower()).strip('-')
        rings = []
        offsets = list(r.shape.parts) + [len(r.shape.points)]
        for a, b in zip(offsets, offsets[1:]):
            points = r.shape.points[a:b]
            assert points[0] == points[-1]
            xy = [screen(*project.transform(x, y)) for x, y in points]
            rings.append('M' + ' L'.join(f'{x:.3f},{y:.3f}' for x, y in xy) + ' Z')
        path = ET.SubElement(paths, tag('path'), {
            'id': 'county-' + key, 'class': 'lit-map-county',
            'data-county-key': key, 'data-geoid': geoid, 'data-county-name': name,
            'd': ' '.join(rings), 'fill-rule': 'evenodd',
        })
        ET.SubElement(path, tag('title')).text = f'{name} County — Census GEOID {geoid}'
        # Interior point of the largest polygon; other parts remain in the path.
        polygons = list(geom.geoms) if geom.geom_type == 'MultiPolygon' else [geom]
        point = max(polygons, key=lambda p: p.area).representative_point()
        x, y = screen(point.x, point.y)
        label = ET.SubElement(labels, tag('text'), {'x': f'{x:.3f}', 'y': f'{y:.3f}', 'data-label-for': key})
        label.text = name
        counties.append({'key': key, 'geoid': geoid, 'name': name, 'statefp': r.record.STATEFP,
                         'countyfp': r.record.COUNTYFP, 'rings': len(rings),
                         'polygon_parts': len(polygons), 'vertices': len(r.shape.points)})
    ET.indent(svg)
    svg_bytes = ET.tostring(svg, encoding='utf-8') + b'\n'
    data = {'source': SOURCE_URL, 'vintage': 2025, 'projection': 'EPSG:32111', 'counties': counties}
    csv_out = io.StringIO(newline='')
    writer = csv.DictWriter(csv_out, fieldnames=list(counties[0]), lineterminator='\n')
    writer.writeheader()
    writer.writerows(counties)
    metadata = z.read(STEM + '.shp.iso.xml')
    report = {
        'source_sha256': SOURCE_SHA256, 'svg_sha256': hashlib.sha256(svg_bytes).hexdigest(),
        'tools': {'python': '.'.join(map(str, sys.version_info[:3])), 'pyshp': shapefile.__version__,
                  'pyproj': pyproj.__version__, 'PROJ': pyproj.proj_version_str,
                  'shapely': shapely.__version__, 'GEOS': shapely.geos_version_string},
        'source_crs': source_crs.to_string(), 'target_crs': target_crs.to_string(),
        'target_name': target_crs.name, 'viewBox': svg.attrib['viewBox'],
        'svg_units_per_metre': scale, 'counties': len(counties),
        'rings': sum(c['rings'] for c in counties), 'vertices': sum(c['vertices'] for c in counties),
        'all_projected_geometries_valid': True, 'additional_simplification': False,
    }
    outputs = {
        SVG: svg_bytes,
        MODULE / 'data/nj-counties.json': (json.dumps(data, indent=2) + '\n').encode(),
        ROOT / 'docs/geometry/county-identifiers.csv': csv_out.getvalue().encode(),
        ROOT / 'docs/geometry/conversion-report.json': (json.dumps(report, indent=2) + '\n').encode(),
        ROOT / 'docs/geometry/census-source-metadata.xml': metadata,
    }
    validate(svg_bytes, counties)
    return outputs


def validate(svg_bytes, counties):
    """Allowlist the SVG, and compare identifiers and every ring to source records."""
    assert b'<!' not in svg_bytes and b'<?' not in svg_bytes
    root = ET.fromstring(svg_bytes)
    allowed = {'svg', 'title', 'desc', 'g', 'path', 'text'}
    allowed_attrs = {'viewBox', 'preserveAspectRatio', 'class', 'role', 'aria-label',
                     'aria-hidden', 'id', 'data-county-key', 'data-geoid', 'data-county-name',
                     'd', 'fill-rule', 'x', 'y', 'data-label-for'}
    for element in root.iter():
        assert element.tag in {tag(t) for t in allowed}, element.tag
        assert set(element.attrib) <= allowed_attrs
        assert not any('url(' in v.lower() or 'javascript:' in v.lower() for v in element.attrib.values())
    paths = root.findall('.//' + tag('path'))
    assert len(paths) == len({p.attrib['id'] for p in paths}) == 21
    assert len({p.attrib['data-county-key'] for p in paths}) == 21
    assert len({p.attrib['data-geoid'] for p in paths}) == 21
    for p, c in zip(paths, counties):
        assert (p.attrib['data-county-key'], p.attrib['data-geoid'], p.attrib['data-county-name']) == (c['key'], c['geoid'], c['name'])
        assert p.attrib['d'].count('M') == p.attrib['d'].count('Z') == c['rings']
        assert p.attrib['d'].count('L') + c['rings'] == c['vertices']
    assert len(root.findall('.//' + tag('text'))) == 21


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, default=Path(__file__).parent / 'source' / (STEM + '.zip'))
    parser.add_argument('--check', action='store_true', help='Compare regenerated outputs byte-for-byte; do not write.')
    args = parser.parse_args()
    outputs = build(args.source)
    for path, content in outputs.items():
        if args.check:
            assert path.read_bytes() == content, f'Generated file differs: {path}'
        else:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(content)
    print(('Verified' if args.check else 'Generated') + ': 21 counties, source identifiers, all rings/vertices, safe SVG allowlist, deterministic outputs.')
