<?php
// include Wordpress configuration to be able to use Standard wordpress functions like get_option
require('../../../../wp-config.php'); 
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
	<title><?php _e('WP-Gallery-Remote Album Chooser', $wpgr->text_domain) ?></title>
	<script language="javascript" type="text/javascript" src="wp-gallery-remote-mce-plugin.js"></script><?php
	if (function_exists('lightbox_header')) {
	  lightbox_header();
    } ?>
</head>
<body>
<form method="post"><?php
  if (!isset($_POST['wpgr_galleries'])) {
    $wpgr->options['gallery'] = 0;
  } else {
    $wpgr->options['gallery'] = $_POST['wpgr_galleries'];
  } 
  
  $gallery_opts = $wpgr->options['galleries'][$wpgr->options['gallery']];
  $gallery_id = $wpgr->options['gallery']; ?>
  <input type="hidden" name="hidden_clickimage" value="<?php echo $gallery_opts['clickimage'] ?>" />
  <input type="hidden" name="hidden_thumbsize" value="<?php echo $gallery_opts['thumbsize'] ?>" />
  <input type="hidden" name="hidden_nocaching" value="<?php echo $gallery_opts['nocaching'] ?>" />
  <input type="hidden" name="hidden_showalbumtitle" value="<?php echo $wpgr->options['showalbumtitle'] ?>" />
  <input type="hidden" name="hidden_clickalbumtitle" value="<?php echo $wpgr->options['clickalbumtitle'] ?>" />
  <input type="hidden" name="hidden_showsubalbums" value="<?php echo $wpgr->options['showsubalbums'] ?>" />
  <input type="hidden" name="hidden_showimagesheader" value="<?php echo $wpgr->options['showimagesheader'] ?>" /><?php
  if ($gallery_opts['outputtype'] == 'plain') { ?>
  	<input type="hidden" name="hidden_divstyle" value="<?php echo $gallery_opts['divstyle'] ?>" /><?php
  } 
  _e('Available Galleries:', $wpgr->text_domain) ?>
  <select name="wpgr_galleries" id="wpgr_galleries" onchange="javascript:document.forms[0].submit();"><?php
      foreach ($wpgr->options['galleries'] as $id => $gallery) { ?>
        <option value="<?php echo $id ?>"<?php if ($id == $_POST['wpgr_galleries']) { echo ' selected'; } ?>><?php echo $gallery['name'] ?></option><?php
      } ?>
  </select><?php
  
  try {
    $disable_caching = isset($_REQUEST['clear_album_cache']);
    $wpgr->fetch_albums(0, $disable_caching);
    $level = -1; ?>
    <p>
      <input type="button" name="insert_tag" value="<?php _e('Insert WP-GR Tag &raquo;', $wpgr->text_domain) ?>" onclick="insert_wp_gallery_remote_tag();" style="font-weight:bold;" />
      <input type="submit" name="clear_album_cache" value="<?php _e('Clear Album Cache', $wpgr->text_domain) ?>">
      <input type="button" name="cancel" value="<?php _e('Cancel', $wpgr->text_domain) ?>" onclick="cancelAction();" />
    </p>
    <fieldset>
      <p>
        <label for="album"><?php _e('Album:', $wpgr->text_domain) ?></label>
        <select name="album" id="album" size="1" style="width: 450px;" onchange="document.forms[0].submit();">
          <?php 
            (!isset($_REQUEST['album'])) ? $selected = -1 : $selected = $_REQUEST['album']; 
            $current_album = 0;        
            wp_gallery_remote_build_album_hierarchy($current_album, $level, $wpgr->album_list[$gallery_id], $wpgr, $selected); 
          ?>
        </select>
        <input type="submit" name="clear_image_cache" value="<?php _e('Clear Album\'s Image Cache', $wpgr->text_domain) ?>">
      </p>
      <p>
        <label for="clickimage"><?php _e('Click on Image:', $wpgr->text_domain) ?></label>
		<select name="clickimage" id="clickimage">
			<option value="lightbox"<?php if ($gallery_opts['clickimage'] == 'lightbox') { echo ' selected'; } ?>><?php _e('opens image using Lightbox effect', $wpgr->text_domain) ?></option>
			<option value="newwindowplain"<?php if ($gallery_opts['clickimage'] == 'newwindowplain') { echo ' selected'; } ?>><?php _e('opens image in new window', $wpgr->text_domain) ?></option>
			<option value="newwindowgallery"<?php if ($gallery_opts['clickimage'] == 'newwindowgallery') { echo ' selected'; } ?>><?php _e('opens image\'s Gallery page in new window', $wpgr->text_domain) ?></option>
		</select><br />
        <label for="thumbsize"><?php _e('Thumbnail Size:', $wpgr->text_domain) ?></label>
        <input name="thumbsize" type="text" id="thumbsize" value="<?php echo attribute_escape($gallery_opts['thumbsize']); ?>" size="4" />
      </p>
      <p>
      	<input name="nocaching" type="checkbox" id="nocaching" value="true" <?php if ($gallery_opts['nocaching']) { echo 'checked'; } ?> />
        <label for="nocaching"><?php _e('Deactivate Caching', $wpgr->text_domain) ?></label><br />
        <input name="showalbumtitle" type="checkbox" id="showalbumtitle" value="true" <?php if ($wpgr->options['showalbumtitle']) { echo 'checked'; } ?> />
        <label for="showalbumtitle"><?php _e('Show Album Title', $wpgr->text_domain) ?></label><br />
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e('Click on Album Title', $wpgr->text_domain) ?>
        <select name="clickalbumtitle" id="clickalbumtitle">
			<option value="none"<?php if ($wpgr->options['clickalbumtitle'] == 'none') { echo ' selected'; } ?>><?php _e('does nothing', $wpgr->text_domain) ?></option>
			<option value="opensame"<?php if ($wpgr->options['clickalbumtitle'] == 'opensame') { echo ' selected'; } ?>><?php _e('opens Gallery album page in same window', $wpgr->text_domain) ?></option>
			<option value="opennew"<?php if ($wpgr->options['clickalbumtitle'] == 'opennew') { echo ' selected'; } ?>><?php _e('opens Gallery album page in new window', $wpgr->text_domain) ?></option>
		</select></br>
        <input name="showsubalbums" type="checkbox" id="showsubalbums" value="true" <?php if ($wpgr->options['showsubalbums']) { echo 'checked'; } ?> />
        <label for="showsubalbums"><?php _e('Show Subalbums', $wpgr->text_domain) ?></label><br />
        <input name="showimagesheader" type="checkbox" id="showimagesheader" value="true" <?php if ($wpgr->options['showimagesheader']) { echo 'checked'; } ?> />
        <label for="showimagesheader"><?php _e('Show Images Header', $wpgr->text_domain) ?></label>
      </p>
      <p>
        <?php _e('Output Type of selected Gallery:', $wpgr->text_domain) ?> <?php echo $gallery_opts['outputtype'] ?><br /><?php
      if ($gallery_opts['outputtype'] == 'plain') { ?>
        <label for="divstyle"><?php _e('Plain output formatting:', $wpgr->text_domain) ?> </label>
        <input name="divstyle" type="text" id="divstyle" value="<?php echo attribute_escape($gallery_opts['divstyle']) ?>" size="60" /><br /><?php
      } ?>
      </p><?php
      if (isset($_REQUEST['album'])) {
      	$current_album = $_REQUEST['album'];
      } else {
      	$current_album = $wpgr->album_list['root_album'];
      }

      $disable_image_caching = isset($_REQUEST['clear_image_cache']);
      $wpgr->fetch_images($current_album, $disable_image_caching);

      if (!empty($wpgr->images[$gallery_id][$current_album]['image'])) { ?>
        <p>
          <input name="useimagefilter" type="checkbox" id="useimagefilter" value="true" />
          <label for="useimagefilter"><?php _e('Apply Image Filter', $wpgr->text_domain) ?></label><br />
          <label for="filtertype"><?php _e('Filter Type', $wpgr->text_domain) ?></label>
          <select name="filtertype" id="filtertype" size="1">
            <option value="include"><?php _e('Include', $wpgr->text_domain) ?></option>
            <option value="exclude"><?php _e('Exclude', $wpgr->text_domain) ?></option>
          </select><br /><?php
          $target = '';
          if (function_exists('lightbox_header')) { $target = ' target="_blank"'; }
          foreach ($wpgr->images[$gallery_id][$current_album]['image']['name'] as $key => $value) {
            if ($wpgr->images[$gallery_id][$current_album]['image']['hidden'][$key] == 'no') { $hidden = true; }
  
      	    $caption = $wpgr->replace_bbcode($wpgr->images[$gallery_id][$current_album]['image']['caption'][$key]);
      	    (array_key_exists('resizedName', $wpgr->images[$gallery_id][$current_album]['image']) && array_key_exists($key, $wpgr->images[$gallery_id][$current_album]['image']['resizedName'])) ? $name_key = 'resizedName' : $name_key = 'name';
      	    $image_url = stripslashes($wpgr->images[$gallery_id][$current_album]['baseurl']) . $wpgr->images[$gallery_id][$current_album]['image']['thumbName'][$key];
            $thumb_width = $wpgr->images[$gallery_id][$current_album]['image']['thumb_width'][$key];
            $thumb_height = $wpgr->images[$gallery_id][$current_album]['image']['thumb_height'][$key]; ?>
            <div style="background:#000000 url(<?php printf('%s); width:%spx; height:%s', $image_url, $thumb_width, $thumb_height) ?>px; float:left;">
              <input type="checkbox" name="images" value="<?php echo $value; ?>" onclick="activate_wpgr_filter();" /><?php 
              printf('<a title=\'%s\' rel="lightbox[wpgr]" href="%s%s&filename=%s.%s"%s>',
                      $wpgr->replace_bbcode($wpgr->images[$gallery_id][$current_album]['image']['caption'][$key]),                      
                      stripslashes($wpgr->images[$gallery_id][$current_album]['baseurl']),
                      $wpgr->images[$gallery_id][$current_album]['image'][$name_key][$key],
                      $wpgr->images[$gallery_id][$current_album]['image']['resizedName'][$key],
                      $wpgr->images[$gallery_id][$current_album]['image']['forceExtension'][$key],
                      $target
                    );
              ?><img src="magnifier.gif" border="0">
              </a>
            </div><?php
          } ?>
        </p><?php
      } else { ?>
        <strong><?php _e('- No images available in this album. -', $wpgr->text_domain) ?></strong><?php
      } ?>        
    </fieldset>
  </form> <?php
} catch(Exception $e) { ?>
  <?php _e('<b>WP-Gallery-Remote Error:</b> Could not retrieve data from Gallery. Inform the administrator to review the WP-Gallery-Remote options and to check the Gallery installation.', $wpgr->text_domain);
}

/**
 * wp_gallery_remote_build_album_hierarchy
 * generates album hierarchy as <option> entites of selection box
 * 
 * @param String/int $current_album id of current album
 * @param int $level hierarchy level; used to indent the entries of the album hierarchy
 * @param Array $album_list array of album information
 */
function wp_gallery_remote_build_album_hierarchy($current_album, $level, &$album_list, $wpgr, $selected) {
  $indexes = array_keys($album_list['album']['parent'], $current_album);
  
  if (count($indexes) > 0) {
    $level++;
    foreach ($indexes as $idx) {
      $album_name = $wpgr->replace_bbcode($album_list['album']['name'][$idx]);
      $album_title = $wpgr->replace_bbcode($album_list['album']['title'][$idx]); 
      ($selected == $album_name) ? $is_selected = ' selected' : $is_selected = '';
      printf('<option value="%s"%s>%s%s</option>',
              $album_name,
              $is_selected,
              wp_gallery_remote_indent($level),
              $album_title
            );
      wp_gallery_remote_build_album_hierarchy($album_name, $level, $album_list, $wpgr, $selected);
    }
  }
}

function wp_gallery_remote_indent($level) {
  $s = '';
  for ($i=1; $i<=$level; $i++) {
    $s .= '&nbsp;&nbsp;&nbsp;&nbsp;';
  }
  return $s;
}
?>
</body>
</html>