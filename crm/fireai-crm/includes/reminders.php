<?php
/**
 * Hẹn chăm sóc khách và bản tin nhắc việc mỗi sáng (Telegram + email).
 *
 * Bản tin gồm: khách quá hẹn, khách hẹn hôm nay, khách mới chưa xử lý (chưa có hẹn).
 * Chạy bằng WP-Cron nên cần website có người truy cập quanh giờ hẹn.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FIREAI_CRM_DIGEST_HOOK = 'fireai_crm_daily_digest';
// Số khách tối đa liệt kê cho mỗi nhóm trong tin nhắn (tránh vượt giới hạn 4096 ký tự của Telegram).
const FIREAI_CRM_DIGEST_LIMIT = 15;

add_action( FIREAI_CRM_DIGEST_HOOK, 'fireai_crm_send_digest' );

/**
 * Đặt lịch gửi bản tin hằng ngày theo giờ trong Cài đặt (giờ Việt Nam / múi giờ của website).
 */
function fireai_crm_schedule_digest() {
	fireai_crm_unschedule_digest();
	$settings = fireai_crm_settings();
	if ( empty( $settings['remind_on'] ) ) {
		return;
	}
	$time = preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $settings['remind_time'] ) ? $settings['remind_time'] : '08:00';
	$next = new DateTime( 'today ' . $time, wp_timezone() );
	if ( $next->getTimestamp() <= time() ) {
		$next->modify( '+1 day' );
	}
	wp_schedule_event( $next->getTimestamp(), 'daily', FIREAI_CRM_DIGEST_HOOK );
}

function fireai_crm_unschedule_digest() {
	wp_clear_scheduled_hook( FIREAI_CRM_DIGEST_HOOK );
}

/**
 * Khách cần chăm sóc, chia 3 nhóm: overdue (quá hẹn), today (hẹn hôm nay), fresh (mới, chưa có hẹn).
 */
function fireai_crm_followups( $limit = FIREAI_CRM_DIGEST_LIMIT ) {
	global $wpdb;
	$table  = fireai_crm_table( 'leads' );
	$today  = current_time( 'Y-m-d' );
	$open   = "status NOT IN ('" . implode( "','", array_map( 'esc_sql', fireai_crm_closed_statuses() ) ) . "')";
	$fields = 'id, name, phone, status, next_contact, created_at';
	$limit  = (int) $limit;

	return array(
		'overdue' => $wpdb->get_results( $wpdb->prepare( "SELECT {$fields} FROM {$table} WHERE {$open} AND next_contact < %s ORDER BY next_contact ASC LIMIT {$limit}", $today ) ), // phpcs:ignore
		'today'   => $wpdb->get_results( $wpdb->prepare( "SELECT {$fields} FROM {$table} WHERE {$open} AND next_contact = %s ORDER BY id ASC LIMIT {$limit}", $today ) ), // phpcs:ignore
		'fresh'   => $wpdb->get_results( "SELECT {$fields} FROM {$table} WHERE status = 'new' AND next_contact IS NULL ORDER BY created_at DESC LIMIT {$limit}" ), // phpcs:ignore
	);
}

/**
 * Đếm số khách mỗi nhóm (không giới hạn), cho dòng thống kê ở trang Khách hàng và bản tin.
 */
function fireai_crm_followup_counts() {
	global $wpdb;
	$table = fireai_crm_table( 'leads' );
	$today = current_time( 'Y-m-d' );
	$open  = "status NOT IN ('" . implode( "','", array_map( 'esc_sql', fireai_crm_closed_statuses() ) ) . "')";
	return array(
		'overdue' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$open} AND next_contact < %s", $today ) ), // phpcs:ignore
		'today'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$open} AND next_contact = %s", $today ) ), // phpcs:ignore
		'fresh'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'new' AND next_contact IS NULL" ), // phpcs:ignore
	);
}

/**
 * Nội dung bản tin (HTML cho Telegram). Trả về chuỗi rỗng nếu không có việc gì.
 */
function fireai_crm_digest_message() {
	$groups = fireai_crm_followups();
	$counts = fireai_crm_followup_counts();
	if ( ! array_sum( $counts ) ) {
		return '';
	}

	$esc    = function ( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	};
	$line   = function ( $lead, $extra ) use ( $esc ) {
		$name = '' !== $lead->name ? $lead->name : '(Chưa có tên)';
		return '• <a href="' . $esc( admin_url( 'admin.php?page=fireai-crm&lead=' . (int) $lead->id ) ) . '">' . $esc( $name ) . '</a>'
			. ( $lead->phone ? ' – ' . $esc( $lead->phone ) : '' ) . ' – ' . $esc( $extra );
	};
	$titles = array(
		'overdue' => '🔴 <b>Quá hẹn</b>',
		'today'   => '🟡 <b>Hẹn hôm nay</b>',
		'fresh'   => '🆕 <b>Khách mới chưa xử lý</b>',
	);

	$lines = array( '📅 <b>Việc chăm sóc khách – ' . $esc( current_time( 'd/m/Y' ) ) . '</b>' );
	foreach ( $titles as $key => $title ) {
		if ( ! $counts[ $key ] ) {
			continue;
		}
		$lines[] = '';
		$lines[] = $title . ' (' . $counts[ $key ] . ')';
		foreach ( $groups[ $key ] as $lead ) {
			$status = fireai_crm_label( fireai_crm_statuses(), $lead->status );
			if ( 'overdue' === $key ) {
				$extra = $status . ', hẹn ' . mysql2date( 'd/m', $lead->next_contact );
			} elseif ( 'fresh' === $key ) {
				$extra = 'vào ' . mysql2date( 'd/m H:i', $lead->created_at );
			} else {
				$extra = $status;
			}
			$lines[] = $line( $lead, $extra );
		}
		if ( $counts[ $key ] > count( $groups[ $key ] ) ) {
			$lines[] = '… và ' . ( $counts[ $key ] - count( $groups[ $key ] ) ) . ' khách khác';
		}
	}
	$lines[] = '';
	$lines[] = '<a href="' . $esc( admin_url( 'admin.php?page=fireai-crm&follow=due' ) ) . '">Mở danh sách cần chăm sóc</a>';
	return implode( "\n", $lines );
}

/**
 * @return bool|WP_Error true nếu đã gửi, false nếu không có việc gì.
 */
function fireai_crm_send_digest() {
	$message = fireai_crm_digest_message();
	if ( '' === $message ) {
		return false;
	}
	$settings = fireai_crm_settings();
	$result   = false;
	if ( $settings['tg_token'] && $settings['tg_chat_id'] ) {
		$result = fireai_crm_telegram_send( $message );
	}
	if ( $settings['notify_emails'] ) {
		$to = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $settings['notify_emails'] ) ) ) );
		if ( $to ) {
			$text = html_entity_decode( wp_strip_all_tags( str_replace( '</a>', '', $message ) ), ENT_QUOTES, 'UTF-8' );
			wp_mail( $to, '[CRM] Việc chăm sóc khách ' . current_time( 'd/m/Y' ), $text . "\n" . admin_url( 'admin.php?page=fireai-crm&follow=due' ) );
			$result = is_wp_error( $result ) ? $result : true;
		}
	}
	return $result;
}
