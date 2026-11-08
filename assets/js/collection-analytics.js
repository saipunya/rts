/* All series share the server-side date/lane filter. No currency/weight dual axes. */
(() => {
  'use strict';
  const payload = document.getElementById('collectionAnalyticsData');
  const status = document.getElementById('analyticsChartStatus');
  if (!payload || !document.getElementById('quantityChart')) return;
  const fail = () => { if (status) status.hidden = false; };
  if (!window.Chart) { fail(); return; }
  try {
    const data = JSON.parse(payload.textContent);
    const amount = new Intl.NumberFormat('th-TH', { maximumFractionDigits: 2 });
    const whole = new Intl.NumberFormat('th-TH', { maximumFractionDigits: 0 });
    const dates = data.dates.map((value) => new Intl.DateTimeFormat('th-TH', { day: 'numeric', month: 'short', year: '2-digit', timeZone: 'Asia/Bangkok' }).format(new Date(`${value}T00:00:00+07:00`)));
    Chart.defaults.font.family = 'Sarabun, Tahoma, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#52665a';
    const line = (label, values, color, extra = {}) => ({ label, data: values, borderColor: color, backgroundColor: color, borderWidth: 2, pointRadius: values.length > 45 ? 0 : 2, pointHoverRadius: 5, tension: 0, spanGaps: false, fill: false, ...extra });
    const options = (unit, integer = false) => ({
      responsive: true, maintainAspectRatio: false,
      animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 200 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
        tooltip: { backgroundColor: '#203b2b', padding: 12, callbacks: { label: (context) => `${context.dataset.label}: ${(integer ? whole : amount).format(context.parsed.y)} ${unit}` } }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 7 } },
        y: { beginAtZero: true, border: { display: false }, title: { display: true, text: unit }, grid: { color: '#e7ece9' }, ticks: { precision: integer ? 0 : undefined, callback: (value) => (integer ? whole : amount).format(value) } }
      }
    });
    new Chart(document.getElementById('quantityChart'), {
      type: 'bar', data: { labels: dates, datasets: [
        { label: 'ปริมาณจริง', data: data.quantity, backgroundColor: '#356b4d', borderRadius: 2, maxBarThickness: 36 },
        line('เฉลี่ย 3 วันรับซื้อ', data.movingAverage, '#ad772d', { type: 'line', borderDash: [5, 4], order: -1 })
      ] }, options: options('กก.')
    });
    new Chart(document.getElementById('weightedPriceChart'), { type: 'line', data: { labels: dates, datasets: [line('ราคาเฉลี่ยถ่วงน้ำหนัก', data.price, '#8a672c')] }, options: options('บาท/กก.') });
    new Chart(document.getElementById('valueChart'), { type: 'line', data: { labels: dates, datasets: [line('มูลค่ารับซื้อ', data.value, '#426886'), line('ยอดสุทธิ', data.net, '#356b4d', { borderDash: [5, 3] })] }, options: options('บาท') });
    const laneOptions = options('กก.');
    laneOptions.indexAxis = 'y';
    laneOptions.plugins.legend.display = false;
    laneOptions.plugins.tooltip.callbacks.label = (context) => `ปริมาณ: ${amount.format(context.parsed.x)} กก.`;
    laneOptions.scales.x = { beginAtZero: true, grid: { color: '#e7ece9' }, border: { display: false }, ticks: { callback: (value) => amount.format(value) }, title: { display: true, text: 'กก.' } };
    laneOptions.scales.y = { grid: { display: false }, border: { display: false } };
    new Chart(document.getElementById('laneQuantityChart'), { type: 'bar', data: { labels: data.lanes, datasets: [{ label: 'ปริมาณรับซื้อ', data: data.laneQuantity, backgroundColor: '#426886', borderRadius: 2, maxBarThickness: 32 }] }, options: laneOptions });
    const peopleOptions = options('คน', true);
    peopleOptions.scales.x.stacked = true; peopleOptions.scales.y.stacked = true;
    new Chart(document.getElementById('peopleChart'), { type: 'bar', data: { labels: dates, datasets: [
      { label: 'สมาชิก', data: data.members, backgroundColor: '#356b4d', maxBarThickness: 40 },
      { label: 'เกษตรกรทั่วไป', data: data.general, backgroundColor: '#8099ae', maxBarThickness: 40 }
    ] }, options: peopleOptions });
  } catch (error) { fail(); }
})();
