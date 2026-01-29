<?php
declare(strict_types=1);

namespace SEOJusAI\Analyze\Intent;

defined('ABSPATH') || exit;

/**
 * IntentClassifier
 * Евристичний класифікатор інтенцій для українських/російських запитів (legal/YMYL).
 * Повертає: informational | commercial | navigational | local | legal_action
 */
final class IntentClassifier {

	public function classify_query(string $query): string {
		$q = mb_strtolower(trim($query));
		if ($q === '') { return 'informational'; }

		if ($this->has_any($q, ['адвокат', 'юрист', 'ціна', 'вартість', 'замовити', 'послуги', 'консультація', 'скільки'])) {
			return 'commercial';
		}
		if ($this->has_any($q, ['як', 'що', 'чому', 'коли', 'зразок', 'інструкція', 'порядок', 'терміни'])) {
			return 'informational';
		}
		if ($this->has_any($q, ['поруч', 'на мапі', 'київ', 'львів', 'одеса', 'дніпро', 'харків', 'вінниця'])) {
			return 'local';
		}
		if ($this->has_any($q, ['суд', 'скарга', 'позов', 'заява', 'апеляція', 'клопотання', 'обшук', 'затримання', 'ст.'])) {
			return 'legal_action';
		}
		if ($this->has_any($q, ['jus.in.ua', 'jus', 'назва', 'контакти', 'телефон'])) {
			return 'navigational';
		}
		return 'informational';
	}

	/** @param string[] $needles */
	private function has_any(string $q, array $needles): bool {
		foreach ($needles as $n) {
			if (mb_strpos($q, mb_strtolower($n)) !== false) { return true; }
		}
		return false;
	}
}
