<?php
/**
 * Custom post types notifications.
 *
 * @package     SlackNotifications\Notifications
 * @subpackage  CPT
 * @author      Dor Zuberi <webmaster@dorzki.co.il>
 * @link        https://www.dorzki.co.il
 * @since       2.0.0
 * @version     2.0.5
 */

namespace SlackNotifications\Notifications;

// Block direct access to the file via url.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class CPT
 *
 * @package SlackNotifications\Notifications
 */
class CPT extends Notification_Type {

	/**
	 * @var array
	 */
	private $ignore_cpts = [];


	/**
	 * CPT constructor.
	 */
	public function __construct() {

		$post_types = get_post_types( [
			'public'   => true,
			'_builtin' => false,
		] );

		// Ignore certain CPTs.
		$this->ignore_cpts = apply_filters( 'slack_ignore_cpts', [
			'post',
			'page',
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'product_variation',
			'shop_order',
			'shop_order_refund',
			'shop_coupon',
			'shop_webhook',
		] );

		$post_types = array_diff( $post_types, $this->ignore_cpts );

		// Register CPTs notifications.
		foreach ( $post_types as $post_type ) {

			$cpt = get_post_type_object( $post_type );

			if ( is_null( $cpt ) ) {
				continue;
			}

			$this->object_type    = 'cpt_' . $post_type;
			$this->object_label   = $cpt->labels->name;
			$this->object_options = [
				'new_cpt'     => [
					'label'    => sprintf( esc_html__( '%s Published', 'dorzki-notifications-to-slack' ), $cpt->labels->singular_name ),
					'hooks'    => [
						'auto-draft_to_publish' => 'cpt_published',
						'draft_to_publish'      => 'cpt_published',
						'future_to_publish'     => 'cpt_published',
						'pending_to_publish'    => 'cpt_published',
					],
					'priority' => 10,
					'params'   => 1,
				],
				'future_cpt'  => [
					'label'    => sprintf( esc_html__( '%s Scheduled', 'dorzki-notifications-to-slack' ), $cpt->labels->singular_name ),
					'hooks'    => [
						'auto-draft_to_future' => 'cpt_scheduled',
						'draft_to_future'      => 'cpt_scheduled',
					],
					'priority' => 10,
					'params'   => 1,
				],
				'pending_cpt' => [
					'label'    => sprintf( esc_html__( '%s Pending', 'dorzki-notifications-to-slack' ), $cpt->labels->singular_name ),
					'hooks'    => [
						'auto-draft_to_pending' => 'cpt_pending',
						'draft_to_pending'      => 'cpt_pending',
					],
					'priority' => 10,
					'params'   => 1,
				],
				'update_cpt'  => [
					'label'    => sprintf( esc_html__( '%s Updated', 'dorzki-notifications-to-slack' ), $cpt->labels->singular_name ),
					'hooks'    => [
						'publish_to_publish' => 'cpt_updated',
					],
					'priority' => 10,
					'params'   => 1,
				],
				'trash_cpt'   => [
					'label'    => sprintf( esc_html__( '%s Moved to Trash', 'dorzki-notifications-to-slack' ), $cpt->labels->singular_name ),
					'hooks'    => [
						'trashed_post' => 'cpt_trashed',
					],
					'priority' => 10,
					'params'   => 1,
				],
			];

			parent::__construct();

		}

	}


	/**
	 * Post notification when a new cpt has been posted.
	 *
	 * @param $cpt
	 *
	 * @return bool
	 */
	public function cpt_published( $cpt ) {

		if ( empty( $cpt ) || ! is_object( $cpt ) ) {
			return false;
		}

		if ( in_array( $cpt->post_type, $this->ignore_cpts ) ) {
			return false;
		}

		$cpt_obj = get_post_type_object( $cpt->post_type );

		// Build notification
		$message = __( ':memo: The %s *<%s|%s>* was published right now!', 'dorzki-notifications-to-slack' );
		$message = sprintf( $message, $cpt_obj->labels->singular_name, get_permalink( $cpt->ID ), $cpt->post_title );

		$attachments = [
			[
				'title' => sprintf( esc_html__( '%s Author', 'dorzki-notifications-to-slack' ), $cpt_obj->labels->singular_name ),
				'value' => get_the_author_meta( 'display_name', $cpt->post_author ),
				'short' => true,
			],
			[
				'title' => esc_html__( 'Published Date', 'dorzki-notifications-to-slack' ),
				'value' => get_the_date( null, $cpt->ID ),
				'short' => true,
			],
		];

		$channel = $this->get_notification_channel( __FUNCTION__ );

		return $this->slack_bot->send_message( $message, $attachments, [
			'color'   => '#9b59b6',
			'channel' => $channel,
		] );

	}


	/**
	 * Post notification when a cpt is scheduled to be published.
	 *
	 * @param $cpt
	 *
	 * @return bool
	 */
	public function cpt_scheduled( $cpt ) {

		if ( empty( $cpt ) || ! is_object( $cpt ) ) {
			return false;
		}

		if ( in_array( $cpt->post_type, $this->ignore_cpts ) ) {
			return false;
		}

		$cpt_obj = get_post_type_object( $cpt->post_type );

		// Build notification
		$message = __( ':clock3: The %s *<%s|%s>* was scheduled to be published on *%s*.', 'dorzki-notifications-to-slack' );
		$message = sprintf( $message, $cpt_obj->labels->singular_name, get_permalink( $cpt->ID ), $cpt->post_title, get_the_date( null, $cpt->ID ) );

		$attachments = [
			[
				'title' => sprintf( esc_html__( '%s Author', 'dorzki-notifications-to-slack' ), $cpt_obj->labels->singular_name ),
				'value' => get_the_author_meta( 'display_name', $cpt->post_author ),
				'short' => true,
			],
			[
				'title' => esc_html__( 'Scheduled Date', 'dorzki-notifications-to-slack' ),
				'value' => get_the_date( null, $cpt->ID ),
				'short' => true,
			],
			[
				'title' => esc_html__( 'Scheduled Time', 'dorzki-notifications-to-slack' ),
				'value' => get_the_time( null, $cpt->ID ),
				'short' => true,
			],
		];

		$channel = $this->get_notification_channel( __FUNCTION__ );

		return $this->slack_bot->send_message( $message, $attachments, [
			'color'   => '#8e44ad',
			'channel' => $channel,
		] );

	}


	/**
	 * Post notification when a cpt is pending approval.
	 *
	 * @param $cpt
	 *
	 * @return bool
	 */
	public function cpt_pending( $cpt ) {

		if ( empty( $cpt ) || ! is_object( $cpt ) ) {
			return false;
		}

		if ( in_array( $cpt->post_type, $this->ignore_cpts ) ) {
			return false;
		}

		$cpt_obj = get_post_type_object( $cpt->post_type );

		// Build notification
		$message = __( ':eye: The %s *<%s|%s>* is pending approval.', 'dorzki-notifications-to-slack' );
		$message = sprintf( $message, $cpt_obj->labels->singular_name, get_permalink( $cpt->ID ), $cpt->post_title );

		$attachments = [
			[
				'title' => sprintf( esc_html__( '%s Author', 'dorzki-notifications-to-slack' ), $cpt_obj->labels->singular_name ),
				'value' => get_the_author_meta( 'display_name', $cpt->post_author ),
				'short' => true,
			],
			[
				'title' => esc_html__( 'Pending Date', 'dorzki-notifications-to-slack' ),
				'value' => get_the_date( null, $cpt->ID ),
				'short' => true,
			],
		];

		$channel = $this->get_notification_channel( __FUNCTION__ );

		return $this->slack_bot->send_message( $message, $attachments, [
			'color'   => '#8e44ad',
			'channel' => $channel,
		] );

	}


	/**
	 * Post notification when a cpt was updated.
	 *
	 * @param $cpt
	 *
	 * @return bool
	 */
	public function cpt_updated( $cpt ) {

		if ( empty( $cpt ) || ! is_object( $cpt ) ) {
			return false;
		}

		if ( in_array( $cpt->post_type, $this->ignore_cpts ) ) {
			return false;
		}

		$cpt_obj = get_post_type_object( $cpt->post_type );

		$user_id = ( isset( $_POST[ 'user_ID' ] ) ) ? intval( $_POST[ 'user_ID' ] ) : $cpt->post_author;
		$user    = get_user_by( 'id', $user_id );

		// Build notification
		$message = __( ':pencil2: The %s *<%s|%s>* has been updated right now.', 'dorzki-notifications-to-slack' );
		$message = sprintf( $message, $cpt_obj->labels->singular_name, get_permalink( $cpt->ID ), $cpt->post_title );

		$attachments = [
			[
				'title' => esc_html__( 'Updated By', 'dorzki-notifications-to-slack' ),
				'value' => $user->display_name,
				'short' => true,
			],
			[
				'title' => esc_html__( 'Update Date', 'dorzki-notifications-to-slack' ),
				'value' => get_the_modified_date( null, $cpt->ID ),
				'short' => true,
			],
		];

		$channel = $this->get_notification_channel( __FUNCTION__ );

		return $this->slack_bot->send_message( $message, $attachments, [
			'color'   => '#8e44ad',
			'channel' => $channel,
		] );

	}


	/**
	 * Post notification when a cpt was trashed.
	 *
	 * @param $cpt_id
	 *
	 * @return bool
	 */
	public function cpt_trashed( $cpt_id ) {

		// Get cpt
		$cpt = get_post( $cpt_id );

		if ( is_wp_error( $cpt ) ) {
			return false;
		}

		if ( in_array( $cpt->post_type, $this->ignore_cpts ) ) {
			return false;
		}

		$cpt_obj = get_post_type_object( $cpt->post_type );

		// Build notification
		$message = __( ':wastebasket: The %s *<%s|%s>* was moved to trash.', 'dorzki-notifications-to-slack' );
		$message = sprintf( $message, $cpt_obj->labels->singular_name, get_permalink( $cpt->ID ), $cpt->post_title );

		$attachments = [
			[
				'title' => sprintf( esc_html__( '%s Author', 'dorzki-notifications-to-slack' ), $cpt_obj->labels->singular_name ),
				'value' => get_the_author_meta( 'display_name', $cpt->post_author ),
				'short' => true,
			],
			[
				'title' => esc_html__( 'Trashed Date', 'dorzki-notifications-to-slack' ),
				'value' => get_the_modified_date( null, $cpt->ID ),
				'short' => true,
			],
		];

		$channel = $this->get_notification_channel( __FUNCTION__ );

		return $this->slack_bot->send_message( $message, $attachments, [
			'color'   => '#e74c3c',
			'channel' => $channel,
		] );

	}

}