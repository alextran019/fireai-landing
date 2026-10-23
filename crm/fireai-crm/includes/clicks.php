<?php
/**
 * Ghi nhận lượt khách bấm nút Gọi / Zalo trên website.
 *
 * Website không thể biết số điện thoại của người bấm, nhưng biết họ đến từ quảng cáo nào.
 * Mỗi lượt bấm được báo vào nhóm Telegram, và khi người cầm máy nhập khách qua bot ngay sau đó,
 * khách được gắn với chiến dịch của lượt bấm gần nhất.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Lượt bấm trong khoảng này trước khi nhập khách qua bot được coi là của khách đó.
const FIREAI_CRM_CLICK_MATCH_MINUTES = 30;

add_action( 'wp_enqueue_scripts', 'fireai_crm_enqueue_tracker' );
add_action( 'rest_api_init', 'fireai_crm_register_click_route' );

function fireai_crm_enqueue_tracker() {
	$settings = fireai_crm_settings();
	if ( empty( $settings['click_track'] ) ) {
		return;
	}
	wp_enqueue_script( 'fireai-crm-track', FIREAI_CRM_URL . 'assets/track.js', array(), FIREAI_CRM_VERSION, true );
	wp_localize_script( 'fireai-crm-track', 'FireAICRMTrack', array( 'url' => rest_url( 'fireai-crm/v1/click' ) ) );
}

function fireai_crm_register_click_route() {
	register_rest_route(
		'fireai-crm/v1',
		'/click',
		array(
			'methods'             => 'POST',
			'callback'            => 'fireai_crm_record_click',
			'permission_callback' => '__return_true',
		)
	);
}

function fireai_crm_record_click( WP_REST_Request $request ) {
	global $wpdb;
	$settings = fireai_crm_settings();
	$type     = sanitize_key( (string) $request->get_param( 'type' ) );
	if ( empty( $settings['click_track'] ) || ! in_array( $type, array( 'call', 'zalo' ), true ) ) {
		return array( 'ok' => false );
	}

	// Chống spam: mỗi thiết bị 1 lượt / loại / 2 phút, toàn site tối đa 120 lượt / giờ.
	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip_hash = substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
	$key     = 'fireai_crm_click_' . md5( $ip_hash . $type );
	if ( get_transient( $key ) ) {
		return array( 'ok' => true );
	}
	set_transient( $key, 1, 2 * MINUTE_IN_SECONDS );
	$hour = (int) get_transient( 'fireai_crm_click_hour' );
	if ( $hour >= 120 ) {
		return array( 'ok' => true );
	}
	set_transient( 'fireai_crm_click_hour', $hour + 1, HOUR_IN_SECONDS );

	$params = array();
	foreach ( array( 'utm_source', 'utm_campaign', 'utm_content', 'fbclid', 'gclid', 'ttclid' ) as $p ) {
		$value = $request->get_param( $p );
		if ( is_scalar( $value ) && '' !== (string) $value ) {
			$params[ $p ] = substr( sanitize_text_field( (string) $value ), 0, 190 );
		}
	}
	$tracked = fireai_crm_source_from_params( $params );
	$page    = esc_url_raw( substr( (string) $request->get_param( 'page' ), 0, 500 ) );

	$wpdb->insert(
		fireai_crm_table( 'clicks' ),
		array(
			'type'       => $type,
			'source'     => $tracked['source'] ? $tracked['source'] : 'website',
			'campaign'   => $tracked['campaign'],
			'page'       => $page,
			'ip_hash'    => $ip_hash,
			'lead_id'    => 0,
			'created_at' => current_time( 'mysql' ),
		)
	);

	if ( ! empty( $settings['click_notify'] ) && $settings['tg_token'] && $settings['tg_chat_id'] ) {
		$esc   = function ( $t ) {
			return htmlspecialchars( (string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		};
		$lines = array(
			( 'call' === $type ? '📞 <b>Có khách vừa bấm GỌI HOTLINE</b>' : '💬 <b>Có khách vừa bấm CHAT ZALO</b>' ),
			'Nguồn: ' . $esc( fireai_crm_label( fireai_crm_sources(), $tracked['source'] ? $tracked['source'] : 'website' ) ) . ( $tracked['campaign'] ? ' · ' . $esc( $tracked['campaign'] ) : '' ),
		);
		$path = wp_parse_url( $page, PHP_URL_PATH );
		if ( $path ) {
			$lines[] = 'Trang: ' . $esc( $path );
		}
		$lines[] = '';
		$lines[] = 'Nói chuyện xong nhớ gõ <code>' . ( 'call' === $type ? '/kh' : '/zalo' ) . ' SĐT Tên, nội dung</code> để lưu vào CRM.';
		fireai_crm_telegram_send( implode( "\n", $lines ) );
	}

	return array( 'ok' => true );
}

/**
 * Lượt bấm gần nhất (chưa gắn khách) cùng loại với kênh khách vừa nhập qua bot.
 *
 * @return array|null [id, label]
 */
function fireai_crm_claim_recent_click( $source ) {
	global $wpdb;
	$type = 'zalo' === $source ? 'zalo' : ( 'hotline' === $source ? 'call' : '' );
	if ( '' === $type ) {
		return null;
	}
	$since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - FIREAI_CRM_CLICK_MATCH_MINUTES * MINUTE_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$click = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT id, source, campaign FROM ' . fireai_crm_table( 'clicks' ) . ' WHERE type = %s AND lead_id = 0 AND created_at >= %s ORDER BY created_at DESC, id DESC LIMIT 1',
			$type,
			$since
		)
	);
	if ( ! $click || ( 'website' === $click->source && '' === $click->campaign ) ) {
		return null;
	}
	return array(
		'id'    => (int) $click->id,
		'label' => fireai_crm_label( fireai_crm_sources(), $click->source ) . ( $click->campaign ? ' · ' . $click->campaign : '' ),
	);
}

function fireai_crm_link_click( $click_id, $lead_id ) {
	global $wpdb;
	$wpdb->update( fireai_crm_table( 'clicks' ), array( 'lead_id' => (int) $lead_id ), array( 'id' => (int) $click_id ) );
}

/**
 * Số lượt bấm Gọi / Zalo hôm nay, cho dòng thống kê ở trang Khách hàng.
 */
function fireai_crm_clicks_today() {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare( 'SELECT type, COUNT(*) AS c FROM ' . fireai_crm_table( 'clicks' ) . ' WHERE created_at >= %s GROUP BY type', current_time( 'Y-m-d' ) . ' 00:00:00' ),
		OBJECT_K
	);
	return array(
		'call' => isset( $rows['call'] ) ? (int) $rows['call']->c : 0,
		'zalo' => isset( $rows['zalo'] ) ? (int) $rows['zalo']->c : 0,
	);
}
