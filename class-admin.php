<?php

namespace MDT\Apple_News_Core_Enhancements;

/**
 * Class Admin
 *
 * @package MDT\Apple_News_Core_Enhancements
 */
class Admin {

	/**
	 * Admin constructor.
	 */
	public static function init() {
		add_filter( 'manage_posts_columns', [ __CLASS__, 'register_custom_manage_posts_column' ], 10, 2 );
		add_action( 'manage_posts_custom_column', [ __CLASS__, 'custom_manage_posts_content' ], 10, 2 );
	}

	public static function output_columns() {
		return apply_filters('mdt_apple_news_ce_output_columns', true);
	}

	/**
	 * Adds the Apple News Status column to the manage posts view
	 *
	 * @param $defaults
	 * @param $post_type
	 * @return mixed
	 */
	public function register_custom_manage_posts_column( $defaults, $post_type ) {
		if (
			self::output_columns()
			&& $post_type === 'post'
			&& self::is_valid_user()
		) {
			$defaults['apple_news_status'] = 'Apple News Status';
		}

		return $defaults;
	}

	/**
	 * Echoes out the apple news status as returned from the plugin itself
	 *
	 * @param $column_name
	 * @param $post_id
	 */
	public function custom_manage_posts_content( $column_name, $post_id ) {
		if (
			self::output_columns()
			&& class_exists( '\Admin_Apple_News' )
			&& 'apple_news_status' === $column_name
		) {
			echo esc_html( \Admin_Apple_News::get_post_status( $post_id ) );
		}
	}

	/**
	 * Is the user an author or higher
	 *
	 * @return bool
	 */
	public static function is_valid_user() {

		$capability = apply_filters( 'mdt_apple_news_ce_columns_capability', 'edit_published_posts' );

		if ( ! current_user_can( $capability ) ) {
			return false;
		}
		return true;
	}
}

new Admin();
