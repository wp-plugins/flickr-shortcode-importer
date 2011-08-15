=== Flickr Shortcode Importer ===
Contributors: comprock
Donate link: http://peimic.com/about-peimic/donate/
Tags: flickr,featured image,import,media library,photo
Requires at least: 3.0.0
Tested up to: 3.2.1
Stable tag: 1.3.5

Imports [flickr] & [flickrset] shortcode images into the Media Library.

== Description ==
Imports [flickr] & [flickrset] shortcode images into the Media Library. Furthermore, it transforms the post content [flickr] shortcodes into links containing the Media Library based image of the proper size and alignment.

The first [flickr] image found in post content is set as the post's Featured Image and removed from the post content. The remaining [flickr] shortcodes are then transfromed as image links to their attachment page.  

[flickrset] shortcode is converted to [gallery] after the Flickr set images have been added to the Media Library. If 'Set Featured Image' is checked in Options, then the first image of the [flickrset] is used as such.

This plugin is handy for transitioning from plugin `wordpress-flickr-manager` to your own Media Library because you have CDN services or want to move off of third party software.

There is no restore functionality. Backup beforehand or be prepared to revert every transformed post by hand.

= Options =
* Default A Tag Class
* Default Image Alignment
* Default Image Size
* Import Flickr-based A/IMG tags
* Set Captions
* Set first [flickr] image as featured image or not
* Force Set Featured Image
* Remove first [flickr] from post content or not
* Make Nice Image Title?
* Set limit of posts to be processed

= Handled shortcode samples =
* [flickr id="5348222727" thumbnail="small" overlay="false" size="large" group="" align="none"]
* [flickrset id="72157626986038277" thumbnail="small" photos="" overlay="true" size="large"]

= Warnings =
* Backup your database before importing. You can use revision to revert individual posts, but doing so in mass is a major PITA.
* It's strongly recommended to deactivate plugins like WordSocial, WP Smush.it and similar to prevent extended import times. You can always enable them and run them enmasse later.

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
4. Before Flickr Shortcode Importer for [flickr]
5. After Flickr Shortcode Importer for [flickr]
6. Flickr Shortcode Importer Options
7. Before Flickr Shortcode Importer for [flickrset]
8. After Flickr Shortcode Importer for [flickrset]

== Changelog ==
= trunk =

= 1.3.5 =
* Rename ChangeLog to changelog.txt
* Convert Flickr sourced IMG to [flickr]

= 1.3.4 =
* Check for camera given photo title
* html_entity_decode photo description
* Update Options screenshot
* Put progress for a/img to shortcode conversion - moved convert_flickr_sourced_tags() into ajax_process_shortcode()
* Update readme verbiage

= 1.3.3 =
* Add option Default A Tag Class

= 1.3.2 =
* Properly call cbMkReadableStr

= 1.3.1 =
* Update plugin description

= 1.3.0 =
* Fix over zealous replacement of content for A/IMG tag to [flickr] conversion
* Use cbMkReadableStr to pretty print media filenames as a title as needed
* Update Options screenshot
* Replace preg_match_all with explode and preg_match to handle single line of many A/IMG tags
* Line break after shortcodes to ensure proper reading by WordPress
* Add option Default Image Alignment
* Add option Default Image Size
* Add warnings to readme
* Import Flickr-based A/IMG tags
* Set get_shortcode_size default to medium
* Add Estimated time required to import notice

= 1.2.0 =
* Add option Set Captions
* Add option Force Set Featured Image
* Add option Make Nice Image Title?
* Allow 2 minutes per photo import before timing out
* Add Flickr Shortcode Importer before & after screenshots for [flickrset]
* Import [flickrset] shortcode content

= 1.1.0 =
* Add Flickr Shortcode Importer Options screenshot
* Add option Remove First Flickr Shortcode
* Add option to setting Featured Image or not
* Add import page link to options page
* Add conversion limit option
* Polish up About Flickr Shortcode Importer section
* Add text domain
* Rename class.settings.php to class.options.php
* Add [flickr] Options link to [flickr] Import page
* Add icon to [flickr] Import page
* Flickr Shortcode Importer Options page added
* [flickr] Options linked from Plugins
* Update pot file
* Remove old settings file
* Use Alison Barret's class My_Theme_Options
* Add ob_settings.php options page helper for using Settings API
* TODO video import
* Backup or reversion reminders

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
* Make the Settings page link to the link list, and vice versa
* Add estimated time remaining notice
* Import Flickr video - currently rendering as video/object/embed tag using Flickr src
* Add option set A tag class attribute like 'lightbox'. Not sure if really needed
