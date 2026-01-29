<?php
declare(strict_types=1);

namespace SEOJusAI\Capabilities;

defined('ABSPATH') || exit;

/**
 * RoleInstaller
 *
 * М'яко додає capabilities до існуючих ролей (без створення нових ролей).
 */
final class RoleInstaller {

    public static function install(): void {
        // Administrator: повний доступ
        $admin = get_role('administrator');
        if ($admin) {
            foreach (CapabilityMap::all() as $cap) {
                $admin->add_cap($cap);
            }
        }

        // Editor: read-only доступ до звітів/пояснень (можна змінити на наступних етапах)
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap(CapabilityMap::VIEW_REPORTS);
        }
    }
}
