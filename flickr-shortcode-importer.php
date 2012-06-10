<?php
/*
Plugin Name: Flickr Shortcode Importer
Plugin URI: http://wordpress.org/extend/plugins/flickr-shortcode-importer/
Description: Imports [flickr], [flickrset], [flickr-gallery] shortcode and Flickr-sourced A/IMG tagged media into the Media Library.
Version: 1.7.7
Author: Michael Cannon
Author URI: http://typo3vagabond.com/about-typo3-vagabond/hire-michael/
License: GPL2

Copyright 2012  Michael Cannon  (email : michael@typo3vagabond.com)

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

// Load dependencies
require_once( 'lib/inc.flickr.php' );
require_once( 'settings.flickr-shortcode-importer.php' );

if ( ! function_exists( 'add_screen_meta_link' ) ) {
	require_once( 'screen-meta-links.php' );
}


class Flickr_Shortcode_Importer {
	var $menu_id;
	var $flickr_id				= false;
	var $flickset_id			= false;

	// Plugin initialization
	function Flickr_Shortcode_Importer() {
		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's "localization" folder and name it "flickr-shortcode-importer-[value in wp-config].mo"
		load_plugin_textdomain( 'flickr-shortcode-importer', false, '/flickr-shortcode-importer/languages/' );

		$role_enable_post_widget	= fsi_get_options( 'role_enable_post_widget' );
		if ( current_user_can( $role_enable_post_widget ) ) {
			add_action( 'add_meta_boxes', array( &$this, 'flickr_import_meta_boxes' ) );
		}

		add_action( 'wp_ajax_importflickrshortcode', array( &$this, 'ajax_process_shortcode' ) );
        
		// needed to include has_post_thumbnail code
		add_theme_support( 'post-thumbnails' );

		if ( function_exists( 'admin_url' ) ) {
			$this->options_link		= '<a href="'.get_admin_url().'options-general.php?page=fsi-options">'.__('Settings', 'flickr-shortcode-importer').'</a>';
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueues' ) );
			add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );
			add_filter( 'plugin_action_links', array( &$this, 'add_plugin_action_links' ), 10, 2 );
		}
	}


	function flickr_import_meta_boxes() {
		add_meta_box( 'flickr_import', __( '[flickr] Importer', 'flickr-shortcode-importer' ), array( &$this, 'post_flickr_import_meta_box' ), 'page', 'side' );
		add_meta_box( 'flickr_import', __( '[flickr] Importer', 'flickr-shortcode-importer' ), array( &$this, 'post_flickr_import_meta_box' ), 'post', 'side' );
	}


	function post_flickr_import_meta_box( $post ) {
		wp_nonce_field( 'flickr_import', 'flickr-shortcode-importer' );
		echo '<label for="flickr_import" class="selectit">';
		$checked				= get_post_meta( $post->ID, 'process_flickr_shortcode', true );
		echo '<input name="flickr_import" type="checkbox" id="flickr_import" value="1" ' . checked( $checked, 1, false ) . ' /> ';
		echo __( 'Import [flickr] content', 'flickr-shortcode-importer' );
		echo '</label>';
	}


	// Display a Options link on the main Plugins page
	function add_plugin_action_links( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
			array_unshift( $links, $this->options_link );

			$link				= '<a href="'.get_admin_url().'tools.php?page=flickr-shortcode-importer">'.__('Import', 'flickr-shortcode-importer').'</a>';
			array_unshift( $links, $link );
		}

		return $links;
	}


	// Register the management page
	function add_admin_menu() {
		$this->menu_id = add_management_page( __( 'Flickr Shortcode Importer', 'flickr-shortcode-importer' ), __( '[flickr] Importer', 'flickr-shortcode-importer' ), 'manage_options', 'flickr-shortcode-importer', array(&$this, 'user_interface') );

        add_screen_meta_link(
        	'fsi-settings-link',
			__('[Flickr] Settings', 'flickr-shortcode-importer'),
			admin_url('options-general.php?page=fsi-options'),
			$this->menu_id,
			array('style' => 'font-weight: bold;')
		);
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

		wp_enqueue_style( 'jquery-ui-fsiposts', plugins_url( 'jquery-ui/redmond/jquery-ui-1.7.2.custom.css', __FILE__ ), array(), '1.7.2' );
	}


	function user_interface() {
		global $wpdb;

		// Capability check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( $this->post_id, __( "Your user account doesn't have permission to import images", 'flickr-shortcode-importer' ) );
		}

?>

<div id="message" class="updated fade" style="display:none"></div>

<div class="wrap fsiposts">
	<div class="icon32" id="icon-tools"></div>
	<h2><?php _e('Flickr Shortcode Importer', 'flickr-shortcode-importer'); ?></h2>

<?php
		if ( fsi_get_options( 'debug_mode' ) ) {
			$posts_to_import		= fsi_get_options( 'posts_to_import' );
			$posts_to_import		= explode( ',', $posts_to_import );
			foreach ( $posts_to_import as $key => $post_id ) {
				$this->post_id		= $post_id;
				$this->ajax_process_shortcode();
			}
		}

		// If the button was clicked
		if ( ! empty( $_POST['flickr-shortcode-importer'] ) || ! empty( $_REQUEST['posts'] ) ) {
-			// Form nonce check
			check_admin_referer( 'flickr-shortcode-importer' );

			// Create the list of image IDs
			if ( ! empty( $_REQUEST['posts'] ) ) {
				$posts			= array_map( 'intval', explode( ',', trim( $_REQUEST['posts'], ',' ) ) );
				$count			= count( $posts );
				$posts			= implode( ',', $posts );
			} else {
				$flickr_source_where = "";
				if ( fsi_get_options( 'import_flickr_sourced_tags' ) ) {
					$flickr_source_where = <<<EOD
						OR (
							post_content LIKE '%<img%src=%http://farm%.static.flickr.com/%>%'
							OR post_content LIKE '%<img%src=%http://farm%staticflickr.com/%>%'
						)
EOD;
				}

				// Directly querying the database is normally frowned upon, but all of the API functions will return the full post objects which will suck up lots of memory. This is best, just not as future proof.
				$query			= "
					SELECT ID
					FROM $wpdb->posts
					WHERE 1 = 1
					AND ( post_type = 'post' OR post_type = 'page' )
					AND post_parent = 0
					AND (
						post_content LIKE '%[flickr %'
						OR post_content LIKE '%[flickrset %'
						$flickr_source_where
					)
					";

				$include_ids		= fsi_get_options( 'posts_to_import' );
				if ( $include_ids )
					$query		.= ' AND ID IN ( ' . $include_ids . ' )';

				$skip_ids		= fsi_get_options( 'skip_importing_post_ids' );
				if ( $skip_ids )
					$query		.= ' AND ID NOT IN ( ' . $skip_ids . ' )';

				$limit			= (int) fsi_get_options( 'limit' );
				if ( $limit )
					$query		.= ' LIMIT ' . $limit;

				$results		= $wpdb->get_results( $query );
				$count			= 0;

				// Generate the list of IDs
				$posts			= array();
				foreach ( $results as $post ) {
					$posts[]	= $post->ID;
					$count++;
				}

				if ( ! $count ) {
					echo '	<p>' . _e( 'All done. No [flickr] codes found in posts', 'flickr-shortcode-importer' ) . "</p></div>";
					return;
				}

				$posts			= implode( ',', $posts );
			}

			$this->show_status( $count, $posts );
		} else {
			// No button click? Display the form.
			$this->show_greeting();
		}
?>
	</div>
<?php
	}


	function convert_flickr_sourced_tags( $post ) {
		global $wpdb;

		$post_content			= $post->post_content;

		// looking for
		// <a class="tt-flickr tt-flickr-Medium" title="Khan Sao Road, Bangkok, Thailand" href="http://www.flickr.com/photos/comprock/4334303694/" target="_blank"><img class="alignnone" src="http://farm3.static.flickr.com/2768/4334303694_37785d0f0d.jpg" alt="Khan Sao Road, Bangkok, Thailand" width="500" height="375" /></a>
		// cycle through a/img
		$find_flickr_a_tag		= '#<a.*href=.*http://www.flickr.com/.*><img.*src=.*http://farm\d+.static.?flickr.com/[^>]+></a>#i';
		$a_tag_open				= '<a ';

		$post_content			= $this->convert_tag_to_flickr( $post_content, $a_tag_open, $find_flickr_a_tag );

		// cycle through standalone img
		$find_flickr_img_tag		= '#<img.*src=.*http://farm\d+.static.?flickr.com/[^>]+>#i';
		$img_tag_open			= '<img ';
		$post_content			= $this->convert_tag_to_flickr( $post_content, $img_tag_open, $find_flickr_img_tag, true );

		$update					= array(
			'ID'				=> $post->ID,
			'post_content'		=> $post_content,
		);

		wp_update_post( $update );
	}


	function convert_tag_to_flickr( $post_content, $tag_open, $find_tag, $img_only = false ) {
		$default_alignment		= fsi_get_options( 'default_image_alignment', 'left' );
		$doc					= new DOMDocument();
		$flickr_shortcode		= '[flickr id="%1$s" thumbnail="%2$s" align="%3$s"]' . "\n";
		$matches				= explode( $tag_open, $post_content );
		$size					= '';

		// for each A/IMG tag set
		foreach ( $matches as $html ) {
			$html				= $tag_open . $html;

			if ( ! preg_match( $find_tag, $html, $match ) ) {
				continue;
			}

			// deal only with the A/IMG tag
			$tag_html				= $match[0];

			// safer than home grown regex
			if ( ! $doc->loadHTML( $tag_html ) ) {
				continue;
			}

			if ( ! $img_only ) {
				// parse out parts id, thumbnail, align
				$a_tags				= $doc->getElementsByTagName( 'a' );
				$a_tag				= $a_tags->item( 0 );

				// gives size tt-flickr tt-flickr-Medium
				$size				= $a_tag->getAttribute( 'class' );
			}

			$size				= $this->get_shortcode_size( $size );

			$image_tags			= $doc->getElementsByTagName( 'img' );
			$image_tag			= $image_tags->item( 0 );

			// give photo id http://farm3.static.flickr.com/2768/4334303694_37785d0f0d.jpg
			$src				= $image_tag->getAttribute( 'src' );
			$filename			= basename( $src );
			$id					= preg_replace( '#^(\d+)_.*#', '\1', $filename );

			// gives alginment alignnone
			$align_primary		= $image_tag->getAttribute( 'class' );
			$align_secondary	= $image_tag->getAttribute( 'align' );
			$align_combined		= $align_secondary . ' ' . $align_primary;

			$find_align			= '#(none|left|center|right)#i';
			preg_match_all( $find_align, $align_combined, $align_matches );
			// get the last align mentioned since that has precedence
			$align				= ( count( $align_matches[0] ) ) ? array_pop( $align_matches[0] ) : $default_alignment;

			// ceate simple [flickr] like
			// [flickr id="5348222727" thumbnail="small" align="none"]
			$replacement		= sprintf( $flickr_shortcode, $id, $size, $align );
			// replace A/IMG with new [flickr]
			$post_content		= str_replace( $tag_html, $replacement, $post_content );
		}

		return $post_content;
	}


	function show_status( $count, $posts ) {
		echo '<p>' . __( "Please be patient while the [flickr(set)] shortcodes are processed. This can take a while, up to 2 minutes per individual Flickr media item. Do not navigate away from this page until this script is done or the import will not be completed. You will be notified via this page when the import is completed.", 'flickr-shortcode-importer' ) . '</p>';

		echo '<p>' . sprintf( __( 'Estimated time required to import is %1$s minutes.', 'flickr-shortcode-importer' ), ( $count * 2 ) ) . '</p>';

		$text_goback = ( ! empty( $_GET['goback'] ) ) ? sprintf( __( 'To go back to the previous page, <a href="%s">click here</a>.', 'flickr-shortcode-importer' ), 'javascript:history.go(-1)' ) : '';

		$text_failures = sprintf( __( 'All done! %1$s [flickr(set)](s) were successfully processed in %2$s seconds and there were %3$s failure(s). To try importing the failed [flickr]s again, <a href="%4$s">click here</a>. %5$s', 'flickr-shortcode-importer' ), "' + rt_successes + '", "' + rt_totaltime + '", "' + rt_errors + '", esc_url( wp_nonce_url( admin_url( 'tools.php?page=flickr-shortcode-importer&goback=1' ), 'flickr-shortcode-importer' ) . '&posts=' ) . "' + rt_failedlist + '", $text_goback );

		$text_nofailures = sprintf( __( 'All done! %1$s [flickr(set)](s) were successfully processed in %2$s seconds and there were no failures. %3$s', 'flickr-shortcode-importer' ), "' + rt_successes + '", "' + rt_totaltime + '", $text_goback );
?>

	<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'flickr-shortcode-importer' ) ?></em></p></noscript>

	<div id="fsiposts-bar" style="position:relative;height:25px;">
		<div id="fsiposts-bar-percent" style="position:absolute;left:50%;top:50%;width:300px;margin-left:-150px;height:25px;margin-top:-9px;font-weight:bold;text-align:center;"></div>
	</div>

	<p><input type="button" class="button hide-if-no-js" name="fsiposts-stop" id="fsiposts-stop" value="<?php _e( 'Abort Importing [flickr(set)]s', 'flickr-shortcode-importer' ) ?>" /></p>

	<h3 class="title"><?php _e( 'Debugging Information', 'flickr-shortcode-importer' ) ?></h3>

	<p>
		<?php printf( __( 'Total [flickr(set)]s: %s', 'flickr-shortcode-importer' ), $count ); ?><br />
		<?php printf( __( '[flickr(set)]s Imported: %s', 'flickr-shortcode-importer' ), '<span id="fsiposts-debug-successcount">0</span>' ); ?><br />
		<?php printf( __( 'Import Failures: %s', 'flickr-shortcode-importer' ), '<span id="fsiposts-debug-failurecount">0</span>' ); ?>
	</p>

	<ol id="fsiposts-debuglist">
		<li style="display:none"></li>
	</ol>

	<script type="text/javascript">
	// <![CDATA[
		jQuery(document).ready(function($){
			var i;
			var rt_posts = [<?php echo $posts; ?>];
			var rt_total = rt_posts.length;
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
			$("#fsiposts-bar").progressbar();
			$("#fsiposts-bar-percent").html( "0%" );

			// Stop button
			$("#fsiposts-stop").click(function() {
				rt_continue = false;
				$('#fsiposts-stop').val("<?php echo $this->esc_quotes( __( 'Stopping, please wait a moment.', 'flickr-shortcode-importer' ) ); ?>");
			});

			// Clear out the empty list element that's there for HTML validation purposes
			$("#fsiposts-debuglist li").remove();

			// Called after each import. Updates debug information and the progress bar.
			function FSIPostsUpdateStatus( id, success, response ) {
				$("#fsiposts-bar").progressbar( "value", ( rt_count / rt_total ) * 100 );
				$("#fsiposts-bar-percent").html( Math.round( ( rt_count / rt_total ) * 1000 ) / 10 + "%" );
				rt_count = rt_count + 1;

				if ( success ) {
					rt_successes = rt_successes + 1;
					$("#fsiposts-debug-successcount").html(rt_successes);
					$("#fsiposts-debuglist").append("<li>" + response.success + "</li>");
				}
				else {
					rt_errors = rt_errors + 1;
					rt_failedlist = rt_failedlist + ',' + id;
					$("#fsiposts-debug-failurecount").html(rt_errors);
					$("#fsiposts-debuglist").append("<li>" + response.error + "</li>");
				}
			}

			// Called when all posts have been processed. Shows the results and cleans up.
			function FSIPostsFinishUp() {
				rt_timeend = new Date().getTime();
				rt_totaltime = Math.round( ( rt_timeend - rt_timestart ) / 1000 );

				$('#fsiposts-stop').hide();

				if ( rt_errors > 0 ) {
					rt_resulttext = '<?php echo $text_failures; ?>';
				} else {
					rt_resulttext = '<?php echo $text_nofailures; ?>';
				}

				$("#message").html("<p><strong>" + rt_resulttext + "</strong></p>");
				$("#message").show();
			}

			// Regenerate a specified image via AJAX
			function FSIPosts( id ) {
				$.ajax({
					type: 'POST',
					url: ajaxurl,
					data: { action: "importflickrshortcode", id: id },
					success: function( response ) {
						if ( response.success ) {
							FSIPostsUpdateStatus( id, true, response );
						}
						else {
							FSIPostsUpdateStatus( id, false, response );
						}

						if ( rt_posts.length && rt_continue ) {
							FSIPosts( rt_posts.shift() );
						}
						else {
							FSIPostsFinishUp();
						}
					},
					error: function( response ) {
						FSIPostsUpdateStatus( id, false, response );

						if ( rt_posts.length && rt_continue ) {
							FSIPosts( rt_posts.shift() );
						} 
						else {
							FSIPostsFinishUp();
						}
					}
				});
			}

			FSIPosts( rt_posts.shift() );
		});
	// ]]>
	</script>
<?php
	}


	function show_greeting() {
?>
	<form method="post" action="">
<?php wp_nonce_field( 'flickr-shortcode-importer' ); ?>

	<p><?php _e( "Use this tool to import [flickr] shortcodes into the Media Library. The first [flickr] image found in post content is set as the post's Featured Image and removed from the post content. The remaining [flickr] shortcodes are then transitioned to like sized locally referenced images.", 'flickr-shortcode-importer' ); ?></p>

	<p><?php _e( "[flickrset] shortcodes are handled similarly to [flickr] importing. The difference is that [flickrset] is replaced by [gallery] and the Featured Image of a post is set from the first image in the [flickrset] per Options.", 'flickr-shortcode-importer' ); ?></p>

	<p><?php _e( "Flickr shortcode import is not reversible. Backup your database beforehand or be prepared to revert each transformmed post manually.", 'flickr-shortcode-importer' ); ?></p>

	<p><?php printf( __( 'Please review your %s before proceeding.', 'flickr-shortcode-importer' ), $this->options_link ); ?></p>

	<p><?php _e( 'To begin, just press the button below.', 'flickr-shortcode-importer ', 'flickr-shortcode-importer'); ?></p>

	<p><input type="submit" class="button hide-if-no-js" name="flickr-shortcode-importer" id="flickr-shortcode-importer" value="<?php _e( 'Import Flickr Shortcode', 'flickr-shortcode-importer' ) ?>" /></p>

	<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'flickr-shortcode-importer' ) ?></em></p></noscript>

	</form>
<?php
	}


	// Process a single post ID
	function process_flickr_shortcode( $post_id ) {
		$this->post_id			= (int) $post_id;
		$post					= get_post( $this->post_id );

		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ) )  )
			return;

		if ( fsi_get_options( 'import_flickr_sourced_tags' ) ) {
			$this->convert_flickr_sourced_tags( $post );
			$post				= get_post( $this->post_id );
		}

		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ) ) || ! stristr( $post->post_content, '[flickr' ) )
			return;

	   	$this->_process_shortcode( $post );
	}


	// Process a single post ID (this is an AJAX handler)
	function ajax_process_shortcode() {
		if ( ! fsi_get_options( 'debug_mode' ) ) {
			error_reporting( 0 ); // Don't break the JSON result
			header( 'Content-type: application/json' );
			$this->post_id		= (int) $_REQUEST['id'];
		}

		$post					= get_post( $this->post_id );

		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ) )  )
			return;

		if ( fsi_get_options( 'import_flickr_sourced_tags' ) ) {
			$this->convert_flickr_sourced_tags( $post );
			$post				= get_post( $this->post_id );
		}

		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ) ) || ! stristr( $post->post_content, '[flickr' ) )
			die( json_encode( array( 'error' => sprintf( __( "Failed import: %s doesn't contain [flickr].", 'flickr-shortcode-importer' ), esc_html( $_REQUEST['id'] ) ) ) ) );
		
	   	$this->_process_shortcode( $post );

		die( json_encode( array( 'success' => sprintf( __( '&quot;<a href="%1$s" target="_blank">%2$s</a>&quot; Post ID %3$s was successfully processed in %4$s seconds.', 'flickr-shortcode-importer' ), get_permalink( $this->post_id ), esc_html( get_the_title( $this->post_id ) ), $this->post_id, timer_stop() ) ) ) );
	}


	function _process_shortcode( $post ) {
		// default is Flickr Shortcode Import API key
		$api_key				= fsi_get_options( 'flickr_api_key' );
		$secret					= fsi_get_options( 'flickr_api_secret' );
		$this->flickr			= new phpFlickr( $api_key, $secret );

		$this->licenses			= array();
		$licenses				= $this->flickr->photos_licenses_getInfo();
		foreach ( $licenses as $license ) {
			if ( fsi_get_options( 'debug_mode' ) ) {
				print_r($license); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			}
			$this->licenses[ $license['id'] ]	= array(
				'name'	=> $license['name'],
				'url'	=> $license['url']
			);
		}
		ksort( $this->licenses );
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($this->licenses); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		// only use our shortcode handlers to prevent messing up post content 
		remove_all_shortcodes();
		add_shortcode( 'flickr-gallery', array( &$this, 'shortcode_flickr_gallery' ) );
		add_shortcode( 'flickr', array( &$this, 'shortcode_flickr' ) );
		add_shortcode( 'flickrset', array( &$this, 'shortcode_flickrset' ) );

		// Don't overwrite Featured Images
		$this->featured_id		= false;
		$this->first_image		= fsi_get_options( 'remove_first_flickr_shortcode' ) ? true : false;
		$this->menu_order		= 1;

		// process [flickr] codes in posts
		$post_content			= do_shortcode( $post->post_content );

		$post					= array(
			'ID'			=> $this->post_id,
			'post_content'	=> $post_content,
		);

		wp_update_post( $post );

		// allow overriding Featured Image
		if ( $this->featured_id
			&& fsi_get_options( 'set_featured_image' )
			&& ( ! has_post_thumbnail( $this->post_id ) || fsi_get_options( 'force_set_featured_image' ) ) ) {
			$updated			= update_post_meta( $this->post_id, "_thumbnail_id", $this->featured_id );
		}

		if ( fsi_get_options( 'force_reimport' ) ) {
			update_fsi_options( 'force_reimport', 0 );
		}
	}

	
	// process each [flickr] entry
	function shortcode_flickr( $args, $content = null ) {
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($args); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($content); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		if ( isset( $args['id'] ) ) {
			$this->flickr_id	= $args['id'];
		} else {
			if ( preg_match( '#/([0-9]+)/?$#', $content, $match ) ) {
				$this->flickr_id	= $match[1];
			} elseif ( preg_match( '#^([0-9]+)$#', $content, $match ) ) {
				$this->flickr_id	= $content;
			} else {
				return '';
			}

			if ( isset( $args['size'] ) )
				$args['thumbnail']	= $args['size'];

			// for [flickr-gallery] width denotes video
			if ( isset( $args['width'] ) && isset( $args['height'] ) )
				$args['thumbnail']	= 'video_player';

			if ( isset( $args['float'] ) )
				$args['align']	= $args['float'];

			if ( fsi_get_options( 'debug_mode' ) ) {
				print_r($this->flickr_id); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
				print_r($args); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			}
		}

		set_time_limit( 120 );

		$photo					= $this->flickr->photos_getInfo( $this->flickr_id );
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($photo); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		$photo					= $photo['photo'];

		if ( isset( $args['set_title'] ) ) {
			$photo['set_title']	= $args['set_title'];
		} else {
			$contexts			= $this->flickr->photos_getAllContexts( $this->flickr_id );
			$photo['set_title']	= isset( $contexts['set'][0]['title'] ) ? $contexts['set'][0]['title'] : '';
		}
		
		$markup					= $this->process_flickr_media( $photo, $args );
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($markup); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		return $markup;
	}

	
	// process each [flickr-gallery] entry from plugin flickr-gallery
	function shortcode_flickr_gallery( $args ) {
		// attributes for passing to flickr directly
		$attr					= $args;
		unset( $attr['mode'] );

		if ( ! isset( $attr[ 'user_id' ] ) ) {
			// fg-user_id of flickr-gallery plugin
			$attr[ 'user_id' ]	= get_option('fg-user_id');
			// developer's user id (comprock)
			// $attr[ 'user_id' ]	= get_option('fg-user_id', '90901451@N00');
		}

		if ( ! isset( $attr[ 'per_page' ] ) ) {
			$attr[ 'per_page' ]	= get_option('fg-per_page');
		}

		switch ( $args['mode'] ) {
			case 'photoset':
				$this->flickset_id	= $args['photoset'];
				$info				= $this->flickr->photosets_getInfo( $this->flickset_id );
				$args['set_title']	= $info['title'];

				$photos			= $this->flickr->photosets_getPhotos( $this->flickset_id );
				$photos			= $photos['photoset']['photo'];
				break;

			case 'recent':
			case 'tag':
				$photos			= $this->flickr->photos_search( $attr );
				$photos			= $photos['photo'];
				break;

			case 'interesting':
				$attr['sort']	= 'interestingness-desc';
				$photos			= $this->flickr->photos_search( $attr );
				$photos			= $photos['photo'];
				break;

			case 'search':
				unset( $attr[ 'user_id' ] );
				$photos			= $this->flickr->photos_search( $attr );
				$photos			= $photos['photo'];
				break;

			default:
				break;
		}

		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($photos); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		set_time_limit( 120 * count( $photos ) );
		
		foreach( $photos as $entry ) {
			$args['id']			= $entry['id'];
			$this->shortcode_flickr( $args );
		}
		
		$markup					= '[gallery]';
		$this->flickset_id		= false;

		return $markup;
	}

	
	// process each [flickrset] entry
	function shortcode_flickrset( $args ) {
		$this->flickset_id		= $args['id'];
		$import_limit			= ( $args['photos'] ) ? $args['photos'] : -1;

		$info					= $this->flickr->photosets_getInfo( $this->flickset_id );
		$args['set_title']		= $info['title'];

		$photos					= $this->flickr->photosets_getPhotos( $this->flickset_id );
		$photos					= $photos['photoset']['photo'];

		// increased because [flickrset] might have lots of photos
		set_time_limit( 120 * count( $photos ) );
		
		foreach( $photos as $entry ) {
			$args['id']			= $entry['id'];
			$this->shortcode_flickr( $args );

			if ( 0 == --$import_limit )
				break;
		}
		
		$markup					= '[gallery]';
		$this->flickset_id		= false;

		return $markup;
	}


	function process_flickr_media( $photo, $args = false ) {
		$markup					= '';

		if ( 'photo' == $photo['media'] ) {
			$markup				= $this->render_photo($photo, $args);
		} elseif ( $photo['media'] == 'video' && in_array( $args['thumbnail'], array('video_player','site_mp4') ) ) {
			$mode				= ($args['thumbnail'] == 'site_mp4') ? 'html5': 'flash';
			$video_id			= $this->import_flickr_media($photo, $mode);
			$markup				= $this->RenderVideo($this->flickr_id, $mode);
		}

		return $markup;
	}

	
	function render_photo( $photo, $args = false ) {
		// add image to media library
		$image_id				= $this->import_flickr_media( $photo );
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($image_id); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}


		// if first image, set as featured 
		if ( ! $this->featured_id ) {
			$this->featured_id	= $image_id;
		}

		// no args, means nothing further to do
		if ( false === $args )
			return $markup;

		// wrap in link to attachment itself
		$size					= isset( $args['thumbnail'] ) ? $args['thumbnail'] : '';
		$size					= $this->get_shortcode_size( $size );
		$link_to_attach_page	= fsi_get_options( 'link_image_to_attach_page' ) ? true : false;
		$image_link				= wp_get_attachment_link( $image_id, $size, $link_to_attach_page );
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($size); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			var_dump($link_to_attach_page); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($image_link); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		// correct class per args
		$align					= isset( $args['align'] ) ? $args['align'] : fsi_get_options( 'default_image_alignment', 'left' );
		$align					= ' align' . $align;
		$wp_image				= ' wp-image-' . $image_id;
		$image_link				= preg_replace( '#(class="[^"]+)"#', '\1'
			. $align
			. $wp_image
			. '"', $image_link );

		$class					= fsi_get_options( 'default_a_tag_class' );
		if ( $class ) {
			$image_link			= preg_replace(
				'#(<a )#',
				'\1class="' . $class . '" ',
				$image_link );
		}

		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($image_link); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		if ( ! $this->first_image ) {
			// remaining [flickr] converted to locally reference image
			$markup				= $image_link;

			if ( fsi_get_options( 'flickr_image_attribution' ) ) {
				$wrap_class			= fsi_get_options( 'flickr_image_attribution_wrap_class' );
				if ( $wrap_class ) {
					$markup			.= '<span class="'. $wrap_class . '">';
				}

				$attribution_text	= fsi_get_options( 'flickr_image_attribution_text', __( 'Photo by ' , 'flickr-shortcode-importer') );
				$markup			.= $attribution_text;

				$attribution_link	= '<a href="' . $photo['urls']['url'][0]['_content'];
				$username			= $photo['owner']['username'];
				$attribution_link	.= '">' . $username . '</a>';

				$markup			.= $attribution_link;

				if ( $wrap_class ) {
					$markup		.= '</span>';
				}
			}

			$image_wrap_class	= fsi_get_options( 'image_wrap_class' );
			if ( $image_wrap_class ) {
				$markup			= '<span class="'. $image_wrap_class . '">' . $markup . '</span>';
			}
		} else {
			// remove [flickr] from post
			$this->first_image	= false;
		}

		return $markup;
	}


	/*
	From...
		Plugin Name: Flickr Manager
		Plugin URI: http://tgardner.net/wordpress-flickr-manager/
		Version: 3.0.1
		Author: Trent Gardner
	*/
	function RenderVideo($vid, $type = 'flash', $sizes = null) {
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($vid); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($type); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($sizes); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		// TODO import media

		if(is_null($sizes)) {
			$sizes = $this->flickr->photos_getSizes($vid);
		}
		
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($sizes); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		if($type == 'html5') {
			
			$video = array();
			foreach($sizes as $v) {
				if($v['label'] == 'Site MP4') {
					$video = $v;
					break;
				}
			}
			
			return sprintf('<video width="%s" height="%s" controls><source src="%s" type="video/mp4">%s</video>'
							, $video['width']
							, $video['height']
							, $this->video_source
							, $this->RenderVideo($vid, 'flash', $sizes));
			
		} else {
		
			$video = array();
			foreach($sizes as $v) {
				if($v['label'] == 'Video Player') {
					$video = $v;
					break;
				}
			}
			
			return sprintf('<object width="%s" height="%s" data="%s" type="application/x-shockwave-flash" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000">
	<param name="flashvars" value="flickr_show_info_box=false"></param>
	<param name="movie" value="%s"></param>
	<param name="allowFullScreen" value="true"></param>
	<embed type="application/x-shockwave-flash" flashvars="flickr_show_info_box=false" src="%s" allowfullscreen="true" height="%s" width="%s"></embed>
</object>', $video['width'], $video['height'], $this->video_source, $this->video_source, $this->video_source, $video['height'], $video['width']);
								
		}
	}


	// correct none thumbnail, medium, large or full size values
	function get_shortcode_size( $size_name = '' ) {
		$find_size				= '#(square|thumbnail|small|medium|large|original|full)#i';
		preg_match_all( $find_size, $size_name, $size_matches );
		// get the last size mentioned since that has precedence
		$size_name				= ( isset( $size_matches[0] ) ) ? array_pop( $size_matches[0] ) : '';

		switch ( strtolower( $size_name ) ) {
			case 'square':
			case 'thumbnail':
			case 'small':
				$size			= 'thumbnail';
				break;

			case 'medium':
			case 'medium_640':
				$size			= 'medium';
				break;

			case 'large':
				$size			= 'large';
				break;

			case 'original':
			case 'full':
				$size			= 'full';
				break;

			default:
				$size			= fsi_get_options( 'default_image_size', 'medium' );
				break;
		}

		return $size;
	}
	

	function import_flickr_media( $photo, $mode = true ) {
		global $wpdb;

		$photo_id				= $photo['id'];
		$set_title				= isset( $photo['set_title'] ) ? $photo['set_title'] : '';
		$title					= $photo['title'];

		if ( fsi_get_options( 'make_nice_image_title' ) ) {
			// if title is a filename, use set_title - menu order instead
			if ( ( preg_match( '#\.[a-zA-Z]{3}$#', $title ) 
			   	|| preg_match( '#^DSCF\d+#', $title ) )
				&& ! empty( $set_title ) ) {
				$title			= $set_title . ' - ' . $this->menu_order;
			} elseif ( ! preg_match( '#\s#', $title ) ) {
				$title			= $this->cbMkReadableStr( $title );
			}
		}

		$alt					= $title;
		$caption				= fsi_get_options( 'set_caption' ) ? $title : '';
		$desc					= html_entity_decode( $photo['description'] );
		$date					= $photo['dates']['taken'];

		$sizes					= $this->flickr->photos_getSizes( $photo_id );
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($sizes); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		$src					= false;

		if ( true === $mode ) {
			$image_import_size	= fsi_get_options( 'image_import_size', 'Large' );
			// check that requested image size exists & grab source url
			// array is in smallest to largest image size ordering
			foreach ( $sizes as $size ) {
				if ( 'photo' == $size['media'] ) {
					$src		= $size['source'];
					if ( $image_import_size == $size['label'] ) {
						break;
					}
				}
			}
			if ( fsi_get_options( 'debug_mode' ) ) {
				print_r($image_import_size); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			}

			// Flickr saves images as jpg
			$ext				= '.jpg';

			if ( ! empty( $title ) && fsi_get_options( 'replace_file_name' ) ) {
				$file				= preg_replace( '#[^\w]#', '-', $title );
				$file				= preg_replace( '#-{2,}#', '-', $file );
				$file				.= $ext;
			} else {
				$file				= basename( $src );
				$file				= str_replace( '?zz=1', '', $file );
			}
		} else {
			if ( fsi_get_options( 'skip_videos' ) ) {
				// TODO can video import from Flickr be made to work?
				$this->video_source	= $src;
				return null;
			}

			reset( $sizes );
			foreach( $sizes as $v ) {
				if ( 'html5' == $mode && $v['label'] == 'Site MP4' ) {
					$video		= $v;
					$ext		= '.mp4';
					break;
				} elseif ( 'flash' == $mode && $v['label'] == 'Video Player' ) {
					$video		= $v;
					$ext		= '.swf';
					break;
				}
				// TODO what about unknown here?
			}

			$src				= $video['source'];

			$file				= preg_replace( '#[^\w]#', '-', $title );
			$file				= preg_replace( '#-{2,}#', '-', $file );
			$file				.= $ext;
		}
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($src); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($file); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		// see if src is duplicate, if so return image_id
		$query					= "
			SELECT m.post_id
			FROM $wpdb->postmeta m
			WHERE 1 = 1
				AND m.meta_key LIKE '_flickr_photo_id'
				AND m.meta_value LIKE '$photo_id'
		";
		$dup					= $wpdb->get_var( $query );

		if ( $dup && fsi_get_options( 'force_reimport' ) ) {
			print_r('force_reimport'); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($dup); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($src); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			// delete prior import
			wp_delete_attachment( $dup, true );
			$dup				= false;
		}

		if ( $dup ) {
			if ( true !== $mode ) {
				$this->video_source	= wp_get_attachment_url( $dup );

				if ( fsi_get_options( 'debug_mode' ) ) {
					print_r($this->video_source); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
				}

				return $dup;
			}

			// ignore dup if importing [flickrset]
			if( ! $this->flickset_id ) {
				return $dup;
			} else {
				// use local source to speed up transfer
				$src			= wp_get_attachment_url( $dup );
			}
		}

		if ( fsi_get_options( 'flickr_link_in_desc' ) ) {
			$desc				.= "\n" . fsi_get_options( 'flickr_link_text', __( 'Photo by ' , 'flickr-shortcode-importer') );
			$link				= '<a href="' . $photo['urls']['url'][0]['_content'];

			if( $this->flickset_id ) {
				$link			.= 'in/set-' . $this->flickset_id . '/';
			}

			$username			= $photo['owner']['username'];
			$link				.= '">' . $username . '</a>';
			$desc				.= $link;
		}

		if ( fsi_get_options( 'flickr_image_license' ) ) {
			// append license info
			// <photo id="2733" secret="123456" server="12" isfavorite="0" license="3"
			// no license All rights reserved, any license Some rights reserved
			$license			= $photo['license'];
			$desc				.= "\n" . fsi_get_options( 'flickr_image_license_text', __( 'License ' , 'flickr-shortcode-importer') );
			if ( $license ) {
				$link				= '<a href="' . $this->licenses[$license]['url'];
				$link				.= '" title="' . $this->licenses[$license]['name'];

				$link				.= '">' . $this->licenses[$license]['name'] . '</a>';
			} else {
				$link				= $this->licenses[$license]['name'];
			}
			$desc				.= $link;
		}

		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($desc); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		$file_move				= wp_upload_bits( $file, null, $this->file_get_contents_curl( $src ) );
		$filename				= $file_move['file'];

		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($file_move); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($filename); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		if ( ! $filename ) {
			$this->die_json_error_msg( $this->post_id, sprintf( __( 'Source file not found: %s', 'flickr-shortcode-importer' ), $src ) );
		}

		$wp_filetype			= wp_check_filetype( $file, null );
		$attachment				= array(
			'menu_order'		=> $this->menu_order++,
			'post_content'		=> $desc,
			'post_date'			=> $date,
			'post_excerpt'		=> $caption,
			'post_mime_type'	=> $wp_filetype['type'],
			'post_status'		=> 'inherit',
			'post_title'		=> $title,
		);
		if ( fsi_get_options( 'debug_mode' ) ) {
			print_r($wp_filetype); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			print_r($attachment); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
		}

		// relate image to post
		$image_id				= wp_insert_attachment( $attachment, $filename, $this->post_id );
		if ( true !== $mode ) {
			$this->video_source	= wp_get_attachment_url( $image_id );

			if ( fsi_get_options( 'debug_mode' ) ) {
				print_r($this->video_source); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			}
		}

		if ( ! $image_id )
			$this->die_json_error_msg( $this->post_id, sprintf( __( 'The originally uploaded image file cannot be found at %s', 'flickr-shortcode-importer' ), esc_html( $filename ) ) );

		// help keep track of what's been imported already
		update_post_meta( $image_id, '_flickr_photo_id', $photo_id );

		if ( true === $mode ) {
			$metadata			= wp_generate_attachment_metadata( $image_id, $filename );
			if ( fsi_get_options( 'debug_mode' ) ) {
				print_r($metadata); echo '<br />'; echo '' . __LINE__ . ':' . basename( __FILE__ )  . '<br />';	
			}

			if ( is_wp_error( $metadata ) )
				$this->die_json_error_msg( $this->post_id, $metadata->get_error_message() );

			if ( empty( $metadata ) )
				$this->die_json_error_msg( $this->post_id, __( 'Unknown failure reason.', 'flickr-shortcode-importer' ) );

			// If this fails, then it just means that nothing was changed (old value == new value)
			wp_update_attachment_metadata( $image_id, $metadata );
			update_post_meta( $image_id, '_wp_attachment_image_alt', $alt );
		}

		return $image_id;
	}


	// Thank you Tobylewis
	// http://wordpress.org/support/topic/plugin-flickr-shortcode-importer-file_get_contents-with-url-isp-does-not-support?replies=2#post-2878241
	function file_get_contents_curl( $url ) {
		$ch						= curl_init();

		curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );

		$data					= curl_exec( $ch );
		curl_close( $ch );

		return $data;
	}


	// Helper to make a JSON error message
	function die_json_error_msg( $id, $message ) {
		die( json_encode( array( 'error' => sprintf( __( '&quot;%1$s&quot; Post ID %2$s failed to be processed. The error message was: %3$s', 'flickr-shortcode-importer' ), esc_html( get_the_title( $id ) ), $id, $message ) ) ) );
	}


	// Helper function to escape quotes in strings for use in Javascript
	function esc_quotes( $string ) {
		return str_replace( '"', '\"', $string );
	}


	/**
	 * Returns string of a filename or string converted to a spaced extension
	 * less header type string.
	 *
	 * @author Michael Cannon <michael@typo3vagabond.com>
	 * @param string filename or arbitrary text
	 * @return mixed string/boolean
	 */
	function cbMkReadableStr($str) {
		if ( is_numeric( $str ) ) {
			return number_format( $str );
		}

		if ( is_string($str) )
		{
			$clean_str = htmlspecialchars($str);

			// remove file extension
			$clean_str = preg_replace('/\.[[:alnum:]]+$/i', '', $clean_str);

			// remove funky characters
			$clean_str = preg_replace('/[^[:print:]]/', '_', $clean_str);

			// Convert camelcase to underscore
			$clean_str = preg_replace('/([[:alpha:]][a-z]+)/', "$1_", $clean_str);

			// try to cactch N.N or the like
			$clean_str = preg_replace('/([[:digit:]\.\-]+)/', "$1_", $clean_str);

			// change underscore or underscore-hyphen to become space
			$clean_str = preg_replace('/(_-|_)/', ' ', $clean_str);

			// remove extra spaces
			$clean_str = preg_replace('/ +/', ' ', $clean_str);

			// convert stand alone s to 's
			$clean_str = preg_replace('/ s /', "'s ", $clean_str);

			// remove beg/end spaces
			$clean_str = trim($clean_str);

			// capitalize
			$clean_str = ucwords($clean_str);

			// restore previous entities facing &amp; issues
			$clean_str = preg_replace( '/(&amp ;)([a-z0-9]+) ;/i'
				, '&\2;'
				, $clean_str
			);

			return $clean_str;
		}

		return false;
	}
}


// Start up this plugin
function Flickr_Shortcode_Importer() {
	global $Flickr_Shortcode_Importer;
	$Flickr_Shortcode_Importer	= new Flickr_Shortcode_Importer();
}

add_action( 'init', 'Flickr_Shortcode_Importer' );


function fsi_save_post( $post_id ) {
	global $Flickr_Shortcode_Importer;

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
		return;

	if ( ! is_numeric( $post_id ) )
		return;

	// check that post is wanting the flickr codes imported
	if ( ! wp_verify_nonce( $_POST['flickr-shortcode-importer'], 'flickr_import' ) )
		return;

	// save checkbox or not
	$checked					= ( isset( $_POST['flickr_import'] ) && ! empty( $_POST['flickr_import'] ) ) ? 1 : 0;
	update_post_meta( $post_id, 'process_flickr_shortcode', $checked );

	if ( ! $checked )
		return;

	remove_action( 'save_post', 'fsi_save_post', 99 );

	$Flickr_Shortcode_Importer->process_flickr_shortcode( $post_id );
	
	add_action( 'save_post', 'fsi_save_post', 99 );
}

add_action( 'save_post', 'fsi_save_post', 99 );

?>