<?php
/**
 * BP Attachments Screens.
 *
 * User's screens
 *
 * @package BP Attachments
 * @subpackage Screens
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Attachments User Screens Class.
 *
 * @since BP Attachments (1.0.0)
 */
class BP_Attachments_User_Screens {

	public $title = 'user_title';
	public $content = 'user_content';

	/**
	 * Construct the screen.
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public function __construct( $title = '', $content = '' ) {
		bp_core_load_template( apply_filters( 'bp_attachments_user_screens', 'members/single/plugins' ) );

		if ( ! empty( $title ) && ! empty( $content) ) {
			$this->title = $title;
			$this->content = $content;
		}

		$this->query_string();
		$this->setup_actions();
	}

	/**
	 * Deal with actions.
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public function query_string() {
		if ( ! empty( $_GET['action'] ) && 'delete' == $_GET['action'] && ! empty( $_GET['attachment'] ) ) {
			check_admin_referer( 'bp_attachments_delete' );

			$redirect = remove_query_arg( array( 'attachment', 'action' ), wp_get_referer() );

			$deleted = bp_attachments_delete_attachment( $_GET['attachment'] );

			if ( ! empty( $deleted ) ) {
				bp_core_add_message( sprintf( __( 'Attachment: %s successfully deleted.', 'bp-attachments' ), $deleted ) );
			} else {
				bp_core_add_message( __( 'Attachment could not be deleted.', 'bp-attachments' ), 'error' );
			}

			bp_core_redirect( $redirect );
		}

		if ( ! empty( $_GET['action'] ) && 'edit' == $_GET['action'] && ! empty( $_GET['attachment'] ) ) {
			$this->title = 'edit_title';
			$this->content = 'edit_content';

			$redirect = remove_query_arg( array( 'attachment', 'action' ), wp_get_referer() );
			$attachment_id = absint( $_GET['attachment'] );

			$attachment = bp_attachments_plugin_get_attachment( $attachment_id );

			if ( empty( $attachment ) || ! bp_attachments_loggedin_user_can( 'edit_bp_attachment', $attachment_id ) ) {
				bp_core_add_message( __( 'Attachment could not be found.', 'bp-attachments' ), 'error' );
				bp_core_redirect( $redirect );
			}

			// Set up the attachment global
			buddypress()->attachments->attachment = $attachment;
		}

		if ( ! empty( $_POST['_bp_attachments_edit']['update'] ) ) {

			check_admin_referer( 'bp_attachment_update' );
			$redirect = wp_get_referer();

			$attachment_id = absint( $_POST['_bp_attachments_edit']['id'] );

			if ( empty( $attachment_id ) || ! bp_attachments_loggedin_user_can( 'edit_bp_attachment', $attachment_id ) ) {
				bp_core_add_message( __( 'Attachment could not be edited.', 'bp-attachments' ), 'error' );
				bp_core_redirect( $redirect );
			}

			$updated = bp_attachments_update_attachment( $_POST['_bp_attachments_edit'] );

			if ( ! empty( $updated ) ) {
				$redirect = trailingslashit( bp_core_get_user_domain( bp_displayed_user_id() ) . buddypress()->attachments->slug );
				bp_core_add_message( sprintf( __( 'Attachment: %s successfully updated', 'bp-attachments' ), $updated ) );
			} else {
				bp_core_add_message( __( 'Attachment could not be edited.', 'bp-attachments' ), 'error' );
			}

			bp_core_redirect( $redirect );
		}
	}

	/**
	 * Register the screen class.
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public static function legacy_screens() {

		$bp = buddypress();

		if( empty( $bp->attachments->user_screens ) ) {
			$bp->attachments->user_screens = new self;
		}

		return $bp->attachments->user_screens;
	}

	/**
	 * Register the screen class.
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public static function new_screens() {
		$bp = buddypress();

		if ( empty( $bp->attachments->user_screens ) ) {
			$bp->attachments->user_screens = new self( 'new_user_title', 'new_user_content' );
		}

		return $bp->attachments->user_screens;
	}

	/**
	 * Customize the members/single/plugins template
	 *
	 * @since BP Attachments (1.0.0)
	 */
	private function setup_actions() {
		add_action( 'bp_template_title', array( $this, $this->title ) );
		add_action( 'bp_template_content', array( $this, $this->content ) );
	}

	/**
	 * Displays the button to launch the BP Media Editor
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public function user_title() {
		bp_attachments_browser( 'bp-attachments-upload', array(
			'item_type'       => 'attachment',
			'btn_caption'     => __( 'Manage attachments', 'bp-attachments' ),
			'multi_selection' => true,
			'btn_class'       => 'attachments-editor',
			'callback'        => trailingslashit( bp_core_get_user_domain( bp_loggedin_user_id() ) . buddypress()->attachments->slug )
		) );
	}

	/**
	 * Displays the component loop
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public function user_content() {
		do_action( 'bp_attachments_uploader_fallback' );

		bp_attachments_template_loop( buddypress()->attachments->current_component );
	}

	/**
	 * Displays the button to launch the BP Media Editor
	 *
	 * @since BP Attachments (1.1.0)
	 */
	public function new_user_title() {
		esc_html_e( 'Testing the BuddyPress Attachments API', 'bp-attachments' );
	}

	/**
	 * Displays the component loop
	 *
	 * @since BP Attachments (1.1.0)
	 */
	public function new_user_content() {
		// Enqueue BuddyPress attachments scripts
		bp_attachments_enqueue_scripts( 'BP_Attachments_Attachment' );

		bp_attachments_get_template_part( 'files/index' );
	}

	/**
	 * Displays the name of the file being edited
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public function edit_title() {
		echo esc_html( sprintf( __( 'Editing: %s', 'bp-attachments' ), buddypress()->attachments->attachment->title ) );
	}

	/**
	 * Displays the form to edit the file
	 *
	 * @since BP Attachments (1.0.0)
	 */
	public function edit_content() {
		bp_attachments_template_single();
	}

}
