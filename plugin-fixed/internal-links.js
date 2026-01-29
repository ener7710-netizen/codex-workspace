(function () {
	'use strict';

	if (typeof wp === 'undefined' || !wp.apiFetch) {
		return;
	}

	const settings = window.SEOJusAIAdminSettings || {};
	const apiRoot = (settings.restUrl || '').replace(/\/+$/, '');
	const nonce = settings.nonce || '';

	wp.apiFetch.use(function (options, next) {
		options = options || {};
		options.headers = Object.assign({}, options.headers || {}, {
			'X-WP-Nonce': nonce
		});
		return next(options);
	});

	function el(id) { return document.getElementById(id); }

	function showNotice(type, message) {
		const box = el('seojusai-il-notice');
		if (!box) return;
		box.style.display = 'block';
		box.className = 'seojusai-notice seojusai-notice--' + type;
		box.textContent = message;
	}

	function setBusy(busy) {
		const sp = el('seojusai-il-spinner');
		if (sp) sp.style.display = busy ? 'inline-block' : 'none';
		const b1 = el('seojusai-il-scan');
		const b2 = el('seojusai-il-check');
		if (b1) b1.disabled = busy;
		if (b2) b2.disabled = busy;
	}

	function renderIssues(container, issues) {
		if (!container) return;
		container.innerHTML = '';
		if (!Array.isArray(issues) || issues.length === 0) {
			container.textContent = 'Проблем не знайдено.';
			return;
		}
		const ul = document.createElement('ul');
		ul.className = 'seojusai-issues';
		issues.forEach(function (it) {
			const li = document.createElement('li');
			const title = (it && it.title) ? String(it.title) : 'Проблема';
			const level = (it && it.level) ? String(it.level) : 'info';
			const tip = (it && it.tooltip) ? String(it.tooltip) : '';
			li.className = 'seojusai-issue seojusai-issue--' + level;
			li.textContent = title;
			if (tip) li.title = tip;
			ul.appendChild(li);
		});
		container.appendChild(ul);
	}

	async function scan() {
		const pid = parseInt((el('seojusai-il-post-id') || {}).value || '0', 10);
		const lim = parseInt((el('seojusai-il-limit') || {}).value || '50', 10);
		setBusy(true);
		try {
			const res = await wp.apiFetch({
				path: apiRoot + '/linking/scan',
				method: 'POST',
				data: { post_id: pid > 0 ? pid : 0, limit: lim }
			});
			if (!res || !res.ok) {
				showNotice('error', (res && res.error) ? res.error : 'Сталася помилка. Спробуйте ще раз.');
				return;
			}
			showNotice('success', 'Задачі поставлено у чергу: ' + String(res.queued || 0));
		} catch (e) {
			showNotice('error', 'Сталася помилка. Спробуйте ще раз.');
		} finally {
			setBusy(false);
		}
	}

	async function check() {
		const pid = parseInt((el('seojusai-il-post-id') || {}).value || '0', 10);
		if (!pid || pid <= 0) {
			showNotice('error', 'Вкажіть ID сторінки/запису для перегляду.');
			return;
		}
		setBusy(true);
		try {
			const res = await wp.apiFetch({
				path: apiRoot + '/page-audit-summary',
				method: 'POST',
				data: { post_id: pid }
			});
			if (!res || !res.ok) {
				showNotice('error', (res && res.error) ? res.error : 'Сталася помилка. Спробуйте ще раз.');
				return;
			}
			const box = el('seojusai-il-result');
			const cont = el('seojusai-il-issues');
			if (box) box.style.display = 'block';
			renderIssues(cont, res.issues || []);
			showNotice('success', 'Оцінка SEOJusAI: ' + String(res.score || 0));
		} catch (e) {
			showNotice('error', 'Сталася помилка. Спробуйте ще раз.');
		} finally {
			setBusy(false);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		const b1 = el('seojusai-il-scan');
		const b2 = el('seojusai-il-check');
		if (b1) b1.addEventListener('click', function (e) { e.preventDefault(); scan(); });
		if (b2) b2.addEventListener('click', function (e) { e.preventDefault(); check(); });
	});
})();