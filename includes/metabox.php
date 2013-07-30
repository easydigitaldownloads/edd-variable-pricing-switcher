<?php
/**
 * Add Variable Pricing Metabox Meta Box
 *
 * @since 1.0
 */
function edd_vps_add_meta_box() {
	global $post;

	if( 'bundle' != edd_get_download_type( get_the_ID() ) && edd_has_variable_prices( get_the_ID() ) ) {
		add_meta_box( 'edd_vps_box', __( 'Variable Pricing Switcher', 'edd-vps' ), 'edd_vps_render_meta_box', 'download', 'side', 'core' );
	}
}
add_action( 'add_meta_boxes', 'edd_vps_add_meta_box', 100 );


/**
 * Render the download information meta box
 *
 * @since 1.0
 */
function edd_vps_render_meta_box()	{
	global $post;

	// Use nonce for verification
	echo '<input type="hidden" name="edd_vps_meta_box_nonce" value="', wp_create_nonce( basename( __FILE__ ) ), '" />';

	echo '<table class="form-table">';

	$enabled = get_post_meta( $post->ID, '_edd_vps_enabled', true ) ? true : false;

	echo '<tr>';
	echo '<td class="edd_field_type_text" colspan="2">';
	echo '<input type="checkbox" name="edd_vps_enabled" id="edd_vps_enabled" value="1" ' . checked( true, $enabled, false ) . '/>&nbsp;';
	echo '<label for="edd_vps_enabled">' . __( 'Check to enable pricing switcher', 'edd-vps' ) . '</label>';
	echo '<td>';
	echo '</tr>';

	echo '</table>';
}


/**
 * Save data from meta box
 *
 * @since 1.0
 */
function edd_vps_download_meta_box_save( $post_id ) {

	global $post;

	// verify nonce
	if ( isset( $_POST['edd_vps_meta_box_nonce'] ) && ! wp_verify_nonce( $_POST['edd_vps_meta_box_nonce'], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	// check autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}

	if ( isset( $_POST['post_type'] ) && 'download' != $_POST['post_type'] ) {
		return $post_id;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	if ( isset( $_POST['edd_vps_enabled'] ) ) {
		update_post_meta( $post_id, '_edd_vps_enabled', true );
	} else {
		delete_post_meta( $post_id, '_edd_vps_enabled' );
	}

}
add_action( 'save_post', 'edd_vps_download_meta_box_save' );