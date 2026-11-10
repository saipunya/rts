(() => {
  'use strict';
  const root = document.getElementById('wangMainLive');
  if (!root) return;
  const status = document.getElementById('wangLiveStatus');
  const number = new Intl.NumberFormat('th-TH');
  const sacks = new Intl.NumberFormat('th-TH', {minimumFractionDigits:2, maximumFractionDigits:2});
  const clock = new Intl.DateTimeFormat('th-TH', {hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'Asia/Bangkok'});
  let timer, controller, lastUpdate = '', stopped = false;
  function schedule() {
    clearTimeout(timer);
    if (!document.hidden && !stopped) timer = setTimeout(refresh, 5000);
  }
  async function refresh() {
    clearTimeout(timer);
    if (document.hidden || controller || stopped) return;
    controller = new AbortController();
    const timeout = setTimeout(() => controller?.abort(), 10000);
    try {
      const url = new URL('wang_main.php', window.location.href);
      url.searchParams.set('ajax', 'summary');
      const response = await fetch(url, {headers:{Accept:'application/json'}, cache:'no-store', credentials:'same-origin', signal:controller.signal});
      if (response.status === 401 || response.status === 403) {
        stopped = true;
        status.textContent = 'สิทธิ์หรือการเข้าสู่ระบบเปลี่ยนแล้ว กรุณาโหลดหน้าใหม่';
        return;
      }
      if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Invalid response');
      const data = await response.json();
      if (!data.summary || !data.lanes || !data.workflow) throw new Error('Invalid summary');
      root.querySelectorAll('[data-summary]').forEach(el => {
        const key = el.dataset.summary;
        el.textContent = (key === 'sacks' ? sacks : number).format(data.summary[key]);
      });
      root.querySelectorAll('[data-lane]').forEach(el => {
        const lane = data.lanes[el.dataset.lane];
        el.textContent = lane.count > 0 ? `${sacks.format(lane.sacks)} กระสอบ · ${number.format(lane.count)} รายการ` : 'วันนี้ยังไม่มีข้อมูล';
      });
      root.querySelectorAll('[data-workflow]').forEach(el => {
        el.querySelector('strong').textContent = number.format(data.workflow[el.dataset.workflow]);
        const target = new URL(el.href, window.location.href);
        target.searchParams.set('round', data.round_id);
        el.href = target.pathname + target.search;
      });
      document.getElementById('wangCurrentDate').textContent = 'วันที่ปัจจุบัน: ' + data.date_label;
      root.querySelector('.collection-round-bar strong').textContent = data.round_label;
      root.querySelector('.collection-round-description').textContent = data.round_description;
      lastUpdate = clock.format(new Date());
      status.textContent = 'อัปเดตล่าสุด ' + lastUpdate + ' · อัปเดตอัตโนมัติทุก 5 วินาที';
    } catch (error) {
      status.textContent = (lastUpdate ? 'ข้อมูลล่าสุด ' + lastUpdate + ' · ' : '') + 'เชื่อมต่อไม่ได้ กำลังลองใหม่';
    } finally {
      clearTimeout(timeout);
      controller = null;
      schedule();
    }
  }
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearTimeout(timer); controller?.abort(); }
    else refresh();
  });
  window.addEventListener('focus', refresh);
  window.addEventListener('pageshow', refresh);
  window.addEventListener('online', refresh);
  refresh();
})();
