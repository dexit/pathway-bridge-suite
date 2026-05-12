<?php
/**
 * Class WP_Post_Bridge
 *
 * @package postsbridge
 */

namespace POSTS_BRIDGE;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * WordPress Post Bridge
 */
class WP_Post_Bridge extends Post_Bridge {

	/**
	 * Bridge constructor.
	 *
	 * @param array $data Bridge data.
	 */
	public function __construct( $data ) {
		parent::__construct( $data, 'wp' );
		$this->data['single_endpoint'] = rtrim( $data['endpoint'], '/' ) . '/{id}';
	}

	/**
	 * Fetches remote data for a given foreign id.
	 *
	 * @param int   $foreign_id Remote post ID.
	 * @param array $params Request query params.
	 * @param array $headers Request headers.
	 *
	 * @return array|WP_Error
	 */
	public function fetch_one( $foreign_id, $params = array(), $headers = array() ) {
		if ( ! $this->is_valid ) {
			return new WP_Error( 'invalid_bridge', 'Bridge is invalid', (array) $this->data );
		}

		$params['context'] = 'edit';

		$endpoint = $this->endpoint( $foreign_id );
		$response = parent::request( $endpoint, $params, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $this->remote_data( $response['data'] );
		return $data;
	}

	/**
	 * Peforms a recursive request through paged responses to get all post IDs.
	 *
	 * @param array $params Ignored.
	 * @param array $headers HTTP headers.
	 *
	 * @return array|WP_Error List of remote model IDs.
	 */
	public function fetch_all( $params = array(), $headers = array() ) {
		return $this->get_paged_ids();
	}

	/**
	 * Peforms a recursive request through paged responses to get all post IDs.
	 *
	 * @param integer $page Current page index.
	 *
	 * @return array|WP_Error
	 */
	private function get_paged_ids( $page = 1 ) {
		$pages    = 1e10;
		$endpoint = $this->endpoint();
		$backend  = $this->backend;

		$posts = array();
		while ( $page <= $pages ) {
			$res = $backend->get(
				$endpoint,
				array(
					'context'  => 'embed',
					'_fields'  => 'id',
					'per_page' => '100',
					'page'     => $page,
				),
				array(
					'Accept' => 'application/json',
				)
			);

			if ( is_wp_error( $res ) ) {
				return $res;
			}

			if ( empty( $res['data'] ) ) {
				break;
			}

			$posts = array_merge( $posts, (array) $res['data'] );
			$pages = (int) $res['headers']['x-wp-totalpages'];
			++$page;
		}

		return $posts;
	}

	/**
	 * Traverse post response links to fetch full post data and formats the return.
	 *
	 * @param array $data REST response data.
	 *
	 * @return array
	 */
	private function remote_data( $data ) {
		$backend = $this->backend();

		unset( $data['guid'] );

		if ( wp_is_rest_endpoint() ) {
			unset( $data['id'] );
		}

		// Replace attachment URLs from post content by local attachment URLs.
		$attachments = $data['_links']['wp:attachments'] ?? array();
		foreach ( $attachments as $attachment ) {
			$res = $backend->get( $attachment['href'] );
			if ( is_wp_error( $res ) ) {
				continue;
			}

			$attachments = $res['data'];

			foreach ( $attachments as $attachment ) {
				$attachment_id = Remote_Featured_Media::handle( $attachment['source_url'] );

				if ( $attachment_id ) {
					$url = wp_get_attachment_url( $attachment_id );

					$data['post_content'] = str_replace(
						$attachment['source_url'],
						$url,
						$data['post_content']
					);
				}
			}
		}

		// Replace featured_media ID by its remote URL.
		$featured_media_href = $data['_links']['wp:featuredmedia'][0]['href'] ?? null;
		if ( $featured_media_href ) {
			$res = $backend->get( $featured_media_href );

			if ( ! is_wp_error( $res ) ) {
				$attachment             = $res['data'];
				$data['featured_media'] = $attachment['source_url'];
			}
		}

		// Fetch remote taxonomy terms and replace IDs by names.
		$taxonomies       = $data['_links']['wp:term'] ?? array();
		$known_taxonomies = $taxonomies ? array_keys( get_taxonomies() ) : array();
		foreach ( $taxonomies as $tax ) {
			if ( ! in_array( $tax['taxonomy'], $known_taxonomies, true ) ) {
				continue;
			}

			if ( 'category' === $tax['taxonomy'] ) {
				if ( empty( $data['categories'] ) ) {
					continue;
				}
			} elseif ( 'post_tag' === $tax['taxonomy'] ) {
				if ( empty( $data['tags'] ) ) {
					continue;
				}
			} elseif ( empty( $data[ $tax['taxonomy'] ] ) ) {
				continue;
			}

			$field = 'post_tag' === $tax['taxonomy']
				? 'tags'
				: ( 'category' === $tax['taxonomy']
					? 'categories'
					: $tax['taxonomy'] );

			$res = $backend->get( $tax['href'] );
			if ( is_wp_error( $res ) ) {
				continue;
			}

			$names = array();
			$terms = $res['data'];
			foreach ( $terms as $term ) {
				$names[] = $term['name'];
			}

			$data[ $field ] = $names;
		}

		return $data;
	}
}
