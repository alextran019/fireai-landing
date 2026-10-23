<?php
/**
 * Nhận khách hàng từ Contact Form 7, webhook quảng cáo, và dữ liệu cũ của CFDB7.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Contact Form 7: mỗi lần khách gửi form hợp lệ (không phải spam) thì lưu vào CRM.
 */
add_action( 'wpcf7_before_send_mail', 'fireai_crm_from_cf7' );

function fireai_crm_from_cf7( $contact_form ) {
	$settings = fireai_crm_settings();
	if ( empty( $settings['cf7_enabled'] ) || ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}
	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return;
	}

	$posted   = (array) $submission->get_posted_data();
	$url      = (string) $submission->get_meta( 'url' );
	$detected = fireai_crm_detect_source( $url );
	$mapped   = fireai_crm_map_fields( $posted );
	$title    = is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ? $contact_form->title() : '';

	fireai_crm_insert_lead(
		array_merge(
			$mapped,
			array(
				'source'     => $detected['source'],
				'campaign'   => $detected['campaign'],
				'created_by' => 0,
				'raw'        => array(
					'form'   => $title,
					'url'    => $url,
					'fields' => $posted,
				),
			)
		)
	);
}

/*
 * Webhook: POST https://ten-mien/wp-json/fireai-crm/v1/lead
 * Dùng cho Zapier / Make / Facebook Lead Ads / Google Ads lead form.
 */
add_action( 'rest_api_init', 'fireai_crm_register_routes' );

function fireai_crm_register_routes() {
	register_rest_route(
		'fireai-crm/v1',
		'/lead',
		array(
			'methods'             => 'POST',
			'callback'            => 'fireai_crm_webhook',
			'permission_callback' => 'fireai_crm_webhook_permission',
		)
	);
}

function fireai_crm_webhook_permission( WP_REST_Request $request ) {
	$settings = fireai_crm_settings();
	$key      = (string) $request->get_header( 'x-crm-key' );
	if ( '' === $key ) {
		$key = (string) $request->get_param( 'key' );
	}
	if ( '' === $settings['webhook_key'] || '' === $key || ! hash_equals( $settings['webhook_key'], $key ) ) {
		return new WP_Error( 'fireai_crm_forbidden', 'Sai mã bảo mật (key).', array( 'status' => 403 ) );
	}
	return true;
}

function fireai_crm_webhook( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	if ( ! is_array( $params ) || ! $params ) {
		$params = $request->get_body_params();
	}
	if ( ! is_array( $params ) || ! $params ) {
		$params = $request->get_query_params();
	}
	unset( $params['key'] );

	$pick = function ( array $keys ) use ( $params ) {
		foreach ( $keys as $k ) {
			if ( isset( $params[ $k ] ) && is_scalar( $params[ $k ] ) && '' !== trim( (string) $params[ $k ] ) ) {
				return trim( (string) $params[ $k ] );
			}
		}
		return '';
	};

	$keys = array(
		'name'     => array( 'name', 'full_name', 'fullname', 'ho_ten', 'ten' ),
		'phone'    => array( 'phone', 'phone_number', 'sdt', 'mobile', 'tel' ),
		'email'    => array( 'email', 'mail' ),
		'message'  => array( 'message', 'note', 'noi_dung', 'ghi_chu' ),
		'campaign' => array( 'campaign', 'utm_campaign', 'campaign_name', 'ad_name', 'form_name' ),
		'source'   => array( 'source', 'nguon', 'utm_source' ),
	);

	// Các trường không nằm trong danh sách trên vẫn được nhận diện và ghép vào nội dung.
	$rest   = array_diff_key( $params, array_flip( array_merge( ...array_values( $keys ) ) ) );
	$mapped = fireai_crm_map_fields( $rest );

	$source = strtolower( $pick( $keys['source'] ) );
	if ( preg_match( '/^(fb|facebook|ig|instagram)/', $source ) ) {
		$source = 'facebook';
	} elseif ( false !== strpos( $source, 'google' ) ) {
		$source = 'google';
	} elseif ( false !== strpos( $source, 'tiktok' ) ) {
		$source = 'tiktok';
	}

	$id = fireai_crm_insert_lead(
		array(
			'name'       => $pick( $keys['name'] ) ?: $mapped['name'],
			'phone'      => $pick( $keys['phone'] ) ?: $mapped['phone'],
			'email'      => $pick( $keys['email'] ) ?: $mapped['email'],
			'message'    => trim( $pick( $keys['message'] ) . "\n\n" . $mapped['message'] ),
			'campaign'   => $pick( $keys['campaign'] ),
			'source'     => $source,
			'created_by' => 0,
			'raw'        => $params,
		)
	);

	if ( ! $id ) {
		return new WP_Error( 'fireai_crm_invalid', 'Cần ít nhất tên, số điện thoại hoặc email.', array( 'status' => 400 ) );
	}
	return array(
		'ok' => true,
		'id' => $id,
	);
}

/**
 * Nhập các khách đã gửi form trước khi cài CRM (lưu bởi plugin Contact Form 7 Database Addon - CFDB7).
 * Chỉ lấy bản ghi cũ hơn thời điểm cài CRM để không trùng với khách đã tự vào CRM.
 *
 * @return int|WP_Error Số khách đã nhập.
 */
function fireai_crm_import_cfdb7() {
	global $wpdb;
	$table = $wpdb->prefix . 'db7_forms';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return new WP_Error( 'fireai_crm_no_cfdb7', 'Không tìm thấy dữ liệu của plugin Contact Form 7 Database Addon (CFDB7).' );
	}

	$installed = get_option( 'fireai_crm_installed_at', current_time( 'mysql' ) );
	$rows      = $wpdb->get_results(
		$wpdb->prepare( "SELECT form_id, form_post_id, form_value, form_date FROM {$table} WHERE form_date < %s ORDER BY form_id ASC", $installed )
	);
	$existing  = $wpdb->get_col( 'SELECT external_ref FROM ' . fireai_crm_table( 'leads' ) . " WHERE external_ref LIKE 'cfdb7:%'" );
	$existing  = array_flip( $existing );
	$count     = 0;

	foreach ( $rows as $row ) {
		$ref = 'cfdb7:' . $row->form_id;
		if ( isset( $existing[ $ref ] ) || ! is_serialized( $row->form_value ) ) {
			continue;
		}
		$fields = @unserialize( $row->form_value, array( 'allowed_classes' => false ) ); // phpcs:ignore
		if ( ! is_array( $fields ) ) {
			continue;
		}
		$id = fireai_crm_insert_lead(
			array_merge(
				fireai_crm_map_fields( $fields ),
				array(
					'source'       => 'website',
					'campaign'     => get_the_title( (int) $row->form_post_id ),
					'external_ref' => $ref,
					'created_at'   => $row->form_date,
					'created_by'   => 0,
					'raw'          => array( 'fields' => $fields ),
				)
			)
		);
		if ( $id ) {
			$count++;
		}
	}
	return $count;
}

/*
 * Cho phép tài khoản Sale vào trang quản trị (WooCommerce mặc định chặn người không phải nhân viên cửa hàng).
 */
add_filter( 'woocommerce_prevent_admin_access', 'fireai_crm_allow_admin_access' );

function fireai_crm_allow_admin_access( $prevent ) {
	return current_user_can( FIREAI_CRM_CAP ) ? false : $prevent;
}

/*
 * Sale đăng nhập xong vào thẳng trang CRM.
 */
add_filter( 'login_redirect', 'fireai_crm_login_redirect', 20, 3 );
add_filter( 'woocommerce_login_redirect', 'fireai_crm_login_redirect', 20, 2 );

function fireai_crm_login_redirect( $redirect, $requested = '', $user = null ) {
	if ( $requested instanceof WP_User ) {
		$user = $requested;
	}
	if ( $user instanceof WP_User && $user->has_cap( FIREAI_CRM_CAP ) && ! $user->has_cap( 'edit_posts' ) ) {
		return admin_url( 'admin.php?page=fireai-crm' );
	}
	return $redirect;
}
