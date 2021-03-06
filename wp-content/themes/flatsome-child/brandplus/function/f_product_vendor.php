<?php
class product_add
{

  if ( ! function_exists( 'wf_insert_update_user_meta' ) ) {
  	/**
  	 * Creates a meta key and inserts the meta value.
  	 * If the passed meta key already exists then updates the meta value.
  	 *
  	 * @param {int} $user_id User Id.
  	 * @param {string} $meta_key Meta Key.
  	 * @param {string} $meta_value Meta value.
  	 *
  	 * @return bool
  	 */
  	public function wf_insert_update_user_meta( $user_id, $meta_key, $meta_value ) {

  		// Add data in the user meta field.
  		$meta_key_not_exists = add_user_meta( $user_id, $meta_key, $meta_value, true );

  		// If meta key already exists then just update the meta value for and return true
  		if ( ! $meta_key_not_exists ) {
  			update_user_meta( $user_id, $meta_key, $meta_value );
  			return true;
  		}
  	}
  }

  if ( ! function_exists( 'wf_create_update_user_post' ) ) {
  	/**
  	 * Creates a new post for the user under post type 'post'.
  	 * And if there is a post that already exists for the user then just updates it.
  	 *
  	 * @param {int} $user_id User Id.
  	 * @param {string} $user_display_name User name.
  	 * @param {string} $post_status post status
  	 *
  	 * @return {int|mixed|WP_Error} $post_id Post Id.
  	 */
  	public function wf_create_update_user_post( $user_id, $user_display_name, $post_status ) {
  		$post_id = get_user_meta( $user_id, 'user_custom_post', true );
  		if ( $post_id ) {
  			// The custom post already exists , Just update it .
  			$my_post = array(
  				'ID'           => $post_id,
  				'post_title'   => sanitize_text_field( $user_display_name ),
  				'post_status'   => $post_status,
  				'post_content'   => '',
  				'post_name' => sanitize_text_field( $user_display_name )
  			);
  			wp_update_post( $my_post, false );
  		} else {
  			// Custom post does not exist for this user
  			$my_post = array(
  				'post_author' => $user_id,
  				'post_title'   => sanitize_text_field( $user_display_name ),
  				'post_status'   => 'pending',
  				'post_content'   => 'test',
  				'post_name' => sanitize_text_field( $user_display_name ),
  				'post_type' => 'product'
  			);
  			$post_id = wp_insert_post( $my_post ); // It will return the new inserted $post_id
  			$meta_existed = wf_insert_update_user_meta( $user_id, 'user_custom_post', $post_id );
  		}
  		return $post_id;
  	}
  }

  if ( ! function_exists( 'wf_move_attach_to_upload_dir' ) ) {
  	/**
  	 * Uses wordpress function wp_handle_upload() to move the uploaded file into
  	 * wordpress upload's directory.
  	 *
  	 * @param {string} $file_input_name File name.
  	 *
  	 * @return {array} $movefile Array containing the path, url and other uploaded file info.
  	 */
  	public function wf_move_attach_to_upload_dir( $file_input_name ) {
  		if ( ! function_exists( 'wp_handle_upload' ) ) {
  			require_once( ABSPATH . 'wp-admin/includes/file.php' );
  		}

  		$uploadedfile = $_FILES[ $file_input_name ];

  		$upload_overrides = array( 'test_form' => false );

  		$movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

  		if ( $movefile && ! isset( $movefile['error'] ) ) {
  //			return "File is valid, and was successfully uploaded.\n";
  			return $movefile;
  		} else {
  			/**
  			 * Error generated by _wp_handle_upload()
  			 * @see _wp_handle_upload() in wp-admin/includes/file.php
  			 */
  			return $movefile['error'];
  		}
  	}
  }

  if ( ! function_exists( 'wf_save_profile_media' ) ) {
  	/**
  	 * @param {string} $file_input_name File name.
  	 *
  	 * @return {array|bool} $inserted_file_obj or false File object containing file info.
  	 */
  	public function wf_save_profile_media( $file_input_name ) {
  		$errors= array();
  		$file_size = ( ! empty( $_FILES[ $file_input_name ]['size'] ) ) ? $_FILES[ $file_input_name ]['size'] : '';
  		$file_type = ( ! empty( $_FILES[ $file_input_name ]['type'] ) ) ? $_FILES[ $file_input_name ]['type'] : '';
  		$file_ext_arr = explode( '/', $file_type );
  		$file_ext = ( ! empty( $file_ext_arr[1] ) ) ? $file_ext_arr[1] : '';

  		$expensions= array( "jpeg", "jpg", "png", "pdf" );

  		// Check if the file has the required format.
  		if( false === in_array( $file_ext, $expensions ) ){
  			$errors[]="Extension not allowed, please choose a JPEG or PNG file.";
  		}

  		// Check if the file has the required size . Below unit is in Bytes ( 2097152 = 2 Mb )
  		if( $file_size > 2097152 ){
  			$errors[]='File size must be exactly 2 MB';
  		}

  		// If there are no errors and the file is of the type and size we permit.
  		if ( empty( $errors ) ) {
  			$inserted_file_obj = wf_move_attach_to_upload_dir( $file_input_name );
  			return $inserted_file_obj;
  		} else {
  			return false;
  		}
  	}
  }

  if ( ! function_exists( 'wf_update_post_with_attach' ) ) {
  	/**
  	 * @param {string} $filename Absolute path of file up until WordPress upload's directory
  	 * @param {int} $post_id Post Id.
  	 * @param {int} $user_id User id.
  	 * @param {string} $pic_type Profile or work file.
  	 */
  	public function wf_update_post_with_attach( $filename, $post_id, $user_id, $pic_type ) {
  		// $filename should be the path to a file in the upload directory.
  		// The ID of the post this attachment is for.
  		$parent_post_id = $post_id;

  		// Check the type of file. We'll use this as the 'post_mime_type'.
  		$filetype = wp_check_filetype( basename( $filename ), null );

  		// Get the path to the upload directory.
  		$wp_upload_dir = wp_upload_dir();

  		// Prepare an array of post data for the attachment.
  		$attachment = array(
  			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
  			'post_mime_type' => $filetype['type'],
  			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
  			'post_content'   => '',
  			'post_status'    => 'inherit'
  		);

  		if ( 'profile-pic' === $pic_type ) {
  			// Insert the attachment.
  			$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
  			wf_insert_update_user_meta( $user_id, 'user_prfl_img_post_id', $attach_id );
  		} else if ( 'work-attach' === $pic_type ) {
  			// Insert the attachment.
  			$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
  			wf_insert_update_user_meta( $user_id, 'user_wrk_img_post_id', $attach_id );
  		}

  		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
  		require_once( ABSPATH . 'wp-admin/includes/image.php' );
  	}
  }

  if ( ! function_exists( 'wf_handle_profile_media' ) ) {

  	/**
  	 * Handles media upload.
  	 * Checks if the media is uploaded, uses custom functions to move the media to WordPress uploads directory,
  	 * deletes previous uploaded media from uploads dir and the attachment post and creates/updates the new one.
  	 *
  	 * @param {int} $post_id Post Id.
  	 * @param {int} $user_id User Id.
  	 */
  	public function wf_handle_profile_media( $post_id, $user_id ) {
  		if( ! empty( $_FILES ) ){

  			// Profile Pic
  			if ( ! empty( $_FILES['user-profile-pic'] ) ) {
  				$inserted_file_obj = wf_save_profile_media( 'user-profile-pic' );
  				$file_path = ( ! empty( $inserted_file_obj ) ) ? $inserted_file_obj['file'] : '';

  				// If any new file is inserted only then add the file path.
  				if ( ! empty( $file_path ) ) {
  					// Get existing attach post id for this user first, delete img from the uploads folder and the existing post attach from wp_posts and then save the new one.
  //					unlink( $file_path);
  					$profile_attach_post_id = ( get_user_meta( $user_id, 'user_prfl_img_post_id', true ) ) ? get_user_meta( $user_id, 'user_prfl_img_post_id', true ) : '';
  					wp_delete_post( $profile_attach_post_id, true );
  					wf_update_post_with_attach( $file_path, $post_id, $user_id, 'profile-pic' );
  				}
  			}

  			// Work File.
  			if ( ! empty( $_FILES['user-past-work-pic'] ) ) {
  				$inserted_file_obj = wf_save_profile_media( 'user-past-work-pic' );
  				$file_path = ( ! empty( $inserted_file_obj ) ) ? $inserted_file_obj['file'] : '';

  				// If any new file is inserted only then add the file path.
  				if ( ! empty( $file_path ) ) {

  					// Get existing attach post id for this user first, delete img from the uploads folder and the existing post attach from wp_posts and then save the new one.
  //					unlink( $file_path );
  					$wrk_attach_post_id = $phone = ( get_user_meta( $user_id, 'user_wrk_img_post_id', true ) ) ? get_user_meta( $user_id, 'user_wrk_img_post_id', true ) : '';
  					wp_delete_post( $wrk_attach_post_id, true );
  					wf_update_post_with_attach( $file_path, $post_id, $user_id, 'work-attach' );
  				}
  			}
  		}
  		unset( $_FILES );
  	}
  }

}
