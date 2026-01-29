<?php
declare(strict_types=1);

namespace SEOJusAI\AIRiskFunnel\Analyzer;

defined('ABSPATH') || exit;

final class DecisionGapAnalyzer {

    /** @return array{score:float,terms:array<int,string>} */
    public function analyze(string $text): array {

        $t = mb_strtolower($text);

        // Optimal: без прямого CTA, але з логікою "самостійно складно"
        $terms = [
            'без адвоката', 'без захисника', 'правова допомога', 'професійна допомога',
            'самостійно складно', 'краще не робити самостійно', 'ризиковано без досвіду',
            'часто помиляються', 'практика неоднозначна', 'потрібна стратегія захисту',
            'важливо узгодити позицію', 'не давайте показання без', 'не підписуйте без',
        ];

        $found = $this->hits($t, $terms);

        $score = min(1.0, count($found) / 5.0);

        // penalty за надмірний продаж
        $sales = ['найкращий адвокат', 'гарантуємо', '100%', 'акція', 'знижка', 'замовте'];
        foreach ($sales as $s) {
            if (mb_strpos($t, $s) !== false) {
                $score = max(0.0, $score - 0.20);
                break;
            }
        }

        return [
            'score' => $score,
            'terms' => $found,
        ];
    }

    /** @param array<int,string> $terms @return array<int,string> */
    private function hits(string $text, array $terms): array {
        $found = [];
        foreach ($terms as $term) {
            if ($term === '') continue;
            if (mb_strpos($text, $term) !== false) {
                $found[] = $term;
            }
        }
        return array_values(array_unique($found));
    }
}
