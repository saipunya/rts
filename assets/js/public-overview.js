/* Public status board: read-only polling; no workflow mutations or full-page reloads. */
(() => {
  'use strict';
  const root = document.getElementById('publicDashboard');
  const initial = document.getElementById('publicDashboardData');
  if (!root || !initial) return;
  const input = document.getElementById('publicMemberNumber');
  const clear = document.getElementById('publicClearSearch');
  const refreshButton = document.getElementById('publicRefresh');
  const connection = document.getElementById('publicConnection');
  const records = document.getElementById('publicRecords');
  const resultLabel = document.getElementById('publicResultLabel');
  const countFormat = new Intl.NumberFormat('th-TH');
  const weightFormat = new Intl.NumberFormat('th-TH', {minimumFractionDigits:2, maximumFractionDigits:2});
  const dateFormat = new Intl.DateTimeFormat('th-TH', {day:'numeric', month:'short', year:'numeric', timeZone:'Asia/Bangkok'});
  const timeFormat = new Intl.DateTimeFormat('th-TH', {hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'Asia/Bangkok'});
  const activityFormat = new Intl.DateTimeFormat('th-TH', {day:'numeric', month:'short', hour:'2-digit', minute:'2-digit', hour12:false, timeZone:'Asia/Bangkok'});
  const labels = {waiting:'วางยางแล้ว', weighed:'ชั่งแล้ว', ready:'รอออกใบเสร็จ', issued:'ออกใบเสร็จแล้ว'};
  const storageKey = 'rts-public-member:' + new URL('.',window.location.href).pathname;
  let snapshot = JSON.parse(initial.textContent);
  let member = snapshot.member;
  let stage = 'all';
  let timer, searchTimer, controller, sequence = 0;
  let composing = false;
  let lastRecords = JSON.stringify(snapshot.records);
  let lastLanes = JSON.stringify(snapshot.lanes || []);
  const node = (tag, text, className) => {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    if (className) element.className = className;
    return element;
  };
  function deductionIcon(value) {
    const state = ['has','none'].includes(value) ? value : 'pending';
    const icon = node('span',undefined,`public-deduction-icon public-deduction-${state}`);
    icon.setAttribute('role','img');
    icon.setAttribute('aria-label',{has:'มียอดหัก',none:'ไม่มียอดหัก',pending:'รอยืนยันยอดหัก'}[state]);
    const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    Object.entries({viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2.5','stroke-linecap':'round','stroke-linejoin':'round','aria-hidden':'true'}).forEach(([key,val])=>svg.setAttribute(key,val));
    const circle = document.createElementNS(svg.namespaceURI,'circle');
    Object.entries({cx:'12',cy:'12',r:'9'}).forEach(([key,val])=>circle.setAttribute(key,val));
    const path = document.createElementNS(svg.namespaceURI,'path');
    path.setAttribute('d',{has:'M8 12h8',none:'m8 12 3 3 5-6',pending:'M12 7v5l3 2'}[state]);
    svg.append(circle,path); icon.append(svg); return icon;
  }
  function render(data) {
    snapshot = data;
    Object.entries(data.summary).forEach(([key,value]) => {
      const target = root.querySelector(`[data-public-metric="${key}"]`);
      if (target) target.textContent = (key === 'weight' ? weightFormat : countFormat).format(value);
    });
    const roundLabel = data.round ? 'รอบวันที่ ' + dateFormat.format(new Date(data.round.date + 'T00:00:00+07:00')) : 'ยังไม่มีรอบราคายาง';
    document.getElementById('publicRoundLabel').textContent = roundLabel;
    const laneRound = document.getElementById('publicLaneRound');
    if (laneRound) laneRound.textContent = roundLabel;
    const laneTotal = document.getElementById('publicLaneTotal');
    if (laneTotal) laneTotal.textContent = weightFormat.format(data.summary.weight);
    const laneCards = document.getElementById('publicLaneCards');
    if (laneCards && Array.isArray(data.lanes) && JSON.stringify(data.lanes) !== lastLanes) {
      const fragment = document.createDocumentFragment();
      data.lanes.forEach(lane => {
        const card = node('article',undefined,'public-lane-card'); card.dataset.lane = lane.lane;
        const label = lane.lane ? `ลาน ${lane.lane}` : 'ไม่ระบุลาน';
        const weight = node('div',undefined,'public-lane-weight');
        weight.append(node('strong',weightFormat.format(lane.weight)),node('small','กก.'));
        const progress = node('progress'); progress.max = Math.max(1,data.summary.weight); progress.value = lane.weight;
        progress.setAttribute('aria-label','สัดส่วนน้ำหนัก' + label);
        card.append(node('span',label,'public-lane-number'),weight,progress,node('span',`${countFormat.format(lane.weighed)} รายการชั่งแล้ว`,'public-lane-count'));
        fragment.append(card);
      });
      laneCards.replaceChildren(fragment); lastLanes = JSON.stringify(data.lanes);
    }
    document.getElementById('publicRoundState').textContent = !data.round ? 'รอประกาศราคา' : data.round.is_today ? 'รอบวันนี้' : data.round.is_future ? 'รอบที่ประกาศล่วงหน้า' : 'ข้อมูลรอบล่าสุด';
    document.getElementById('publicPriceDate').textContent = roundLabel;
    const price = document.getElementById('publicPrice');
    price.replaceChildren(document.createTextNode(data.round ? weightFormat.format(data.round.price) : '—'), node('small','บาท / กก.'));
    const progress = document.getElementById('publicProgress');
    progress.max = Math.max(1,data.summary.placed); progress.value = data.summary.weighed;
    document.getElementById('publicProgressText').textContent = `${countFormat.format(data.summary.weighed)} / ${countFormat.format(data.summary.placed)} รายการ`;
    resultLabel.textContent = (member ? `คำค้น “${member}” · ` : '') + `${countFormat.format(data.matched)} รายการ` + (data.matched > data.limit ? ` · แสดง ${data.limit} รายการล่าสุด` : '');
    const allRecords = document.getElementById('publicAllRecords');
    if (allRecords) {
      const address = new URL('public_collection_list.php',window.location.href);
      address.searchParams.set('stage',stage);
      if (member) address.searchParams.set('member',member);
      allRecords.href = address.pathname + address.search;
    }
    const serialized = JSON.stringify(data.records);
    if (serialized !== lastRecords || !data.records.length) {
      const fragment = document.createDocumentFragment();
      data.records.forEach(record => {
        const row = node('article',undefined,'public-status-record');
        const identity = node('div');
        const name = node('strong',undefined,'public-record-name');
        name.append(deductionIcon(record.deduction_state),node('span',record.name));
        const sacks = record.sacks == null ? 'ไม่ระบุจำนวนกระสอบ' : `${weightFormat.format(record.sacks)} กระสอบ`;
        identity.append(name,node('span',`เลขสมาชิก ${record.member_number || '—'} · ลาน ${record.lane} · ${sacks}`));
        const state = node('div');
        const status = Object.hasOwn(labels,record.status) ? record.status : 'waiting';
        const time = node('time',activityFormat.format(new Date(record.activity_at))); time.dateTime = record.activity_at;
        state.append(node('span',labels[status],`public-status public-status-${status}`),time);
        row.append(identity,state); fragment.append(row);
      });
      if (!data.records.length) {
        const empty = node('div',undefined,'public-empty');
        empty.append(node('strong',member ? 'ยังไม่พบรายการที่ตรงกับคำค้นในรอบล่าสุด' : 'ยังไม่มีรายการในขั้นตอนนี้'),node('p','ตรวจสอบชื่อ เลขสมาชิก และวันที่รอบ หรือสอบถามเจ้าหน้าที่ประจำลาน'));
        fragment.append(empty);
      }
      records.replaceChildren(fragment); lastRecords = serialized;
    }
    connection.textContent = 'อัปเดตล่าสุด ' + timeFormat.format(new Date(data.updated_at));
    connection.parentElement.classList.remove('is-stale');
  }
  async function refresh() {
    clearTimeout(timer);
    if (document.hidden) return;
    if (controller) controller.abort();
    const current = ++sequence;
    const requestController = new AbortController();
    controller = requestController;
    const timeout = setTimeout(() => requestController.abort(),14000);
    refreshButton.disabled = true;
    try {
      const url = new URL('public_collection_status.php',window.location.href);
      if (member) url.searchParams.set('member',member);
      url.searchParams.set('stage',stage);
      const response = await fetch(url,{cache:'no-store',signal:requestController.signal,headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error('Status unavailable');
      const data = await response.json();
      if (current !== sequence) return;
      if (!data.summary || !Array.isArray(data.records) || !data.updated_at) throw new Error('Invalid snapshot');
      render(data);
    } catch (error) {
      if (current !== sequence) return;
      const lastTime = timeFormat.format(new Date(snapshot.updated_at));
      connection.textContent = `ยังอัปเดตไม่ได้ · ข้อมูลล่าสุด ${lastTime} ระบบจะลองใหม่`;
      connection.parentElement.classList.add('is-stale');
      if (snapshot.member !== member || snapshot.stage !== stage) resultLabel.textContent = 'ยังโหลดตัวกรองใหม่ไม่ได้ · รายการด้านล่างเป็นข้อมูลก่อนหน้า';
    } finally {
      clearTimeout(timeout);
      if (current === sequence) {
        refreshButton.disabled = false;
        timer = setTimeout(refresh,10000);
      }
    }
  }
  function search() {
    clearTimeout(searchTimer);
    member = input.value.trim(); clear.hidden = !member;
    try { if (member) localStorage.setItem(storageKey,member); else localStorage.removeItem(storageKey); } catch (_) { /* Storage may be unavailable. */ }
    const address = new URL(window.location.href);
    if (member) address.searchParams.set('member',member); else address.searchParams.delete('member');
    window.history.replaceState(null,'',address);
    resultLabel.textContent = 'กำลังค้นหา…';
    refresh();
  }
  function scheduleSearch() {
    clearTimeout(searchTimer);
    clearTimeout(timer);
    ++sequence;
    controller?.abort();
    refreshButton.disabled = false;
    clear.hidden = !input.value.trim();
    resultLabel.textContent = 'กำลังค้นหา…';
    if (!composing) searchTimer = setTimeout(search,350);
  }
  input.addEventListener('input',scheduleSearch);
  input.addEventListener('compositionstart',() => {composing=true;scheduleSearch();});
  input.addEventListener('compositionend',() => {composing=false;scheduleSearch();});
  document.getElementById('publicMemberSearch').addEventListener('submit',event => {
    event.preventDefault();
    if (composing) return;
    search(); document.getElementById('tracking').scrollIntoView({behavior:'auto',block:'start'});
  });
  clear.addEventListener('click',() => {input.value='';search();input.focus();});
  root.querySelectorAll('[data-public-stage]').forEach(button => button.addEventListener('click',() => {
    stage = button.dataset.publicStage;
    root.querySelectorAll('[data-public-stage]').forEach(item => {
      const active = item === button; item.classList.toggle('is-active',active); item.setAttribute('aria-pressed',String(active));
    });
    search();
  }));
  refreshButton.addEventListener('click',search);
  document.addEventListener('visibilitychange',() => {
    clearTimeout(timer);
    clearTimeout(searchTimer);
    if (!document.hidden) { if (input.value.trim() !== member) search(); else refresh(); }
    else { ++sequence; controller?.abort(); refreshButton.disabled=false; }
  });
  render(snapshot);
  try {
    const remembered = localStorage.getItem(storageKey);
    if (!member && remembered && remembered.length<=100) {input.value=remembered;search();return;}
  } catch (_) { /* The board works without storage. */ }
  timer = setTimeout(refresh,10000);
})();
