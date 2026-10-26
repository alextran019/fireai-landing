<?php
/**
 * Hàm dùng chung: bảng dữ liệu, danh mục, chuẩn hóa và lưu khách hàng.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Quyền xem và nhập khách hàng (Sale + Admin).
define( 'FIREAI_CRM_CAP', 'fireai_crm_access' );
// Quyền quản lý: cài đặt, xuất file, xóa, giao khách (chỉ Admin).
define( 'FIREAI_CRM_MANAGE_CAP', 'fireai_crm_manage' );

function fireai_crm_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'crm_' . $name;
}

function fireai_crm_sources() {
	return array(
		'website'  => 'Website',
		'facebook' => 'Facebook',
		'google'   => 'Google',
		'tiktok'   => 'TikTok',
		'zalo'     => 'Zalo',
		'hotline'  => 'Hotline',
		'referral' => 'Giới thiệu',
		'other'    => 'Khác',
	);
}

/**
 * Các bước theo quy trình nhà phân phối: tiếp nhận → báo giá → ký hợp đồng → chuyển tiền.
 */
function fireai_crm_statuses() {
	return array(
		'new'      => 'Mới tiếp nhận',
		'quoted'   => 'Đã gửi báo giá',
		'contract' => 'Đã ký hợp đồng',
		'paid'     => 'Đã chuyển tiền',
		'lost'     => 'Không thành công',
		'junk'     => 'Sai số / Rác',
	);
}

/**
 * Thứ tự bước trong quy trình (dùng cho báo cáo: khách đã ký thì cũng đã qua bước báo giá).
 * Không thành công / Sai số trả về -1.
 */
function fireai_crm_status_rank( $status ) {
	$ranks = array(
		'new'      => 0,
		'quoted'   => 1,
		'contract' => 2,
		'paid'     => 3,
	);
	return isset( $ranks[ $status ] ) ? $ranks[ $status ] : -1;
}

/**
 * Trạng thái đã kết thúc: không cần nhắc chăm sóc nữa.
 */
function fireai_crm_closed_statuses() {
	return array( 'lost', 'junk' );
}

function fireai_crm_money( $value ) {
	return number_format( (float) $value, 0, ',', '.' ) . ' đ';
}

/**
 * Ngày dạng Y-m-d hợp lệ, hoặc chuỗi rỗng.
 */
function fireai_crm_sanitize_date( $date ) {
	$date = trim( (string) $date );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && checkdate( (int) substr( $date, 5, 2 ), (int) substr( $date, 8, 2 ), (int) substr( $date, 0, 4 ) ) ? $date : '';
}

function fireai_crm_label( $list, $key ) {
	return isset( $list[ $key ] ) ? $list[ $key ] : $key;
}

function fireai_crm_settings() {
	$settings = get_option( 'fireai_crm_settings' );
	return wp_parse_args(
		is_array( $settings ) ? $settings : array(),
		array(
			'webhook_key'   => '',
			'cf7_enabled'   => 1,
			'sale_own_only' => 0,
			'tg_token'      => '',
			'tg_chat_id'    => '',
			'notify_emails' => '',
			'click_track'   => 1,
			'click_notify'  => 0,
			'remind_on'     => 1,
			'remind_time'   => '08:00',
			'sla_on'        => 1,
			'sla_first'     => 15,
			'sla_second'    => 60,
			'work_start'    => '08:00',
			'work_end'      => '20:00',
		)
	);
}

/**
 * Chuẩn hóa số điện thoại về dạng 0xxxxxxxxx để so trùng.
 */
function fireai_crm_normalize_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( strlen( $digits ) === 11 && strpos( $digits, '84' ) === 0 ) {
		$digits = '0' . substr( $digits, 2 );
	}
	return substr( $digits, 0, 32 );
}

function fireai_crm_can_manage() {
	return current_user_can( FIREAI_CRM_MANAGE_CAP );
}

/**
 * Sale chỉ thấy khách của mình khi bật tùy chọn "sale_own_only".
 */
function fireai_crm_restrict_to_own() {
	$settings = fireai_crm_settings();
	return ! fireai_crm_can_manage() && ! empty( $settings['sale_own_only'] );
}

function fireai_crm_can_access_lead( $lead ) {
	if ( ! $lead || ! current_user_can( FIREAI_CRM_CAP ) ) {
		return false;
	}
	if ( ! fireai_crm_restrict_to_own() ) {
		return true;
	}
	$me = get_current_user_id();
	return (int) $lead->assigned_to === $me || (int) $lead->created_by === $me;
}

function fireai_crm_user_name( $id ) {
	if ( ! $id ) {
		return '—';
	}
	$user = get_userdata( $id );
	return $user ? $user->display_name : '#' . $id;
}

function fireai_crm_get_lead( $id ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . fireai_crm_table( 'leads' ) . ' WHERE id = %d', $id )
	);
}

/**
 * Lưu một khách hàng mới. Trả về ID hoặc 0 nếu lỗi.
 */
function fireai_crm_insert_lead( array $data ) {
	global $wpdb;

	$sources  = fireai_crm_sources();
	$statuses = fireai_crm_statuses();
	$now      = current_time( 'mysql' );

	$row = array(
		'name'         => sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' ),
		'phone'        => fireai_crm_normalize_phone( isset( $data['phone'] ) ? $data['phone'] : '' ),
		'email'        => sanitize_email( isset( $data['email'] ) ? $data['email'] : '' ),
		'source'       => isset( $data['source'], $sources[ $data['source'] ] ) ? $data['source'] : 'other',
		'campaign'     => sanitize_text_field( isset( $data['campaign'] ) ? $data['campaign'] : '' ),
		'status'       => isset( $data['status'], $statuses[ $data['status'] ] ) ? $data['status'] : 'new',
		'assigned_to'  => isset( $data['assigned_to'] ) ? absint( $data['assigned_to'] ) : 0,
		'message'      => sanitize_textarea_field( isset( $data['message'] ) ? $data['message'] : '' ),
		'next_contact' => ! empty( $data['next_contact'] ) && fireai_crm_sanitize_date( $data['next_contact'] ) ? $data['next_contact'] : null,
		'value'        => isset( $data['value'] ) ? absint( $data['value'] ) : 0,
		'external_ref' => sanitize_text_field( isset( $data['external_ref'] ) ? $data['external_ref'] : '' ),
		'raw'          => isset( $data['raw'] ) ? wp_json_encode( $data['raw'], JSON_UNESCAPED_UNICODE ) : null,
		'created_by'   => isset( $data['created_by'] ) ? absint( $data['created_by'] ) : get_current_user_id(),
		'created_at'   => ! empty( $data['created_at'] ) ? $data['created_at'] : $now,
		'updated_at'   => $now,
	);

	if ( '' === $row['name'] && '' === $row['phone'] && '' === $row['email'] ) {
		return 0;
	}
	// Khách do người nhập tay (hotline, bot) coi như đã liên hệ; khách tự đổ về thì chờ Sale liên hệ.
	$row['first_contact_at'] = $row['created_by'] || ! empty( $data['contacted'] ) ? $now : null;

	$ok = $wpdb->insert( fireai_crm_table( 'leads' ), $row );
	if ( ! $ok ) {
		return 0;
	}

	$id = (int) $wpdb->insert_id;
	do_action( 'fireai_crm_lead_created', $id, $row, $data );
	return $id;
}

/**
 * Ghi nhận lần liên hệ đầu tiên (đổi trạng thái, ghi chú, đặt hẹn): dừng nhắc phản hồi
 * và dùng để tính thời gian phản hồi trong Báo cáo.
 */
function fireai_crm_mark_contacted( $lead_id ) {
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . fireai_crm_table( 'leads' ) . ' SET first_contact_at = %s WHERE id = %d AND first_contact_at IS NULL',
			current_time( 'mysql' ),
			$lead_id
		)
	);
}

function fireai_crm_add_note( $lead_id, $content, $user_id = null ) {
	global $wpdb;
	$content = trim( (string) $content );
	if ( '' === $content ) {
		return;
	}
	$wpdb->insert(
		fireai_crm_table( 'notes' ),
		array(
			'lead_id'    => (int) $lead_id,
			'user_id'    => null === $user_id ? get_current_user_id() : (int) $user_id,
			'content'    => sanitize_textarea_field( $content ),
			'created_at' => current_time( 'mysql' ),
		)
	);
	$wpdb->update( fireai_crm_table( 'leads' ), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $lead_id ) );
}

/**
 * Các khách hàng khác có cùng số điện thoại.
 */
function fireai_crm_find_by_phone( $phone, $exclude_id = 0, $limit = 5 ) {
	global $wpdb;
	$phone = fireai_crm_normalize_phone( $phone );
	if ( strlen( $phone ) < 8 ) {
		return array();
	}
	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT id, name, phone, status, source, created_at FROM ' . fireai_crm_table( 'leads' ) . ' WHERE phone = %s AND id <> %d ORDER BY created_at DESC LIMIT %d',
			$phone,
			$exclude_id,
			$limit
		)
	);
}

/**
 * Đoán nguồn quảng cáo và chiến dịch từ các tham số utm_*, fbclid, gclid...
 * Trả về source rỗng nếu không có dấu hiệu quảng cáo nào.
 */
function fireai_crm_source_from_params( array $params ) {
	$get = function ( $key ) use ( $params ) {
		return isset( $params[ $key ] ) && is_scalar( $params[ $key ] ) ? trim( (string) $params[ $key ] ) : '';
	};

	$utm    = strtolower( $get( 'utm_source' ) );
	$source = '';
	if ( '' !== $get( 'gclid' ) || false !== strpos( $utm, 'google' ) ) {
		$source = 'google';
	} elseif ( '' !== $get( 'fbclid' ) || preg_match( '/^(fb|facebook|ig|instagram|meta)/', $utm ) ) {
		$source = 'facebook';
	} elseif ( '' !== $get( 'ttclid' ) || false !== strpos( $utm, 'tiktok' ) ) {
		$source = 'tiktok';
	} elseif ( false !== strpos( $utm, 'zalo' ) ) {
		$source = 'zalo';
	} elseif ( '' !== $utm ) {
		$source = 'other';
	}

	// Chiến dịch + mẫu quảng cáo (utm_content), VD: "bao-chay-q4 · M1A".
	$campaign = implode( ' · ', array_filter( array( $get( 'utm_campaign' ), $get( 'utm_content' ) ) ) );

	return array(
		'source'   => $source,
		'campaign' => sanitize_text_field( $campaign ),
	);
}

/**
 * Như trên nhưng đọc từ URL trang khách gửi form.
 */
function fireai_crm_detect_source( $url ) {
	$query = wp_parse_url( (string) $url, PHP_URL_QUERY );
	$params = array();
	if ( $query ) {
		parse_str( $query, $params );
	}
	return fireai_crm_source_from_params( $params );
}

/**
 * Trường form ẩn chỉ dùng để theo dõi quảng cáo (utm, mã click, link trang, mã sự kiện CAPI):
 * không đưa vào nội dung, vẫn giữ trong "Dữ liệu gốc".
 */
function fireai_crm_is_tracking_key( $key ) {
	return (bool) preg_match(
		'/^(utm_[a-z]+|fbclid|gclid|ttclid|fbc|fbp|gbraid|wbraid|page_url|page_uri|landing_page|landing_url|current_url|source_url|referrer|referer|capi_[a-z_]+|event_id|external_id)$/',
		strtolower( (string) $key )
	);
}

/**
 * Nhận diện tên, SĐT, email, nội dung từ các trường form có tên bất kỳ.
 * Các trường còn lại được ghép vào nội dung để Sale không bỏ sót thông tin.
 * Kèm theo source/campaign (rỗng nếu form không có utm_*).
 */
function fireai_crm_map_fields( array $fields ) {
	$out   = array(
		'name'    => '',
		'phone'   => '',
		'email'   => '',
		'message' => '',
	);
	$extra = array();

	$tracking        = fireai_crm_source_from_params( $fields );
	$out['source']   = $tracking['source'];
	$out['campaign'] = $tracking['campaign'];

	foreach ( $fields as $key => $value ) {
		$key = (string) $key;
		if ( '' === $key || '_' === $key[0] || 0 === strpos( $key, 'cfdb7' ) || false !== strpos( $key, 'cfdb7_file' ) || fireai_crm_is_tracking_key( $key ) ) {
			continue;
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
		}
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) {
			continue;
		}

		$k = strtolower( $key );
		if ( '' === $out['email'] && ( false !== strpos( $k, 'mail' ) || is_email( $value ) ) ) {
			$out['email'] = $value;
		} elseif ( '' === $out['phone'] && ( preg_match( '/phone|tel|sdt|so-?dien|so_?dien|dien-?thoai|dien_?thoai|mobile|hotline/', $k ) || preg_match( '/^\+?[\d\s.\-()]{9,15}$/', $value ) ) ) {
			$out['phone'] = $value;
		} elseif ( '' === $out['name'] && preg_match( '/(^|[-_])(name|ten|ho-?ten|ho_?ten|fullname|khach-?hang|khach_?hang)($|[-_])/', $k ) ) {
			$out['name'] = $value;
		} elseif ( '' === $out['message'] && preg_match( '/message|noi-?dung|noi_?dung|ghi-?chu|ghi_?chu|yeu-?cau|yeu_?cau|content|loi-?nhan|note/', $k ) ) {
			$out['message'] = $value;
		} else {
			$extra[] = $key . ': ' . $value;
		}
	}

	if ( $extra ) {
		$out['message'] = trim( $out['message'] . "\n\n" . implode( "\n", $extra ) );
	}
	return $out;
}
