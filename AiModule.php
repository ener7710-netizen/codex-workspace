<?php
declare(strict_types=1);

namespace SEOJusAI\Modules;

use SEOJusAI\AI\AIKernel;
use SEOJusAI\AI\Integrations\GeminiRuntimeBridge;
use SEOJusAI\Core\Contracts\ModuleInterface;
use SEOJusAI\Core\Kernel;

defined('ABSPATH') || exit;

final class AiModule implements ModuleInterface {

	public function get_slug(): string {
		return 'ai';
	}

	public function register(Kernel $kernel): void {
		$kernel->register_module($this->get_slug(), $this);
	}

	public function init(Kernel $kernel): void {

		// Ключі/моделі для провайдерів (джерело істини — WP options).
		add_filter('seojusai/openai_key', static function ($value): string {
			$opt = (string) get_option('seojusai_openai_key', '');
			return $opt !== '' ? $opt : (string) $value;
		});

		add_filter('seojusai/openai_model', static function ($value): string {
			$opt = (string) get_option('seojusai_openai_model', 'gpt-4.1');
			return $opt !== '' ? $opt : (string) $value;
		});

		add_filter('seojusai/gemini_key', static function ($value): string {
			$opt = (string) get_option('seojusai_gemini_key', '');
			return $opt !== '' ? $opt : (string) $value;
		});

		add_filter('seojusai/gemini_model', static function ($value): string {
			$opt = (string) get_option('seojusai_gemini_model', 'models/gemini-1.5-pro');
			return $opt !== '' ? $opt : (string) $value;
		});

		if (class_exists(AIKernel::class)) {
			(new AIKernel())->register();
			GeminiRuntimeBridge::register();
		}
	}
}
