<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

use SEOJusAI\Audit\AuditLogger;
use SEOJusAI\Repository\DecisionItemRepository;

defined('ABSPATH') || exit;

/**
 * Lightweight self-training model.
 * Learns keyword weights per label from human-approved decisions (status=executed).
 * Stores a simple log-odds bag-of-words model in wp_options.
 */
final class SelfTrainingService {

    public static function train(string $taxonomy, int $limit = 500): array {
        $taxonomy = sanitize_key($taxonomy);
        $limit = max(50, min(2000, $limit));

        $samples = DecisionItemRepository::get_approved_samples($taxonomy, $limit);
        if (empty($samples)) {
            return ['ok' => false, 'reason' => 'no_samples'];
        }

        // Build label priors (counts)
        $label_counts = [];
        foreach ($samples as $s) {
            $label = (string)($s['label'] ?? '');
            if ($label === '') continue;
            $label_counts[$label] = ($label_counts[$label] ?? 0) + 1;
        }
        if (!$label_counts) return ['ok' => false, 'reason' => 'no_labels'];

        // Token counts per label
        $token_counts = []; // [label][token] => count
        $total_tokens = []; // [label] => total
        foreach ($samples as $s) {
            $post_id = (int)($s['post_id'] ?? 0);
            $label = (string)($s['label'] ?? '');
            if ($post_id <= 0 || $label === '') continue;

            $post = get_post($post_id);
            if (!$post) continue;

            $text = strtolower(trim((string)$post->post_title . " " . wp_strip_all_tags((string)$post->post_content)));
            $tokens = self::tokenize($text);

            foreach ($tokens as $t) {
                if ($t === '') continue;
                $token_counts[$label][$t] = ($token_counts[$label][$t] ?? 0) + 1;
                $total_tokens[$label] = ($total_tokens[$label] ?? 0) + 1;
            }
        }

        // Compute log-odds weights for each label/token with Laplace smoothing.
        $labels = array_keys($label_counts);
        $vocab = [];
        foreach ($token_counts as $lbl => $map) {
            foreach (array_keys($map) as $tok) $vocab[$tok] = true;
        }
        $vocab_size = count($vocab);
        if ($vocab_size === 0) return ['ok' => false, 'reason' => 'empty_vocab'];

        $weights = []; // [label][token] => weight
        foreach ($labels as $lbl) {
            $weights[$lbl] = [];
        }

        foreach (array_keys($vocab) as $tok) {
            foreach ($labels as $lbl) {
                $c = (int)($token_counts[$lbl][$tok] ?? 0);
                $tot = (int)($total_tokens[$lbl] ?? 0);
                $p = ($c + 1.0) / ($tot + $vocab_size); // smoothed
                // Compare against average probability across other labels
                $other_c = 0;
                $other_tot = 0;
                foreach ($labels as $lbl2) {
                    if ($lbl2 === $lbl) continue;
                    $other_c += (int)($token_counts[$lbl2][$tok] ?? 0);
                    $other_tot += (int)($total_tokens[$lbl2] ?? 0);
                }
                $q = ($other_c + 1.0) / ($other_tot + $vocab_size);

                $w = log($p / $q);
                // Keep only informative weights
                if (abs($w) >= 0.6) {
                    $weights[$lbl][$tok] = $w;
                }
            }
        }

        $model = [
            'taxonomy' => $taxonomy,
            'labels' => $labels,
            'label_counts' => $label_counts,
            'weights' => $weights,
            'trained_at' => time(),
            'samples' => count($samples),
        ];

        update_option('seojusai_st_model_' . $taxonomy, $model, false);

        AuditLogger::log(
            sha1('self_train|' . $taxonomy . '|' . $model['trained_at']),
            'system',
            0,
            'self_training_completed',
            'Self-training model updated',
            ['taxonomy' => $taxonomy, 'samples' => $model['samples'], 'labels' => $labels]
        );

        return ['ok' => true, 'model' => ['taxonomy'=>$taxonomy,'samples'=>$model['samples'],'labels'=>$labels]];
    }

    public static function predict(string $taxonomy, string $text): ?array {
        $taxonomy = sanitize_key($taxonomy);
        $model = get_option('seojusai_st_model_' . $taxonomy);
        if (!is_array($model) || empty($model['weights']) || empty($model['labels'])) {
            return null;
        }

        $tokens = self::tokenize(strtolower($text));
        $scores = [];
        foreach ((array)$model['labels'] as $lbl) {
            $scores[$lbl] = 0.0;
            $wmap = (array)($model['weights'][$lbl] ?? []);
            foreach ($tokens as $t) {
                if (isset($wmap[$t])) {
                    $scores[$lbl] += (float)$wmap[$t];
                }
            }
        }

        arsort($scores);
        $best_label = key($scores);
        $best_score = (float)current($scores);

        // Convert score to pseudo-confidence via logistic
        $conf = 1.0 / (1.0 + exp(-$best_score));
        return [
            'label' => (string)$best_label,
            'confidence' => round((float)$conf, 4),
            'rationale' => 'Self-trained keyword model',
            'evidence' => implode(' ', array_slice($tokens, 0, 20)),
            'source' => 'self_train',
        ];
    }

    private static function tokenize(string $text): array {
        $text = preg_replace('/[^a-z0-9\s\-]/', ' ', $text);
        $parts = preg_split('/\s+/', (string)$text);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || strlen($p) < 3) continue;
            // basic stopwords
            if (in_array($p, ['the','and','for','with','you','your','are','this','that','from','into','what','when','where','who','why','how'], true)) continue;
            $out[] = $p;
        }
        return array_slice($out, 0, 600);
    }
}