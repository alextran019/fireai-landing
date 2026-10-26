<?php
/**
 * Plugin Name: FireAI CRM
 * Description: Quản lý khách hàng (lead) đổ về từ website, quảng cáo và hotline cho đội Sale.
 * Version:     1.4.0
 * Author:      FireAI
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Text Domain: fireai-crm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FIREAI_CRM_VERSION', '1.4.0' );
define( 'FIREAI_CRM_DB_VERSION', '6' );
define( 'FIREAI_CRM_FILE', __FILE__ );
define( 'FIREAI_CRM_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/install.php';
require_once __DIR__ . '/includes/integrations.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/telegram-bot.php';
require_once __DIR__ . '/includes/clicks.php';
require_once __DIR__ . '/includes/reminders.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
	require_once __DIR__ . '/includes/report.php';
}

register_activation_hook( __FILE__, 'fireai_crm_activate' );
register_deactivation_hook( __FILE__, 'fireai_crm_unschedule_digest' );
add_action( 'plugins_loaded', 'fireai_crm_maybe_upgrade' );
