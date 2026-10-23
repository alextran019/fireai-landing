<?php
/**
 * Tạo bảng dữ liệu, vai trò Sale và cài đặt mặc định.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fireai_crm_activate() {
	$previous = (string) get_option( 'fireai_crm_db_version' );
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

	if ( '' !== $previous && version_compare( $previous, '2', '<' ) ) {
		fireai_crm_resync_form_leads();
	}
}

function fireai_crm_maybe_upgrade() {
	if ( get_option( 'fireai_crm_db_version' ) !== FIREAI_CRM_DB_VERSION ) {
		fireai_crm_activate();
	}
}

/**
 * Bản 1.0 chưa đọc các trường utm_* trong form nên mọi khách đều ghi nguồn "Website"
 * và utm_content bị hiểu nhầm là nội dung. Tính lại từ dữ liệu gốc cho các khách tự động
 * mà Sale chưa đổi nguồn.
 */
function fireai_crm_resync_form_leads() {
	global $wpdb;
	$table = fireai_crm_table( 'leads' );
	$rows  = $wpdb->get_results( "SELECT id, source, campaign, message, raw FROM {$table} WHERE created_by = 0 AND source = 'website' AND raw IS NOT NULL" ); // phpcs:ignore

	foreach ( $rows as $row ) {
		$raw = json_decode( (string) $row->raw, true );
		if ( ! is_array( $raw ) || empty( $raw['fields'] ) || ! is_array( $raw['fields'] ) ) {
			continue;
		}
		$title  = isset( $raw['form'] ) ? (string) $raw['form'] : (string) $row->campaign;
		$url    = isset( $raw['url'] ) ? (string) $raw['url'] : '';
		$lead   = fireai_crm_lead_from_form( $raw['fields'], $title, array(), $url );
		$update = array(
			'source'   => $lead['source'],
			'campaign' => sanitize_text_field( $lead['campaign'] ),
		);
		// Chỉ thay nội dung khi vẫn còn dấu vết utm_*, tức là Sale chưa sửa tay.
		if ( false !== strpos( (string) $row->message, 'utm_' ) || ( isset( $raw['fields']['utm_content'] ) && is_scalar( $raw['fields']['utm_content'] ) && '' !== (string) $raw['fields']['utm_content'] && 0 === strpos( (string) $row->message, (string) $raw['fields']['utm_content'] ) ) ) {
			$update['message'] = sanitize_textarea_field( $lead['message'] );
		}
		$wpdb->update( $table, $update, array( 'id' => (int) $row->id ) );
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
