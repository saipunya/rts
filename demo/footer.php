</div>
</div>
</main>
<?php
$footerOnlineStats = [
    'online_users' => 0,
    'online_guests' => 0,
    'online_total' => 0,
    'last_seen_at' => null,
];
if (function_exists('fetch_online_presence_stats')) {
    $footerOnlineStats = fetch_online_presence_stats(function_exists('db') ? db() : null);
}
?>
<footer class="site-footer">
  <div class="app-shell">
    <div class="footer-workspace">
      <div><strong>ระบบรวบรวมยางพารา</strong><small>สหกรณ์ตัวอย่าง (ระบบทดลอง)</small></div>
      <div class="footer-counters" aria-label="สถานะผู้ใช้งานออนไลน์">
        <span>เจ้าหน้าที่ <strong><?php echo number_format((int)$footerOnlineStats['online_users']); ?></strong></span>
        <span>ผู้เยี่ยมชม <strong><?php echo number_format((int)$footerOnlineStats['online_guests']); ?></strong></span>
        <span>ออนไลน์รวม <strong><?php echo number_format((int)$footerOnlineStats['online_total']); ?></strong></span>
      </div>
    </div>
    <div class="footer-support"><span>ผู้พัฒนา: สุเมธ ชูรัตน์ · โทร <a href="tel:0808281906">080-8281906</a> · LINE saipunya</span><a href="activation.php">สิทธิ์ใช้งานและติดต่อผู้พัฒนา</a></div>
  </div>
</footer>
<link rel="stylesheet" media="screen" href="assets/css/professional-theme.css?v=<?php echo filemtime(__DIR__ . '/assets/css/professional-theme.css'); ?>">
<!-- Bootstrap JavaScript Libraries -->
<script src="assets/offline/popper-2.11.8.min.js"
  integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="assets/offline/bootstrap-5.3.2.min.js"
  integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+" crossorigin="anonymous"></script>
<!-- AOS Animation Library -->
<script src="assets/offline/aos-2.3.1.js"></script>
<script>
// Initialize AOS
AOS.init({
  duration: 800,
  once: true,
  offset: 100
});
</script>
<script>
(function() {
  const endpoint = 'presence.php';
  const intervalMs = 60000;
  let timer = null;
  let inFlight = false;

  function pingPresence() {
    if (inFlight || !window.fetch) {
      return;
    }

    inFlight = true;
    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      keepalive: true,
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ping: true
      })
    }).catch(function() {
      // Ignore network hiccups; presence will refresh on the next request.
    }).then(function() {
      inFlight = false;
    });
  }

  function startPresenceLoop() {
    pingPresence();
    if (timer) {
      return;
    }
    timer = window.setInterval(pingPresence, intervalMs);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startPresenceLoop, {
      once: true
    });
  } else {
    startPresenceLoop();
  }

  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      pingPresence();
    }
  });

  window.addEventListener('focus', pingPresence);
  window.addEventListener('pageshow', pingPresence);
})();
</script>
<script src="assets/js/thai-date-preview.js?v=<?php echo filemtime(__DIR__ . '/assets/js/thai-date-preview.js'); ?>"></script>
</body>

</html>
<?php
// close stmt and mysqli safely if they exist
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    try {
        $stmt->close();
    } catch (Throwable $e) {
        // ignore: statement may already be closed
    }
}
if (isset($mysqli) && $mysqli instanceof mysqli) {
    try {
        $mysqli->close();
    } catch (Throwable $e) {
        // ignore
    }
}
?>