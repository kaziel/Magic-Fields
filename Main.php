<?php
/*
Plugin Name: Magic-fields
Plugin URI: http://magicfields.org
Description: Create custom write panels and easily retrieve their values in your templates.
Author: Hunk and Gnuget
Version: 1.3
Author URI: http://magicfields.org
*/

/**
 * This work is free software; you can redistribute it and/or 
 * modify it under the terms of the GNU General Public License 
 * as published by the Free Software Foundation; either version 
 * 2 of the License, or any later version.
 *
 * This work is distributed in the hope that it will be useful, 
 * but without any warranty; without even the implied warranty 
 * of merchantability or fitness for a particular purpose. See 
 * Version 2 and version 3 of the GNU General Public License for
 * more details. You should have received a copy of the GNU General 
 * Public License along with this program; if not, write to the 
 * Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, 
 * Boston, MA 02110-1301 USA
 */


// Globals
global $wpdb,$post,$current_user,$FIELD_TYPES,$current_user,$flag,$is_wordpress_mu;


if(isset($current_blog)){
	$is_wordpress_mu=true;
}else{
	$is_wordpress_mu=false;
}

//for the save_post action doesn't be execute  twice
$flag = 0;


// Getting the  Custom field object
require_once 'PanelFields.php';

// Getting the RCCWP_CustomGroup object for work with groups
require_once 'RCCWP_CustomGroup.php';

// Getting the constants
require_once 'RCCWP_Constant.php';

// Getting the RCCWP_CustomField object for work with  Custom Fields
require_once 'RCCWP_CustomField.php';

// Getting the RCCWP_CustomWritePanel for work with Writepanels
require_once 'RCCWP_CustomWritePanel.php';

// Include files containing Magic Fields public functions
require_once 'get-custom.php';

// Include other files used in this script

//Include files for put  the  write panels in the menu
require_once 'RCCWP_Menu.php';

require_once 'RCCWP_CreateCustomFieldPage.php';

//Debug tool
require_once 'tools/debug.php';

 /**
  * function for languages
  */
global $mf_domain;
$mf_domain = 'magic_fields';	
load_plugin_textdomain($mf_domain, '/'.PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/lang', basename(dirname(__FILE__)).'/lang');

		

/**
 *  Here actions/hooks only required in the Admin area
 */
if (is_admin()) {
	require_once ('RCCWP_Application.php');
	require_once ('RCCWP_WritePostPage.php');
	
	register_activation_hook(dirname(__FILE__) . '/Main.php', array('RCCWP_Application', 'Install'));
	
	//Attaching the Magic Fields Menus
	add_action('admin_menu', array('RCCWP_Menu', 'AttachMagicFieldsMenus'));

	if($is_wordpress_mu) {
		//checking if the method Install was executed before
		//if exists the option called "mf_custom_write_panel" 
		//is because Magic Fields was already installed
		$option = get_option('mf_custom_write_panel');
		
		if(!$option){
			RCCWP_Application::Install();
			add_action('admin_menu', array('RCCWP_Application', 'ContinueInstallation'));
		}
	}

	if (get_option(RC_CWP_OPTION_KEY) !== false) {
		require_once ('RCCWP_Processor.php');
		add_action('init', array('RCCWP_Processor', 'Main'));
		

		add_action('admin_menu', array('RCCWP_Menu', 'AttachCustomWritePanelMenuItems'));
		add_action('admin_menu', array('RCCWP_Menu', 'DetachWpWritePanelMenuItems'));
		add_action('admin_menu', array('RCCWP_Menu', 'AttachOptionsMenuItem'));
		
		add_filter('posts_where', array('RCCWP_Menu', 'FilterPostsPagesList'));
		add_action('admin_head', array('RCCWP_Menu', 'HighlightCustomPanel'));
		
		add_action('admin_head', 'mf_admin_style');
	


		// -- Hook all functions related to saving posts in order to save custom fields values
		require_once ('RCCWP_Post.php');	
		add_action('save_post', array('RCCWP_Post', 'SaveCustomFields'));
 		add_action('delete_post', array('RCCWP_Post','DeletePostMetaData')) ;
		

		
		add_filter('wp_redirect', array('RCCWP_Processor', 'Redirect'));

		add_action('shutdown', array('RCCWP_Processor', 'FlushAllOutputBuffer'));

		add_action('admin_notices', array('RCCWP_Application', 'CheckInstallation'));  
		add_action('admin_notices', array('RCCWP_WritePostPage', 'FormError'));
		
	}
}

require_once ('RCCWP_EditnPlace.php');
require_once ('RCCWP_Options.php');

// Adding javascript for the editnplace only if it is turned on
$customWritePanelOptions = RCCWP_Options::Get();
if( $customWritePanelOptions['enable-editnplace'] ) {
	add_action('wp_head', array('RCCWP_EditnPlace', 'EditnHeader'));
	add_action('template_redirect',array('RCCWP_EditnPlace','EditnPlaceJavascript'));
}

require_once ('RCCWP_Query.php');
add_action('pre_get_posts', array('RCCWP_Query', 'FilterPrepare'));
add_filter('posts_where', array('RCCWP_Query', 'FilterCustomPostsWhere'));
add_filter('posts_where', array('RCCWP_Query','ExcludeWritepanelsPosts'));
add_filter('posts_orderby', array('RCCWP_Query', 'FilterCustomPostsOrderby'));
add_filter('posts_fields', array('RCCWP_Query', 'FilterCustomPostsFields'));
add_filter('posts_join_paged', array('RCCWP_Query', 'FilterCustomPostsJoin'));

if($customWritePanelOptions['condense-menu']){
	//adding Column for posts
	add_filter('manage_posts_columns',array('RCCWP_Query','ColumnWritePanel'));
	add_action('manage_posts_custom_column',array('RCCWP_Query','ColumnWritePanelData'));
	
	//adding Column for pages
	add_filter('manage_pages_columns',array('RCCWP_Query','ColumnWritePanel'));
	add_action('manage_pages_custom_column',array('RCCWP_Query','ColumnWritePanelData'));
}



add_action('edit_page_form','cwp_add_pages_identifiers');
add_action('edit_form_advanced','cwp_add_type_identifier');

add_action('edit_form_advanced','put_write_panel_id');
add_action('edit_page_form','put_write_panel_id');
/**
 * put the id of the write panel as a hidden field in the 'create post/page' and 'edit post/page'
 */
function put_write_panel_id(){
	global $CUSTOM_WRITE_PANEL;
	
	if(!empty($CUSTOM_WRITE_PANEL->id)){
		echo "<input type='hidden' name='rc-custom-write-panel-verify-key' id='rc-custom-write-panel-verify-key' value='".wp_create_nonce('rc-custom-write-panel')."'/>";
		echo "<input type='hidden' name='rc-cwp-custom-write-panel-id' value='".$CUSTOM_WRITE_PANEL->id."'/>";
	}
}

function cwp_add_type_identifier(){

	global $wpdb;
	global $post;
	
	
	if( isset($_GET['custom-write-panel-id']) && !empty($_GET['custom-write-panel-id']))
	{
		$getPostID = $wpdb->get_results("SELECT id, type FROM ". MF_TABLE_PANELS ." WHERE id='".$_GET['custom-write-panel-id']."'");
		echo "<input type=\"hidden\" id=\"post_type\" name=\"post_type\" value=\"". $getPostID[0]->type ."\" />";

	}
	else{
		if($post->post_type == 'page') { 
			echo "<input type=\"hidden\" id=\"post_type\" name=\"post_type\" value=\"page\" />";
 		} else {
			echo "<input type=\"hidden\" id=\"post_type\" name=\"post_type\" value=\"post\" />";
 		}

 	}
}

function cwp_add_pages_identifiers(){
	global $post;
	global $wpdb;

	$key = wp_create_nonce('rc-custom-write-panel');
	$id = "";
	$result = $wpdb->get_results( " SELECT meta_value
					FROM $wpdb->postmeta
					WHERE post_id = '$post->ID' and meta_key = '_mf_write_panel_id'", ARRAY_A );
	
	if (count($result) > 0)
		$id = $result[0]['meta_value'];
	echo 
<<<EOF
		<input type="hidden" name="rc-custom-write-panel-verify-key" id="rc-custom-write-panel-verify-key" value="$key" />
		
EOF;
}


if ( !function_exists('sys_get_temp_dir')) {
  function sys_get_temp_dir() {
	if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
	if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
	if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
	$tempfile=tempnam(uniqid(rand(),TRUE),'');
	if (file_exists($tempfile)) {
		unlink($tempfile);
		return realpath(dirname($tempfile));
	}
  }
}

function mf_admin_style() {
	$url = MF_URI.'css/admin.css';
	echo '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
}

/**
*  Check the mime type of the file for 
*  avoid upload any dangerous file.
*  
*  @param string $mime is the type of file can be "image","audio" or "file"
*  @param string $file_type  is the mimetype of the field
*/
function valid_mime($mime,$file_type){
	$imagesExts = array(
						'image/gif',
						'image/jpeg',
						'image/pjpeg',
						'image/png',
						'image/x-png'
						);
	$audioExts = array(
						'audio/mpeg',
						'audio/mpg',
						'audio/x-wav'
						);
						
	if($file_type == "image"){
		if(in_array($mime,$imagesExts)){
			return true;
		}
	}elseif($file_type == "audio"){
		if(in_array($mime,$audioExts)){
			return true;
		}
	}else{
		//TODO: here users should be set what mime types
		//are safety for the "files" type of field
		return true;
	}
	return false;
}

/* Loading modules */

add_action( 'plugins_loaded', 'mf_load_modules', 1 );

function mf_load_modules() {
        $dir = WP_PLUGIN_DIR."/".MF_PLUGIN_DIR."/modules";

        if ( ! ( is_dir( $dir ) && $dh = opendir( $dir ) ) )
                return false;

        while ( ( $module = readdir( $dh ) ) !== false ) {
                if ( substr( $module, -4 ) == '.php' ) {
                        include_once $dir . '/' . $module;
                }
        }
}
