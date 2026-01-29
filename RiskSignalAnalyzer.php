<?php
declare(strict_types=1);

namespace SEOJusAI\AIRiskFunnel\Analyzer;

defined('ABSPATH') || exit;

final class RiskSignalAnalyzer {

    /** @return array{score:float,terms:array<string,array<int,string>>} */
    public function analyze(string $text): array {

        $t = mb_strtolower($text);
        $risk_terms = [
            'кримінальн', 'кк', 'кк україни', 'кримінальне проваджен', 'підозр', 'обвинувальн',
            'затриман', 'обшук', 'допит', 'слідч', 'прокурор', 'суд', 'запобіжн', 'ухвал', 'постанова',
            'повістк', 'повідомлення про підозру', 'єрдр', 'нсрд'
        ];
        $sanctions_terms = [
            'штраф', 'позбавлення волі', 'обмеження волі', 'арешт', 'конфіскац', 'судиміст', 'заборон',
            'застава', 'домашній арешт'
        ];
        $process_terms = [
            'статт', 'склад злочину', 'доказ', 'показан', 'експертиз', 'слідчі дії', 'протокол',
            'апеляці', 'касаці', 'скарга', 'клопотан'
        ];

        $hits = [
            'risk_terms' => $this->hits($t, $risk_terms),
            'sanctions_terms' => $this->hits($t, $sanctions_terms),
            'process_terms' => $this->hits($t, $process_terms),
        ];

        $risk = count($hits['risk_terms']);
        $sanctions = count($hits['sanctions_terms']);
        $proc = count($hits['process_terms']);

        // Optimal: достатньо сигналів ризику + хоча б 1 санкція або процес
        $score = 0.0;
        $score += min(1.0, $risk / 6.0) * 0.55;
        $score += min(1.0, $sanctions / 3.0) * 0.30;
        $score += min(1.0, $proc / 5.0) * 0.15;
        $score = max(0.0, min(1.0, $score));

        return [
            'score' => $score,
            'terms' => $hits,
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
