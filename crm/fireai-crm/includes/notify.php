<?php
/**
 * Báo khách mới về điện thoại: Telegram (tức thì, miễn phí) và email.
 * Chỉ báo khách tự động đổ về (form website, webhook quảng cáo), không báo khách Sale tự nhập
 * hay khách cũ được nhập lại.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'fireai_crm_lead_created', 'fireai_crm_notify_new_lead', 10, 3 );

function fireai_crm_notify_new_lead( $id, $row, $data = array() ) {
	if ( ! empty( $data['silent'] ) || ! empty( $row['created_by'] ) ) {
		return;
	}
	$lead = fireai_crm_get_lead( $id );
	if ( ! $lead ) {
		return;
	}
	$settings = fireai_crm_settings();
	if ( $settings['tg_token'] && $settings['tg_chat_id'] ) {
		fireai_crm_telegram_send( fireai_crm_telegram_message( $lead ) );
	}
	if ( $settings['notify_emails'] ) {
		fireai_crm_email_lead( $lead, $settings['notify_emails'] );
	}
}

function fireai_crm_telegram_message( $lead ) {
	$esc   = function ( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	};
	$lines = array( '🔥 <b>Khách mới – ' . $esc( fireai_crm_label( fireai_crm_sources(), $lead->source ) ) . '</b>' );

	$lines[] = '👤 ' . $esc( '' !== $lead->name ? $lead->name : '(Chưa có tên)' );
	if ( $lead->phone ) {
		$lines[] = '📞 ' . $esc( $lead->phone );
	}
	if ( $lead->email ) {
		$lines[] = '✉️ ' . $esc( $lead->email );
	}
	if ( $lead->campaign ) {
		$lines[] = '📣 ' . $esc( $lead->campaign );
	}
	if ( $lead->message ) {
		// Giữ xuống dòng giữa các trường form, cắt bớt nếu quá dài.
		$message = (string) $lead->message;
		if ( preg_match( '/^.{400}/su', $message, $m ) && strlen( $m[0] ) < strlen( $message ) ) {
			$message = $m[0] . '…';
		}
		$lines[] = '📝 ' . $esc( $message );
	}
	$lines[] = '';
	$lines[] = '<a href="' . $esc( admin_url( 'admin.php?page=fireai-crm&lead=' . (int) $lead->id ) ) . '">Mở trong CRM</a>';
	return implode( "\n", $lines );
}

/**
 * Gọi một hàm của Telegram Bot API.
 *
 * @return mixed|WP_Error Trường "result" của Telegram.
 */
function fireai_crm_telegram_api( $method, array $params = array() ) {
	$settings = fireai_crm_settings();
	if ( ! $settings['tg_token'] ) {
		return new WP_Error( 'fireai_crm_tg', 'Chưa nhập Bot Token.' );
	}
	$response = wp_remote_post(
		'https://api.telegram.org/bot' . $settings['tg_token'] . '/' . $method,
		array(
			'timeout' => 8,
			'body'    => $params,
		)
	);
	return fireai_crm_telegram_result( $response, true );
}

/**
 * @return true|WP_Error
 */
function fireai_crm_telegram_send( $text, $chat_id = null, array $extra = array() ) {
	$settings = fireai_crm_settings();
	$chat_id  = null === $chat_id ? $settings['tg_chat_id'] : $chat_id;
	if ( ! $settings['tg_token'] || ! $chat_id ) {
		return new WP_Error( 'fireai_crm_tg', 'Chưa nhập Bot Token hoặc Chat ID.' );
	}
	$result = fireai_crm_telegram_api(
		'sendMessage',
		array_merge(
			array(
				'chat_id'                  => $chat_id,
				'text'                     => $text,
				'parse_mode'               => 'HTML',
				'disable_web_page_preview' => 'true',
			),
			$extra
		)
	);
	return is_wp_error( $result ) ? $result : true;
}

/**
 * Tìm các nhóm / cuộc trò chuyện mà bot vừa nhận tin nhắn.
 *
 * @return array|WP_Error Danh sách [id => tên].
 */
function fireai_crm_telegram_find_chats() {
	$settings = fireai_crm_settings();
	if ( ! $settings['tg_token'] ) {
		return new WP_Error( 'fireai_crm_tg', 'Chưa nhập Bot Token.' );
	}
	// Khi bot đang nhận tin qua webhook, Telegram không cho đọc getUpdates: dùng các nhóm bot đã thấy.
	if ( get_option( 'fireai_crm_tg_webhook_secret' ) ) {
		return (array) get_option( 'fireai_crm_tg_seen_chats', array() );
	}
	$result = fireai_crm_telegram_api( 'getUpdates' );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$chats = array();
	foreach ( (array) $result as $update ) {
		foreach ( array( 'message', 'my_chat_member', 'channel_post', 'edited_message' ) as $type ) {
			if ( empty( $update[ $type ]['chat']['id'] ) ) {
				continue;
			}
			$chat = $update[ $type ]['chat'];
			$name = isset( $chat['title'] ) ? $chat['title'] : trim( ( isset( $chat['first_name'] ) ? $chat['first_name'] : '' ) . ' ' . ( isset( $chat['last_name'] ) ? $chat['last_name'] : '' ) );

			$chats[ (string) $chat['id'] ] = $name;
		}
	}
	return $chats;
}

function fireai_crm_telegram_result( $response, $want_result = false ) {
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'fireai_crm_tg', 'Không kết nối được Telegram: ' . $response->get_error_message() );
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['ok'] ) ) {
		$desc = isset( $body['description'] ) ? $body['description'] : 'HTTP ' . wp_remote_retrieve_response_code( $response );
		return new WP_Error( 'fireai_crm_tg', 'Telegram báo lỗi: ' . $desc );
	}
	return $want_result ? ( isset( $body['result'] ) ? $body['result'] : array() ) : true;
}

function fireai_crm_email_lead( $lead, $emails ) {
	$to = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $emails ) ) ) );
	if ( ! $to ) {
		return;
	}
	$name    = '' !== $lead->name ? $lead->name : '(Chưa có tên)';
	$subject = '[CRM] Khách mới: ' . $name . ( $lead->phone ? ' – ' . $lead->phone : '' );
	$body    = implode(
		"\n",
		array_filter(
			array(
				'Khách hàng: ' . $name,
				$lead->phone ? 'Điện thoại: ' . $lead->phone : '',
				$lead->email ? 'Email: ' . $lead->email : '',
				'Nguồn: ' . fireai_crm_label( fireai_crm_sources(), $lead->source ),
				$lead->campaign ? 'Chiến dịch: ' . $lead->campaign : '',
				$lead->message ? "\nNội dung:\n" . $lead->message : '',
				"\nMở trong CRM: " . admin_url( 'admin.php?page=fireai-crm&lead=' . (int) $lead->id ),
			)
		)
	);
	wp_mail( $to, $subject, $body );
}
