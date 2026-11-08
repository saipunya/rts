<?php
declare(strict_types=1);
// All keys and licensing records in this suite are temporary, never the developer key.
$source=dirname(__DIR__);
$root=sys_get_temp_dir().'/rts-license-test-'.bin2hex(random_bytes(5));
mkdir($root.'/app/setup',0700,true);
foreach (['license_functions.php','license_settings.php'] as $file) copy($source.'/'.$file,$root.'/app/'.$file);
copy($source.'/setup/license_state.php',$root.'/app/setup/license_state.php');
require $root.'/app/license_functions.php';
putenv('RTS_DEMO_LICENSE_DIR='.$root.'/state'); putenv('RTS_DEMO_MACHINE='.hash('sha256','test-machine-one'));
$count=0;
function check(bool $value,string $label): void { global $count; if (!$value) throw new RuntimeException($label); $count++; echo "PASS: $label\n"; }
function rejects(callable $operation,string $label): void { try { $operation(); } catch (Throwable $error) { check(true,$label); return; } throw new RuntimeException('Expected rejection: '.$label); }
function tool(array $args,string $input=''): array {
    $pipes=[]; $process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fwrite($pipes[0],$input); fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($process),$out,$err];
}
function remove_tree(string $path): void {
    if (is_dir($path)) { foreach (array_diff(scandir($path),['.','..']) as $item) remove_tree($path.'/'.$item); rmdir($path); }
    elseif (file_exists($path)) unlink($path);
}
try {
    $result=tool([PHP_BINARY,$source.'/developer-tools/license.php','init','--private-key',$root.'/private.pem','--public-key',$root.'/app/license_public.pem']);
    check($result[0]===0,'Temporary RSA keys generated without using developer keys');
    check(!rts_license_status()['allowed'],'Missing trial state does not auto-start a new trial');
    $now=time(); $state=rts_license_initialize($now);
    check(rts_license_status($now)['remaining_days']===60,'Trial begins with 60 full days');
    check(rts_license_status($now)['expires_at']===$now+60*86400,'Trial deadline is exactly 60 days after successful setup');
    check(rts_license_initialize($now+86400)['started_at']===$now,'Repeated initialization preserves the original start time');
    check(rts_license_status($now+86400)['allowed'],'Trial remains active on day two');
    check(rts_license_status($now)['reason']==='clock','Rolling the clock back is blocked');
    check(rts_license_status($now+2*86400)['allowed'],'Correcting the clock restores trial access');
    check(rts_license_status($now+60*86400-1)['allowed'],'Trial works one second before expiry');
    check(!rts_license_status($now+60*86400)['allowed'],'Trial is blocked at the exact expiry boundary');
    $request=rts_license_request($state);
    $base=[PHP_BINARY,$source.'/developer-tools/license.php','issue','--private-key',$root.'/private.pem','--public-key',$root.'/app/license_public.pem','--request',$request];
    $result=tool(array_merge($base,['--days','365'])); check($result[0]===0,'Developer tool issues a 365-day signed code'); $token=trim($result[1]);
    $license=rts_license_decode($token,$state);
    check($license['expires_at']>$now+300*86400,'Code contains a bounded signed expiry');
    $other=$state; $other['installation_id']=bin2hex(random_bytes(16));
    rejects(fn()=>rts_license_decode($token,$other),'A code for another installation is rejected');
    $other=$state; $other['machine_id']=hash('sha256','another-machine');
    rejects(fn()=>rts_license_decode($token,$other),'A code for another computer is rejected');
    $parts=explode('.',$token); $payload=json_decode(rts_license_unb64($parts[1]),true); $payload['expires_at']=null;
    $parts[1]=rts_license_b64(rts_license_json($payload));
    rejects(fn()=>rts_license_decode(implode('.',$parts),$state),'Editing a code to make it permanent invalidates its signature');
    $parts=explode('.',$token); $parts[2]='AAAA';
    rejects(fn()=>rts_license_decode(implode('.',$parts),$state),'Invalid signatures are rejected');
    rejects(fn()=>rts_license_activate(str_repeat('A',16000),$now+60*86400),'Oversized activation codes are rejected');
    rts_license_activate($token,$now+60*86400);
    check(rts_license_status($now+60*86400)['mode']==='licensed','Expired trial resumes with a valid code');
    check(rts_license_status($license['expires_at']-1)['allowed'],'Paid code works one second before its expiry');
    check(!rts_license_status($license['expires_at'])['allowed'],'Paid code expires at its exact deadline');
    rejects(fn()=>rts_license_activate($token,$license['expires_at']),'Expired codes cannot be replayed to restart their term');
    $result=tool(array_merge($base,['--permanent'])); check($result[0]===0,'Developer tool can issue permanent rights'); $permanent=trim($result[1]);
    rts_license_activate($permanent,$license['expires_at']);
    check(rts_license_status($now+50*365*86400)['allowed'],'Permanent rights remain active after 50 years');
    rejects(fn()=>rts_license_activate($token,$now+60*86400),'Timed code cannot replace permanent rights');
    $before=file_get_contents($root.'/state/state.json'); $altered=json_decode($before,true); $altered['started_at']=$now+50*86400;
    file_put_contents($root.'/state/state.json',json_encode($altered));
    check(rts_license_status($now+60*86400)['reason']==='invalid','Editing the local trial start is detected');
    file_put_contents($root.'/state/state.json',$before);
    putenv('RTS_DEMO_MACHINE='.hash('sha256','copied-computer'));
    check(rts_license_status($now+60*86400)['reason']==='invalid','Copied trial records fail on another machine');
    putenv('RTS_DEMO_MACHINE='.hash('sha256','test-machine-one'));
    $control=[PHP_BINARY,$root.'/app/setup/license_state.php'];
    $result=tool(array_merge($control,['export'])); check($result[0]===0,'License checkpoint can be exported'); $checkpoint=$result[1];
    remove_tree($root.'/state');
    $result=tool(array_merge($control,['recover']),$checkpoint); check($result[0]===0,'Missing licensing files recover from a checkpoint');
    $restored=rts_license_initialize();
    check($restored['installation_id']===$state['installation_id'] && $restored['started_at']===$now,'Recovery preserves installation identity and first trial date');
    check(rts_license_status($now+60*86400)['mode']==='permanent','Recovery retains the existing activation code');
    file_put_contents($root.'/state/state.json','broken');
    $result=tool(array_merge($control,['recover']),$checkpoint); check($result[0]===0,'Damaged records recover from the authenticated checkpoint');
    $corrupt=json_decode($checkpoint,true); $saved=json_decode(base64_decode($corrupt['state']),true); $saved['started_at']++; $corrupt['state']=base64_encode(json_encode($saved));
    $result=tool(array_merge($control,['recover']),json_encode($corrupt)); check($result[0]!==0,'Tampered checkpoint cannot reset the trial');
    $result=tool(array_merge($base,['--days','0'])); check($result[0]!==0,'Developer tool rejects invalid duration');
    $result=tool(array_merge($base,['--days','365','--permanent'])); check($result[0]!==0,'Developer must choose one activation duration type');
    $result=tool([PHP_BINARY,$source.'/developer-tools/license.php','init','--private-key',$root.'/private.pem','--public-key',$root.'/app/license_public.pem']);
    check($result[0]!==0,'Signing keys cannot be overwritten by reinitializing');
    echo "Licensing suite: $count checks passed.\n";
} finally { remove_tree($root); }
