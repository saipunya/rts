<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/license_functions.php';
try {
    $command=$argv[1] ?? '';
    if ($command === 'initialize') { rts_license_initialize(); echo "OK\n"; }
    elseif ($command === 'export') {
        echo rts_license_lock(function (string $dir): string {
            rts_license_load_state($dir);
            return rts_license_json(['version'=>1,'state'=>base64_encode((string)file_get_contents($dir.'/state.json')),'key'=>base64_encode((string)file_get_contents($dir.'/local.key')),'activation'=>is_file($dir.'/activation.txt') ? (string)file_get_contents($dir.'/activation.txt') : '']);
        });
    } elseif ($command === 'recover') {
        $snapshot=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || ($snapshot['version'] ?? null)!==1) throw new RuntimeException('Invalid license checkpoint.');
        $raw=base64_decode((string)($snapshot['state'] ?? ''),true); $key=base64_decode((string)($snapshot['key'] ?? ''),true);
        if ($raw===false || $key===false) throw new RuntimeException('Invalid license checkpoint.');
        $saved=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        rts_license_validate_state($saved,$key);
        $dir=rts_license_directory();
        if (!is_dir($dir)) mkdir($dir,0700,true);
        rts_license_lock(function (string $dir) use ($raw,$key,$snapshot,$saved): void {
            $current=null;
            try { $current=rts_license_load_state($dir); } catch (Throwable $error) { /* Recover the authenticated checkpoint below. */ }
            if ($current !== null) {
                if ($current['installation_id'] !== $saved['installation_id'] || $current['started_at'] !== $saved['started_at']) throw new RuntimeException('Installation identity differs from its Windows checkpoint. Contact the developer.');
                $current['last_seen_at']=max($current['last_seen_at'],$saved['last_seen_at']);
                rts_license_save_state($dir,$current);
            } else {
                rts_license_write($dir.'/local.key',$key); rts_license_write($dir.'/state.json',$raw);
            }
            if (!is_file($dir.'/activation.txt') && ($snapshot['activation'] ?? '') !== '') {
                rts_license_decode($snapshot['activation'],rts_license_load_state($dir));
                rts_license_write($dir.'/activation.txt',$snapshot['activation']);
            }
        });
        echo "OK\n";
    } else { throw new RuntimeException('Use initialize, export or recover.'); }
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
