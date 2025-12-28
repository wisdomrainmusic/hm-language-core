<?php
/**
 * Plugin Name: HM Language Core
 * Description: Simplified multilingual core inspired by Polylang architecture (internal).
 * Version: 0.1.0
 * Author: HM
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('HMLC_VERSION', '0.1.0');
define('HMLC_PLUGIN_FILE', __FILE__);
define('HMLC_PLUGIN_DIR', __DIR__);

require_once HMLC_PLUGIN_DIR . '/includes/class-hmlc.php';

function hmlc(): HMLC
{
    return HMLC::instance();
}

add_action('plugins_loaded', 'hmlc');
