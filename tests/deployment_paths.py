"""Run: python3 tests/deployment_paths.py (PHP CLI required; no database used)."""
import http.cookiejar
import json
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.request

bootstrap = Path(__file__).resolve().parents[1] / "app_bootstrap.php"
with tempfile.TemporaryDirectory(prefix="rts-path-test-") as directory:
    root = Path(directory)
    (root / "test").mkdir()
    fixture = "<?php require " + json.dumps(str(bootstrap)) + ";" + """
if (isset($_GET['login'])) $_SESSION['probe_user'] = 'tester';
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); }
header('Content-Type: application/json');
echo json_encode(['base'=>app_base_path(), 'url'=>app_url('weigh_wang.php?lan=1'),
    'session'=>session_name(), 'cookie'=>session_get_cookie_params(),
    'user'=>$_SESSION['probe_user'] ?? null,
    'redirect'=>app_login_destination((string)($_GET['redirect'] ?? 'rubbers.php?lan=all'))]);
"""
    for target in [root / "probe.php", root / "test" / "probe.php"]:
        target.write_text(fixture)
    with socket.socket() as available:
        available.bind(('127.0.0.1', 0))
        port = available.getsockname()[1]
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root)],
                              stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        def get(path):
            with browser.open(f'http://127.0.0.1:{port}' + path, timeout=3) as response:
                return json.load(response)
        for attempt in range(50):
            try:
                main = get('/probe.php?login=1')
                break
            except OSError:
                if server.poll() is not None:
                    raise RuntimeError('PHP server failed to start')
                time.sleep(.05)
        else:
            raise RuntimeError('PHP server did not become ready')
        test = get('/test/probe.php')
        assert main['base'] == '' and test['base'] == '/test'
        assert main['url'] == '/weigh_wang.php?lan=1'
        assert test['url'] == '/test/weigh_wang.php?lan=1'
        assert main['cookie']['path'] == '/' and test['cookie']['path'] == '/test/'
        assert main['session'] != test['session']
        assert main['user'] == 'tester' and test['user'] is None
        assert test['redirect'] == '/test/rubbers.php?lan=all'
        assert get('/test/probe.php?login=1')['user'] == 'tester'
        get('/test/probe.php?logout=1')
        assert get('/probe.php')['user'] == 'tester'
        assert get('/test/probe.php')['user'] is None
        for destination in ['https://example.com', '//example.com', '../index.php', '/index.php']:
            result = get('/test/probe.php?redirect=' + urllib.parse.quote(destination))
            assert result['redirect'] == '/test/dashboard.php'
        print('PASS: root/subdirectory URLs, isolated login/logout cookies and local redirects')
    finally:
        server.terminate()
        server.wait(timeout=5)
