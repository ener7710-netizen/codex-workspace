(function () {
  'use strict';

  const cfg = window.SEOJusAIMarket || {};
  // restUrl використовується для nonce/context; apiFetch працює з REST path.
  if (!window.wp || !window.wp.apiFetch) return;

  // Ensure REST nonce for wp-api-fetch
  if (window.wp && window.wp.apiFetch && cfg.nonce) {
    window.wp.apiFetch.use(window.wp.apiFetch.createNonceMiddleware(cfg.nonce));
  }

  const api = async (endpoint, options = {}) => {
    const path = '/seojusai/v1' + endpoint;
    return await window.wp.apiFetch({ path, ...options });
  };

  const el = (sel) => document.querySelector(sel);

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[c]));
  }

  async function loadCompetitors() {
    const box = el('.seojusai-market-competitors');
    if (!box) return;

    const data = await api('/market/competitors', { method: 'GET' });
    const items = (data && data.items) ? data.items : [];

    if (!items.length) {
      box.innerHTML = '<p>Поки що немає даних. Натисніть “Оновити та просканувати”.</p>';
      return;
    }

    const rows = items.map((it) => {
      const id = parseInt(it.id, 10) || 0;
      const url = esc(it.url);
      const source = esc(it.source || 'serp');
      const q = esc(it.query_text || '');
      const best = parseInt(it.best_position, 10) || 0;
      const app = parseInt(it.appearances, 10) || 0;
      const st = esc(it.status || '');
      const ignored = (it.status === 'ignored');

      return `
        <tr data-id="${id}">
          <td><a href="${url}" target="_blank" rel="noopener">${url}</a></td>
          <td>${source}</td>
          <td>${best ? '#' + best : '—'}</td>
          <td>${app}</td>
          <td title="${q}">${q ? q.slice(0, 40) + (q.length > 40 ? '…' : '') : '—'}</td>
          <td>${st}</td>
          <td>
            <label><input type="checkbox" class="seojusai-market-ignore" ${ignored ? 'checked' : ''}/> Ігнорувати</label>
          </td>
        </tr>
      `;
    }).join('');

    box.innerHTML = `
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Конкурент</th>
            <th>Джерело</th>
            <th>Краща позиція</th>
            <th>Появи</th>
            <th>Запит</th>
            <th>Статус</th>
            <th>Дії</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
      <p class="description">Конкуренти формуються автоматично з SERP. “Ігнорувати” — виключає зі сканувань.</p>
    `;

    box.querySelectorAll('.seojusai-market-ignore').forEach((cb) => {
      cb.addEventListener('change', async (e) => {
        const tr = e.target.closest('tr');
        const id = parseInt(tr.getAttribute('data-id'), 10) || 0;
        try {
          await api(`/market/competitors/${id}/ignore`, {
            method: 'POST',
            data: { ignored: e.target.checked ? 1 : 0 }
          });
          await loadCompetitors();
        } catch (err) {
          alert('Помилка збереження.');
          e.target.checked = !e.target.checked;
        }
      });
    });
  }

  async function loadSummary() {
    const box = el('.seojusai-market-summary');
    if (!box) return;

    const data = await api('/market/summary', { method: 'GET' });
    const summary = (data && data.summary) ? data.summary : { total: 0, by_type: {} };
    const rules = (data && data.rules) ? data.rules : {};

    const by = summary.by_type || {};
    const rows = Object.keys(by).map((k) => {
      const it = by[k];
      const pct = (it.pct || 0) * 100;
      return `<tr><td>${esc(k)}</td><td>${parseInt(it.total,10)||0}</td><td>${parseInt(it.with_cta,10)||0}</td><td>${pct.toFixed(0)}%</td></tr>`;
    }).join('') || '<tr><td colspan="4">Немає сигналів.</td></tr>';

    box.innerHTML = `
      <div class="card">
        <h3>Сигнали (агреговано)</h3>
        <p>Всього сигналів: <strong>${parseInt(summary.total,10)||0}</strong></p>
        <table class="widefat striped">
          <thead><tr><th>Тип сторінки</th><th>Всього</th><th>З soft CTA</th><th>%</th></tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
      <div class="card">
        <h3>Правила (для Lead Funnel)</h3>
        <pre style="white-space:pre-wrap;">${esc(JSON.stringify(rules, null, 2))}</pre>
      </div>
    `;
  }

  async function refresh() {
    const btn = el('.seojusai-market-refresh');
    const st = el('.seojusai-market-status');
    if (btn) btn.disabled = true;
    if (st) st.textContent = 'Працюю…';

    try {
      await api('/market/refresh', {
        method: 'POST',
        data: { max_queries: 8 }
      });
      if (st) st.textContent = 'Готово.';
    } catch (e) {
      if (st) st.textContent = 'Помилка. Перевірте налаштування SERP/Gemini.';
    } finally {
      if (btn) btn.disabled = false;
      await loadCompetitors();
      await loadSummary();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const btn = el('.seojusai-market-refresh');
    if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); refresh(); });
    loadCompetitors();
    loadSummary();
  });
})();
