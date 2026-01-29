(function(){
  if (!window.SEOJusAIBulk) return;

  const cfg = window.SEOJusAIBulk;
  const apiFetch = window.wp && wp.apiFetch ? wp.apiFetch : null;
  if (!apiFetch) return;

  apiFetch.use( wp.apiFetch.createNonceMiddleware(cfg.nonce) );

  const $ = (sel, root=document)=>root.querySelector(sel);
  const $$ = (sel, root=document)=>Array.from(root.querySelectorAll(sel));

  function readFilters(){
    const postTypes = $$('.seojusai-bulk-posttype').filter(i=>i.checked).map(i=>i.value);
    const statuses  = $$('.seojusai-bulk-status').filter(i=>i.checked).map(i=>i.value);
    const limit     = parseInt($('.seojusai-bulk-limit')?.value || '200', 10);
    return { post_types: postTypes, statuses, limit };
  }

  async function listJobs(){
    const box = $('.seojusai-bulk-jobs');
    if (!box) return;
    box.innerHTML = '<p class="muted">Завантаження...</p>';
    try {
      const rows = await apiFetch({ path: cfg.restUrl + '/bulk/jobs?limit=20', method:'GET' });
      if (!rows || !rows.length) { box.innerHTML = '<p class="muted">Поки що немає задач.</p>'; return; }

      const html = [
        '<table class="widefat striped">',
        '<thead><tr>',
        '<th>ID</th><th>Тип</th><th>Статус</th><th>Прогрес</th><th>Оновлено</th><th>Дії</th>',
        '</tr></thead><tbody>',
        rows.map(r=>{
          const total = parseInt(r.total_items||0,10);
          const done = parseInt(r.processed_items||0,10);
          const pct = total>0 ? Math.round((done/total)*100) : 0;
          return `<tr>
            <td>${r.id}</td>
            <td>${escapeHtml(r.job_type||'')}</td>
            <td class="status">${escapeHtml(r.status||'')}</td>
            <td>${done}/${total} (${pct}%)</td>
            <td>${escapeHtml(r.updated_at||'')}</td>
            <td>${renderActions(r)}</td>
          </tr>`;
        }).join(''),
        '</tbody></table>'
      ].join('');
      box.innerHTML = html;
    } catch (e) {
      box.innerHTML = '<p class="muted">Помилка завантаження.</p>';
    }
  }

  function renderActions(r){
    const id = r.id;
    const type = (r.job_type||'');
    const status = (r.status||'');
    const needs = (type==='apply' || type==='rollback');
    if (!needs) return '';
    if (status==='awaiting_approval') return `<button class="button button-small seojusai-approve" data-id="${id}">Approve</button>`;
    if (status==='running' && r.approved_until) return `<button class="button button-small seojusai-revoke" data-id="${id}">Revoke</button>`;
    return '';
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (m)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
  }

  

async function approveJob(id){
  try {
    await apiFetch({ path: cfg.restUrl + '/bulk/jobs/' + id + '/approve', method:'POST', data:{} });
    await listJobs();
  } catch(e) {}
}

async function revokeJob(id){
  try {
    await apiFetch({ path: cfg.restUrl + '/bulk/jobs/' + id + '/revoke', method:'POST', data:{} });
    await listJobs();
  } catch(e) {}
}

async function start(mode){
    const hint = $('.seojusai-bulk-start-hint');
    if (hint) hint.textContent = 'Запуск...';
    const filters = readFilters();
    try {
      const res = await apiFetch({
        path: cfg.restUrl + '/bulk/' + mode,
        method: 'POST',
        data: { filters }
      });
      if (hint) hint.textContent = res && res.job_id ? ('Bulk job #' + res.job_id + ' створено') : 'Готово';
      await listJobs();
    } catch (e) {
      if (hint) hint.textContent = (e && e.message) ? e.message : 'Помилка';
    }
  }

  document.addEventListener('click', (ev)=>{
    const ab = ev.target.closest('.seojusai-approve');
    if (ab){ ev.preventDefault(); approveJob(ab.getAttribute('data-id')); return; }
    const rb = ev.target.closest('.seojusai-revoke');
    if (rb){ ev.preventDefault(); revokeJob(rb.getAttribute('data-id')); return; }

    const btn = ev.target.closest('.seojusai-bulk-start');
    if (!btn) return;
    ev.preventDefault();
    const mode = btn.getAttribute('data-mode') || cfg.mode || 'audit';
    start(mode);
  });

  // initial
  listJobs();
  setInterval(listJobs, 5000);
})();
