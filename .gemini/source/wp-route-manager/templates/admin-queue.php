<?php
/**
 * @var WP_Route_Manager\Storage\PayloadQueue $queue
 * @var array<string,int>                      $counts
 * @var array{items: object[], total: int}      $result
 * @var string                                  $filter_status
 * @var int                                     $filter_endpoint
 * @var WP_Post[]                               $endpoints
 */
defined( 'ABSPATH' ) || exit;

$has_action_scheduler = function_exists( 'as_schedule_single_action' );
?>
<div class="wrap wprm-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-database-view"></span>
		<?php esc_html_e( 'Payload Queue', 'wp-route-manager' ); ?>
	</h1>
	<span class="title-count theme-count"><?php echo (int) array_sum( $counts ); ?></span>

	<?php if ( ! $has_action_scheduler ) : ?>
	<div class="notice notice-warning inline" style="margin:12px 0">
		<p>
			<?php esc_html_e( 'Action Scheduler is not active. Queue items will use WP Cron for deferred processing. Install WooCommerce or the standalone Action Scheduler plugin for reliable background processing.', 'wp-route-manager' ); ?>
		</p>
	</div>
	<?php endif; ?>

	<!-- Status tabs -->
	<ul class="subsubsub" style="margin-bottom:16px">
		<?php
		$statuses = [
			''           => __( 'All', 'wp-route-manager' ),
			'pending'    => __( 'Pending', 'wp-route-manager' ),
			'processing' => __( 'Processing', 'wp-route-manager' ),
			'held'       => __( 'Held', 'wp-route-manager' ),
			'completed'  => __( 'Completed', 'wp-route-manager' ),
			'failed'     => __( 'Failed', 'wp-route-manager' ),
		];

		$total_all = (int) array_sum( $counts );
		$status_counts = array_merge( [ '' => $total_all ], $counts );

		$items = array_keys( $statuses );
		foreach ( $items as $i => $s ) :
			$count = $status_counts[ $s ] ?? $total_all;
			$url   = admin_url( 'admin.php?page=wprm-queue&status=' . rawurlencode( $s ) . ( $filter_endpoint ? '&endpoint_id=' . $filter_endpoint : '' ) );
			$class = ( $filter_status === $s ) ? 'class="current"' : '';
			$sep   = ( $i < count( $items ) - 1 ) ? ' |' : '';
			printf(
				'<li><a href="%s" %s>%s <span class="count">(%d)</span></a>%s </li>',
				esc_url( $url ),
				$class,
				esc_html( $statuses[ $s ] ),
				$count,
				$sep
			);
		endforeach;
		?>
	</ul>

	<!-- Action bar -->
	<div class="wprm-queue-actions" style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">

		<!-- Filter by endpoint -->
		<form method="get" style="display:flex;gap:6px;align-items:center">
			<input type="hidden" name="page" value="wprm-queue">
			<?php if ( $filter_status ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $filter_status ); ?>">
			<?php endif; ?>
			<select name="endpoint_id">
				<option value=""><?php esc_html_e( 'All Endpoints', 'wp-route-manager' ); ?></option>
				<?php foreach ( $endpoints as $ep ) : ?>
				<option value="<?php echo (int) $ep->ID; ?>" <?php selected( $filter_endpoint, $ep->ID ); ?>>
					<?php echo esc_html( $ep->post_title ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-route-manager' ); ?></button>
		</form>

		<div style="flex:1"></div>

		<!-- Chunk runner -->
		<div style="display:flex;align-items:center;gap:8px;border:1px solid #ddd;border-radius:4px;padding:6px 12px;background:#f9f9f9">
			<label style="font-weight:600;font-size:13px"><?php esc_html_e( 'Process chunk:', 'wp-route-manager' ); ?></label>
			<input type="number" id="wprm-chunk-size" value="20" min="1" max="500" style="width:70px">
			<button id="wprm-run-chunk" class="button button-primary">
				<span class="dashicons dashicons-controls-play" style="line-height:1.8;margin-right:4px"></span>
				<?php esc_html_e( 'Run Now', 'wp-route-manager' ); ?>
			</button>
			<span id="wprm-chunk-result" style="font-size:12px;color:#46b450;display:none"></span>
		</div>

		<!-- Release held -->
		<?php if ( $counts['held'] ?? 0 ) : ?>
		<button id="wprm-release-held" class="button" style="background:#f0b849;border-color:#c89f2b;color:#1d2327">
			<span class="dashicons dashicons-unlock" style="line-height:1.8;margin-right:4px"></span>
			<?php printf( esc_html__( 'Release %d Held', 'wp-route-manager' ), (int) $counts['held'] ); ?>
		</button>
		<?php endif; ?>

		<!-- Clear completed -->
		<?php if ( $counts['completed'] ?? 0 ) : ?>
		<button id="wprm-clear-completed" class="button" data-status="completed"
		        data-confirm="<?php esc_attr_e( 'Delete all completed queue entries?', 'wp-route-manager' ); ?>">
			<?php printf( esc_html__( 'Clear %d Completed', 'wp-route-manager' ), (int) $counts['completed'] ); ?>
		</button>
		<?php endif; ?>

		<!-- Retry failed -->
		<?php if ( $counts['failed'] ?? 0 ) : ?>
		<button id="wprm-retry-failed" class="button" style="color:#d63638;border-color:#d63638">
			<span class="dashicons dashicons-update" style="line-height:1.8;margin-right:4px"></span>
			<?php printf( esc_html__( 'Retry %d Failed', 'wp-route-manager' ), (int) $counts['failed'] ); ?>
		</button>
		<?php endif; ?>

	</div>

	<!-- Queue table -->
	<table class="wp-list-table widefat fixed striped" id="wprm-queue-table">
		<thead>
			<tr>
				<th style="width:50px"><?php esc_html_e( '#', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Endpoint', 'wp-route-manager' ); ?></th>
				<th style="width:100px"><?php esc_html_e( 'Status', 'wp-route-manager' ); ?></th>
				<th style="width:60px"><?php esc_html_e( 'Priority', 'wp-route-manager' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Attempts', 'wp-route-manager' ); ?></th>
				<th style="width:170px"><?php esc_html_e( 'Scheduled', 'wp-route-manager' ); ?></th>
				<th style="width:170px"><?php esc_html_e( 'Completed', 'wp-route-manager' ); ?></th>
				<th><?php esc_html_e( 'Payload Preview', 'wp-route-manager' ); ?></th>
				<th style="width:160px"><?php esc_html_e( 'Actions', 'wp-route-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( $result['items'] ) : foreach ( $result['items'] as $item ) :
			$payload = json_decode( $item->payload, true );
			$preview = $payload ? implode( ', ', array_slice( array_map(
				fn( $k, $v ) => $k . ': ' . ( is_string( $v ) ? substr( $v, 0, 30 ) : gettype( $v ) ),
				array_keys( (array) $payload ), array_values( (array) $payload )
			), 0, 3 ) ) : $item->payload;

			$status_colours = [
				'pending'    => '#0073aa',
				'processing' => '#f0b849',
				'held'       => '#826eb4',
				'completed'  => '#46b450',
				'failed'     => '#d63638',
			];
			$colour = $status_colours[ $item->status ] ?? '#666';
		?>
		<tr id="wprm-queue-row-<?php echo (int) $item->id; ?>">
			<td><strong>#<?php echo (int) $item->id; ?></strong></td>
			<td>
				<?php if ( $item->endpoint_id ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( (int) $item->endpoint_id ) ); ?>">
						<?php echo esc_html( $item->endpoint_slug ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $item->endpoint_slug ); ?>
				<?php endif; ?>
			</td>
			<td>
				<span class="wprm-status" style="background:<?php echo esc_attr( $colour ); ?>20;color:<?php echo esc_attr( $colour ); ?>;border:1px solid <?php echo esc_attr( $colour ); ?>40;padding:2px 8px;border-radius:20px;font-size:12px;font-weight:600">
					<?php echo esc_html( ucfirst( $item->status ) ); ?>
				</span>
			</td>
			<td style="text-align:center"><?php echo (int) $item->priority; ?></td>
			<td style="text-align:center"><?php echo (int) $item->attempts; ?>/<?php echo (int) $item->max_attempts; ?></td>
			<td>
				<abbr title="<?php echo esc_attr( $item->scheduled_at ); ?>">
					<?php echo esc_html( human_time_diff( strtotime( $item->scheduled_at ) ) . ' ago' ); ?>
				</abbr>
			</td>
			<td>
				<?php if ( $item->completed_at ) : ?>
					<abbr title="<?php echo esc_attr( $item->completed_at ); ?>">
						<?php echo esc_html( human_time_diff( strtotime( $item->completed_at ) ) . ' ago' ); ?>
					</abbr>
				<?php elseif ( $item->error ) : ?>
					<span style="color:#d63638;font-size:11px"><?php echo esc_html( substr( $item->error, 0, 60 ) ); ?></span>
				<?php else : ?>
					—
				<?php endif; ?>
			</td>
			<td style="font-size:11px;font-family:monospace;color:#50575e">
				<?php echo esc_html( $preview ); ?>
			</td>
			<td>
				<button class="button button-small wprm-queue-view"
				        data-id="<?php echo (int) $item->id; ?>"
				        data-payload="<?php echo esc_attr( $item->payload ); ?>"
				        data-result="<?php echo esc_attr( $item->result ?? '' ); ?>"
				        data-error="<?php echo esc_attr( $item->error ?? '' ); ?>">
					<?php esc_html_e( 'View', 'wp-route-manager' ); ?>
				</button>
				<?php if ( in_array( $item->status, [ 'held', 'failed' ], true ) ) : ?>
				<button class="button button-small wprm-queue-release" data-id="<?php echo (int) $item->id; ?>"
				        style="margin-top:4px;display:block">
					<?php esc_html_e( 'Release', 'wp-route-manager' ); ?>
				</button>
				<?php endif; ?>
				<button class="button button-small wprm-queue-delete" data-id="<?php echo (int) $item->id; ?>"
				        style="margin-top:4px;display:block;color:#d63638;border-color:#d63638">
					<?php esc_html_e( 'Delete', 'wp-route-manager' ); ?>
				</button>
			</td>
		</tr>
		<?php endforeach; else : ?>
		<tr><td colspan="9" style="text-align:center;padding:40px;color:#666">
			<?php esc_html_e( 'No queue entries found.', 'wp-route-manager' ); ?>
		</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $result['total'] > 50 ) : ?>
	<p style="margin-top:12px;color:#50575e">
		<?php printf( esc_html__( 'Showing first 50 of %d entries.', 'wp-route-manager' ), (int) $result['total'] ); ?>
	</p>
	<?php endif; ?>

</div><!-- /wrap -->

<!-- Queue item detail modal -->
<div id="wprm-queue-modal" class="wprm-modal" style="display:none" role="dialog" aria-modal="true">
	<div class="wprm-modal-backdrop"></div>
	<div class="wprm-modal-box">
		<div class="wprm-modal-header">
			<h2><?php esc_html_e( 'Queue Item Detail', 'wp-route-manager' ); ?> <span id="wprm-qm-id"></span></h2>
			<button class="wprm-modal-close" aria-label="Close">✕</button>
		</div>
		<div class="wprm-modal-body">
			<div class="wprm-log-tabs">
				<button class="wprm-tab-btn active" data-tab="qm-payload"><?php esc_html_e( 'Payload', 'wp-route-manager' ); ?></button>
				<button class="wprm-tab-btn" data-tab="qm-result"><?php esc_html_e( 'Result', 'wp-route-manager' ); ?></button>
				<button class="wprm-tab-btn" data-tab="qm-error"><?php esc_html_e( 'Error', 'wp-route-manager' ); ?></button>
			</div>
			<div class="wprm-tab-panel active" id="wprm-tab-qm-payload">
				<pre id="wprm-qm-payload" class="wprm-pre"></pre>
			</div>
			<div class="wprm-tab-panel" id="wprm-tab-qm-result">
				<pre id="wprm-qm-result" class="wprm-pre"></pre>
			</div>
			<div class="wprm-tab-panel" id="wprm-tab-qm-error">
				<pre id="wprm-qm-error" class="wprm-pre wprm-pre--error"></pre>
			</div>
		</div>
	</div>
</div>
