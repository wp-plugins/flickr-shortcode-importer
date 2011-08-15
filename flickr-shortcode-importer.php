<?php
/*
Plugin Name: Flickr Shortcode Importer
Plugin URI: http://wordpress.org/extend/plugins/flickr-shortcode-importer/
Description: Imports [flickr] & [flickrset] shortcode images into the Media Library.
Version: 1.3.4
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

// Load dependencies
require_once( dirname(__FILE__) . '/lib/inc.flickr.php' );
require_once( dirname(__FILE__) . '/class.options.php' );


class Flickr_Shortcode_Importer {
	var $menu_id;
	var $in_flickset			= false;
	// Peimic.com API key
	var $api_key				= 'd7a73fed961744db01c498ca910a003d';
	var $secret					= '7d8a757b8bc2b50b';

	// Plugin initialization
	function Flickr_Shortcode_Importer() {
		if ( ! function_exists( 'admin_url' ) )
			return false;

		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's "localization" folder and name it "flickr-shortcode-importer-[value in wp-config].mo"
		load_plugin_textdomain( 'flickr-shortcode-importer', false, '/flickr-shortcode-importer/languages/' );

		add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueues' ) );
		add_action( 'wp_ajax_importflickrshortcode', array( &$this, 'ajax_process_shortcode' ) );
		add_filter( 'plugin_action_links', array( &$this, 'add_plugin_action_links' ), 10, 2 );
		
		$this->options_link		= '<a href="'.get_admin_url().'options-general.php?page=fsi-options">'.__('[flickr] Options', 'flickr-shortcode-importer').'</a>';
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


	// The user interface plus thumbnail regenerator
	function user_interface() {
		global $wpdb;
?>

<div id="message" class="updated fade" style="display:none"></div>

<div class="wrap fsiposts">
	<div class="icon32" id="icon-tools"></div>
	<h2><?php _e('Flickr Shortcode Importer', 'flickr-shortcode-importer'); ?></h2>

<?php
		// testing helper
		if ( $_REQUEST['importflickrshortcode'] ) {
			$this->ajax_process_shortcode();
			exit( __LINE__ . ':' . __FILE__ . " ERROR<br />\n" );
		}

		// If the button was clicked
		if ( ! empty( $_POST['flickr-shortcode-importer'] ) || ! empty( $_REQUEST['posts'] ) ) {
			// Capability check
			if ( !current_user_can( 'manage_options' ) )
				wp_die( __( 'Cheatin&#8217; uh?' , 'flickr-shortcode-importer') );

			// Form nonce check
			check_admin_referer( 'flickr-shortcode-importer' );

			// Create the list of image IDs
			if ( ! empty( $_REQUEST['posts'] ) ) {
				$posts			= array_map( 'intval', explode( ',', trim( $_REQUEST['posts'], ',' ) ) );
				$count			= count( $posts );
				$posts			= implode( ',', $posts );
			} else {
				// TODO figure out way to skip aggressive grabbing
				// look for posts containing A/IMG tags referencing Flickr
				$flickr_source_where = "";
				if ( fsi_options( 'import_flickr_sourced_tags' ) ) {
					$flickr_source_where = <<<EOD
						OR (
							post_content LIKE '%<a%href=%http://www.flickr.com/%><img%src=%http://farm%.static.flickr.com/%></a>%'
							OR post_content LIKE '%<img%src=%http://farm%.static.flickr.com/%>%'
						)
EOD;
				}

				// Directly querying the database is normally frowned upon, but all of the API functions will return the full post objects which will suck up lots of memory. This is best, just not as future proof.
				$query			= "
					SELECT ID
					FROM $wpdb->posts
					WHERE 1 = 1
					AND post_type = 'post'
					AND post_parent = 0
					AND (
						post_content LIKE '%[flickr %'
						OR post_content LIKE '%[flickrset %'
						$flickr_source_where
					)
					";

				$limit			= (int) fsi_options( 'limit' );
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
		$find_flickr_a_tag		= '#<a.*href=.*http://www.flickr.com/.*><img.*src=.*http://farm\d+.static.flickr.com/.*></a>#i';
		$a_tag_open				= '<a ';

		$post_content			= $this->convert_tag_to_flickr( $post_content, $a_tag_open, $find_flickr_a_tag );

		// cycle through standalone img
		$find_flickr_img_tag		= '#<img.*src=.*http://farm\d+.static.flickr.com/.*>#i';
		$img_tag_open			= '<img ';
		$post_content			= $this->convert_tag_to_flickr( $post_content, $img_tag_open, $find_flickr_img_tag, true );

		$update					= array(
			'ID'				=> $post->ID,
			'post_content'		=> $post_content,
		);

		wp_update_post( $update );
	}


	function convert_tag_to_flickr( $post_content, $tag_open, $find_tag, $img_only = false ) {
		$default_alignment		= fsi_options( 'default_image_alignment' );
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

		// TODO add estimated time remaining 

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
<?php wp_nonce_field('flickr-shortcode-importer') ?>

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


	// Process a single image ID (this is an AJAX handler)
	function ajax_process_shortcode() {
		@error_reporting( 0 ); // Don't break the JSON result

		header( 'Content-type: application/json' );

		$this->post_id			= (int) $_REQUEST['id'];
		$post					= get_post( $this->post_id );

		if ( fsi_options( 'import_flickr_sourced_tags' ) ) {
			$this->convert_flickr_sourced_tags( $post );
			$post				= get_post( $this->post_id );
		}

		if ( ! $post || 'post' != $post->post_type || ! stristr( $post->post_content, '[flickr' ) )
			die( json_encode( array( 'error' => sprintf( __( "Failed import: %s doesn't contain [flickr].", 'flickr-shortcode-importer' ), esc_html( $_REQUEST['id'] ) ) ) ) );

		if ( ! current_user_can( 'manage_options' ) )
			$this->die_json_error_msg( $this->post_id, __( "Your user account doesn't have permission to import images", 'flickr-shortcode-importer' ) );

		$this->flickr			= new phpFlickr($this->api_key, $this->secret);

		// only use our shortcode handlers to prevent messing up post content 
		remove_all_shortcodes();
		add_shortcode( 'flickr', array( &$this, 'shortcode_flickr' ) );
		add_shortcode( 'flickrset', array( &$this, 'shortcode_flickrset' ) );

		// Don't overwrite Featured Images
		$this->featured_id		= false;
		$this->first_image		= fsi_options( 'remove_first_flickr_shortcode' ) ? true : false;
		$this->menu_order		= 1;

		// TODO progress by post

		// process [flickr] codes in posts
		$post_content			= do_shortcode( $post->post_content );

		// allow overriding Featured Image
		if ( $this->featured_id
			&& fsi_options( 'set_featured_image' )
			&& ( ! has_post_thumbnail( $this->post_id ) || fsi_options( 'force_set_featured_image' ) ) ) {
			$updated			= update_post_meta( $this->post_id, "_thumbnail_id", $this->featured_id );
		}

		$post					= array(
			'ID'			=> $this->post_id,
			'post_content'	=> $post_content,
		);

		wp_update_post( $post );

		die( json_encode( array( 'success' => sprintf( __( '&quot;<a href="%1$s" target="_blank">%2$s</a>&quot; Post ID %3$s was successfully processed in %4$s seconds.', 'flickr-shortcode-importer' ), get_permalink( $this->post_id ), esc_html( get_the_title( $this->post_id ) ), $this->post_id, timer_stop() ) ) ) );
	}

	
	// process each [flickr] entry
	function shortcode_flickr( $args ) {
		set_time_limit( 120 );

		$photo					= $this->flickr->photos_getInfo( $args['id'] );
		$photo					= $photo['photo'];

		if ( isset( $args['set_title'] ) ) {
			$photo['set_title']	= $args['set_title'];
		} else {
			$contexts			= $this->flickr->photos_getAllContexts( $args['id'] );
			$photo['set_title']	= isset( $contexts['set'][0]['title'] ) ? $contexts['set'][0]['title'] : '';
		}
		
		$markup					= $this->process_flickr_media( $photo, $args );
		
		return $markup;
	}

	
	// process each [flickrset] entry
	function shortcode_flickrset( $args ) {
		$this->in_flickset		= true;
		$import_limit			= ( $args['photos'] ) ? $args['photos'] : -1;

		$info					= $this->flickr->photosets_getInfo( $args['id'] );
		$args['set_title']		= $info['title'];

		$photos					= $this->flickr->photosets_getPhotos( $args['id'] );
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
		$this->in_flickset		= false;

		return $markup;
	}


	function process_flickr_media( $photo, $args = false ) {
		$markup					= '';

		if ( 'photo' == $photo['media'] ) {
			// pull original Flickr image
			$src				= $this->flickr->buildPhotoURL( $photo, 'original' );
			// add image to media library
			$image_id			= $this->import_flickr_media( $src, $photo );

			// if first image, set as featured 
			if ( ! $this->featured_id ) {
				$this->featured_id	= $image_id;
			}

			// no args, means nothing further to do
			if ( false === $args )
				return $markup;

			// wrap in link to attachment itself
			$size				= $this->get_shortcode_size( $args['thumbnail'] );
			$image_link			= wp_get_attachment_link( $image_id, $size, true 	);

			// correct class per args
			$align				= $args['align'] ? $args['align'] : fsi_options( 'default_image_alignment' );
			$align				= ' align' . $align;
			$wp_image			= ' wp-image-' . $image_id;
			$image_link			= preg_replace( '#(class="[^"]+)"#', '\1'
				. $align
				. $wp_image
				. '"', $image_link );

			$class				= fsi_options( 'default_a_tag_class' );
			if ( $class ) {
				$image_link		= preg_replace(
					'#(<a )#',
					'\1class="' . $class . '" ',
					$image_link );
			}

			if ( ! $this->first_image ) {
				// remaining [flickr] converted to locally reference image
				$markup			= $image_link;
			} else {
				// remove [flickr] from post
				$this->first_image	= false;
			}
		} elseif ($photo['media'] == 'video' && in_array($args['thumbnail'], array('video_player','site_mp4'))) {
			// TODO import video
			$markup				= $this->RenderVideo($args['id'], ($args['thumbnail'] == 'site_mp4') ? 'html5': 'flash');
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
		if(empty($sizes)) {
			$sizes = $this->flickr->photos_getSizes($vid);
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
							, $video['source']
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
							</object>', $video['width'], $video['height'], $video['source'], $video['source']);
								
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
				$size			= fsi_options( 'default_image_size' );
				break;
		}

		return $size;
	}
	

	function import_flickr_media( $src, $photo ) {
		global $wpdb;

		$set_title			= isset( $photo['set_title'] ) ? $photo['set_title'] : '';
		$title				= $photo['title'];

		if ( fsi_options( 'make_nice_image_title' ) ) {
			// if title is a filename, use set_title - menu order instead
			if ( ( preg_match( '#\.[a-zA-Z]{3}$#', $title ) 
			   	|| preg_match( '#^DSCF\d+#', $title ) )
				&& ! empty( $set_title ) ) {
				$title			= $set_title . ' - ' . $this->menu_order;
			} elseif ( ! preg_match( '#\s#', $title ) ) {
				$title		= $this->cbMkReadableStr( $title );
			}
		}

		$alt				= $title;
		$caption			= fsi_options( 'set_caption' ) ? $title : '';
		$desc				= html_entity_decode( $photo['description'] );
		$date				= $photo['dates']['taken'];
		$file				= basename( $src );

		// see if src is duplicate, if so return image_id
		$query				= "
			SELECT m.post_id
			FROM $wpdb->postmeta m
			WHERE 1 = 1
				AND m.meta_key LIKE '_flickr_src'
				AND m.meta_value LIKE '$src'
		";
		$dup				= $wpdb->get_var( $query );

		if ( $dup ) {
			// ignore dup if importing [flickrset]
			if( ! $this->in_flickset ) {
				return $dup;
			} else {
				// use local source to speed up transfer
				$src		= wp_get_attachment_url( $dup );
			}
		}

		$file_move			= wp_upload_bits( $file, null, file_get_contents( $src ) );
		$filename			= $file_move['file'];

		$wp_filetype		= wp_check_filetype( $file, null );
		$attachment			= array(
			'menu_order'		=> $this->menu_order++,
			'post_content'		=> $desc,
			'post_date'			=> $date,
			'post_excerpt'		=> $caption,
			'post_mime_type'	=> $wp_filetype['type'],
			'post_status'		=> 'inherit',
			'post_title'		=> $title,
		);

		// relate image to post
		$image_id			= wp_insert_attachment( $attachment, $filename, $this->post_id );

		if ( ! $image_id )
			$this->die_json_error_msg( $this->post_id, sprintf( __( 'The originally uploaded image file cannot be found at %s', 'flickr-shortcode-importer' ), '<code>' . esc_html( $filename ) . '</code>' ) );

		$metadata				= wp_generate_attachment_metadata( $image_id, $filename );

		if ( is_wp_error( $metadata ) )
			$this->die_json_error_msg( $this->post_id, $metadata->get_error_message() );

		if ( empty( $metadata ) )
			$this->die_json_error_msg( $this->post_id, __( 'Unknown failure reason.', 'flickr-shortcode-importer' ) );

		// If this fails, then it just means that nothing was changed (old value == new value)
		wp_update_attachment_metadata( $image_id, $metadata );
		update_post_meta( $image_id, '_wp_attachment_image_alt', $alt );
		// help keep track of what's been imported already
		update_post_meta( $image_id, '_flickr_src', $src );

		return $image_id;
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
	 * @author Michael Cannon <michael@peimic.com>
	 * @param string filename or arbitrary text
	 * @return mixed string/boolean
	 */
	function cbMkReadableStr($str) {
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

?>