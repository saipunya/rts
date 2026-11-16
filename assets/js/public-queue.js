/* Read-only TV queue view. */
(() => {
  'use strict';
  const initial = document.getElementById('queueInitialData');
  const updated = document.getElementById('queueUpdated');
  const roundLabel = document.getElementById('queueRoundLabel');
  if (!initial || !updated || !roundLabel) return;
  const data = JSON.parse(initial.textContent);
  const columns = ['weighed','issued'].map(stage => ({
    stage,
    count: document.querySelector(`[data-queue-column="${stage}"] [data-queue-count]`),
    records: document.querySelector(`[data-queue-column="${stage}"] [data-queue-records]`),
    signature: ''
  }));
  const countFormat = new Intl.NumberFormat('th-TH');
  const weightFormat = new Intl.NumberFormat('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2});
  const dateFormat = new Intl.DateTimeFormat('th-TH',{day:'numeric',month:'short',year:'numeric',timeZone:'Asia/Bangkok'});
  const timeFormat = new Intl.DateTimeFormat('th-TH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,timeZone:'Asia/Bangkok'});
  const activityFormat = new Intl.DateTimeFormat('th-TH',{hour:'2-digit',minute:'2-digit',hour12:false,timeZone:'Asia/Bangkok'});
  let timer, controller, sequence=0;
  const node = (tag,text,className) => {
    const element=document.createElement(tag);
    if(text!==undefined) element.textContent=text;
    if(className) element.className=className;
    return element;
  };
  function roundText(round) {
    return round ? `รอบวันที่ ${dateFormat.format(new Date(round.date+'T00:00:00+07:00'))}` : 'ยังไม่มีรอบราคายาง';
  }
  function renderColumn(column,snapshot) {
    const records=snapshot.records.slice(0,10);
    column.count.textContent=countFormat.format(snapshot.matched);
    column.count.title=`แสดง ${countFormat.format(records.length)} รายการล่าสุด`;
    column.records.style.gridTemplateRows=`repeat(${Math.max(1,records.length)},minmax(0,1fr))`;
    const signature=JSON.stringify([records,snapshot.matched]);
    if(signature===column.signature) return;
    const fragment=document.createDocumentFragment();
    records.forEach(record=>{
      const row=node('article',undefined,'queue-record');
      const main=node('div',undefined,'queue-record-main');
      const name=node('strong',record.name);
      name.title=record.name;
      const sacks=record.sacks==null?'ไม่ระบุจำนวนกระสอบ':`${weightFormat.format(record.sacks)} กระสอบ`;
      const details=`เลขสมาชิก ${record.member_number||'—'} · ลาน ${record.lane||'—'} · ${sacks}`;
      const detailLine=node('span',details);
      detailLine.title=details;
      const time=node('time',activityFormat.format(new Date(record.activity_at)));
      time.dateTime=record.activity_at;
      main.append(name,detailLine); row.append(main,time); fragment.append(row);
    });
    if(!records.length) fragment.append(node('div','ยังไม่มีรายการในคิวนี้','queue-empty'));
    column.records.replaceChildren(fragment);
    column.signature=signature;
  }
  function renderRound(round) { roundLabel.textContent=roundText(round); }
  async function refresh() {
    clearTimeout(timer);
    if(document.hidden) return;
    controller?.abort();
    const request=new AbortController(); controller=request;
    const current=++sequence;
    const timeout=setTimeout(()=>request.abort(),14000);
    updated.parentElement.classList.remove('is-stale');
    try {
      const snapshots=await Promise.all(columns.map(async column=>{
        const url=new URL('public_collection_status.php',window.location.href);
        url.searchParams.set('stage',column.stage);
        const response=await fetch(url,{cache:'no-store',signal:request.signal,headers:{Accept:'application/json'}});
        if(!response.ok) throw new Error('Queue unavailable');
        return response.json();
      }));
      if(current!==sequence) return;
      if(snapshots.some(snapshot=>!Array.isArray(snapshot.records)||!snapshot.updated_at)) throw new Error('Invalid queue data');
      snapshots.forEach((snapshot,index)=>renderColumn(columns[index],snapshot));
      renderRound(snapshots[0].round);
      updated.textContent=`อัปเดตล่าสุด ${timeFormat.format(new Date(snapshots[0].updated_at))}`;
    } catch(error) {
      if(current!==sequence) return;
      updated.textContent=`เชื่อมต่อไม่ได้ · ข้อมูลล่าสุด ${timeFormat.format(new Date(data.updated_at))}`;
      updated.parentElement.classList.add('is-stale');
    } finally {
      clearTimeout(timeout);
      if(current===sequence) timer=setTimeout(refresh,10000);
    }
  }
  columns.forEach((column,index)=>renderColumn(column,data.columns[column.stage]));
  renderRound(data.round);
  document.addEventListener('visibilitychange',()=>{
    clearTimeout(timer);
    if(document.hidden){sequence++;controller?.abort();}
    else refresh();
  });
  timer=setTimeout(refresh,10000);
})();
