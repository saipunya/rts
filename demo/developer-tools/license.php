<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/license_functions.php';
try {
    $command=$argv[1] ?? ''; $options=[];
    for ($i=2;$i<$argc;$i++) {
        if ($argv[$i]==='--permanent') { $options['permanent']=true; continue; }
        if (!preg_match('/\A--(private-key|public-key|request|days|output)\z/D',$argv[$i],$match) || !isset($argv[$i+1])) throw new RuntimeException('Invalid option. See README.md.');
        $options[$match[1]]=$argv[++$i];
    }
    $private=$options['private-key'] ?? __DIR__.'/private/signing-private.pem';
    $public=$options['public-key'] ?? dirname(__DIR__).'/license_public.pem';
    if ($command==='init') {
        if (file_exists($private) || file_exists($public)) throw new RuntimeException('Keys already exist. Never replace an existing signing key. Restore its private key from backup if needed.');
        if (!is_dir(dirname($private))) mkdir(dirname($private),0700,true);
        $key=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        if (!$key || !openssl_pkey_export($key,$pem)) throw new RuntimeException('Could not generate signing key.');
        $details=openssl_pkey_get_details($key);
        rts_license_write($private,$pem); rts_license_write($public,$details['key']);
        echo "Signing key created. Keep the private directory on the developer computer only and back it up securely.\nPublic key SHA256: ".hash('sha256',$details['key'])."\n";
    } elseif ($command==='issue') {
        $request=(string)($options['request'] ?? '');
        if (!str_starts_with($request,'RTSREQ1.') || strlen($request)>2048) throw new RuntimeException('Provide the request code copied from the client activation page.');
        $data=json_decode(rts_license_unb64(substr($request,8)),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? null)!==1 || ($data['product'] ?? '')!=='RTS-DEMO'
            || !preg_match('/\A[a-f0-9]{32}\z/D',(string)($data['installation_id'] ?? ''))
            || !preg_match('/\A[a-f0-9]{64}\z/D',(string)($data['machine_id'] ?? ''))) throw new RuntimeException('Invalid RTS installation request.');
        if (isset($options['permanent']) === isset($options['days'])) throw new RuntimeException('Choose exactly one: --days NUMBER or --permanent.');
        $days=$options['days'] ?? '';
        if (!isset($options['permanent']) && (!ctype_digit($days) || (int)$days<1 || (int)$days>36500)) throw new RuntimeException('Days must be 1 to 36500.');
        $pem=@file_get_contents($private);
        $key=$pem===false ? false : openssl_pkey_get_private($pem);
        if (!$key) throw new RuntimeException('Private signing key not found. Restore it from the developer backup.');
        $details=openssl_pkey_get_details($key);
        if ($details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<3072 || !is_file($public) || !hash_equals($details['key'],(string)file_get_contents($public))) throw new RuntimeException('Private and public keys do not match the application.');
        $now=time();
        $payload=rts_license_json(['version'=>1,'product'=>'RTS-DEMO','installation_id'=>$data['installation_id'],'machine_id'=>$data['machine_id'],'license_id'=>bin2hex(random_bytes(16)),'issued_at'=>$now,'expires_at'=>isset($options['permanent']) ? null : $now+(int)$days*86400]);
        if (!openssl_sign($payload,$signature,$key,OPENSSL_ALGO_SHA256)) throw new RuntimeException('Could not sign activation code.');
        $token='RTS1.'.rts_license_b64($payload).'.'.rts_license_b64($signature);
        if (isset($options['output'])) rts_license_write($options['output'],$token."\n");
        echo $token."\n";
        fwrite(STDERR,isset($options['permanent']) ? "Permanent activation code issued.\n" : 'Valid until '.rts_license_date($now+(int)$days*86400)." (Asia/Bangkok).\n");
    } else { throw new RuntimeException('Usage: php developer-tools/license.php init | issue --request RTSREQ1... (--days 365 | --permanent) [--output activation.txt]'); }
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
