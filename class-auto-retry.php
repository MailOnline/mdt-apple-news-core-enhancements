<?php

namespace MDT\Apple_News_Core_Enhancements;

/**
 * Class Auto_Retry
 *
 * @package MDT\Apple_News_Core_Enhancements
 */
class Auto_Retry {
	/**
	 * Cron event name
	 *
	 * @var string
	 */
	const CRON_EVENT = 'mdt_an_auto_retry_publish';

	/**
	 * Cron bulk event name
	 *
	 * @var string
	 */
	const CRON_BULK_EVENT = 'mdt_an_auto_retry_bulk_publish';

	/**
	 * Should schedule filter name
	 *
	 * @var string
	 */
	const FILTER_NAME_SHOULD_SCHEDULE = 'mdt_an_auto_retry_should_schedule';

	/**
	 * Should schedule filter name
	 *
	 * @var string
	 */
	const FILTER_NAME_SCHEDULE_INTERVAL = 'mdt_an_auto_retry_schedule_interval';

	/**
	 * Should retry again after failure filter name
	 *
	 * @var string
	 */
	const FILTER_NAME_SHOULD_RETRY_AGAIN_ON_FAILURE = 'mdt_an_auto_should_retry_on_failure';

	/**
	 * Retry success action name
	 *
	 * @var string
	 */
	const ACTION_NAME_RETRY_SUCCESS = 'mdt_an_auto_retry_push_success';

	/**
	 * Retry failure action name
	 *
	 * @var string
	 */
	const ACTION_NAME_RETRY_FAILURE = 'mdt_an_auto_retry_push_failure';

	/**
	 * Single Scheduling failure action name
	 *
	 * @var string
	 */
	const ACTION_NAME_SINGLE_SCHEDULE_FAILURE = 'mdt_an_auto_retry_single_schedule_failure';

	/**
	 * Attempts meta key name
	 *
	 * @var string
	 */
	const META_KEY_ATTEMPTS = 'mdt_an_auto_retry_attempts';

	/**
	 * Scheduled retry time meta key name
	 *
	 * @var string
	 */
	const META_KEY_SCHEDULED = 'mdt_an_auto_retry_next_scheduled';

	/**
	 * Last published via plugin meta key name
	 *
	 * @var string
	 */
	const META_KEY_PUBLISHED = 'mdt_an_auto_retry_last_published';

	/**
	 * Maximum retry attempts
	 *
	 * @var integer
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Init
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'add_actions' ] );

		self::retry_publish(10847884);
	}

	/**
	 * Binds the actions if the publish-to-apple-news plugin is
	 * also present
	 */
	public static function add_actions() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_add_half_hourly' ) );

		add_action( self::CRON_EVENT, array( __CLASS__, 'retry_publish' ), 10 );
		add_action( self::CRON_BULK_EVENT, array( __CLASS__, 'bulk_retry_publish' ), 10 );

		add_action( 'wp_after_insert_post', array( __CLASS__, 'schedule_auto_retry' ), 100, 2 );
		if ( ! wp_next_scheduled( self::CRON_BULK_EVENT ) ) {
			wp_schedule_event( time(), 'mdt_half_hourly', self::CRON_BULK_EVENT );
		}

		// On successful AN push delete remaining retry events + meta
		add_action( 'apple_news_after_push', array( __CLASS__, 'clear_existing_retry' ), 10 );
	}

	/**
	 * Add in a custom WP Cron schedule
	 */
	public static function cron_add_half_hourly( $schedules ) {
		$schedules['mdt_half_hourly'] = array(
			'interval' => 1800,
			'display'  => __( 'Half Hourly' ),
		);
		return $schedules;
	}


	/**
	 * Attempt retry
	 *
	 * @param int $post_id Post ID
	 */
	public static function retry_publish( $post_id ) {
		$an_id   = get_post_meta( $post_id, 'apple_news_api_id', true );
		$pending = get_post_meta( $post_id, 'apple_news_api_pending', true );
		$next    = get_post_meta( $post_id, self::META_KEY_SCHEDULED, true );

		// Do not perform sync push if the article already has an apple-news ID and isn't in a pending state or isn't
		// scheduled for an auto retry
		if ( $an_id && ! $pending && ! $next ) {
			self::clear_existing_retry( $post_id );
			return;
		}

		$attempt = get_post_meta( $post_id, self::META_KEY_ATTEMPTS, true ) ?: 1;
		$result  = self::do_sync_push( $post_id );

		if ( $result['success'] ) {
			$share_url = get_post_meta( $post_id, 'apple_news_api_share_url', true );
			$revision  = get_post_meta( $post_id, 'apple_news_api_revision', true );
			update_post_meta( $post_id, self::META_KEY_PUBLISHED, $revision );
			do_action( self::ACTION_NAME_RETRY_SUCCESS, $post_id, $share_url, $attempt );
		} else {
			do_action( self::ACTION_NAME_RETRY_FAILURE, $post_id, $result['error'], $attempt );

			$try_again = apply_filters( self::FILTER_NAME_SHOULD_RETRY_AGAIN_ON_FAILURE, true, $post_id, $result['error'] );
			// if failure, schedule for a further retry if below MAX_ATTEMPTS
			if ( $try_again && $attempt < self::MAX_ATTEMPTS ) {
				self::schedule_single_event( $post_id );
				$attempt++;
				update_post_meta( $post_id, self::META_KEY_ATTEMPTS, $attempt );
			}
		}
	}

	/**
	 * Perform a synchronous push to Apple News for the given $post_id
	 *
	 * @param int $post_id Post ID
	 * @return array Success or Error information
	 */
	public static function do_sync_push( $post_id ) {
		$admin_settings = new \Admin_Apple_Settings();
		$settings       = $admin_settings->fetch_settings();

		// pretend that the article is out of sync to have the plugin process accordingly in all cases.
		add_filter( 'apple_news_is_post_in_sync', '__return_false' );

		$action = new \Apple_Actions\Index\Push( $settings, $post_id );
		$error  = false;

		try {
			$action->perform( true );
		} catch ( \Apple_Actions\Action_Exception $e ) {
			$error = $e->getMessage();
		}

		remove_filter( 'apple_news_is_post_in_sync', '__return_false' );

		// if no error from push then cleanup
		if ( ! $error ) {
			self::clear_existing_retry( $post_id );
		}

		return [
			'success' => ! $error,
			'error'   => $error,
		];
	}


	/**
	 * See if a published post's content was updated to include a new video
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Updated post object.
	 */
	public static function schedule_auto_retry( $post_id, $post ) {
		// Do not schedule on autosaves or revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only schedule for published posts
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Only handle default posts
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// clear previous scheduled retry and attempt count
		self::clear_existing_retry( $post_id );

		// Allow clients to prevent retry scheduling e.g when an article isn't suitable
		$should_schedule = apply_filters( self::FILTER_NAME_SHOULD_SCHEDULE, true, $post_id );

		if ( $should_schedule ) {
			self::schedule_single_event( $post_id );
		}
	}

	/**
	 * Schedule the retry attempt 2 minutes (default) into the future
	 *
	 * @param int $post_id Post ID
	 */
	public static function schedule_single_event( $post_id ) {
		$interval  = (int) apply_filters( self::FILTER_NAME_SCHEDULE_INTERVAL, 120 );
		$time      = time() + $interval;
		$scheduled = wp_schedule_single_event( $time, self::CRON_EVENT, self::get_cron_arguments( $post_id ) );
		if ( is_wp_error( $scheduled ) ) {
			do_action( self::ACTION_NAME_SINGLE_SCHEDULE_FAILURE, $post_id, $scheduled->get_error_message() );
		}
		update_post_meta( $post_id, self::META_KEY_SCHEDULED, $time );
	}

	/**
	 * Clear the scheduled retry even for the given post and delete
	 * related meta values.
	 *
	 * @param int $post_id Post ID
	 */
	public static function clear_existing_retry( $post_id ) {
		if ( $post_id ) {
			wp_clear_scheduled_hook( self::CRON_EVENT, self::get_cron_arguments( $post_id ) );
			delete_post_meta( $post_id, self::META_KEY_ATTEMPTS );
			delete_post_meta( $post_id, self::META_KEY_SCHEDULED );
		}
	}

	/**
	 * Returns the arguments to pass along in the scheduled retry events
	 *
	 * @param int $post_id Post ID
	 * @return array Arguments for scheduled event
	 */
	public static function get_cron_arguments( $post_id ) {
		$cron_args = [ $post_id ];

		return $cron_args;
	}

	/**
	 * Bulk retry all posts modified within the last 40 minutes.
	 */
	public static function bulk_retry_publish() {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'before'   => '5 minutes ago',
					'after'    => '45 minutes ago',
					'inlusive' => true,
					'column'   => 'post_modified',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$should_schedule = apply_filters( self::FILTER_NAME_SHOULD_SCHEDULE, true, $post_id );

				if ( $should_schedule ) {
					self::retry_publish( $post_id );
				}
			}
		}
	}
}




