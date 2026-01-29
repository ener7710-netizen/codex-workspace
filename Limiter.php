<?php
declare(strict_types=1);

namespace SEOJusAI\SaaS\Limits;

use SEOJusAI\SaaS\PlanCatalog;
use SEOJusAI\SaaS\TenantContext;
use SEOJusAI\SaaS\Usage\UsageService;

defined('ABSPATH') || exit;

final class Limiter {

    public static function can_upsert_vector(): bool {
        $plan = PlanCatalog::get(TenantContext::plan());
        $used = UsageService::count_vectors();
        return $used < (int) $plan['max_vectors'];
    }

    public static function can_run_audit(): bool {
        $plan = PlanCatalog::get(TenantContext::plan());
        $used = UsageService::audits_today();
        return $used < (int) $plan['max_audits_per_day'];
    }

    public static function can_bulk_apply(): bool {
        $plan = PlanCatalog::get(TenantContext::plan());
        $used = UsageService::// disabled: \1
        return $used < (int) $plan['max_// disabled: \1
    }

    public static function can_use_remote_compute(): bool {
        $plan = PlanCatalog::get(TenantContext::plan());
        return !empty($plan['allow_remote_compute']);
    }
}
