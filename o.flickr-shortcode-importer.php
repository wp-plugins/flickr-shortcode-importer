<?php
/*
X	Plugin Name: Flickr Shortcode Importer
X	Plugin URI: http://wordpress.org/extend/plugins/flickr-shortcode-importer/
X	Description: Import [flickr] shortcodes into Media Library. First [flickr] image is set as the post's Featured Images. Handy for transitioning from plugin wordpress-flickr-manager to own Media Library.
X	Version: 1.0.0
X	Author: Michael Cannon
X	Author URI: http://peimic.com/contact-peimic/
X	License: GPL2
	
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

// ------------------------------------------------------------------------
// PLUGIN PREFIX:                                                          
// ------------------------------------------------------------------------
// A PREFIX IS USED TO AVOID CONFLICTS WITH EXISTING PLUGIN FUNCTION NAMES.
// WHEN CREATING A NEW PLUGIN, CHANGE THE PREFIX AND USE YOUR TEXT EDITORS 
// SEARCH/REPLACE FUNCTION TO RENAME THEM ALL QUICKLY.                     
// ------------------------------------------------------------------------

// 'fsi_' prefix is derived from [f]lickr [s]hortcode [i]mporter

// ------------------------------------------------------------------------
// REGISTER HOOKS & CALLBACK FUNCTIONS:                                    
// ------------------------------------------------------------------------
// HOOKS TO SETUP DEFAULT PLUGIN OPTIONS, HANDLE CLEAN-UP OF OPTIONS WHEN
// PLUGIN IS DEACTIVATED AND DELETED, INITIALISE PLUGIN, ADD OPTIONS PAGE.
// ------------------------------------------------------------------------

// Set-up Hooks
register_activation_hook(__FILE__, 'fsi_add_defaults');
register_uninstall_hook(__FILE__, 'fsi_delete_plugin_options');
add_action('admin_init', 'fsi_init' );
add_action('admin_menu', 'fsi_add_options_page');

// --------------------------------------------------------------------------------------
// CALLBACK FUNCTION FOR: register_uninstall_hook(__FILE__, 'fsi_delete_plugin_options')
// --------------------------------------------------------------------------------------
// THIS FUNCTION RUNS WHEN THE USER DEACTIVATES AND DELETES THE PLUGIN. IT SIMPLY DELETES
// THE PLUGIN OPTIONS DB ENTRY (WHICH IS AN ARRAY STORING ALL THE PLUGIN OPTIONS).
// --------------------------------------------------------------------------------------

// Delete options table entries ONLY when plugin deactivated AND deleted
function fsi_delete_plugin_options() {
	delete_option('fsi_options');
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION FOR: register_activation_hook(__FILE__, 'fsi_add_defaults')
// ------------------------------------------------------------------------------
// THIS FUNCTION RUNS WHEN THE PLUGIN IS ACTIVATED. IF THERE ARE NO THEME OPTIONS
// CURRENTLY SET, OR THE USER HAS SELECTED THE CHECKBOX TO RESET OPTIONS TO THEIR
// DEFAULTS THEN THE OPTIONS ARE SET/RESET.
//
// OTHERWISE, THE PLUGIN OPTIONS REMAIN UNCHANGED.
// ------------------------------------------------------------------------------

// Define default option settings
function fsi_add_defaults() {
	$tmp = get_option('fsi_options');
    if(($tmp['chk_default_options_db']=='1')||(!is_array($tmp))) {
		delete_option('fsi_options'); // so we don't have to reset all the 'off' checkboxes too! (don't think this is needed but leave for now)
		$arr = array(	"chk_button1" => "1",
						"chk_button3" => "1",
						"textarea_one" => esc_html( "This type of control allows a large amount of information to be entered all at once. Set the 'rows' and 'cols' attributes to set the width and height." ),
						"txt_one" => "Enter whatever you like here..",
						"drp_select_box" => "four",
						"chk_default_options_db" => "",
						"rdo_group_one" => "one",
						"rdo_group_two" => "two"
		);
		update_option('fsi_options', $arr);
	}
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION FOR: add_action('admin_init', 'fsi_init' )
// ------------------------------------------------------------------------------
// THIS FUNCTION RUNS WHEN THE 'admin_init' HOOK FIRES, AND REGISTERS YOUR PLUGIN
// SETTING WITH THE WORDPRESS SETTINGS API. YOU WON'T BE ABLE TO USE THE SETTINGS
// API UNTIL YOU DO.
// ------------------------------------------------------------------------------

// Init plugin options to white list our options
function fsi_init(){
	register_setting( 'fsi_plugin_options', 'fsi_options', 'fsi_validate_options' );
    load_plugin_textdomain( 'flickr-shortcode-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION FOR: add_action('admin_menu', 'fsi_add_options_page');
// ------------------------------------------------------------------------------
// THIS FUNCTION RUNS WHEN THE 'admin_menu' HOOK FIRES, AND ADDS A NEW OPTIONS
// PAGE FOR YOUR PLUGIN TO THE SETTINGS MENU.
// ------------------------------------------------------------------------------

// Add menu page
function fsi_add_options_page() {
	add_options_page('Flickr Shortcode Importer Options Page', 'Flickr Shortcode Importer', 'manage_options', __FILE__, 'fsi_render_form');
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION SPECIFIED IN: add_options_page()
// ------------------------------------------------------------------------------
// THIS FUNCTION IS SPECIFIED IN add_options_page() AS THE CALLBACK FUNCTION THAT
// ACTUALLY RENDER THE PLUGIN OPTIONS FORM AS A SUB-MENU UNDER THE EXISTING
// SETTINGS ADMIN MENU.
// ------------------------------------------------------------------------------

// Render the Plugin options form
function fsi_render_form() {
	?>
	<div class="wrap">
		
		<!-- Display Plugin Icon, Header, and Description -->
		<div class="icon32" id="icon-options-general"><br></div>
		<h2>Flickr Shortcode Importer</h2>
		<p>Below is a collection of sample controls you can use in your own Plugins. Or, you can analyse the code and learn how all the most common controls can be added to a Plugin options form. See the code for more details, it is fully commented.</p>

		<!-- Beginning of the Plugin Options Form -->
		<form method="post" action="options.php">
			<?php settings_fields('fsi_plugin_options'); ?>
			<?php $options = get_option('fsi_options'); ?>

			<!-- Table Structure Containing Form Controls -->
			<!-- Each Plugin Option Defined on a New Table Row -->
			<table class="form-table">

				<!-- Text Area Control -->
				<tr>
					<th scope="row">Sample Text Area</th>
					<td>
						<textarea name="fsi_options[textarea_one]" rows="7" cols="50" type='textarea'><?php echo $options['textarea_one']; ?></textarea><br /><span style="color:#666666;margin-left:2px;">Add a comment here to give extra information to Plugin users</span>
					</td>
				</tr>

				<!-- Textbox Control -->
				<tr>
					<th scope="row">Enter Some Information</th>
					<td>
						<input type="text" size="57" name="fsi_options[txt_one]" value="<?php echo $options['txt_one']; ?>" />
					</td>
				</tr>

				<!-- Radio Button Group -->
				<tr valign="top">
					<th scope="row">Radio Button Group #1</th>
					<td>
						<!-- First radio button -->
						<label><input name="fsi_options[rdo_group_one]" type="radio" value="one" <?php checked('one', $options['rdo_group_one']); ?> /> Radio Button #1 <span style="color:#666666;margin-left:32px;">[option specific comment could go here]</span></label><br />

						<!-- Second radio button -->
						<label><input name="fsi_options[rdo_group_one]" type="radio" value="two" <?php checked('two', $options['rdo_group_one']); ?> /> Radio Button #2 <span style="color:#666666;margin-left:32px;">[option specific comment could go here]</span></label><br /><span style="color:#666666;">General comment to explain more about this Plugin option.</span>
					</td>
				</tr>

				<!-- Checkbox Buttons -->
				<tr valign="top">
					<th scope="row">Group of Checkboxes</th>
					<td>
						<!-- First checkbox button -->
						<label><input name="fsi_options[chk_button1]" type="checkbox" value="1" <?php if (isset($options['chk_button1'])) { checked('1', $options['chk_button1']); } ?> /> Checkbox #1</label><br />

						<!-- Second checkbox button -->
						<label><input name="fsi_options[chk_button2]" type="checkbox" value="1" <?php if (isset($options['chk_button2'])) { checked('1', $options['chk_button2']); } ?> /> Checkbox #2 <em>(useful extra information can be added here)</em></label><br />

						<!-- Third checkbox button -->
						<label><input name="fsi_options[chk_button3]" type="checkbox" value="1" <?php if (isset($options['chk_button3'])) { checked('1', $options['chk_button3']); } ?> /> Checkbox #3 <em>(useful extra information can be added here)</em></label><br />

						<!-- Fourth checkbox button -->
						<label><input name="fsi_options[chk_button4]" type="checkbox" value="1" <?php if (isset($options['chk_button4'])) { checked('1', $options['chk_button4']); } ?> /> Checkbox #4 </label><br />

						<!-- Fifth checkbox button -->
						<label><input name="fsi_options[chk_button5]" type="checkbox" value="1" <?php if (isset($options['chk_button5'])) { checked('1', $options['chk_button5']); } ?> /> Checkbox #5 </label>
					</td>
				</tr>

				<!-- Another Radio Button Group -->
				<tr valign="top">
					<th scope="row">Radio Button Group #2</th>
					<td>
						<!-- First radio button -->
						<label><input name="fsi_options[rdo_group_two]" type="radio" value="one" <?php checked('one', $options['rdo_group_two']); ?> /> Radio Button #1</label><br />

						<!-- Second radio button -->
						<label><input name="fsi_options[rdo_group_two]" type="radio" value="two" <?php checked('two', $options['rdo_group_two']); ?> /> Radio Button #2</label><br />

						<!-- Third radio button -->
						<label><input name="fsi_options[rdo_group_two]" type="radio" value="three" <?php checked('three', $options['rdo_group_two']); ?> /> Radio Button #3</label>
					</td>
				</tr>

				<!-- Select Drop-Down Control -->
				<tr>
					<th scope="row">Sample Select Box</th>
					<td>
						<select name='fsi_options[drp_select_box]'>
							<option value='one' <?php selected('one', $options['drp_select_box']); ?>>One</option>
							<option value='two' <?php selected('two', $options['drp_select_box']); ?>>Two</option>
							<option value='three' <?php selected('three', $options['drp_select_box']); ?>>Three</option>
							<option value='four' <?php selected('four', $options['drp_select_box']); ?>>Four</option>
							<option value='five' <?php selected('five', $options['drp_select_box']); ?>>Five</option>
							<option value='six' <?php selected('six', $options['drp_select_box']); ?>>Six</option>
							<option value='seven' <?php selected('seven', $options['drp_select_box']); ?>>Seven</option>
							<option value='eight' <?php selected('eight', $options['drp_select_box']); ?>>Eight</option>
						</select>
						<span style="color:#666666;margin-left:2px;">Add a comment here to explain more about how to use the option above</span>
					</td>
				</tr>

				<tr><td colspan="2"><div style="margin-top:10px;"></div></td></tr>
				<tr valign="top" style="border-top:#dddddd 1px solid;">
					<th scope="row">Database Options</th>
					<td>
						<label><input name="fsi_options[chk_default_options_db]" type="checkbox" value="1" <?php if (isset($options['chk_default_options_db'])) { checked('1', $options['chk_default_options_db']); } ?> /> Restore defaults upon plugin deactivation/reactivation</label>
						<br /><span style="color:#666666;margin-left:2px;">Only check this if you want to reset plugin settings upon Plugin reactivation</span>
					</td>
				</tr>
			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>

		<p style="margin-top:15px;">
			<p style="font-style: italic;font-weight: bold;color: #26779a;">If you have found this starter kit at all useful, please consider making a <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=XKZXD2BHQ5UB2" target="_blank" style="color:#72a1c6;">donation</a>. Thanks.</p>
			<span><a href="http://www.facebook.com/PressCoders" title="Our Facebook page" target="_blank"><img style="border:1px #ccc solid;" src="<?php echo plugins_url(); ?>/wp-content-filter/images/facebook-icon.png" /></a></span>
			&nbsp;&nbsp;<span><a href="http://www.twitter.com/dgwyer" title="Follow on Twitter" target="_blank"><img style="border:1px #ccc solid;" src="<?php echo plugins_url(); ?>/wp-content-filter/images/twitter-icon.png" /></a></span>
			&nbsp;&nbsp;<span><a href="http://www.presscoders.com" title="PressCoders.com" target="_blank"><img style="border:1px #ccc solid;" src="<?php echo plugins_url(); ?>/wp-content-filter/images/pc-icon.png" /></a></span>
		</p>

	</div>
	<?php	
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function fsi_validate_options($input) {
	 // strip html from textboxes
	$input['textarea_one'] =  wp_filter_nohtml_kses($input['textarea_one']); // Sanitize textarea input (strip html tags, and escape characters)
	$input['txt_one'] =  wp_filter_nohtml_kses($input['txt_one']); // Sanitize textbox input (strip html tags, and escape characters)
	return $input;
}

add_filter( 'plugin_action_links', 'fsi_plugin_action_links', 10, 2 );
// Display a Settings link on the main Plugins page
function fsi_plugin_action_links( $links, $file ) {

	if ( $file == plugin_basename( __FILE__ ) ) {
		$fsi_links = '<a href="'.get_admin_url().'options-general.php?page=flickr-shortcode-importer/flickr-shortcode-importer.php">'.__('Settings').'</a>';
		// make the 'Settings' link appear first
		array_unshift( $links, $fsi_links );
	}

	return $links;
}

// ------------------------------------------------------------------------------
// SAMPLE USAGE FUNCTIONS:
// ------------------------------------------------------------------------------
// THE FOLLOWING FUNCTIONS SAMPLE USAGE OF THE PLUGINS OPTIONS DEFINED ABOVE. TRY
// CHANGING THE DROPDOWN SELECT BOX VALUE AND SAVING THE CHANGES. THEN REFRESH
// A PAGE ON YOUR SITE TO SEE THE UPDATED VALUE.
// ------------------------------------------------------------------------------

// As a demo let's add a paragraph of the select box value to the content output
add_filter( "the_content", "fsi_add_content" );
function fsi_add_content($text) {
	$options = get_option('fsi_options');
	$select = $options['drp_select_box'];
	$text = "<p style=\"color: #777;border:1px dashed #999; padding: 6px;\">Select box Plugin option is: {$select}</p>{$text}";
	return $text;
}
