/**
 * SEOJusAI — Gutenberg Sidebar (Issues + AI Chat)
 * UI: українська мова
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.apiFetch || !wp.data) {
		return;
	}

	if (typeof SEOJusAIEditor === 'undefined' || !SEOJusAIEditor || !SEOJusAIEditor.nonce) {
		return;
	}

	var apiFetch = wp.apiFetch;
	apiFetch.use(wp.apiFetch.createNonceMiddleware(SEOJusAIEditor.nonce));

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;

	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var Spinner = wp.components.Spinner;
	var TabPanel = wp.components.TabPanel;
	var TextareaControl = wp.components.TextareaControl;
	var Flex = wp.components.Flex;
	var FlexItem = wp.components.FlexItem;
	var Badge = wp.components.__experimentalBadge || wp.components.Badge;

	var i18n = (SEOJusAIEditor && SEOJusAIEditor.i18n) ? SEOJusAIEditor.i18n : {};
	var T = function (key, fallback) {
		return (i18n && i18n[key]) ? i18n[key] : (fallback || key);
	};

	var getEditor = function () {
		return wp.data.select('core/editor');
	};

	var getPostId = function () {
		var editor = getEditor();
		return editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
	};

	var getPostMeta = function () {
		var editor = getEditor();
		return editor && editor.getEditedPostAttribute ? (editor.getEditedPostAttribute('meta') || {}) : {};
	};

	var formatTime = function (unixSeconds) {
		if (!unixSeconds || unixSeconds <= 0) {
			return '';
		}
		try {
			var d = new Date(unixSeconds * 1000);
			return d.toLocaleString();
		} catch (e) {
			return '';
		}
	};

	var LevelBadge = function (props) {
		var level = props.level || '';
		var text = level === 'critical' ? 'КРИТ' : (level === 'warning' ? 'УВАГА' : 'ІНФО');

		if (Badge) {
			return el(Badge, { className: 'seojusai-badge seojusai-badge-' + level }, text);
		}
		return el('span', { className: 'seojusai-badge seojusai-badge-' + level }, text);
	};

	var IssuesTab = function () {
		var postId = getPostId();

		var meta = getPostMeta();
		var initialScore = meta && typeof meta._seojusai_score !== 'undefined' ? parseInt(meta._seojusai_score, 10) : null;
		var initialUpdated = meta && meta._seojusai_score_updated ? meta._seojusai_score_updated : '';

		var [busy, setBusy] = useState(false);
		var [error, setError] = useState('');
		var [notice, setNotice] = useState('');
		var [summary, setSummary] = useState(null);
		var [score, setScore] = useState(isNaN(initialScore) ? null : initialScore);
		var [updatedAt, setUpdatedAt] = useState(initialUpdated ? parseInt(initialUpdated, 10) : 0);

		var [dirty, setDirty] = useState(false);
		var [dirtyHint, setDirtyHint] = useState('');

		var loadSummary = function () {
			if (!postId) {
				setError(T('errorGeneric', 'Сталася помилка. Спробуйте ще раз.'));
				return;
			}

			setBusy(true);
			setError('');

			apiFetch({
				path: '/seojusai/v1/page-audit-summary',
				method: 'POST',
				data: { post_id: postId }
			}).then(function (res) {
				if (!res || res.ok === false) {
					throw new Error((res && res.error) ? res.error : 'request_failed');
				}

				setSummary(res);

				if (typeof res.score !== 'undefined') {
					var s = parseInt(res.score, 10);
					if (!isNaN(s)) {
						setScore(s);
					}
				}

				if (typeof res.updated_at !== 'undefined') {
					var u = parseInt(res.updated_at, 10);
					if (!isNaN(u)) {
						setUpdatedAt(u);
					}
				}

				// Синхронізуємо meta в редакторі (якщо show_in_rest ввімкнено)
				try {
					var dispatch = wp.data.dispatch('core/editor');
					if (dispatch && dispatch.editPost && typeof res.score !== 'undefined') {
						dispatch.editPost({
							meta: {
								_seojusai_score: parseInt(res.score, 10),
								_seojusai_score_updated: String(Math.floor(Date.now() / 1000))
							}
						});
					}
				} catch (e) {}
			}).catch(function (e) {
				setError(e && e.message ? e.message : T('errorGeneric', 'Сталася помилка. Спробуйте ще раз.'));
			}).finally(function () {
				setBusy(false);
			});
		};

		var forceRefresh = function () {
			if (!postId) {
				setError(T('errorGeneric', 'Сталася помилка. Спробуйте ще раз.'));
				return;
			}

			setBusy(true);
			setError('');

			apiFetch({
				path: '/seojusai/v1/page-audit-summary',
				method: 'POST',
				data: { post_id: postId, force: true }
			}).then(function (res) {
				if (!res || res.ok === false) {
					throw new Error((res && res.error) ? res.error : 'request_failed');
				}

				setSummary(res);
				if (res && res.enqueued) {
					setNotice(T('enqueued', 'Запит прийнято. Перевірка виконується у фоні.'));
				}

				if (typeof res.score !== 'undefined') {
					var s = parseInt(res.score, 10);
					if (!isNaN(s)) {
						setScore(s);
					}
				}

				if (typeof res.updated_at !== 'undefined') {
					var u = parseInt(res.updated_at, 10);
					if (!isNaN(u)) {
						setUpdatedAt(u);
					}
				}

				// Синхронізуємо meta в редакторі (якщо show_in_rest ввімкнено)
				try {
					var dispatch = wp.data.dispatch('core/editor');
					if (dispatch && dispatch.editPost && typeof res.score !== 'undefined') {
						dispatch.editPost({
							meta: {
								_seojusai_score: parseInt(res.score, 10),
								_seojusai_score_updated: String(Math.floor(Date.now() / 1000))
							}
						});
					}
				} catch (e) {}
			}).catch(function (e) {
				setError(e && e.message ? e.message : T('errorGeneric', 'Сталася помилка. Спробуйте ще раз.'));
			}).finally(function () {
				setBusy(false);
			});
		};

		useEffect(function () {
			// Автозавантаження після відкриття редактора
			loadSummary();
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [postId]);


		useEffect(function () {
			var unsubscribe = null;
			try {
				var last = '';
				unsubscribe = wp.data.subscribe(function () {
					var editor = getEditor();
					if (!editor || !editor.isEditedPostDirty) return;
					var isDirty = !!editor.isEditedPostDirty();
					if (isDirty !== dirty) {
						setDirty(isDirty);
						if (isDirty) {
							setDirtyHint(T('dirtyHint', 'Є незбережені зміни. Для актуальної перевірки збережіть запис або натисніть «Оновити перевірку» після збереження.'));
						} else {
							setDirtyHint('');
						}
					}
				});
			} catch (e) {
				// no-op
			}
			return function () {
				if (typeof unsubscribe === 'function') {
					unsubscribe();
				}
			};
		// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [postId, dirty]);


		var counts = summary && summary.counts ? summary.counts : { critical: 0, warning: 0, info: 0, total: 0 };
		var issues = summary && Array.isArray(summary.issues) ? summary.issues : [];

		var scoreLabel = useMemo(function () {
			if (score === null || isNaN(score)) {
				return '—';
			}
			var s = Math.max(0, Math.min(100, parseInt(score, 10)));
			return String(s);
		}, [score]);

		return el(Fragment, {},
			error ? el(Notice, { status: 'error', isDismissible: false }, error) : null,
			notice ? el(Notice, { status: 'info', isDismissible: false }, notice) : null,
			dirtyHint ? el(Notice, { status: 'warning', isDismissible: false }, dirtyHint) : null,

			el(PanelBody, { title: T('issuesTab', 'Проблеми'), initialOpen: true },
				el(Flex, { justify: 'space-between', align: 'center' },
					el(FlexItem, {},
						el('div', { style: { fontSize: '12px', opacity: 0.9 } }, T('scoreLabel', 'Оцінка SEOJusAI')),
						el('div', { style: { fontSize: '26px', fontWeight: 700, lineHeight: '30px' } }, scoreLabel)
					),
					el(FlexItem, {},
						el(Button, { variant: 'secondary', onClick: forceRefresh, disabled: busy }, busy ? T('computing', 'Виконується перевірка…') : T('refresh', 'Оновити перевірку'))
					)
				),

				el('div', { style: { marginTop: '10px' } },
					el('div', { style: { display: 'flex', gap: '8px', flexWrap: 'wrap' } },
						el('span', {}, el(LevelBadge, { level: 'critical' }), ' ', String(counts.critical || 0)),
						el('span', {}, el(LevelBadge, { level: 'warning' }), ' ', String(counts.warning || 0)),
						el('span', {}, el(LevelBadge, { level: 'info' }), ' ', String(counts.info || 0))
					),
					el('div', { style: { marginTop: '6px', fontSize: '12px', opacity: 0.85 } },
						T('lastUpdate', 'Останнє оновлення') + ': ' + (updatedAt > 0 ? formatTime(updatedAt) : '—')
					)
				),

				el('hr', { style: { margin: '12px 0' } }),

				busy ? el('div', {}, el(Spinner, {}), ' ', T('computing', 'Виконується перевірка…')) : null,

				issues.length === 0 && !busy
					? el('p', { style: { fontSize: '13px' } }, T('noIssues', 'Проблем не знайдено.'))
					: el('ul', { style: { marginLeft: '18px', fontSize: '13px' } },
						issues.map(function (it, idx) {
							var lvl = it && it.level ? String(it.level) : 'info';
							var msg = it && it.message ? String(it.message) : '';
							var code = it && it.code ? String(it.code) : '';

							return el('li', { key: idx, style: { marginBottom: '8px' } },
								el('div', { style: { display: 'flex', gap: '8px', alignItems: 'baseline' } },
									el(LevelBadge, { level: lvl }),
									el('div', {},
										el('div', { style: { fontWeight: 600 } }, msg),
										code ? el('div', { style: { fontSize: '12px', opacity: 0.75 } }, code) : null
									)
								)
							);
						})
					)
			)
		);
	};

	var ChatTab = function () {
		var postId = getPostId();

		var [busy, setBusy] = useState(false);
		var [error, setError] = useState('');
		var [notice, setNotice] = useState('');
		var [message, setMessage] = useState('');
		var [history, setHistory] = useState([]);

		var append = function (role, text) {
			var item = {
				role: role,
				text: String(text || ''),
				at: Math.floor(Date.now() / 1000)
			};
			setHistory(function (prev) {
				var next = prev.concat([item]);
				if (next.length > 50) {
					next = next.slice(next.length - 50);
				}
				return next;
			});
		};

		var send = function () {
			if (!postId) {
				setError(T('errorGeneric', 'Сталася помилка. Спробуйте ще раз.'));
				return;
			}

			var msg = (message || '').trim();
			if (!msg) {
				setError(T('emptyMessage', 'Введіть повідомлення.'));
				return;
			}

			setBusy(true);
			setError('');

			append('user', msg);

			apiFetch({
				path: '/seojusai/v1/chat',
				method: 'POST',
				data: {
					post_id: postId,
					message: msg,
					is_learning: false
				}
			}).then(function (res) {
				if (!res || res.ok === false) {
					throw new Error((res && res.reply) ? res.reply : 'chat_failed');
				}
				append('assistant', res.reply || '');
				setMessage('');
			}).catch(function (e) {
				setError(e && e.message ? e.message : T('errorGeneric', 'Сталася помилка. Спробуйте ще раз.'));
			}).finally(function () {
				setBusy(false);
			});
		};

		return el(Fragment, {},
			error ? el(Notice, { status: 'error', isDismissible: true, onRemove: function () { setError(''); } }, error) : null,

			el(PanelBody, { title: T('chatTab', 'Чат з ІІ'), initialOpen: true },
				el('div', { style: { maxHeight: '240px', overflowY: 'auto', padding: '8px 0' } },
					history.length === 0
						? el('p', { style: { fontSize: '13px', opacity: 0.85 } }, T('messageHelp', 'Питайте про SEO, структуру, Schema.org та покращення для цієї сторінки.'))
						: el('div', {},
							history.map(function (h, idx) {
								var isUser = h.role === 'user';
								return el('div', {
									key: idx,
									style: {
										marginBottom: '10px',
										padding: '8px 10px',
										borderRadius: '8px',
										background: isUser ? '#f0f6ff' : '#f6f7f7'
									}
								},
									el('div', { style: { fontSize: '12px', opacity: 0.75, marginBottom: '4px' } }, isUser ? 'Ви' : 'SEOJusAI'),
									el('div', { style: { whiteSpace: 'pre-wrap', fontSize: '13px' } }, h.text)
								);
							})
						)
				),

				el(TextareaControl, {
					label: T('messageLabel', 'Повідомлення'),
					value: message,
					onChange: function (v) { setMessage(v); },
					help: '',
					rows: 3
				}),

				el(Flex, { justify: 'flex-end', align: 'center' },
					el(FlexItem, {},
						el(Button, { variant: 'primary', onClick: send, disabled: busy }, busy ? T('loadingChat', 'Завантаження…') : T('send', 'Надіслати'))
					)
				)
			)
		);
	};

	
// Post-release calibration (stability monitor)
var CalibrationPanel = function () {
	var postId = getPostId();
	var stateInit = { loading: true, stable: null, baseline: null, error: false };
	var _state = useState(stateInit);
	var state = _state[0];
	var setState = _state[1];

	useEffect(function () {
		var cancelled = false;
		setState({ loading: true, stable: null, baseline: null, error: false });

		apiFetch({ path: '/seojusai/v1/calibration/status/' + String(postId) })
			.then(function (data) {
				if (cancelled) return;
				setState({
					loading: false,
					stable: data && data.stable === true,
					baseline: data && data.baseline ? data.baseline : null,
					error: false
				});
			})
			.catch(function () {
				if (cancelled) return;
				setState({ loading: false, stable: null, baseline: null, error: true });
			});

		return function () { cancelled = true; };
	}, [postId]);

	var badge = T('calibUnknown', 'Невідомо');
	var badgeClass = 'seojusai-badge seojusai-badge--neutral';

	if (state.loading) {
		badge = T('computing', 'Виконується перевірка…');
	} else if (state.error) {
		badge = T('errorGeneric', 'Сталася помилка. Спробуйте ще раз.');
		badgeClass = 'seojusai-badge seojusai-badge--bad';
	} else if (state.stable === true) {
		badge = '🟢 ' + T('calibStable', 'Стабільна');
		badgeClass = 'seojusai-badge seojusai-badge--good';
	} else {
		badge = '🟡 ' + T('calibCalibrating', 'Калібрується');
		badgeClass = 'seojusai-badge seojusai-badge--warn';
	}

	return el('div', { className: 'seojusai-card seojusai-card--tight' },
		el('div', { className: 'seojusai-card__header' },
			el('strong', {}, T('calibrationTitle', 'Післярелізна стабільність')),
			el('span', { className: badgeClass }, badge)
		),
		el('div', { className: 'seojusai-card__body' },
			el('div', { className: 'seojusai-muted' }, T('calibNote', 'Під час калібрування критичні зміни можуть бути обмежені політиками безпеки.'))
		)
	);
};

var SidebarContent = function () {
		return el(Fragment, {},
			el(CalibrationPanel, {}),
			el(TabPanel, {
			className: 'seojusai-sidebar-tabs',
			activeClass: 'is-active',
			tabs: [
				{ name: 'issues', title: T('issuesTab', 'Проблеми') },
				{ name: 'chat', title: T('chatTab', 'Чат з ІІ') }
			]
		}, function (tab) {
			if (tab.name === 'chat') {
				return el(ChatTab, {});
			}
			return el(IssuesTab, {});
		})
		);
	};

	registerPlugin('seojusai-sidebar', {
		icon: el('span', { className: 'dashicons dashicons-chart-area', style: { fontSize: '20px' } }),
		render: function () {
			return el(Fragment, {},
				el(PluginSidebarMoreMenuItem, { target: 'seojusai-sidebar' }, T('title', 'SEOJusAI')),
				el(PluginSidebar, { name: 'seojusai-sidebar', title: T('title', 'SEOJusAI') }, el(SidebarContent, {}))
			);
		}
	});
})(window.wp);
