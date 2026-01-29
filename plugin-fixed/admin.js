/**
 * SEOJusAI Admin App (Base)
 * Vanilla JS Orchestrator.
 */

(function () {
    'use strict';

    const SEOJusAI = {
        /**
         * Инициализация
         */
        init: function () {
            this.bindEvents();
            this.initModuleToggles();
            // this.initHealthCheck(); // Зарезервировано для будущих проверок API
        },

        /**
         * Привязка событий
         */
        bindEvents: function () {
            // Подтверждение действий для элементов с атрибутом data-seojusai-confirm
            document.querySelectorAll('[data-seojusai-confirm]').forEach(el => {
                el.addEventListener('click', e => {
                    const msg = el.getAttribute('data-seojusai-confirm');
                    if (!window.confirm(msg || 'Вы уверены?')) {
                        e.preventDefault();
                    }
                });
            });
        },

        /**
         * Управление модулями через AJAX (iOS Style Toggles)
         */
        initModuleToggles: function () {
            const toggles = document.querySelectorAll('.seojusai-module-toggle');
            const config = window.SEOJusAIAdmin || {};

            toggles.forEach(checkbox => {
                checkbox.addEventListener('change', async (e) => {
                    const moduleSlug = e.target.dataset.module;
                    const isEnabled = e.target.checked;
                    
                    this.notify(`Обновление модуля ${moduleSlug}...`, 'info');

                    try {
                        const formData = new FormData();
                        formData.append('action', 'seojusai_toggle_module');
                        formData.append('module', moduleSlug);
                        formData.append('enabled', isEnabled ? '1' : '0');
                        formData.append('_ajax_nonce', config.nonce);

                        const response = await fetch(config.ajaxurl, {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            this.notify(`Модуль ${moduleSlug} успешно ${isEnabled ? 'включен' : 'выключен'}`, 'success');
                        } else {
                            this.notify(result.data || 'Ошибка обновления', 'error');
                            e.target.checked = !isEnabled; // Откат состояния переключателя
                        }
                    } catch (error) {
                        this.notify('Ошибка связи с сервером', 'error');
                        e.target.checked = !isEnabled; // Откат состояния
                    }
                });
            });
        },

        /**
         * Система уведомлений (Toasts)
         * Создает контейнер, если его нет, и добавляет в него сообщения.
         */
        notify: function (message, type = 'info') {
            let container = document.querySelector('.seojusai-toast-container');
            
            if (!container) {
                container = document.createElement('div');
                container.className = 'seojusai-toast-container';
                container.style.cssText = 'position:fixed; top:40px; right:20px; z-index:10000; width:300px;';
                document.body.appendChild(container);
            }

            const notice = document.createElement('div');
            // Приведение типов к стандартам WP (success, error, info, warning)
            const wpType = type === 'error' ? 'notice-error' : (type === 'success' ? 'notice-success' : 'notice-info');
            
            notice.className = `notice ${wpType} is-dismissible seojusai-toast`;
            notice.style.cssText = 'margin-bottom:10px; display:block; box-shadow:0 4px 12px rgba(0,0,0,0.1); opacity:1; transition: opacity 0.5s ease;';
            
            notice.innerHTML = `
                <p><strong>SEOJusAI:</strong> ${message}</p>
                <button type="button" class="notice-dismiss" style="text-decoration:none;">
                    <span class="screen-reader-text">Закрыть</span>
                </button>
            `;

            // Обработка кнопки закрытия
            notice.querySelector('.notice-dismiss').addEventListener('click', () => {
                notice.remove();
            });

            container.appendChild(notice);
            
            // Автоматическое удаление через 4 секунды
            setTimeout(() => {
                if (notice.parentNode) {
                    notice.style.opacity = '0';
                    setTimeout(() => notice.remove(), 500);
                }
            }, 4000);
        }
    };

    // Запуск при полной загрузке DOM
    document.addEventListener('DOMContentLoaded', () => SEOJusAI.init());

})();
document.addEventListener('DOMContentLoaded', function() {
    // Обробка кнопок створення сторінок у списку завдань (Tasks)
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.seojusai-btn-create-page')) {
            const btn = e.target.closest('.seojusai-btn-create-page');
            const title = btn.dataset.title;
            const reason = btn.dataset.reason;
            
            if (!confirm(`Створити чернетку сторінки "${title}"?`)) return;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner is-active"></span> Створюю...';

            const data = new URLSearchParams();
            data.append('action', 'seojusai_create_page');
            data.append('nonce', seojusai_data.nonce);
            data.append('title', title);
            data.append('reason', reason);

            fetch(ajaxurl, {
                method: 'POST',
                body: data,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    btn.innerHTML = '✅ Створено';
                    btn.classList.replace('button-primary', 'button-disabled');
                    // Додаємо посилання на редагування поруч
                    const link = document.createElement('a');
                    link.href = response.data.edit_url;
                    link.target = '_blank';
                    link.innerText = ' Перейти до редагування';
                    link.style.display = 'block';
                    btn.parentNode.appendChild(link);
                } else {
                    alert('Помилка: ' + response.data.message);
                    btn.disabled = false;
                    btn.innerText = 'Спробувати знову';
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                btn.disabled = false;
            });
        }
    });
});


// SEOJusAI: GSC UI (async via REST)
(function(){
  'use strict';
  function getCfg(){ return window.SEOJusAIAdmin || {}; }
  function restFetch(path, opts){
    const cfg=getCfg();
    const url=(cfg.restRoot || '/wp-json/').replace(/\/+$/,'/') + path.replace(/^\/+/, '');
    const headers=Object.assign({'X-WP-Nonce': cfg.restNonce || ''}, (opts && opts.headers) ? opts.headers : {});
    return fetch(url, Object.assign({credentials:'same-origin'}, opts||{}, {headers}));
  }
  function setStatus(el, msg, type){ if(!el) return; el.textContent=msg; el.className='seojusai-status seojusai-status-' + (type||'info'); }
  document.addEventListener('DOMContentLoaded', function(){
    const btn=document.getElementById('seojusai-gsc-refresh');
    if(!btn) return;
    const out=document.getElementById('seojusai-gsc-output');
    const status=document.getElementById('seojusai-gsc-status');
    btn.addEventListener('click', function(e){
      e.preventDefault();
      setStatus(status, 'Перевіряємо підключення…', 'info');
      restFetch('seojusai/v1/gsc/properties', {method:'GET'})
        .then(function(r){ return r.json().then(function(j){ return {ok:r.ok, status:r.status, json:j};}); })
        .then(function(res){
          if(!res.ok){ setStatus(status, 'Помилка отримання даних GSC ('+res.status+').', 'error'); return; }
          const j=res.json||{};
          if(!j.connected){ setStatus(status, 'GSC не підключено. Перевір service account та доступ до ресурсу.', 'warning'); }
          else { setStatus(status, '✅ GSC підключено. Знайдено властивостей: ' + ((j.properties||[]).length), 'success'); }
          if(out){
            out.innerHTML='';
            const props=j.properties||[];
            if(!props.length){ out.innerHTML='<div class="seojusai-empty">Немає доступних ресурсів.</div>'; return; }
            const ul=document.createElement('ul'); ul.className='seojusai-list';
            props.forEach(function(p){ const li=document.createElement('li'); li.textContent=p; ul.appendChild(li); });
            out.appendChild(ul);
          }
        })
        .catch(function(){ setStatus(status, 'Сталася помилка. Спробуйте ще раз.', 'error'); });
    });
  });
})();
