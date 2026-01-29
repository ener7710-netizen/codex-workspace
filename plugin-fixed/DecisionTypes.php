<?php
declare(strict_types=1);

namespace SEOJusAI\AI;

defined('ABSPATH') || exit;

final class DecisionTypes {
	public const ADD_SCHEMA        = 'add_schema';
	public const CLEANUP_SNAPSHOTS = 'cleanup_snapshots';
	public const ADD_SECTION       = 'add_section';

	// майбутнє:
	public const UPDATE_META       = 'update_meta';
	public const INTERNAL_LINKS    = 'internal_links';
}
