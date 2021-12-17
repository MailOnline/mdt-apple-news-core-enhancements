<?php

namespace MDT\Apple_News_Core_Enhancements;

/**
 * Class Gutenberg
 *
 * @package MDT\Apple_News_Core_Enhancements
 */
class Gutenberg {

	const PUBLISH_META_KEY = 'mdt-publish-to-apple-news';

	/**
	 * Init
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
		add_action( 'init', [ __CLASS__, 'add_actions' ] );
	}

	/**
	 * Admin actions
	 */
	public static function add_actions() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_field' ] );
	}


	/**
	 * Enqueue the block editor plugin script
	 */
	public static function enqueue_block_editor_assets() {
		if ( 'post' === get_post_type() ) {
			$asset_data = include( __DIR__ . 'build/index.asset.php' );
			wp_enqueue_script(
				'mdt-gutenberg-apple-news',
				plugin_dir_url( __FILE__ ) . 'build/index.js',
				$asset_data['dependencies'],
				$asset_data['version'],
				true
			);
		}
	}

	/**
	 * Exposing the 'mdt-publish-to-apple-news' meta value to the rest api so that we can read/write to it from
	 * a Gutenberg plugin
	 */
	public static function register_rest_field() {
		register_rest_field(
			'post',
			'publish_to_apple_news',
			[
				'get_callback'    => [ __CLASS__, 'get_meta' ],
				'update_callback' => [ __CLASS__, 'update_meta' ],
			]
		);
	}

	/**
	 * Fetches the meta value for use in the rest api. If a value doesn't already exist we
	 * return true as the default.
	 *
	 * @param $object array rest_api object
	 * @return bool
	 */
	public static function get_meta( $object ) {
		$post_id = $object['id'];
		if ( metadata_exists( 'post', $post_id, self::PUBLISH_META_KEY ) ) {
			$meta_value = get_post_meta( $post_id, self::PUBLISH_META_KEY, true );
			return filter_var( $meta_value, FILTER_VALIDATE_BOOLEAN );
		}
		return true;
	}

	/**
	 * Updates the meta value
	 *
	 * @param $value bool
	 * @param $post \WP_Post
	 */
	public static function update_meta( $value, $post ) {
		$publish = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		update_post_meta( $post->ID, self::PUBLISH_META_KEY, $publish );
	}
}

Gutenberg::init();

