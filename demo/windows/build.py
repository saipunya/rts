#!/usr/bin/env python3
"""Build the complete Windows x64 installer on macOS/Linux/Windows with NSIS."""
import hashlib, json, pathlib, shutil, subprocess, sys, urllib.request, zipfile
HERE=pathlib.Path(__file__).resolve().parent
ROOT=HERE.parent
STAGE=HERE/'stage'
DIST=ROOT/'dist'
def copy(src,dst):
    dst.parent.mkdir(parents=True,exist_ok=True); shutil.copy2(src,dst)
def download(url,target):
    if not target.exists():
        target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes(urllib.request.urlopen(url,timeout=120).read())
    return target
subprocess.run([sys.executable,str(HERE/'fetch_runtime.py')],check=True)
subprocess.run([sys.executable,str(HERE/'prepare_offline.py')],check=True)
lock=json.loads((HERE/'runtime-lock.json').read_text())
if STAGE.exists(): shutil.rmtree(STAGE)
STAGE.mkdir(); DIST.mkdir(exist_ok=True)
for name,info in lock.items():
    archive=HERE/'cache'/info['file']
    assert hashlib.sha256(archive.read_bytes()).hexdigest()==info['sha256'],name
for name in ['php','mariadb']:
    target=STAGE/'runtime'/name; target.mkdir(parents=True)
    with zipfile.ZipFile(HERE/'cache'/lock[name]['file']) as archive:
        for entry in archive.infolist():
            relative=pathlib.PurePosixPath(entry.filename)
            if name=='mariadb': relative=pathlib.PurePosixPath(*relative.parts[1:])
            if not relative.parts or entry.is_dir(): continue
            if '..' in relative.parts or relative.is_absolute(): raise RuntimeError('Unsafe archive entry')
            out=target/pathlib.Path(*relative.parts); out.parent.mkdir(parents=True,exist_ok=True)
            out.write_bytes(archive.read(entry))
public_key=ROOT/'license_public.pem'
if not public_key.exists() or 'BEGIN PUBLIC KEY' not in public_key.read_text() or 'PRIVATE KEY' in public_key.read_text():
    raise RuntimeError('Initialize the developer signing key before building.')
copy(public_key, STAGE/'app/license_public.pem')
copy(HERE/'cache'/lock['vcredist']['file'], STAGE/'runtime/vc_redist.x64.exe')
copy(HERE/'cache'/lock['mariadb_source']['file'], STAGE/'licenses/sources'/lock['mariadb_source']['file'])
for p in ROOT.iterdir():
    if p.is_file() and p.suffix in ['.php','.js','.json'] and p.name not in ['db_config.php','test.php','ensure_pdf_env.php']:
        copy(p,STAGE/'app'/p.name)
for folder in ['assets','fonts','vendor','setup']:
    shutil.copytree(ROOT/folder,STAGE/'app'/folder,ignore=shutil.ignore_patterns('.DS_Store','*.bak','*.log'))
for p in HERE.glob('*.ps1'): copy(p,STAGE/'windows'/p.name)
# Windows PowerShell 5.1 reads Thai reliably with a UTF-8 BOM.
for p in (STAGE/'windows').glob('*.ps1'):
    p.write_text(p.read_text(encoding='utf-8-sig'),encoding='utf-8-sig')
shutil.copytree(ROOT/'manual',STAGE/'manual')
manual=STAGE/'manual/คู่มือใช้งาน.html'
manual.write_text(manual.read_text().replace("../fonts/","../app/fonts/"))
(STAGE/'version.txt').write_text('0.2.0\n')
copy(HERE/'runtime-lock.json',STAGE/'runtime-lock.json')
licenses={
 'Bootstrap-MIT.txt':'https://raw.githubusercontent.com/twbs/bootstrap/v5.3.3/LICENSE',
 'Bootstrap-Icons-MIT.txt':'https://raw.githubusercontent.com/twbs/icons/v1.11.3/LICENSE',
 'Popper-MIT.txt':'https://raw.githubusercontent.com/floating-ui/floating-ui/v2.11.8/LICENSE.md',
 'AOS-MIT.txt':'https://raw.githubusercontent.com/michalsnik/aos/v2.3.1/LICENSE',
 'Lucide-ISC.txt':'https://raw.githubusercontent.com/lucide-icons/lucide/0.468.0/LICENSE',
 'ChartJS-MIT.txt':'https://raw.githubusercontent.com/chartjs/Chart.js/v4.4.1/LICENSE.md',
 'Sarabun-OFL.txt':'https://raw.githubusercontent.com/google/fonts/main/ofl/sarabun/OFL.txt',
 'Kanit-OFL.txt':'https://raw.githubusercontent.com/google/fonts/main/ofl/kanit/OFL.txt',
}
for filename,url in licenses.items():
    cached=download(url,HERE/'cache/licenses'/filename)
    copy(cached,STAGE/'licenses'/filename)
copy(ROOT/'windows/THIRD-PARTY.md',STAGE/'licenses/THIRD-PARTY.md')
# Do not accidentally distribute an old or production database configuration.
assert not (STAGE/'app/db_config.php').exists()
assert not (STAGE/'app/developer-tools').exists()
assert not list((STAGE/'app').rglob('signing-private.pem'))
assert (STAGE/'app/license_public.pem').read_bytes()==public_key.read_bytes()
assert not list((STAGE/'app').glob('*.sql'))
assert all((STAGE/'runtime/mariadb/bin'/n).exists() for n in ['mariadbd.exe','mariadb-install-db.exe','mariadb.exe','mariadb-dump.exe','mariadb-admin.exe'])
compiler=shutil.which('makensis') or shutil.which('makensis.exe')
if not compiler: raise RuntimeError('Install NSIS and put makensis on PATH.')
subprocess.run([compiler,'-V2',str(HERE/'installer.nsi')],cwd=HERE,check=True)
exe=DIST/'RTS-Demo-Setup-x64.exe'
digest=hashlib.sha256(exe.read_bytes()).hexdigest()
(DIST/'SHA256SUMS.txt').write_text(digest+'  '+exe.name+'\n')
shutil.copytree(ROOT/'manual',DIST/'manual',dirs_exist_ok=True)
# Include fonts for the standalone HTML manual beside the installer too.
shutil.copytree(ROOT/'fonts',DIST/'fonts',dirs_exist_ok=True)
(DIST/'อ่านก่อนติดตั้ง.txt').write_text('RTS Demo สำหรับ Windows x64 รุ่นทดลอง 0.2.0 (ทดลอง 60 วัน)\n\n1. เปิด manual/คู่มือใช้งาน.html\n2. เรียก RTS-Demo-Setup-x64.exe บน Windows\n3. เปิด RTS Demo บน Desktop แล้วสร้างบัญชีใหม่\n\nไม่ต้องติดตั้ง XAMPP, PHP หรือ MySQL เพิ่ม\nเป็นชุดทดลองที่ยังต้องตรวจรับบน Windows จริงก่อนแจกใช้งาน\n')
print('Built:',exe,'size:',round(exe.stat().st_size/1024/1024,1),'MiB')
print('SHA256:',digest)
