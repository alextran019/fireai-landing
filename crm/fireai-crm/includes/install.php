<?php
/**
 * Tạo bảng dữ liệu, vai trò Sale và cài đặt mặc định.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fireai_crm_activate() {
	fireai_crm_install_tables();
	fireai_crm_install_roles();

	if ( ! get_option( 'fireai_crm_installed_at' ) ) {
		update_option( 'fireai_crm_installed_at', current_time( 'mysql' ), false );
	}

	$settings = get_option( 'fireai_crm_settings' );
	if ( ! is_array( $settings ) ) {
		update_option(
			'fireai_crm_settings',
			array(
				'webhook_key'   => wp_generate_password( 32, false ),
				'cf7_enabled'   => 1,
				'sale_own_only' => 0,
			),
			false
		);
	}
}

function fireai_crm_maybe_upgrade() {
	if ( get_option( 'fireai_crm_db_version' ) !== FIREAI_CRM_DB_VERSION ) {
		fireai_crm_activate();
	}
}

function fireai_crm_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$leads   = fireai_crm_table( 'leads' );
	$notes   = fireai_crm_table( 'notes' );

	dbDelta(
		"CREATE TABLE {$leads} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			source varchar(32) NOT NULL DEFAULT 'other',
			campaign varchar(191) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'new',
			assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NULL,
			external_ref varchar(191) NOT NULL DEFAULT '',
			raw longtext NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY source (source),
			KEY status (status),
			KEY assigned_to (assigned_to),
			KEY created_at (created_at),
			KEY external_ref (external_ref)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			content text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) {$charset};"
	);

	update_option( 'fireai_crm_db_version', FIREAI_CRM_DB_VERSION, false );
}

function fireai_crm_install_roles() {
	if ( ! get_role( 'fireai_crm_sale' ) ) {
		add_role(
			'fireai_crm_sale',
			'Nhân viên Sale (CRM)',
			array(
				'read'              => true,
				FIREAI_CRM_CAP      => true,
			)
		);
	}

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( FIREAI_CRM_CAP );
		$admin->add_cap( FIREAI_CRM_MANAGE_CAP );
	}
}
