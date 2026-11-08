#!/usr/bin/env python3
"""Vendor UI assets, preserving original library versions and UI behavior."""
import concurrent.futures, hashlib, json, pathlib, re, urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[1]
ASSETS={
'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css':'bootstrap-5.3.2.min.css',
'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css':'bootstrap-5.3.3.min.css',
'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js':'bootstrap-5.3.2.min.js',
'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js':'bootstrap-5.3.2.bundle.min.js',
'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js':'bootstrap-5.3.3.bundle.min.js',
'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js':'popper-2.11.8.min.js',
'https://unpkg.com/aos@2.3.1/dist/aos.css':'aos-2.3.1.css',
'https://unpkg.com/aos@2.3.1/dist/aos.js':'aos-2.3.1.js',
'https://cdn.jsdelivr.net/npm/lucide@0.278.0/dist/umd/lucide.min.js':'lucide-0.278.0.min.js',
'https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js':'lucide-0.468.0.min.js',
'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js':'chart-4.4.1.umd.min.js',
'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css':'bootstrap-icons-1.11.3.min.css',
'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2':'fonts/bootstrap-icons.woff2',
'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff':'fonts/bootstrap-icons.woff',
}
DEST=ROOT/'assets/offline'; DEST.mkdir(parents=True,exist_ok=True)
def fetch(item):
 url, name=item; target=DEST/name; target.parent.mkdir(parents=True,exist_ok=True)
 if not target.exists(): target.write_bytes(urllib.request.urlopen(url,timeout=90).read())
 return {'url':url,'file':name,'sha256':hashlib.sha256(target.read_bytes()).hexdigest()}
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool: manifest=list(pool.map(fetch, ASSETS.items()))
(DEST/'sources.json').write_text(json.dumps(manifest,indent=2)+'\n')
for p in list(ROOT.glob('*.php'))+[ROOT/'sw.js']:
 text=p.read_text()
 for url,name in ASSETS.items(): text=text.replace(url,'assets/offline/'+name)
 text=re.sub(r'https://fonts.googleapis.com/css2\?[^"\s)]+','assets/offline/sarabun.css',text)
 p.write_text(text)
css=[]
for weight,style in [(100,'Thin'),(200,'ExtraLight'),(300,'Light'),(400,'Regular'),(500,'Medium'),(600,'SemiBold'),(700,'Bold'),(800,'ExtraBold')]:
 css.append("@font-face{font-family:Sarabun;font-style:normal;font-weight:%d;font-display:swap;src:url('../../fonts/Sarabun-%s.ttf') format('truetype')}"%(weight,style))
(DEST/'sarabun.css').write_text('\n'.join(css)+'\n')
sw=ROOT/'sw.js'; text=sw.read_text().replace("const CACHE_NAME = CACHE_PREFIX + 'v2';", "const CACHE_NAME = CACHE_PREFIX + 'offline-v3';")
text=text.replace('if (STATIC_ASSETS.includes(event.request.url))', 'if (STATIC_ASSETS.some((asset) => new URL(asset, self.registration.scope).href === event.request.url))')
sw.write_text(text)
print('Offline UI assets prepared:',len(manifest))
