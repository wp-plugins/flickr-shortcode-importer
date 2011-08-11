=== Flickr Shortcode Importer ===
Contributors: comprock
Donate link: http://peimic.com/about-peimic/donate/
Tags: flickr,featured image,import,media library,
Requires at least: 3.0.0
Tested up to: 3.2.1
Stable tag: 1.0.0

Import [flickr] shortcodes into the Media Library.

== Description ==
!!! DON'T USE YET !!!

!!! NOT READY FOR RELEASE !!!

Import [flickr] shortcodes into the Media Library. The first [flickr] image found in post content is set as the post's Featured Image and removed from the post content. The remaining [flickr] shortcodes are then transitioned to like sized locally referenced images.

Handy for transitioning from plugin `wordpress-flickr-manager` to own Media Library.

Optional to remove first [flickr] from post content that's been used as Featured Image.

Handled shortcode samples
* [flickr id="5348222727" thumbnail="small" overlay="false" size="large" group="" align="none"]
* [flickrset id="72157626986038277" thumbnail="small" photos="" overlay="true" size="large"]

Initial code is modeled after Viper007Bond's class based Regenerate Thumbnails plugin.

== Installation ==
1. Upload the `flickr-shortcode-importer` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Tools submenu, click on Flickr Shortcode Importer

== Frequently Asked Questions ==
= Can I sponsor changes? =
Yes. Any sponsoring would be greatly welcome. Please [donate](http://peimic.com/about-peimic/donate/ "Help sponsor Flickr Shortcode Importer") and let me know what's wanted

== Screenshots ==
1. Where to find Flickr Shortcode Importer in Tools
2. Flickr Shortcode Importer settings
3. Flickr Shortcode Importer progress

== Changelog ==
= 1.0.0 =
* Initial release

== Upgrade Notice ==
* None
