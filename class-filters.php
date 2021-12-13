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
}
