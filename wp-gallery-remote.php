<?php
/*
  Plugin Name: WP-Gallery-Remote
  Plugin URI: http://blog.thebartels.de/projects/wp-gallery-remote/
  Description: Allows to integrate images from any Gallery installation (http://gallery.sf.net) into your posts/pages.
  Version: 1.5.1
  License: GPL
  Author: Christian Bartels
  Author URI: http://blog.thebartels.de/

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
  
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
*/

require_once("lib/libcurlemu/libcurlemu.inc.php");

$wpgr = new wp_gallery_remote(true);

class wp_gallery_remote {
  private $wpgr_version = 89;
  public $options = array();
  public $album_list = array();
  public $images = null;
  public $text_domain = 'wp-gallery-remote';
  private $debug = array();
  private $pluginpath;
  private $callback_passes = 0;
  
  /**
   * constructor
   * registers all wordpress hooks and gets options 
   */
  public function __construct($is_plugin = true) {
    if (is_plugin) {
      register_activation_hook(plugin_basename(__FILE__), array(&$this, 'activate'));
      register_deactivation_hook(plugin_basename(__FILE__), array(&$this, 'deactivate'));
  
      add_filter('the_content', array(&$this, 'callback'));
      add_action('admin_menu', array(&$this, 'admin_menu'));
      add_action('wp_head', array(&$this, 'header'));
      add_action('wp_footer', array(&$this, 'footer'));
      add_action('admin_head', array(&$this, 'admin_header'));
      add_action('init', array(&$this, 'addbuttons'));
      add_action('edit_form_advanced', array(&$this, 'wpgrOpenJS'));
      add_action('edit_page_form', array(&$this, 'wpgrOpenJS'));
    }
    
    load_plugin_textdomain($this->text_domain, 'wp-content/plugins/wp-gallery-remote/lang');
    
    $this->get_options();
    $this->pluginpath = get_settings('siteurl').'/wp-content/plugins/wp-gallery-remote/';
    
    // check whether upgrade is necessary
	if ($this->wpgr_version != $this->options['wpgrversion']) {
	  // delete caches
	  $this->delete_all_caches();
	  
	  // conversion from single to mutiple gallery support (wpgr <= 1.1)
	  if (!isset($this->options['galleries'])) {
	    $gallery_url = (isset($this->options['galleryurl'])) ? $this->options['galleryurl'] : '';
	    $thumbsize = (isset($this->options['thumbsize'])) ? $this->options['thumbsize'] : 90;
	    $usecaching = (isset($this->options['usecaching'])) ? $this->options['usecaching'] : true;
	    $cachetimeoutalbums = (isset($this->options['cachetimeoutalbums'])) ? $this->options['cachetimeoutalbums'] : 0;
	    $cachetimeoutimages = (isset($this->options['cachetimeoutimages'])) ? $this->options['cachetimeoutimages'] : 0;
		$this->options['galleries'] = array(array('name' => 'Default', 
		                         'url' => $gallery_url,
		                         'thumbsize' => $thumbsize,
		                         'outputtype' => 'plain',
		                         'usecaching' => $usecaching,
		                         'cachetimeoutalbums' => $cachetimeoutalbums,
		                         'cachetimeoutimages' => $cachetimeoutimages));
	    $this->options['carousel_needed'] = true;
		unset($this->options['galleryurl']);
        unset($this->options['thumbsize']);
        unset($this->options['usecaching']);
        unset($this->options['cachetimeoutalbums']);
        unset($this->options['cachetimeoutimages']);
	  }
      
	  // save new wpgr version and save changed options
	  $this->options['wpgrversion'] = $this->wpgr_version;
	  update_option('wp_gallery_remote_options', $this->options);
	}
	
	// load carousel JS, if we are in plugin mode, not in feed more and carousel is needed :)
	// wp_enqueue_script call does not work in wp_head; that's why it is here
	if (is_plugin && !is_feed() && !is_admin() && $this->options['carousel_needed']) { 
	  if (function_exists('wp_enqueue_script')) {
		wp_enqueue_script('wpgr_carousel', $this->pluginpath . 'js/carousel.js', array('prototype', 'scriptaculous-effects'), $this->wpgr_version);
	  }
	}
	
	// load tooltip script for admin page
	if ($_REQUEST['page'] == 'wp-gallery-remote.php') {
	  if (function_exists('wp_enqueue_script')) {
	    wp_enqueue_script('wpgr_tooltip', $this->pluginpath . 'js/tooltip.js', array('prototype'), $this->wpgr_version);
	  }
	}
  }
  
  /**
   * activate
   * Is called on plugin activation.
   * 
   * @param
   * @return
   */
  public function activate() {
    // give administrator new cap: wpgr_debug
    global $wp_roles;
    $wp_roles->add_cap('administrator','wpgr_debug');
  }
  
  /**
   * deactivate
   * Is called on plugin deactivation.
   * 
   * @param
   * @return
   */
  public function deactivate() {
    // delete caches
    $this->delete_all_caches();
    
    // delete wpgr_debug capability
    global $wp_roles;
    $wp_roles->remove_cap('administrator','wpgr_debug');
  }
  
  /**
   * delete_all_caches
   * deletes all album and image caches from options table
   */
  private function delete_all_caches() {
    // wpgr <= 1.1
    delete_option('wp_gallery_remote_albums');
    delete_option('wp_gallery_remote_images');
    
    // wpgr > 1.1
	if (is_array($this->options['galleries'])) {
	    foreach ($this->options['galleries'] as $id => $gallery) {
	      delete_option('wp_gallery_remote_albums' . $id);
	      delete_option('wp_gallery_remote_images' . $id);
	    }
	}
  }
  
  /**
   * callback
   * Is called for each page/post content.
   * 
   * @param string $content post/page content
   */
  public function callback($content) {
    $this->callback_passes = 0;
    // search $content for wp-gallery-remote tag => {wp-gallery-remote:param1=val1;param2=val2;param3=3,3;}
    return preg_replace_callback('/\{wp-gallery-remote:[\s;]*(([a-z]+[a-z0-9]*[\s]*=[\s]*[a-z0-9,|:\s]*[\s]*;[\s;]*)*)\}/i', array(&$this, parse_wpgr_tags_callback), $content);
  }

  /**
   * preg_replace_callback
   * callback function for preg_replace_callback
   */
  public function parse_wpgr_tags_callback($matches) {
    // reload options on each callback as we modify them with tag specific parameters
    $this->get_options();
    
    // count number of callback calls - this is used to generate unique ids for images
    $this->callback_passes++;
    
    // convert parameters into array
    $param_pairs = explode(';', $matches[1]);
    $params = array();
    foreach ($param_pairs as $pair) {
      list($key, $value) = explode('=', $pair);
      $params[trim($key)] = trim($value);
    }

    // select gallery
	if (array_key_exists('gallery', $params)) {
	  $this->options['gallery'] = $params['gallery'];
	} else {
	  $this->options['gallery'] = 0;
	}
    
    // generate WP-Gallery-Remote output
    $output = '<div id="wp-gallery-remote">';
    if ($this->options['galleries'][$this->options['gallery']]['url'] == '') {
      $output .= __('<b>WP-Gallery-Remote Error:</b> WP-Gallery-Remote is not configured. Please go to WP-Admin -> Options -> WP-Gallery-Remote and configure WP-Gallery-Remote.', $this->text_domain);
    } else {
      // set post-specific parameters
      if (array_key_exists('rootalbum', $params)) { $this->options['rootalbum'] = $params['rootalbum']; } else { $this->options['rootalbum'] = 0; }
      if (array_key_exists('showalbumtitle', $params)) { $this->options['showalbumtitle'] = ($params['showalbumtitle'] == 'true') ? true : false; }
      if (array_key_exists('showsubalbums', $params)) { $this->options['showsubalbums'] = ($params['showsubalbums'] == 'true') ? true : false; }
      if (array_key_exists('showimagesheader', $params)) { $this->options['showimagesheader'] = ($params['showimagesheader'] == 'true') ? true : false; }
      if (array_key_exists('thumbsize', $params)) { $this->options['galleries'][$this->options['gallery']]['thumbsize'] = $params['thumbsize']; }
      if (array_key_exists('nocaching', $params)) { $this->options['galleries'][$this->options['gallery']]['nocaching'] = ($params['nocaching'] == 'true') ? true : false; } else { $this->options['galleries'][$this->options['gallery']]['nocaching'] = false; }
      if (array_key_exists('divstyle', $params)) { $this->options['divstyle'] = str_replace('|', ';', $params['divstyle']); } else { $this->options['divstyle'] = $this->options['galleries'][$this->options['gallery']]['divstyle']; }
      if (array_key_exists('clickalbumtitle', $params)) { $this->options['clickalbumtitle'] = $params['clickalbumtitle']; }
      if (array_key_exists('clickimage', $params)) { $this->options['galleries'][$this->options['gallery']]['clickimage'] = $params['clickimage']; }
      
      $image_filter = array();
      if (array_key_exists('imagefilter', $params)) {
        list($image_filter['type'], $image_filter['filter']) = explode(':', $params['imagefilter']);        
        $image_filter['filter'] = explode(',', $image_filter['filter']);
      }
      
      // generate output
      try {
        $this->debug_add($this->options['rootalbum'], array('content_gen_start' => microtime(true)));
        $this->fetch_albums($this->options['rootalbum'], false);
        $this->debug_add($this->options['rootalbum'], array('content_gen_album_fetch' => microtime(true)));

        $output .= $this->get_album_content($image_filter);
      } catch (Exception $e) {
        $output = __('<b>WP-Gallery-Remote Error:</b> Could not retrieve data from Gallery. Inform the administrator to review the WP-Gallery-Remote options and to check the Gallery installation.', $this->text_domain);
      }
    }
    
    $output .= '<div class="clear"></div></div>';
  
    $this->debug_add($this->options['rootalbum'], array('content_gen_end' => microtime(true)));
    
    // replace wp-gallery-remote tag with generated output
	return $output;
  }
  
  /**
   * admin_menu
   * adds WP-Gallery-Remote option page to wordpress admin menu
   * 
   * @param
   * @return 
   */
  public function admin_menu() {
    if (function_exists('add_options_page')) {
      add_options_page(__('WP-Gallery-Remote Options Page', $this->text_domain), __('WP-Gallery-Remote', $this->text_domain), 8, basename(__FILE__), array(&$this, 'admin'));
    }
  }
  
  /**
   * admin_header
   * outputs css and js includes for wpgr admin page
   */
  public function admin_header() { 
    // we do not use wp_enqueue_script() here, as the JS has no dependencies
    if ($_REQUEST['page'] == 'wp-gallery-remote.php') { ?>
      <script type="text/javascript" src="<? echo $this->pluginpath ?>js/wpgr-admin.js?ver=<?php echo $this->wpgr_version ?>"></script>
      <link rel="stylesheet" type="text/css" media="screen" href="<?php echo $this->pluginpath ?>css/wpgr-admin.css" /><?php
    }
  }
  
  /**
   * admin
   * outputs WP-Gallery-Remote admin page and handles update action (saving options, clearing caches)
   * 
   * @param 
   * @return
   */
  public function admin() {
    // gallery deletion
    if (isset($_POST['wpgr_gallery_delete']) && isset($_POST['wpgr_galleries'])) {
      // check whether gallery exists
      if (array_key_exists($_POST['wpgr_galleries'], $this->options['galleries'])) {
        $name = $this->options['galleries'][$_POST['wpgr_galleries']]['name'];
        unset($this->options['galleries'][$_POST['wpgr_galleries']]);
      
        $_POST['wpgr_galleries'] = 0;;
        update_option('wp_gallery_remote_options', $this->options);
        $msg = sprintf(__('Gallery "%s" has been deleted.', $this->text_domain), $name);
      } else {
        $msg = sprintf(__('Gallery "%s" has been deleted.', $this->text_domain), $_POST['wpgr_name']);
        $_POST['wpgr_galleries'] = 0;
      } ?>
      <div class="updated fade"><p><?php echo $msg; ?></p></div> <?php
    }
    
    // create new gallery, if no other galleries exist
    if (!is_array($this->options['galleries']) || empty($this->options['galleries'])) {
      $this->options['galleries'] = array(array('name' => 'Default', 
				                                'url' => '',
				                                'clickimage' => 'lightbox',
				                                'thumbsize' => 90,
				                                'outputtype' => 'carousel',
				                                'usecaching' => true,
				                                'cachetimeoutalbums' => 0,
				                                'cachetimeoutimages' => 0));
    }

    if (!isset($_POST['wpgr_galleries'])) { $_POST['wpgr_galleries'] = 0; }
    
    // create new gallery
    if (isset($_POST['wpgr_gallery_add'])) {
      $new_gallery = array('name' => 'New Gallery', 
                           'url' => '',
                           'clickimage' => 'lightbox',
                           'thumbsize' => 90,
                           'outputtype' => 'carousel',
                           'usecaching' => true,
                           'cachetimeoutalbums' => 0,
                           'cachetimeoutimages' => 0);
      array_push($this->options['galleries'], $new_gallery);
      $_POST['wpgr_galleries'] = count($this->options['galleries']) - 1; ?>
      <script type="text/javascript">
      	wpgr_gallery_changed = true;
      </script>
      <div class="updated fade"><p><?php _e('Please enter at least a name and the URL of the Gallery and press the "Update Gallery Options" button.', $this->text_domain) ?></p></div> <?php
    }
    
    $gallery = $this->options['galleries'][$_POST['wpgr_galleries']];

    // save general options
    if (isset($_POST['wpgr_update_general'])) {
      ($_POST['wpgr_showalbumtitle'] == true) ? $this->options['showalbumtitle'] = true : $this->options['showalbumtitle'] = false;
      ($_POST['wpgr_showsubalbums'] == true) ? $this->options['showsubalbums'] = true : $this->options['showsubalbums'] = false;
      ($_POST['wpgr_showimagesheader'] == true) ? $this->options['showimagesheader'] = true : $this->options['showimagesheader'] = false;
      ($_POST['wpgr_showdebugwindow'] == true) ? $this->options['showdebugwindow'] = true : $this->options['showdebugwindow'] = false;
      ($_POST['wpgr_rssmode'] == true) ? $this->options['rssmode'] = true : $this->options['showdebugwindow'] = false;
      if (array_key_exists('wpgr_clickalbumtitle', $_POST)) {
        $this->options['clickalbumtitle'] = $_POST['wpgr_clickalbumtitle'];
      }
      
      update_option('wp_gallery_remote_options', $this->options); ?>
      <div class="updated fade"><p><?php _e('General options have been saved.', $this->text_domain) ?></p></div> <?php
    }
    
    // save album settings
    if (isset($_POST['wpgr_update_gallery']) && isset($_POST['wpgr_galleries'])) {
      // check, if entered data is correct; otherwise abort
	  $abort = false;
	  $msg = '';
	  $gallery_url_new = strip_tags(stripslashes($_POST['wpgr_url']));
	  
	  if (empty($_POST['wpgr_name'])) { 
	    $msg.= __('Please enter a name for the Gallery.<br />', $this->text_domain);
	    $abort = true;
	  }
	  
	  if (empty($gallery_url_new)) {
	    $msg.= __('Please enter the URL for the Gallery.<br />', $this->text_domain);
	    $abort = true;
	  }
	  
	  if (!preg_match('/[0..9]+/', $_POST['wpgr_thumbsize'])) {
	    $msg.= __('Only numerical values allowed for thumbnail size.<br />', $this->text_domain);
	    $abort = true;
	  }
	  
	  if ($_POST['wpgr_clickimage'] != 'lightbox' && $_POST['wpgr_clickimage'] != 'newwindowplain' && $_POST['wpgr_clickimage'] != 'newwindowgallery') {
	    $msg.= __('Please choose one of the available options for clicking on an image.', $this->text_domain);
	    $abort = true;
	  }
	  
	  if ($_POST['wpgr_outputtype'] != 'carousel' && $_POST['wpgr_outputtype'] != 'plain') {
	    $msg.= __('Please choose one of the available output types.<br />', $this->text_domain);
	    $abort = true;
	  }
	  
	  if (!preg_match('/[0..9]+/', $_POST['wpgr_cachetimeoutalbums'])) {
	    $msg.= __('Only numerical values allowed for timeout of album cache.<br />', $this->text_domain);
	    $abort = true;
	  }
	  
	  if (!preg_match('/[0..9]+/', $_POST['wpgr_cachetimeoutimages'])) {
	    $msg.= __('Only numerical values allowed for timeout of album image cache.<br />', $this->text_domain);
	    $abort = true; 
	  }
	  
	  $type = 'error';
	  
	  // only save, if value checks were successful
	  if (!$abort) {
	    $type = 'updated fade';
	    
	    if (is_array($this->options['galleries'][$_POST['wpgr_galleries']])) {
	      $gallery = $this->options['galleries'][$_POST['wpgr_galleries']];
	    } else {
	      $gallery = array();
	    }

        $gallery['name'] = $_POST['wpgr_name'];
      
        // if gallery url was changed: save new url + delete chached album/image information
        if ($gallery_url_new != $gallery['url']) {
          $gallery['url'] = $gallery_url_new;
          $delete_caches = true;
        }
      
        $gallery['clickimage'] = $_POST['wpgr_clickimage'];
        $gallery['thumbsize'] = $_POST['wpgr_thumbsize'];
        $gallery['outputtype'] = $_POST['wpgr_outputtype'];
        $gallery['divstyle'] = $_POST['wpgr_divstyle'];
      
        if ($_POST['wpgr_usecaching'] == true) {
          $gallery['usecaching'] = true;
        } else {
          $gallery['usecaching'] = false;
          $delete_caches = true;
        }
        $gallery['cachetimeoutalbums'] = $_POST['wpgr_cachetimeoutalbums'];
        $gallery['cachetimeoutimages'] = $_POST['wpgr_cachetimeoutimages'];
      
        if ($delete_caches == true) {
          delete_option('wp_gallery_remote_albums' . $_POST['wpgr_galleries']);
          delete_option('wp_gallery_remote_images' . $_POST['wpgr_galleries']);
        }
      
        // do we have to load prototype, spect.aculo.us and carousel javascript libs?
	    $carousel_needed = false;
	    foreach ($this->options['galleries'] as $g) {
	      if ($g['outputtype'] == 'carousel') {
	        $this->options['carousel_needed'] = true;
	        break;
	      }
	    }

	    if (is_array($this->options['galleries'][$_POST['wpgr_galleries']])) {
          $this->options['galleries'][$_POST['wpgr_galleries']] = $gallery;
	    } else {
	      array_push($this->options['galleries'], $gallery);
	    }
        
        update_option('wp_gallery_remote_options', $this->options); 
        $msg = sprintf(__('Settings for Gallery "%s" updated.', $this->text_domain), $gallery['name']);
      } ?>
      <div class="<?php echo $type; ?>"><p><?php echo $msg; ?></p></div> <?php
    }
    
    // clear album cache
    if (isset($_POST['wp_gallery_remote_clear_albumcache'])) {
      delete_option('wp_gallery_remote_albums' . $_POST['wpgr_galleries']); ?>
      <div class="updated fade"><p><?php printf(__('Album cache of Gallery "%s" cleared.', $this->text_domain), $gallery['name']) ?></p></div> <?php
    }
    
    // clear image cache
    if (isset($_POST['wp_gallery_remote_clear_imagecache'])) {
      delete_option('wp_gallery_remote_images' . $_POST['wpgr_galleries']); ?>
      <div class="updated fade"><p><?php printf(__('Image cache of Gallery "%s" cleared.', $this->text_domain), $gallery['name']) ?></p></div> <?php
    }
    
    // rebuild caches of currently selected gallery
	if (isset($_POST['wp_gallery_remote_rebuild_caches'])) {
	  try {
	    $this->options['gallery'] = $_POST['wpgr_galleries'];
	    $this->fetch_albums(0, true);
	    foreach ($this->album_list[$this->options['gallery']]['album']['name'] as $id) {
	      $this->fetch_images($id, true);
	    }
	    $msg = sprintf(__('Caches of Gallery "%s" have been rebuilt.', $this->text_domain), $gallery['name']);
	    $type = 'updated fade';
	  } catch (Exception $e) {
	    $msg = sprintf(__('A connection problem ocurred while retrieving data from Gallery "%s".', $this->text_domain), $gallery['name']);
	    $type = 'error';
	  } ?>
      <div class="<?php echo $type ?>"><p><?php echo $msg ?></p></div> <?php
	}
	
	// test Gallery Access
	if (isset($_POST['wpgr_gallery_url_check'])) {
	  $this->options['gallery'] = $_POST['wpgr_galleries'];
	  try {
	    $this->fetch_albums(0, true);
	    $msg = sprintf(__('Connection to Gallery "%s" successful.', $this->text_domain), $this->options['galleries'][$_POST['wpgr_galleries']]['name']);
	    $type = 'updated fade';
	  } catch (Exception $e) {
	    $msg = sprintf(__('Connection to Gallery "%s" failed!', $this->text_domain), $this->options['galleries'][$_POST['wpgr_galleries']]['name']);
	    $type = 'error';
	  } ?>
	  <div class="<?php echo $type ?>"><p><?php echo $msg; ?></p></div><?php
	}
  ?>
  	<div class="wrap">
  		<form name="wpgr_general_options" method="post">
  		<h2>General Options</h2>
		<table class="wpgr_general_options_table">
			<tr>
				<td><input name="wpgr_showdebugwindow" type="checkbox" id="wpgr_showdebugwindow" value="true" <?php if ($this->options['showdebugwindow']) { echo 'checked'; } ?> /></td>
				<td><?php _e('Show Debug Window', $this->text_domain) ?> <span id="wpgr_tpa_debug_window"><img src="<? echo $this->pluginpath ?>img/info.png" width="16" height="16" alt="info"></span>
					<div id="wpgr_tp_debug_window" style="display:none; margin:5px; padding:5px; background-color:#cccccc; width:300px; height:70px;"><?php _e('The debug window is an overlay box on the upper left corner and is only visible for users, who have admin authorization.', $this->text_domain) ?></div>
				</td>
			</tr>
			<tr>
				<td><input name="wpgr_rssmode" type="checkbox" id="wpgr_rssmode" value="true" <?php if ($this->options['rssmode']) { echo 'checked'; } ?> /></td>
				<td><?php _e('Link to plain images in RSS feed', $this->text_domain) ?> <span id="wpgr_tpa_rssmode"><img src="<? echo $this->pluginpath ?>img/info.png" width="16" height="16" alt="info"></span>
					<div id="wpgr_tp_rssmode" style="display:none; margin:5px; padding:5px; background-color:#cccccc; width:350px; height:80px;"><?php _e('By default, clicking on a thumbnail in your blog\'s RSS feed opens the respective post in a new window and automatically triggers the lightbox effect for the clicked image. If you want, that the thumbnails are linked to the plain image, activate this option.', $this->text_domain) ?></div>
				</td>
			</tr>
 			<tr>
				<td><input name="wpgr_showalbumtitle" type="checkbox" id="wpgr_showalbumtitle" value="true" <?php if ($this->options['showalbumtitle']) { echo 'checked'; } ?> /></td>
				<td><?php _e('Show Album Title', $this->text_domain) ?></td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td>
					<table border="0">
						<td><?php _e('Click on Album Title', $this->text_domain) ?></td>
						<td>
							<select name="wpgr_clickalbumtitle" id="wpgr_clickalbumtitle">
								<option value="none"<?php if ($this->options['clickalbumtitle'] == 'none') { echo ' selected'; } ?>><?php _e('does nothing', $this->text_domain) ?></option>
								<option value="opensame"<?php if ($this->options['clickalbumtitle'] == 'opensame') { echo ' selected'; } ?>><?php _e('opens Gallery album page in same window', $this->text_domain) ?></option>
								<option value="opennew"<?php if ($this->options['clickalbumtitle'] == 'opennew') { echo ' selected'; } ?>><?php _e('opens Gallery album page in new window', $this->text_domain) ?></option>
							</select>
					</table>
				</td>
			</tr>
 			<tr>
				<td><input name="wpgr_showsubalbums" type="checkbox" id="wpgr_showsubalbums" value="true" <?php if ($this->options['showsubalbums']) { echo 'checked'; } ?> /></td>
				<td><?php _e('Show Subalbums', $this->text_domain) ?></td>
			</tr>
			<tr>
				<td><input name="wpgr_showimagesheader" type="checkbox" id="wpgr_showimagesheader" value="true" <?php if ($this->options['showimagesheader']) { echo 'checked'; } ?> /></td>
				<td><?php _e('Show Images Header', $this->text_domain) ?></td>
			</tr>
		</table>
		<div class="submit">
			<input type="submit" name="wpgr_update_general" value="<?php _e('Save General Options &raquo;', $this->text_domain) ?>" />
		</div>
		</form>				
  		<h2><?php _e('Galleries', $this->text_domain) ?></h2>
  		<form name="wpgr_gallery_options" method="post" onsubmit="">
		<div>
			<?php _e('Available Galleries:', $this->text_domain) ?>
			<select name="wpgr_galleries" id="wpgr_galleries" onchange="javascript:document.forms[1].submit();"><?php
                $galleries = $this->options['galleries'];
			    foreach ($galleries as $id => $gallery) { ?>
			      <option value="<?php echo $id ?>"<?php if ($id == $_POST['wpgr_galleries']) { echo ' selected'; } ?>><?php echo $gallery['name'] ?></option><?php
			    } ?>
			</select>
			<input type="submit" name="wpgr_gallery_add" value="<?php _e('Add', $this->text_domain) ?>" onclick="return check_changed();" />&nbsp;
			<input type="submit" name="wpgr_gallery_delete" value="<?php _e('Delete', $this->text_domain) ?>" />
		</div><?php
		$gallery = $galleries[$_POST['wpgr_galleries']];
		?>
		<h3><?php printf(__('Settings for Gallery "%s"', $this->text_domain), $gallery['name']) ?></h3>
		<table class="wpgr_album_options_table">
			<tr>
				<td style="width:120px;"><?php _e('Gallery Name', $this->text_domain) ?></td>
				<td><input name="wpgr_name" type="text" id="wpgr_name" onchange="set_changed()" value="<?php echo attribute_escape($gallery['name']); ?>" size="30" /></td>
			</tr>
			<tr>
				<td style="width:120px;"><?php _e('Gallery URL', $this->text_domain) ?> <span id="wpgr_tpa_gallery_url"><img src="<? echo $this->pluginpath ?>img/info.png" width="16" height="16" alt="info"></span>
					<div id="wpgr_tp_gallery_url" style="display:none; margin:5px; padding:5px; background-color:#cccccc; width:300px; height:80px;"><?php _e('Please enter the URL to \'main.php\' of your Gallery installation.<p>Example:<br />http://www.example.com/gallery2/main.php', $this->text_domain) ?></div>
				</td>
				<td><input name="wpgr_url" type="text" id="wpgr_url" onchange="set_changed()" value="<?php echo attribute_escape($gallery['url']); ?>" size="80" />&nbsp;<input type="submit" name="wpgr_gallery_url_check" value="Test Connection" onclick="return check_changed();" /></td>
			</tr>
			<tr>
				<td style="width:120px;"><?php _e('Click on Image', $this->text_domain) ?></td>
				<td>
					<select name="wpgr_clickimage" id="wpgr_clickimage" onchange="set_changed();">
 						<option value="lightbox"<?php if ($gallery['clickimage'] == 'lightbox') { echo ' selected'; } ?>><?php _e('opens image using Lightbox effect', $this->text_domain) ?></option>
 						<option value="newwindowplain"<?php if ($gallery['clickimage'] == 'newwindowplain') { echo ' selected'; } ?>><?php _e('opens image in new window', $this->text_domain) ?></option>
 						<option value="newwindowgallery"<?php if ($gallery['clickimage'] == 'newwindowgallery') { echo ' selected'; } ?>><?php _e('opens image\'s Gallery page in new window', $this->text_domain) ?></option>
 					</select>
			<tr>
				<td style="width:120px;"><?php _e('Thumbnail Size', $this->text_domain) ?></td>
				<td><input name="wpgr_thumbsize" type="text" id="wpgr_thumbsize" onchange="set_changed()" value="<?php echo attribute_escape($gallery['thumbsize']); ?>" size="4" /></td>
			</tr>
			<tr>
				<td scope="row"><?php _e('Output Type', $this->text_domain) ?></td>
				<td>
					<select name="wpgr_outputtype" id="wpgr_outputtype" onchange="set_changed(); set_visibility('wpgr_outputtype', 'wpgr_plain_divstyle');">
 							<option value="carousel"<?php if ($gallery['outputtype'] == 'carousel') { echo ' selected'; } ?>><?php _e('Carousel', $this->text_domain) ?></option>
 							<option value="plain"<?php if ($gallery['outputtype'] == 'plain') { echo ' selected'; } ?>><?php _e('Plain', $this->text_domain) ?></option>
 						</select>
				</td>
			</tr>
			<tr id="wpgr_plain_divstyle" style="visibility:<?php echo ($gallery['outputtype'] == 'plain') ? 'visible' : 'collapse'; ?>;">
				<td scope="row">&nbsp;</td>
				<td>
					<table class="wpgr_album_caching_options_table">
						<tr>
							<td><?php _e('Formatting', $this->text_domain) ?> <span id="wpgr_tpa_divstyle"><img src="<? echo $this->pluginpath ?>img/info.png" width="16" height="16" alt="info"></span>
								<div id="wpgr_tp_divstyle" style="display:none; margin:5px; padding:5px; background-color:#cccccc; width:370px; height:80px;"><?php _e('You can enter additional CSS formatting here, which is used in the style attribute of the surrounding div layer of the album image, e.g. "float:right;".<p>This option is not(!) relevant for the carousel output type.', $this->text_domain) ?></div>
							</td>
							<td><input name="wpgr_divstyle" type="text" id="wpgr_divstyle" onchange="set_changed();" value="<?php echo attribute_escape($gallery['divstyle']); ?>" size="80" /></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td scope="row"><?php _e('Use Caching', $this->text_domain) ?> <span id="wpgr_tpa_usecaching"><img src="<? echo $this->pluginpath ?>img/info.png" width="16" height="16" alt="info"></span>
					<div id="wpgr_tp_usecaching" style="display:none; margin:5px; padding:5px; background-color:#cccccc; width:330px; height:80px;"><?php _e('If caching is activated, the list of albums as well as information about the images of each album, which were retrieved from you Gallery installation, is cached in Wordpress\' database to speed up page generation. Activating caching is highly recommended.', $this->text_domain) ?></div>
				</td>
				<td><input name="wpgr_usecaching" type="checkbox" id="wpgr_usecaching" onchange="set_changed(); set_visibility('wpgr_usecaching', 'wpgr_album_caching');" value="true" <?php if ($gallery['usecaching']) { echo 'checked'; } ?> /></td>
			</tr>
			<tr id="wpgr_album_caching" style="visibility:<?php echo ($gallery['usecaching'] == true) ? 'visible' : 'collapse'; ?>;">
				<td scope="row">&nbsp;</td>
				<td>
					<table class="wpgr_album_caching_options_table">
						<tr>
							<td colspan="2">
								<input type="submit" name="wp_gallery_remote_clear_albumcache" value="<?php _e('Clear Album Cache', $this->text_domain) ?>" onclick="return check_changed();" />&nbsp;
								<input type="submit" name="wp_gallery_remote_clear_imagecache" value="<?php _e('Clear Image Cache', $this->text_domain) ?>" onclick="return check_changed();" />&nbsp;
								<input type="submit" name="wp_gallery_remote_rebuild_caches" value="<?php _e('Rebuild Caches', $this->text_domain) ?>" onclick="return confirm_rebuild();" />
							</td>
						</tr>
						<tr>
							<td style="width:170px"><?php _e('Album Cache Timeout', $this->text_domain) ?> <span id="wpgr_tpa_cachetimeoutalbums"><img src="<? echo $this->pluginpath ?>img/info.png" width="16" height="16" alt="info"></span>
								<div id="wpgr_tp_cachetimeout" style="display:none; margin:5px; padding:5px; background-color:#cccccc; width:330px; height:80px;"><?php _e('Setting timeouts to 0, disables the timeouts, so that you have to manually delete caches. Caches can be updated in the WP-Gallery-Remote album chooser, which can be opened from Wordpress\' post and page editor (TinyMCE).', $this->text_domain) ?></div>
							</td>
							<td><input name="wpgr_cachetimeoutalbums" type="text" id="wpgr_cachetimeoutalbums" onchange="set_changed()" value="<?php echo attribute_escape($gallery['cachetimeoutalbums']); ?>" size="5" /> seconds</td>
						</tr>
						<tr>
							<td style="width:170px"><?php _e('Image Cache Timeout', $this->text_domain) ?> <span id="wpgr_tpa_cachetimeoutimages"><img src="<? echo $this->pluginpath ?>img/info.png" width="16" height="16" alt="info"></span>
							</td>
							<td><input name="wpgr_cachetimeoutimages" type="text" id="wpgr_cachetimeoutimages" onchange="set_changed()" value="<?php echo attribute_escape($gallery['cachetimeoutimages']); ?>" size="5" /> seconds</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<div class="submit">
			<input type="submit" name="wpgr_update_gallery" value="<?php _e('Save Gallery Options &raquo;', $this->text_domain) ?>" onclick="javascript:return validate_data();" />
		</div>
		</form>
		<script type="text/javascript">
  			var wpgr_tp_debug_window = new Tooltip('wpgr_tpa_debug_window', 'wpgr_tp_debug_window');
  			var wpgr_tp_gallery_url = new Tooltip('wpgr_tpa_gallery_url', 'wpgr_tp_gallery_url');
  			var wpgr_tp_usecaching = new Tooltip('wpgr_tpa_usecaching', 'wpgr_tp_usecaching');
  			var wpgr_tp_cachetimeoutalbums = new Tooltip('wpgr_tpa_cachetimeoutalbums', 'wpgr_tp_cachetimeout');
  			var wpgr_tp_cachetimeoutimages = new Tooltip('wpgr_tpa_cachetimeoutimages', 'wpgr_tp_cachetimeout');
  			var wpgr_tp_rssmode = new Tooltip('wpgr_tpa_rssmode', 'wpgr_tp_rssmode');
  			var wpgr_tp_divstyle = new Tooltip('wpgr_tpa_divstyle', 'wpgr_tp_divstyle');
		</script>
  	</div>
  <?php
  }
  
  /**
   * header
   * adds plugin css and js files to page header
   * 
   * @param 
   * @return 
   */
  public function header() {
    // only show debug window, if admin && debug window option activated
    if (current_user_can('wpgr_debug') && $this->options['showdebugwindow'] == true) {
      echo "\n<link rel=\"stylesheet\" href=\"" . $this->pluginpath . "css/wpgr-debug.css\" type=\"text/css\" />\n";
    }
    
    if (!is_feed() && $this->options['carousel_needed']) { ?>
	    <link rel="stylesheet" type="text/css" media="screen" href="<?php echo $this->pluginpath ?>css/wpgr-carousel.css" />
	    <script type="text/javascript">
		    function buttonStateHandler(button, enabled) {
	
			 if (button.substr(0,10) == "prev-arrow") 
			   $(button).src = enabled ? "<?php echo $this->pluginpath ?>img/left-enabled.gif" : "<?php echo $this->pluginpath ?>img/left-disabled.gif"
			 else 
			   $(button).src = enabled ? "<?php echo $this->pluginpath ?>img/right-enabled.gif" : "<?php echo $this->pluginpath ?>img/right-disabled.gif"
			}
			
			function animHandler(carouselID, status, direction) {
			  var region = $(carouselID).down(".carousel-clip-region")
			  if (status == "before") {
			    Effect.Fade(region, {to: 0.3, queue: { position:'end', scope: "carousel" }, duration: 0.2})
			  }
			  if (status == "after") {
			    Effect.Fade(region, {to: 1, queue: { position:'end', scope: "carousel" }, duration: 0.2})
			  }
			}<?php 
			
			// if request parameters include an image id, add javascript to automatically open the respective image
			if (isset($_REQUEST['wpgr_current_image'])) { ?>
			 	var oldonload = window.onload;
	
				window.onload = function() {
					if (oldonload == 'function') {
						oldonload();
					}
					
					var img = document.getElementById("<?php echo $_REQUEST['wpgr_current_image']; ?>");
		 			if (img != null) {
		 				window.myLightbox.start(img);
		 			}
				}<?php
			} ?>
		</script><?php
    }  
  }
  
  /**
   * footer
   * outputs debug window, if activated
   * 
   * @param 
   * @return 
   */
  public function footer() {
    // only show debug window, if admin && debug window option activated
    if (current_user_can('wpgr_debug') && $this->options['showdebugwindow'] == true) { 
      $overall_gen_time = (float)0; ?>
      <p class="wpgr_transparent" id="wpgr_debug"><?php 
      foreach ($this->debug as $key => $row) { ?>
        <strong><?php _e('Album:', $this->text_domain) ?></strong> <?php echo $key ?><br /><?php
        $gen_time = $row['content_gen_end'] - $row['content_gen_start'];
        $overall_gen_time+= (float)$gen_time;
        $album_fetch_time = $row['content_gen_album_fetch'] - $row['content_gen_start'];
        $image_fetch_time = $row['content_gen_image_fetch'] - $row['content_gen_album_fetch']; ?>        
        <strong><?php _e('Gen. Time:', $this->text_domain) ?></strong> <?php echo $gen_time ?><br />
        <strong><?php _e('Album Gen. Time:', $this->text_domain) ?></strong> <?php echo $album_fetch_time ?><br />
        <strong><?php _e('Images Gen. Time:', $this->text_domain) ?></strong> <?php echo $image_fetch_time ?><br />
        <br /><?php
      } ?>
      	<strong><?php _e('Overall Gen. Time:', $this->text_domain) ?></strong> <?php echo $overall_gen_time ?>
      </p><?php
    }
  }
  
  /**
   * mce_plugins
   * adds WP-Gallery-Remote TinyMCE plugin to Wordpress' TinyMCE
   * 
   * @param Array $plugins contains all registered TinyMCE plugins
   * @return Array $plugins
   */
  public function mce_plugins($plugins) {
  	$plugin_array['wpgr'] = get_option('siteurl') . '/wp-content/plugins/wp-gallery-remote/mce_plugin/editor_plugin.js';
    return $plugin_array;
  }
  
  /**
   * mce_buttons
   * adds WP-GR button to Wordpress' TinyMCE
   * 
   * @param Array $buttons contains all registered TinyMCE buttons
   * @return Array $buttons
   */
  public function mce_buttons($buttons) {
  	array_push($buttons, 'wpgrChooser');
  	return $buttons;
  }
  
  /**
   * addbuttons
   * registers the correct filters for adding buttons to richtext and non-richtext editor
   */
  public function addbuttons() {
    // Don't bother doing this stuff if the current user lacks permissions
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages'))
      return;
 
    // Add only in Rich Editor mode
    if (get_user_option('rich_editing') == 'true') {
      add_filter('mce_external_plugins', array(&$this, 'mce_plugins'));
      add_filter('mce_buttons', array(&$this, 'mce_buttons'));
    }    
  }
  
  public function wpgrOpenJS() {
    $mce_plugin_url = $this->pluginpath . 'mce_plugin/';
  
    // bloody workaround for compatibility with admin-ssl module
    // this only works, if the standard ports (80, 443) are used for http and https
    // (well...this is a limitation of the admin-ssl module anyway)
    if ($_SERVER['HTTPS'] == 'on' && strpos(strtolower($mce_plugin_url), 'https') === false) {
      $mce_plugin_url = 'https' . substr($mce_plugin_url, 4);
    } ?>
  
      <script language="JavaScript" type="text/javascript"><!--
        var wp_gallery_remote_toolbar = document.getElementById("ed_toolbar"); 
        <?php $this->edit_insert_button('WP-GR', 'wp_gallery_remote_open', __('WP-Gallery-Remote Image Chooser', $this->text_domain)); ?>

        function wp_gallery_remote_open() {
          var url = '<?php echo $mce_plugin_url; ?>wp-gallery-remote-mce-plugin.php';
          var name = 'wp_gallery_remote';
  		  var w = 800;
  		  var h = 600;
  		  var valLeft = (screen.width) ? (screen.width-w)/2 : 0;
  		  var valTop = (screen.height) ? (screen.height-h)/2 : 0;
  		  var features = 'width='+w+',height='+h+',left='+valLeft+',top='+valTop+',resizable=1,scrollbars=1';
  		  var wp_gallery_remote_window = window.open(url, name, features);
  		  wp_gallery_remote_window.focus();
        }
      //--></script> 
      <?php
  }
  
  /**
   * edit_insert_button
   * adds WP-GR button to TinyMCE toolbar
   * 
   * @param String $caption value of the button
   * @param String $js_onclick JS code to call on click function
   * @param String $title caption of the button
   * @return
   */
  private function edit_insert_button($caption, $js_onclick, $title = '') { ?>
    if (wp_gallery_remote_toolbar){
      var theButton = document.createElement('input');
      theButton.type = 'button';
      theButton.value = '<?php echo $caption; ?>';
      theButton.onclick = <?php echo $js_onclick; ?>;
      theButton.className = 'ed_button';
      theButton.title = "<?php echo $title; ?>";
      theButton.id = "<?php echo "ed_{$caption}"; ?>";
      wp_gallery_remote_toolbar.appendChild(theButton);
    } <?php
  }
  

  /**
   * get_album_content
   * generates the HTML album and image output
   * 
   * @param Array $this->album_list array of album information
   * @param Array $this->options array of WP-Gallery-Remote options
   * @param Array $image_filter array of image ids
   * @return String $content generated HTML output
   */
  private function get_album_content($image_filter = array()) {
    $gallery = $this->options['galleries'][$this->options['gallery']];
    $gallery_id = $this->options['gallery'];
    
    // start output bufferung
    ob_start();
    
    // back link
    if ($this->album_list['current_album'] != $this->album_list['root_album']) { ?>
  		<p><a href="<?php printf('%scurrent_album=%s', $this->get_url(), $this->album_list['current_album_parent']) ?>"><?php _e('Back', $this->text_domain) ?></a></p> <?php
    }
  
    if ($this->options['showalbumtitle'] == true) { ?>
    	<strong><?php _e('Current Album:', $this->text_domain) ?></strong> <?php 
    	if ($this->options['clickalbumtitle'] != 'none') {
    	  $title = sprintf('<a href="%s?g2_itemId=%s"', $gallery['url'], $this->album_list['current_album']);
          if ($this->options['clickalbumtitle'] == 'opennew') {
            $title.= ' target="_blank"';
          }
    	  $title.= sprintf('>%s</a>', $this->replace_bbcode($this->album_list['current_album_title']));
    	  echo $title;
    	} else {
          echo $this->replace_bbcode($this->album_list['current_album_title']);
    	}
    	
    	echo "<br />";
    }

    // subalbums
    if ($this->options['showsubalbums'] == true) {
      // get list of current subalbums (albums with parent = current_album)
      $albums = array();
      foreach ($this->album_list[$gallery_id]['album']['parent'] as $idx => $parent) {
        if ($parent == $this->album_list['current_album']) {
          array_push($albums, $idx);
        }
      }
  
      // list subalbums, if available
      if (!empty($albums)) { ?>
  		<p>
  		<strong><?php _e('Subalbums:', $this->text_domain) ?></strong><br /> <?php
  	
  		$url = $this->get_url() . 'current_album=';
    
  		foreach ($albums as $idx) {
  		  echo sprintf('<a href="%s">%s</a><br />', $url . $this->album_list[$gallery_id]['album']['name'][$idx], $this->replace_bbcode($this->album_list[$gallery_id]['album']['title'][$idx]));  
  		} ?>
  		</p> <?php
      }
    }
  
    // fetch images of current album and display thumbnails, if images available
    $current_album = $this->album_list['current_album'];
    $this->fetch_images($current_album, $gallery['nocaching']);
    $this->debug_add($this->options['rootalbum'], array('content_gen_image_fetch' => microtime(true)));

    if (isset($this->images[$gallery_id][$current_album]['image']['name'])) { 
      if ($this->options['showimagesheader'] == true) { ?>
  		<strong><?php _e('Images:', $this->text_domain) ?></strong><br /> <?php
      }
      
      $li_start = '';
      $li_end = '';
      if (!is_feed() && $gallery['outputtype'] == "carousel") {
        $carouselID = sprintf('%s_%s_%s', $gallery_id, $current_album, $this->callback_passes);
         
        // used to add <li> tags around images
	    $li_start = '<li>';
	    $li_end = '</li>'; ?>
     	<div id="prev-arrow-container">
  			<img id="prev-arrow<?php echo $carouselID ?>" class="left-button-image" src="<?php echo $this->pluginpath ?>img/left-enabled.gif" style="border:0px solid #CCCCCC; cursor:pointer; margin:0pt 0px; max-width:100px; padding:0px;"/>
	  	</div>
	  	<div id="html-carousel<?php echo $carouselID ?>" class="carousel-component" style="margin:0px 0pt;">
  			<div class="carousel-clip-region">
    			<ul class="carousel-list"><?php
      } else {
        $div_style = $this->options['divstyle']; ?>
        <div style="<?php echo $div_style ?>"><?php
      }
    
      $target = (function_exists('lightbox_header')) ? '' : ' target=\'_blank\'';
      global $wp_query;
      // generate a id for each wpgr-tag which is globally unique; it contains of: gallery id + post id + callback pass no. + album id
      $album_include_idx = sprintf('%s-%s-%s-%s', $gallery_id, $wp_query->post->ID, $this->callback_passes, $this->album_list['current_album']);
      // check whether a resized image is available
	  foreach ($this->images[$gallery_id][$current_album]['image']['name'] as $key => $value) {
        if ($this->images[$gallery_id][$current_album]['image']['hidden'][$key] == 'no') {
          if (empty($image_filter) 
            || ($image_filter['type'] == 'include' && in_array($value, $image_filter['filter']))
            || ($image_filter['type'] == 'exclude' && !in_array($value, $image_filter['filter']))
           ) {
            // The plan was to show title and summary of an image, but as the bloody remote gallery protocol module does not
            // support/deliver the summary property in fetch-album-images, we only output the title 
            // (which is called 'caption' in the protocol spec of fetch-album-images => how stupid is that??)
            // btw...same for fetch-image-properties :-(
	        $image_idx = sprintf('%s-%s', $album_include_idx, $value); 
            $caption = $this->images[$gallery_id][$current_album]['image']['caption'][$key];
            (array_key_exists('resizedName', $this->images[$gallery_id][$current_album]['image']) && array_key_exists($key, $this->images[$gallery_id][$current_album]['image']['resizedName'])) ? $name_key = 'resizedName' : $name_key = 'name';
            // feed mode
            if (is_feed()) {
              if ($this->options['rssmode'] == true) {
                $image_link = sprintf('%s%s&filename=%s.%s', stripslashes($this->images[$gallery_id][$current_album]['baseurl']),
                                              $this->images[$gallery_id][$current_album]['image'][$name_key][$key],
                                              $this->images[$gallery_id][$current_album]['image'][$name_key][$key],
                                              $this->images[$gallery_id][$current_album]['image']['forceExtension'][$key]
                                     );
              } else {
                $image_link = sprintf('%swpgr_current_image=%s', $this->get_url(), $image_idx);
              }
              printf('<a href=\'%s\' target=\'_blank\'><img src="%s%s" width="%s"></a>',
                      $image_link,
                      stripslashes($this->images[$gallery_id][$current_album]['baseurl']),
                      $this->images[$gallery_id][$current_album]['image']['thumbName'][$key],
                      $gallery['thumbsize']
                    );
            // normal post/page mode
            } else {
              switch ($gallery['clickimage']) {
                case 'newwindowplain':
                  $rel_or_target = ' target="_blank" ';
	              $link = sprintf('%s%s&filename=%s.%s',
	                              stripslashes($this->images[$gallery_id][$current_album]['baseurl']),
	                              $this->images[$gallery_id][$current_album]['image'][$name_key][$key],
	                              $this->images[$gallery_id][$current_album]['image'][$name_key][$key],
	                              $this->images[$gallery_id][$current_album]['image']['forceExtension'][$key]
	                             );
                  break;
                  
                case 'newwindowgallery':
                  $rel_or_target = ' target="_blank" ';
                  $link = sprintf('%s?g2_itemId=%s',
                                  $gallery['url'],
                                  $this->images[$gallery_id][$current_album]['image']['name'][$key]
                                 );
                  break;
                  
                default: //lightbox
	              $rel_or_target = sprintf('rel="lightbox[%s]"', $album_include_idx);
	              $link = sprintf('%s%s&filename=%s.%s',
	                              stripslashes($this->images[$gallery_id][$current_album]['baseurl']),
	                              $this->images[$gallery_id][$current_album]['image'][$name_key][$key],
	                              $this->images[$gallery_id][$current_album]['image'][$name_key][$key],
	                              $this->images[$gallery_id][$current_album]['image']['forceExtension'][$key]
	                             );
              }
              
              printf('%s<a id="%s" title="%s" %s href="%s"><img src="%s%s" width="%s"></a>%s',
                      $li_start,
                      $image_idx,
                      $this->replace_bbcode($caption),
                      $rel_or_target,                      
                      $link,
                      stripslashes($this->images[$gallery_id][$current_album]['baseurl']),
                      $this->images[$gallery_id][$current_album]['image']['thumbName'][$key],
                      $gallery['thumbsize'],
                      $li_end
                    );
            }
          }
        }
      }
      
      if (!is_feed() && $gallery['outputtype'] == "carousel") { ?>
	  		</ul>
		  </div>
		</div>
		<div id="next-arrow-container" >
	    	<img id="next-arrow<?php echo $carouselID ?>" class="right-button-image" src="<?php echo $this->pluginpath ?>img/right-enabled.gif" style="border:0px solid #CCCCCC; cursor:pointer; margin:0pt 0px; max-width:100px; padding:0px;"/>
		</div>
		<script type="text/javascript">
		//<![CDATA[
		function initCarousel_html_carousel<?php echo $carouselID ?>() {carousel = new Carousel('html-carousel<?php echo $carouselID ?>', {animHandler:animHandler, animParameters:{duration:0.5}, buttonStateHandler:buttonStateHandler, nextElementID:'next-arrow<?php echo $carouselID ?>', prevElementID:'prev-arrow<?php echo $carouselID ?>', size:31})};Event.observe(window, 'load', initCarousel_html_carousel<?php echo $carouselID ?>);
		//]]>
		</script><?php
      } else { ?>
        </div><?php
      }
    }
    
    // get output from buffer, and return content
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
  }
  
  /**
   * get_url
   * return URL of current context (page/post) ending with & or ? depending whether permalinks are used
   * 
   * @param
   * @return String $url
   */
  private function get_url() {
    $url = get_permalink();
    if ('' == get_option('permalink_structure')) {
      $url .= '&';
    } else {
      $url .= '?';
    }
    
    return $url;
  }
  
  /**
   * fetch_albums
   * fetches album metadata from Gallery installation
   * 
   * @param String $root_album id of the current root album
   * @param Array $this->options array of WP-Gallery-Remote options
   * @param Bool $disable_cacahing defines whether caching should be explicitly activated or deactiacted. default is activated (true)
   * @return null
   **/
  public function fetch_albums($root_album, $disable_caching = false) {
    $gallery_id = $this->options['gallery'];
    $gallery = $this->options['galleries'][$gallery_id];    

    if ($gallery['usecaching'] == true && $disable_caching == false) {
      // only fetch album list
      if (!is_array($this->album_list[$gallery_id])) {
        $this->album_list[$gallery_id] = get_option('wp_gallery_remote_albums' . $gallery_id);
      }
      
      // check for album cache timeout
      if (is_array($this->album_list[$gallery_id]) && $gallery['cachetimeoutalbums'] != 0 && $this->album_list[$gallery_id]['last_update'] + $gallery['cachetimeoutalbums'] <= time()) {
        unset($this->album_list[$gallery_id]);
      }
    } else {
      unset($this->album_list[$gallery_id]);
    }
    
    if (!is_array($this->album_list[$gallery_id])) {
      $params = '&g2_form[cmd]=fetch-albums'
      				. '&g2_form[protocol_version]=2.0'
      				. '&g2_form[no_perms]=yes';

      $response = $this->do_post_request($gallery['url'], $params);
      $this->album_list[$gallery_id] = $this->parse_response($response);

      if ($gallery['usecaching'] == true) {
        $this->album_list[$gallery_id]['last_update'] = time();
        update_option('wp_gallery_remote_albums' . $gallery_id, $this->album_list[$gallery_id], 'cached gallery album information', false);
      }
    }

    // set root album, if not yet set
    if ($root_album == 0) {
      $arr_idx = array_search('0', $this->album_list[$gallery_id]['album']['parent']);
      $root_album = $this->album_list[$gallery_id]['album']['name'][$arr_idx];    
    }
    $this->album_list['root_album'] = $root_album;
  
    // set current album
    if (isset($_REQUEST['current_album']) && $this->album_allowed($_REQUEST['current_album'], $root_album, $this->album_list[$gallery_id])) {
      $this->album_list['current_album'] = $_REQUEST['current_album'];
    } else {
      $this->album_list['current_album'] = $root_album;
    }
    
    // set index, title and parent of current album
    $this->album_list['current_album_idx'] = array_search($this->album_list['current_album'], $this->album_list[$gallery_id]['album']['name']);
    $this->album_list['current_album_title'] = $this->album_list[$gallery_id]['album']['title'][$this->album_list['current_album_idx']];
    if ($root_album == $this->album_list['current_album']) {
      $this->album_list['current_album_parent'] = $root_album;
    } else {
      $this->album_list['current_album_parent'] = $this->album_list[$gallery_id]['album']['parent'][$this->album_list['current_album_idx']];
    }
  }
  
  /**
   * fetch_images
   * fetches images metadata of a specific album
   * 
   * @param Array $this->options array of WP-Gallery-Remote options
   * @param Bool $disable_cacahing defines whether caching should be explicitly activated or deactiacted. default is activated (true) 
   * @return null
   */
  public function fetch_images($current_album, $disable_caching = false) {
    $gallery_id = $this->options['gallery'];
    $gallery = $this->options['galleries'][$gallery_id];
    
    if (!is_array($this->images[$gallery_id])) {
      $this->images[$gallery_id] = get_option('wp_gallery_remote_images' . $gallery_id);
    }
    
    if (is_array($this->images[$gallery_id]) && $gallery['usecaching'] == true && $disable_caching == false) { 
      if (array_key_exists($current_album, $this->images[$gallery_id])) {
        if ($gallery['cachetimeoutimages'] == 0 || $this->images[$gallery_id][$current_album]['last_update'] + $gallery['cachetimeoutimages'] <= time()) {
          return;
        }
      }    
    }

    // fetch images
    $params = '&g2_form[cmd]=fetch-album-images'
    			. '&g2_form[protocol_version]=2.4'
    			. '&g2_form[albums_too]=no'
    			. '&g2_form[set_albumName]=' . $current_album;
  
    $response = $this->do_post_request($gallery['url'], $params);
    $images = $this->parse_response($response);

    $images['last_update'] = time();
    $this->images[$gallery_id][$current_album] = $images;

    // cache image information
    if ($gallery['usecaching'] == true) { 
      update_option('wp_gallery_remote_images' . $gallery_id, $this->images[$gallery_id], 'cached image information of gallery albums', false);
    }
  }
  
  /**
   * parse_response
   * parses Gallery Remote response String and converts to array - example:
   * album.name.1=5
   * album.title.1=Test Title
   * album.summary.1=Test Summary bla bla bla
   * album.name.2=10
   *   converts to
   * $this->album_list['name'][1]=5
   * $this->album_list['title'][1]='Test Title'
   * $this->album_list['summary'][1]='Test Summary bla bla bla'
   * $this->album_list['name'][2]=10
   * 
   * @param String $response Gallery Remote response String
   * @return Array $this->album_list
   */
  private function parse_response($response) {
    $result = array();
    $lines = explode("\n", $response);
  
    foreach ($lines as $line) {
      if ($line{0} != '#') {
        list($key, $value) = explode('=', $line, 2);
        $keys = explode('.', $key);
        $p = &$result;
        foreach ($keys as $key) {
          if (!array_key_exists($key, $p)) { $p[$key] = array(); }
          $p = &$p[$key];
        }
        $p = stripslashes($value);
      }
    }

    return $result;
  }
  
  /**
   * do_post_request
   * sends a POST request to a certain URL;
   * code from: http://netevil.org/blog/2006/nov/http-post-from-php-without-curl
   * 
   * @param String $url URL to send request to
   * @param String $data data of the POST request
   * @param String $optional_headers additional HTTP headers
   */
  private function do_post_request($url, $params = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?g2_controller=remote:GalleryRemote');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close ($ch);
 	
 	if (strpos($response, '#__GR2PROTO__') != 0)
 	  return false;
 	else
      return $response;
  }
  
  /**
   * replace_bbcode
   * replaces a number of BBcodes with their HTML pendents in a string
   * 
   * @param String $s
   * @return String $s
   */
  public function replace_bbcode($s) {
    // [b]
    $s = preg_replace('/\[b\]/i', '<strong>', $s);
    $s = preg_replace('/\[\/b\]/i', '</strong>', $s);
    
    // [i]
    $s = preg_replace('/\[i\]/i', '<i>', $s);
    $s = preg_replace('/\[\/i\]/i', '</i>', $s);
   
    // [list]
    $s = preg_replace('/\[list\]/i', '', $s);
    $s = preg_replace('/\[\/list\]/i', '', $s);
    
    // [*]
    $s = preg_replace('/\[\*\]/i', '<li>', $s);
    
    // [url]<url>[\url]
    $s = preg_replace('/\[url\](.+?)\[\/url\]/i', '<a href=\"$1\" target=\"_blank\">$1</a>', $s);
    
    // [url=<url>]<title>[\url]
    $s = preg_replace('/\[url=(.+?)\](.+?)\[\/url\]/i', '<a href=\"$1\" target=\"_blank\">$2</a>', $s);
    
    // [list]
    $s = preg_replace('/\[color=(.+?)\]/i', '<font color=\"$1\">', $s);
    $s = preg_replace('/\[\/color\]/i', '</font>', $s);
    
	// Quotes - not BB code but are needed as well :)
	$s = str_replace('"', '&quot;', $s);
    
    return $s;
  }
  
  /**
   * set_image_count
   * calls increment-view-count function of Gallery Remote protocol to increment the view count 
   * of a given Gallery item (album or image)
   * 
   * @param String/int $image_id id of Gallery item
   * @param Array $this->options array of WP-Gallery-Remote options
   */
  private function set_image_count($image_id) {
    $params = '&g2_form[cmd]=increment-view-count'
    			. '&g2_form[protocol_version]=****'
                . '&g2_form[itemId]=' . $image_id;
  
    $response = do_post_request($this->options['galleryurl'], $params);
  }
  
  /**
   * get_options
   * retrieves WP-Gallery-Remote options from database and sets defaults, if not present
   * 
   * @param
   * @return null
   */
  private function get_options() {
    // load current options
    $this->options = get_option('wp_gallery_remote_options');
    
    if ( !is_array($this->options) ) {
      $this->options = array();
    }
    
    if (!array_key_exists('showalbumtitle', $this->options)) { $this->options['showalbumtitle'] = true; }
    if (!array_key_exists('showsubalbums', $this->options)) { $this->options['showsubalbums'] = true; }
    if (!array_key_exists('showimagesheader', $this->options)) { $this->options['showimagesheader'] = true; }
    if (!array_key_exists('showdebugwindow', $this->options)) { $this->options['showdebugwindow'] = true; }
    if (!array_key_exists('rssmode', $this->options)) { $this->options['rssmode'] = false; }
    if (!array_key_exists('clickalbumtitle', $this->options)) { $this->options['clickalbumtitle'] = 'none'; }
  }
  
  /**
   * album_allowed
   * checks whether $album is in the hierarchy of $root_album in $this->album_list.
   * 
   * @param int $album id of album
   * @param int $root_album id of root album
   * @param Array $this->album_list Array with album meta data
   * @return bool $result
   */
  private function album_allowed($album, $root_album) {
    $gallery_id = $this->options['gallery'];

    // if invalid album id
    if (!in_array($album, $this->album_list[$gallery_id]['album']['name'])) { return false; }
    
    // if subalbums deactivated
    if ($this->options['showsubalbums'] == false) { return false; }
    
    // if we are not on a page or a single post
    if (!is_page() && !is_single()) { return false; }
    
    // album is not in the hierarchy of $root_album
    $current_album = $album;
    $current_parent = $this->album_list[$gallery_id]['album']['parent'][array_search($current_album, $this->album_list[$gallery_id]['album']['name'])];
    while ($current_parent != '0' && $current_parent != $root_album) {
      $current_album = $current_parent;
      $current_parent = $this->album_list[$gallery_id]['album']['parent'][array_search($current_album, $this->album_list[$gallery_id]['album']['name'])];
    }

    if ($current_parent == '0') {
      return false;
    }
    
    return true;
  }

  /**
   * add_debug
   * adds $d to $this->debug array, if debugging is activated)
   * 
   * @param $d debug information
   */
  private function debug_add($key, $d) {
    if (current_user_can('wpgr_debug') && $this->options['showdebugwindow'] == true) {
      if (!is_array($this->debug[$key])) { $this->debug[$key] = array(); }
      $this->debug[$key] = array_merge($this->debug[$key], $d);
    }
  }
}

?>
