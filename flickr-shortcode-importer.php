<?php
/*
	Plugin Name: Flickr Shortcode Importer
	Plugin URI: http://wordpress.org/extend/plugins/flickr-shortcode-importer/
	Description: Import [flickr] shortcodes into the Media Library. The first [flickr] image found in post content is set as the post's Featured Image and removed from the post content. The remaining [flickr] shortcodes are then transitioned to like sized locally referenced images.
	Version: 1.0.0
	Author: Michael Cannon
	Author URI: http://peimic.com/contact-peimic/
	License: GPL2
	
	Copyright 2011  Michael Cannon  (email : michael@peimic.com)
 
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class FlickrShortcodeImporter {
	var $menu_id;

	// Plugin initialization
	function FlickrShortcodeImporter() {
		if ( ! function_exists( 'admin_url' ) )
			return false;

		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's "localization" folder and name it "flickr-shortcode-importer-[value in wp-config].mo"
		load_plugin_textdomain( 'flickr-shortcode-importer', false, '/flickr-shortcode-importer/localization' );

		add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueues' ) );
		add_action( 'wp_ajax_regeneratethumbnail', array( &$this, 'ajax_process_image' ) );
		add_filter( 'plugin_action_links', array( &$this, 'add_plugin_action_links' ), 10, 2 );
		add_action( 'admin_action_bulk_regenerate_thumbnails', array( &$this, 'bulk_action_handler' ) );
	}


	// Display a Settings link on the main Plugins page
	function add_plugin_action_links( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
			// $fsi_link			= '<a href="'.get_admin_url().'options-general.php?page=flickr-shortcode-importer/flickr-shortcode-importer.php">'.__('Settings').'</a>';
			// array_unshift( $links, $fsi_link );

			$fsi_link			= '<a href="'.get_admin_url().'tools.php?page=flickr-shortcode-importer">'.__('Import', 'flickr-shortcode-importer').'</a>';
			// make the 'Import' link appear first
			array_unshift( $links, $fsi_link );
		}

		return $links;
	}

	// Register the management page
	function add_admin_menu() {
		$this->menu_id = add_management_page( __( 'Flickr Shortcode Importer', 'flickr-shortcode-importer' ), __( '[flickr] Importer', 'flickr-shortcode-importer' ), 'manage_options', 'flickr-shortcode-importer', array(&$this, 'regenerate_interface') );
	}


	// Enqueue the needed Javascript and CSS
	function admin_enqueues( $hook_suffix ) {
		if ( $hook_suffix != $this->menu_id )
			return;

		// WordPress 3.1 vs older version compatibility
		if ( wp_script_is( 'jquery-ui-widget', 'registered' ) )
			wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'jquery-ui/jquery.ui.progressbar.min.js', __FILE__ ), array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.8.6' );
		else
			wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'jquery-ui/jquery.ui.progressbar.min.1.7.2.js', __FILE__ ), array( 'jquery-ui-core' ), '1.7.2' );

		wp_enqueue_style( 'jquery-ui-regenthumbs', plugins_url( 'jquery-ui/redmond/jquery-ui-1.7.2.custom.css', __FILE__ ), array(), '1.7.2' );
	}


	// Handles the bulk actions POST
	function bulk_action_handler() {
		check_admin_referer( 'bulk-media' );

		if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) )
			return;

		$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) );

		// Can't use wp_nonce_url() as it escapes HTML entities
		wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'flickr-shortcode-importer' ), admin_url( 'tools.php?page=flickr-shortcode-importer&goback=1&ids=' . $ids ) ) );
		exit();
	}


	// The user interface plus thumbnail regenerator
	function regenerate_interface() {
		global $wpdb;

		?>

<div id="message" class="updated fade" style="display:none"></div>

<div class="wrap regenthumbs">
	<h2><?php _e('Flickr Shortcode Importer', 'flickr-shortcode-importer'); ?></h2>

<?php

		// If the button was clicked
		if ( ! empty( $_POST['flickr-shortcode-importer'] ) || ! empty( $_REQUEST['ids'] ) ) {
			// Capability check
			if ( !current_user_can( 'manage_options' ) )
				wp_die( __( 'Cheatin&#8217; uh?' , 'flickr-shortcode-importer') );

			// Form nonce check
			check_admin_referer( 'flickr-shortcode-importer' );

			// Create the list of image IDs
			if ( ! empty( $_REQUEST['ids'] ) ) {
				$images = array_map( 'intval', explode( ',', trim( $_REQUEST['ids'], ',' ) ) );
				$ids = implode( ',', $images );
			} else {
				// Directly querying the database is normally frowned upon, but all
				// of the API functions will return the full post objects which will
				// suck up lots of memory. This is best, just not as future proof.
				if ( ! $images = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC" ) ) {
					echo '	<p>' . sprintf( __( "Unable to find any images. Are you sure <a href='%s'>some exist</a>?", 'flickr-shortcode-importer' ), admin_url( 'upload.php?post_mime_type=image' ) ) . "</p></div>";
					return;
				}

				// Generate the list of IDs
				$ids = array();
				foreach ( $images as $image )
					$ids[] = $image->ID;
				$ids = implode( ',', $ids );
			}

			echo '	<p>' . __( "Please be patient while the thumbnails are regenerated. This can take a while if your server is slow (inexpensive hosting) or if you have many images. Do not navigate away from this page until this script is done or the thumbnails will not be resized. You will be notified via this page when the regenerating is completed.", 'flickr-shortcode-importer' ) . '</p>';

			$count = count( $images );

			$text_goback = ( ! empty( $_GET['goback'] ) ) ? sprintf( __( 'To go back to the previous page, <a href="%s">click here</a>.', 'flickr-shortcode-importer' ), 'javascript:history.go(-1)' ) : '';
			$text_failures = sprintf( __( 'All done! %1$s image(s) were successfully resized in %2$s seconds and there were %3$s failure(s). To try regenerating the failed images again, <a href="%4$s">click here</a>. %5$s', 'flickr-shortcode-importer' ), "' + rt_successes + '", "' + rt_totaltime + '", "' + rt_errors + '", esc_url( wp_nonce_url( admin_url( 'tools.php?page=flickr-shortcode-importer&goback=1' ), 'flickr-shortcode-importer' ) . '&ids=' ) . "' + rt_failedlist + '", $text_goback );
			$text_nofailures = sprintf( __( 'All done! %1$s image(s) were successfully resized in %2$s seconds and there were 0 failures. %3$s', 'flickr-shortcode-importer' ), "' + rt_successes + '", "' + rt_totaltime + '", $text_goback );
?>


	<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'flickr-shortcode-importer' ) ?></em></p></noscript>

	<div id="regenthumbs-bar" style="position:relative;height:25px;">
		<div id="regenthumbs-bar-percent" style="position:absolute;left:50%;top:50%;width:300px;margin-left:-150px;height:25px;margin-top:-9px;font-weight:bold;text-align:center;"></div>
	</div>

	<p><input type="button" class="button hide-if-no-js" name="regenthumbs-stop" id="regenthumbs-stop" value="<?php _e( 'Abort Resizing Images', 'flickr-shortcode-importer' ) ?>" /></p>

	<h3 class="title"><?php _e( 'Debugging Information', 'flickr-shortcode-importer' ) ?></h3>

	<p>
		<?php printf( __( 'Total Images: %s', 'flickr-shortcode-importer' ), $count ); ?><br />
		<?php printf( __( 'Images Resized: %s', 'flickr-shortcode-importer' ), '<span id="regenthumbs-debug-successcount">0</span>' ); ?><br />
		<?php printf( __( 'Resize Failures: %s', 'flickr-shortcode-importer' ), '<span id="regenthumbs-debug-failurecount">0</span>' ); ?>
	</p>

	<ol id="regenthumbs-debuglist">
		<li style="display:none"></li>
	</ol>

	<script type="text/javascript">
	// <![CDATA[
		jQuery(document).ready(function($){
			var i;
			var rt_images = [<?php echo $ids; ?>];
			var rt_total = rt_images.length;
			var rt_count = 1;
			var rt_percent = 0;
			var rt_successes = 0;
			var rt_errors = 0;
			var rt_failedlist = '';
			var rt_resulttext = '';
			var rt_timestart = new Date().getTime();
			var rt_timeend = 0;
			var rt_totaltime = 0;
			var rt_continue = true;

			// Create the progress bar
			$("#regenthumbs-bar").progressbar();
			$("#regenthumbs-bar-percent").html( "0%" );

			// Stop button
			$("#regenthumbs-stop").click(function() {
				rt_continue = false;
				$('#regenthumbs-stop').val("<?php echo $this->esc_quotes( __( 'Stopping...', 'flickr-shortcode-importer' ) ); ?>");
			});

			// Clear out the empty list element that's there for HTML validation purposes
			$("#regenthumbs-debuglist li").remove();

			// Called after each resize. Updates debug information and the progress bar.
			function FSIThumbsUpdateStatus( id, success, response ) {
				$("#regenthumbs-bar").progressbar( "value", ( rt_count / rt_total ) * 100 );
				$("#regenthumbs-bar-percent").html( Math.round( ( rt_count / rt_total ) * 1000 ) / 10 + "%" );
				rt_count = rt_count + 1;

				if ( success ) {
					rt_successes = rt_successes + 1;
					$("#regenthumbs-debug-successcount").html(rt_successes);
					$("#regenthumbs-debuglist").append("<li>" + response.success + "</li>");
				}
				else {
					rt_errors = rt_errors + 1;
					rt_failedlist = rt_failedlist + ',' + id;
					$("#regenthumbs-debug-failurecount").html(rt_errors);
					$("#regenthumbs-debuglist").append("<li>" + response.error + "</li>");
				}
			}

			// Called when all images have been processed. Shows the results and cleans up.
			function FSIThumbsFinishUp() {
				rt_timeend = new Date().getTime();
				rt_totaltime = Math.round( ( rt_timeend - rt_timestart ) / 1000 );

				$('#regenthumbs-stop').hide();

				if ( rt_errors > 0 ) {
					rt_resulttext = '<?php echo $text_failures; ?>';
				} else {
					rt_resulttext = '<?php echo $text_nofailures; ?>';
				}

				$("#message").html("<p><strong>" + rt_resulttext + "</strong></p>");
				$("#message").show();
			}

			// Regenerate a specified image via AJAX
			function FSIThumbs( id ) {
				$.ajax({
					type: 'POST',
					url: ajaxurl,
					data: { action: "regeneratethumbnail", id: id },
					success: function( response ) {
						if ( response.success ) {
							FSIThumbsUpdateStatus( id, true, response );
						}
						else {
							FSIThumbsUpdateStatus( id, false, response );
						}

						if ( rt_images.length && rt_continue ) {
							FSIThumbs( rt_images.shift() );
						}
						else {
							FSIThumbsFinishUp();
						}
					},
					error: function( response ) {
						FSIThumbsUpdateStatus( id, false, response );

						if ( rt_images.length && rt_continue ) {
							FSIThumbs( rt_images.shift() );
						} 
						else {
							FSIThumbsFinishUp();
						}
					}
				});
			}

			FSIThumbs( rt_images.shift() );
		});
	// ]]>
	</script>
<?php
		}

		// No button click? Display the form.
		else {
?>
	<form method="post" action="">
<?php wp_nonce_field('flickr-shortcode-importer') ?>

	<p><?php _e( "Use this tool to import [flickr] shortcodes into the Media Library. The first [flickr] image found in post content is set as the post's Featured Image and removed from the post content. The remaining [flickr] shortcodes are then transitioned to like sized locally referenced images.", 'flickr-shortcode-importer' ); ?></p>

	<p><?php _e( "Flickr shortcode import is not reversible. Backup your database beforehand.", 'flickr-shortcode-importer' ); ?></p>

	<p><?php _e( 'To begin, just press the button below.', 'flickr-shortcode-importer ', 'flickr-shortcode-importer'); ?></p>

	<p><input type="submit" class="button hide-if-no-js" name="flickr-shortcode-importer" id="flickr-shortcode-importer" value="<?php _e( 'Import Flickr Shortcode', 'flickr-shortcode-importer' ) ?>" /></p>

	<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'flickr-shortcode-importer' ) ?></em></p></noscript>

	</form>
<?php
		} // End if button
?>
</div>

<?php
	}


	// Process a single image ID (this is an AJAX handler)
	function ajax_process_image() {
		@error_reporting( 0 ); // Don't break the JSON result

		header( 'Content-type: application/json' );

		$id = (int) $_REQUEST['id'];
		$image = get_post( $id );

		if ( ! $image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) )
			die( json_encode( array( 'error' => sprintf( __( 'Failed resize: %s is an invalid image ID.', 'flickr-shortcode-importer' ), esc_html( $_REQUEST['id'] ) ) ) ) );

		if ( !current_user_can( 'manage_options' ) )
			$this->die_json_error_msg( $image->ID, __( "Your user account doesn't have permission to resize images", 'flickr-shortcode-importer' ) );

		$fullsizepath = get_attached_file( $image->ID );

		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) )
			$this->die_json_error_msg( $image->ID, sprintf( __( 'The originally uploaded image file cannot be found at %s', 'flickr-shortcode-importer' ), '<code>' . esc_html( $fullsizepath ) . '</code>' ) );

		@set_time_limit( 900 ); // 5 minutes per image should be PLENTY

		$metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );

		if ( is_wp_error( $metadata ) )
			$this->die_json_error_msg( $image->ID, $metadata->get_error_message() );
		if ( empty( $metadata ) )
			$this->die_json_error_msg( $image->ID, __( 'Unknown failure reason.', 'flickr-shortcode-importer' ) );

		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $image->ID, $metadata );

		die( json_encode( array( 'success' => sprintf( __( '&quot;%1$s&quot; (ID %2$s) was successfully resized in %3$s seconds.', 'flickr-shortcode-importer' ), esc_html( get_the_title( $image->ID ) ), $image->ID, timer_stop() ) ) ) );
	}


	// Helper to make a JSON error message
	function die_json_error_msg( $id, $message ) {
		die( json_encode( array( 'error' => sprintf( __( '&quot;%1$s&quot; (ID %2$s) failed to resize. The error message was: %3$s', 'flickr-shortcode-importer' ), esc_html( get_the_title( $id ) ), $id, $message ) ) ) );
	}


	// Helper function to escape quotes in strings for use in Javascript
	function esc_quotes( $string ) {
		return str_replace( '"', '\"', $string );
	}
}

// Start up this plugin
add_action( 'init', 'FlickrShortcodeImporter' );
function FlickrShortcodeImporter() {
	global $FlickrShortcodeImporter;
	$FlickrShortcodeImporter = new FlickrShortcodeImporter();
}

?>