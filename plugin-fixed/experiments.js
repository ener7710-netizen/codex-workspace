(function(){
  if (typeof SEOJusAIExperiments === 'undefined' || !SEOJusAIExperiments.experiments) return;

  function getCookie(name){
    var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\\/\+^]/g, '\\$&') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function setCookie(name, value, days){
    var d = new Date(); d.setTime(d.getTime() + (days*24*60*60*1000));
    document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; expires=' + d.toUTCString();
  }
  function pickVariant(exp){
    var cookieName = (SEOJusAIExperiments.cookiePrefix || 'seojusai_exp_') + exp.id;
    var v = getCookie(cookieName);
    if (v === 'A' || v === 'B') return v;
    // deterministic-ish: use crypto if available
    var seed = (navigator.userAgent || '') + '|' + exp.id + '|' + (new Date().toISOString().slice(0,10));
    var hash = 0; for (var i=0;i<seed.length;i++){ hash = ((hash<<5)-hash) + seed.charCodeAt(i); hash |= 0; }
    var pct = Math.abs(hash) % 100;
    v = (pct < (exp.split || 50)) ? 'A' : 'B';
    setCookie(cookieName, v, 30);
    return v;
  }
  function applyExp(exp){
    if (!exp || exp.type !== 'cta_text') return;
    var v = pickVariant(exp);
    var selector = exp.selector || '';
    if (!selector) return;
    var el = document.querySelector(selector);
    if (!el) return;
    var text = (v === 'A') ? (exp.variant_a || '') : (exp.variant_b || '');
    if (!text) return;
    el.textContent = text;
    el.setAttribute('data-seojusai-exp', String(exp.id));
    el.setAttribute('data-seojusai-variant', v);
  }

  try {
    SEOJusAIExperiments.experiments.forEach(applyExp);
  } catch(e){}
})();