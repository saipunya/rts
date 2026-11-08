#!/usr/bin/env python3
"""Validate the assembled installer inputs and produced Windows executable."""
import hashlib,json,pathlib,re,struct
ROOT=pathlib.Path(__file__).resolve().parents[1]
STAGE=ROOT/'windows/stage'
EXE=ROOT/'dist/RTS-Demo-Setup-x64.exe'
def pe_machine(path):
 data=path.read_bytes(); assert data[:2]==b'MZ',path
 offset=struct.unpack_from('<I',data,0x3c)[0]
 assert data[offset:offset+4]==b'PE\0\0',path
 return struct.unpack_from('<H',data,offset+4)[0]
assert pe_machine(EXE) in (0x14c,0x8664)
for name in ['runtime/php/php.exe','runtime/mariadb/bin/mariadbd.exe','runtime/mariadb/bin/mariadb-install-db.exe','runtime/mariadb/bin/mariadb-dump.exe','runtime/mariadb/bin/mariadb-admin.exe']:
 assert pe_machine(STAGE/name)==0x8664,name
print('PASS: actual PE installer and Windows x64 runtime binaries')
expected=(ROOT/'dist/SHA256SUMS.txt').read_text().split()[0]
assert hashlib.sha256(EXE.read_bytes()).hexdigest()==expected
lock=json.loads((STAGE/'runtime-lock.json').read_text())
assert hashlib.sha256((STAGE/'runtime/vc_redist.x64.exe').read_bytes()).hexdigest()==lock['vcredist']['sha256']
assert hashlib.sha256((STAGE/'licenses/sources'/lock['mariadb_source']['file']).read_bytes()).hexdigest()==lock['mariadb_source']['sha256']
assert not (STAGE/'app/db_config.php').exists()
assert not (STAGE/'app/developer-tools').exists()
assert not list((STAGE/'app').rglob('signing-private.pem'))
assert (STAGE/'app/license_public.pem').read_bytes()==(ROOT/'license_public.pem').read_bytes()
assert 'BEGIN PUBLIC KEY' in (STAGE/'app/license_public.pem').read_text()
for p in (STAGE/'app').rglob('*.pem'): assert 'PRIVATE KEY' not in p.read_text(),p
assert (STAGE/'version.txt').read_text().strip()=='0.2.0'
assert 'INSERT INTO' not in (STAGE/'app/setup/schema.sql').read_text()
print('PASS: installer checksum, bundled source and credential isolation')
for base in [ROOT,STAGE/'app']:
 for p in base.glob('*.php'):
  text=p.read_text()
  assert not re.search(r'(?:src|href)=["\']https?://|@import\s+url\(["\']https?://',text),p
  for asset in re.findall(r'(assets/offline/[\w./-]+)',text): assert (base/asset).exists(),(p,asset)
manual=STAGE/'manual/คู่มือใช้งาน.html'
for font in re.findall(r"url\('([^']+)'\)",manual.read_text()): assert (manual.parent/font).exists(),font
for path in ['windows/Launcher.ps1','windows/Check-Install.ps1','windows/Verify-Microsoft.ps1']:
 assert (STAGE/path).read_bytes().startswith(b'\xef\xbb\xbf'),path
 assert (STAGE/path).read_text(encoding='utf-8-sig')==(ROOT/path).read_text(encoding='utf-8-sig'),path
print('PASS: offline resource references, manual fonts and current launcher payload')
print('Installer size: %.1f MiB'%(EXE.stat().st_size/1024/1024))
print('Windows installation and runtime execution require a Windows test machine.')
