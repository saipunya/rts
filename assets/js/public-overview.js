/* Public charts are loaded and rendered only when the screen has enough room. */
(() => {
  'use strict';
  const node = document.getElementById('publicOverviewData');
  if (!node || !document.getElementById('publicQuantityChart')) return;
  const status = document.getElementById('publicChartStatus');
  const wideScreen = window.matchMedia('(min-width: 992px)');
  let libraryPromise;
  let charts = [];
  let revision = 0;
  const clearCharts = () => { charts.forEach((chart) => chart.destroy()); charts = []; };
  const loadLibrary = () => {
    if (window.Chart) return Promise.resolve();
    if (!libraryPromise) {
      libraryPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'assets/vendor/chart-4.4.1.umd.min.js';
        script.async = true;
        script.onload = resolve;
        script.onerror = () => reject(new Error('Chart library unavailable'));
        document.head.appendChild(script);
      });
    }
    return libraryPromise;
  };
  async function synchronize() {
    const current = ++revision;
    if (!wideScreen.matches) {
      clearCharts();
      if (status) status.hidden = true;
      return;
    }
    if (charts.length) return;
    try {
      await loadLibrary();
      if (document.fonts) await document.fonts.ready;
      if (current !== revision || !wideScreen.matches) return;
      const Chart = window.Chart;
      const data = JSON.parse(node.textContent);
      const labels = data.dates.map((date) => new Intl.DateTimeFormat('th-TH', { day: 'numeric', month: 'short', year: '2-digit', timeZone: 'Asia/Bangkok' }).format(new Date(`${date}T00:00:00+07:00`)));
      const format = new Intl.NumberFormat('th-TH', { maximumFractionDigits: 2 });
      Chart.defaults.font.family = 'Sarabun, Tahoma, sans-serif';
      Chart.defaults.font.size = 12;
      Chart.defaults.color = '#52665a';
      const draw = (id, values, label, unit, color) => new Chart(document.getElementById(id), {
        type: 'bar', data: { labels, datasets: [{ label, data: values, backgroundColor: color, borderRadius: 2, maxBarThickness: 36 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 200 },
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `${label}: ${format.format(context.parsed.y)} ${unit}` } } },
          scales: { x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, maxTicksLimit: 5 } }, y: { beginAtZero: true, grid: { color: '#e7ece9' }, border: { display: false }, ticks: { callback: (value) => format.format(value) }, title: { display: true, text: unit } } }
        }
      });
      charts.push(draw('publicQuantityChart', data.quantity, 'ปริมาณรวบรวม', 'กก.', '#356b4d'));
      charts.push(draw('publicValueChart', data.value, 'มูลค่ารับซื้อ', 'บาท', '#426886'));
      if (status) status.hidden = true;
    } catch (error) {
      clearCharts();
      if (current === revision && wideScreen.matches && status) status.hidden = false;
    }
  }
  if (wideScreen.addEventListener) wideScreen.addEventListener('change', synchronize);
  else wideScreen.addListener(synchronize);
  synchronize();
})();

(() => {
  const section = document.getElementById('liveRoundSection');
  if (!section) return;
  const number = new Intl.NumberFormat('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  async function refresh() {
    try {
      const response = await fetch('rubber_round_live_summary.php', { cache: 'no-store' });
      if (!response.ok) return;
      const data = await response.json();
      section.classList.toggle('d-none', !data.show);
      if (!data.show) return;
      [['liveRoundQuantity', 'total_quantity', ' kg'], ['liveRoundDeduct', 'total_expend', ' ฿'], ['liveRoundNet', 'total_net', ' ฿']].forEach(([id, field, unit]) => {
        const target = document.getElementById(id); if (target) target.textContent = number.format(Number(data[field] || 0)) + unit;
      });
      const updated = document.getElementById('liveRoundUpdatedAt'); if (updated && data.updated_at_text) updated.textContent = data.updated_at_text;
    } catch (error) { /* Keep the last displayed snapshot on a temporary network failure. */ }
  }
  refresh(); window.setInterval(refresh, 300000);
})();
