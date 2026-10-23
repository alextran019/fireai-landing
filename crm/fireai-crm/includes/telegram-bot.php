<?php
/**
 * Nhập khách nhanh qua bot Telegram: người cầm hotline / Zalo gõ
 * "/kh 0912345678 Anh Nam, hỏi báo cháy nhà xưởng" là khách vào CRM.
 *
 * Bot nhận tin qua webhook: POST /wp-json/fireai-crm/v1/telegram
 * (Telegram gửi kèm mã bí mật trong header X-Telegram-Bot-Api-Secret-Token).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'fireai_crm_register_telegram_route' );

function fireai_crm_register_telegram_route() {
	register_rest_route(
		'fireai-crm/v1',
		'/telegram',
		array(
			'methods'             => 'POST',
			'callback'            => 'fireai_crm_telegram_webhook',
			'permission_callback' => 'fireai_crm_telegram_webhook_permission',
		)
	);
}

function fireai_crm_telegram_webhook_permission( WP_REST_Request $request ) {
	$secret = (string) get_option( 'fireai_crm_tg_webhook_secret' );
	$given  = (string) $request->get_header( 'x-telegram-bot-api-secret-token' );
	if ( '' === $secret || '' === $given || ! hash_equals( $secret, $given ) ) {
		return new WP_Error( 'fireai_crm_forbidden', 'Forbidden', array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Bật nhận tin: báo cho Telegram địa chỉ webhook của website.
 *
 * @return true|WP_Error
 */
function fireai_crm_telegram_enable_bot() {
	$secret = wp_generate_password( 40, false );
	$result = fireai_crm_telegram_api(
		'setWebhook',
		array(
			'url'             => rest_url( 'fireai-crm/v1/telegram' ),
			'secret_token'    => $secret,
			'allowed_updates' => wp_json_encode( array( 'message', 'my_chat_member' ) ),
		)
	);
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	update_option( 'fireai_crm_tg_webhook_secret', $secret, false );
	return true;
}

/**
 * @return true|WP_Error
 */
function fireai_crm_telegram_disable_bot() {
	$result = fireai_crm_telegram_api( 'deleteWebhook' );
	delete_option( 'fireai_crm_tg_webhook_secret' );
	return is_wp_error( $result ) ? $result : true;
}

/**
 * Tình trạng webhook theo Telegram (dùng để báo lỗi trong trang Cài đặt).
 *
 * @return array|WP_Error
 */
function fireai_crm_telegram_bot_status() {
	return fireai_crm_telegram_api( 'getWebhookInfo' );
}

function fireai_crm_telegram_webhook( WP_REST_Request $request ) {
	$update = $request->get_json_params();
	if ( ! is_array( $update ) ) {
		return array( 'ok' => true );
	}

	// Ghi nhớ nhóm mà bot được thêm vào, để nút "Tìm Chat ID" vẫn dùng được khi webhook đang bật.
	foreach ( array( 'message', 'my_chat_member' ) as $type ) {
		if ( ! empty( $update[ $type ]['chat']['id'] ) && 'private' !== ( isset( $update[ $type ]['chat']['type'] ) ? $update[ $type ]['chat']['type'] : '' ) ) {
			$seen = (array) get_option( 'fireai_crm_tg_seen_chats', array() );

			$seen[ (string) $update[ $type ]['chat']['id'] ] = isset( $update[ $type ]['chat']['title'] ) ? $update[ $type ]['chat']['title'] : '';
			update_option( 'fireai_crm_tg_seen_chats', array_slice( $seen, -10, null, true ), false );
		}
	}

	if ( empty( $update['message']['text'] ) || empty( $update['message']['chat']['id'] ) || empty( $update['message']['from']['id'] ) ) {
		return array( 'ok' => true );
	}
	fireai_crm_telegram_handle_message( $update['message'] );

	// Luôn trả 200 để Telegram không gửi lại tin.
	return array( 'ok' => true );
}

function fireai_crm_telegram_handle_message( array $message ) {
	$settings = fireai_crm_settings();
	$chat_id  = (string) $message['chat']['id'];
	$is_dm    = isset( $message['chat']['type'] ) && 'private' === $message['chat']['type'];
	$text     = trim( (string) $message['text'] );
	$reply    = array( 'reply_to_message_id' => (int) $message['message_id'] );

	// Trong nhóm chỉ xử lý lệnh (bắt đầu bằng "/"), tin trò chuyện thường bỏ qua.
	if ( ! $is_dm && 0 !== strpos( $text, '/' ) ) {
		return;
	}
	// Chỉ nhận từ nhóm Sale đã cài đặt, hoặc nhắn riêng từ thành viên của nhóm đó.
	if ( ! $is_dm && $chat_id !== (string) $settings['tg_chat_id'] ) {
		return;
	}
	if ( $is_dm && ! fireai_crm_telegram_is_team_member( (int) $message['from']['id'] ) ) {
		fireai_crm_telegram_send( 'Bạn chưa có quyền dùng bot này. Hãy nhờ quản lý thêm bạn vào nhóm Sale trên Telegram.', $chat_id );
		return;
	}

	$command = '';
	if ( preg_match( '#^/([a-z_]+)(?:@\w+)?\s*#i', $text, $m ) ) {
		$command = strtolower( $m[1] );
		$text    = trim( substr( $text, strlen( $m[0] ) ) );
	}

	if ( in_array( $command, array( 'start', 'help', 'huongdan' ), true ) || ( '' === $text && '' !== $command ) ) {
		fireai_crm_telegram_send( fireai_crm_telegram_help(), $chat_id, $reply );
		return;
	}
	if ( '' !== $command && ! in_array( $command, array( 'kh', 'khach', 'goi', 'hotline', 'zalo' ), true ) ) {
		return;
	}

	$lead = fireai_crm_parse_quick_lead( $text, 'zalo' === $command ? 'zalo' : '' );
	if ( '' === $lead['phone'] ) {
		fireai_crm_telegram_send( '⚠️ Chưa thấy số điện thoại. Ví dụ:' . "\n" . '<code>/kh 0912345678 Anh Nam, hỏi báo cháy nhà xưởng</code>', $chat_id, $reply );
		return;
	}

	$from    = $message['from'];
	$tg_name = trim( ( isset( $from['first_name'] ) ? $from['first_name'] : '' ) . ' ' . ( isset( $from['last_name'] ) ? $from['last_name'] : '' ) );
	$wp_user = fireai_crm_user_by_telegram( $from );
	$dupes   = fireai_crm_find_by_phone( $lead['phone'] );
	$click   = fireai_crm_claim_recent_click( $lead['source'] );

	$id = fireai_crm_insert_lead(
		array(
			'name'        => $lead['name'],
			'phone'       => $lead['phone'],
			'message'     => $lead['message'],
			'source'      => $lead['source'],
			'campaign'    => $click ? $click['label'] : '',
			// Để trống người phụ trách: Sale sẽ nhận chăm sóc. Chỉ ghi lại ai đã nhập.
			'assigned_to' => 0,
			'created_by'  => $wp_user,
			'silent'      => true,
			'raw'         => array(
				'telegram' => array(
					'from' => $tg_name . ( isset( $from['username'] ) ? ' (@' . $from['username'] . ')' : '' ),
					'text' => $message['text'],
				),
			),
		)
	);
	if ( ! $id ) {
		fireai_crm_telegram_send( '❌ Không lưu được khách, hãy thử lại.', $chat_id, $reply );
		return;
	}
	if ( $click ) {
		fireai_crm_link_click( $click['id'], $id );
	}
	fireai_crm_add_note( $id, 'Nhập qua Telegram bởi ' . $tg_name, $wp_user );

	$esc   = function ( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	};
	$saved = fireai_crm_get_lead( $id );
	$extra = array( '✍️ Nhập bởi: ' . $esc( $tg_name ) );
	if ( $dupes ) {
		$extra[] = '⚠️ <b>Số này đã có ' . count( $dupes ) . ' lần trong CRM</b>, gần nhất: ' . $esc( ( $dupes[0]->name ? $dupes[0]->name : '(Chưa có tên)' ) . ' – ' . mysql2date( 'd/m/Y', $dupes[0]->created_at ) . ' – ' . fireai_crm_label( fireai_crm_statuses(), $dupes[0]->status ) );
	}
	$card = fireai_crm_telegram_message( $saved, $extra );

	if ( $is_dm ) {
		// Nhắn riêng: xác nhận cho người trực máy, và báo cả nhóm Sale như khách từ form.
		fireai_crm_telegram_send( '✅ Đã lưu khách <b>' . $esc( '' !== $saved->name ? $saved->name : $saved->phone ) . '</b> vào CRM và báo nhóm Sale.', $chat_id, $reply );
		fireai_crm_telegram_send( $card );
	} else {
		fireai_crm_telegram_send( '✅ Đã lưu vào CRM' . "\n\n" . $card, $chat_id, $reply );
	}
}

function fireai_crm_telegram_help() {
	return implode(
		"\n",
		array(
			'<b>Nhập khách vào CRM</b>',
			'',
			'Khách gọi hotline:',
			'<code>/kh 0912345678 Anh Nam, hỏi báo cháy nhà xưởng</code>',
			'',
			'Khách nhắn Zalo:',
			'<code>/zalo 0912345678 Chị Lan, cần báo giá 3 tầng</code>',
			'',
			'Sau số điện thoại: tên khách, dấu phẩy, rồi nội dung.',
			'Nhắn riêng cho bot thì không cần gõ /kh.',
		)
	);
}

/**
 * Tách số điện thoại, tên, nội dung từ một dòng gõ nhanh.
 * "0912 345 678 Anh Nam, hỏi báo cháy" → phone 0912345678, name "Anh Nam", message "hỏi báo cháy".
 */
function fireai_crm_parse_quick_lead( $text, $source = '' ) {
	$text = trim( (string) $text );
	if ( '' === $source ) {
		$source = 'hotline';
		if ( preg_match( '/^(#?zalo)\b[\s:,\-]*/iu', $text, $m ) ) {
			$source = 'zalo';
			$text   = trim( substr( $text, strlen( $m[0] ) ) );
		}
	}

	$phone = '';
	if ( preg_match( '/(?:\+?84|0)(?:[\s.\-]?\d){8,10}/u', $text, $m ) ) {
		$phone = fireai_crm_normalize_phone( $m[0] );
		$text  = trim( str_replace( $m[0], ' ', $text ) );
	}
	$text = trim( preg_replace( '/^[\s,;:\-–]+/u', '', $text ) );

	$name    = '';
	$message = $text;
	$parts   = preg_split( '/\s*(?:,|\n| - | – )\s*/u', $text, 2 );
	if ( count( $parts ) === 2 || ( '' !== $parts[0] && strlen( $parts[0] ) <= 60 ) ) {
		$name    = $parts[0];
		$message = isset( $parts[1] ) ? $parts[1] : '';
	}

	return array(
		'phone'   => $phone,
		'name'    => $name,
		'message' => $message,
		'source'  => $source,
	);
}

/**
 * Người nhắn riêng cho bot phải là thành viên nhóm Sale.
 */
function fireai_crm_telegram_is_team_member( $user_id ) {
	$settings = fireai_crm_settings();
	if ( ! $settings['tg_chat_id'] ) {
		return false;
	}
	$member = fireai_crm_telegram_api(
		'getChatMember',
		array(
			'chat_id' => $settings['tg_chat_id'],
			'user_id' => $user_id,
		)
	);
	return ! is_wp_error( $member ) && isset( $member['status'] ) && in_array( $member['status'], array( 'creator', 'administrator', 'member', 'restricted' ), true );
}

/*
 * Liên kết tài khoản Telegram với tài khoản WordPress (trang Hồ sơ) để CRM
 * ghi đúng người đã nhập khách qua bot.
 */
add_action( 'show_user_profile', 'fireai_crm_telegram_profile_field' );
add_action( 'edit_user_profile', 'fireai_crm_telegram_profile_field' );
add_action( 'personal_options_update', 'fireai_crm_telegram_profile_save' );
add_action( 'edit_user_profile_update', 'fireai_crm_telegram_profile_save' );

function fireai_crm_telegram_profile_field( $user ) {
	if ( ! user_can( $user, FIREAI_CRM_CAP ) ) {
		return;
	}
	?>
	<h2>CRM</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="fireai-crm-telegram">Username Telegram</label></th>
			<td>
				<input type="text" id="fireai-crm-telegram" name="fireai_crm_telegram" class="regular-text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'fireai_crm_telegram', true ) ); ?>" placeholder="vd: trung_fireai">
				<p class="description">Dành cho người trực hotline / Zalo nhập khách qua bot Telegram. Telegram → Cài đặt → Username (không cần dấu @). CRM sẽ ghi bạn là người nhập khách.</p>
			</td>
		</tr>
	</table>
	<?php
}

function fireai_crm_telegram_profile_save( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['fireai_crm_telegram'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- trang Hồ sơ đã kiểm tra nonce.
		return;
	}
	$username = strtolower( ltrim( sanitize_text_field( wp_unslash( $_POST['fireai_crm_telegram'] ) ), '@' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	update_user_meta( $user_id, 'fireai_crm_telegram', preg_replace( '/[^a-z0-9_]/', '', $username ) );
}

function fireai_crm_user_by_telegram( array $from ) {
	if ( empty( $from['username'] ) ) {
		return 0;
	}
	$users = get_users(
		array(
			'meta_key'   => 'fireai_crm_telegram', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => strtolower( $from['username'] ), // phpcs:ignore WordPress.DB.SlowDBQuery
			'capability' => FIREAI_CRM_CAP,
			'number'     => 1,
			'fields'     => 'ID',
		)
	);
	return $users ? (int) $users[0] : 0;
}
