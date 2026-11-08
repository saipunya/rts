<?php
declare(strict_types=1);

function rts_license_settings(): array {
    return require __DIR__ . '/license_settings.php';
}
function rts_license_directory(): string {
    $path = getenv('RTS_DEMO_LICENSE_DIR');
    if (!$path) {
        $data = getenv('RTS_DEMO_DATA');
        $path = $data ? $data . '/licensing' : __DIR__ . '/.license-data';
    }
    return rtrim($path, '/\\');
}
function rts_license_machine(): string {
    $machine = getenv('RTS_DEMO_MACHINE');
    if ($machine && preg_match('/\A[a-f0-9]{64}\z/D', $machine)) return $machine;
    return hash('sha256', 'RTS-DEMO|' . php_uname('n') . '|' . php_uname('m'));
}
function rts_license_b64(string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}
function rts_license_unb64(string $text): string {
    if ($text === '' || !preg_match('/\A[A-Za-z0-9_-]+\z/D', $text)) throw new DomainException('รูปแบบรหัสไม่ถูกต้อง');
    $bytes = base64_decode(strtr($text, '-_', '+/'), true);
    if ($bytes === false || rts_license_b64($bytes) !== $text) throw new DomainException('รูปแบบรหัสไม่ถูกต้อง');
    return $bytes;
}
function rts_license_json(array $data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function rts_license_write(string $path, string $content): void {
    $temporary = $path . '.' . bin2hex(random_bytes(5)) . '.tmp';
    if (file_put_contents($temporary, $content) === false) throw new RuntimeException('บันทึกข้อมูลสิทธิ์ใช้งานไม่สำเร็จ');
    @chmod($temporary, 0600);
    if (!rename($temporary, $path)) { @unlink($temporary); throw new RuntimeException('บันทึกข้อมูลสิทธิ์ใช้งานไม่สำเร็จ'); }
}
function rts_license_lock(callable $callback) {
    $directory = rts_license_directory();
    if (!is_dir($directory)) throw new DomainException('ยังไม่ได้เตรียมข้อมูลสิทธิ์ใช้งาน กรุณาเปิดผ่านไอคอน RTS Demo');
    $lock = fopen($directory . '/license.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('ไม่สามารถอ่านสิทธิ์ใช้งานได้ กรุณาลองใหม่');
    try { return $callback($directory); }
    finally { flock($lock, LOCK_UN); fclose($lock); }
}
function rts_license_validate_state(array $state, string $key): array {
    $mac = $state['mac'] ?? ''; unset($state['mac']);
    if (strlen($key) !== 32 || !is_string($mac) || !hash_equals(hash_hmac('sha256', rts_license_json($state), $key), $mac)) {
        throw new DomainException('ข้อมูลสิทธิ์ใช้งานไม่สมบูรณ์ กรุณาปิดและเปิด RTS Demo อีกครั้ง หรือติดต่อผู้พัฒนา');
    }
    if (($state['version'] ?? null) !== 1 || ($state['product'] ?? '') !== 'RTS-DEMO'
        || !preg_match('/\A[a-f0-9]{32}\z/D', (string)($state['installation_id'] ?? ''))
        || !preg_match('/\A[a-f0-9]{64}\z/D', (string)($state['machine_id'] ?? ''))
        || !is_int($state['started_at'] ?? null) || !is_int($state['last_seen_at'] ?? null)
        || $state['started_at'] < 1 || $state['last_seen_at'] < $state['started_at']) {
        throw new DomainException('ข้อมูลสิทธิ์ใช้งานไม่ถูกต้อง กรุณาติดต่อผู้พัฒนา');
    }
    if (!hash_equals(rts_license_machine(), $state['machine_id'])) throw new DomainException('ข้อมูลสิทธิ์ใช้งานเป็นของเครื่องอื่น กรุณาติดต่อผู้พัฒนา');
    return $state;
}
function rts_license_load_state(string $directory): array {
    if (!is_file($directory . '/state.json') || !is_file($directory . '/local.key')) throw new DomainException('ไม่พบข้อมูลสิทธิ์ใช้งาน กรุณาปิดและเปิด RTS Demo อีกครั้ง');
    $state = json_decode((string)file_get_contents($directory . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($state)) throw new DomainException('ข้อมูลสิทธิ์ใช้งานไม่ถูกต้อง');
    return rts_license_validate_state($state, (string)file_get_contents($directory . '/local.key'));
}
function rts_license_save_state(string $directory, array $state): void {
    $key = (string)file_get_contents($directory . '/local.key');
    $state['mac'] = hash_hmac('sha256', rts_license_json($state), $key);
    rts_license_write($directory . '/state.json', rts_license_json($state));
}
function rts_license_initialize(?int $now = null): array {
    if (PHP_SAPI !== 'cli') throw new DomainException('เตรียมสิทธิ์ใช้งานได้เฉพาะตัวติดตั้ง');
    $directory = rts_license_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('สร้างโฟลเดอร์สิทธิ์ใช้งานไม่สำเร็จ');
    return rts_license_lock(function (string $dir) use ($now): array {
        if (is_file($dir . '/state.json') || is_file($dir . '/local.key')) return rts_license_load_state($dir);
        $time = $now ?? time();
        $state = ['version'=>1, 'product'=>'RTS-DEMO', 'installation_id'=>bin2hex(random_bytes(16)), 'machine_id'=>rts_license_machine(), 'started_at'=>$time, 'last_seen_at'=>$time];
        rts_license_write($dir . '/local.key', random_bytes(32));
        rts_license_save_state($dir, $state);
        return $state;
    });
}
function rts_license_decode(string $token, array $state): array {
    $token = preg_replace('/\s+/', '', $token);
    if (strlen($token) > 12000) throw new DomainException('รหัสใช้งานยาวเกินกำหนด');
    $parts = explode('.', $token);
    if (count($parts) !== 3 || $parts[0] !== 'RTS1') throw new DomainException('รูปแบบรหัสใช้งานไม่ถูกต้อง');
    $payload = rts_license_unb64($parts[1]); $signature = rts_license_unb64($parts[2]);
    $keyFile = __DIR__ . '/license_public.pem';
    $key = is_file($keyFile) ? openssl_pkey_get_public((string)file_get_contents($keyFile)) : false;
    if (!$key || openssl_verify($payload, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) throw new DomainException('รหัสใช้งานไม่ถูกต้องหรือไม่ได้ออกโดยผู้พัฒนา');
    $license = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($license) || ($license['version'] ?? null) !== 1 || ($license['product'] ?? '') !== 'RTS-DEMO'
        || ($license['installation_id'] ?? '') !== $state['installation_id'] || ($license['machine_id'] ?? '') !== $state['machine_id']
        || !preg_match('/\A[a-f0-9]{32}\z/D', (string)($license['license_id'] ?? ''))
        || !is_int($license['issued_at'] ?? null) || $license['issued_at'] < 1
        || !array_key_exists('expires_at', $license) || !($license['expires_at'] === null || (is_int($license['expires_at']) && $license['expires_at'] > $license['issued_at']))) {
        throw new DomainException('รหัสนี้ไม่ตรงกับชุดติดตั้งในเครื่องนี้ หรือข้อมูลรหัสไม่สมบูรณ์');
    }
    return $license;
}
function rts_license_request(array $state): string {
    return 'RTSREQ1.' . rts_license_b64(rts_license_json(['version'=>1,'product'=>'RTS-DEMO','installation_id'=>$state['installation_id'],'machine_id'=>$state['machine_id']]));
}
function rts_license_status(?int $now = null): array {
    try {
        return rts_license_lock(function (string $dir) use ($now): array {
            $state = rts_license_load_state($dir); $time = $now ?? time();
            $trialEnd = $state['started_at'] + 60 * 86400;
            $result = ['allowed'=>false,'reason'=>'expired','state'=>$state,'request'=>rts_license_request($state),'mode'=>'trial','expires_at'=>$trialEnd,'trial_expires_at'=>$trialEnd,'remaining_days'=>0,'message'=>'ครบกำหนดทดลองใช้งาน 60 วันแล้ว กรุณาติดต่อผู้พัฒนาเพื่อขอรหัสใช้งานต่อ'];
            if (is_file($dir . '/activation.txt')) {
                // A damaged activation fails closed; it never silently creates another trial.
                $license = rts_license_decode((string)file_get_contents($dir . '/activation.txt'), $state);
                $result['mode'] = $license['expires_at'] === null ? 'permanent' : 'licensed';
                $result['expires_at'] = $license['expires_at'] === null ? null : max($trialEnd, $license['expires_at']);
                if ($license['issued_at'] > $time + 300) {
                    $result['reason']='clock'; $result['message']='วันที่เครื่องอยู่ก่อนวันที่ออกรหัส กรุณาตรวจวันเวลา Windows'; return $result;
                }
            }
            if ($result['mode'] !== 'permanent' && $time < $state['last_seen_at'] - 300) {
                $result['reason']='clock'; $result['message']='ตรวจพบวันที่เครื่องย้อนหลัง กรุณาตั้งวันเวลา Windows ให้ถูกต้อง แล้วเปิดระบบอีกครั้ง'; return $result;
            }
            $state['last_seen_at'] = max($state['last_seen_at'], $time);
            rts_license_save_state($dir, $state); $result['state']=$state;
            $end = $result['expires_at'];
            if ($end === null || $time < $end) {
                $result['allowed']=true; $result['reason']='active';
                $result['remaining_days']=$end === null ? null : (int)ceil(($end-$time)/86400);
                $result['message']=$end === null ? 'เปิดใช้งานแบบไม่จำกัดเวลาแล้ว' : ($result['mode']==='trial' ? 'อยู่ระหว่างทดลองใช้งาน 60 วัน' : 'เปิดใช้งานด้วยรหัสผู้พัฒนาแล้ว');
            } elseif ($result['mode'] === 'licensed') { $result['message']='สิทธิ์ใช้งานหมดอายุแล้ว กรุณาติดต่อผู้พัฒนาเพื่อขอรหัสใหม่'; }
            return $result;
        });
    } catch (Throwable $error) {
        error_log('RTS licensing: ' . $error->getMessage());
        return ['allowed'=>false,'reason'=>'invalid','mode'=>'unknown','request'=>'','message'=>'อ่านข้อมูลสิทธิ์ใช้งานไม่ได้ กรุณาปิดและเปิดผ่านไอคอน RTS Demo อีกครั้ง หรือติดต่อผู้พัฒนา'];
    }
}
function rts_license_activate(string $token, ?int $now = null): void {
    rts_license_lock(function (string $dir) use ($token, $now): void {
        $state = rts_license_load_state($dir); $time = $now ?? time();
        $license = rts_license_decode($token, $state);
        if ($license['issued_at'] > $time + 300) throw new DomainException('วันที่เครื่องอยู่ก่อนวันที่ออกรหัส กรุณาตรวจวันเวลา Windows');
        if ($license['expires_at'] !== null && $license['expires_at'] <= $time) throw new DomainException('รหัสใช้งานนี้หมดอายุแล้ว กรุณาขอรหัสใหม่จากผู้พัฒนา');
        if ($license['expires_at'] !== null && $time < $state['last_seen_at'] - 300) throw new DomainException('กรุณาตั้งวันเวลา Windows ให้ถูกต้องก่อนเปิดใช้รหัส');
        if (is_file($dir . '/activation.txt')) {
            $old = rts_license_decode((string)file_get_contents($dir . '/activation.txt'), $state);
            if ($old['expires_at'] === null && $license['expires_at'] !== null) throw new DomainException('เครื่องนี้เปิดใช้งานแบบไม่จำกัดเวลาแล้ว');
            if ($old['expires_at'] !== null && $license['expires_at'] !== null && $license['expires_at'] < $old['expires_at']) throw new DomainException('รหัสใหม่มีอายุสั้นกว่าสิทธิ์ปัจจุบัน กรุณาตรวจรหัส');
        }
        rts_license_write($dir . '/activation.txt', preg_replace('/\s+/', '', $token));
        $state['last_seen_at']=max($state['last_seen_at'],$time); rts_license_save_state($dir,$state);
    });
}
function rts_license_date(?int $timestamp): string {
    return $timestamp === null ? 'ไม่จำกัดเวลา' : (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('d/m/Y H:i');
}
function rts_license_guard(): void {
    if (PHP_SAPI === 'cli') return;
    $status = rts_license_status(); $GLOBALS['rts_license_status'] = $status;
    if ($status['allowed']) return;
    // Use the actual executing file, not the URL/query string, for the exemption.
    $executing = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $activation = realpath(__DIR__ . '/activation.php');
    if ($executing && $activation && strcasecmp($executing, $activation) === 0) return;
    $destination = function_exists('app_url') ? app_url('activation.php') : '/activation.php';
    header('Cache-Control: no-store');
    header('Location: ' . $destination, true, ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 302 : 303);
    exit;
}
