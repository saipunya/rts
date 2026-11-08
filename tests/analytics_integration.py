#!/usr/bin/env python3
"""Exercise the main system on synthetic data in an isolated temporary MariaDB instance."""
import datetime, http.cookiejar, json, os, pathlib, re, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[1]
def run(args,**kwargs): return subprocess.run(args,capture_output=True,text=True,**kwargs)
def extract(html): return json.loads(re.search(r'<script type="application/json" id="collectionAnalyticsData">(.*?)</script>',html,re.S).group(1))
with tempfile.TemporaryDirectory(prefix='rts-main-analytics-') as td:
    temp=pathlib.Path(td); app=temp/'app'; app.mkdir(); sock=str(temp/'mysql.sock'); db=None; web=None
    for p in ROOT.iterdir():
        if p.is_file() and p.suffix in ['.php','.js','.json'] and p.name!='db_config.php': shutil.copy2(p,app/p.name)
    for folder in ['assets','fonts','vendor','partials']: shutil.copytree(ROOT/folder,app/folder)
    (app/'db_config.php').write_text("<?php\n$db_host='localhost';$db_user='root';$db_pass='';$db_name='rts_analysis_test';\n")
    try:
        result=run(['mariadb-install-db','--datadir='+str(temp/'database'),'--auth-root-authentication-method=normal','--skip-test-db']); assert result.returncode==0,result.stderr
        db=subprocess.Popen(['mysqld','--datadir='+str(temp/'database'),'--socket='+sock,'--skip-networking','--pid-file='+str(temp/'db.pid'),'--log-error='+str(temp/'db.log')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        for _ in range(150):
            if run(['mariadb-admin','--socket='+sock,'-u','root','ping']).returncode==0: break
            assert db.poll() is None,(temp/'db.log').read_text()
            time.sleep(.1)
        else: raise RuntimeError('Temporary database did not start')
        dump=(ROOT/'rts_db.sql').read_text()
        ddl=re.findall(r'CREATE TABLE `[^`]+` \(.*?\) ENGINE=.*?;',dump,re.S)+re.findall(r'ALTER TABLE `[^`]+`\s+.*?;',dump,re.S)
        schema='CREATE DATABASE rts_analysis_test CHARACTER SET utf8mb4; USE rts_analysis_test;\n'+'\n'.join(ddl)
        schema=re.sub(r', AUTO_INCREMENT=\d+','',schema).replace('utf8mb3','utf8mb4')
        schema+="\nALTER TABLE tbl_member ADD COLUMN mem_personcode VARCHAR(4) NOT NULL DEFAULT '', ADD COLUMN mem_birthtext VARCHAR(8) NOT NULL DEFAULT '';"
        result=run(['mariadb','--socket='+sock,'-u','root'],input=schema); assert result.returncode==0,result.stderr
        php=['php','-d','mysqli.default_socket='+sock]
        seed=temp/'seed.php'
        seed.write_text("<?php\nrequire "+repr(str(app/'functions.php'))+"; ensure_weighing_schema(db());\n"+"$hash=password_hash('Temporary-analysis-482!',PASSWORD_DEFAULT); $stmt=db()->prepare(\"INSERT INTO tbl_user (user_username,user_password,user_fullname,user_level,user_status) VALUES ('analysis_staff',?,'Test staff','admin','active')\"); $stmt->bind_param('s',$hash); $stmt->execute();\n")
        result=run(php+[str(seed)]); assert result.returncode==0,result.stdout+result.stderr
        values=[]
        for i in range(220):
            date=(datetime.date(2025,1,1)+datetime.timedelta(days=i)).isoformat()
            for lane,qty,value,kind,number,name in [('1',100,1000,'member','M001','Test member'),('2',300,6000,'general','','Test grower')]:
                values.append(f"('{date}','{lane}','1','{number}','{name}','{kind}',{qty},{value},0,0,0,0,0,0,10,{value-10},'test','{date}','normal')")
        columns='ru_date,ru_lan,ru_group,ru_number,ru_fullname,ru_class,ru_quantity,ru_value,ru_hoon,ru_loan,ru_shortdebt,ru_deposit,ru_tradeloan,ru_insurance,ru_expend,ru_netvalue,ru_saveby,ru_savedate,record_status'
        sql=f"INSERT INTO tbl_rubber ({columns}) VALUES "+','.join(values)+';'
        sql+=f"INSERT INTO tbl_rubber ({columns}) VALUES ('2025-08-08','1','1','M999','Cancelled','member',999,999999,0,0,0,0,0,0,0,999999,'test','2025-08-08','cancelled'),('2025-08-08','1','1','','Blank identity','member',0,0,0,0,0,0,0,0,0,0,'test','2025-08-08','normal');"
        sql+="INSERT INTO tbl_price (pr_year,pr_date,pr_number,pr_price,pr_saveby,pr_savedate) VALUES (2568,'2025-08-08','1',99,'test','2025-08-08');"
        for index,record_id in enumerate(range(416,442),start=1):
            bags=12.5 if record_id==440 else (3 if record_id==439 else 1)
            weight=300 if record_id%2==0 else 100
            stamp='2025-08-08 09:00:00' if record_id==416 else f'2025-08-08 08:{index:02d}:00'
            sql+=f"INSERT INTO tbl_wangyang (wang_date,wang_name,wang_sack,wang_savedate) VALUES ('2025-08-07','Test placement {record_id}',{bags},'2025-08-07 08:00:00');"
            sql+=f"INSERT INTO tbl_wang_weighing (wang_id,ru_id,net_weight,price_id,price_per_kg,round_date,weighed_by,weighed_at) VALUES ({index},{record_id},{weight},1,20,'2025-08-08','test','{stamp}');"
        for record_id in range(419,443):
            stamp=f'2025-08-08 10:{record_id-419:02d}:00'
            sql+=f"INSERT INTO tbl_rubber_receipts (ru_id,issued_at,issued_by,original_data) VALUES ({record_id},'{stamp}','test','{{}}');"
        sql+="UPDATE tbl_wang_weighing SET deductions_saved_at=CONCAT('2025-08-08 11:',LPAD(ru_id-419,2,'0'),':00') WHERE ru_id BETWEEN 419 AND 440;"
        sql+="UPDATE tbl_wang_weighing SET deductions_saved_at='2025-08-08 11:00:00' WHERE ru_id=418;"
        sql+="UPDATE tbl_rubber_receipts SET issued_at='2025-08-08 12:00:00' WHERE ru_id IN (441,442);"
        sql+="UPDATE tbl_rubber SET ru_fullname='Member <script>bad</script>' WHERE ru_id=439;"

        result=run(['mariadb','--socket='+sock,'-u','root','rts_analysis_test'],input=sql); assert result.returncode==0,result.stderr
        with socket.socket() as s: s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
        log=open(temp/'web.log','w')
        web=subprocess.Popen(php+['-S',f'127.0.0.1:{port}','-t',str(app)],stdout=log,stderr=log)
        base=f'http://127.0.0.1:{port}'
        opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        def page(query=''):
            body=opener.open(base+'/collection_analysis.php'+query).read().decode()
            assert 'Fatal error' not in body and 'Warning:' not in body,body[-1000:]
            return body
        for _ in range(50):
            try: public=opener.open(base+'/index.php').read().decode(); break
            except OSError: time.sleep(.1)
        public_data=json.loads(re.search(r'<script type="application/json" id="publicOverviewData">(.*?)</script>',public,re.S).group(1))
        assert len(public_data['dates'])==12 and public_data['quantity']==[400]*12
        assert public_data['value']==[7000]*12 and '88,000.00' in public
        def feed(html,feed_id):
            return re.search(r'<section class="public-recent" id="'+feed_id+r'".*?</section>',html,re.S).group(0)
        def feed_ids(html,feed_id): return [int(value) for value in re.findall(r'data-record-id="(\d+)"',feed(html,feed_id))]
        recent=feed(public,'recentWeighed')
        printed=feed(public,'recentReceipts')
        assert feed_ids(public,'recentWeighed')==[416]+list(range(440,421,-1)),feed_ids(public,'recentWeighed')
        assert feed_ids(public,'recentReceipts')==[442]+list(range(440,421,-1)),feed_ids(public,'recentReceipts')
        assert 440 in feed_ids(public,'recentWeighed') and 440 in feed_ids(public,'recentReceipts')
        assert 442 not in feed_ids(public,'recentWeighed') and 441 not in feed_ids(public,'recentWeighed')+feed_ids(public,'recentReceipts')
        assert '4,200.00' in recent and '3,900.00' in printed
        assert '33.50' in recent and '32.50' in printed and '—' in printed
        for section in [recent,printed]:
            assert 'มูลค่ารับซื้อ' not in section and 'บาท' not in section
            assert 'Test grower' in section and 'Member &lt;script&gt;bad&lt;/script&gt;' in section
            assert '<script>bad</script>' not in section
        comparison=temp/'receipt-comparison.php'
        comparison.write_text("<?php require "+repr(str(app/'functions.php'))+"; echo json_encode(array_map('intval',array_slice(array_column(issued_rubber_receipts(db())['rows'],'ru_id'),0,20)));")
        result=run(php+[str(comparison)]); assert result.returncode==0,result.stderr
        assert feed_ids(public,'recentReceipts')==json.loads(result.stdout)
        print('PASS: separate 20-row milestone feeds, actual weighing order, same receipt-page membership/order, overlap allowed and cancelled/legacy handling',flush=True)
        assert 'analysisFrom' not in public and 'collectionAnalyticsData' not in public
        assert 'weigh_wang.php' not in public and 'id="quantityChart"' not in public
        assert 'เข้าสู่ระบบเจ้าหน้าที่' in public and 'สำหรับสมาชิก' in public
        for internal in ['partials/collection-analytics-data.php','partials/collection-analytics-panel.php']:
            try:
                opener.open(base+'/'+internal); raise AssertionError('Internal analysis template was accessible')
            except urllib.error.HTTPError as error: assert error.code==404
        assert '7,000.00' in public and '39,600.00' not in public
        unchanged=opener.open(base+'/index.php?date_from=2030-01-01&lan=99').read().decode()
        assert '88,000.00' in unchanged and 'analysisFrom' not in unchanged
        denied=opener.open(base+'/collection_analysis.php')
        assert '/login.php' in denied.url
        login=denied.read().decode()
        csrf=re.search(r'name="csrf_token" value="([^"]+)"',login).group(1)
        logged=opener.open(base+'/login.php',urllib.parse.urlencode({'csrf_token':csrf,'username':'analysis_staff','password':'Temporary-analysis-482!'}).encode())
        logged.read(); assert '/login.php' not in logged.url
        html=page()
        print('PASS: public homepage has read-only 12-day summaries; analysis requires staff login',flush=True)
        data=extract(html)
        assert len(data['dates'])==220 and sum(data['quantity'])==88000,data['dates'][:2]
        assert data['price'][-1]==17.5 and sum(data['value'])==1540000
        assert data['members'][-1]==1 and data['general'][-1]==1
        print('PASS: 440 grouped rows, full 220-day series, weighted prices, cancelled exclusion and daily unique identities',flush=True)
        filtered=extract(page('?date_from=2025-08-07&date_to=2025-08-08&lan=1'))
        assert filtered['dates']==['2025-08-07','2025-08-08'] and filtered['quantity']==[100,100]
        assert filtered['price']==[10,10] and filtered['general']==[0,0]
        assert filtered['lanes']==['ลาน 1'] and filtered['laneQuantity']==[200]
        print('PASS: date/lane filter applies consistently to every series and lane total',flush=True)
        for query in ['?date_from=2025-08-08&date_to=2025-01-01','?date_from=2025-02-30','?lan=99','?lan[]=1']:
            assert extract(page(query))['dates']==[],query
        assert extract(page('?date_from=2030-01-01'))['dates']==[]
        print('PASS: invalid/reversed filters and empty periods show no invented data',flush=True)
        for resource in ['/assets/js/public-overview.js','/assets/css/public-overview.css','/assets/js/collection-analytics.js','/assets/css/collection-analytics.css','/assets/css/operations-theme.css','/assets/vendor/chart-4.4.1.umd.min.js','/fonts/Sarabun-Regular.ttf']:
            assert urllib.request.urlopen(base+resource).status==200,resource
        print('PASS: chart renderer, bundled Chart.js, shared theme and Thai font are served',flush=True)
        if os.environ.get('RTS_ANALYTICS_PREVIEW')=='1':
            print('PREVIEW '+base,flush=True)
            with open('/dev/tty') as terminal: terminal.readline()
        source=(ROOT/'tests/weighing_integration.php').read_text()
        for filename in ['functions.php','demo_reset_functions.php','db_config.php','print_deduction_check.php']:
            source=source.replace("__DIR__ . '/../"+filename+"'",repr(str(app/filename)))
        test=temp/'weighing.php';test.write_text(source)
        result=run(php+[str(test)]); assert result.returncode==0,result.stdout[-2000:]+result.stderr[-2000:]
        print('PASS: existing weighing workflow suite ('+str(result.stdout.count('PASS:'))+' checks)',flush=True)
        few="DELETE FROM tbl_rubber_receipts; DELETE FROM tbl_wang_weighing; DELETE FROM tbl_wangyang; DELETE FROM tbl_rubber; INSERT INTO tbl_rubber ("+columns+") VALUES "+values[2]+","+values[0]+";"
        result=run(['mariadb','--socket='+sock,'-u','root','rts_analysis_test'],input=few); assert result.returncode==0,result.stderr
        few_html=opener.open(base+'/index.php').read().decode()
        assert not feed_ids(few_html,'recentWeighed') and not feed_ids(few_html,'recentReceipts')
        transition=temp/'stage-transition.php'
        transition.write_text("<?php require "+repr(str(app/'functions.php'))+"; $id=(int)db()->query('SELECT MAX(ru_id) FROM tbl_rubber')->fetch_row()[0]; db()->query(\"INSERT INTO tbl_wangyang (wang_date,wang_name,wang_sack,wang_savedate) VALUES ('2025-08-07','Stage test',2,'2025-08-07 08:00:00')\"); $wang=(int)db()->insert_id; db()->query(\"INSERT INTO tbl_wang_weighing (wang_id,ru_id,net_weight,price_id,price_per_kg,round_date,weighed_by,weighed_at) VALUES ($wang,$id,100,1,10,'2025-08-08','test','2025-08-08 08:00:00')\"); echo $id;")
        result=run(php+[str(transition)]); assert result.returncode==0,result.stderr; record_id=int(result.stdout)
        staged=opener.open(base+'/index.php').read().decode()
        assert feed_ids(staged,'recentWeighed')==[record_id] and not feed_ids(staged,'recentReceipts')
        result=run(['mariadb','--socket='+sock,'-u','root','rts_analysis_test','-e',f"UPDATE tbl_wang_weighing SET approval_route='zero', deductions_saved_at='2025-08-08 08:30:00' WHERE ru_id={record_id}"]); assert result.returncode==0,result.stderr
        staged=opener.open(base+'/index.php').read().decode(); assert not feed_ids(staged,'recentReceipts')
        result=run(['mariadb','--socket='+sock,'-u','root','rts_analysis_test','-e',f"INSERT INTO tbl_rubber_receipts (ru_id,issued_at,issued_by,original_data) VALUES ({record_id},'2025-08-08 09:00:00','test','{{}}')"]); assert result.returncode==0,result.stderr
        staged=opener.open(base+'/index.php').read().decode()
        assert feed_ids(staged,'recentWeighed')==[record_id] and feed_ids(staged,'recentReceipts')==[record_id]
        result=run(['mariadb','--socket='+sock,'-u','root','rts_analysis_test','-e',f"UPDATE tbl_rubber SET record_status='cancelled' WHERE ru_id={record_id}"]); assert result.returncode==0,result.stderr
        cancelled=opener.open(base+'/index.php').read().decode()
        assert not feed_ids(cancelled,'recentWeighed') and not feed_ids(cancelled,'recentReceipts')
        print('PASS: unweighed → weighed → ready but not issued → receipt issued → cancelled transitions match the two feeds',flush=True)

    finally:
        if web: web.terminate();web.wait(timeout=10)
        if db: db.terminate();db.wait(timeout=30)
print('Temporary database removed. Production and demo configurations were not used.',flush=True)
