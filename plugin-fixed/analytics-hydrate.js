(function () {
    function ready(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded', fn);} else { fn(); } }

    function showNotice(kind, msg){
    var sel = kind==='error' ? '.seojusai-analytics-error' : (kind==='success' ? '.seojusai-analytics-success' : '.seojusai-analytics-notice');
    var box = document.querySelector(sel);
    if(!box) return;
    var p = box.querySelector('p') || box;
    p.textContent = msg;
    box.style.display = '';
}
function hideNotices(){
    ['.seojusai-analytics-notice','.seojusai-analytics-error','.seojusai-analytics-success'].forEach(function(s){
        var el=document.querySelector(s); if(el) el.style.display='none';
    });
}

    function num(x){ return (typeof x==='number' && isFinite(x)) ? x : (isFinite(parseFloat(x)) ? parseFloat(x) : 0); }
    function fmtInt(x){ return String(Math.round(num(x))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
    function fmtPct(x){ return (num(x)*100).toFixed(1) + '%'; }
    function fmtPos(x){ return num(x).toFixed(1); }
    function fmtTime(sec){
        sec = Math.max(0, Math.round(num(sec)));
        var m = Math.floor(sec/60), s = sec%60;
        return m + 'm ' + String(s).padStart(2,'0') + 's';
    }

    function setKpi(metric, value, formatter){
        var el = document.querySelector('.jusai-kpi[data-metric="'+metric+'"] .kpi-num');
        if(!el) return;
        el.textContent = formatter ? formatter(value) : String(value);
    }

    function tableCard(key){
        return document.querySelector('.jusai-card[data-table="'+key+'"]');
    }

    function clearEmpty(card){
        if(!card) return;
        var empty = card.querySelector('tr.seojusai-empty');
        if(empty) empty.remove();
    }

    function setTable(key, rowsHtml){
        var card = tableCard(key);
        if(!card) return;
        var tbody = card.querySelector('tbody');
        if(!tbody) return;
        tbody.innerHTML = rowsHtml || '';
        if(!rowsHtml) {
            tbody.innerHTML = '<tr class="seojusai-empty"><td colspan="'+ (card.querySelectorAll('thead th').length || 1) +'">Немає даних</td></tr>';
        } else {
            clearEmpty(card);
        }

        // Attach SERP overlay behavior to Top Queries table.
        if(key === 'gsc.top_queries'){
            bindSerpOverlay(card);
        }
    }

    function bindSerpOverlay(card){
        if(!card) return;
        if(card.__seojusaiSerpBound) return;
        card.__seojusaiSerpBound = true;

        var overlay = document.getElementById('seojusai-serp-overlay');
        var listEl = overlay ? overlay.querySelector('.seojusai-serp-overlay__list') : null;
        var kwEl = overlay ? overlay.querySelector('.seojusai-serp-overlay__keyword') : null;
        if(!overlay || !listEl || !kwEl) return;

        card.addEventListener('click', async function(ev){
            var td = ev.target && ev.target.closest ? ev.target.closest('td') : null;
            if(!td) return;
            var tr = td.parentElement;
            if(!tr || tr.tagName !== 'TR') return;

            // Query is in the 1st visible column for this table.
            var tds = tr.querySelectorAll('td');
            if(!tds || !tds.length) return;
            var query = (tds[0] ? tds[0].textContent : '') || '';
            query = query.trim();
            if(!query) return;

            overlay.style.display = '';
            kwEl.textContent = query;
            listEl.innerHTML = '<li>Завантаження…</li>';

            try{
                var resp = await apiFetch('/seojusai/v1/analytics/serp-overlay?keyword='+encodeURIComponent(query)+'&limit=10&ai=0');
                var data = resp && resp.data ? resp.data : resp;
                if(!data || data.ok === false){
                    listEl.innerHTML = '<li>Немає даних SERP. Перевір ключ SerpAPI.</li>';
                    return;
                }
                var results = data && data.serp && Array.isArray(data.serp.results) ? data.serp.results : [];
                if(!results.length){
                    listEl.innerHTML = '<li>Немає результатів.</li>';
                    return;
                }
                listEl.innerHTML = results.slice(0,10).map(function(it, idx){
                    var pos = it.position || (idx+1);
                    var title = it.title || it.domain || it.link || '';
                    var link = it.link || it.url || '';
                    var safeTitle = escapeHtml(title);
                    var safeLink = escapeHtml(link);
                    if(link){
                        return '<li>#'+pos+' <a href="'+safeLink+'" target="_blank" rel="noopener">'+safeTitle+'</a></li>';
                    }
                    return '<li>#'+pos+' '+safeTitle+'</li>';
                }).join('');
            }catch(e){
                listEl.innerHTML = '<li>Помилка завантаження SERP: '+ escapeHtml(e && e.message ? e.message : String(e)) +'</li>';
            }
        });
    }

    function cfg(){
        var c = window.SEOJusAIAnalyticsApp || {};
        if(!c.restRoot) c.restRoot = '/wp-json/seojusai/v1';
        if(!c.nonce && window.wpApiSettings && window.wpApiSettings.nonce) c.nonce = window.wpApiSettings.nonce;
        return c;
    }

    function apiFetch(path){
        var c = cfg();
        if(window.wp && wp.apiFetch){
            if(c.nonce && wp.apiFetch.setNonce) wp.apiFetch.setNonce(c.nonce);
            return wp.apiFetch({ path: path });
        }
        // Fallback (should not happen in WP admin)
        return fetch(path, { credentials: 'same-origin' }).then(r=>r.json());
    }

    function getActiveTab(){
        var url = new URL(window.location.href);
        return url.searchParams.get('tab') || 'dashboard';
    }

function getDays(){
    var sel = document.getElementById('seojusai-analytics-days');
    if(sel && sel.value){
        var v = parseInt(sel.value, 10);
        if(isFinite(v) && v>0) return v;
    }
    var c = cfg();
    var d = parseInt(c.defaultDays || 30, 10);
    return (isFinite(d) && d>0) ? d : 30;
}

function uniq(arr){
    var out=[]; var seen=new Set();
    arr.forEach(function(x){
        if(!x) return;
        if(seen.has(x)) return;
        seen.add(x); out.push(x);
    });
    return out;
}

function buildGscCandidates(props){
    var c = cfg();
    var host = (c.siteHost || '').toLowerCase();
    var home = (c.homeUrl || '').toLowerCase();

    var stored = '';
    try { stored = localStorage.getItem('seojusai_gsc_site') || ''; } catch(e){}
    var list = [];
    if(stored) list.push(stored);

    // prefer URL-prefix for current home_url
    if(home){
        list.push(home);
        // normalize trailing slash
        if(!home.endsWith('/')) list.push(home + '/');
    }

    // try https://host and https://www.host
    if(host){
        list.push('https://' + host + '/');
        if(!host.startsWith('www.')) list.push('https://www.' + host + '/');
        list.push('http://' + host + '/');
        if(!host.startsWith('www.')) list.push('http://www.' + host + '/');
        list.push('sc-domain:' + host.replace(/^www\./,''));
    }

    // append all props as fallback
    props.forEach(function(p){ list.push(getSiteStr(p)); });

    return uniq(list);
}

function extractRows(gscResp){
    var raw = (gscResp && gscResp.data) ? gscResp.data : gscResp;
    if(Array.isArray(raw)) return raw;
    if(raw && Array.isArray(raw.rows)) return raw.rows;
    if(raw && raw.data && Array.isArray(raw.data.rows)) return raw.data.rows;
    return [];
}

async function findFirstGscSiteWithData(props, days){
    var candidates = buildGscCandidates(props);
    var checked = 0;
    for(var i=0;i<candidates.length;i++){
        var site = candidates[i];
        if(!site) continue;
        checked++;
        try{
            var resp = await apiFetch('/seojusai/v1/gsc/analytics?site='+encodeURIComponent(site)+'&days='+encodeURIComponent(days));
            var rows = extractRows(resp);
            if(rows && rows.length){
                // consider data present if any impressions/clicks OR any row at all
                var sum = summarizeGsc(rows);
                if(sum.impressions > 0 || sum.clicks > 0 || rows.length > 0){
                    try { localStorage.setItem('seojusai_gsc_site', site); } catch(e){}
                    return { site: site, rows: rows, checked: checked };
                }
            }
        }catch(e){
            // ignore and try next
        }
    }
    return { site: '', rows: [], checked: checked };
}


    
function getSiteStr(p){
    if(!p) return '';
    if(typeof p === 'string') return p;
    return p.siteUrl || p.site || p.url || '';
}

function pickGscSite(props){
    if(!Array.isArray(props) || props.length===0) return '';
    var c = cfg();
    var host = (c.siteHost || '').toLowerCase();
    var home = (c.homeUrl || '').toLowerCase();
    // prefer a property that matches current host
    if(host){
        // prefer https URL-prefix property
        var httpsPrefix = props.find(function(p){
            var s = getSiteStr(p).toLowerCase();
            return s.startsWith('https://') && s.includes(host);
        });
        if(httpsPrefix) return getSiteStr(httpsPrefix);
        // then any URL-prefix
        var anyPrefix = props.find(function(p){
            var s = getSiteStr(p).toLowerCase();
            return (s.startsWith('http://') || s.startsWith('https://')) && s.includes(host);
        });
        if(anyPrefix) return getSiteStr(anyPrefix);
        // then domain property
        var domainProp = props.find(function(p){
            var s = getSiteStr(p).toLowerCase();
            return s.startsWith('sc-domain:') && s.includes(host);
        });
        if(domainProp) return getSiteStr(domainProp);
    }
    // stored preference
    var stored = '';
    try { stored = localStorage.getItem('seojusai_gsc_site') || ''; } catch(e){}
    if(stored){
        var found = props.find(function(p){ return getSiteStr(p) === stored; });
        if(found) return stored;
    }
    var first = props[0];
    var site = getSiteStr(first);
    if(site){
        try { localStorage.setItem('seojusai_gsc_site', site); } catch(e){}
    }
    return site;
}

    function summarizeGsc(rows){
        var clicks=0, imps=0, posSum=0;
        if(!Array.isArray(rows)) rows=[];
        rows.forEach(function(r){
            var c = num(r.clicks), i = num(r.impressions);
            clicks += c;
            imps += i;
            posSum += num(r.position) * i; // weight by impressions
        });
        var ctr = imps>0 ? (clicks/imps) : 0;
        var pos = imps>0 ? (posSum/imps) : 0;
        return { clicks: clicks, impressions: imps, ctr: ctr, position: pos };
    }

    function topQueries(rows, limit){
        limit = limit || 10;
        var map = new Map();
        (Array.isArray(rows)?rows:[]).forEach(function(r){
            var keys = Array.isArray(r.keys) ? r.keys : [];
            var q = keys[0] || '';
            if(!q) return;
            var cur = map.get(q) || { clicks:0, impressions:0, positionSum:0 };
            cur.clicks += num(r.clicks);
            cur.impressions += num(r.impressions);
            cur.positionSum += num(r.position) * num(r.impressions);
            map.set(q, cur);
        });
        var arr = Array.from(map.entries()).map(function(e){
            var q=e[0], v=e[1];
            var ctr = v.impressions>0 ? v.clicks/v.impressions : 0;
            var pos = v.impressions>0 ? v.positionSum/v.impressions : 0;
            return { query:q, clicks:v.clicks, impressions:v.impressions, ctr:ctr, position:pos };
        });
        arr.sort((a,b)=>b.clicks-a.clicks);
        return arr.slice(0, limit);
    }

    
    function topPagesGsc(rows, limit){
        limit = limit || 10;
        var map = new Map();
        (Array.isArray(rows)?rows:[]).forEach(function(r){
            var keys = Array.isArray(r.keys) ? r.keys : [];
            var page = keys[1] || '';
            if(!page) return;
            var cur = map.get(page) || { clicks:0, impressions:0 };
            cur.clicks += num(r.clicks);
            cur.impressions += num(r.impressions);
            map.set(page, cur);
        });
        var arr = Array.from(map.entries()).map(function(e){
            var page=e[0], v=e[1];
            var ctr = v.impressions>0 ? v.clicks/v.impressions : 0;
            return { page:page, clicks:v.clicks, impressions:v.impressions, ctr:ctr };
        });
        arr.sort((a,b)=>b.clicks-a.clicks);
        return arr.slice(0, limit);
    }

    function renderGscPagesTable(items){
        if(!items || items.length===0) return '';
        return items.map(function(it){
            return '<tr>'
                + '<td>'+ escapeHtml(it.page) +'</td>'
                + '<td>'+ fmtInt(it.impressions) +'</td>'
                + '<td>'+ fmtInt(it.clicks) +'</td>'
                + '<td>'+ (it.impressions>0 ? (it.ctr*100).toFixed(2)+'%' : '0%') +'</td>'
                + '</tr>';
        }).join('');
    }
function topPagesGa4(rows, limit){
        limit = limit || 10;
        if(!Array.isArray(rows)) return [];
        return rows.slice(0, limit);
    }

    function renderQueriesTable(items){
        if(!items || items.length===0) return '';
        return items.map(function(it){
            return '<tr>'
                + '<td>'+ escapeHtml(it.query) +'</td>'
                + '<td>'+ fmtInt(it.clicks) +'</td>'
                + '<td>'+ fmtInt(it.impressions) +'</td>'
                + '<td>'+ (it.impressions>0 ? (it.ctr*100).toFixed(2)+'%' : '0%') +'</td>'
                + '<td>'+ fmtPos(it.position) +'</td>'
                + '</tr>';
        }).join('');
    }

    function renderPagesTable(items){
        if(!items || items.length===0) return '';
        return items.map(function(p){
            var path = p.pagePath || p.path || p.url || '';
            var title = p.title || '';
            var sessions = p.sessions || p.session || p.views || 0;
            var users = p.users || p.totalUsers || 0;
            var pageviews = p.pageviews || p.screenPageViews || p.views || 0;
            return '<tr>'
                + '<td>'+ escapeHtml(path) +'</td>'
                + '<td>'+ fmtInt(sessions) +'</td>'
                + '<td>'+ fmtInt(users) +'</td>'
                + '<td>'+ fmtInt(pageviews) +'</td>'
                + '</tr>';
        }).join('');
    }

    function escapeHtml(s){
        s = String(s||'');
        return s.replace(/[&<>"']/g, function(ch){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]);
        });
    }

    async function hydrate(){
        hideNotices();
        var tab = getActiveTab();
        // Use the same day selector for all data sources.
        var days = getDays();

        // GA4 overview
        try{
            var ga4Overview = await apiFetch('/seojusai/v1/ga4/overview?days='+days);
            var ovWrap = ga4Overview && ga4Overview.data ? ga4Overview.data : ga4Overview;
            // Controller returns { source, overview } (and snapshot meta). Accept both shapes.
            var ov = (ovWrap && ovWrap.overview && typeof ovWrap.overview==='object') ? ovWrap.overview : ovWrap;
            if(ov && typeof ov==='object'){
                setKpi('ga4.sessions', ov.sessions, fmtInt);
                setKpi('ga4.users', ov.users, fmtInt);
                setKpi('ga4.pageviews', ov.pageviews, fmtInt);
                setKpi('ga4.engagementRate', ov.engagementRate, fmtPct);
                setKpi('ga4.bounceRate', ov.bounceRate, fmtPct);
                setKpi('ga4.avgSessionDuration', ov.avgSessionDuration, fmtTime);
            }
        }catch(e){
            showNotice('notice', 'GA4: дані недоступні (не підключено або немає доступу).');
        }

        // GA4 pages
        try{
            var ga4Pages = await apiFetch('/seojusai/v1/ga4/pages?days='+days+'&limit=10');
            var pdataWrap = ga4Pages && ga4Pages.data ? ga4Pages.data : ga4Pages;
            // Controller returns { source, pages }. Accept both shapes.
            var pdata = (pdataWrap && Array.isArray(pdataWrap.pages)) ? pdataWrap.pages : pdataWrap;
            var pages = Array.isArray(pdata) ? pdata : (pdata && pdata.rows ? pdata.rows : []);
            setTable('ga4.top_pages', renderPagesTable(pages));
        }catch(e){
            // keep silent if GA4 not connected
        }

        
// GSC
try{
    var propsResp = await apiFetch('/seojusai/v1/gsc/properties');
    var raw = (propsResp && propsResp.data) ? propsResp.data : propsResp;

    // normalize possible shapes
    var props = raw;
    if(props && !Array.isArray(props)){
        if(Array.isArray(props.sites)) props = props.sites;
        else if(Array.isArray(props.properties)) props = props.properties;
        else if(props.data && Array.isArray(props.data)) props = props.data;
        else props = [];
    }

    if(!Array.isArray(props) || props.length === 0){
        showNotice('error','GSC: немає доступних ресурсів. Перевірте права Service Account у Search Console (Users and permissions).');
        return;
    }

    
// days already resolved at hydrate() start

// First, try smart selection (and ensure return type is string)
var site = pickGscSite(props);
if(!site){
    var first = props[0] || {};
    site = getSiteStr(first);
}

// If selected site yields no data, automatically try other available properties (Rank Math-like behavior)
var found = await findFirstGscSiteWithData(props, days);
if(!found.site){
    showNotice('notice','GSC: даних за обраний період не знайдено ('+days+' днів) у жодному доступному ресурсі. Перевірте, що у GSC є дані та Service Account має доступ до потрібного ресурсу (URL-prefix vs sc-domain). Перевірено ресурсів: '+found.checked+'.');
    return;
}

// If our picked site differs from the one with data, inform and continue with the working one.
if(site && found.site !== site){
    showNotice('success','GSC ресурс змінено на: ' + found.site + ' (знайдені дані)');
}else{
    showNotice('success','GSC ресурс: ' + found.site);
}

site = found.site;
var rows = found.rows;


    var sum = summarizeGsc(rows);
    setKpi('gsc.clicks', sum.clicks, fmtInt);
    setKpi('gsc.impressions', sum.impressions, fmtInt);
    setKpi('gsc.ctr', sum.ctr, function(v){ return (v*100).toFixed(2)+'%'; });
    setKpi('gsc.position', sum.position, fmtPos);

    // Total unique queries + position buckets
    var qset = new Set();
    var b13=0,b410=0,b1150=0,b51100=0;
    rows.forEach(function(r){
        var keys = Array.isArray(r.keys) ? r.keys : [];
        var q = keys[0] || '';
        if(q) qset.add(q);
        var p = num(r.position);
        if(p>0 && p<=3) b13++;
        else if(p>3 && p<=10) b410++;
        else if(p>10 && p<=50) b1150++;
        else if(p>50 && p<=100) b51100++;
    });
    setKpi('gsc.keywords', qset.size, fmtInt);
    setKpi('gsc.pos_1_3', b13, fmtInt);
    setKpi('gsc.pos_4_10', b410, fmtInt);
    setKpi('gsc.pos_11_50', b1150, fmtInt);
    setKpi('gsc.pos_51_100', b51100, fmtInt);

    var tq = topQueries(rows, 10);
    setTable('gsc.top_queries', renderQueriesTable(tq));
    var tp = topPagesGsc(rows, 10);
    setTable('gsc.top_pages', renderGscPagesTable(tp));
}catch(e){
    showNotice('error','GSC: помилка запиту (' + (e && e.message ? e.message : String(e)) + ').');
}
}

    ready(hydrate);
})();