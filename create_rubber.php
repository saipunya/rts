<?php
require_once 'functions.php';
require_login();

$db = db();
$groups = [];
$stm = $db->prepare("SELECT DISTINCT mem_group FROM tbl_member WHERE mem_group IS NOT NULL AND mem_group <> '' ORDER BY mem_group ASC");
if ($stm) {
  $stm->execute();
  $res = $stm->get_result();
  while ($r = $res->fetch_assoc()) {
    $groupName = $r['mem_group'];
    $mstm = $db->prepare("SELECT mem_id, mem_fullname FROM tbl_member WHERE mem_group = ? ORDER BY mem_fullname ASC");
    if ($mstm) {
      $mstm->bind_param('s', $groupName);
      $mstm->execute();
      $mres = $mstm->get_result();
      $members = [];
      while ($m = $mres->fetch_assoc()) { $members[] = ['id' => (int)$m['mem_id'], 'name' => $m['mem_fullname']]; }
      $mstm->close();
    } else {
      $members = [];
    }
    $groups[] = ['id' => $groupName, 'name' => $groupName, 'members' => $members];
  }
  $stm->close();
}

$groups_js = ['groups' => $groups];

// load existing wang records to display in the table (optionally filter by lane)
$initial_records = [];
$selected_lane = isset($_GET['lane']) ? trim((string)$_GET['lane']) : '';
if ($selected_lane !== '') {
  $wstm = $db->prepare("SELECT wang_id, wang_date, wang_mid, wang_group, wang_name, wang_sack, wang_weight, wang_lan, wang_status FROM tbl_wangyang WHERE wang_lan = ? ORDER BY wang_savedate DESC LIMIT 500");
  if ($wstm) {
    $wstm->bind_param('s', $selected_lane);
    $wstm->execute();
    $wres = $wstm->get_result();
    while ($wr = $wres->fetch_assoc()) {
      $initial_records[] = [
        '__backendId' => 'db-' . (int)$wr['wang_id'],
        'member_id' => (int)$wr['wang_mid'],
        'farmer_name' => $wr['wang_name'],
        'group_name' => $wr['wang_group'],
        'lane' => $wr['wang_lan'],
        'bags' => (int)$wr['wang_sack'],
        'weight' => (float)$wr['wang_weight'],
        'date' => $wr['wang_date'],
        'status' => $wr['wang_status'] ?? ''
      ];
    }
    $wstm->close();
  }
} else {
  $wstm = $db->prepare("SELECT wang_id, wang_date, wang_mid, wang_group, wang_name, wang_sack, wang_weight, wang_lan, wang_status FROM tbl_wangyang ORDER BY wang_savedate DESC LIMIT 500");
  if ($wstm) {
    $wstm->execute();
    $wres = $wstm->get_result();
    while ($wr = $wres->fetch_assoc()) {
      $initial_records[] = [
        '__backendId' => 'db-' . (int)$wr['wang_id'],
        'member_id' => (int)$wr['wang_mid'],
        'farmer_name' => $wr['wang_name'],
        'group_name' => $wr['wang_group'],
        'lane' => $wr['wang_lan'],
        'bags' => (int)$wr['wang_sack'],
        'weight' => (float)$wr['wang_weight'],
        'date' => $wr['wang_date'],
        'status' => $wr['wang_status'] ?? ''
      ];
    }
    $wstm->close();
  }
}

$initial_records_js = json_encode($initial_records, JSON_UNESCAPED_UNICODE);
?>
<?php
$selected_lane = isset($_GET['lane']) ? trim((string)$_GET['lane']) : '';
$selected_lane_js = $selected_lane !== '' ? $selected_lane : '';
?>

<!doctype html>
<html lang="th" class="h-full">
 <head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ระบบรวบรวมยางพารา</title>
  <link rel="stylesheet" href="assets/css/tailwind.css">
  <script src="/_sdk/element_sdk.js"></script>
  <script src="/_sdk/data_sdk.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&amp;display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Sarabun', sans-serif; }
    .fade-in { animation: fadeIn 0.4s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
  </style>
  <style>
    /* Smaller font for group/farmer selects to fit UI */
    #f-group, #f-name { font-size: 14px; }
    #f-group option, #f-name option { font-size: 14px; }
  </style>
  <style>body { box-sizing: border-box; }</style>
  <style>
    /* ── Responsive header buttons ── */
    .header-actions { display:flex; gap:0.5rem; align-items:center; flex-shrink:0; }
    @media (max-width:540px) {
      .header-actions { gap:0.35rem; }
      .header-actions a, .header-actions button { font-size:0.75rem !important; padding:0.4rem 0.65rem !important; }
      #lane-badge { font-size:0.6875rem !important; padding:0.125rem 0.5rem !important; }
    }

    /* ── Single-lane: hide Group + Lane columns ── */
    table.single-lane th:nth-child(2), table.single-lane th:nth-child(3),
    table.single-lane td:nth-child(2), table.single-lane td:nth-child(3) { display:none; }

    /* ── Mobile card view for data table ── */
    @media (max-width:640px) {
      /* hide normal table header */
      #data-table-wrap thead { display:none; }
      /* each row becomes a card */
      #data-table tr {
        display:block;
        margin-bottom:0.75rem;
        border-radius:0.75rem;
        border:1px solid #d1fae5;
        box-shadow:0 1px 4px rgba(0,0,0,0.06);
        padding:0.75rem 1rem;
        background:#fff;
      }
      #data-table td {
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:0.3rem 0;
        border:none;
        font-size:0.85rem;
      }
      #data-table td::before {
        content: attr(data-label);
        font-weight:600;
        color:#15803d;
        flex-shrink:0;
        margin-right:0.5rem;
        font-size:0.8rem;
      }
      /* hide dividers inside card */
      #data-table.divide-y > tr { border-top:none !important; }
    }
  </style>
 </head>
 <body class="h-full">
  <div id="app" class="w-full h-full overflow-auto bg-gradient-to-br from-green-50 via-emerald-50 to-teal-50">
   <!-- Header -->
   <header class="bg-white/80 backdrop-blur-sm border-b border-green-100 sticky top-0 z-10">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-3">
     <div class="flex min-w-0 items-center gap-3">
      <div class="w-10 h-10 shrink-0 bg-green-500 rounded-xl flex items-center justify-center">
       <?php echo heroicon('archive-box', 'w-5 h-5 text-white'); ?>
      </div>
      <div class="min-w-0">
       <h1 id="app-title" class="truncate text-base font-bold text-green-900 sm:text-xl">ระบบรวบรวมยางพารา</h1>
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
         <p id="app-subtitle" class="text-xs text-green-600 sm:text-sm">บันทึกข้อมูลวันวางยางพารา</p>
         <div id="lane-badge" class="whitespace-nowrap rounded-full bg-red-500 px-2 py-0.5 text-[11px] font-semibold leading-tight text-white shadow-sm sm:px-2.5 sm:py-1 sm:text-xs" style="display:none;">ลาน -</div>
        </div>
      </div>
     </div>
     <div class="header-actions flex items-center gap-2">
      <a href="wang_main.php" class="inline-flex items-center px-3 py-2 rounded-lg border border-green-200 text-green-700 bg-white hover:bg-green-50 text-sm font-medium">← กลับ</a>
      <button id="btn-add" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors text-sm font-semibold"> <?php echo heroicon('plus', 'w-4 h-4'); ?> รายการ </button>
     </div>
    </div>
   </header><!-- Stats -->
   <section class="max-w-6xl mx-auto px-4 py-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
     <div class="bg-white rounded-xl p-4 border border-green-100 shadow-sm">
      <div class="flex items-center gap-3">
       <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center"><?php echo heroicon('archive-box', 'w-5 h-5 text-green-600'); ?>
       </div>
       <div>
        <p class="text-xs text-green-600">รายการทั้งหมด</p>
        <p id="stat-total" class="text-lg font-bold text-green-900">0</p>
       </div>
      </div>
     </div>
     <div class="bg-white rounded-xl p-4 border border-green-100 shadow-sm">
      <div class="flex items-center gap-3">
       <div class="w-9 h-9 bg-emerald-100 rounded-lg flex items-center justify-center"><?php echo heroicon('scale', 'w-5 h-5 text-emerald-600'); ?>
       </div>
       <div>
        <p class="text-xs text-emerald-600">น้ำหนักรวม (กก.)</p>
        <p id="stat-weight" class="text-lg font-bold text-green-900">0</p>
       </div>
      </div>
     </div>
     <div class="bg-white rounded-xl p-4 border border-green-100 shadow-sm">
      <div class="flex items-center gap-3">
       <div class="w-9 h-9 bg-teal-100 rounded-lg flex items-center justify-center"><?php echo heroicon('users', 'w-5 h-5 text-teal-600'); ?>
       </div>
       <div>
        <p class="text-xs text-teal-600">เกษตรกร</p>
        <p id="stat-farmers" class="text-lg font-bold text-green-900">0</p>
       </div>
      </div>
     </div>
    </div>
   </section><!-- Table -->
   <section class="max-w-6xl mx-auto px-4 pb-8">
    <div class="bg-white rounded-xl border border-green-100 shadow-sm overflow-hidden">
     <div class="overflow-x-auto" id="data-table-wrap">
      <table class="w-full text-sm <?php echo ($selected_lane !== '' ? 'single-lane' : ''); ?>">
       <thead class="bg-green-50 text-green-700">
        <tr>
         <th class="px-4 py-3 text-left font-semibold">ชื่อเกษตรกร</th>
         <th class="px-4 py-3 text-left font-semibold">กลุ่ม</th>
         <th class="px-4 py-3 text-left font-semibold">ลาน</th>
          <th class="px-4 py-3 text-left font-semibold">กระสอบ</th>
          <th class="px-4 py-3 text-left font-semibold">น้ำหนัก (กก.)</th>
          <th class="px-4 py-3 text-left font-semibold">วันที่</th>
         <th class="px-4 py-3 text-center font-semibold">จัดการ</th>
        </tr>
       </thead>
       <tbody id="data-table" class="divide-y divide-green-50">
       </tbody>
      </table>
     </div>
     <div id="empty-state" class="py-12 text-center text-green-400">
      <?php echo heroicon('inbox', 'w-12 h-12 mx-auto mb-3'); ?>
      <p>ยังไม่มีรายการ — กดปุ่ม "เพิ่มรายการ" เพื่อเริ่มต้น</p>
     </div>
    </div>
   </section><!-- Modal -->
   <div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md fade-in" style="max-height:90vh;overflow-y:auto;">
     <div class="flex items-center justify-between px-6 pt-5 pb-3 sticky top-0 bg-white z-10 border-b border-green-50">
      <h2 class="text-lg font-bold text-green-900">เพิ่มรายการรวบรวมยาง</h2><button id="btn-close-modal" class="text-green-400 hover:text-green-700"><?php echo heroicon('x-mark', 'w-5 h-5'); ?></button>
     </div>
     <form id="form-add" class="px-6 pb-6 space-y-4">
      <div>
       <label for="f-group" class="block text-sm font-semibold text-green-700 mb-1">กลุ่ม</label> <select id="f-group" required class="w-full border border-green-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400"> <option value="">เลือกกลุ่ม</option> </select>
      </div>
      <div>
       <label for="f-name" class="block text-sm font-semibold text-green-700 mb-1">ชื่อเกษตรกร</label> <select id="f-name" required disabled class="w-full border border-green-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400"> <option value="">เลือกกลุ่มก่อน</option> </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
       <div>
        <label for="f-lane" class="block text-sm font-semibold text-green-700 mb-1">ลาน</label> <select id="f-lane" required class="w-full border border-green-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400"> <option value="">เลือกลาน</option> <option value="1">ลาน 1</option> <option value="2">ลาน 2</option> <option value="3">ลาน 3</option> <option value="4">ลาน 4</option> </select>
       </div>
       <div>
        <label for="f-bags" class="block text-sm font-semibold text-green-700 mb-1">จำนวนกระสอบ</label> <input id="f-bags" type="number" step="1" min="1" required class="w-full border border-green-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400">
       </div>
      </div>
      <div>
       <label for="f-weight" class="block text-sm font-semibold text-green-700 mb-1">น้ำหนัก (กก.)</label>
       <input id="f-weight" type="number" step="0.01" min="0" required class="w-full border border-green-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400">
      </div>
      <div id="date-display" class="bg-green-50 rounded-lg px-3 py-2 border border-green-200 text-sm font-semibold text-green-700">
       วันที่: <span id="current-date-display"></span>
      </div><button type="submit" id="btn-submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2.5 rounded-lg transition-colors">บันทึก</button>
     </form>
    </div>
   </div><!-- Delete Confirm -->
   <div id="delete-confirm" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center fade-in">
     <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3"><?php echo heroicon('trash', 'w-6 h-6 text-red-500'); ?>
     </div>
     <p class="text-green-900 font-semibold mb-1">ยืนยันการลบ?</p>
     <p class="text-sm text-green-600 mb-4">รายการนี้จะถูกลบอย่างถาวร</p>
     <div class="flex gap-3">
      <button id="btn-cancel-del" class="flex-1 border border-green-200 rounded-lg py-2 text-green-700 hover:bg-green-50 transition-colors">ยกเลิก</button> <button id="btn-confirm-del" class="flex-1 bg-red-500 hover:bg-red-600 text-white rounded-lg py-2 transition-colors">ลบ</button>
     </div>
    </div>
   </div>
  </div>
  <script>
  // Groups and members loaded from database
  const referenceData = <?php echo json_encode($groups_js, JSON_UNESCAPED_UNICODE); ?>;

  // State (initialized from server data)
  let records = <?php echo $initial_records_js ?? '[]'; ?>;
  const defaultLane = '<?php echo htmlspecialchars($selected_lane_js, ENT_QUOTES); ?>';

  // show current lane badge
  (function(){
    const badge = document.getElementById('lane-badge');
    if (!badge) return;
    if (defaultLane) {
      badge.textContent = 'ลาน ' + defaultLane;
      badge.style.display = 'inline-block';
    } else {
      badge.textContent = 'ทุกลาน';
      badge.style.display = 'inline-block';
    }
  })();
  let deleteTarget = null;

  const defaultConfig = {
    app_title: 'ระบบรวบรวมยางพารา',
    subtitle_text: 'บันทึกและติดตามการรวบรวมยางพารา',
    background_color: '#f0fdf4',
    surface_color: '#ffffff',
    text_color: '#14532d',
    primary_action_color: '#22c55e',
    secondary_action_color: '#065f46'
  };

  // Element SDK
  if (window.elementSdk && typeof window.elementSdk.init === 'function') {
    window.elementSdk.init({
      defaultConfig,
      onConfigChange: async (config) => {
        document.getElementById('app-title').textContent = config.app_title || defaultConfig.app_title;
        document.getElementById('app-subtitle').textContent = config.subtitle_text || defaultConfig.subtitle_text;
        const app = document.getElementById('app');
        app.style.background = `linear-gradient(135deg, ${config.background_color || defaultConfig.background_color}, ${config.surface_color || defaultConfig.surface_color})`;
      },
      mapToCapabilities: (config) => ({
        recolorables: [
          { get: () => config.background_color || defaultConfig.background_color, set: (v) => { config.background_color = v; window.elementSdk.setConfig({ background_color: v }); } },
          { get: () => config.surface_color || defaultConfig.surface_color, set: (v) => { config.surface_color = v; window.elementSdk.setConfig({ surface_color: v }); } },
          { get: () => config.text_color || defaultConfig.text_color, set: (v) => { config.text_color = v; window.elementSdk.setConfig({ text_color: v }); } },
          { get: () => config.primary_action_color || defaultConfig.primary_action_color, set: (v) => { config.primary_action_color = v; window.elementSdk.setConfig({ primary_action_color: v }); } },
          { get: () => config.secondary_action_color || defaultConfig.secondary_action_color, set: (v) => { config.secondary_action_color = v; window.elementSdk.setConfig({ secondary_action_color: v }); } }
        ],
        borderables: [],
        fontEditable: {
          get: () => config.font_family || 'Sarabun',
          set: (v) => { config.font_family = v; window.elementSdk.setConfig({ font_family: v }); }
        },
        fontSizeable: {
          get: () => config.font_size || 14,
          set: (v) => { config.font_size = v; window.elementSdk.setConfig({ font_size: v }); }
        }
      }),
      mapToEditPanelValues: (config) => new Map([
        ['app_title', config.app_title || defaultConfig.app_title],
        ['subtitle_text', config.subtitle_text || defaultConfig.subtitle_text]
      ])
    });
  } else {
    document.getElementById('app-title').textContent = defaultConfig.app_title;
    document.getElementById('app-subtitle').textContent = defaultConfig.subtitle_text;
  }

  // Data SDK
  const dataHandler = {
    onDataChanged(data) {
      records = data;
      renderTable();
      updateStats();
    }
  };

  if (window.dataSdk && typeof window.dataSdk.init === 'function') {
    (async () => {
      const r = await window.dataSdk.init(dataHandler);
      if (!r.isOk) console.error('Data SDK init failed');
    })();
  } else {
    console.warn('dataSdk not available — using local state only');
  }

  // Fallback helpers when dataSdk is not present
  async function createRecord(payload) {
    // Try server-side save first
    try {
      const res = await fetch('save_wang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
        body: JSON.stringify(payload)
      });
      if (res.ok) {
        const j = await res.json();
        if (j && j.isOk) {
          const id = 'db-' + j.id;
          const rec = Object.assign({ __backendId: id }, payload);
          records.push(rec);
          dataHandler.onDataChanged(records);
          return { isOk: true, data: rec };
        }
      }
    } catch (e) {
      // ignore and fallback
    }

    // Fallback to dataSdk if available
    if (window.dataSdk && typeof window.dataSdk.create === 'function') {
      return await window.dataSdk.create(payload);
    }

    // Local fallback
    const id = 'local-' + Date.now() + Math.floor(Math.random() * 1000);
    const rec = Object.assign({ __backendId: id }, payload);
    records.push(rec);
    dataHandler.onDataChanged(records);
    return { isOk: true, data: rec };
  }

  async function deleteRecord(target) {
    if (window.dataSdk && typeof window.dataSdk.delete === 'function') {
      return await window.dataSdk.delete(target);
    }
    records = records.filter(r => r.__backendId !== target.__backendId);
    dataHandler.onDataChanged(records);
    return { isOk: true };
  }

  function updateStats() {
    document.getElementById('stat-total').textContent = records.length;
    const farmers = new Set(records.map(r => r.farmer_name)).size;
    document.getElementById('stat-farmers').textContent = farmers;
    const totalWeight = records.reduce((s, r) => s + (parseFloat(r.weight) || 0), 0);
    document.getElementById('stat-weight').textContent = totalWeight.toFixed(2);
  }

  function renderTable() {
    const tbody = document.getElementById('data-table');
    const empty = document.getElementById('empty-state');
    empty.style.display = records.length === 0 ? 'block' : 'none';

    const existingRows = new Map([...tbody.children].map(el => [el.dataset.id, el]));
    const ids = new Set(records.map(r => r.__backendId));

    records.forEach(rec => {
      if (existingRows.has(rec.__backendId)) {
        const row = existingRows.get(rec.__backendId);
        row.children[0].textContent = rec.farmer_name;
        row.children[1].textContent = rec.group_name;
        row.children[2].textContent = rec.lane;
        row.children[3].textContent = rec.bags;
        row.children[4].textContent = (parseFloat(rec.weight) || 0) + ' กก.';
        row.children[5].textContent = formatThaiDate(rec.date);
        existingRows.delete(rec.__backendId);
      } else {
        const row = document.createElement('tr');
        row.dataset.id = rec.__backendId;
        row.className = 'hover:bg-green-50/50 transition-colors';
        row.innerHTML = `
          <td data-label="ชื่อเกษตรกร" class="px-4 py-3 font-medium text-green-900">${esc(rec.farmer_name)}</td>
          <td data-label="กลุ่ม" class="px-4 py-3 text-sm text-green-700">${esc(rec.group_name)}</td>
          <td data-label="ลาน" class="px-4 py-3">ลาน ${esc(rec.lane)}</td>
          <td data-label="กระสอบ" class="px-4 py-3">${esc(String(rec.bags))} กระสอบ</td>
          <td data-label="น้ำหนัก" class="px-4 py-3">${esc(String(rec.weight || 0))} กก.</td>
          <td data-label="วันที่" class="px-4 py-3 text-green-600">${formatThaiDate(rec.date)}</td>
          <td data-label="" class="px-4 py-3 text-center"><button class="text-red-400 hover:text-red-600" onclick="confirmDelete('${rec.__backendId}')"><?php echo heroicon('trash', 'w-4 h-4'); ?></button></td>
        `;
        tbody.appendChild(row);
      }
    });

    existingRows.forEach(el => el.remove());
  }

  function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function formatThaiDate(dateStr) {
    const date = new Date(dateStr);
    const monthsTh = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    const day = date.getDate();
    const month = monthsTh[date.getMonth()];
    const year = date.getFullYear() + 543;
    return `${day} ${month} ${year}`;
  }

  function getTodayDateString() {
    const today = new Date();
    return today.toISOString().split('T')[0];
  }

  // Modal
  const modal = document.getElementById('modal');
  document.getElementById('btn-add').onclick = () => {
    document.getElementById('current-date-display').textContent = formatThaiDate(getTodayDateString());
    // set default lane if provided from main page
    const laneEl = document.getElementById('f-lane');
    // if defaultLane provided, lock the lane select to prevent changes
    if (defaultLane) {
      if (laneEl) {
        laneEl.value = defaultLane;
        laneEl.disabled = true;
        laneEl.classList.add('bg-gray-100');
        // ensure hidden input exists for graceful form submission if needed
        let hid = document.getElementById('f-lane-hidden');
        if (!hid) {
          hid = document.createElement('input');
          hid.type = 'hidden';
          hid.id = 'f-lane-hidden';
          hid.name = 'f-lane-hidden';
          document.getElementById('form-add').appendChild(hid);
        }
        hid.value = defaultLane;
      }
    } else {
      if (laneEl) {
        laneEl.disabled = false;
        laneEl.classList.remove('bg-gray-100');
      }
      const hid = document.getElementById('f-lane-hidden');
      if (hid) hid.remove();
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  };
  document.getElementById('btn-close-modal').onclick = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

  // Populate group dropdown
  const groupSelect = document.getElementById('f-group');
  referenceData.groups.forEach(g => {
    const opt = document.createElement('option');
    opt.value = g.id;
    opt.textContent = g.name;
    groupSelect.appendChild(opt);
  });

  // Group selection changes farmer dropdown
  const farmerSelect = document.getElementById('f-name');
  groupSelect.addEventListener('change', () => {
    farmerSelect.innerHTML = '<option value="">เลือกเกษตรกร</option>';
    if (!groupSelect.value) {
      farmerSelect.disabled = true;
      return;
    }
    const group = referenceData.groups.find(g => g.id === groupSelect.value);
      if (group) {
      group.members.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name;
        farmerSelect.appendChild(opt);
      });
      farmerSelect.disabled = false;
    }
  });

  document.getElementById('form-add').onsubmit = async (e) => {
    e.preventDefault();
    if (records.length >= 999) { document.getElementById('btn-submit').textContent = 'ถึงขีดจำกัดแล้ว (999)'; return; }
    const btn = document.getElementById('btn-submit');
    btn.disabled = true; btn.textContent = 'กำลังบันทึก...';
    const groupId = document.getElementById('f-group').value;
    const group = referenceData.groups.find(g => g.id === groupId);
    const selectedMemberId = parseInt(document.getElementById('f-name').value) || null;
    const selectedMemberName = farmerSelect.options[farmerSelect.selectedIndex] ? farmerSelect.options[farmerSelect.selectedIndex].text : '';
    const result = await createRecord({
      member_id: selectedMemberId,
      farmer_name: selectedMemberName,
      group_name: group ? group.name : '',
      lane: document.getElementById('f-lane').value,
      bags: parseInt(document.getElementById('f-bags').value),
      weight: parseFloat(document.getElementById('f-weight').value) || 0,
      date: getTodayDateString()
    });
    btn.disabled = false; btn.textContent = 'บันทึก';
    if (result.isOk) { e.target.reset(); groupSelect.value = ''; farmerSelect.value = ''; farmerSelect.disabled = true; modal.classList.add('hidden'); modal.classList.remove('flex'); }
  };

  // Delete
  const delModal = document.getElementById('delete-confirm');
  window.confirmDelete = (id) => { deleteTarget = records.find(r => r.__backendId === id); delModal.classList.remove('hidden'); delModal.classList.add('flex'); };
  document.getElementById('btn-cancel-del').onclick = () => { delModal.classList.add('hidden'); delModal.classList.remove('flex'); };
  document.getElementById('btn-confirm-del').onclick = async () => {
    if (!deleteTarget) return;
    const btn = document.getElementById('btn-confirm-del');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    await deleteRecord(deleteTarget);
    btn.disabled = false; btn.textContent = 'ลบ';
    delModal.classList.add('hidden'); delModal.classList.remove('flex');
    deleteTarget = null;
  };

  // Render initial server-provided records
  try { renderTable(); updateStats(); } catch (e) { /* ignore if functions not ready */ }
</script>
 <script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'9f4f28a87496a5d8',t:'MTc3NzY0MjQxNS4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
