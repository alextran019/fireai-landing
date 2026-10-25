<?php
/**
 * Báo cáo hiệu quả theo nguồn / chiến dịch / mẫu quảng cáo:
 * bao nhiêu khách, bao nhiêu đã nhận báo giá, ký hợp đồng, chuyển tiền và tổng giá trị hợp đồng.
 * Tính theo ngày khách vào CRM, nên một khách vào tháng 9 ký tháng 10 vẫn tính cho tháng 9.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fireai_crm_report_range() {
	$get    = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
	$today  = current_time( 'Y-m-d' );
	$preset = isset( $get['range'] ) ? sanitize_key( $get['range'] ) : '';
	$from   = isset( $get['from'] ) ? fireai_crm_sanitize_date( $get['from'] ) : '';
	$to     = isset( $get['to'] ) ? fireai_crm_sanitize_date( $get['to'] ) : '';

	if ( 'last_month' === $preset ) {
		$first = new DateTime( 'first day of last month', wp_timezone() );
		$last  = new DateTime( 'last day of last month', wp_timezone() );
		return array( $first->format( 'Y-m-d' ), $last->format( 'Y-m-d' ), $preset );
	}
	if ( '90d' === $preset ) {
		return array( wp_date( 'Y-m-d', strtotime( '-89 days' ) ), $today, $preset );
	}
	if ( 'all' === $preset ) {
		return array( '', '', $preset );
	}
	if ( '' !== $from || '' !== $to ) {
		return array( $from, $to, 'custom' );
	}
	return array( current_time( 'Y-m-01' ), $today, 'this_month' );
}

/**
 * Gom số liệu theo nhóm.
 *
 * @param string $group "ad" (nguồn + chiến dịch + mẫu QC), "campaign" (bỏ mẫu QC) hoặc "source".
 */
function fireai_crm_report_data( $from, $to, $group ) {
	global $wpdb;
	$range = array( '1=1' );
	if ( '' !== $from ) {
		$range[] = $wpdb->prepare( 'created_at >= %s', $from . ' 00:00:00' );
	}
	if ( '' !== $to ) {
		$range[] = $wpdb->prepare( 'created_at <= %s', $to . ' 23:59:59' );
	}
	$range = implode( ' AND ', $range );

	$key_of = function ( $source, $campaign ) use ( $group ) {
		if ( 'source' === $group ) {
			$campaign = '';
		} elseif ( 'campaign' === $group ) {
			$campaign = trim( explode( ' · ', (string) $campaign )[0] );
		}
		return array( $source . '|' . $campaign, $campaign );
	};
	$empty  = array(
		'leads'    => 0,
		'junk'     => 0,
		'quoted'   => 0,
		'contract' => 0,
		'paid'     => 0,
		'value'    => 0,
		'call'     => 0,
		'zalo'     => 0,
	);

	$rows  = array();
	$leads = $wpdb->get_results( 'SELECT source, campaign, status, value FROM ' . fireai_crm_table( 'leads' ) . " WHERE {$range}" ); // phpcs:ignore
	foreach ( $leads as $lead ) {
		list( $key, $campaign ) = $key_of( $lead->source, $lead->campaign );
		if ( ! isset( $rows[ $key ] ) ) {
			$rows[ $key ] = array_merge( $empty, array( 'source' => $lead->source, 'campaign' => $campaign ) );
		}
		$rank = fireai_crm_status_rank( $lead->status );
		$rows[ $key ]['leads']++;
		$rows[ $key ]['junk']     += 'junk' === $lead->status ? 1 : 0;
		$rows[ $key ]['quoted']   += $rank >= 1 ? 1 : 0;
		$rows[ $key ]['contract'] += $rank >= 2 ? 1 : 0;
		$rows[ $key ]['paid']     += $rank >= 3 ? 1 : 0;
		$rows[ $key ]['value']    += $rank >= 2 ? (int) $lead->value : 0;
	}

	$clicks = $wpdb->get_results( 'SELECT source, campaign, type, COUNT(*) AS c FROM ' . fireai_crm_table( 'clicks' ) . " WHERE {$range} GROUP BY source, campaign, type" ); // phpcs:ignore
	foreach ( $clicks as $click ) {
		list( $key, $campaign ) = $key_of( $click->source, $click->campaign );
		if ( ! isset( $rows[ $key ] ) ) {
			$rows[ $key ] = array_merge( $empty, array( 'source' => $click->source, 'campaign' => $campaign ) );
		}
		if ( isset( $rows[ $key ][ $click->type ] ) ) {
			$rows[ $key ][ $click->type ] += (int) $click->c;
		}
	}

	uasort(
		$rows,
		function ( $a, $b ) {
			return array( $b['contract'], $b['leads'] ) <=> array( $a['contract'], $a['leads'] );
		}
	);

	$total = $empty;
	foreach ( $rows as $row ) {
		foreach ( $empty as $field => $zero ) {
			$total[ $field ] += $row[ $field ];
		}
	}
	return array( $rows, $total );
}

function fireai_crm_rate( $part, $whole ) {
	return $whole > 0 ? round( 100 * $part / $whole ) . '%' : '–';
}

function fireai_crm_page_report() {
	list( $from, $to, $preset ) = fireai_crm_report_range();
	$group                      = isset( $_GET['group'] ) && in_array( $_GET['group'], array( 'ad', 'campaign', 'source' ), true ) ? sanitize_key( $_GET['group'] ) : 'ad'; // phpcs:ignore
	list( $rows, $total )       = fireai_crm_report_data( $from, $to, $group );
	$base                       = admin_url( 'admin.php?page=fireai-crm-report' );
	$valid                      = $total['leads'] - $total['junk'];
	$presets                    = array(
		'this_month' => 'Tháng này',
		'last_month' => 'Tháng trước',
		'90d'        => '90 ngày',
		'all'        => 'Tất cả',
	);
	?>
	<div class="wrap fireai-crm">
		<h1>Báo cáo</h1>
		<p class="crm-muted">Tính theo ngày khách vào CRM. Khách đã ký hợp đồng được tính luôn là đã nhận báo giá.</p>

		<ul class="subsubsub">
			<?php
			$links = array();
			foreach ( $presets as $key => $label ) {
				$links[] = '<a href="' . esc_url( add_query_arg( array( 'range' => $key, 'group' => $group ), $base ) ) . '"' . ( $preset === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a>';
			}
			echo '<li>' . implode( ' |</li><li>', $links ) . '</li>'; // phpcs:ignore
			?>
		</ul>
		<form method="get" class="crm-filters">
			<input type="hidden" name="page" value="fireai-crm-report">
			<label>Từ <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
			<label>đến <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
			<select name="group">
				<option value="ad" <?php selected( $group, 'ad' ); ?>>Theo mẫu quảng cáo</option>
				<option value="campaign" <?php selected( $group, 'campaign' ); ?>>Theo chiến dịch</option>
				<option value="source" <?php selected( $group, 'source' ); ?>>Theo nguồn</option>
			</select>
			<button class="button">Xem</button>
		</form>

		<div class="crm-cards">
			<div class="crm-card"><span>Khách vào</span><strong><?php echo esc_html( $total['leads'] ); ?></strong><em><?php echo esc_html( $total['junk'] ); ?> sai số / rác</em></div>
			<div class="crm-card"><span>Đã gửi báo giá</span><strong><?php echo esc_html( $total['quoted'] ); ?></strong><em><?php echo esc_html( fireai_crm_rate( $total['quoted'], $valid ) ); ?> khách hợp lệ</em></div>
			<div class="crm-card"><span>Đã ký hợp đồng</span><strong><?php echo esc_html( $total['contract'] ); ?></strong><em><?php echo esc_html( fireai_crm_rate( $total['contract'], $valid ) ); ?> khách hợp lệ</em></div>
			<div class="crm-card"><span>Đã chuyển tiền</span><strong><?php echo esc_html( $total['paid'] ); ?></strong><em><?php echo esc_html( fireai_crm_rate( $total['paid'], $total['contract'] ) ); ?> số đã ký</em></div>
			<div class="crm-card"><span>Giá trị HĐ / tháng</span><strong><?php echo esc_html( fireai_crm_money( $total['value'] ) ); ?></strong><em>các hợp đồng đã ký</em></div>
		</div>

		<table class="wp-list-table widefat fixed striped crm-report">
			<thead>
				<tr>
					<th class="col-name"><?php echo 'source' === $group ? 'Nguồn' : 'Nguồn · Chiến dịch'; ?></th>
					<th>Bấm Gọi</th>
					<th>Bấm Zalo</th>
					<th>Khách vào</th>
					<th>Báo giá</th>
					<th>Ký HĐ</th>
					<th>Tỉ lệ ký</th>
					<th>Chuyển tiền</th>
					<th class="col-value">Giá trị HĐ / tháng</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="9">Chưa có dữ liệu trong khoảng thời gian này.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td class="col-name">
						<strong><?php echo esc_html( fireai_crm_label( fireai_crm_sources(), $row['source'] ) ); ?></strong>
						<?php if ( '' !== $row['campaign'] ) : ?><br><span class="crm-muted"><?php echo esc_html( $row['campaign'] ); ?></span><?php endif; ?>
					</td>
					<td><?php echo esc_html( $row['call'] ); ?></td>
					<td><?php echo esc_html( $row['zalo'] ); ?></td>
					<td><?php echo esc_html( $row['leads'] ); ?><?php echo $row['junk'] ? ' <span class="crm-muted">(' . esc_html( $row['junk'] ) . ' rác)</span>' : ''; ?></td>
					<td><?php echo esc_html( $row['quoted'] ); ?></td>
					<td><strong><?php echo esc_html( $row['contract'] ); ?></strong></td>
					<td><?php echo esc_html( fireai_crm_rate( $row['contract'], $row['leads'] - $row['junk'] ) ); ?></td>
					<td><?php echo esc_html( $row['paid'] ); ?></td>
					<td class="col-value"><?php echo $row['value'] ? esc_html( fireai_crm_money( $row['value'] ) ) : '–'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
