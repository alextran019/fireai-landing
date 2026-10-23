<?php
/**
 * Giao diện quản trị: danh sách khách hàng, thêm/sửa, ghi chú, xuất file, cài đặt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FIREAI_CRM_PER_PAGE = 30;

add_action( 'admin_menu', 'fireai_crm_admin_menu' );
add_action( 'admin_enqueue_scripts', 'fireai_crm_admin_assets' );
add_action( 'admin_init', 'fireai_crm_redirect_sale_dashboard' );

add_action( 'admin_post_fireai_crm_save', 'fireai_crm_handle_save' );
add_action( 'admin_post_fireai_crm_note', 'fireai_crm_handle_note' );
add_action( 'admin_post_fireai_crm_quick_status', 'fireai_crm_handle_quick_status' );
add_action( 'admin_post_fireai_crm_delete', 'fireai_crm_handle_delete' );
add_action( 'admin_post_fireai_crm_export', 'fireai_crm_handle_export' );
add_action( 'admin_post_fireai_crm_settings', 'fireai_crm_handle_settings' );
add_action( 'admin_post_fireai_crm_import', 'fireai_crm_handle_import' );
add_action( 'admin_post_fireai_crm_tg_find', 'fireai_crm_handle_tg_find' );
add_action( 'admin_post_fireai_crm_tg_test', 'fireai_crm_handle_tg_test' );
add_action( 'admin_post_fireai_crm_tg_bot', 'fireai_crm_handle_tg_bot' );
add_action( 'in_admin_header', 'fireai_crm_hide_other_notices', 1000 );
add_filter( 'admin_body_class', 'fireai_crm_body_class' );
add_action( 'wp_ajax_fireai_crm_check_phone', 'fireai_crm_ajax_check_phone' );

function fireai_crm_admin_menu() {
	add_menu_page( 'Khách hàng', 'CRM', FIREAI_CRM_CAP, 'fireai-crm', 'fireai_crm_page_list', 'dashicons-groups', 3 );
	add_submenu_page( 'fireai-crm', 'Danh sách khách hàng', 'Khách hàng', FIREAI_CRM_CAP, 'fireai-crm', 'fireai_crm_page_list' );
	add_submenu_page( 'fireai-crm', 'Thêm khách hàng', 'Thêm khách (Hotline)', FIREAI_CRM_CAP, 'fireai-crm-new', 'fireai_crm_page_new' );
	add_submenu_page( 'fireai-crm', 'Cài đặt CRM', 'Cài đặt', FIREAI_CRM_MANAGE_CAP, 'fireai-crm-settings', 'fireai_crm_page_settings' );
}

function fireai_crm_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'fireai-crm' ) ) {
		return;
	}
	wp_enqueue_style( 'fireai-crm', FIREAI_CRM_URL . 'assets/admin.css', array(), FIREAI_CRM_VERSION );
	wp_enqueue_script( 'fireai-crm', FIREAI_CRM_URL . 'assets/admin.js', array(), FIREAI_CRM_VERSION, true );
	wp_localize_script(
		'fireai-crm',
		'FireAICRM',
		array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fireai_crm_check_phone' ),
		)
	);
}

/**
 * Tài khoản Sale không cần trang Bảng tin của WordPress: chuyển thẳng tới CRM.
 */
function fireai_crm_redirect_sale_dashboard() {
	global $pagenow;
	if ( 'index.php' === $pagenow && ! wp_doing_ajax() && current_user_can( FIREAI_CRM_CAP ) && ! current_user_can( 'edit_posts' ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=fireai-crm' ) );
		exit;
	}
}

function fireai_crm_is_crm_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && false !== strpos( (string) $screen->id, 'fireai-crm' );
}

function fireai_crm_is_sale_only() {
	return current_user_can( FIREAI_CRM_CAP ) && ! current_user_can( 'edit_posts' );
}

/**
 * Ẩn thông báo, quảng cáo của plugin khác trên trang CRM (và mọi trang khi Sale đăng nhập).
 * Thông báo của CRM được in trong nội dung trang nên không bị ảnh hưởng.
 */
function fireai_crm_hide_other_notices() {
	if ( ! fireai_crm_is_crm_screen() && ! fireai_crm_is_sale_only() ) {
		return;
	}
	foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook ) {
		remove_all_actions( $hook );
	}
}

function fireai_crm_body_class( $classes ) {
	if ( fireai_crm_is_crm_screen() || fireai_crm_is_sale_only() ) {
		$classes .= ' fireai-crm-screen';
	}
	return $classes;
}

/* -------------------------------------------------------------------------
 * Truy vấn danh sách
 * ---------------------------------------------------------------------- */

function fireai_crm_list_filters() {
	$get = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
	return array(
		's'        => isset( $get['s'] ) ? sanitize_text_field( $get['s'] ) : '',
		'source'   => isset( $get['source'] ) ? sanitize_key( $get['source'] ) : '',
		'status'   => isset( $get['status'] ) ? sanitize_key( $get['status'] ) : '',
		'assigned' => isset( $get['assigned'] ) ? sanitize_text_field( $get['assigned'] ) : '',
		'from'     => isset( $get['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $get['from'] ) ? $get['from'] : '',
		'to'       => isset( $get['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $get['to'] ) ? $get['to'] : '',
	);
}

/**
 * Điều kiện WHERE theo bộ lọc. $skip cho phép bỏ một bộ lọc (dùng khi đếm theo trạng thái).
 */
function fireai_crm_build_where( array $f, $skip = '' ) {
	global $wpdb;
	$where = array( '1=1' );

	if ( '' !== $f['s'] ) {
		$like  = '%' . $wpdb->esc_like( $f['s'] ) . '%';
		$phone = fireai_crm_normalize_phone( $f['s'] );
		if ( strlen( $phone ) >= 3 ) {
			$where[] = $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR campaign LIKE %s OR phone LIKE %s)', $like, $like, $like, '%' . $wpdb->esc_like( $phone ) . '%' );
		} else {
			$where[] = $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR campaign LIKE %s)', $like, $like, $like );
		}
	}
	if ( '' !== $f['source'] ) {
		$where[] = $wpdb->prepare( 'source = %s', $f['source'] );
	}
	if ( '' !== $f['status'] && 'status' !== $skip ) {
		$where[] = $wpdb->prepare( 'status = %s', $f['status'] );
	}
	if ( 'none' === $f['assigned'] ) {
		$where[] = 'assigned_to = 0';
	} elseif ( '' !== $f['assigned'] ) {
		$where[] = $wpdb->prepare( 'assigned_to = %d', absint( $f['assigned'] ) );
	}
	if ( '' !== $f['from'] ) {
		$where[] = $wpdb->prepare( 'created_at >= %s', $f['from'] . ' 00:00:00' );
	}
	if ( '' !== $f['to'] ) {
		$where[] = $wpdb->prepare( 'created_at <= %s', $f['to'] . ' 23:59:59' );
	}
	if ( fireai_crm_restrict_to_own() ) {
		$me      = get_current_user_id();
		$where[] = $wpdb->prepare( '(assigned_to = %d OR created_by = %d)', $me, $me );
	}
	return implode( ' AND ', $where );
}

function fireai_crm_users() {
	static $users = null;
	if ( null === $users ) {
		$users = get_users(
			array(
				'capability' => FIREAI_CRM_CAP,
				'orderby'    => 'display_name',
				'fields'     => array( 'ID', 'display_name' ),
			)
		);
	}
	return $users;
}

function fireai_crm_notice() {
	$messages = array(
		'saved'    => 'Đã lưu khách hàng.',
		'created'  => 'Đã thêm khách hàng mới.',
		'note'     => 'Đã thêm ghi chú.',
		'deleted'  => 'Đã xóa khách hàng.',
		'settings' => 'Đã lưu cài đặt.',
		'status'   => 'Đã đổi trạng thái.',
		'tg_test'  => 'Đã gửi tin nhắn thử. Kiểm tra nhóm Telegram trên điện thoại.',
		'bot_on'   => 'Đã bật nhập khách qua Telegram. Thử gõ /kh trong nhóm Sale.',
		'bot_off'  => 'Đã tắt nhập khách qua Telegram.',
	);
	$msg = isset( $_GET['crm_msg'] ) ? sanitize_key( wp_unslash( $_GET['crm_msg'] ) ) : ''; // phpcs:ignore
	if ( isset( $messages[ $msg ] ) ) {
		echo '<div class="notice crm-notice notice-success is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
	}
	if ( 'imported' === $msg && isset( $_GET['count'] ) ) { // phpcs:ignore
		echo '<div class="notice crm-notice notice-success is-dismissible"><p>' . esc_html( sprintf( 'Đã nhập %d khách hàng từ dữ liệu form cũ.', absint( $_GET['count'] ) ) ) . '</p></div>'; // phpcs:ignore
	}
	if ( 'tg_found' === $msg ) {
		$chat = isset( $_GET['chat'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['chat'] ) ) ) : ''; // phpcs:ignore
		echo '<div class="notice crm-notice notice-success is-dismissible"><p>' . esc_html( 'Đã kết nối với "' . $chat . '". Bấm "Gửi tin nhắn thử" để kiểm tra.' ) . '</p></div>';
	}
	if ( 'error' === $msg && isset( $_GET['error'] ) ) { // phpcs:ignore
		echo '<div class="notice crm-notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) . '</p></div>'; // phpcs:ignore
	}
}

function fireai_crm_status_badge( $status ) {
	return '<span class="crm-badge crm-status-' . esc_attr( $status ) . '">' . esc_html( fireai_crm_label( fireai_crm_statuses(), $status ) ) . '</span>';
}

/* -------------------------------------------------------------------------
 * Trang danh sách
 * ---------------------------------------------------------------------- */

function fireai_crm_page_list() {
	if ( isset( $_GET['lead'] ) ) { // phpcs:ignore
		fireai_crm_page_detail( absint( $_GET['lead'] ) ); // phpcs:ignore
		return;
	}

	global $wpdb;
	$table    = fireai_crm_table( 'leads' );
	$f        = fireai_crm_list_filters();
	$where    = fireai_crm_build_where( $f );
	$paged    = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore
	$offset   = ( $paged - 1 ) * FIREAI_CRM_PER_PAGE;
	$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore
	$leads    = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT " . (int) FIREAI_CRM_PER_PAGE . ' OFFSET ' . (int) $offset ); // phpcs:ignore
	$counts   = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} WHERE " . fireai_crm_build_where( $f, 'status' ) . ' GROUP BY status', OBJECT_K ); // phpcs:ignore
	$statuses = fireai_crm_statuses();
	$sources  = fireai_crm_sources();
	$base     = admin_url( 'admin.php?page=fireai-crm' );

	// Đánh dấu số điện thoại xuất hiện nhiều lần.
	$dupes  = array();
	$phones = array_filter( array_unique( wp_list_pluck( $leads, 'phone' ) ) );
	if ( $phones ) {
		$in    = implode( ',', array_fill( 0, count( $phones ), '%s' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT phone, COUNT(*) AS c FROM {$table} WHERE phone IN ({$in}) GROUP BY phone", array_values( $phones ) ) ); // phpcs:ignore
		foreach ( $rows as $row ) {
			if ( (int) $row->c > 1 ) {
				$dupes[ $row->phone ] = true;
			}
		}
	}

	$today_where = $wpdb->prepare( 'created_at >= %s', current_time( 'Y-m-d' ) . ' 00:00:00' ) . ' AND ' . fireai_crm_build_where( array_fill_keys( array_keys( $f ), '' ) );
	$today       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$today_where}" ); // phpcs:ignore
	?>
	<div class="wrap fireai-crm">
		<h1 class="wp-heading-inline">Khách hàng</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=fireai-crm-new' ) ); ?>" class="page-title-action">+ Thêm khách (Hotline)</a>
		<?php if ( fireai_crm_can_manage() ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( $f, array( 'action' => 'fireai_crm_export' ) ), admin_url( 'admin-post.php' ) ), 'fireai_crm_export' ) ); ?>" class="page-title-action">Xuất Excel</a>
		<?php endif; ?>
		<?php $clicks = fireai_crm_clicks_today(); ?>
		<span class="crm-today">Hôm nay: <strong><?php echo esc_html( $today ); ?></strong> khách mới · <strong><?php echo esc_html( $clicks['call'] ); ?></strong> lượt bấm Gọi · <strong><?php echo esc_html( $clicks['zalo'] ); ?></strong> lượt bấm Zalo</span>
		<hr class="wp-header-end">
		<?php fireai_crm_notice(); ?>

		<ul class="subsubsub">
			<?php
			$all   = array_sum( wp_list_pluck( $counts, 'c' ) );
			$links = array( '<a href="' . esc_url( add_query_arg( 'status', false, add_query_arg( $f, $base ) ) ) . '"' . ( '' === $f['status'] ? ' class="current"' : '' ) . '>Tất cả <span class="count">(' . (int) $all . ')</span></a>' );
			foreach ( $statuses as $key => $label ) {
				$c       = isset( $counts[ $key ] ) ? (int) $counts[ $key ]->c : 0;
				$links[] = '<a href="' . esc_url( add_query_arg( array_merge( $f, array( 'status' => $key ) ), $base ) ) . '"' . ( $f['status'] === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . $c . ')</span></a>';
			}
			echo '<li>' . implode( ' |</li><li>', $links ) . '</li>'; // phpcs:ignore
			?>
		</ul>

		<form method="get" class="crm-filters">
			<input type="hidden" name="page" value="fireai-crm">
			<input type="hidden" name="status" value="<?php echo esc_attr( $f['status'] ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="Tên, SĐT, email, chiến dịch...">
			<select name="source">
				<option value="">Mọi nguồn</option>
				<?php foreach ( $sources as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['source'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! fireai_crm_restrict_to_own() ) : ?>
				<select name="assigned">
					<option value="">Mọi người phụ trách</option>
					<option value="none" <?php selected( $f['assigned'], 'none' ); ?>>Chưa giao</option>
					<?php foreach ( fireai_crm_users() as $u ) : ?>
						<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $f['assigned'], (string) $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<label>Từ <input type="date" name="from" value="<?php echo esc_attr( $f['from'] ); ?>"></label>
			<label>đến <input type="date" name="to" value="<?php echo esc_attr( $f['to'] ); ?>"></label>
			<button class="button">Lọc</button>
			<a class="button-link" href="<?php echo esc_url( $base ); ?>">Xóa lọc</a>
		</form>

		<table class="wp-list-table widefat fixed striped crm-table">
			<thead>
				<tr>
					<th class="col-date">Ngày</th>
					<th>Khách hàng</th>
					<th class="col-phone">Điện thoại</th>
					<th class="col-source">Nguồn</th>
					<th>Nội dung</th>
					<th class="col-status">Trạng thái</th>
					<th class="col-user">Phụ trách</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $leads ) : ?>
				<tr><td colspan="7">Chưa có khách hàng nào phù hợp.</td></tr>
			<?php endif; ?>
			<?php foreach ( $leads as $lead ) : ?>
				<?php $url = add_query_arg( 'lead', $lead->id, $base ); ?>
				<tr>
					<td class="col-date"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $lead->created_at ) ); ?></td>
					<td>
						<strong><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( '' !== $lead->name ? $lead->name : '(Chưa có tên)' ); ?></a></strong>
						<?php if ( $lead->email ) : ?><br><span class="crm-muted"><?php echo esc_html( $lead->email ); ?></span><?php endif; ?>
					</td>
					<td class="col-phone">
						<?php if ( $lead->phone ) : ?>
							<a href="tel:<?php echo esc_attr( $lead->phone ); ?>"><?php echo esc_html( $lead->phone ); ?></a>
							<?php if ( isset( $dupes[ $lead->phone ] ) ) : ?>
								<br><a class="crm-badge crm-dupe" href="<?php echo esc_url( add_query_arg( 's', $lead->phone, $base ) ); ?>">Trùng SĐT</a>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td class="col-source">
						<?php echo esc_html( fireai_crm_label( $sources, $lead->source ) ); ?>
						<?php if ( $lead->campaign ) : ?><br><span class="crm-muted"><?php echo esc_html( $lead->campaign ); ?></span><?php endif; ?>
					</td>
					<td class="crm-muted"><?php echo esc_html( wp_trim_words( (string) $lead->message, 18 ) ); ?></td>
					<td class="col-status">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="fireai_crm_quick_status">
							<input type="hidden" name="id" value="<?php echo esc_attr( $lead->id ); ?>">
							<input type="hidden" name="back" value="<?php echo esc_attr( add_query_arg( array_merge( $f, array( 'paged' => $paged ) ), $base ) ); ?>">
							<?php wp_nonce_field( 'fireai_crm_quick_status_' . $lead->id ); ?>
							<select name="status" class="crm-status-select crm-status-<?php echo esc_attr( $lead->status ); ?>" data-autosubmit>
								<?php foreach ( $statuses as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $lead->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</form>
					</td>
					<td class="col-user"><?php echo esc_html( fireai_crm_user_name( $lead->assigned_to ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$pages = (int) ceil( $total / FIREAI_CRM_PER_PAGE );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo paginate_links( // phpcs:ignore
				array(
					'base'    => add_query_arg( 'paged', '%#%', add_query_arg( $f, $base ) ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			);
			echo '</div></div>';
		}
		?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Form thêm / sửa
 * ---------------------------------------------------------------------- */

function fireai_crm_page_new() {
	echo '<div class="wrap fireai-crm"><h1>Thêm khách hàng</h1>';
	echo '<p class="crm-muted">Dùng khi khách gọi thẳng hotline hoặc nhắn tin mà không điền form.</p>';
	fireai_crm_notice();
	fireai_crm_lead_form( null );
	echo '</div>';
}

function fireai_crm_lead_form( $lead ) {
	$is_new   = ! $lead;
	$can_mgr  = fireai_crm_can_manage();
	$value    = function ( $field, $default = '' ) use ( $lead ) {
		return $lead ? $lead->$field : $default;
	};
	$assigned = (int) $value( 'assigned_to', get_current_user_id() );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crm-form">
		<input type="hidden" name="action" value="fireai_crm_save">
		<input type="hidden" name="id" value="<?php echo esc_attr( $is_new ? 0 : $lead->id ); ?>">
		<?php wp_nonce_field( 'fireai_crm_save' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="crm-phone">Số điện thoại <span class="required">*</span></label></th>
				<td>
					<input type="tel" id="crm-phone" name="phone" class="regular-text" required value="<?php echo esc_attr( $value( 'phone' ) ); ?>" data-lead="<?php echo esc_attr( $is_new ? 0 : $lead->id ); ?>">
					<div id="crm-phone-warning" class="crm-warning" hidden></div>
				</td>
			</tr>
			<tr>
				<th><label for="crm-name">Tên khách hàng</label></th>
				<td><input type="text" id="crm-name" name="name" class="regular-text" value="<?php echo esc_attr( $value( 'name' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="crm-email">Email</label></th>
				<td><input type="email" id="crm-email" name="email" class="regular-text" value="<?php echo esc_attr( $value( 'email' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="crm-source">Nguồn</label></th>
				<td>
					<select id="crm-source" name="source">
						<?php foreach ( fireai_crm_sources() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value( 'source', 'hotline' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="crm-campaign">Chiến dịch / Sản phẩm quan tâm</label></th>
				<td><input type="text" id="crm-campaign" name="campaign" class="regular-text" value="<?php echo esc_attr( $value( 'campaign' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="crm-status">Trạng thái</label></th>
				<td>
					<select id="crm-status" name="status">
						<?php foreach ( fireai_crm_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value( 'status', 'new' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="crm-assigned">Người phụ trách</label></th>
				<td>
					<?php if ( $can_mgr ) : ?>
						<select id="crm-assigned" name="assigned_to">
							<option value="0">— Chưa giao —</option>
							<?php foreach ( fireai_crm_users() as $u ) : ?>
								<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $assigned, (int) $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<?php echo esc_html( fireai_crm_user_name( $assigned ) ); ?>
						<?php if ( ! $is_new && ! $lead->assigned_to ) : ?>
							<label><input type="checkbox" name="take" value="1"> Nhận khách này</label>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="crm-message"><?php echo $is_new ? 'Nội dung cuộc gọi' : 'Nội dung khách gửi'; ?></label></th>
				<td><textarea id="crm-message" name="message" rows="5" class="large-text"><?php echo esc_textarea( (string) $value( 'message' ) ); ?></textarea></td>
			</tr>
		</table>
		<?php submit_button( $is_new ? 'Lưu khách hàng' : 'Cập nhật' ); ?>
	</form>
	<?php
}

/* -------------------------------------------------------------------------
 * Trang chi tiết
 * ---------------------------------------------------------------------- */

function fireai_crm_page_detail( $id ) {
	global $wpdb;
	$lead = fireai_crm_get_lead( $id );
	if ( ! fireai_crm_can_access_lead( $lead ) ) {
		echo '<div class="wrap"><h1>Không tìm thấy khách hàng</h1><p>Khách hàng không tồn tại hoặc bạn không có quyền xem.</p></div>';
		return;
	}
	$notes = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . fireai_crm_table( 'notes' ) . ' WHERE lead_id = %d ORDER BY created_at DESC, id DESC', $id ) );
	$dupes = fireai_crm_find_by_phone( $lead->phone, $lead->id, 10 );
	$raw   = $lead->raw ? json_decode( $lead->raw, true ) : null;
	$base  = admin_url( 'admin.php?page=fireai-crm' );
	?>
	<div class="wrap fireai-crm">
		<p><a href="<?php echo esc_url( $base ); ?>">&larr; Danh sách khách hàng</a></p>
		<h1><?php echo esc_html( '' !== $lead->name ? $lead->name : '(Chưa có tên)' ); ?> <?php echo fireai_crm_status_badge( $lead->status ); // phpcs:ignore ?></h1>
		<p class="crm-muted">
			Vào CRM lúc <?php echo esc_html( mysql2date( 'd/m/Y H:i', $lead->created_at ) ); ?>
			· Nguồn: <?php echo esc_html( fireai_crm_label( fireai_crm_sources(), $lead->source ) ); ?>
			<?php if ( $lead->created_by ) : ?>· Người nhập: <?php echo esc_html( fireai_crm_user_name( $lead->created_by ) ); ?><?php endif; ?>
		</p>
		<?php fireai_crm_notice(); ?>

		<?php if ( $dupes ) : ?>
			<div class="notice crm-notice notice-warning inline"><p><strong>Số điện thoại này đã có <?php echo count( $dupes ); ?> lần khác trong CRM:</strong>
				<?php foreach ( $dupes as $d ) : ?>
					<br><a href="<?php echo esc_url( add_query_arg( 'lead', $d->id, $base ) ); ?>"><?php echo esc_html( ( $d->name ? $d->name : '(Chưa có tên)' ) . ' – ' . mysql2date( 'd/m/Y', $d->created_at ) . ' – ' . fireai_crm_label( fireai_crm_statuses(), $d->status ) ); ?></a>
				<?php endforeach; ?>
			</p></div>
		<?php endif; ?>

		<div class="crm-detail">
			<div class="crm-detail-main">
				<?php fireai_crm_lead_form( $lead ); ?>
				<?php if ( is_array( $raw ) && ! empty( $raw ) ) : ?>
					<details class="crm-raw">
						<summary>Dữ liệu gốc khách đã gửi</summary>
						<?php if ( ! empty( $raw['url'] ) ) : ?><p>Trang: <code><?php echo esc_html( $raw['url'] ); ?></code></p><?php endif; ?>
						<table class="widefat striped">
							<?php foreach ( ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : $raw ) as $k => $v ) : ?>
								<tr><th><?php echo esc_html( $k ); ?></th><td><?php echo esc_html( is_scalar( $v ) ? (string) $v : wp_json_encode( $v, JSON_UNESCAPED_UNICODE ) ); ?></td></tr>
							<?php endforeach; ?>
						</table>
					</details>
				<?php endif; ?>
				<?php if ( fireai_crm_can_manage() ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crm-delete" onsubmit="return confirm('Xóa vĩnh viễn khách hàng này và toàn bộ ghi chú?');">
						<input type="hidden" name="action" value="fireai_crm_delete">
						<input type="hidden" name="id" value="<?php echo esc_attr( $lead->id ); ?>">
						<?php wp_nonce_field( 'fireai_crm_delete_' . $lead->id ); ?>
						<button class="button-link crm-danger">Xóa khách hàng</button>
					</form>
				<?php endif; ?>
			</div>

			<div class="crm-detail-side">
				<h2>Lịch sử chăm sóc</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="fireai_crm_note">
					<input type="hidden" name="id" value="<?php echo esc_attr( $lead->id ); ?>">
					<?php wp_nonce_field( 'fireai_crm_note_' . $lead->id ); ?>
					<textarea name="content" rows="3" class="large-text" placeholder="VD: Đã gọi, khách hẹn gọi lại thứ 5..." required></textarea>
					<p><button class="button button-primary">Thêm ghi chú</button></p>
				</form>
				<?php if ( ! $notes ) : ?>
					<p class="crm-muted">Chưa có ghi chú nào.</p>
				<?php endif; ?>
				<ul class="crm-notes">
					<?php foreach ( $notes as $note ) : ?>
						<li>
							<div class="crm-note-meta"><?php echo esc_html( fireai_crm_user_name( $note->user_id ) . ' · ' . mysql2date( 'd/m/Y H:i', $note->created_at ) ); ?></div>
							<div><?php echo nl2br( esc_html( $note->content ) ); ?></div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Xử lý form
 * ---------------------------------------------------------------------- */

function fireai_crm_redirect( $url, $msg, $extra = array() ) {
	wp_safe_redirect( add_query_arg( array_merge( array( 'crm_msg' => $msg ), $extra ), $url ) );
	exit;
}

function fireai_crm_lead_url( $id ) {
	return admin_url( 'admin.php?page=fireai-crm&lead=' . (int) $id );
}

function fireai_crm_handle_save() {
	if ( ! current_user_can( FIREAI_CRM_CAP ) ) {
		wp_die( 'Bạn không có quyền.' );
	}
	check_admin_referer( 'fireai_crm_save' );
	global $wpdb;

	$post = wp_unslash( $_POST );
	$id   = isset( $post['id'] ) ? absint( $post['id'] ) : 0;
	$data = array(
		'name'     => isset( $post['name'] ) ? $post['name'] : '',
		'phone'    => isset( $post['phone'] ) ? $post['phone'] : '',
		'email'    => isset( $post['email'] ) ? $post['email'] : '',
		'source'   => isset( $post['source'] ) ? sanitize_key( $post['source'] ) : 'hotline',
		'campaign' => isset( $post['campaign'] ) ? $post['campaign'] : '',
		'status'   => isset( $post['status'] ) ? sanitize_key( $post['status'] ) : 'new',
		'message'  => isset( $post['message'] ) ? $post['message'] : '',
	);

	if ( ! $id ) {
		$data['assigned_to'] = fireai_crm_can_manage() && isset( $post['assigned_to'] ) ? absint( $post['assigned_to'] ) : get_current_user_id();
		$new_id              = fireai_crm_insert_lead( $data );
		if ( ! $new_id ) {
			fireai_crm_redirect( admin_url( 'admin.php?page=fireai-crm-new' ), 'error', array( 'error' => 'Cần nhập ít nhất số điện thoại.' ) );
		}
		fireai_crm_redirect( fireai_crm_lead_url( $new_id ), 'created' );
	}

	$lead = fireai_crm_get_lead( $id );
	if ( ! fireai_crm_can_access_lead( $lead ) ) {
		wp_die( 'Bạn không có quyền sửa khách hàng này.' );
	}

	$statuses = fireai_crm_statuses();
	$sources  = fireai_crm_sources();
	$update   = array(
		'name'       => sanitize_text_field( $data['name'] ),
		'phone'      => fireai_crm_normalize_phone( $data['phone'] ),
		'email'      => sanitize_email( $data['email'] ),
		'source'     => isset( $sources[ $data['source'] ] ) ? $data['source'] : $lead->source,
		'campaign'   => sanitize_text_field( $data['campaign'] ),
		'status'     => isset( $statuses[ $data['status'] ] ) ? $data['status'] : $lead->status,
		'message'    => sanitize_textarea_field( $data['message'] ),
		'updated_at' => current_time( 'mysql' ),
	);
	if ( fireai_crm_can_manage() && isset( $post['assigned_to'] ) ) {
		$update['assigned_to'] = absint( $post['assigned_to'] );
	} elseif ( ! empty( $post['take'] ) && ! $lead->assigned_to ) {
		$update['assigned_to'] = get_current_user_id();
	}

	$wpdb->update( fireai_crm_table( 'leads' ), $update, array( 'id' => $id ) );
	fireai_crm_log_changes( $lead, $update );
	fireai_crm_redirect( fireai_crm_lead_url( $id ), 'saved' );
}

/**
 * Ghi lại việc đổi trạng thái / người phụ trách vào lịch sử chăm sóc.
 */
function fireai_crm_log_changes( $lead, array $update ) {
	$statuses = fireai_crm_statuses();
	$lines    = array();
	if ( isset( $update['status'] ) && $update['status'] !== $lead->status ) {
		$lines[] = 'Đổi trạng thái: ' . fireai_crm_label( $statuses, $lead->status ) . ' → ' . fireai_crm_label( $statuses, $update['status'] );
	}
	if ( isset( $update['assigned_to'] ) && (int) $update['assigned_to'] !== (int) $lead->assigned_to ) {
		$lines[] = 'Giao cho: ' . fireai_crm_user_name( $update['assigned_to'] );
	}
	if ( $lines ) {
		fireai_crm_add_note( $lead->id, implode( "\n", $lines ) );
	}
}

function fireai_crm_handle_quick_status() {
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	check_admin_referer( 'fireai_crm_quick_status_' . $id );
	$lead = fireai_crm_get_lead( $id );
	if ( ! fireai_crm_can_access_lead( $lead ) ) {
		wp_die( 'Bạn không có quyền.' );
	}
	$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	if ( isset( fireai_crm_statuses()[ $status ] ) && $status !== $lead->status ) {
		global $wpdb;
		$update = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		$wpdb->update( fireai_crm_table( 'leads' ), $update, array( 'id' => $id ) );
		fireai_crm_log_changes( $lead, $update );
	}
	$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : admin_url( 'admin.php?page=fireai-crm' );
	fireai_crm_redirect( $back, 'status' );
}

function fireai_crm_handle_note() {
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	check_admin_referer( 'fireai_crm_note_' . $id );
	$lead = fireai_crm_get_lead( $id );
	if ( ! fireai_crm_can_access_lead( $lead ) ) {
		wp_die( 'Bạn không có quyền.' );
	}
	fireai_crm_add_note( $id, isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '' ); // phpcs:ignore
	fireai_crm_redirect( fireai_crm_lead_url( $id ), 'note' );
}

function fireai_crm_handle_delete() {
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	check_admin_referer( 'fireai_crm_delete_' . $id );
	if ( ! fireai_crm_can_manage() ) {
		wp_die( 'Bạn không có quyền.' );
	}
	global $wpdb;
	$wpdb->delete( fireai_crm_table( 'notes' ), array( 'lead_id' => $id ) );
	$wpdb->delete( fireai_crm_table( 'leads' ), array( 'id' => $id ) );
	fireai_crm_redirect( admin_url( 'admin.php?page=fireai-crm' ), 'deleted' );
}

function fireai_crm_handle_export() {
	check_admin_referer( 'fireai_crm_export' );
	if ( ! fireai_crm_can_manage() ) {
		wp_die( 'Bạn không có quyền.' );
	}
	global $wpdb;
	$table = fireai_crm_table( 'leads' );
	$where = fireai_crm_build_where( fireai_crm_list_filters() );
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC" ); // phpcs:ignore

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=khach-hang-' . current_time( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM để Excel hiển thị đúng tiếng Việt.
	fputcsv( $out, array( 'ID', 'Ngày', 'Tên', 'Điện thoại', 'Email', 'Nguồn', 'Chiến dịch', 'Trạng thái', 'Phụ trách', 'Nội dung' ) );
	foreach ( $rows as $r ) {
		fputcsv(
			$out,
			array(
				$r->id,
				mysql2date( 'd/m/Y H:i', $r->created_at ),
				fireai_crm_csv_safe( $r->name ),
				// Dấu ' giữ số 0 ở đầu khi mở bằng Excel.
				$r->phone ? "'" . $r->phone : '',
				fireai_crm_csv_safe( $r->email ),
				fireai_crm_label( fireai_crm_sources(), $r->source ),
				fireai_crm_csv_safe( $r->campaign ),
				fireai_crm_label( fireai_crm_statuses(), $r->status ),
				fireai_crm_user_name( $r->assigned_to ),
				fireai_crm_csv_safe( $r->message ),
			)
		);
	}
	fclose( $out );
	exit;
}

/**
 * Chặn công thức Excel độc hại (=, +, -, @) trong dữ liệu khách tự gõ.
 */
function fireai_crm_csv_safe( $value ) {
	$value = (string) $value;
	return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $value : $value;
}

function fireai_crm_ajax_check_phone() {
	check_ajax_referer( 'fireai_crm_check_phone' );
	if ( ! current_user_can( FIREAI_CRM_CAP ) ) {
		wp_send_json_error();
	}
	$phone   = isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( $_GET['phone'] ) ) : '';
	$exclude = isset( $_GET['exclude'] ) ? absint( $_GET['exclude'] ) : 0;
	$result  = array();
	foreach ( fireai_crm_find_by_phone( $phone, $exclude ) as $lead ) {
		$result[] = array(
			'url'   => fireai_crm_lead_url( $lead->id ),
			'label' => ( $lead->name ? $lead->name : '(Chưa có tên)' ) . ' – ' . mysql2date( 'd/m/Y', $lead->created_at ) . ' – ' . fireai_crm_label( fireai_crm_statuses(), $lead->status ),
		);
	}
	wp_send_json_success( $result );
}

/* -------------------------------------------------------------------------
 * Cài đặt
 * ---------------------------------------------------------------------- */

function fireai_crm_page_settings() {
	$s       = fireai_crm_settings();
	$webhook = rest_url( 'fireai-crm/v1/lead' );
	?>
	<div class="wrap fireai-crm">
		<h1>Cài đặt CRM</h1>
		<?php fireai_crm_notice(); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fireai_crm_settings">
			<?php wp_nonce_field( 'fireai_crm_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th>Form liên hệ trên website</th>
					<td><label><input type="checkbox" name="cf7_enabled" value="1" <?php checked( $s['cf7_enabled'] ); ?>> Tự động đưa khách gửi Contact Form 7 vào CRM</label></td>
				</tr>
				<tr>
					<th>Quyền của Sale</th>
					<td><label><input type="checkbox" name="sale_own_only" value="1" <?php checked( $s['sale_own_only'] ); ?>> Sale chỉ thấy khách được giao cho mình hoặc do mình nhập</label></td>
				</tr>
				<tr>
					<th colspan="2"><h2>Báo khách mới về điện thoại</h2></th>
				</tr>
				<tr>
					<th><label for="crm-tg-token">Telegram Bot Token</label></th>
					<td>
						<input type="password" id="crm-tg-token" name="tg_token" class="regular-text code" autocomplete="off" placeholder="<?php echo $s['tg_token'] ? esc_attr( 'Đã lưu – để trống nếu không đổi' ) : '123456789:AA...'; ?>">
						<?php if ( $s['tg_token'] ) : ?>
							<label><input type="checkbox" name="tg_clear" value="1"> Xóa token</label>
						<?php endif; ?>
						<p class="description">Lấy từ @BotFather trên Telegram. Xem hướng dẫn trong file HUONG_DAN_CAI_DAT.</p>
					</td>
				</tr>
				<tr>
					<th><label for="crm-tg-chat">Telegram Chat ID</label></th>
					<td>
						<input type="text" id="crm-tg-chat" name="tg_chat_id" class="regular-text code" value="<?php echo esc_attr( $s['tg_chat_id'] ); ?>">
						<p class="description">Không cần tự điền: lưu Bot Token trước, rồi bấm nút <strong>Tìm Chat ID</strong> bên dưới.</p>
					</td>
				</tr>
				<tr>
					<th><label for="crm-emails">Email nhận thông báo</label></th>
					<td>
						<input type="text" id="crm-emails" name="notify_emails" class="large-text" value="<?php echo esc_attr( $s['notify_emails'] ); ?>" placeholder="sale1@gmail.com, sale2@gmail.com">
						<p class="description">Nhiều email cách nhau bằng dấu phẩy. Để trống nếu không cần.</p>
					</td>
				</tr>
				<tr>
					<th>Nút Gọi / Zalo trên website</th>
					<td>
						<label><input type="checkbox" name="click_track" value="1" <?php checked( $s['click_track'] ); ?>> Đếm lượt khách bấm Gọi / Chat Zalo và ghi nhận nguồn quảng cáo</label><br>
						<label><input type="checkbox" name="click_notify" value="1" <?php checked( $s['click_notify'] ); ?>> Báo vào nhóm Telegram mỗi khi có khách bấm</label>
					</td>
				</tr>
				<tr>
					<th>Đổi mã bảo mật webhook</th>
					<td><label><input type="checkbox" name="regen_key" value="1"> Tạo mã mới (mã cũ sẽ ngừng hoạt động)</label></td>
				</tr>
			</table>
			<?php submit_button( 'Lưu cài đặt' ); ?>
		</form>

		<?php if ( $s['tg_token'] ) : ?>
			<div class="crm-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="fireai_crm_tg_find">
					<?php wp_nonce_field( 'fireai_crm_tg_find' ); ?>
					<button class="button">Tìm Chat ID</button>
				</form>
				<?php if ( $s['tg_chat_id'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="fireai_crm_tg_test">
						<?php wp_nonce_field( 'fireai_crm_tg_test' ); ?>
						<button class="button">Gửi tin nhắn thử</button>
					</form>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<h2>Nhập khách qua Telegram (cho người cầm hotline / Zalo)</h2>
		<?php if ( ! $s['tg_token'] || ! $s['tg_chat_id'] ) : ?>
			<p class="crm-muted">Cần nhập Bot Token và Chat ID ở trên trước.</p>
		<?php else : ?>
			<?php
			$bot_on = (bool) get_option( 'fireai_crm_tg_webhook_secret' );
			$status = $bot_on ? fireai_crm_telegram_bot_status() : null;
			?>
			<p>Trong nhóm Sale gõ <code>/kh 0912345678 Anh Nam, hỏi báo cháy nhà xưởng</code> (khách gọi) hoặc <code>/zalo 0912… Chị Lan, nội dung</code> (khách nhắn Zalo) là khách vào CRM ngay. Gõ <code>/help</code> để xem hướng dẫn.</p>
			<?php if ( $bot_on ) : ?>
				<?php if ( is_wp_error( $status ) ) : ?>
					<p class="crm-warning">Không kiểm tra được trạng thái bot: <?php echo esc_html( $status->get_error_message() ); ?></p>
				<?php elseif ( ! empty( $status['last_error_message'] ) && ! empty( $status['last_error_date'] ) && time() - (int) $status['last_error_date'] < DAY_IN_SECONDS ) : ?>
					<p class="crm-warning"><strong>Telegram không gửi được tin về website:</strong> <?php echo esc_html( $status['last_error_message'] ); ?>. Nếu lỗi là 401/403, kiểm tra plugin bảo mật (All-In-One Security) có đang chặn REST API không.</p>
				<?php else : ?>
					<p><span class="crm-badge crm-status-won">Đang hoạt động</span></p>
				<?php endif; ?>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fireai_crm_tg_bot">
				<input type="hidden" name="enable" value="<?php echo $bot_on ? '0' : '1'; ?>">
				<?php wp_nonce_field( 'fireai_crm_tg_bot' ); ?>
				<button class="button <?php echo $bot_on ? '' : 'button-primary'; ?>"><?php echo $bot_on ? 'Tắt nhập khách qua Telegram' : 'Bật nhập khách qua Telegram'; ?></button>
			</form>
			<p class="crm-muted">Mỗi Sale vào <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">Hồ sơ</a> điền Username Telegram để khách mình nhập qua bot được giao luôn cho mình.</p>
		<?php endif; ?>

		<h2>Nhận khách từ quảng cáo (Facebook, Google, TikTok...)</h2>
		<p>Dùng Zapier hoặc Make để gửi khách từ form quảng cáo về địa chỉ sau, phương thức <strong>POST</strong>:</p>
		<p><input type="text" class="large-text code" readonly onclick="this.select()" value="<?php echo esc_attr( add_query_arg( 'key', $s['webhook_key'], $webhook ) ); ?>"></p>
		<p>Các trường gửi kèm: <code>name</code>, <code>phone</code>, <code>email</code>, <code>source</code> (facebook / google / tiktok / zalo), <code>campaign</code>, <code>message</code>. Trường khác (nếu có) sẽ được ghép vào phần nội dung.</p>
		<p class="crm-muted">Giữ bí mật địa chỉ này: ai có nó đều có thể gửi khách vào CRM.</p>

		<h2>Nhập khách cũ từ form website</h2>
		<p>Lấy các khách đã gửi form trước khi cài CRM (lưu trong menu <em>Contact Forms</em>). Bấm nhiều lần cũng không bị trùng.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fireai_crm_import">
			<?php wp_nonce_field( 'fireai_crm_import' ); ?>
			<button class="button">Nhập khách cũ</button>
		</form>

		<h2>Tạo tài khoản cho Sale</h2>
		<p>Vào <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">Tài khoản → Thêm mới</a>, chọn vai trò <strong>Nhân viên Sale (CRM)</strong>. Tài khoản này chỉ dùng được CRM, không sửa được website.</p>
	</div>
	<?php
}

function fireai_crm_handle_settings() {
	check_admin_referer( 'fireai_crm_settings' );
	if ( ! fireai_crm_can_manage() ) {
		wp_die( 'Bạn không có quyền.' );
	}
	$s                  = fireai_crm_settings();
	$s['cf7_enabled']   = empty( $_POST['cf7_enabled'] ) ? 0 : 1;
	$s['sale_own_only'] = empty( $_POST['sale_own_only'] ) ? 0 : 1;
	$post = wp_unslash( $_POST );
	$token = isset( $post['tg_token'] ) ? trim( sanitize_text_field( $post['tg_token'] ) ) : '';
	if ( ! empty( $post['tg_clear'] ) ) {
		if ( get_option( 'fireai_crm_tg_webhook_secret' ) ) {
			fireai_crm_telegram_disable_bot();
		}
		$s['tg_token']   = '';
		$s['tg_chat_id'] = '';
	} elseif ( '' !== $token ) {
		if ( ! preg_match( '/^\d+:[A-Za-z0-9_-]{20,}$/', $token ) ) {
			fireai_crm_redirect( admin_url( 'admin.php?page=fireai-crm-settings' ), 'error', array( 'error' => 'Bot Token không đúng định dạng. Hãy sao chép lại nguyên dòng token từ @BotFather.' ) );
		}
		if ( $token !== $s['tg_token'] ) {
			// Bot mới chưa được trỏ webhook về website: cần bật lại nhập khách qua Telegram.
			delete_option( 'fireai_crm_tg_webhook_secret' );
		}
		$s['tg_token'] = $token;
	}
	if ( empty( $post['tg_clear'] ) ) {
		$s['tg_chat_id'] = isset( $post['tg_chat_id'] ) && preg_match( '/^-?\d+$/', trim( $post['tg_chat_id'] ) ) ? trim( $post['tg_chat_id'] ) : '';
	}
	$emails             = isset( $post['notify_emails'] ) ? explode( ',', $post['notify_emails'] ) : array();
	$s['notify_emails'] = implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', $emails ) ) ) );
	$s['click_track']   = empty( $post['click_track'] ) ? 0 : 1;
	$s['click_notify']  = empty( $post['click_notify'] ) ? 0 : 1;
	if ( ! empty( $_POST['regen_key'] ) || '' === $s['webhook_key'] ) {
		$s['webhook_key'] = wp_generate_password( 32, false );
	}
	update_option( 'fireai_crm_settings', $s, false );
	fireai_crm_redirect( admin_url( 'admin.php?page=fireai-crm-settings' ), 'settings' );
}

function fireai_crm_handle_import() {
	check_admin_referer( 'fireai_crm_import' );
	if ( ! fireai_crm_can_manage() ) {
		wp_die( 'Bạn không có quyền.' );
	}
	$result = fireai_crm_import_cfdb7();
	$url    = admin_url( 'admin.php?page=fireai-crm-settings' );
	if ( is_wp_error( $result ) ) {
		fireai_crm_redirect( $url, 'error', array( 'error' => $result->get_error_message() ) );
	}
	fireai_crm_redirect( $url, 'imported', array( 'count' => $result ) );
}

function fireai_crm_handle_tg_find() {
	check_admin_referer( 'fireai_crm_tg_find' );
	if ( ! fireai_crm_can_manage() ) {
		wp_die( 'Bạn không có quyền.' );
	}
	$url   = admin_url( 'admin.php?page=fireai-crm-settings' );
	$chats = fireai_crm_telegram_find_chats();
	if ( is_wp_error( $chats ) ) {
		fireai_crm_redirect( $url, 'error', array( 'error' => $chats->get_error_message() ) );
	}
	if ( ! $chats ) {
		fireai_crm_redirect( $url, 'error', array( 'error' => 'Bot chưa nhận được tin nhắn nào. Hãy thêm bot vào nhóm Telegram, gửi /start trong nhóm, rồi bấm lại "Tìm Chat ID".' ) );
	}
	if ( count( $chats ) > 1 ) {
		$list = array();
		foreach ( $chats as $id => $name ) {
			$list[] = $name . ': ' . $id;
		}
		fireai_crm_redirect( $url, 'error', array( 'error' => 'Bot đang ở nhiều cuộc trò chuyện. Chép số Chat ID của nhóm cần dùng vào ô Telegram Chat ID rồi bấm Lưu. ' . implode( ' | ', $list ) ) );
	}
	$s               = fireai_crm_settings();
	$s['tg_chat_id'] = (string) key( $chats );
	update_option( 'fireai_crm_settings', $s, false );
	fireai_crm_redirect( $url, 'tg_found', array( 'chat' => rawurlencode( (string) current( $chats ) ) ) );
}

function fireai_crm_handle_tg_test() {
	check_admin_referer( 'fireai_crm_tg_test' );
	if ( ! fireai_crm_can_manage() ) {
		wp_die( 'Bạn không có quyền.' );
	}
	$url    = admin_url( 'admin.php?page=fireai-crm-settings' );
	$result = fireai_crm_telegram_send( "✅ Kết nối thành công!\nTừ giờ khách mới vào CRM sẽ được báo tại đây." );
	if ( is_wp_error( $result ) ) {
		fireai_crm_redirect( $url, 'error', array( 'error' => $result->get_error_message() ) );
	}
	fireai_crm_redirect( $url, 'tg_test' );
}

function fireai_crm_handle_tg_bot() {
	check_admin_referer( 'fireai_crm_tg_bot' );
	if ( ! fireai_crm_can_manage() ) {
		wp_die( 'Bạn không có quyền.' );
	}
	$url    = admin_url( 'admin.php?page=fireai-crm-settings' );
	$enable = ! empty( $_POST['enable'] );
	$result = $enable ? fireai_crm_telegram_enable_bot() : fireai_crm_telegram_disable_bot();
	if ( is_wp_error( $result ) ) {
		fireai_crm_redirect( $url, 'error', array( 'error' => $result->get_error_message() ) );
	}
	fireai_crm_redirect( $url, $enable ? 'bot_on' : 'bot_off' );
}
