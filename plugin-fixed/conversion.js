(function(){
  try{
    var cfg = window.SEOJusAIConv || {};
    if(!cfg.endpoint || !cfg.nonce){ return; }

    var startTs = Date.now();

    function getSessionId(){
      try{
        var k='seojusai_sid_v1';
        var v = localStorage.getItem(k);
        if(!v){
          v = 's_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
          localStorage.setItem(k, v);
        }
        return v;
      }catch(e){ return ''; }
    }

    function detectSource(){
      try{
        var r = document.referrer || '';
        var s = r.toLowerCase();
        if(!s) return 'unknown';
        if(s.indexOf('chatgpt')>-1 || s.indexOf('openai')>-1) return 'openai';
        if(s.indexOf('gemini')>-1 || s.indexOf('bard')>-1) return 'gemini';
        if(s.indexOf('claude')>-1 || s.indexOf('anthropic')>-1) return 'claude';
        if(s.indexOf('perplexity')>-1) return 'perplexity';
        if(s.indexOf('copilot')>-1 || s.indexOf('bing')>-1) return 'copilot';
        return 'unknown';
      }catch(e){ return 'unknown'; }
    }

    var sid = getSessionId();
    var src = detectSource();

    function send(eventType, meta){
      try{
        var payload = {
          nonce: cfg.nonce,
          post_id: cfg.post_id || 0,
          event_type: eventType,
          source: src,
          session_id: sid,
          ref: document.referrer || '',
          meta: meta || {}
        };
        fetch(cfg.endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-SeoJusAI-Nonce': cfg.nonce
          },
          body: JSON.stringify(payload),
          credentials: 'omit',
          keepalive: true
        }).catch(function(){});
      }catch(e){}
    }

    // tel/mail clicks
    document.addEventListener('click', function(ev){
      var t = ev.target;
      if(!t) return;
      var a = t.closest ? t.closest('a') : null;
      if(!a || !a.getAttribute) return;
      var href = a.getAttribute('href') || '';

      if(href.indexOf('tel:') === 0){
        // lead only if user spent some time on page (anti-noise)
        var dwell = Math.max(0, Math.round((Date.now()-startTs)/1000));
        var isLead = dwell >= 8;
        send(isLead ? 'lead' : 'tel_click', {lead_kind: 'tel', dwell_s: dwell});
      }
      if(href.indexOf('mailto:') === 0){
        send('lead', {lead_kind: 'mailto'});
      }
    }, true);

    // forms: if marked as lead => lead, else generic conversion
    document.addEventListener('submit', function(ev){
      try{
        var form = ev.target;
        if(!form || !form.getAttribute) { send('form_submit'); return; }
        var leadKind = form.getAttribute('data-seojusai-lead');
        if(leadKind){
          send('lead', {lead_kind: leadKind});
        }else{
          send('form_submit');
        }
      }catch(e){
        send('form_submit');
      }
    }, true);

    // buttons/links explicitly marked as lead CTA
    document.addEventListener('click', function(ev){
      try{
        var t = ev.target;
        if(!t) return;
        var el = t.closest ? t.closest('[data-seojusai-lead]') : null;
        if(!el || !el.getAttribute) return;

        // avoid double counting tel/mail already handled
        if(el.tagName && el.tagName.toLowerCase()==='a'){
          var href = el.getAttribute('href') || '';
          if(href.indexOf('tel:')===0 || href.indexOf('mailto:')===0) return;
        }

        var kind = el.getAttribute('data-seojusai-lead') || 'cta';
        send('lead', {lead_kind: kind});
      }catch(e){}
    }, true);

  }catch(e){}
})();
