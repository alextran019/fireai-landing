<?php
/**
 * Nhắc việc qua Telegram (+ email cho bản tin sáng):
 * - Bản tin mỗi sáng: khách quá hẹn, hẹn hôm nay, khách mới chưa xử lý.
 * - Nhắc phản hồi: khách điền form quá X phút (mặc định 15) chưa ai liên hệ, nhắc gấp lần 2 sau Y phút (60).
 *   Chỉ tính trong giờ làm việc; khách vào ngoài giờ được tính từ lúc mở cửa hôm sau.
 * Chạy bằng WP-Cron nên cần website có người truy cập.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FIREAI_CRM_DIGEST_HOOK = 'fireai_crm_daily_digest';
// Số khách tối đa liệt kê cho mỗi nhóm trong tin nhắn (tránh vượt giới hạn 4096 ký tự của Telegram).
const FIREAI_CRM_DIGEST_LIMIT = 15;

const FIREAI_CRM_SLA_HOOK = 'fireai_crm_check_response';

add_action( FIREAI_CRM_DIGEST_HOOK, 'fireai_crm_send_digest' );
add_action( FIREAI_CRM_SLA_HOOK, 'fireai_crm_check_response_times' );
add_filter( 'cron_schedules', 'fireai_crm_cron_schedules' );

function fireai_crm_cron_schedules( $schedules ) {
	$schedules['fireai_crm_5min'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => 'FireAI CRM: 5 phút',
	);
	return $schedules;
}

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

/**
 * Đặt lại cả bản tin sáng và việc kiểm tra thời gian phản hồi (gọi khi kích hoạt / lưu cài đặt).
 */
function fireai_crm_schedule_reminders() {
	fireai_crm_schedule_digest();
	wp_clear_scheduled_hook( FIREAI_CRM_SLA_HOOK );
	$settings = fireai_crm_settings();
	if ( ! empty( $settings['sla_on'] ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'fireai_crm_5min', FIREAI_CRM_SLA_HOOK );
	}
}

function fireai_crm_unschedule_digest() {
	wp_clear_scheduled_hook( FIREAI_CRM_DIGEST_HOOK );
	wp_clear_scheduled_hook( FIREAI_CRM_SLA_HOOK );
}

/**
 * Giờ làm việc trong ngày của $day: [mở cửa, đóng cửa].
 */
function fireai_crm_work_hours( DateTime $day ) {
	$settings = fireai_crm_settings();
	$valid    = function ( $t, $default ) {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $t ) ? $t : $default;
	};
	$open     = new DateTime( $day->format( 'Y-m-d' ) . ' ' . $valid( $settings['work_start'], '08:00' ), wp_timezone() );
	$close    = new DateTime( $day->format( 'Y-m-d' ) . ' ' . $valid( $settings['work_end'], '20:00' ), wp_timezone() );
	return array( $open, $close );
}

/**
 * Mốc bắt đầu tính thời gian phản hồi: lúc khách vào nếu trong giờ làm việc,
 * ngược lại là giờ mở cửa gần nhất sau đó.
 */
function fireai_crm_sla_start( $created_at ) {
	$created              = new DateTime( $created_at, wp_timezone() );
	list( $open, $close ) = fireai_crm_work_hours( $created );
	if ( $created < $open ) {
		return $open;
	}
	if ( $created >= $close ) {
		$next = ( clone $created )->modify( '+1 day' );
		return fireai_crm_work_hours( $next )[0];
	}
	return $created;
}

/**
 * Số phút làm việc tính từ lúc khách vào tới lúc liên hệ (dùng cho Báo cáo).
 */
function fireai_crm_response_minutes( $created_at, $contacted_at ) {
	$start = fireai_crm_sla_start( $created_at );
	$end   = new DateTime( $contacted_at, wp_timezone() );
	return max( 0, (int) floor( ( $end->getTimestamp() - $start->getTimestamp() ) / MINUTE_IN_SECONDS ) );
}

/**
 * Chạy mỗi 5 phút: nhắc khách điền form chưa được liên hệ.
 *
 * @return int Số khách được nhắc lần này.
 */
function fireai_crm_check_response_times() {
	global $wpdb;
	$settings = fireai_crm_settings();
	if ( empty( $settings['sla_on'] ) || ! $settings['tg_token'] || ! $settings['tg_chat_id'] ) {
		return 0;
	}
	$now                  = new DateTime( 'now', wp_timezone() );
	list( $open, $close ) = fireai_crm_work_hours( $now );
	if ( $now < $open || $now >= $close ) {
		return 0;
	}
	$first  = max( 1, (int) $settings['sla_first'] );
	$second = max( $first + 1, (int) $settings['sla_second'] );
	$table  = fireai_crm_table( 'leads' );
	$since  = ( clone $now )->modify( '-3 days' )->format( 'Y-m-d H:i:s' );
	$rows   = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, name, phone, source, campaign, created_at, sla_level FROM {$table} WHERE first_contact_at IS NULL AND status = 'new' AND created_by = 0 AND sla_level < 2 AND created_at >= %s ORDER BY created_at ASC LIMIT 30", // phpcs:ignore
			$since
		)
	);

	$groups = array(
		1 => array(),
		2 => array(),
	);
	foreach ( $rows as $lead ) {
		$waited = (int) floor( ( $now->getTimestamp() - fireai_crm_sla_start( $lead->created_at )->getTimestamp() ) / MINUTE_IN_SECONDS );
		if ( $waited >= $second ) {
			$groups[2][] = $lead;
		} elseif ( $waited >= $first && (int) $lead->sla_level < 1 ) {
			$groups[1][] = $lead;
		}
	}
	if ( ! $groups[1] && ! $groups[2] ) {
		return 0;
	}

	$esc    = function ( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	};
	$titles = array(
		1 => '⏰ <b>Khách điền form ' . $first . ' phút chưa được liên hệ</b>',
		2 => '🚨 <b>Quá ' . $second . ' phút vẫn chưa liên hệ</b>',
	);
	$lines  = array();
	foreach ( $groups as $level => $leads ) {
		if ( ! $leads ) {
			continue;
		}
		if ( $lines ) {
			$lines[] = '';
		}
		$lines[] = $titles[ $level ] . ' (' . count( $leads ) . ')';
		foreach ( $leads as $lead ) {
			$lines[] = '• <a href="' . $esc( admin_url( 'admin.php?page=fireai-crm&lead=' . (int) $lead->id ) ) . '">' . $esc( '' !== $lead->name ? $lead->name : '(Chưa có tên)' ) . '</a>'
				. ( $lead->phone ? ' – ' . $esc( $lead->phone ) : '' )
				. ' – ' . $esc( fireai_crm_label( fireai_crm_sources(), $lead->source ) . ( $lead->campaign ? ' · ' . $lead->campaign : '' ) )
				. ' – vào ' . $esc( mysql2date( 'H:i d/m', $lead->created_at ) );
		}
	}
	$lines[] = '';
	$lines[] = '👉 Gọi ngay, rồi đổi trạng thái hoặc ghi chú trên CRM để dừng nhắc.';

	$result = fireai_crm_telegram_send( implode( "\n", $lines ) );
	if ( is_wp_error( $result ) ) {
		return 0;
	}
	foreach ( $groups as $level => $leads ) {
		foreach ( $leads as $lead ) {
			$wpdb->update( $table, array( 'sla_level' => $level ), array( 'id' => (int) $lead->id ) );
		}
	}
	return count( $groups[1] ) + count( $groups[2] );
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
