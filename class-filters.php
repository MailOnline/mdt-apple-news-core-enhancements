<?php

namespace MDT\Apple_News_Core_Enhancements;

/**
 * Class Filters
 *
 * @package MDT\Apple_News_Core_Enhancements
 */
class Filters {

	/**
	 * Attach all filters
	 */
	public static function init() {
		add_filter( 'apple_news_post_args', [ __CLASS__, 'increase_api_timeout' ] );
		add_filter( 'apple_news_skip_push', [ __CLASS__, 'skip_sending_post_to_apple_news' ], 10, 2 );
		add_filter( 'apple_news_initialize_components', [ __CLASS__, 'add_custom_components' ] );
	}

	/**
	 * Increase timeout for apple-news api requests from production environments to combat
	 * occasional slowness from the publishing api.
	 *
	 * @param $args HTTP request args
	 *
	 * @return mixed Filtered HTTP request args
	 */
	public function increase_api_timeout( $args ) {
		if ( function_exists( 'wp_get_environment_type' ) && 'production' === wp_get_environment_type() ) {
			//phpcs:ignore
			$args['timeout'] = 120;
		}
		return $args;
	}

	/**
	 * Determines whether the post currently being filtered should be pushed to Apple News or not.
	 *
	 * Exceptions are thrown if rejected as the publish-to-apple-news plugins uses these to create the relevant
	 * user notices
	 *
	 * @param bool $reject
	 * @param int  $post_id
	 *
	 * @throws \Apple_Actions\Action_Exception
	 * @return bool
	 */
	public function skip_sending_post_to_apple_news( $reject, $post_id ) {

		$post     = get_post( $post_id );
		$headline = '"' . get_the_title( $post ) . '" (' . $post_id . ')';

		// Reject if 'Auto-publish to Apple News?' has been set to false.
		$auto_publish = true;
		if ( metadata_exists( 'post', $post->ID, Gutenberg::PUBLISH_META_KEY ) ) {
			$meta_value   = get_post_meta( $post->ID, Gutenberg::PUBLISH_META_KEY, true );
			$auto_publish = filter_var( $meta_value, FILTER_VALIDATE_BOOLEAN );
		}

		if ( ! $auto_publish ) {
			throw new \Apple_Actions\Action_Exception(
				$this->generate_error_message(
					'Not publishing due to the \'Publish to Apple News?\' field being unchecked',
					$headline
				)
			);
		}

		return $reject;
	}

	/**
	 * Register custom components for the publish-to-apple-news plugin to use
	 *
	 * @param $components
	 * @return mixed Array of components classes
	 */
	public function add_custom_components( $components ) {
		require_once( __DIR__ . '/components/class-tiktok.php' );

		// Merging array to ensure out tiktok component runs before the embed-generic component which output
		// the tiktok as a simple link instead.
		$components = array_merge( [ 'tiktok' => '\MDT\Apple_News_Core_Enhancements\Components\Tiktok' ], $components );

		return $components;
	}

	/**
	 * Simple helper for building an error message
	 *
	 * @param $error
	 * @param $headline
	 *
	 * @return string
	 */
	public function generate_error_message( $error, $headline ) {
		return sprintf(
			'Error: %s for Article: %s',
			esc_html( $error ),
			esc_html( $headline )
		);
	}
}
