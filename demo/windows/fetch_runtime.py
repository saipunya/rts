#!/usr/bin/env python3
"""Fetch official Windows x64 packages; pin and verify hashes in runtime-lock.json."""
import concurrent.futures, hashlib, json, pathlib, urllib.request
BASE = pathlib.Path(__file__).resolve().parent
CACHE = BASE / 'cache'
CACHE.mkdir(exist_ok=True)
LOCK = BASE / 'runtime-lock.json'
def get(url):
    return urllib.request.urlopen(url, timeout=120).read()
if LOCK.exists():
    lock = json.loads(LOCK.read_text())
else:
    release = json.loads(get('https://downloads.php.net/~windows/releases/releases.json'))['8.3']
    php = release['nts-vs16-x64']['zip']
    maria_name = 'mariadb-11.4.13-winx64.zip'
    maria_base = 'https://archive.mariadb.org/mariadb-11.4.13/winx64-packages/'
    sums = get(maria_base + 'sha256sums.txt').decode()
    maria_hash = next(line.split()[0] for line in sums.splitlines() if line.split()[-1].lstrip('*./') == maria_name)
    lock = {
        'php': {'version': release['version'], 'url': 'https://downloads.php.net/~windows/releases/' + php['path'], 'file': php['path'], 'sha256': php['sha256']},
        'mariadb': {'version': '11.4.13', 'url': maria_base + maria_name, 'file': maria_name, 'sha256': maria_hash},
        'vcredist': {'url': 'https://aka.ms/vc14/vc_redist.x64.exe', 'file': 'vc_redist.x64.exe'},
    }
if 'mariadb_source' not in lock:
    source_base = 'https://archive.mariadb.org/mariadb-11.4.13/source/'
    source_name = 'mariadb-11.4.13.tar.gz'
    source_sums = get(source_base + 'sha256sums.txt').decode()
    source_hash = next(line.split()[0] for line in source_sums.splitlines() if line.split()[-1].lstrip('*./') == source_name)
    lock['mariadb_source'] = {'version': '11.4.13', 'url': source_base + source_name, 'file': source_name, 'sha256': source_hash}
def download(item):
    name, info = item
    target = CACHE / info['file']
    if not target.exists():
        print('Downloading ' + name, flush=True)
        data = get(info['url'])
        target.write_bytes(data)
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    if info.get('sha256') and digest != info['sha256']:
        raise RuntimeError('SHA256 mismatch: ' + name)
    info['sha256'] = digest
    print(name + ': SHA256 ' + digest, flush=True)
    return name, info
with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
    lock = dict(pool.map(download, lock.items()))
LOCK.write_text(json.dumps(lock, indent=2) + '\n')
print('Official runtime archives verified and locked. VC signature is checked again by the Windows installer.')
