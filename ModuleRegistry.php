<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

use SEOJusAI\Core\I18n;

defined('ABSPATH') || exit;

/**
 * Реєстр модулів системи.
 * Керує станом активації окремих компонентів.
 */
final class ModuleRegistry {

	private const OPTION_KEY = 'seojusai_modules';

	private static ?self $instance = null;

	/** @var array<string, array<string, mixed>> */
	private array $state = [];

	private function __construct() {
		$this->load();
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/* ==========================================================
	 * DEFAULT MODULE DEFINITIONS (UI + STATE)
	 * ========================================================== */

	public function defaults(): array {
		return [
			'background' => [
				'label'       => I18n::t('Фонові задачі (Action Scheduler)'),
				'description' => I18n::t('Виконує важкі SEO-задачі у фоні (черга + планувальник).'),
				'order'       => 12,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'vectors' => [
				'label'       => I18n::t('Векторна пам\'ять (Embeddings)'),
				'description' => I18n::t('Семантичний пошук по знаннях сайту: embeddings + векторне сховище.'),
				'order'       => 13,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'learning' => [
				'label'       => I18n::t('Самонавчання'),
				'description' => I18n::t('Калібрування пріоритетів (Opportunity) за фактичними результатами.'),
				'order'       => 14,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],

			'lead_funnel' => [
				'label'       => I18n::t('Lead Funnel (Юридичні звернення)'),
				'description' => I18n::t('Рекомендації CTA та конверсій для сторінок. Без автозмін.'),
				'order'       => 18,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'experiments' => [
				'label'       => I18n::t('Експерименти (A/B)'),
				'description' => I18n::t('Безпечні A/B експерименти (UI-шар) для CTA та конверсій.'),
				'order'       => 19,
				'enabled'     => false,
				'locked'      => false,
				'reason'      => '',
			],
			'ai' => [
				'label'       => I18n::t('AI-ядро'),
				'description' => I18n::t('Центральний модуль аналізу сайту та сторінок.'),
				'order'       => 10,
				'enabled'     => true,
				'locked'      => true,
				'reason'      => I18n::t('Системний модуль'),
			],

			'schema' => [
				'label'            => I18n::t('Schema & Microdata'),
				'description'      => I18n::t('Генерація та перевірка Schema.org (організація, статті, FAQ, breadcrumbs).'),
				'long_description' => I18n::t('Формує структуровані дані для сторінок, підсилює розмітку для пошукових сніпетів. Працює обережно: спочатку аналіз, потім пропозиція змін.'),
				'order'            => 11,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],
			'autopilot' => [
				'label'            => I18n::t('SEO Autopilot'),
				'description'      => I18n::t('Пайплайн рекомендацій, черга задач і контроль “людина в контурі”.'),
				'long_description' => I18n::t('Збирає сигнали (контент/структура/GSC/SERP), будує план дій та пропонує правки. Нічого не застосовує без підтвердження.'),
				'order'            => 15,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],
			'task_state' => [
				'label'            => I18n::t('Task State & Журнал'),
				'description'      => I18n::t('Стан задач, історія виконання, відкладені дії та повтори.'),
				'long_description' => I18n::t('Зберігає статуси аудитів/рекомендацій/застосувань. Допомагає відстежити, що робилось, коли і чому.'),
				'order'            => 16,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],

			'snapshots' => [
				'label'       => I18n::t('Знімки стану сайту'),
				'description' => I18n::t('Автоматичне збереження контенту перед змінами ШІ.'),
				'order'       => 20,
				'enabled'     => true,
				'locked'      => true,
				'reason'      => I18n::t('Захист даних'),
			],
			'kbe' => [
				'label'       => I18n::t('База знань (KBE)'),
				'description' => I18n::t('Навчання ШІ на професійному досвіді юриста.'),
				'order'       => 30,
				'enabled'     => true,
				'locked'      => true,
				'reason'      => I18n::t('Системний модуль'),
			],
			'gsc' => [
				'label'       => I18n::t('Google Search Console'),
				'description' => I18n::t('Інтеграція реальних запитів та кліків з Google.'),
				'order'       => 40,
				'enabled'     => false,
				'locked'      => false,
				'reason'      => '',
			],
			'serp' => [
				'label'       => I18n::t('Аналіз конкурентів (SERP)'),
				'description' => I18n::t('Порівняння контенту з ТОП результатами.'),
				'order'       => 50,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'structure' => [
				'label'       => I18n::t('Структура та URL'),
				'description' => I18n::t('Архітектура сайту та ЧПУ.'),
				'order'       => 60,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'eeat' => [
				'label'       => I18n::t('E-E-A-T аудитор'),
				'description' => I18n::t('Фактори довіри та експертності.'),
				'order'       => 70,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'lsi' => [
				'label'       => I18n::t('LSI семантика'),
				'description' => I18n::t('Семантичні прогалини.'),
				'order'       => 80,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'linking' => [
				'label'       => I18n::t('Авто-перелінковка'),
				'description' => I18n::t('Розумні внутрішні посилання.'),
				'order'       => 90,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'new_pages' => [
				'label'       => I18n::t('Генератор сторінок'),
				'description' => I18n::t('Створення нових сторінок.'),
				'order'       => 100,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],

			'meta' => [
				'label'       => I18n::t('Meta та Snippet'),
				'description' => I18n::t('Керування Title/Description/Canonical/Robots та OpenGraph.'),
				'order'       => 75,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'content_score' => [
				'label'       => I18n::t('Оцінка контенту'),
				'description' => I18n::t('Правила якості контенту та підказки для покращення.'),
				'order'       => 76,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'sitemap' => [
				'label'       => I18n::t('XML Sitemap'),
				'description' => I18n::t('Генерація мапи сайту без сторонніх плагінів.'),
				'order'       => 77,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],
			'redirects' => [
				'label'       => I18n::t('Редиректи та 404'),
				'description' => I18n::t('Керування редиректами та журнал 404.'),
				'order'       => 78,
				'enabled'     => true,
				'locked'      => false,
				'reason'      => '',
			],

			'breadcrumbs' => [
				'label'            => I18n::t('Breadcrumbs'),
				'description'      => I18n::t('Хлібні крихти: навігація + розмітка для пошуку.'),
				'long_description' => I18n::t('Генерує крихти у темі/шаблонах та підсилює SEO через розмітку BreadcrumbList.'),
				'order'            => 60,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],
			'robots' => [
				'label'            => I18n::t('Robots & Index Control'),
				'description'      => I18n::t('Керування індексацією: meta robots, noindex, правила та перевірки.'),
				'long_description' => I18n::t('Допомагає уникнути індексації технічних/дубльованих сторінок, контролює директиви та конфлікти.'),
				'order'            => 61,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],
			'bulk' => [
				'label'            => I18n::t('Bulk / Масові операції'),
				'description'      => I18n::t('Масовий аудит, застосування та відкат через знімки.'),
				'long_description' => I18n::t('Дозволяє запускати аудит на групі сторінок, формувати список правок, застосовувати та відкатувати зміни. SafeMode захищає від ризиків.'),
				'order'            => 70,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],
			'ai_risk_funnel' => [
				'label'            => I18n::t('AI Risk Funnel'),
				'description'      => I18n::t('Оцінка ризику змін: ймовірність просідання та сценарії відкату.'),
				'long_description' => I18n::t('Класифікує рекомендації за ризиком, підказує безпечні кроки, інтегрується зі Snapshots/SafeMode.'),
				'order'            => 71,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],
			'case_learning' => [
				'label'            => I18n::t('Case Learning'),
				'description'      => I18n::t('Навчання на кейсах: що спрацювало/не спрацювало в минулому.'),
				'long_description' => I18n::t('Збирає результати змін та пов’язує їх з типами сторінок/темами. Допомагає точніше прогнозувати Opportunity.'),
				'order'            => 72,
				'enabled'          => true,
				'locked'           => false,
				'reason'           => '',
			],

];
	}

	/* ==========================================================
	 * PUBLIC API
	 * ========================================================== */

	public function all(): array {
		$out = [];
		$defaults = $this->defaults();

		foreach ($defaults as $slug => $def) {
			$saved = $this->state[$slug] ?? [];

			$out[$slug] = [
				'label'       => (string) $def['label'],
				'description' => (string) $def['description'],
				'order'       => (int) $def['order'],
				'enabled'     => (bool) (
					$def['locked']
						? $def['enabled']
						: ($saved['enabled'] ?? $def['enabled'])
				),
				'locked'      => (bool) $def['locked'],
				'reason'      => (string) $def['reason'],
			];
		}

		uasort($out, static fn($a, $b) => $a['order'] <=> $b['order']);
		return $out;
	}

	public function is_enabled(string $slug): bool {
		$slug = sanitize_key($slug);
		$all = $this->all();
		return !empty($all[$slug]['enabled']);
	}

	public function can_init(string $slug): bool {
		$slug = sanitize_key($slug);
		if (!$this->is_enabled($slug)) {
			return false;
		}

		// Сумісність з іншими SEO-плагінами: не дублюємо frontend emitting
		if (class_exists('SEOJusAI\\Compat\\SeoEnvironmentDetector') && \SEOJusAI\Compat\SeoEnvironmentDetector::should_disable_frontend_emitting()) {
			$frontend_modules = ['meta','schema','sitemap','breadcrumbs','robots','redirects'];
			if (in_array($slug, $frontend_modules, true)) {
				return false;
			}
		}
		return true;
	}

	public function set_enabled(string $slug, bool $enabled): bool {
		$slug = sanitize_key($slug);
		$defaults = $this->defaults();

		if (!isset($defaults[$slug]) || !empty($defaults[$slug]['locked'])) {
			return false;
		}

		$this->state[$slug]['enabled'] = $enabled;
		$this->persist();
		return true;
	}

	/* ==========================================================
	 * STORAGE
	 * ========================================================== */

	private function load(): void {
		$data = get_option(self::OPTION_KEY, []);
		$this->state = is_array($data) ? $data : [];
	}

	private function persist(): void {
		update_option(self::OPTION_KEY, $this->state, false);
	}
}
