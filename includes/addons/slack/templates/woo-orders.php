<?php
/**
 * Slack WooCommerce orders channel bridge template
 *
 * @package formsbridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

return array(
	'title'        => __( 'Woo Orders', 'forms-bridge' ),
	'description'  => __(
		'WooCommerce orders form template. The resulting bridge will notify new woocommerce customers to Slack channels',
		'forms-bridge'
	),
	'integrations' => array( 'woo' ),
	'fields'       => array(
		array(
			'ref'   => '#bridge',
			'name'  => 'endpoint',
			'value' => '/api/chat.postMessage',
		),
		array(
			'ref'     => '#form',
			'name'    => 'title',
			'default' => __( 'Contacts', 'forms-bridge' ),
		),
	),
	'bridge'       => array(
		'endpoint'  => '/api/chat.postMessage',
		'workflow'  => array( 'summary-md' ),
		'mutations' => array(
			array(
				array(
					'from' => 'id',
					'to'   => 'id',
					'cast' => 'null',
				),
				array(
					'from' => 'parent_id',
					'to'   => 'parent_id',
					'cast' => 'null',
				),
				array(
					'from' => 'status',
					'to'   => 'status',
					'cast' => 'null',
				),
				array(
					'from' => 'version',
					'to'   => 'version',
					'cast' => 'null',
				),
				array(
					'from' => 'prices_include_tax',
					'to'   => 'prices_include_tax',
					'cast' => 'null',
				),
				array(
					'from' => 'date_created',
					'to'   => 'fields["Order Date"]',
					'cast' => 'string',
				),
				array(
					'from' => 'date_modified',
					'to'   => 'date_modified',
					'cast' => 'null',
				),
				array(
					'from' => 'discount_total',
					'to'   => 'discount_total',
					'cast' => 'null',
				),
				array(
					'from' => 'discount_tax',
					'to'   => 'discount_tax',
					'cast' => 'null',
				),
				array(
					'from' => 'shipping_total',
					'to'   => 'shipping_total',
					'cast' => 'null',
				),
				array(
					'from' => 'shipping_tax',
					'to'   => 'shipping_tax',
					'cast' => 'null',
				),
				array(
					'from' => 'cart_total',
					'to'   => 'cart_total',
					'cast' => 'null',
				),
				array(
					'from' => 'cart_tax',
					'to'   => 'cart_tax',
					'cast' => 'null',
				),
				array(
					'from' => 'total',
					'to'   => 'fields["Order Total"]',
					'cast' => 'string',
				),
				array(
					'from' => 'total_tax',
					'to'   => 'total_tax',
					'cast' => 'null',
				),
				array(
					'from' => 'customer_id',
					'to'   => 'customer_id',
					'cast' => 'null',
				),
				array(
					'from' => 'order_key',
					'to'   => 'order_key',
					'cast' => 'null',
				),
				array(
					'from' => '?billing.company',
					'to'   => 'fields.Company',
					'cast' => 'string',
				),
				array(
					'from' => '?billing.first_name',
					'to'   => 'fields["First Name"]',
					'cast' => 'string',
				),
				array(
					'from' => 'billing.last_name',
					'to'   => 'fields["Last Name"]',
					'cast' => 'string',
				),
				array(
					'from' => 'billing.email',
					'to'   => 'fields.Email',
					'cast' => 'string',
				),
				array(
					'from' => '?billing.phone',
					'to'   => 'fields.Phone',
					'cast' => 'string',
				),
				array(
					'from' => 'billing',
					'to'   => 'billing',
					'cast' => 'null',
				),
				array(
					'from' => 'shipping',
					'to'   => 'shipping',
					'cast' => 'null',
				),
				array(
					'from' => 'payment_method',
					'to'   => 'payment_method',
					'cast' => 'null',
				),
				array(
					'from' => 'payment_method_title',
					'to'   => 'payment_method_title',
					'cast' => 'null',
				),
				array(
					'from' => 'transaction_id',
					'to'   => 'transaction_id',
					'cast' => 'null',
				),
				array(
					'from' => 'customer_ip_address',
					'to'   => 'ip_signup',
					'cast' => 'null',
				),
				array(
					'from' => 'customer_user_agent',
					'to'   => 'customer_user_agent',
					'cast' => 'null',
				),
				array(
					'from' => 'created_via',
					'to'   => 'created_via',
					'cast' => 'null',
				),
				array(
					'from' => 'customer_note',
					'to'   => 'notes',
					'cast' => 'null',
				),
				array(
					'from' => 'date_completed',
					'to'   => 'date_completed',
					'cast' => 'null',
				),
				array(
					'from' => 'date_paid',
					'to'   => 'date_paid',
					'cast' => 'null',
				),
				array(
					'from' => 'cart_hash',
					'to'   => 'cart_hash',
					'cast' => 'null',
				),
				array(
					'from' => 'order_stock_reduced',
					'to'   => 'order_stock_reduced',
					'cast' => 'null',
				),
				array(
					'from' => 'download_permissions_granted',
					'to'   => 'download_permissions_granted',
					'cast' => 'null',
				),
				array(
					'from' => 'new_order_email_sent',
					'to'   => 'new_order_email_sent',
					'cast' => 'null',
				),
				array(
					'from' => 'recorded_sales',
					'to'   => 'recorded_sales',
					'cast' => 'null',
				),
				array(
					'from' => 'recorded_coupon_usage_counts',
					'to'   => 'recorded_coupon_usage_counts',
					'cast' => 'null',
				),
				array(
					'from' => 'number',
					'to'   => 'number',
					'cast' => 'null',
				),
				array(
					'from' => 'tax_lines',
					'to'   => 'tax_lines',
					'cast' => 'null',
				),
				array(
					'from' => 'shipping_lines',
					'to'   => 'shipping_lines',
					'cast' => 'null',
				),
				array(
					'from' => 'fee_lines',
					'to'   => 'fee_lines',
					'cast' => 'null',
				),
				array(
					'from' => 'coupon_lines',
					'to'   => 'coupon_lines',
					'cast' => 'null',
				),
				array(
					'from' => 'line_items[].name',
					'to'   => 'fields.Products[].Name',
					'cast' => 'string',
				),
				array(
					'from' => 'line_items[].quantity',
					'to'   => 'fields.Products[].Quantity',
					'cast' => 'string',
				),
				array(
					'from' => 'line_items',
					'to'   => 'line_items',
					'cast' => 'null',
				),
				array(
					'from' => 'currency',
					'to'   => 'currency',
					'cast' => 'null',
				),
			),
		),
	),
);
