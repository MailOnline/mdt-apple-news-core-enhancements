<?php
/**
 * Plugin Name: Apple News Core Enhancements
 * Plugin URI:  https://github.com/MailOnline/mdt-apple-news-core-enhancements/
 * Description: Core enhancements for the publish-to-apple-news plugin
 * Version:     0.0.1
 * Author:      Metro.co.uk
 * Author URI:  https://github.com/MailOnline/mdt-apple-news-auto-retry/graphs/contributors
 */

namespace MDT\Apple_News_Core_Enhancements;

if ( ! class_exists( '\Apple_News' ) || ! class_exists( '\Admin_Apple_Settings' ) ) {
	return;
}

require_once( __DIR__ . '/class-gutenberg.php' );
require_once( __DIR__ . '/class-admin.php' );
require_once( __DIR__ . '/class-notices.php' );
require_once( __DIR__ . '/class-filters.php' );
require_once( __DIR__ . '/class-auto-retry.php' );

add_action('init', function(){
	Notices::init();
	Filters::init();
	Auto_Retry::init();
	Admin::init();
	Gutenberg::init();
});


