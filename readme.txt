=== Flickr Shortcode Importer ===
Contributors: comprock
Donate link: http://peimic.com/about-peimic/donate/
Tags: flickr,featured image,import,media library,photo
Requires at least: 3.0.0
Tested up to: 3.2.1
Stable tag: 0.1.0

Imports [flickr] shortcode images into the Media Library.

== Description ==
Imports [flickr] shortcode images into the Media Library.

The first [flickr] image found in post content is set as the post's Featured Image and removed from the post content.

The remaining [flickr] shortcodes are then transfromed as image links to their attachment page. Image size and alignment properties are kept in the new link.

This plugin is handy for transitioning from plugin `wordpress-flickr-manager` to own Media Library because you have your own CDN services.

= Handled shortcode samples =
* [flickr id="5348222727" thumbnail="small" overlay="false" size="large" group="" align="none"]

= Thank You =
* Initial code is modeled after Viper007Bond's class based Regenerate Thumbnails plugin. The AJAX status and single auto-submission operations were a big help.
* [flickr] shortcode handling code copied from Trent Gardner's very fine Flickr Manager plugin.

== Installation ==
1. Upload the `flickr-shortcode-importer` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Tools submenu, click on Flickr Shortcode Importer

== Frequently Asked Questions ==
= Can I sponsor changes? =
Yes. Any sponsoring would be greatly welcome. Please [donate](http://peimic.com/about-peimic/donate/ "Help sponsor Flickr Shortcode Importer") and let me know what's wanted

== Screenshots ==
1. Flickr Shortcode Importer in Plugins
2. Flickr Shortcode Importer in Tools
3. Flickr Shortcode Importer progress

== Changelog ==
= 0.1.0 =
* Initial release for production testing

== Upgrade Notice ==
* None

== TODO ==
= Add options =
* Convert limit
* Set Featured Image
* Remove first [flickr] from post content
* a tag class like 'lightbox'

= Prevent =
* Duplicates - difficult to assign guid and recall for later use

= Handle [flickrset] =
* [flickrset id="72157626986038277" thumbnail="small" photos="" overlay="true" size="large"]
