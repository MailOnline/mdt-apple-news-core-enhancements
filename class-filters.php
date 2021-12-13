<?php

namespace MDT\Apple_News_Core_Enhancements;

class Filters {
	public static function init () {
		add_filter( 'apple_news_initialize_components', [ __CLASS__, 'add_custom_components' ] );
	}

	/**
	 * Register custom components for the publish-to-apple-news plugin to use
	 *
	 * @param $components
	 * @return mixed Array of components classes
	 */
	public function add_custom_components($components ){
		require_once( __DIR__ . '/components/class-tiktok.php' );

		// Merging array to ensure out tiktok component runs before the embed-generic component which output
		// the tiktok as a simple link instead.
		$components = array_merge( [ 'tiktok' => '\MDT\Apple_News_Core_Enhancements\Components\Tiktok' ], $components );

		return $components;
	}
}
