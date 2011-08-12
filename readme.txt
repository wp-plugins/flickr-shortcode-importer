=== Flickr Shortcode Importer ===
Contributors: comprock
Donate link: http://peimic.com/about-peimic/donate/
Tags: flickr,featured image,import,media library,photo
Requires at least: 3.0.0
Tested up to: 3.2.1
Stable tag: 1.0.1

Imports [flickr] shortcode images into the Media Library.

== Description ==
Imports [flickr] shortcode images into the Media Library. Furthermore, it transforms the post content [flickr] shortcodes into links containing the Media Library based image of the proper size and alignment.

The first [flickr] image found in post content is set as the post's Featured Image and removed from the post content. The remaining [flickr] shortcodes are then transfromed as image links to their attachment page.

This plugin is handy for transitioning from plugin `wordpress-flickr-manager` to your own Media Library because you have CDN services or want to move off of third party software.

There is no restore functionality. Backup beforehand or be prepared to revert every transformed post by hand.

= Options =
* Set limit of posts to be processed
* Set first [flickr] image as featured image or not

= Handled shortcode samples =
* [flickr id="5348222727" thumbnail="small" overlay="false" size="large" group="" align="none"]

= Thank You =
* Initial code is modeled after Viper007Bond's class based Regenerate Thumbnails plugin. The AJAX status and single auto-submission operations were a big help.
* [flickr] shortcode handling code copied from Trent Gardner's very fine Flickr Manager plugin.
* Hat's off to Alison Barret for her Settings API tutorials and class My_Theme_Options.

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
4. Before Flickr Shortcode Importer
5. After Flickr Shortcode Importer

== Changelog ==
= 1.0.1 =
* Replace duplicate lookup by guid with _flickr_src in postmeta
* RenderVideo via FlickrManager code
* Remove [flickr] lookup LIMIT

= 1.0.0 =
* Initial release for production use

= 0.1.0 =
* Initial release for production testing
* Prep for further production testing;
* Remove import LIMIT;
* Turn off duplicate handling due to being unable to process guid setting or recall;
* Remove unused helper files;
* Add screenshots;
* Set version 0.1.0;
* Correct featured image setting and first_image removal handling;
* Prevent duplicate [flickr] imports;
* Revise readme content;
* Handle featured image setting;
* Remove first [flickr] from post_content;
* Correct [flickr] replacement for attachment links
* Load phpFlickr;
* In test mode;
* Verbiage updates;
* Process shortcode via do_shortcode;
* Readme thank you
* Update method names for FSI;
* Remove unused methods;
* Begin post pulling with [flickr];
* Set 5-minute time limit per post;
* Update verbiage for FSI usage
* UL shortcode samples
* Update for coding standard;
* Disallow production use
* Set version 0.0.0
* Domain text;
* Add language
* Don't Use Text
* Add PHPFlickr library;
* Add FlickManager reference
* Update verbiage to Flickr Shortcode Importer;
* Ignore options for now

== Upgrade Notice ==
* None

== TODO ==
= Add options =
* Remove first [flickr] from post content
* a tag class like 'lightbox'

= Handle =
* Flickr video importing - currently rendering as video/object/embed tag using Flickr
* [flickrset id="72157626986038277" thumbnail="small" photos="" overlay="true" size="large"]
