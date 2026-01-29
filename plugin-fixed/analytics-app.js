/* global SEOJusAIAnalyticsApp */

/**
 * SEOJusAI Analytics SPA
 *
 * Ціль: візуально та логічно наблизити UX до Rank Math Analytics,
 * але залишити реалізацію максимально легкою (без білду, без сторонніх бібліотек).
 */
(function () {
  const el = window.wp && window.wp.element;
  if (!el) return;

  const apiFetch = (window.wp && window.wp.apiFetch) ? window.wp.apiFetch : null;
  const components = (window.wp && window.wp.components) ? window.wp.components : {};

  const { createElement: h, useEffect, useMemo, useState } = el;
  const {
    Button,
    Spinner,
    Modal,
    Notice,
    SelectControl,
    TextControl,
  } = components;

  function setNonce() {
    if (!apiFetch) return;
    apiFetch.use((options, next) => {
      options.headers = options.headers || {};
      options.headers['X-WP-Nonce'] = SEOJusAIAnalyticsApp.nonce;
      return next(options);
    });
  }

  function formatNum(n) {
    const num = Number(n || 0);
    return new Intl.NumberFormat().format(num);
  }

  function formatPct(n) {
    const num = Number(n || 0);
    return (num * 100).toFixed(2) + '%';
  }

  function formatPctRaw(n) {
    const num = Number(n || 0);
    return num.toFixed(2) + '%';
  }

  function toPath(urlOrPath) {
    if (!urlOrPath) return '';
    try {
      if (String(urlOrPath).startsWith('http')) {
        return new URL(String(urlOrPath)).pathname || '';
      }
    } catch (e) {
      // ignore
    }
    return String(urlOrPath);
  }

  function sumGscRows(rows) {
    let clicks = 0;
    let impressions = 0;
    let posWeighted = 0;
    rows.forEach(r => {
      clicks += Number(r.clicks || 0);
      impressions += Number(r.impressions || 0);
      posWeighted += Number(r.position || 0) * Number(r.impressions || 0);
    });
    const ctr = impressions > 0 ? (clicks / impressions) : 0;
    const position = impressions > 0 ? (posWeighted / impressions) : 0;
    return { clicks, impressions, ctr, position };
  }

  function aggregateGscPages(rows) {
    const map = new Map();
    rows.forEach(r => {
      const keys = Array.isArray(r.keys) ? r.keys : [];
      const page = toPath(keys[1] || keys[0] || '');
      if (!page) return;
      const prev = map.get(page) || { pagePath: page, clicks: 0, impressions: 0, ctr: 0, position: 0 };
      const clicks = Number(r.clicks || 0);
      const impressions = Number(r.impressions || 0);
      prev.clicks += clicks;
      prev.impressions += impressions;
      prev._posw = (prev._posw || 0) + Number(r.position || 0) * impressions;
      map.set(page, prev);
    });
    const out = [];
    map.forEach(v => {
      v.ctr = v.impressions > 0 ? (v.clicks / v.impressions) : 0;
      v.position = v.impressions > 0 ? ((v._posw || 0) / v.impressions) : 0;
      delete v._posw;
      out.push(v);
    });
    out.sort((a, b) => (b.clicks - a.clicks));
    return out;
  }

  function mergePages(ga4Pages, gscPages) {
    const map = new Map();
    (ga4Pages || []).forEach(p => {
      const path = toPath(p.pagePath || p.path || '');
      if (!path) return;
      map.set(path, { path, ga4: p, gsc: null });
    });
    (gscPages || []).forEach(p => {
      const path = toPath(p.pagePath || p.path || '');
      if (!path) return;
      const cur = map.get(path) || { path, ga4: null, gsc: null };
      cur.gsc = p;
      map.set(path, cur);
    });
    const merged = Array.from(map.values());
    merged.sort((a, b) => {
      const aScore = Number((a.gsc && a.gsc.clicks) || 0) + Number((a.ga4 && a.ga4.sessions) || 0);
      const bScore = Number((b.gsc && b.gsc.clicks) || 0) + Number((b.ga4 && b.ga4.sessions) || 0);
      return bScore - aScore;
    });
    return merged;
  }

  function Card({ title, value, sub, tone }) {
    const cls = 'seojusai-analytics-card' + (tone ? (' is-' + tone) : '');
    return h('div', { className: cls },
      h('div', { className: 'seojusai-analytics-card__title' }, title),
      h('div', { className: 'seojusai-analytics-card__value' }, value),
      sub ? h('div', { className: 'seojusai-analytics-card__sub' }, sub) : null
    );
  }

  function Tabs({ tabs, active, onChange }) {
    return h('div', { className: 'seojusai-analytics-tabs' },
      tabs.map(t => h('button', {
        key: t.id,
        className: 'seojusai-analytics-tab' + (active === t.id ? ' is-active' : ''),
        onClick: () => onChange(t.id)
      }, t.title))
    );
  }

  function LineChart({ title, series, valueKey, format, height }) {
    const hgt = height || 120;
    const w = 520;
    const pad = 14;
    const pts = Array.isArray(series) ? series : [];
    const values = pts.map(p => Number(p[valueKey] || 0));
    const max = Math.max(1, ...values);
    const min = Math.min(0, ...values);
    const range = Math.max(1, max - min);

    const stepX = pts.length > 1 ? (w - pad * 2) / (pts.length - 1) : 0;
    const points = pts.map((p, i) => {
      const x = pad + i * stepX;
      const v = Number(p[valueKey] || 0);
      const y = pad + (hgt - pad * 2) * (1 - ((v - min) / range));
      return { x, y, v, date: p.date };
    });

    const poly = points.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');

    const last = points.length ? points[points.length - 1].v : 0;
    const display = format ? format(last) : String(last);

    return h('div', { className: 'seojusai-analytics-chart' },
      h('div', { className: 'seojusai-analytics-chart__head' },
        h('div', { className: 'seojusai-analytics-chart__title' }, title),
        h('div', { className: 'seojusai-analytics-chart__value' }, display)
      ),
      h('div', { className: 'seojusai-analytics-chart__canvas' },
        h('svg', { width: '100%', viewBox: `0 0 ${w} ${hgt}`, preserveAspectRatio: 'none' },
          h('polyline', { className: 'seojusai-analytics-chart__line', points: poly, fill: 'none' }),
          points.length ? h('circle', { className: 'seojusai-analytics-chart__dot', cx: points[points.length - 1].x, cy: points[points.length - 1].y, r: 2.5 }) : null
        )
      ),
      h('div', { className: 'seojusai-analytics-chart__foot' },
        pts.length ? (pts[0].date + ' — ' + pts[pts.length - 1].date) : '—'
      )
    );
  }

  function DataTable({ columns, rows, sortKey, sortDir, onSort, onRowClick }) {
    return h('table', { className: 'widefat striped seojusai-analytics-table' },
      h('thead', null,
        h('tr', null,
          columns.map(c => {
            const sortable = !!c.sortValue;
            const active = sortKey === c.key;
            const cls = 'seojusai-analytics-th' + (sortable ? ' is-sortable' : '') + (active ? ' is-active' : '');
            const arrow = active ? (sortDir === 'desc' ? ' ↓' : ' ↑') : '';
            return h('th', {
              key: c.key,
              className: cls,
              onClick: sortable ? () => onSort(c.key) : undefined,
              title: sortable ? 'Сортувати' : undefined
            }, c.label + arrow);
          })
        )
      ),
      h('tbody', null,
        rows.length ? rows.map((r, idx) =>
          h('tr', {
            key: idx,
            className: onRowClick ? 'is-clickable' : '',
            onClick: onRowClick ? () => onRowClick(r) : undefined
          }, columns.map(c => h('td', { key: c.key }, c.render(r))))
        ) : h('tr', null, h('td', { colSpan: columns.length }, '—'))
      )
    );
  }

  function PageReport({ page, onClose }) {
    if (!page) return null;
    const ga4 = page.ga4 || null;
    const gsc = page.gsc || null;

    return h(Modal, {
      title: 'Звіт по сторінці: ' + page.path,
      onRequestClose: onClose,
      className: 'seojusai-analytics-modal'
    },
      h('div', { className: 'seojusai-analytics-modal__section' },
        h('h3', null, 'GA4'),
        ga4 ? h('div', { className: 'seojusai-analytics-kv' },
          h('div', null, h('strong', null, 'Сеанси: '), formatNum(ga4.sessions || 0)),
          h('div', null, h('strong', null, 'Користувачі: '), formatNum(ga4.users || ga4.totalUsers || 0)),
          h('div', null, h('strong', null, 'Перегляди: '), formatNum(ga4.pageviews || 0)),
          h('div', null, h('strong', null, 'Залучення: '), formatPct(ga4.engagementRate || 0)),
          h('div', null, h('strong', null, 'Відмови: '), formatPctRaw(ga4.bounceRate || 0)),
          h('div', null, h('strong', null, 'Сер. тривалість: '), (Number(ga4.avgSessionDuration || ga4.averageSessionDuration || 0).toFixed(0) + ' c'))
        ) : h('p', { className: 'seojusai-muted' }, 'Немає даних GA4 для цієї сторінки.')
      ),
      h('div', { className: 'seojusai-analytics-modal__section' },
        h('h3', null, 'GSC'),
        gsc ? h('div', { className: 'seojusai-analytics-kv' },
          h('div', null, h('strong', null, 'Кліки: '), formatNum(gsc.clicks || 0)),
          h('div', null, h('strong', null, 'Покази: '), formatNum(gsc.impressions || 0)),
          h('div', null, h('strong', null, 'CTR: '), formatPct(gsc.ctr || 0)),
          h('div', null, h('strong', null, 'Позиція: '), (Number(gsc.position || 0).toFixed(2)))
        ) : h('p', { className: 'seojusai-muted' }, 'Немає даних GSC для цієї сторінки.')
      ),
      h('div', { style: { marginTop: 12 } },
        h(Button, { variant: 'secondary', onClick: onClose }, 'Закрити')
      )
    );
  }

  function AnalyticsApp() {
    const [days, setDays] = useState(Number(SEOJusAIAnalyticsApp.defaultDays || 30));
    const [tab, setTab] = useState('mastery');

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const [ga4Overview, setGa4Overview] = useState(null);
    const [ga4Pages, setGa4Pages] = useState([]);
    const [ga4Series, setGa4Series] = useState([]);

    const [gscProps, setGscProps] = useState([]);
    const [gscSite, setGscSite] = useState('');
    const [gscRows, setGscRows] = useState([]);
    const [gscSeries, setGscSeries] = useState([]);

    const [geminiAnalytics, setGeminiAnalytics] = useState(null);

    // Server-side merged pages (GA4 + GSC) with optional breakdown.
    const [breakdown, setBreakdown] = useState('none');
    const [mergedPages, setMergedPages] = useState([]);

    // Table UX.
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState('score');
    const [sortDir, setSortDir] = useState('desc');
    const [pageReport, setPageReport] = useState(null);

    // SERP competitor overlay (GSC Keywords tab)
    const [selectedKeyword, setSelectedKeyword] = useState(null);
    const [serpOverlay, setSerpOverlay] = useState(null);
    const [serpLoading, setSerpLoading] = useState(false);
    const [serpError, setSerpError] = useState('');

    useEffect(() => {
      setNonce();
    }, []);

    useEffect(() => {
      let mounted = true;
      async function loadAll() {
        if (!apiFetch) return;
        setLoading(true);
        setError('');

        try {
          // 1) GSC properties
          const propsResp = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/gsc/properties' });
          const props = (propsResp && propsResp.properties) ? propsResp.properties : [];
          if (mounted) {
            setGscProps(props);
            const saved = window.localStorage.getItem('seojusai_gsc_site') || '';
            const chosen = saved && props.includes(saved) ? saved : (props[0] || '');
            setGscSite(chosen);
          }

          // 2) GA4
          const ga4O = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/ga4/overview?days=' + days });
          const ga4P = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/ga4/pages?days=' + days + '&limit=500' });
          const ga4T = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/ga4/timeseries?days=' + days });

          // 3) Gemini analytics summary (objective)
          const gemA = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/analytics/gemini?top=30' });

          if (mounted) {
            setGa4Overview((ga4O && ga4O.overview) ? ga4O.overview : null);
            setGa4Pages((ga4P && ga4P.pages) ? ga4P.pages : []);
            setGa4Series((ga4T && ga4T.timeseries) ? ga4T.timeseries : []);
            setGeminiAnalytics((gemA && gemA.ok && gemA.data) ? gemA.data : null);
          }

          // 4) GSC analytics & series (requires selected site)
          const site = (props && props.length)
            ? ((window.localStorage.getItem('seojusai_gsc_site') && props.includes(window.localStorage.getItem('seojusai_gsc_site')))
              ? window.localStorage.getItem('seojusai_gsc_site')
              : props[0])
            : '';

          if (site) {
            const gscA = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/gsc/analytics?site=' + encodeURIComponent(site) + '&days=' + days });
            const gscT = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/gsc/timeseries?site=' + encodeURIComponent(site) + '&days=' + days });
            if (mounted) {
              setGscRows((gscA && gscA.rows) ? gscA.rows : []);
              setGscSeries((gscT && gscT.timeseries) ? gscT.timeseries : []);
              setGscSite(site);
            }
          } else {
            if (mounted) {
              setGscRows([]);
              setGscSeries([]);
            }
          }
        } catch (e) {
          if (mounted) {
            setError((e && e.message) ? e.message : 'Помилка завантаження даних.');
          }
        } finally {
          if (mounted) setLoading(false);
        }
      }
      loadAll();
      return () => { mounted = false; };
    }, [days]);

    useEffect(() => {
      if (gscSite) {
        window.localStorage.setItem('seojusai_gsc_site', gscSite);
      }
    }, [gscSite]);

    const gscSummary = useMemo(() => {
      const rows = Array.isArray(gscRows) ? gscRows : [];
      return sumGscRows(rows);
    }, [gscRows]);

    const gscPagesAgg = useMemo(() => aggregateGscPages(Array.isArray(gscRows) ? gscRows : []), [gscRows]);

    // Fetch server-side merged pages any time the selector changes.
    useEffect(() => {
      let mounted = true;
      async function loadMerged() {
        if (!apiFetch) return;
        try {
          const path = SEOJusAIAnalyticsApp.restUrl + '/analytics/pages?days=' + days
            + '&limit=1000'
            + '&breakdown=' + encodeURIComponent(breakdown)
            + (gscSite ? ('&site=' + encodeURIComponent(gscSite)) : '');
          const resp = await apiFetch({ path });
          if (!mounted) return;
          const rows = (resp && resp.rows) ? resp.rows : [];
          setMergedPages(Array.isArray(rows) ? rows : []);
        } catch (e) {
          if (!mounted) return;
          // Do not fail the whole UI – just clear rows.
          setMergedPages([]);
        }
      }
      loadMerged();
      return () => { mounted = false; };
    }, [days, gscSite, breakdown]);

    // Load SERP overlay when keyword selected.
    useEffect(() => {
      let mounted = true;
      async function loadSerp() {
        if (!apiFetch) return;
        if (!selectedKeyword || !selectedKeyword.query) {
          setSerpOverlay(null);
          setSerpError('');
          return;
        }
        setSerpLoading(true);
        setSerpError('');
        try {
          const path = SEOJusAIAnalyticsApp.restUrl + '/analytics/serp-overlay?keyword=' + encodeURIComponent(selectedKeyword.query)
            + '&hl=uk&gl=ua&device=desktop&limit=10&ai=1';
          const resp = await apiFetch({ path });
          if (!mounted) return;
          const payload = resp && resp.data ? resp.data : resp;
          if (!payload || payload.ok === false) {
            setSerpOverlay(null);
            setSerpError('SERP-оверлей недоступний. Перевір SerpAPI ключ.');
          } else {
            setSerpOverlay(payload);
          }
        } catch (e) {
          if (!mounted) return;
          setSerpOverlay(null);
          setSerpError('Помилка SERP-оверлею.');
        } finally {
          if (mounted) setSerpLoading(false);
        }
      }
      loadSerp();
      return () => { mounted = false; };
    }, [selectedKeyword]);

    const tabs = [
      { id: 'mastery', title: 'Майстерня' },
      { id: 'site', title: 'Аналітика сайту (GA4)' },
      { id: 'seo', title: 'Ефективність SEO (GSC)' },
      { id: 'keywords', title: 'Ключові слова' },
      { id: 'pages', title: 'Сторінки' },
    ];

    const topKeywords = useMemo(() => {
      const rows = Array.isArray(gscRows) ? gscRows : [];
      const map = new Map();
      rows.forEach(r => {
        const keys = Array.isArray(r.keys) ? r.keys : [];
        const q = String(keys[0] || '');
        if (!q) return;
        const prev = map.get(q) || { query: q, clicks: 0, impressions: 0, position: 0, _posw: 0 };
        const clicks = Number(r.clicks || 0);
        const impressions = Number(r.impressions || 0);
        prev.clicks += clicks;
        prev.impressions += impressions;
        prev._posw += Number(r.position || 0) * impressions;
        map.set(q, prev);
      });
      const out = [];
      map.forEach(v => {
        v.ctr = v.impressions > 0 ? (v.clicks / v.impressions) : 0;
        v.position = v.impressions > 0 ? (v._posw / v.impressions) : 0;
        delete v._posw;
        out.push(v);
      });
      out.sort((a, b) => b.clicks - a.clicks);
      return out.slice(0, 50);
    }, [gscRows]);

    function DaysSelector() {
      const opts = [
        { label: '7 днів', value: 7 },
        { label: '30 днів', value: 30 },
        { label: '90 днів', value: 90 },
      ];
      return SelectControl
        ? h(SelectControl, {
          label: 'Період',
          value: days,
          options: opts,
          onChange: (v) => setDays(Number(v))
        })
        : h('div', null, opts.map(o => h('button', {
          key: o.value,
          className: 'button' + (days === o.value ? ' button-primary' : ''),
          style: { marginRight: 6 },
          onClick: () => setDays(o.value)
        }, o.label)));
    }

    const header = h('div', { className: 'seojusai-analytics-header' },
      h('div', { className: 'seojusai-analytics-header__left' },
        h('div', { className: 'seojusai-analytics-header__range' }, h(DaysSelector))
      ),
      h('div', { className: 'seojusai-analytics-header__right' },
        SelectControl ? h(SelectControl, {
          label: 'GSC property',
          value: gscSite,
          options: (gscProps || []).map(p => ({ label: p, value: p })),
          onChange: (v) => setGscSite(v)
        }) : null,
        Button ? h(Button, {
          variant: 'secondary',
          onClick: async () => {
            if (!apiFetch || !gscSite) return;
            setLoading(true);
            try {
              const gscA = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/gsc/analytics?site=' + encodeURIComponent(gscSite) + '&days=' + days });
              const gscT = await apiFetch({ path: SEOJusAIAnalyticsApp.restUrl + '/gsc/timeseries?site=' + encodeURIComponent(gscSite) + '&days=' + days });
              setGscRows((gscA && gscA.rows) ? gscA.rows : []);
              setGscSeries((gscT && gscT.timeseries) ? gscT.timeseries : []);
            } catch (e) {
              setError((e && e.message) ? e.message : 'Помилка оновлення GSC.');
            } finally {
              setLoading(false);
            }
          }
        }, 'Оновити') : null
      )
    );

    // Sorting + filtering for merged pages.
    const filteredMergedPages = useMemo(() => {
      const q = String(search || '').trim().toLowerCase();
      let list = Array.isArray(mergedPages) ? mergedPages.slice() : [];
      if (q) {
        list = list.filter(r => String(r.path || '').toLowerCase().includes(q));
      }

      const sorters = {
        path: (r) => String(r.path || ''),
        gsc_clicks: (r) => Number(r.gsc ? r.gsc.clicks : 0),
        gsc_impressions: (r) => Number(r.gsc ? r.gsc.impressions : 0),
        gsc_ctr: (r) => Number(r.gsc ? r.gsc.ctr : 0),
        gsc_position: (r) => Number(r.gsc ? r.gsc.position : 0),
        ga4_sessions: (r) => Number(r.ga4 ? r.ga4.sessions : 0),
        ga4_users: (r) => Number(r.ga4 ? (r.ga4.users || r.ga4.totalUsers || 0) : 0),
        ga4_pageviews: (r) => Number(r.ga4 ? r.ga4.pageviews : 0),
        ga4_engagement: (r) => Number(r.ga4 ? r.ga4.engagementRate : 0),
        score: (r) => (Number(r.ga4 ? r.ga4.sessions : 0) + Number(r.gsc ? r.gsc.clicks : 0)),
      };

      const fn = sorters[sortKey] || sorters.score;
      list.sort((a, b) => {
        const av = fn(a);
        const bv = fn(b);
        if (typeof av === 'string') {
          return sortDir === 'desc' ? String(bv).localeCompare(String(av)) : String(av).localeCompare(String(bv));
        }
        return sortDir === 'desc' ? (Number(bv) - Number(av)) : (Number(av) - Number(bv));
      });

      return list;
    }, [mergedPages, search, sortKey, sortDir]);

    function onSort(key) {
      if (sortKey === key) {
        setSortDir(sortDir === 'desc' ? 'asc' : 'desc');
      } else {
        setSortKey(key);
        setSortDir('desc');
      }
    }

    if (loading) {
      return h('div', null,
        header,
        h('div', { className: 'seojusai-analytics-loading' }, Spinner ? h(Spinner) : '…', ' Завантаження…')
      );
    }

    if (error) {
      return h('div', null,
        header,
        Notice ? h(Notice, { status: 'error', isDismissible: false }, error)
          : h('div', { className: 'notice notice-error' }, h('p', null, error))
      );
    }

    // ===== Tab content =====
    const mastery = h('div', null,
      h('div', { className: 'seojusai-analytics-cards' },
        h(Card, { title: 'Покази (GSC)', value: formatNum(gscSummary.impressions) }),
        h(Card, { title: 'Кліки (GSC)', value: formatNum(gscSummary.clicks) }),
        h(Card, { title: 'CTR (GSC)', value: formatPct(gscSummary.ctr) }),
        h(Card, { title: 'Позиція (GSC)', value: (gscSummary.position || 0).toFixed(2), tone: 'neutral' })
      ),
      h('div', { className: 'seojusai-analytics-cards' },
        h(Card, { title: 'Сеанси (GA4)', value: formatNum(ga4Overview && ga4Overview.sessions) }),
        h(Card, { title: 'Користувачі (GA4)', value: formatNum(ga4Overview && (ga4Overview.users || ga4Overview.totalUsers)) }),
        h(Card, { title: 'Залучення (GA4)', value: ga4Overview ? formatPct(ga4Overview.engagementRate || 0) : '—' }),
        h(Card, { title: 'Відмови (GA4)', value: ga4Overview ? formatPctRaw(ga4Overview.bounceRate || 0) : '—' })
      ),
      h('div', { className: 'seojusai-analytics-charts' },
        h(LineChart, { title: 'GA4: Сеанси', series: ga4Series, valueKey: 'sessions', format: formatNum }),
        h(LineChart, { title: 'GSC: Кліки', series: gscSeries, valueKey: 'clicks', format: formatNum }),
        h(LineChart, { title: 'GSC: Покази', series: gscSeries, valueKey: 'impressions', format: formatNum })
      ),
      geminiAnalytics ? h('div', { className: 'seojusai-analytics-gemini' },
        h('div', { className: 'seojusai-analytics-gemini__title' }, 'Gemini: обʼєктивний висновок (GA4+GSC)'),
        h('div', { className: 'seojusai-analytics-gemini__summary' }, (geminiAnalytics.meta && geminiAnalytics.meta.summary) ? geminiAnalytics.meta.summary : '—'),
        h('div', { className: 'seojusai-analytics-gemini__meta' },
          'Ризик: ' + ((geminiAnalytics.meta && geminiAnalytics.meta.risk) ? geminiAnalytics.meta.risk : '—') +
          ' • Впевненість: ' + ((geminiAnalytics.meta && (geminiAnalytics.meta.confidence !== undefined)) ? Number(geminiAnalytics.meta.confidence).toFixed(2) : '—')
        )
      ) : null
    );

    const site = h('div', null,
      h('div', { className: 'seojusai-analytics-charts' },
        h(LineChart, { title: 'GA4: Сеанси', series: ga4Series, valueKey: 'sessions', format: formatNum }),
        h(LineChart, { title: 'GA4: Користувачі', series: ga4Series, valueKey: 'users', format: formatNum }),
        h(LineChart, { title: 'GA4: Перегляди', series: ga4Series, valueKey: 'pageviews', format: formatNum })
      ),
      h('h3', { className: 'seojusai-analytics-section-title' }, 'Топ сторінок (GA4)'),
      h(DataTable, {
        columns: [
          { key: 'path', label: 'Сторінка', render: (r) => toPath(r.pagePath || ''), sortValue: (r) => toPath(r.pagePath || '') },
          { key: 'sessions', label: 'Сеанси', render: (r) => formatNum(r.sessions), sortValue: (r) => Number(r.sessions || 0) },
          { key: 'users', label: 'Користувачі', render: (r) => formatNum(r.users || r.totalUsers || 0), sortValue: (r) => Number(r.users || r.totalUsers || 0) },
          { key: 'er', label: 'Залучення', render: (r) => formatPct(r.engagementRate || 0), sortValue: (r) => Number(r.engagementRate || 0) },
        ],
        rows: (ga4Pages || []).slice(0, 50),
        sortKey: null,
        sortDir: null,
        onSort: () => {},
      })
    );

    const seo = h('div', null,
      h('div', { className: 'seojusai-analytics-charts' },
        h(LineChart, { title: 'GSC: Кліки', series: gscSeries, valueKey: 'clicks', format: formatNum }),
        h(LineChart, { title: 'GSC: Покази', series: gscSeries, valueKey: 'impressions', format: formatNum }),
        h(LineChart, { title: 'GSC: CTR', series: gscSeries, valueKey: 'ctr', format: formatPct })
      ),
      h('h3', { className: 'seojusai-analytics-section-title' }, 'Топ сторінок (GSC)'),
      h(DataTable, {
        columns: [
          { key: 'path', label: 'Сторінка', render: (r) => r.pagePath, sortValue: (r) => String(r.pagePath || '') },
          { key: 'clicks', label: 'Кліки', render: (r) => formatNum(r.clicks), sortValue: (r) => Number(r.clicks || 0) },
          { key: 'impr', label: 'Покази', render: (r) => formatNum(r.impressions), sortValue: (r) => Number(r.impressions || 0) },
          { key: 'ctr', label: 'CTR', render: (r) => formatPct(r.ctr), sortValue: (r) => Number(r.ctr || 0) },
          { key: 'pos', label: 'Позиція', render: (r) => (r.position || 0).toFixed(2), sortValue: (r) => Number(r.position || 0) },
        ],
        rows: gscPagesAgg.slice(0, 50),
        sortKey: null,
        sortDir: null,
        onSort: () => {},
      })
    );

    const keywords = h('div', null,
      h('h3', { className: 'seojusai-analytics-section-title' }, 'Топ запитів (GSC)'),
      h(DataTable, {
        columns: [
          { key: 'q', label: 'Запит', render: (r) => r.query, sortValue: (r) => String(r.query || '') },
          { key: 'clicks', label: 'Кліки', render: (r) => formatNum(r.clicks), sortValue: (r) => Number(r.clicks || 0) },
          { key: 'impr', label: 'Покази', render: (r) => formatNum(r.impressions), sortValue: (r) => Number(r.impressions || 0) },
          { key: 'ctr', label: 'CTR', render: (r) => formatPct(r.ctr), sortValue: (r) => Number(r.ctr || 0) },
          { key: 'pos', label: 'Позиція', render: (r) => (r.position || 0).toFixed(2), sortValue: (r) => Number(r.position || 0) },
        ],
        rows: topKeywords,
        sortKey: null,
        sortDir: null,
        onSort: () => {},
        onRowClick: (r) => setSelectedKeyword(r)
      })
      , selectedKeyword ? h('div', { className: 'seojusai-analytics-serp' },
        h('div', { className: 'seojusai-analytics-serp__head' },
          h('div', { className: 'seojusai-analytics-serp__title' }, 'SERP-оверлей: ' + (selectedKeyword.query || '')),
          serpLoading ? (Spinner ? h(Spinner) : h('span', null, '...')) : null
        ),
        serpError ? h('div', { className: 'seojusai-analytics-serp__error' }, serpError) : null,
        serpOverlay && serpOverlay.serp && Array.isArray(serpOverlay.serp.results) ? h('div', { className: 'seojusai-analytics-serp__grid' },
          h('div', null,
            h('h4', { className: 'seojusai-analytics-serp__subtitle' }, 'Топ конкурентів'),
            h('ol', { className: 'seojusai-analytics-serp__list' },
              serpOverlay.serp.results.slice(0, 10).map((it, idx) =>
                h('li', { key: idx },
                  h('div', { className: 'seojusai-analytics-serp__row' },
                    h('span', { className: 'seojusai-analytics-serp__pos' }, '#' + (it.position || (idx + 1))),
                    h('a', { href: it.url, target: '_blank', rel: 'noopener noreferrer' }, it.domain || it.url),
                    it.title ? h('div', { className: 'seojusai-analytics-serp__snippet' }, it.title) : null
                  )
                )
              )
            )
          ),
          serpOverlay.gemini ? h('div', null,
            h('h4', { className: 'seojusai-analytics-serp__subtitle' }, 'Gemini (SERP)'),
            h('div', { className: 'seojusai-analytics-serp__summary' }, (serpOverlay.gemini.meta && serpOverlay.gemini.meta.summary) ? serpOverlay.gemini.meta.summary : '—'),
            (serpOverlay.gemini.opportunities && Array.isArray(serpOverlay.gemini.opportunities) && serpOverlay.gemini.opportunities.length) ?
              h('ul', { className: 'seojusai-analytics-serp__opps' }, serpOverlay.gemini.opportunities.slice(0, 6).map((o, i) =>
                h('li', { key: i }, (o.idea || o.type || 'Ідея') + (o.why ? (' — ' + o.why) : ''))
              ))
              : null
          ) : null
        ) : null
      ) : null
    );

    const pagesColumns = useMemo(() => {
      const cols = [];
      cols.push({ key: 'path', label: 'Сторінка', render: (r) => r.path, sortValue: (r) => String(r.path || '') });

      if (breakdown === 'country') {
        cols.push({ key: 'country', label: 'Країна', render: (r) => r.country || '—', sortValue: (r) => String(r.country || '') });
      } else if (breakdown === 'device') {
        cols.push({ key: 'device', label: 'Пристрій', render: (r) => r.device || '—', sortValue: (r) => String(r.device || '') });
      } else if (breakdown === 'source') {
        cols.push({ key: 'source', label: 'Джерело', render: (r) => r.source || '—', sortValue: (r) => String(r.source || '') });
      }

      cols.push(
        { key: 'gsc_clicks', label: 'Кліки', render: (r) => formatNum(r.gsc ? r.gsc.clicks : 0), sortValue: (r) => Number(r.gsc ? r.gsc.clicks : 0) },
        { key: 'gsc_impressions', label: 'Покази', render: (r) => formatNum(r.gsc ? r.gsc.impressions : 0), sortValue: (r) => Number(r.gsc ? r.gsc.impressions : 0) },
        { key: 'gsc_ctr', label: 'CTR', render: (r) => r.gsc ? formatPct(r.gsc.ctr || 0) : '—', sortValue: (r) => Number(r.gsc ? r.gsc.ctr : 0) },
        { key: 'ga4_sessions', label: 'Сеанси', render: (r) => formatNum(r.ga4 ? r.ga4.sessions : 0), sortValue: (r) => Number(r.ga4 ? r.ga4.sessions : 0) },
        { key: 'ga4_users', label: 'Користувачі', render: (r) => formatNum(r.ga4 ? (r.ga4.users || r.ga4.totalUsers || 0) : 0), sortValue: (r) => Number(r.ga4 ? (r.ga4.users || r.ga4.totalUsers || 0) : 0) },
        { key: 'ga4_engagement', label: 'Залучення', render: (r) => r.ga4 ? formatPct(r.ga4.engagementRate || 0) : '—', sortValue: (r) => Number(r.ga4 ? r.ga4.engagementRate : 0) },
      );
      return cols;
    }, [breakdown]);

    const pages = h('div', null,
      h('div', { className: 'seojusai-analytics-toolbar' },
        TextControl ? h(TextControl, {
          label: 'Пошук по URL',
          value: search,
          onChange: setSearch,
          placeholder: '/services/...
        ' }) : null,
        SelectControl ? h(SelectControl, {
          label: 'Деталізація',
          value: breakdown,
          options: [
            { label: 'Без деталізації', value: 'none' },
            { label: 'За країнами', value: 'country' },
            { label: 'За пристроями', value: 'device' },
            { label: 'За джерелом', value: 'source' },
          ],
          onChange: (v) => setBreakdown(v || 'none')
        }) : null,
        h('div', { className: 'seojusai-analytics-toolbar__hint' }, 'Клік по рядку відкриває звіт сторінки')
      ),
      h('h3', { className: 'seojusai-analytics-section-title' }, 'Сторінки (GA4 + GSC)'),
      h(DataTable, {
        columns: pagesColumns,
        rows: filteredMergedPages.slice(0, 200),
        sortKey,
        sortDir,
        onSort,
        onRowClick: (r) => setPageReport(r)
      }),
      h('div', { className: 'seojusai-analytics-table-foot' },
        'Показано: ' + Math.min(200, filteredMergedPages.length) + ' із ' + filteredMergedPages.length
      )
    );

    const body = { mastery, site, seo, keywords, pages }[tab] || mastery;

    return h('div', { className: 'seojusai-analytics-root' },
      header,
      h(Tabs, { tabs, active: tab, onChange: setTab }),
      h('div', { className: 'seojusai-analytics-body' }, body),
      pageReport ? h(PageReport, { page: pageReport, onClose: () => setPageReport(null) }) : null
    );
  }

  function mount() {
    const root = document.getElementById('seojusai-analytics');
    if (!root) return;

    // WordPress is gradually moving to React 18 APIs. Depending on the WP version,
    // @wordpress/element may expose either `render` (legacy) or `createRoot`.
    if (typeof el.createRoot === 'function') {
      const appRoot = el.createRoot(root);
      appRoot.render(h(AnalyticsApp));
      return;
    }

    if (typeof el.render === 'function') {
      el.render(h(AnalyticsApp), root);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
