<?php
declare(strict_types=1);

namespace SEOJusAI\Database;

defined('ABSPATH') || exit;

/**
 * Клас Tables.
 * Відповідає за створення та оновлення структури БД для всіх модулів плагіна.
 * * ЖОРСТКЕ ПРАВИЛО: Будь-які зміни структури проводяться через dbDelta.
 */
final class Tables
{
    /**
     * Створення всіх необхідних таблиць.
     */
    public function create(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        /**
         * 1. Снапшоти (Snapshots)
         * Зберігають "зріз" даних: GSC, PageSpeed та зібрану структуру SERP (H2-H4).
         */
        $table_snapshots = $wpdb->prefix . 'seojusai_snapshots';
        $sql_snapshots = "CREATE TABLE $table_snapshots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            post_id bigint(20) DEFAULT 0,
            type varchar(50) NOT NULL, -- 'gsc', 'pagespeed', 'serp_structure', 'content'
            data_json longtext NOT NULL,
            hash varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY site_id (site_id),
            KEY type (type),
            KEY post_id (post_id)
        ) $charset_collate;";

        /**
         * 2. Черга завдань (Tasks)
         * Сюди AutopilotEngine та TaskGenerator пишуть рекомендації.
         */
        $table_tasks = $wpdb->prefix . 'seojusai_tasks';
        $sql_tasks = "CREATE TABLE $table_tasks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT 0,
            action varchar(100) NOT NULL, -- 'add_section', 'add_schema', 'review_decision'
            status varchar(20) DEFAULT 'pending' NOT NULL, -- 'pending', 'approved', 'executed', 'failed'
            priority varchar(20) DEFAULT 'medium' NOT NULL,
            payload longtext NOT NULL, -- JSON з деталями (level: h2, title: '...', etc)
            decision_hash varchar(64) DEFAULT '' NOT NULL,
            source varchar(50) DEFAULT 'ai' NOT NULL,
            explanation text, -- Чому ІІ це запропонував
            attempts int(11) NOT NULL DEFAULT 0,
            available_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_error text,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            executed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY available_at (available_at),
            KEY status_available (status, available_at),
            UNIQUE KEY d_hash (decision_hash)
        ) $charset_collate;";

                /**
         * 3. Пояснення / Explain (Explanations)
         * ЄДИНЕ джерело істини для AI-пояснень, ризиків і трасування рішень.
         * Таблиця використовується ExplainRepository/ExplanationRepository.
         *
         * Примітка безпеки:
         * - зберігаємо prompt/response для аудиту, але ключі/секрети НЕ зберігаємо тут.
         */
        $table_explain = $wpdb->prefix . 'seojusai_explanations';
        $sql_explain = "CREATE TABLE $table_explain (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(32) NOT NULL,  -- 'site', 'post', 'cluster'
            entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            decision_hash VARCHAR(64) NOT NULL DEFAULT '',
            model VARCHAR(64) DEFAULT NULL,
            prompt LONGTEXT DEFAULT NULL,
            response LONGTEXT DEFAULT NULL,
            explanation LONGTEXT DEFAULT NULL, -- JSON (reasons, evidence, suggestions)
            risk_level VARCHAR(20) NOT NULL DEFAULT 'low',
            source VARCHAR(50) NOT NULL DEFAULT 'ai',
            tokens INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entity (entity_type, entity_id),
            KEY decision_hash (decision_hash),
            KEY created_at (created_at)
        ) $charset_collate;";
/**
         * 4. База знань (Knowledge Base - KBE)
         * Навчання на помилках та збереження вдалих стратегій.
         */
        $table_kbe = $wpdb->prefix . 'seojusai_knowledge';
        $sql_kbe = "CREATE TABLE $table_kbe (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            context_hash varchar(64) NOT NULL,
            rule_key varchar(100) NOT NULL,
            rule_value text NOT NULL,
            error_weight int DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY context_rule (context_hash, rule_key)
        ) $charset_collate;";


        /**
         * 4b. KBE (compat)
         * Частина коду очікує таблицю {$wpdb->prefix}seojusai_kbe (історично).
         * Створюємо її як сумісний шар, щоб не ламати логіку модулів/дашборду.
         */
        $table_kbe_compat = $wpdb->prefix . 'seojusai_kbe';
        $sql_kbe_compat = "CREATE TABLE $table_kbe_compat (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            topic varchar(255) NOT NULL,
            content longtext NOT NULL,
            vector_id varchar(100) NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY topic (topic)
        ) $charset_collate;";

        /**
         * 5. Логування трасування (Trace)
         * Для дебагу роботи API Gemini/OpenAI.
         */
        $table_trace = $wpdb->prefix . 'seojusai_trace';
        $sql_trace = "CREATE TABLE $table_trace (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            module varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY module (module)
        ) $charset_collate;";

        /**
         * 6. Redirects + 404
         */
        $table_redirects = $wpdb->prefix . 'seojusai_redirects';
        $sql_redirects = "CREATE TABLE $table_redirects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source TEXT NOT NULL,
            target TEXT NOT NULL,
            code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
            hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY code (code)
        ) $charset_collate;";

        $table_404 = $wpdb->prefix . 'seojusai_404';
        $sql_404 = "CREATE TABLE $table_404 (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url TEXT NOT NULL,
            referrer TEXT NOT NULL,
            hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        /**
         * 7. Impact (apply/rollback журнал)
         */
        $table_impact = $wpdb->prefix . 'seojusai_impact';
        $sql_impact = "CREATE TABLE $table_impact (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            content_before LONGTEXT NULL,
            content_after LONGTEXT NULL,
            diff_summary LONGTEXT NULL,
            meta_data LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entity (entity_type, entity_id),
            KEY action_type (action_type)
        ) $charset_collate;";

        /**
         * 8. Locks
         */
        $table_locks = $wpdb->prefix . 'seojusai_locks';
        $sql_locks = "CREATE TABLE $table_locks (
            lock_name VARCHAR(100) NOT NULL,
            created_at BIGINT NOT NULL,
            expires_at BIGINT NOT NULL,
            PRIMARY KEY (lock_name)
        ) $charset_collate;";

        /**
         * 9. Vectors (embeddings)
         */
        $table_vectors = $wpdb->prefix . 'seojusai_vectors';
        $sql_vectors = "CREATE TABLE $table_vectors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id VARCHAR(64) NOT NULL,
            site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            object_type VARCHAR(20) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            chunk_hash VARCHAR(64) NOT NULL,
            content LONGTEXT NOT NULL,
            embedding LONGTEXT NOT NULL,
            model VARCHAR(64) NOT NULL,
            dims INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_obj_chunk (tenant_id, object_type, object_id, chunk_hash),
            KEY tenant_object (tenant_id, object_type, object_id),
            KEY tenant_model (tenant_id, model),
            KEY site_object (site_id, object_type, object_id),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        /**
         * 10. Learning loop (pred vs observed)
         */
        $table_learning = $wpdb->prefix . 'seojusai_learning';
        $sql_learning = "CREATE TABLE $table_learning (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(20) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            decision_hash VARCHAR(64) NOT NULL,
            predicted_impact FLOAT NOT NULL DEFAULT 0,
            predicted_effort FLOAT NOT NULL DEFAULT 0,
            observed_clicks_delta FLOAT NOT NULL DEFAULT 0,
            observed_pos_delta FLOAT NOT NULL DEFAULT 0,
            observed_impressions_delta FLOAT NOT NULL DEFAULT 0,
            window_start DATETIME NULL,
            window_end DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entity (entity_type, entity_id),
            KEY decision_hash (decision_hash)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';


        dbDelta($sql_snapshots);
        dbDelta($sql_tasks);
        $table_audit = $wpdb->prefix . 'seojusai_audit';

        $sql_audit = "CREATE TABLE $table_audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            decision_hash varchar(64) NOT NULL,
            entity_type varchar(32) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            event varchar(64) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY decision_hash (decision_hash),
            KEY entity (entity_type, entity_id),
            KEY event (event)
        ) $charset_collate ENGINE=InnoDB;";

        dbDelta($sql_audit);


        $table_decisions = $wpdb->prefix . 'seojusai_decisions';

        $sql_decisions = "CREATE TABLE $table_decisions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            decision_hash varchar(64) NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            score float NOT NULL,
            summary text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'planned',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY decision_hash (decision_hash),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate ENGINE=InnoDB;";

        dbDelta($sql_decisions);

$table_decision_items = $wpdb->prefix . 'seojusai_decision_items';

$sql_decision_items = "CREATE TABLE $table_decision_items (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    decision_hash varchar(64) NOT NULL,
    post_id bigint(20) unsigned NOT NULL,
    taxonomy varchar(32) NOT NULL,
    label varchar(64) NOT NULL,
    confidence float NOT NULL,
    confidence_raw float DEFAULT NULL,
    rationale text DEFAULT NULL,
    evidence text DEFAULT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY decision_hash (decision_hash),
    KEY post_tax (post_id, taxonomy),
    KEY taxonomy (taxonomy),
    KEY label (label)
) $charset_collate ENGINE=InnoDB;";

dbDelta($sql_decision_items);

$table_seo_meta = $wpdb->prefix . 'seojusai_seo_meta';

$sql_seo_meta = "CREATE TABLE $table_seo_meta (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    decision_hash varchar(64) NOT NULL,
    post_id bigint(20) unsigned NOT NULL,
    seo_title text NULL,
    meta_description text NULL,
    status varchar(20) NOT NULL DEFAULT 'planned',
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY decision_hash (decision_hash),
    KEY post_id (post_id)
) $charset_collate ENGINE=InnoDB;";

dbDelta($sql_seo_meta);

$table_clients = $wpdb->prefix . 'seojusai_clients';
$sql_clients = "CREATE TABLE $table_clients (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_name varchar(191) NOT NULL,
    api_key varchar(64) NOT NULL,
    requests_per_minute int NOT NULL DEFAULT 60,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY api_key (api_key)
) $charset_collate ENGINE=InnoDB;";
dbDelta($sql_clients);



        dbDelta($sql_explain);
        dbDelta($sql_kbe);
        dbDelta($sql_kbe_compat);
        dbDelta($sql_trace);
        dbDelta($sql_redirects);
        dbDelta($sql_404);
        dbDelta($sql_impact);
        dbDelta($sql_locks);
        dbDelta($sql_vectors);
        dbDelta($sql_learning);

        /**
         * 11. Market signals (конкуренти)
         * Зберігає URL конкурентів та базові сигнали по сторінках (без копіювання контенту).
         */
        $table_comp = $wpdb->prefix . 'seojusai_competitors';
        $sql_comp = "CREATE TABLE $table_comp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url TEXT NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'serp',
            query_text VARCHAR(190) NULL,
            best_position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            appearances SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            last_scan_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY source (source),
            KEY best_position (best_position)
        ) $charset_collate;";

        $table_signals = $wpdb->prefix . 'seojusai_competitor_signals';
        $sql_signals = "CREATE TABLE $table_signals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            competitor_id BIGINT UNSIGNED NOT NULL,
            url TEXT NOT NULL,
            page_type VARCHAR(20) NOT NULL DEFAULT 'unknown',
            has_soft_cta TINYINT(1) NOT NULL DEFAULT 0,
            cta_position VARCHAR(10) NOT NULL DEFAULT 'none',
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY competitor_id (competitor_id),
            KEY page_type (page_type),
            KEY has_soft_cta (has_soft_cta)
        ) $charset_collate;";

        dbDelta($sql_comp);
        dbDelta($sql_signals);

/**
 * 9. Bulk Jobs (масові операції)
 */
$table_bulk = $wpdb->prefix . 'seojusai_bulk_jobs';
$sql_bulk = "CREATE TABLE $table_bulk (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL DEFAULT 0,
    job_type varchar(32) NOT NULL, -- 'audit'|'apply'|'rollback'
    filters_json longtext NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending', -- pending|awaiting_approval|running|paused|completed|failed|cancelled
    approved_by bigint(20) NOT NULL DEFAULT 0,
    approved_at datetime NULL,
    approved_until datetime NULL,
    approval_note varchar(190) NULL,
    total_items int(11) NOT NULL DEFAULT 0,
    processed_items int(11) NOT NULL DEFAULT 0,
    success_items int(11) NOT NULL DEFAULT 0,
    failed_items int(11) NOT NULL DEFAULT 0,
    last_error longtext NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY job_type (job_type),
    KEY status (status),
    KEY user_id (user_id)
) $charset_collate;";
dbDelta($sql_bulk);

/**
 * Execution Intents (machine-only)
 * @boundary ExecutionIntent описує ЯК виконувати, але нічого не запускає сам.
 */
$table_intents = $wpdb->prefix . 'seojusai_execution_intents';
$sql_intents = "CREATE TABLE $table_intents (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    strategic_decision_id bigint(20) UNSIGNED NOT NULL,
    intent_type varchar(64) NOT NULL,
    status varchar(16) NOT NULL DEFAULT 'pending',
    payload longtext NOT NULL,
    claimed_by varchar(191) NULL,
    claimed_at datetime NULL,
    completed_at datetime NULL,
    error_message text NULL,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY strategic_decision_id (strategic_decision_id),
    KEY status_created (status, created_at),
    KEY claimed_by (claimed_by)
) $charset_collate;";
dbDelta($sql_intents);

/**
 * Analysis Results (read-only output)
 */
$table_results = $wpdb->prefix . 'seojusai_analysis_results';
$sql_results = "CREATE TABLE $table_results (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    intent_id bigint(20) UNSIGNED NOT NULL,
    post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
    result_json longtext NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY intent_id (intent_id)
) $charset_collate;";
dbDelta($sql_results);


        // Market tables created above.

    }
}
