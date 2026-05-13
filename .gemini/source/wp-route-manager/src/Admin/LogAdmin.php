<?php
declare( strict_types=1 );

namespace WP_Route_Manager\Admin;

use WP_Route_Manager\DB\LogRepository;
use WP_Route_Manager\PostTypes\EndpointPostType;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class LogAdmin {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-route-manager' ) );
		}

		$table = new LogListTable();
		$table->prepare_items();

		$endpoint_id = absint( $_GET['endpoint_id'] ?? 0 );
		$endpoints   = get_posts( [
			'post_type'      => EndpointPostType::POST_TYPE,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );

		require WPRM_DIR . 'templates/admin-logs.php';
	}
}

// ── WP_List_Table implementation ──────────────────────────────────────────────

final class LogListTable extends \WP_List_Table {

	private LogRepository $logs;

	public function __construct() {
		parent::__construct( [
			'singular' => 'log',
			'plural'   => 'logs',
			'ajax'     => true,
		] );
		$this->logs = new LogRepository();
	}

	/** @return array<string,string> */
	public function get_columns(): array {
		return [
			'cb'            => '<input type="checkbox">',
			'id'            => __( '#', 'wp-route-manager' ),
			'endpoint_slug' => __( 'Endpoint', 'wp-route-manager' ),
			'method'        => __( 'Method', 'wp-route-manager' ),
			'caller_ip'     => __( 'IP', 'wp-route-manager' ),
			'response_code' => __( 'Status', 'wp-route-manager' ),
			'duration_ms'   => __( 'Duration', 'wp-route-manager' ),
			'created_at'    => __( 'Time', 'wp-route-manager' ),
			'actions_col'   => __( 'Actions', 'wp-route-manager' ),
		];
	}

	/** @return array<string,string> */
	protected function get_sortable_columns(): array {
		return [
			'id'            => [ 'id', true ],
			'response_code' => [ 'response_code', false ],
			'duration_ms'   => [ 'duration_ms', false ],
			'created_at'    => [ 'created_at', true ],
		];
	}

	protected function get_bulk_actions(): array {
		return [ 'delete' => __( 'Delete', 'wp-route-manager' ) ];
	}

	public function prepare_items(): void {
		$per_page    = 25;
		$current     = $this->get_pagenum();
		$offset      = ( $current - 1 ) * $per_page;

		$args = [
			'limit'       => $per_page,
			'offset'      => $offset,
			'endpoint_id' => absint( $_GET['endpoint_id'] ?? 0 ) ?: null,
			'response_code' => absint( $_GET['response_code'] ?? 0 ) ?: null,
		];

		$result      = $this->logs->query( array_filter( $args ) );
		$this->items = $result['items'];

		$this->set_pagination_args( [
			'total_items' => $result['total'],
			'per_page'    => $per_page,
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	protected function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '—' );
	}

	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d">', (int) $item->id );
	}

	protected function column_id( $item ): string {
		return '<strong>#' . esc_html( (string) $item->id ) . '</strong>';
	}

	protected function column_endpoint_slug( $item ): string {
		$ep_id  = (int) $item->endpoint_id;
		$slug   = esc_html( (string) $item->endpoint_slug );
		if ( $ep_id ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $ep_id ) ), $slug );
		}
		return $slug ?: '—';
	}

	protected function column_method( $item ): string {
		$colours = [
			'GET'    => '#0073aa',
			'POST'   => '#46b450',
			'PUT'    => '#f56e28',
			'PATCH'  => '#826eb4',
			'DELETE' => '#dc3232',
		];
		$m = strtoupper( (string) $item->method );
		$c = $colours[ $m ] ?? '#666';
		return sprintf( '<span class="wprm-badge" style="background:%s">%s</span>', esc_attr( $c ), esc_html( $m ) );
	}

	protected function column_response_code( $item ): string {
		$code = (int) $item->response_code;
		$cls  = $code >= 400 ? 'wprm-status--error' : 'wprm-status--active';
		return sprintf( '<span class="wprm-status %s">%d</span>', esc_attr( $cls ), $code );
	}

	protected function column_duration_ms( $item ): string {
		return esc_html( (string) $item->duration_ms ) . 'ms';
	}

	protected function column_created_at( $item ): string {
		$ts = strtotime( (string) $item->created_at );
		return sprintf(
			'<abbr title="%s">%s</abbr>',
			esc_attr( date_i18n( 'Y-m-d H:i:s', $ts ) ),
			esc_html( human_time_diff( $ts ) . ' ' . __( 'ago', 'wp-route-manager' ) )
		);
	}

	protected function column_actions_col( $item ): string {
		return sprintf(
			'<button class="button button-small wprm-view-log" data-id="%d">%s</button> '
			. '<button class="button button-small wprm-delete-log" data-id="%d">%s</button>',
			(int) $item->id, esc_html__( 'View', 'wp-route-manager' ),
			(int) $item->id, esc_html__( 'Delete', 'wp-route-manager' )
		);
	}

	protected function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<button id="wprm-clear-all-logs" class="button" data-confirm="<?php esc_attr_e( 'Clear all logs? Cannot be undone.', 'wp-route-manager' ); ?>">
				<?php esc_html_e( 'Clear All Logs', 'wp-route-manager' ); ?>
			</button>
		</div>
		<?php
	}
}
