#!/usr/bin/env python3
"""Checks on macOS/Linux. Uses an isolated temporary database; never production config."""
import hashlib, http.cookiejar, os, pathlib, re, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[1]
def run(args,**kwargs):
    return subprocess.run(args,capture_output=True,text=True,**kwargs)
files=list(ROOT.glob('*.php'))+list((ROOT/'setup').glob('*.php'))
for p in files:
    result=run(['php','-l',str(p)]); assert result.returncode==0,result.stdout+result.stderr
print('PASS: PHP syntax for',len(files),'files',flush=True)
for p in ROOT.glob('*.php'):
    assert not re.search(r'(?:src|href)=["\']https?://|@import\s+url\(["\']https?://',p.read_text()),p
assert 'INSERT INTO' not in (ROOT/'setup/schema.sql').read_text()
assert not (ROOT/'db_config.php').exists()
for p in ROOT.glob('*.php'):
    for relative in re.findall(r'(assets/offline/[\w./-]+)',p.read_text()): assert (ROOT/relative).exists(),relative
print('PASS: local UI resources and schema contain no original data',flush=True)
with tempfile.TemporaryDirectory(prefix='rts-complete-demo-') as td:
    temp=pathlib.Path(td); sock=str(temp/'mysql.sock'); db=None; web=None
    APP=temp/'app'; APP.mkdir()
    for p in ROOT.iterdir():
        if p.is_file() and p.suffix in ['.php','.js','.json']: shutil.copy2(p,APP/p.name)
    for folder in ['setup','assets','fonts','vendor']: shutil.copytree(ROOT/folder,APP/folder)
    key=run(['php',str(ROOT/'developer-tools/license.php'),'init','--private-key',str(temp/'test-private.pem'),'--public-key',str(APP/'license_public.pem')])
    assert key.returncode==0,key.stderr
    env=dict(os.environ,RTS_DEMO_DATA=td,RTS_DEMO_HEALTH='demo-test-health-token',RTS_DEMO_LICENSE_DIR=str(temp/'licensing'),RTS_DEMO_MACHINE=hashlib.sha256(b'http-test-machine').hexdigest())
    config=temp/'db_config.php'
    config.write_text("<?php\n$db_host='localhost';$db_user='root';$db_pass='';$db_name='rts_demo';\n")
    try:
        result=run(['mariadb-install-db','--datadir='+str(temp/'database'),'--auth-root-authentication-method=normal','--skip-test-db'])
        assert result.returncode==0,result.stderr
        db=subprocess.Popen(['mysqld','--datadir='+str(temp/'database'),'--socket='+sock,'--skip-networking','--pid-file='+str(temp/'db.pid'),'--log-error='+str(temp/'db.log')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        for _ in range(150):
            if run(['mariadb-admin','--socket='+sock,'-u','root','ping']).returncode==0: break
            assert db.poll() is None,(temp/'db.log').read_text()
            time.sleep(.1)
        else: raise RuntimeError('Temporary database did not start')
        php=['php','-d','mysqli.default_socket='+sock]
        result=run(php+[str(APP/'setup/install.php')],env=env,input='demo_admin\nDemo-Test-4826!\n')
        assert result.returncode==0,result.stdout+result.stderr
        again=run(php+[str(APP/'setup/install.php')],env=env,input='demo_admin\nDemo-Test-4826!\n')
        assert again.returncode!=0 and 'not empty' in again.stderr,again.stderr
        print('PASS: clean installation, environment data config, repeat-install refusal',flush=True)
        with socket.socket() as s: s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
        with open(temp/'web.log','w') as log:
            web=subprocess.Popen(php+['-S',f'127.0.0.1:{port}','-t',str(APP),str(APP/'router.php')],env=env,stdout=log,stderr=log)
            base=f'http://127.0.0.1:{port}'
            opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            for _ in range(50):
                try: page=opener.open(base+'/login.php').read().decode(); break
                except OSError: time.sleep(.1)
            token=re.search(r'name="csrf_token" value="([^"]+)"',page).group(1)
            response=opener.open(base+'/login.php',urllib.parse.urlencode({'csrf_token':token,'username':'demo_admin','password':'Demo-Test-4826!'}).encode())
            assert '/login.php' not in response.url,response.url
            response.read()
            for page in ['activation.php','members.php','create_rubber.php','prices.php','manage_wang.php','weigh_wang.php','rubbers.php','approve_weighing.php','demo_reset.php']:
                response=opener.open(base+'/'+page); body=response.read().decode()
                assert 'Fatal error' not in body and 'Warning:' not in body,page
            assert opener.open(base+'/_demo_health').read()==b'demo-test-health-token'
            for page in ['setup/schema.sql','setup/install.php','db_config.php','windows/Launcher.ps1','vendor/autoload.php','developer-tools/license.php','tests/license_test.php','license_public.pem']:
                try: opener.open(base+'/'+page); raise AssertionError('Internal file accessible: '+page)
                except urllib.error.HTTPError as error: assert error.code==404,(page,error.code)
            print('PASS: administrator login, workflow pages, identity check and private-file protection',flush=True)
            reset_csrf=re.search(r'name="csrf_token" value="([^"]+)"',body).group(1)
            expire="require "+repr(str(APP/'license_functions.php'))+"; rts_license_lock(function($dir){$s=rts_license_load_state($dir);$s['started_at']=time()-60*86400;$s['last_seen_at']=time();rts_license_save_state($dir,$s);});"
            result=run(php+['-r',expire],env=env); assert result.returncode==0,result.stderr
            response=opener.open(base+'/index.php'); expired_page=response.read().decode()
            assert response.url.endswith('/activation.php') and '080-8281906' in expired_page and 'saipunya' in expired_page
            for page in ['members.php','member_save.php','weigh_wang.php','export_rubbers_pdf.php','ensure_pdf_env.php']:
                response=opener.open(base+'/'+page); response.read(); assert response.url.endswith('/activation.php'),page
            blocked_post=opener.open(base+'/demo_reset.php',urllib.parse.urlencode({'csrf_token':reset_csrf,'confirmation':'ล้างข้อมูลเดโม','acknowledge':'1','include_members':'1'}).encode())
            assert blocked_post.url.endswith('/activation.php'); blocked_post.read()
            count=run(['mariadb','--socket='+sock,'-u','root','-N','rts_demo','-e','SELECT COUNT(*) FROM tbl_member;'])
            assert count.stdout.strip()=='5','Expired POST changed the data'
            assert opener.open(base+'/assets/offline/bootstrap-5.3.3.min.css').status==200
            request=re.search(r'id="requestCode"[^>]*>([^<]+)</textarea>',expired_page).group(1)
            token=re.search(r'name="csrf_token" value="([^"]+)"',expired_page).group(1)
            issued=run(php+[str(ROOT/'developer-tools/license.php'),'issue','--private-key',str(temp/'test-private.pem'),'--public-key',str(APP/'license_public.pem'),'--request',request,'--days','365'])
            assert issued.returncode==0,issued.stderr
            try:
                opener.open(base+'/activation.php',urllib.parse.urlencode({'csrf_token':'wrong','activation_code':issued.stdout.strip()}).encode())
                raise AssertionError('Activation accepted a bad CSRF token')
            except urllib.error.HTTPError as error: assert error.code==403
            response=opener.open(base+'/activation.php',urllib.parse.urlencode({'csrf_token':token,'activation_code':'invalid'}).encode())
            assert 'รูปแบบรหัสใช้งานไม่ถูกต้อง' in response.read().decode()
            response=opener.open(base+'/activation.php',urllib.parse.urlencode({'csrf_token':token,'activation_code':issued.stdout.strip()}).encode())
            assert 'เปิดใช้รหัสสำเร็จ' in response.read().decode()
            response=opener.open(base+'/members.php'); restored_body=response.read().decode()
            assert not response.url.endswith('/activation.php') and 'สมาชิกสมมติ 1' in restored_body
            print('PASS: expired GET/POST blocked, data retained, contact shown, CSRF enforced and signed activation resumes HTTP access',flush=True)

        # Execute the existing business workflow suite against this temporary instance.
        integration=ROOT.parent/'tests/weighing_integration.php'
        if integration.exists():
            source=integration.read_text()
            source=source.replace("__DIR__ . '/../functions.php'",repr(str(APP/'functions.php')))
            source=source.replace("__DIR__ . '/../demo_reset_functions.php'",repr(str(APP/'demo_reset_functions.php')))
            source=source.replace("__DIR__ . '/../db_config.php'",repr(str(config)))
            source=source.replace("__DIR__ . '/../print_deduction_check.php'",repr(str(APP/'print_deduction_check.php')))
            test=temp/'integration.php'; test.write_text(source)
            result=run(php+[str(test)],env=env)
            assert result.returncode==0,result.stdout[-2500:]+result.stderr[-2500:]
            print('PASS: existing business workflow suite ('+str(result.stdout.count('PASS:'))+' checks)',flush=True)
        # Confirm backup output can be imported into another isolated database.
        backup=run(['mariadb-dump','--socket='+sock,'-u','root','--single-transaction','--hex-blob','rts_demo'])
        assert backup.returncode==0,backup.stderr
        result=run(['mariadb','--socket='+sock,'-u','root','-e','CREATE DATABASE rts_demo_restore CHARACTER SET utf8mb4'])
        assert result.returncode==0,result.stderr
        restored=run(['mariadb','--socket='+sock,'-u','root','rts_demo_restore'],input=backup.stdout)
        assert restored.returncode==0,restored.stderr
        counts=run(['mariadb','--socket='+sock,'-u','root','-N','rts_demo_restore','-e','SELECT COUNT(*) FROM tbl_member; SELECT COUNT(*) FROM tbl_user; SELECT COUNT(*) FROM tbl_rubber;'])
        assert counts.stdout.split()==['5','1','0'],counts.stdout
        print('PASS: SQL backup round-trip preserves demo members and account',flush=True)
    finally:
        if web: web.terminate(); web.wait(timeout=10)
        if db: db.terminate(); db.wait(timeout=30)
print('These checks do not execute the Windows installer or Windows launcher.',flush=True)
