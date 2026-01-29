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
		const box = el('seojusai-schema-notice');
		if (!box) return;
		box.style.display = 'block';
		box.className = 'seojusai-notice seojusai-notice--' + type;
		box.textContent = message;
	}

	function setBusy(busy) {
		const sp = el('seojusai-schema-spinner');
		if (sp) sp.style.display = busy ? 'inline-block' : 'none';
		const btn1 = el('seojusai-schema-preview');
		const btn2 = el('seojusai-schema-apply');
		if (btn1) btn1.disabled = busy;
		if (btn2) btn2.disabled = busy;
	}

	function parseJsonSafe(str) {
		try { return { ok: true, data: JSON.parse(str) }; } catch (e) { return { ok: false }; }
	}

	async function preview() {
		const pid = parseInt((el('seojusai-schema-post-id') || {}).value || '0', 10);
		const raw = (el('seojusai-schema-json') || {}).value || '';
		if (!pid || pid <= 0) {
			showNotice('error', 'Вкажіть коректний ID сторінки/запису.');
			return;
		}
		const parsed = parseJsonSafe(raw);
		if (!parsed.ok) {
			showNotice('error', 'Schema має бути валідним JSON.');
			return;
		}
		setBusy(true);
		try {
			const res = await wp.apiFetch({
				path: apiRoot + '/schema/preview',
				method: 'POST',
				data: { post_id: pid, schema: parsed.data }
			});
			if (!res || !res.ok) {
				showNotice('error', (res && res.error) ? res.error : 'Сталася помилка. Спробуйте ще раз.');
				return;
			}
			showNotice('success', 'Перевірка пройдена. Схема валідна.');
			const pre = el('seojusai-schema-result-pre');
			const box = el('seojusai-schema-result');
			if (pre && box) {
				box.style.display = 'block';
				pre.textContent = JSON.stringify(res.schema, null, 2);
			}
		} catch (e) {
			showNotice('error', 'Сталася помилка. Спробуйте ще раз.');
		} finally {
			setBusy(false);
		}
	}

	async function apply() {
		const pid = parseInt((el('seojusai-schema-post-id') || {}).value || '0', 10);
		const raw = (el('seojusai-schema-json') || {}).value || '';
		if (!pid || pid <= 0) {
			showNotice('error', 'Вкажіть коректний ID сторінки/запису.');
			return;
		}
		const parsed = parseJsonSafe(raw);
		if (!parsed.ok) {
			showNotice('error', 'Schema має бути валідним JSON.');
			return;
		}
		setBusy(true);
		try {
			const res = await wp.apiFetch({
				path: apiRoot + '/schema/apply',
				method: 'POST',
				data: { post_id: pid, schema: parsed.data }
			});
			if (!res || !res.ok) {
				showNotice('error', (res && res.error) ? res.error : 'Сталася помилка. Спробуйте ще раз.');
				return;
			}
			showNotice('success', 'Схему застосовано.');
		} catch (e) {
			showNotice('error', 'Сталася помилка. Спробуйте ще раз.');
		} finally {
			setBusy(false);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		const b1 = el('seojusai-schema-preview');
		const b2 = el('seojusai-schema-apply');
		if (b1) b1.addEventListener('click', function (e) { e.preventDefault(); preview(); });
		if (b2) b2.addEventListener('click', function (e) { e.preventDefault(); apply(); });
	});
})();