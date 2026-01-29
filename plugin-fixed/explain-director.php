<?php
declare(strict_types=1);

use SEOJusAI\Capabilities\CapabilityGuard;
use SEOJusAI\Capabilities\CapabilityMap;
use SEOJusAI\Explain\ExplainRepository;
use SEOJusAI\Input\Input;

defined('ABSPATH') || exit;

if (!CapabilityGuard::can(CapabilityMap::MANAGE_SETTINGS)) {
    wp_die(__('Недостатньо прав доступу.', 'seojusai'));
}

$repo = new ExplainRepository();

$tab = (Input::get('tab', null) !== null) ? sanitize_key((string)Input::get('tab')) : 'overview';
$risk = (Input::get('risk', null) !== null) ? sanitize_key((string)Input::get('risk')) : '';
$etype = (Input::get('entity_type', null) !== null) ? sanitize_key((string)Input::get('entity_type')) : '';
$qhash = (Input::get('hash', null) !== null) ? sanitize_text_field((string)Input::get('hash')) : '';
$days = (Input::get('days', null) !== null) ? max(1, min(365, (int)Input::get('days'))) : 30;

$base_url = menu_page_url('seojusai-explain', false);

?>
<div class="wrap">
    <h1>Центр пояснень</h1>

    <h2 class="nav-tab-wrapper">
        <a href="<?= esc_url(add_query_arg('tab','overview',$base_url)) ?>" class="nav-tab <?= $tab==='overview'?'nav-tab-active':'' ?>">Огляд</a>
        <a href="<?= esc_url(add_query_arg('tab','history',$base_url)) ?>" class="nav-tab <?= $tab==='history'?'nav-tab-active':'' ?>">Історія</a>
        <a href="<?= esc_url(add_query_arg('tab','diff',$base_url)) ?>" class="nav-tab <?= $tab==='diff'?'nav-tab-active':'' ?>">Diff</a>
    </h2>

    <?php if ($tab === 'overview'): ?>
        <?php $agg = $repo->aggregates_site($days); ?>
        <div class="card" style="max-width:100%; border-left:4px solid #00a32a;">
            <h2 style="margin-top:0;">Підсумок за останні <?= (int)$days ?> днів</h2>
            <p><strong>Усього пояснень:</strong> <?= (int)$agg['total'] ?> | <strong>Середня впевненість:</strong> <?= esc_html((string)$agg['avg_confidence']) ?></p>
            <ul style="display:flex; gap:16px; flex-wrap:wrap;">
                <li><strong>Low:</strong> <?= (int)($agg['by_risk']['low'] ?? 0) ?></li>
                <li><strong>Medium:</strong> <?= (int)($agg['by_risk']['medium'] ?? 0) ?></li>
                <li><strong>High:</strong> <?= (int)($agg['by_risk']['high'] ?? 0) ?></li>
            </ul>
            <p><em>Порада:</em> Якщо High росте — вмикайте “approve only low-risk” або підсилюйте Безпечний режим.</p>
        </div>

        <div class="card" style="max-width:100%; margin-top:14px;">
            <h2 style="margin-top:0;">Швидкий пошук за decision_hash</h2>
            <form method="get">
                <input type="hidden" name="page" value="seojusai-explain" />
                <input type="hidden" name="tab" value="history" />
                <label>decision_hash:</label>
                <input type="text" name="hash" value="<?= esc_attr($qhash) ?>" style="min-width:320px;" />
                <button class="button button-primary" type="submit">Показати</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'history'): ?>
        <div class="card" style="max-width:100%;">
            <h2 style="margin-top:0;">Історія пояснень</h2>

            <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="page" value="seojusai-explain" />
                <input type="hidden" name="tab" value="history" />

                <div>
                    <label><strong>risk</strong></label><br>
                    <select name="risk">
                        <option value="" <?= $risk===''?'selected':'' ?>>Усі</option>
                        <option value="low" <?= $risk==='low'?'selected':'' ?>>low</option>
                        <option value="medium" <?= $risk==='medium'?'selected':'' ?>>medium</option>
                        <option value="high" <?= $risk==='high'?'selected':'' ?>>high</option>
                    </select>
                </div>

                <div>
                    <label><strong>entity_type</strong></label><br>
                    <select name="entity_type">
                        <option value="" <?= $etype===''?'selected':'' ?>>Усі</option>
                        <option value="site" <?= $etype==='site'?'selected':'' ?>>site</option>
                        <option value="post" <?= $etype==='post'?'selected':'' ?>>post</option>
                        <option value="cluster" <?= $etype==='cluster'?'selected':'' ?>>cluster</option>
                    </select>
                </div>

                <div>
                    <label><strong>decision_hash</strong></label><br>
                    <input type="text" name="hash" value="<?= esc_attr($qhash) ?>" style="min-width:260px;" />
                </div>

                <div>
                    <button class="button button-primary" type="submit">Фільтрувати</button>
                </div>
            </form>

            <?php
                $rows = $qhash ? $repo->list_by_hash($qhash, 50) : $repo->list_all(50, 0, $risk, $etype);
            ?>

            <table class="widefat striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:90px;">Risk</th>
                        <th style="width:90px;">Conf</th>
                        <th style="width:90px;">Type</th>
                        <th style="width:90px;">Entity</th>
                        <th>Коротко</th>
                        <th style="width:210px;">Створено</th>
                        <th style="width:120px;">Дії</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8">Немає записів.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $exp = $r['explanation'];
                            $short = '';
                            if (is_array($exp)) {
                                if (!empty($exp['explanation'])) $short = (string)$exp['explanation'];
                                elseif (!empty($exp['summary'])) $short = (string)$exp['summary'];
                                elseif (!empty($exp['title'])) $short = (string)$exp['title'];
                            }
                            $short = $short ? mb_strimwidth($short, 0, 90, '…') : '—';
                            $view_url = esc_url(add_query_arg(['tab'=>'diff','a'=>(int)$r['id']], $base_url));
                        ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><code><?= esc_html((string)$r['risk_level']) ?></code></td>
                            <td><?= esc_html(number_format((float)($r['confidence'] ?? 0), 2)) ?></td>
                            <td><?= esc_html((string)$r['entity_type']) ?></td>
                            <td><?= (int)$r['entity_id'] ?></td>
                            <td><?= esc_html($short) ?></td>
                            <td><small><?= esc_html((string)$r['created_at']) ?></small></td>
                            <td><a class="button" href="<?= $view_url ?>">Відкрити</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <p style="margin-top:10px;"><em>Порада:</em> щоб зробити diff — відкрийте запис і виберіть “B”.</p>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'diff'): ?>
        <?php
            $a = (Input::get('a', null) !== null) ? (int)Input::get('a') : 0;
            $b = (Input::get('b', null) !== null) ? (int)Input::get('b') : 0;
            $rowA = $a ? $repo->get($a) : null;
            $rowB = $b ? $repo->get($b) : null;

            function seojusai_pretty_json($v): string {
                if ($v === null) return 'null';
                $json = wp_json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                return $json ? $json : 'null';
            }

            function seojusai_simple_diff(string $a, string $b): string {
                $al = explode("\n", $a);
                $bl = explode("\n", $b);
                $out = [];
                $max = max(count($al), count($bl));
                for ($i=0; $i<$max; $i++) {
                    $la = $al[$i] ?? '';
                    $lb = $bl[$i] ?? '';
                    if ($la === $lb) {
                        $out[] = '  ' . $la;
                    } else {
                        if ($la !== '') $out[] = '- ' . $la;
                        if ($lb !== '') $out[] = '+ ' . $lb;
                    }
                }
                return implode("\n", $out);
            }
        ?>

        <div class="card" style="max-width:100%; border-left:4px solid #2271b1;">
            <h2 style="margin-top:0;">Diff між двома Explain</h2>
            <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="page" value="seojusai-explain" />
                <input type="hidden" name="tab" value="diff" />
                <div>
                    <label><strong>A (id)</strong></label><br>
                    <input type="number" name="a" value="<?= (int)$a ?>" min="0" />
                </div>
                <div>
                    <label><strong>B (id)</strong></label><br>
                    <input type="number" name="b" value="<?= (int)$b ?>" min="0" />
                </div>
                <div>
                    <button class="button button-primary" type="submit">Порівняти</button>
                </div>
            </form>
            <p style="margin-top:10px;">A можна обрати з вкладки “Історія” (кнопка “Відкрити”). Потім впишіть B.</p>
        </div>

        <?php if ($rowA): ?>
            <div class="card" style="max-width:100%; margin-top:12px;">
                <h3>A: #<?= (int)$rowA['id'] ?> (<?= esc_html((string)$rowA['risk_level']) ?>, conf <?= esc_html(number_format((float)$rowA['confidence'],2)) ?>)</h3>
                <pre style="white-space:pre-wrap; background:#f6f7f7; padding:12px; border:1px solid #dcdcde;"><?= esc_html(seojusai_pretty_json($rowA['explanation'])) ?></pre>
            </div>
        <?php endif; ?>

        <?php if ($rowB): ?>
            <div class="card" style="max-width:100%; margin-top:12px;">
                <h3>B: #<?= (int)$rowB['id'] ?> (<?= esc_html((string)$rowB['risk_level']) ?>, conf <?= esc_html(number_format((float)$rowB['confidence'],2)) ?>)</h3>
                <pre style="white-space:pre-wrap; background:#f6f7f7; padding:12px; border:1px solid #dcdcde;"><?= esc_html(seojusai_pretty_json($rowB['explanation'])) ?></pre>
            </div>
        <?php endif; ?>

        <?php if ($rowA && $rowB): ?>
            <?php
                $ja = seojusai_pretty_json($rowA['explanation']);
                $jb = seojusai_pretty_json($rowB['explanation']);
                $diff = seojusai_simple_diff($ja, $jb);
            ?>
            <div class="card" style="max-width:100%; margin-top:12px; border-left:4px solid #d63638;">
                <h3 style="margin-top:0;">Diff</h3>
                <pre style="white-space:pre-wrap; background:#fff; padding:12px; border:1px solid #dcdcde;"><?= esc_html($diff) ?></pre>
                <p style="margin-top:10px;"><em>Примітка:</em> це простий diff. Наступним кроком можемо зробити “semantic diff” (по ключах).</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
