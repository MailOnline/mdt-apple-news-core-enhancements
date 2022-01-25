<?php

namespace MDT\Apple_News_Core_Enhancements;

/**
 * Class Notices
 *
 * @package MDT\Apple_News_Core_Enhancements
 */
class Notices {
	/**
	 * Notices constructor.
	 */
	public static function init() {
		add_action('init', [__CLASS__, 'add_actions']);
	}

	/**
	 * Adding actions
	 */
	public function add_actions(){
		$slack_endpoint = apply_filters( 'mdt_apple_news_ce_slack_endpoint', '' );
		$hide_notices   = apply_filters( 'mdt_apple_news_ce_hide_notices', true );

		if ( ! $hide_notices ) {
			return;
		}

		if ( $slack_endpoint ) {
			add_action( 'mdt_an_auto_retry_push_success', [ __CLASS__, 'auto_retry_success' ], 10, 3 );
			add_action( 'mdt_an_auto_retry_push_failure', [ __CLASS__, 'auto_retry_failure' ], 10, 3 );
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( ! class_exists( 'Admin_Apple_Notice' ) ) {
			return;
		}

		remove_action( 'admin_notices', 'Admin_Apple_Notice::show' );
		add_action( 'admin_notices', [ __CLASS__, 'check_for_messages' ] );
	}

	/**
	 * Checks to see if there are any Apple_News notices to show and if so processes them.
	 *
	 * Near enough clone the relevant Apple_News method, except we automatically set the dismissed
	 * status to true after the message is passed off to slack.
	 *
	 * @see \Admin_Apple_Notice::show
	 */
	public function check_for_messages() {

		$user_id = get_current_user_id();

		// Check for notices.
		$notices = self::get_user_meta( $user_id );
		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		// Keep track of an updated list of notices to save to the DB, if necessary.
		$updated_notices = [];

		// Show the notices.
		foreach ( $notices as $notice ) {

			// If the notice doesn't have a message (for some reason), skip it.
			if ( empty( $notice['message'] ) ) {
				continue;
			}

			// If a type isn't specified, default to 'updated'.
			$type = isset( $notice['type'] ) ? $notice['type'] : 'updated';

			// Only display notices that aren't dismissed.
			if ( empty( $notice['dismissed'] ) ) {

				// Expose notice information via a hook for others to use
				do_action( 'mdt_apple_news_ce_new_notice', $notice['message'], $type, $user_id );

				// If a slack endpoint exists, then send the notice to slack.
				$slack_endpoint = apply_filters( 'mdt_apple_news_ce_slack_endpoint', '' );
				if ( $slack_endpoint ) {
					$payload = self::generate_notice_payload( $notice['message'], $type, $user_id );
					self::send_payload_to_slack( $slack_endpoint, $payload );
				}
			}

			// If the notice is dismissable, dismiss it ourselves and ensure it persists in the DB.
			if ( ! empty( $notice['dismissable'] ) ) {
				$notice['dismissed'] = true;
				$updated_notices[]   = $notice;
			}
		}

		// Delete the notices in the DB if they have changed.
		if ( $notices !== $updated_notices ) {
			self::update_user_meta( $user_id, $updated_notices );
		}
	}

	/**
	 * Sends a slack message for successful auto retries
	 *
	 * @param int    $post_id  Post ID
	 * @param string $share_url URL for Apple News article
	 * @param int    $attempt Number of attempts
	 */
	public function auto_retry_success( $post_id, $share_url, $attempt ) {
		$slack_endpoint = apply_filters( 'mdt_apple_news_ce_slack_endpoint', '' );
		if ( $slack_endpoint ) {
			$payload = self::generate_retry_payload( 'success', $post_id, $share_url, $attempt );
			self::send_payload_to_slack( $slack_endpoint, $payload );
		}
	}

	/**
	 * Sends a slack message for failing auto retries
	 *
	 * @param int    $post_id Post ID
	 * @param string $error Error message
	 * @param int    $attempt Number of attempts
	 */
	public function auto_retry_failure( $post_id, $error, $attempt ) {
		$slack_endpoint = apply_filters( 'mdt_apple_news_ce_slack_endpoint', '' );
		if ( $slack_endpoint ) {
			$payload = self::generate_retry_payload( 'error', $post_id, $error, $attempt );
			self::send_payload_to_slack( $slack_endpoint, $payload );
		}
	}

	/**
	 * Generates a slack payload for an Apple News notice to send to slack for monitoring.
	 *
	 * @param $message
	 * @param $type
	 * @param $user_id
	 *
	 * @return array
	 */
	public function generate_notice_payload( $message, $type, $user_id ) {
		$type  = strtoupper( $type );
		$emoji = ':bell:';
		$user  = get_userdata( $user_id );

		if ( $type === 'SUCCESS' ) {
			$emoji = ':white_check_mark:';}
		if ( $type === 'ERROR' ) {
			$emoji = ':x:';}

		$payload               = [];
		$payload['icon_emoji'] = $emoji;
		$payload['username']   = 'PUBLISHING ' . strtoupper( $type );
		$payload['text']       = sprintf(
			'```%s``` `$user: %s (%d)` `$site_url: %s`',
			$message,
			$user->user_nicename,
			$user_id,
			get_site_url()
		);

		return $payload;
	}

	/**
	 * Generates a slack payload for an auto retry event to send to slack for monitoring.
	 *
	 * @param $type
	 * @param $post_id
	 * @param $share_url
	 * @param $attempt
	 *
	 * @return array
	 */
	public function generate_retry_payload( $type, $post_id, $share_url, $attempt ) {
		$payload               = [];
		$payload['icon_emoji'] = ':mag:';
		$payload['username']   = 'AUTO RETRY ' . strtoupper( $type );
		$payload['text']       = sprintf(
			'```%s``` `Attempt #%d` `$post_id: %d` `$site_url: %s`',
			$share_url,
			(int) $attempt,
			(int) $post_id,
			get_site_url()
		);

		return $payload;
	}

	/**
	 * POSTs a given $payload to the given $slack_endpoint
	 *
	 * @param string $slack_endpoint Slack endpoint to post payload to
	 * @param array  $payload Payload data for slack
	 */
	public function send_payload_to_slack( $slack_endpoint, $payload ) {
		$payload = apply_filters( 'mdt_apple_news_ce_slack_payload', $payload );
		wp_safe_remote_post(
			$slack_endpoint,
			[
				'body'     => wp_json_encode( $payload ),
				'blocking' => false,
			]
		);
	}

	/**
	 * Fetches Apple_News messages from user meta.
	 *
	 * Clone of the matching Apple_News method due to it being private.
	 *
	 * @see \Admin_Apple_Notice::get_user_meta
	 *
	 * @param $user_id
	 * @return array
	 */
	public function get_user_meta( $user_id ) {
		// Negotiate meta value.
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ) {
			$meta_value = get_user_attribute( $user_id, \Admin_Apple_Notice::KEY );
		} else {
			$meta_value = get_user_meta( $user_id, \Admin_Apple_Notice::KEY, true ); // phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_get_user_meta
		}

		return ( ! empty( $meta_value ) ) ? $meta_value : array();
	}

	/**
	 * Updates Apple_News messages stored in user meta.
	 *
	 * Clone of the matching Apple_News method due to it being private.
	 *
	 * @see \Admin_Apple_Notice::update_user_meta
	 *
	 * @param $user_id
	 * @param $values
	 * @return bool
	 */
	public function update_user_meta( $user_id, $values ) {

		// Delete existing user meta.
		self::delete_user_meta( $user_id );

		// Only add meta back if new values were provided.
		if ( ! empty( $values ) ) {
			// Save using the appropriate method.
			if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ) {
				return update_user_attribute( $user_id, \Admin_Apple_Notice::KEY, $values );
			} else {
				return update_user_meta( $user_id, \Admin_Apple_Notice::KEY, $values ); // phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_update_user_meta
			}
		}

		return true;

	}

	/**
	 * Deletes Apple_News messages from user meta.
	 *
	 * Clone of the matching Apple_News method due to it being private.
	 *
	 * @see \Admin_Apple_Notice::delete_user_meta
	 *
	 * @param $user_id
	 * @return mixed
	 */
	public function delete_user_meta( $user_id ) {
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ) {
			return delete_user_attribute( $user_id, \Admin_Apple_Notice::KEY );
		} else {
			return delete_user_meta( $user_id, \Admin_Apple_Notice::KEY ); // phpcs:ignore WordPress.VIP.RestrictedFunctions.user_meta_delete_user_meta
		}
	}

}
