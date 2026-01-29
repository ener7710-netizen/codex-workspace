<?php
declare(strict_types=1);

namespace SEOJusAI\Core;

use SEOJusAI\Core\EmergencyStop;
use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Strategy\StrategicLoop;

defined('ABSPATH') || exit;

final class Kernel {

	private static ?self $instance = null;

	/** @var array<string,object> */
	private array $modules = [];

	/** @var array<int,array{event:string,payload:array}> */
	private array $event_queue = [];

	private ?StrategicLoop $strategic_loop = null;

	private bool $booted = false;
	private bool $processing = false;

	private ModuleRegistry $registry;

	private function __construct() {
		$this->registry = ModuleRegistry::instance();
	}

	public static function instance(): self {
		if (self::$instance instanceof self) {
			return self::$instance;
		}

		self::$instance = new self();
		self::$instance->boot();
		return self::$instance;
	}

	private function boot(): void {

		// Permanent strategic runtime component (inert placeholder).
		// Exists to define where future strategic loop logic will live.
		$this->strategic_loop = new StrategicLoop();

		if ($this->booted) {
			return;
		}
		$this->booted = true;

		/**
		 * РЕЄСТРАЦІЯ МОДУЛІВ
		 */
		do_action('seojusai/kernel/register_modules', $this);

		/**
		 * ІНІЦІАЛІЗАЦІЯ ДОЗВОЛЕНИХ
		 */
		$this->init_modules();

		do_action('seojusai/kernel/booted', $this);
	}

	/* ==========================================================
	 * MODULES
	 * ========================================================== */

	public function register_module(string $slug, object $module): void {
		$slug = sanitize_key($slug);
		if ($slug === '' || isset($this->modules[$slug])) {
			return;
		}
		$this->modules[$slug] = $module;
	}

	private function init_modules(): void {

		foreach ($this->modules as $slug => $module) {

			if (!$this->registry->can_init($slug)) {
				continue;
			}

			if (EmergencyStop::is_active()) {
				continue;
			}

			try {

				// 🆕 Новий контракт
				if ($module instanceof ModuleInterface) {
					$module->init($this);
					continue;
				}

				// 🧓 Legacy
				if (method_exists($module, 'init')) {
					$module->init($this);
				}

			} catch (\Throwable $e) {
				do_action(
					'seojusai/kernel/module_error',
					$slug,
					$e->getMessage()
				);
			}
		}
	}

	/* ==========================================================
	 * EVENTS
	 * ========================================================== */

	public function dispatch(string $event, array $payload = []): void {

		if (EmergencyStop::is_active()) {
			return;
		}

		$event = trim($event);
		if ($event === '') {
			return;
		}

		$this->event_queue[] = [
			'event'   => $event,
			'payload' => $payload,
		];
	}

	public function process_queue(): void {

		if ($this->processing || EmergencyStop::is_active()) {
			return;
		}

		$this->processing = true;

		try {
			while ($item = array_shift($this->event_queue)) {

				$event = trim((string) ($item['event'] ?? ''));
				if ($event === '') {
					continue;
				}

				$hook = sanitize_key(str_replace(['.', '/'], '_', $event));

				do_action(
					'seojusai/event/' . $hook,
					(array) ($item['payload'] ?? []),
					$this
				);
			}
		} finally {
			$this->processing = false;
		}
	}

	/* ==========================================================
	 * DEBUG
	 * ========================================================== */

	public function get_registered_modules(): array {
		return array_keys($this->modules);
	}
}
