<?php
/**
 * Global helper functions for Burst MainWP Extension.
 *
 * These are intentionally kept minimal. Business logic belongs in classes.
 *
 * @package Burst_Statistics_MainWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieve a Burst option value that was forwarded from the child site.
 *
 * Child site options are hydrated into `child_data['options']` by
 * {@see Burst_MainWP_API::get_child_auth()} and cached on the Individual
 * singleton for the lifetime of the request.
 *
 * @param string $option_name Option key as stored on the child.
 * @param mixed  $_default     Value to return when the option is absent.
 * @return mixed Option value or default.
 */
function burst_get_option( string $option_name, mixed $_default = null ): mixed {
	$individual = Burst_MainWP_Individual::instance();
	$options    = $individual->get_child_options();

	return $options[ $option_name ] ?? $_default;
}
