<?php
/*
Plugin Name: Comments for Nextgen Gallery
Description: The plugin enables the users to comment on separate images in the Nextgen Gallery, using embedded WordPress functionality.
Version: 1.1
Author URI: http://aimbox.com
Author: Aimbox
Depends: NextGEN Gallery
*/

/*
 * Algorithm:
 * Add in comment's meta PID of Gallery Photo
 * Display only comments which have PID == current PID of photo
 * (PID - Photo ID)
*/

if ( ! function_exists( 'ngg_comments_change_comment_form_defaults' ) ) {
	//Add hidden inputs with PID in Comment Form
	function ngg_comments_change_comment_form_defaults( $default ) {
		$pid = get_query_var( 'pid' );
		$id  = get_the_ID();
		if ( ! empty( $pid ) ) {
			$default['logged_in_as'] .= '
				<input name="pid" value="' . esc_attr($pid) . '" type="hidden" />
				<input name="gallery" value="' . esc_attr(get_query_var('gallery')) . '" type="hidden" />
				<input name="album" value="' . esc_attr(get_query_var('album')) . '" type="hidden" />
				<input name="postId" value="' . esc_attr($id) . '" type="hidden" />';
		}
		return $default;
	}

	add_filter( 'comment_form_defaults', 'ngg_comments_change_comment_form_defaults' );
}
if ( ! function_exists( 'ngg_comments_save_comment_meta_data' ) ) {
	//Save in in Comment's Meta
	function ngg_comments_save_comment_meta_data( $comment_id ) {
		if ( ! empty( $_POST['pid'] ) ) {
			add_comment_meta( $comment_id, 'pid', $_POST['pid'] );
		}
	}

	add_action( 'comment_post', 'ngg_comments_save_comment_meta_data', 1 );
}
if ( ! function_exists( 'ngg_comments_return_to_photo' ) ) {
	//Redirect to photo after comment posting
	function ngg_comments_return_to_photo() {
		if ( ! empty( $_POST['pid'] ) ) {
			$params = array();
			foreach ( array( 'pid', 'gallery', 'album' ) as $param ) {
				if ( ! empty( $_POST[$param] ) ) {
					$params[$param] = $_POST[$param];
				}
			}

			$address = add_query_arg( $params, get_permalink( $_POST['postId'] ) );

			header( "Location: " . $address );
			exit;
		}
	}

	add_action( 'comment_post', 'ngg_comments_return_to_photo', 2 );
}
if ( ! function_exists( 'ngg_comments_get_comments_number_custom' ) ) {
	function ngg_comments_get_comments_number_custom( $count, $post_id ) {

		$pid = get_query_var( 'pid' );

		if ( ! ngg_comments_want_to_show_comments() ) {
			return 0;
		}

		if ( empty( $pid ) ) {
			return $count;
		}

		$comments_count = count(get_comments(array
		(
			'post_id' 	 => $post_id,
			'meta_key' 	 => 'pid',
			'meta_value' => $pid,
		)));

		return $comments_count;
	}

	add_filter( 'get_comments_number', 'ngg_comments_get_comments_number_custom', 10, 2 );
}

if ( ! function_exists( 'ngg_comments_want_to_show_comments' ) ) {
	function ngg_comments_want_to_show_comments() {

		$picture_id_is_set   = ( get_query_var( 'pid' ) != '' );

		$post_content = get_post_field( 'post_content', get_the_ID() );
		$is_ngg_gallery_page = ( strpos( $post_content, '[nggallery' ) !== false || strpos( $post_content, '[nggalbum') !== false);

		return ( ! $is_ngg_gallery_page || $is_ngg_gallery_page && $picture_id_is_set );
	}
}

if ( ! function_exists( 'ngg_comments_filter_post_comments' ) ) {

	function ngg_comments_filter_post_comments( $comments ) {

		/** @var wpdb $wpdb */
		global $wpdb;

		if ( ! ngg_comments_want_to_show_comments()) {
			return array();
		}

		$pid = get_query_var( 'pid' );
		if ( $pid ) {
			$comment_ids = array();
			foreach ( $comments as $comment ) {
				$comment_ids[] = $comment->comment_ID;
			}

			if ( $comment_ids ) {
				$comment_ids_string = join( ',', $wpdb->_escape( $comment_ids ) );
				$matching_comment_ids = $wpdb->get_col("
					SELECT DISTINCT comment_id
					FROM {$wpdb->commentmeta}
					WHERE
					comment_id IN ({$comment_ids_string}) AND
					meta_key = 'pid' AND
					meta_value = " . $wpdb->_escape( $pid )
				);

				if ( is_array( $matching_comment_ids ) ) {
					foreach ( $comments as $idx => $comment ) {
						if ( ! in_array( $comment->comment_ID, $matching_comment_ids ) ) {
							unset( $comments[$idx] );
						}
					}

					$comments = array_values($comments);
				}
			}
		}

		return $comments;
	}

	add_action( 'comments_array', 'ngg_comments_filter_post_comments' );
}
if ( ! function_exists( 'ngg_comments_disable_comments_on_pages' ) ) {
	function ngg_comments_disable_comments_on_pages( $file ) {
		return ( ngg_comments_want_to_show_comments() ? $file : __FILE__ );
	}

	add_filter( 'comments_template', 'ngg_comments_disable_comments_on_pages', 11 );
}
?>
