Index: flickr-shortcode-importer.php
===================================================================
--- flickr-shortcode-importer.php	(revision 421931)
+++ flickr-shortcode-importer.php	(working copy)
@@ -50,7 +50,7 @@
 			// $fsi_link			= '<a href="'.get_admin_url().'options-general.php?page=flickr-shortcode-importer/flickr-shortcode-importer.php">'.__('Settings').'</a>';
 			// array_unshift( $links, $fsi_link );
 
-			$fsi_link			= '<a href="'.get_admin_url().'tools.php?page=flickr-shortcode-importer">'.__('Import').'</a>';
+			$fsi_link			= '<a href="'.get_admin_url().'tools.php?page=flickr-shortcode-importer">'.__('Import', 'flickr-shortcode-importer').'</a>';
 			// make the 'Import' link appear first
 			array_unshift( $links, $fsi_link );
 		}
@@ -111,7 +111,7 @@
 		if ( ! empty( $_POST['flickr-shortcode-importer'] ) || ! empty( $_REQUEST['ids'] ) ) {
 			// Capability check
 			if ( !current_user_can( 'manage_options' ) )
-				wp_die( __( 'Cheatin&#8217; uh?' ) );
+				wp_die( __( 'Cheatin&#8217; uh?' , 'flickr-shortcode-importer') );
 
 			// Form nonce check
 			check_admin_referer( 'flickr-shortcode-importer' );
@@ -283,7 +283,7 @@
 
 	<p><?php _e( "Flickr shortcode import is not reversible. Backup your database beforehand.", 'flickr-shortcode-importer' ); ?></p>
 
-	<p><?php _e( 'To begin, just press the button below.', 'flickr-shortcode-importer '); ?></p>
+	<p><?php _e( 'To begin, just press the button below.', 'flickr-shortcode-importer ', 'flickr-shortcode-importer'); ?></p>
 
 	<p><input type="submit" class="button hide-if-no-js" name="flickr-shortcode-importer" id="flickr-shortcode-importer" value="<?php _e( 'Import Flickr Shortcode', 'flickr-shortcode-importer' ) ?>" /></p>
 
