<?php
declare(strict_types=1);
namespace SEOJusAI\Repository;
defined('ABSPATH')||exit;

final class ClientRepository {

    public static function getByKey(string $key): ?object {
        global $wpdb;
        $table=$wpdb->prefix.'seojusai_clients';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_key=%s AND is_active=1",
            $key
        ));
    }
}
