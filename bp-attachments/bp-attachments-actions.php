<?php
/**
 * BP Attachments Actions.
 *
 * Adds specific templates to media templates
 * Handles Attachments upload if Javascrip is disabled
 *
 * @package BP Attachments
 * @subpackage Actions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Add the specific templates needed by BP Media Editor
 *
 * @since BP Attachments (1.0.0)
 */
function bp_attachment_load_backbone_tmpl() {
	?>
	<script type="text/html" id="tmpl-bp-attachment-details">
		<h3>
			<?php _e('Attachment Details'); ?>

			<span class="settings-save-status">
				<span class="spinner"></span>
				<span class="saved"><?php esc_html_e('Saved.'); ?></span>
			</span>
		</h3>
		<div class="attachment-info">
			<div class="thumbnail">
				<# if ( data.uploading ) { #>
					<div class="media-progress-bar"><div></div></div>
				<# } else if ( 'image' === data.type ) { #>
					<img src="{{ data.size.url }}" draggable="false" />
				<# } else { #>
					<img src="{{ data.icon }}" class="icon" draggable="false" />
				<# } #>
			</div>
			<div class="details">
				<div class="filename">{{ data.filename }}</div>
				<div class="uploaded">{{ data.dateFormatted }}</div>

				<# if ( 'image' === data.type && ! data.uploading ) { #>
					<# if ( data.width && data.height ) { #>
						<div class="dimensions">{{ data.width }} &times; {{ data.height }}</div>
					<# } #>

					<# if ( data.can.save ) { #>
						<a class="edit-bp-attachment" href="{{ data.editLink }}"><?php _e( 'Edit Options', 'bp-attachments' ); ?></a>
					<# } #>
				<# } #>

				<# if ( data.fileLength ) { #>
					<div class="file-length"><?php _e( 'Length:' ); ?> {{ data.fileLength }}</div>
				<# } #>

				<# if ( ! data.uploading && data.can.remove ) { #>
					<?php if ( MEDIA_TRASH ): ?>
						<a class="trash-attachment" href="#"><?php _e( 'Trash' ); ?></a>
					<?php else: ?>
						<a class="delete-attachment" href="#"><?php _e( 'Delete Permanently' ); ?></a>
					<?php endif; ?>
				<# } #>

				<div class="compat-meta">
					<# if ( data.compat && data.compat.meta ) { #>
						{{{ data.compat.meta }}}
					<# } #>
				</div>
			</div>
		</div>

		<# var maybeReadOnly = data.can.save || data.allowLocalEdits ? '' : 'readonly'; #>
			<label class="setting" data-setting="title">
				<span><?php _e('Title'); ?></span>
				<input type="text" value="{{ data.title }}" {{ maybeReadOnly }} />
			</label>
			<label class="setting" data-setting="description">
				<span><?php _e('Description'); ?></span>
				<textarea {{ maybeReadOnly }}>{{ data.description }}</textarea>
			</label>
	</script>

	<script type="text/html" id="tmpl-bp-preview">
		<# if ( data.id ) { #>
			<div class="centered">
				<img src="{{ data.img }}" style="max-width:100%"/>
			</div>
		<# } #>
	</script>
	<?php
}

add_action( 'print_media_templates', 'bp_attachment_load_backbone_tmpl' );


/**
 * Handle uploads if no-js
 *
 * @since BP Attachments (1.0.0)
 */
function bp_attachments_catch_upload() {

	if ( ! empty( $_POST['bp_attachment_upload'] ) ) {

		check_admin_referer( 'bp_attachments_upload', 'bp_attachments_upload_nonce' );

		$redirect = $_POST['_wp_http_referer'];

		$file_name = 'bp-attachments-attachment-upload';

		if ( ! empty( $_POST['file_data'] ) ) {
			$file_name = $_POST['file_data'];
		}

		if ( ! empty( $_FILES[ $file_name ] ) ) {

			$args = array();

			if ( ! empty( $_POST['item_id'] ) ) {
				$args['item_id'] = absint( $_POST['item_id'] );
			}

			if ( ! empty( $_POST['item_type'] ) ) {
				$args['item_type'] = $_POST['item_type'];
			} else {
				$args['item_type'] = 'attachment';
			}

			if ( ! empty( $_POST['component'] ) ) {
				$args['component'] = $_POST['component'];
			}

			if ( ! empty( $_POST['action'] ) ) {
				$args['action'] = $_POST['action'];
			}

			if ( ! empty( $_POST['action'] ) && 'bp_attachments_upload' === $_POST['action'] ) {
				$_POST['action'] = 'bp_attachments_attachment_upload';
			}

			$cap_args = false;

			if ( ! empty( $args['component'] ) ) {
				$cap_args = array( 'component' => $args['component'], 'item_id' => $args['item_id'] );
			}

			// capability check
			if ( ! bp_attachments_loggedin_user_can( 'publish_bp_attachments', $cap_args ) ) {
				bp_core_add_message( __( 'Error: you are not allowed to create this attachment.', 'bp-attachments' ), 'error' );
				bp_core_redirect( $redirect );
			}

			$user_id = bp_displayed_user_id();
			if ( ! bp_is_user() ) {
				$user_id = bp_loggedin_user_id();
			}

			$attachment_object = new BP_Attachments_Attachment();
			$response = $attachment_object->insert_attachment( $_FILES, array(
				'post_author'  => $user_id,
				'bp_component' => $args['component'],
				'bp_item_id'   => $args['item_id'],
			) );

			if ( is_wp_error( $response ) ) {
				bp_core_add_message( sprintf( __( 'Error: %s', 'bp-attachments' ), $response->get_error_message() ), 'error' );
			} else {
				bp_core_add_message( sprintf( __( '%s successfully uploaded', 'bp-attachments' ), ucfirst( $args['item_type'] ) ) );
			}

			bp_core_redirect( $redirect );
		}
	}
}
add_action( 'bp_actions', 'bp_attachments_catch_upload' );

/** Activity Actions **********************************************************/

/**
 * Link the Attachments to the Activity and the group
 * using metadatas
 *
 * @since 1.1.0
 */
function bp_attachments_activity_save( $activity = null ) {
	if ( empty( $_POST['bp_attachments_activity_meta'] ) ) {
		return;
	}

	$attachments = (array) $_POST['bp_attachments_activity_meta'];

	// Make sure to link the activity id to the Attachment
	// and vice/versa
	foreach ( $attachments as $attachment_id ) {
		add_post_meta( $attachment_id, '_bp_activity_id', $activity->id );

		// Was it posted within a group from the activity directory ?
		if ( 'groups' === $activity->component && ! empty( $activity->item_id ) && true === (bool) groups_get_groupmeta( $activity->item_id, 'group-use-attachments' ) ) {
			add_post_meta( $attachment_id, '_bp_groups_id', $activity->item_id );

			// Set the term!
			$term = get_term_by( 'slug', $activity->component, 'bp_component' );

			if ( ! empty( $term ) ) {
				wp_set_object_terms( $attachment_id, array( $term->term_id ), 'bp_component' );
			}
		}

		bp_activity_add_meta( $activity->id, '_bp_attachments_attachment_ids', (int) $attachment_id );
	}
}
add_action( 'bp_activity_after_save', 'bp_attachments_activity_save', 10, 1 );

/**
 * Remove an attachment from an activity when it was deleted
 *
 * @since 1.1.0
 */
function bp_attachments_activity_unattach( $attachment_id = 0 ) {
	if ( empty( $attachment_id ) ) {
		return;
	}

	$activity_id = get_post_meta( $attachment_id, '_bp_activity_id', true );

	if ( empty( $activity_id ) ) {
		return;
	}

	bp_activity_delete_meta( $activity_id, '_bp_attachments_attachment_ids', $attachment_id );
}
add_action( 'bp_attachments_before_attachment_delete', 'bp_attachments_activity_unattach', 10, 1 );
