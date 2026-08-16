<?php
/**
 * Backward-compatible wrapper for legacy MAF bridge naming.
 *
 * @package StoryOS
 */

namespace StoryOS\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated Use AI_Agent_Registry instead.
 */
class AI_MAF_Bridge extends AI_Agent_Registry {}
