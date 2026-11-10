"""Run: python3 tests/user_permissions_http.py. Uses an isolated database and PHP server."""
from pathlib import Path
import subprocess,tempfile,shutil,socket,time,textwrap
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json
ROOT=Path(__file__).resolve().parents[1]
BASE=''

def php_literal(value):return "'"+str(value).replace('\\','\\\\').replace("'","\\'")+"'"

def main():
    global BASE
    with tempfile.TemporaryDirectory(prefix='rts-acl-http-') as directory:
        temp=Path(directory);app=temp/'app';app.mkdir()
        for source in ROOT.glob('*.php'):
            if source.name not in ['db_config.php','check-db.php']:shutil.copy2(source,app/source.name)
        for name in ['assets','fonts','partials','vendor']:(app/name).symlink_to(ROOT/name,target_is_directory=True)
        (app/'test').symlink_to(app,target_is_directory=True)
        setup=temp/'setup.php'
        setup.write_text("<?php\nrequire "+php_literal(ROOT/'db_config.php')+";\n$app="+php_literal(app)+";\n"+r'''
$source=new mysqli($db_host,$db_user,$db_pass,$db_name);
$name='rts_acl_http_'.bin2hex(random_bytes(5));
$source->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
file_put_contents($app.'/db_config.php',"<?php\n".'$db_host='.var_export($db_host,true).';$db_user='.var_export($db_user,true).';$db_pass='.var_export($db_pass,true).';$db_name='.var_export($name,true).';');
$target=new mysqli($db_host,$db_user,$db_pass,$name);
foreach(['tbl_user','tbl_member','tbl_rubber','tbl_price'] as $table)$target->query($source->query("SHOW CREATE TABLE `$table`")->fetch_row()[1]);
require $app.'/functions.php';
$db=db();ensure_weighing_schema($db);ensure_user_permission_schema($db);
$hash=password_hash('fixture-pass',PASSWORD_DEFAULT);
foreach([1=>['acladmin','admin'],2=>['acloperator','user'],3=>['acllegacy','user']] as $uid=>$account){
$stmt=$db->prepare("INSERT INTO tbl_user (user_id,user_username,user_password,user_fullname,user_level,user_status) VALUES (?,?,?,?,?,'active')");
$stmt->bind_param('issss',$uid,$account[0],$hash,$account[0],$account[1]);$stmt->execute();$stmt->close();
}
rts_save_user_permissions($db,2,['weighing'],1);
''')
        cleanup=temp/'cleanup.php'
        cleanup.write_text("<?php\nrequire "+php_literal(app/'db_config.php')+";\n"+r'''
if(!preg_match('/^rts_acl_http_[a-f0-9]+$/',$db_name))exit(1);
$db=new mysqli($db_host,$db_user,$db_pass);$db->query("DROP DATABASE `$db_name`");
''')
        server=None
        try:
            subprocess.run(['php',str(setup)],check=True,stdout=subprocess.DEVNULL)
            with socket.socket() as sock:
                sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
            BASE=f'http://127.0.0.1:{port}/test/'
            server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(app)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
            for _ in range(50):
                try:urllib.request.urlopen(BASE+'login.php',timeout=3).close();break
                except OSError:time.sleep(.05)
            else:raise RuntimeError('Test PHP server did not start')
            run_checks()
        finally:
            if server is not None:server.terminate();server.wait(timeout=5)
            if (app/'db_config.php').exists():subprocess.run(['php',str(cleanup)],check=True,stdout=subprocess.DEVNULL)

def browser():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(client,path,data=None,accept=None):
 headers={'Accept':accept} if accept else {}
 req=urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None,headers=headers)
 try:r=client.open(req)
 except urllib.error.HTTPError as e:r=e
 return r.code,r.geturl(),r.read().decode()
def token(html):
 found=re.search(r'name="csrf_token"\s+value="([^"]+)"',html)
 assert found,'No CSRF token in form'
 return found[1]
def login(username,redirect='index.php'):
 client=browser();_,_,html=request(client,'login.php?'+urllib.parse.urlencode({'redirect':redirect}))
 code,url,html=request(client,'login.php?'+urllib.parse.urlencode({'redirect':redirect}),{'username':username,'password':'fixture-pass','csrf_token':token(html)})
 assert code==200,(code,url,html[:160])
 return client,url,html
def edit(client,uid,username,permissions=None,**updates):
 code,_,html=request(client,'user_form.php?action=edit&id='+str(uid));assert code==200
 data={'csrf_token':token(html),'action':'edit','id':uid,'username':username,'fullname':username,'level':'user','status':'active','permissions_present':'1'}
 if permissions is not None:data['permissions[]']=permissions
 data.update(updates)
 return request(client,'user_save.php',data)

def run_checks():
    admin,_,_=login('acladmin','user_form.php?action=create')
    operator,url,html=login('acloperator','users.php')
    assert 'weigh_wang.php' in url
    assert 'href="weigh_wang.php"' in html and 'href="approve_weighing.php"' not in html
    for path in ['create_rubber.php','users.php','prices.php','members.php','admin_weight_edit.php?ru_id=1']:
     assert request(operator,path)[0]==403,path
    assert request(operator,'save_wang.php',{'wang_id':'1'},'application/json')[0]==403
    assert request(operator,'user_save.php',{'action':'edit','id':'2','level':'admin'})[0]==403
    assert request(operator,'public_collection_status.php')[0]==200
    legacy,_,_=login('acllegacy')
    assert request(legacy,'weigh_wang.php')[0]==200 and request(legacy,'members.php')[0]==403
    assert request(legacy,'price_form.php')[0]==403
    assert request(operator,'wang_main.php?ajax=summary',accept='application/json')[0]==403
    assert request(browser(),'wang_main.php?ajax=summary',accept='application/json')[0]==401
    edit(admin,2,'acloperator',['placement'])
    assert request(operator,'weigh_wang.php')[0]==403
    assert request(operator,'wang_main.php')[0]==200
    assert request(operator,'members_search.php?q=fixture')[0]==200
    code,_,body=request(admin,'wang_main.php?ajax=summary',accept='application/json')
    assert code==200
    before=json.loads(body)
    assert before['summary']['entries']==0
    saved=json.loads(request(operator,'save_wang.php',{'farmer_name':'Live fixture','group_name':'1','lane':'2','bags':'2.50','date':before['summary']['current_date'],'member_class':'general'})[2])
    assert saved['isOk'],saved
    after=json.loads(request(admin,'wang_main.php?ajax=summary',accept='application/json')[2])
    assert after['summary']['entries']==1 and after['summary']['sacks']==2.5 and after['lanes']['2']['count']==1
    saved=json.loads(request(operator,'save_wang.php',{'backend_id':'db-'+str(saved['id']),'farmer_name':'Live fixture','group_name':'1','lane':'2','bags':'4.25','date':before['summary']['current_date'],'member_class':'general'})[2])
    assert saved['isOk'],saved
    after=json.loads(request(admin,'wang_main.php?ajax=summary',accept='application/json')[2])
    assert after['summary']['entries']==1 and after['summary']['sacks']==4.25 and after['lanes']['2']['sacks']==4.25

    assert request(operator,'member_save.php',{'action':'create'})[0]==403
    edit(admin,2,'acloperator',['@admin'])
    assert request(operator,'wang_main.php')[0]==200 and request(operator,'users.php')[0]==403
    edit(admin,2,'acloperator',[])
    assert request(operator,'wang_main.php')[0]==403
    no_rights,url,_=login('acloperator')
    assert 'index.php' in url and request(no_rights,'weigh_wang.php')[0]==403
    # Public registration cannot get work permissions, even when role fields are forged.
    guest=browser();_,_,html=request(guest,'register.php')
    code,_,_=request(guest,'register.php',{'csrf_token':token(html),'username':'aclregistered','fullname':'Registered','password':'fixture-pass','level':'admin','permissions[]':['dashboard']})
    assert code==200
    registered,url,_=login('aclregistered')
    assert 'index.php' in url and request(registered,'dashboard.php')[0]==403 and request(registered,'users.php')[0]==403
    # A missing CSRF token must leave the target account untouched.
    edit(admin,2,'acloperator',['weighing'],csrf_token='invalid')
    assert request(operator,'weigh_wang.php')[0]==403
    edit(admin,2,'acloperator',['weighing'])
    assert request(operator,'weigh_wang.php')[0]==200
    edit(admin,2,'acloperator',['weighing'],status='inactive')
    code,url,_=request(operator,'weigh_wang.php')
    assert 'login.php' in url
    # Create a second administrator, then revoke that role while its session remains open.
    _,_,html=request(admin,'user_form.php?action=create')
    request(admin,'user_save.php',{'csrf_token':token(html),'action':'create','username':'aclsecondadmin','fullname':'Second admin','password':'fixture-pass','level':'admin','status':'active','permissions_present':'1'})
    second,_,_=login('aclsecondadmin','users.php')
    assert request(second,'users.php')[0]==200
    _,_,html=request(admin,'users.php?q=aclsecondadmin')
    uid=int(re.search(r'<tr>\s*<td class="fw-semibold">(\d+)</td>\s*<td>aclsecondadmin</td>',html)[1])
    _,_,html=request(admin,'user_form.php?action=edit&id='+str(uid))
    request(admin,'user_delete.php',{'id':uid,'csrf_token':token(html)})
    assert request(second,'users.php')[0]==200
    edit(admin,uid,'aclsecondadmin',[])
    assert request(second,'users.php')[0]==403
    _,_,html=request(admin,'user_form.php?action=edit&id='+str(uid));delete_token=token(html)
    request(admin,'user_delete.php',{'id':uid,'csrf_token':'invalid'})
    assert 'user_form.php' in request(admin,'user_form.php?action=edit&id='+str(uid))[1]
    request(admin,'user_delete.php',{'id':uid,'csrf_token':delete_token})
    assert 'users.php' in request(admin,'user_form.php?action=edit&id='+str(uid))[1]
    # Staff sessions must remain isolated between root and /test installations.
    root_code,root_url,_=request(admin,'../users.php')
    assert 'login.php' in root_url
    print('PASS: real login routing, filtered navigation, direct GET/POST denial, live grants/revocation, legacy defaults, registration isolation, CSRF, inactive accounts, stale admin demotion and root/test isolation')

if __name__=='__main__':main()
