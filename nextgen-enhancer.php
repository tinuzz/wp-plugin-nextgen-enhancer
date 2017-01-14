<?php

/*
Plugin Name: NextGEN Enhancer
Plugin Script: nextgen-enhancer.php
Plugin URI: https://www.grendelman.net/wp/nextgen-enhancer/
Description: NextGEN Gallery Enhancer
Version: 1.1
Author: Martijn Grendelman
Author URI: http://www.grendelman.net/
Template by: http://web.forret.com/tools/wp-plugin.asp

=== RELEASE NOTES ===
2013-12-18 - v1.1 - replace tzzbox with shutter-reloaded based code
2011-02-18 - v1.0 - first version
*/

	if (!defined('ABSPATH')) {
		die("No, sorry.");
	}

	// Load the ngg-db library, so we can statically call methods on it
	$nextgen_path = plugin_dir_path(__FILE__) ."../nextgen-gallery";
	$nextgen_path2 = plugin_dir_path(__FILE__) ."../nextgen-one-reloaded";

	if (file_exists ($nextgen_path2)) {
		require_once($nextgen_path2 . "/lib/ngg-db.php");
	}
	else {
		require_once($nextgen_path . "/lib/ngg-db.php");
	}

	if (!class_exists('nextgen_enhancer')) {
		class nextgen_enhancer {

			var $table_version = "4";
			var $table_name = "";
			var $album_item_srcstrings = array ();
			var $album_item_repstrings = array ();
			var $ngg_options = array ();
			var $options = array ();
			var $options_page = "";
			var $options_page_url = "";
			var $num_records = 0;

			// Option defaults. More in the constructor
			var $option_defaults = array (
				"page_prefix"          => "",
				"page_suffix"          => "",
				"exiftool_path"        => "/usr/bin/exiftool",
				"copy_exif"            => "yes",
				"keep_xmlfile"         => "yes",
				"keep_xmpfile"         => "",
				"manage_description"   => "",
				"hide_ngg_description" => "",
				"description_template" => "{filename} {maplink} {fullsizelink} :: {caption}<br/>{created}<br/>Camera: {camera}<br/>Exposure: {shutterspeed} at {iso} ISO. Focal length: {focallength}<br/><br/>&copy; {copyright}<br/>",
				"desc_video_template" => "{filename} :: {caption}<br/>{created}<br/><br/>&copy; {copyright}<br/><br/>{lightviewoptions}",
				"support_video"         => "",
				"video_regexp"         => '/\.(mp4|flv)\.jpg$/i',
				"video_player_href"    => "/lib/jwplayer/player.swf?file={fileref}&autostart=true&provider=http",
				"video_extra_vheight"  => 0,
				"video_max_height"     => 1080,
				"use_tzzbox"           => "",
			);

			/**
			 * Constructor
			 */
			function __construct ()
			{
				global $wpdb;
				$this -> table_name = $wpdb->prefix . "ngg_enhancer";
				$this -> ngg_options = get_option('ngg_options');
				$this -> options = get_option('nextgen_enhancer_options');

				$sql = "SELECT COUNT(id) FROM ". $this -> table_name;
				$this -> num_records = $wpdb -> get_var($sql);

				// Some more option defaults
				$name = get_option('blogname');
				if (strlen($name) == 0) $name = "John Doe";
				$this -> option_defaults ["default_copyright"] = "%Y $name";
				$this -> option_defaults ["table_version"]     = $this -> table_version;

				// Bootstrap
				$this -> add_actions ();

				if (is_admin ()) {
					session_start(); // We use the session to pass information between pages
					$this -> add_admin_actions ();
				}
			}

			/**
			 * Add actions and filters.
			 */
			function add_actions ()
			{
				// This action is called every time plugins get loaded
				add_action ('plugins_loaded', array (&$this, 'plugins_loaded'));

				// Load nextgen-tzzbox.js
				add_action ('wp_print_scripts', array (&$this, 'load_scripts'));
				add_action ('wp_enqueue_scripts', array (&$this, 'load_styles'));

				// Filter to set up separate image and video counters for display in album view
				add_filter ('ngg_album_galleryobject', array (&$this, 'ngg_album_galleryobject'));

				// Filter to add some javascript to the gallery view
				add_filter ('ngg_show_gallery_content', array (&$this, 'ngg_show_gallery_content'), 20, 2);

				// Filter to replace the item counter in view/album-extend.php
				add_filter ('ngg_show_album_content', array (&$this, 'ngg_show_album_content'), 20, 2);

				// Filter to replace the link on videos, when video support is enabled
				add_filter ('ngg_create_gallery_link', array (&$this, 'ngg_create_gallery_link'), 10, 2);

				// Filter to add GPS information to image metadata
				add_filter ('ngg_get_image_metadata', array (&$this, 'ngg_get_image_metadata'), 10, 2);

				// Filter to replace the thumbnail effect code, when Tzzbox is enabled
				add_filter ('ngg_get_thumbcode', array (&$this, 'ngg_get_thumbcode'), 10, 2);

				// This hook is called upon activation of the plugin
				register_activation_hook(__FILE__, array (&$this, 'nextgen_enhancer_install'));

				// Shortcode for album pages navbar
				add_shortcode ('nggenav', array (&$this, 'shortcode_nggenav'));
			}

			/**
			 * Add actions for the admin pages
			 */
			function add_admin_actions ()
			{
				add_action ('admin_menu', array (&$this, 'admin_menu'));
				add_action ('admin_init', array (&$this, 'admin_init'));

				// Handle action 'ngg_added_new_image'
				add_action ('ngg_added_new_image', array (&$this, 'ngg_added_new_image'));
				//
				// These actions are called just before deleting a picture or a gallery
				add_action ('ngg_delete_picture', array (&$this, 'ngg_delete_picture'));
				add_action ('ngg_delete_gallery', array (&$this, 'ngg_delete_gallery'));

				// This action is called just after an image is updated in the database
				add_action ('ngg_image_updated', array (&$this, 'ngg_image_updated'));

				# Do the javascript for the 'manage gallery' page
				add_action ('admin_print_scripts-gallery_page_nggallery-manage-gallery', array (&$this, 'admin_print_scripts'));

				# Do the javascript for the options page
				//add_action ('admin_print_scripts-admin_page_'. $this -> options_page, array (&$this, 'load_optionpage_scripts'));
				//add_action ('admin_print_scripts-admin_page_nextgen-enhancer-admin-menu', array (&$this, 'load_optionpage_scripts'));
				add_action ('admin_print_scripts', array (&$this, 'load_optionpage_scripts'));

				// Run a hook after updating all images.
				add_action ('ngg_update_gallery', array (&$this, 'ngg_update_gallery'));

				// Display admin notices
				add_action ('admin_notices', array (&$this, 'admin_notices'));

				// Add some HTML to the 'manage gallery' page
				add_action ('admin_footer', array (&$this, 'admin_footer'));

				// Print a warning about global option, if appropriate
				add_action ('ngg_manage_gallery_settings', array (&$this, 'ngg_manage_gallery_settings'));

				add_action ('wp_ajax_ngg_ajax_operation', array (&$this, 'wp_ajax_ngg_ajax_operation_early'), 8);
				add_action ('wp_ajax_ngg_ajax_operation', array (&$this, 'wp_ajax_ngg_ajax_operation_late'), 12);

				// Filter to run before adding a new image to the database
				add_filter ('ngg_pre_add_new_image', array (&$this, 'ngg_pre_add_new_image'), 10, 2);

				// Filter 'ngg_add_new_page' to modify new page content
				add_action ('ngg_add_new_page', array (&$this, 'ngg_add_new_page'), 10, 2);
			}

			/**
			 * Handler for 'plugins_loaded'.
			 */
			function plugins_loaded ()
			{
				$this -> lightview_to_meta ();
				$this -> check_update_db ();
			}

			/**
			 * Check if the database schema is the correct version and upgrade if necessary.
			 */
			function check_update_db ()
			{
				global $wpdb;

				// Upgrade table if necessary. Add upgrade SQL statements here, and
				// update $table_version at the top of the file
				$upgrade_sql = array ();
				$upgrade_sql[2] = "ALTER TABLE ". $this -> table_name ." CHANGE `description` `caption` TEXT NOT NULL";
				$upgrade_sql[3] = "ALTER TABLE ". $this -> table_name ." ADD `metadata` VARCHAR( 255 ) NOT NULL";
				$upgrade_sql[4] = "ALTER TABLE ". $this -> table_name ." DROP `lightview_opts`";

				$installed_version = $this -> options ['table_version'];
				if ($installed_version != $this -> table_version) {
					for ($i = $installed_version + 1; $i <= $this -> table_version; $i++) {
						$wpdb->query ($upgrade_sql [$i]);
					}
				}
				$this -> update_option ('table_version', $this -> table_version);
			}

			function lightview_to_meta ()
			{
				global $wpdb;

				if ($this -> options ['table_version'] < 4) {
					$sql = "SELECT pid, lightview_opts FROM ". $this -> table_name . " WHERE lightview_opts != '' AND metadata = ''";
					$res = $wpdb -> get_results ($sql, ARRAY_A);
					foreach ($res as $row) {
						$pid = $row ['pid'];
						$opts = explode (',', $row ['lightview_opts']);
						$enhancer_meta = array ();
						foreach ($opts as $o) {
							list ($k, $v) = explode (':', $o);
							if (trim ($k) == "height") $v = (int) $v - 30;
							$enhancer_meta [ trim ($k) ] = trim ($v);
						}
						$data = array (
							'metadata'			 => serialize ($enhancer_meta),
						);
						$where = array ('pid' => $pid);
						$wpdb -> update ($this -> table_name, $data, $where);
					}
				}
			}

			/**
			 * Function to update options. Set the value in the options array and
			 * write the array to the database.
			 */
			function update_option ($option, $value)
			{
				$this -> options [$option] = $value;
				update_option ('nextgen_enhancer_options', $this -> options);
			}

			/**
			 * Handler for 'wp_print_scripts'.
			 * Load scripts.
			 */
			function load_scripts ()
			{
				if (isset ($this -> options) && $this -> options ["use_tzzbox"] == "yes") {
					wp_enqueue_script ('nextgen-tzzbox',      plugins_url('tzzbox.js', __FILE__));
					//wp_enqueue_script ('nextgen-tzzboxinit',  plugins_url('nextgen-tzzbox.js', __FILE__));
					wp_enqueue_script ('nextgen-jwplayer',  plugins_url('jwplayer/jwplayer.js', __FILE__));
				}
			}

			/**
			 * Handler for 'wp_enqueue_scripts'.
			 * Load stylesheets.
			 */
			function load_styles ()
			{
				if (isset ($this -> options) && $this -> options ["use_tzzbox"] == "yes") {
					wp_register_style  ('nextgen-tzzbox',   plugins_url('tzzbox.css', __FILE__));
					wp_enqueue_style ('nextgen-tzzbox');
					wp_register_style  ('nextgen-bs-glyph', plugins_url('bs-glyph.css', __FILE__));
					wp_enqueue_style ('nextgen-bs-glyph');
				}
			}

			/**
			 * Handler for 'admin_print_scripts'.
			 * Load admin scripts.
			 */
			function admin_print_scripts ()
			{
				$this -> load_admin_scripts ();
				$this -> javascript_captions ();
			}

			/**
			 * Handler for 'wp_print_scripts'.
			 * Load admin scripts.
			 */
			function load_admin_scripts ()
			{
				wp_enqueue_script ('nextgen-admin', plugins_url('nextgen-admin.js', __FILE__));
			}

			/**
			 * Handler for 'wp_print_scripts'.
			 * Load option page scripts.
			 */
			function load_optionpage_scripts ()
			{
				wp_enqueue_script ('nextgen-admin', plugins_url('nextgen-options.js', __FILE__));
			}

			/**
			 * Handler for 'ngg_added_new_image'.
			 */
			function ngg_added_new_image ($image) {

				// Add images to enhanced table
				$this -> add_to_enhanced_table ($image);

				// Set lightview options
				$this -> set_video_metadata ($image);

				// Restore XMP data if needed
				$this -> load_xmp_data ($image);

				if (isset ($this -> options  ['manage_description']) && $this -> options  ['manage_description'] == "yes") {
					$id = $image ['id'];
					$this -> update_description ($id);
				}
			}

			/**
			 * Add new image to enhanced table
			 */
			function add_to_enhanced_table ($image) {
				global $wpdb;
				$copy = strftime ($this -> options ['default_copyright']);
				$sql = $wpdb->prepare("INSERT INTO ". $this -> table_name. " (id, pid, copyright) VALUES (null, %s, %s)", $image['id'], $copy);
				$wpdb->query($sql);
			}

			/**
			 * Delete an image from the enhanced table
			 */
			function delete_from_enhanced_table ($pid)
			{
				global $wpdb;
				$sql = $wpdb->prepare("DELETE FROM ". $this -> table_name. " WHERE pid=%d", $pid);
				$num = $wpdb->query($sql);
				if ($num == 1) return true;
				else return false;
			}

			/**
			 * For videos, load the XML file that contains the resolution and add
			 * options for lightview to the database. Also, update the meta-information
			 * for the thumbnail image with the 'mtime' data for the video.
			 */
			function set_video_metadata ($image) {
				global $wpdb;

				// For files matching the video regexp from the settings, import the video properties
				// $image = array( 'id' => $pic_id, 'filename' => $picture, 'galleryID' => $galleryID);

				$ext = substr($image ['filename'], -8);

				// Only work if we have a regexp available
				if (isset ($this -> options ['video_regexp']) && ($re = $this -> options ['video_regexp']) != "") {

					$n = @preg_match($re, $image ['filename'], $matches);

					if ($n == 1) {

						$sql = "SELECT p.pid, p.filename, p.meta_data, g.path FROM ".$wpdb->prefix . "ngg_pictures p INNER JOIN ".
							$wpdb->prefix . "ngg_gallery g ON p.galleryid = g.gid WHERE p.pid='". $image['id']. "'";
						$dbimage = $wpdb->get_row ($sql, ARRAY_A);
						$pid = $dbimage ['pid'];
						$meta = unserialize ($dbimage ['meta_data']);

						$gallerydir = ABSPATH . $dbimage['path'];
						$xmlfile = $gallerydir .'/' .str_replace('.jpg', '.xml', $image ['filename']);

						if (file_exists ($xmlfile)) {
							$xml = simplexml_load_file ($xmlfile);
							$width = (int) $xml -> flv -> width;
							$height = (int) $xml -> flv -> height;
							$mtime = (string) $xml -> flv -> mtime;
						}
						else {
							$width = 640;
							$height = 480;
							$mtime = 0;
						}

						$enhancer_meta = array ("width" => $width, "height" => $height);
						$data = array (
							'metadata'       => serialize ($enhancer_meta)
						);
						$where = array ('pid' => $pid);
						$wpdb -> update ($this -> table_name, $data, $where);

						if (strlen($mtime)) {
							$meta ['created_timestamp'] = date ('l j F Y H:i:s', strtotime ($mtime));
							$data = array ('meta_data' => serialize ($meta));
							$wpdb -> update ($wpdb->prefix . "ngg_pictures", $data, $where);
						}
					}
				}
			} // function set_video_metadata

			/**
			 * Load data from XMP file back to the image
			 */
			function load_xmp_data ($image)
			{
				//$image = array( 'id' => $pic_id, 'filename' => $picture, 'galleryID' => $galleryID);

				if ($this -> options ['copy_exif'] == "yes") {
					$im = nggdb::find_image ($image['id']);
					$picture = $im->imagePath;
					$this -> exiftool_save_load_xmp ($picture, "load");
				}
			}

			/**
			 * Save data from image to XMP file
			 */
			function save_xmp_data ($image)
			{
				//$image = array( 'id' => $pic_id, 'filename' => $picture, 'galleryID' => $galleryID);

				if ($this -> options ['copy_exif'] == "yes") {
					$im = nggdb::find_image ($image['id']);
					$picture = $im->imagePath;
					$this -> exiftool_save_load_xmp ($picture, "save");
				}
			}

			/**
			 * This function can save tags from an image to an XMP file and vice versa.
			 * After loading the XMP file back to an image, the XMP file can be deleted,
			 * depending on the value of 'nextgen_enhancer_keep_xmpfile'.
			 */
			function exiftool_save_load_xmp ($picture, $action) {

				$exiftool   = escapeshellcmd ($this -> options ['exiftool_path']);
				$keep_xmp   = $this -> options ['keep_xmpfile'];
				$path_parts = pathinfo( $picture );
				$xmp        = str_replace($path_parts ['extension'], 'xmp', $picture);
				$delete     = false;
				$overwrite  = false;

				if (is_executable ($exiftool)) {
					if ($action == "save") {
						$from = $picture;
						$to   = $xmp;
					}
					elseif ($action == "load") {
						$from = $xmp;
						$to   = $picture;
						$overwrite  = true;
						if ($keep_xmp != "yes") {
							$delete = true;
						}
					}
					if ($from != $to) { // security measure
						if (!file_exists ($to) || $overwrite) {
							exec ($exiftool ." -Tagsfromfile ". escapeshellarg ($from) .' -overwrite_original ' . escapeshellarg ($to));
						}
						if ($delete) {
							@unlink ($xmp);
							@unlink ($xmp. "_original");
						}
					}
				}
			}

			/**
			 * Handler for 'ngg_show_album_content' filter.
			 */
			function ngg_show_album_content ($content, $id)
			{
				if (isset ($this -> options ["support_video"]) && $this -> options ["support_video"] == "yes") {

					return str_replace($this -> album_item_srcstrings, $this -> album_item_repstrings, $content)."\n". $jwplayer;
				}
				else {
					return $content;
				}
			}

			/**
			 * Handler for 'ngg_create_gallery_link' filter.
			 * This function checks if the filename of the link matches the video regexp (if available)
			 * and if it does, replaces the link to point at the flash player, as specified in the
			 * video_player_href option.
			 */
			function ngg_create_gallery_link ($link, $picture)
			{
				if (isset ($this -> options ["support_video"]) && $this -> options ["support_video"] == "yes") {

					// Only work if we have a regexp available
					if (isset ($this -> options ['video_regexp']) && ($re = $this -> options ['video_regexp']) != "") {

						$n = @preg_match($re, $picture -> filename, $matches);
						if ($n == 1) {

							$path_parts = pathinfo( $link );
							$fileref = $path_parts ['dirname'] . "/" . $path_parts ['filename'];
							$player = htmlspecialchars ($this -> options ['video_player_href']);
							$link = str_replace('{fileref}', $fileref, $player);

						}
					}
				}
				return $link;
			}

			/**
			 * Handler for 'ngg_get_image_metadata' filter.
			 * This function expands the image metadata that NGG stores
			 */
			function ngg_get_image_metadata ($meta, $pdata) {
				if ( isset($pdata->exif_data['GPS']) ) {
					$exif = $pdata->exif_data['GPS'];
					if (!empty($exif['GPSLatitudeRef']))
						$meta['common']['GPSLatitudeRef'] = trim( $exif['GPSLatitudeRef'] );
					if (!empty($exif['GPSLatitude']))
						$meta['common']['GPSLatitude'] = $exif['GPSLatitude'];
					if (!empty($exif['GPSLongitudeRef']))
						$meta['common']['GPSLongitudeRef'] = trim( $exif['GPSLongitudeRef'] );
					if (!empty($exif['GPSLongitude']))
						$meta['common']['GPSLongitude'] = $exif['GPSLongitude'];
					if (!empty($exif['GPSAltitudeRef']))
						$meta['common']['GPSAltitudeRef'] = trim( $exif['GPSAltitudeRef'] );
					if (!empty($exif['GPSAltitude']))
						$meta['common']['GPSAltitude'] = trim( $exif['GPSAltitude'] );
				}
				return $meta;
			}

			function ngg_get_thumbcode ($thumbcode, $picture)
			{
				if (isset ($this -> options ["use_tzzbox"]) && $this -> options ["use_tzzbox"] == "yes") {
					//$thumbcode = 'class="tzzbox" rel="set[' . $picture -> name . ']"';
					$thumbcode = 'class="shutterset_'. $picture -> name .'"';
				}
				return $thumbcode;
			}

			/**
			 * Handler for 'ngg_show_gallery_content'.
			 */
			function ngg_show_gallery_content ($content, $gid)
			{
				$gallery = nggdb::find_gallery ($gid);
				$path = htmlspecialchars (addslashes (get_site_url (). "/". $gallery -> path));
				$player = htmlspecialchars (addslashes ($this -> options ['video_player_href']));
				$jwplayer = "";

				$hdr = (strlen ($gallery -> galdesc) ? "<h3>". $gallery -> galdesc . "</h3>\n" : "");

				return $hdr . $content;

			}

			/**
			 * Handler for 'ngg_album_galleryobject' filter. Find out the number of images and videos in all
			 * galleries and set up source and replacement strings for the template.
			 */
			function ngg_album_galleryobject ($gallery)
			{
				global $wpdb;

				// Improve performance when video support is turned off
				if (isset ($this -> options) && $this -> options ["support_video"] == "yes") {

					// Only work if we have a regexp available
					if (isset ($this -> options ['video_regexp']) && ($re = $this -> options ['video_regexp']) != "") {

						// This is dumb as fuck, but since there is no PCRE support in MySQL, and POSIX regexp support
						// in PHP is deprecated as of version 5.3, and since we do not rely on the description starting
						// with 'Video' anymore, we have to iterate over the result set to find the number of videos.

						$sql = "SELECT filename FROM ". $wpdb->prefix . "ngg_pictures WHERE galleryid ='$gallery->gid' AND exclude != 1";
						$filenames = $wpdb -> get_col($sql);

						$gallery -> videocounter = 0;

						foreach ($filenames as $f) {
							$n = @preg_match($re, $f, $matches);
							if ($n == 1) {
								$gallery -> videocounter++;
							}
						}

						$total = $gallery -> counter;
						$gallery -> imagecounter = $gallery -> counter - $gallery -> videocounter;

						$this -> album_item_srcstrings [$gallery->gid] = "<p><strong>".$gallery -> counter."</strong> Photos</p>"; // only works for English
						$this -> album_item_repstrings [$gallery->gid] = "<p><strong>$total</strong> items (<strong>".
							$gallery -> imagecounter . "</strong> images and <strong>" . $gallery -> videocounter . "</strong> videos)</p>";
					}
				}

				return $gallery;
			}

			/**
			 * Handler for 'ngg_pre_add_new_image' filter.
			 */
			function ngg_pre_add_new_image ($picture, $galleryID)
			{
				global $wpdb;

				if ($this -> options ['copy_exif'] == "yes") {
					$sql = "SELECT path FROM ". $wpdb->prefix . "ngg_gallery  WHERE gid='$galleryID'";
					$path = $wpdb->get_var($sql);
					$image = ABSPATH . $path . "/" . $picture;

					$this -> exiftool_save_load_xmp ($image, "save");
				}
				return $picture;
			}

			/**
			 * Handler for 'ngg_delete_picture'. This hook is called when a single picture is deleted
			 * via the 'delete' link (rather than through the bulk actions delete function).
			 */
			function ngg_delete_picture ($id)
			{
				$image = nggdb::find_image ($id);
				if (isset ($image -> pid)) {
					$this -> delete_from_enhanced_table (strval ($image -> pid));
				}
			}

			/**
			 * Handler for 'ngg_delete_gallery'.
			 */
			function ngg_delete_gallery ($id)
			{
				global $wpdb;

				$gallery = nggdb::find_gallery ($id);

				$imagelist = $wpdb -> get_col ("SELECT filename, pid FROM ". $wpdb->prefix . "ngg_pictures WHERE galleryid = '$gallery->gid'");
				$pidlist   = $wpdb -> get_col (null, 1);
				if (is_array($imagelist)) {
					foreach ($imagelist as $filename) {
						$path_parts = pathinfo( $filename );
						$xmp = str_replace($path_parts ['extension'], 'xmp', $filename);
						@unlink($gallery->abspath .'/'. $xmp);
						@unlink($gallery->abspath .'/'. $xmp. "_original");
					}
				}
				$rangestr = "('" . implode("','", $pidlist) . "')";
				$sql = "DELETE FROM ". $this -> table_name . " WHERE pid IN $rangestr";
				$wpdb -> query ($sql);
			}

			/**
			 * Handler for 'ngg_image_updated'.
			 */
			function ngg_image_updated ($image)
			{
				global $wpdb;
				$id = intval ($image->pid);

				$captions =  (isset($_POST['caption']) ? $_POST['caption'] : array());
				$c1 = (isset ($captions [$id]) ? $captions [$id] : false);

				$copyright =  (isset($_POST['copyright']) ? $_POST['copyright'] : array());
				$c2 = (isset ($copyright [$id]) ? $copyright [$id] : false);

				$enhancer =  (isset($_POST['enhancer']) ? $_POST['enhancer'] : array());
				$c3 = (isset ($enhancer [$id]) ? $enhancer [$id] : false);

				// If the 'Enhancer Enabled' checkbox is off, delete the image from the database
				if ($c3 === false) {
					$sql = $wpdb -> prepare ("DELETE FROM ". $this->table_name . " WHERE pid=%d", $id);
					$wpdb -> query ($sql);
				}
				elseif ($c1 !== false || $c2 !== false) {

					// See if the image is present in the database
					$sql = $wpdb -> prepare ("SELECT pid FROM ". $this -> table_name ." WHERE pid=%d", $id);
					$p = $wpdb -> get_var ($sql);

					// If image is not present, add it with default values
					if (is_null ($p)) {
						$copy = strftime ($this -> options ['default_copyright']);
						$sql = $wpdb -> prepare ("INSERT INTO ". $this -> table_name . " (pid, copyright, caption) SELECT pid, %s, description FROM ".
							$wpdb -> prefix . "ngg_pictures WHERE pid = %d", $copy, $id);
						$r2 = $wpdb -> query ($sql);

						// If caption or copyright was not supplied, we should keep the default
						// This is a crappy solution, but the alternative is to make the 'UPDATE' query do different
						// things in different situations, which is also crappy
						if (!strlen($c1)) {
							$sql = $wpdb -> prepare ("SELECT caption FROM ". $this -> table_name ." WHERE pid=%d", $id);
							$c1 = $wpdb -> get_var ($sql);
						}
						if (!strlen($c2)) {
							$sql = $wpdb -> prepare ("SELECT copyright FROM ". $this -> table_name ." WHERE pid=%d", $id);
							$c2 = $wpdb -> get_var ($sql);
						}
					}

					// Update the image in the database with posted values
					$sql = $wpdb -> prepare ("UPDATE ". $this->table_name . " SET caption=%s, copyright=%s WHERE pid=%d", $c1, $c2, $id);
					$n = $wpdb -> query ($sql);

					if ($n == 1 && isset ($this -> options  ['manage_description']) && $this -> options  ['manage_description'] == "yes") {
						$this -> update_description ($id);
					}
				}
			}

			/**
			 * Get the image captions for all images on the current page from the enhanced table
			 * and put them as a JavaScript variable in the header of the page.
			 */
			function javascript_captions () {

				global $wpdb;

				if (isset ($_GET['gid'])) $act_gid  = (int) $_GET['gid'];
				else return;

				// This code is copied from NextGEN Gallery, admin/manage-images.php, from line 48 onward
				// Except $nggdb->get_gallery is now called statically and some variables are named differently

				// look for pagination
				if ( ! isset( $_GET['paged'] ) || $_GET['paged'] < 1 )
					$_GET['paged'] = 1;

				$start = ( $_GET['paged'] - 1 ) * 100;

				// get picture values
				$nggdb = new nggdb();
				$picturelist = $nggdb->get_gallery($act_gid, $this -> ngg_options['galSort'], $this -> ngg_options['galSortDir'], false, 100, $start );

				$ids = array_keys ($picturelist);
				$rangestr = "('" . implode("','", $ids) . "')";
				$sql = "SELECT pid, caption, copyright FROM ". $this -> table_name . " WHERE pid IN $rangestr";
				$res = $wpdb -> get_results ($sql, ARRAY_A);
				// Do we need an array_walk ($res, 'htmlspecialchars') or something like this here?
				echo "<script type='text/javascript'>\n".
					"\tvar captions = ". json_encode ($res) .";\n";

				// Also set a variable that tells the Javascript to hide NextGEN's original description box.
				echo "\tvar hide_ngg_description = ". ($this -> options ['hide_ngg_description'] == "yes" ? "true" : "false") . ";\n";

				echo "</script>\n";

			}

			/**
			 * Handler for 'ngg_update_gallery'.  This runs after updating images.
			 * We use it to re-run 'javascript_captions', to overwrite the first
			 * invocation, which contains the old values. This is ugly, because
			 * now 'var captions = ...' is declared twice in the page. Unfortunately,
			 * 'ngg_image_updated' runs after 'admin_print_scripts', so I see no
			 * other way.
			 */
			function ngg_update_gallery ($gid, $post)
			{
				$this -> javascript_captions ();
			}

			/**
			 * Installer function. This runs when the plugin in activated and installs
			 * the database table and sets default option values
			 */
			function nextgen_enhancer_install ()
			{
				global $wpdb;

				$sql = "CREATE TABLE IF NOT EXISTS ". $this -> table_name. " (
					`id` bigint(20) NOT NULL AUTO_INCREMENT,
					`pid` bigint(20) NOT NULL,
					`copyright` varchar(128) NOT NULL,
					`caption` text NOT NULL,
					`metadata` varchar(255) NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `pid` (`pid`)
					) ENGINE=InnoDB COLLATE utf8_general_ci;";

				$wpdb->query ($sql);

				// Add options to the database
				$this -> add_options ();
			}

			function add_options()
			{
				add_option('nextgen_enhancer_options', $this -> option_defaults);
				$this -> options = get_option('nextgen_enhancer_options');
			}

			function options_page_html ()
			{
				if (!current_user_can('manage_options'))  {
					wp_die( __('You do not have sufficient permissions to access this page.') );
				}

				echo <<<EOF
		<div class="wrap">
			<h2>NextGEN Enhancer Options</h2>
			<h3>About NextGEN Enhancer</h3>
			NextGEN Enhancer is a plugin creates some new features for NextGEN Gallery by Alex Rabe. It is of no use without that.
			Its features:
			<ul>
				<li></li>
				<li>- NextGEN gallery has a <i>description</i> field for every image, which represents the caption of the image
				when displayed. This field cannot be automatically managed. NextGEN Enhancer uses a separate table with extra information
				about images, like copyright information, the actual caption, etc. to automatically assemble the NextGEN Gallery
				description. It can also automatically add information from the pictures meta-data (EXIF), for example create a link
				to Google Maps using the geotags from the image.</li>
				<li>- Support for Videos in addition to Photos</li>
				<li>- When NextGEN gallery is set to use the GD library for file operations, like resizing, rotating, etc., meta-data (EXIF)
				from the original image is lost. NextGEN Enhancer can save the data to a separate file, and restore it after the file
				operations have completed. It uses <i><a href="http://www.sno.phy.queensu.ca/~phil/exiftool/" target="_blank">exiftool</a></i>
				by Phil Harvey for that. The path to <i>exiftool</i> can be specified below. When NextGEN gallery is set to use ImageMagick,
				the meta-data is automatically kept, so this feature can be disabled.</li>
			</ul>
			<br />
			<hr />
EOF;
				if (is_plugin_active('nextgen-gallery/nggallery.php') ||
						is_plugin_active('nextgen-one-reloaded/nggallery.php')) {
					echo "You seem to have NextGEN Gallery active. Good.<br />";
				}
				else {
					echo "<strong>WARNING:</strong> You don't seem to have the NextGEN Gallery plugin active. Activating NextGEN Enhancer is a waste of resources.";
				}
		echo <<<EOF
			<hr />
			<form id="nextgen-enhancer-options" name="nextgen-enhancer-options" action="options.php" method="post">
EOF;

				settings_fields( 'nextgen-enhancer-options' );
				do_settings_sections('nextgen-enhancer');

				$warning = "Your NextGEN Enhancer table is empty, so you can safely go ahead.";
				if ($this -> num_records > 0) {
					$warning = "<strong>WARNING</strong>: You already have ". $this -> num_records ." records in NextGEN Enhancer's table.
					 	Pressing the 'prime' button below will reinitialize the table and reset captions and
						copyright information. Be careful!";
				}

				echo <<<EOF
				<p class="submit">
					<input type="submit" name="submit" value="Update Options" />
				</p>
			</form>
			<hr />
			<h3>Globally update descriptions</h3>
			If you changed the template for your image descriptions, just enabled the automatic management
			of the description field, or changed the 'extra height' setting, it may be necessary to update
			the description of all items in all galleries at once. Press the button below to do this. Images
		 	or videos for which NextGEN Enhancer is disabled (this means items for which no record is present
			in NextGEN Enhancer's database table) will be left alone.<br /><br />
			You can do this on a per-gallery basis via <i>Bulk actions</i> on a gallery management page.<br /><br />
			<form method="post" action="admin-post.php" id="global_description_form">
				<input type="hidden" name="action" value="global_description" />
				<input class="button-secondary" type="submit" id="global_description_button" value="Globally update descriptions" />
			</form>
			<br />
			<hr />
			<h3>Prime database</h3>
			NextGEN Enhancer assumes that NextGEN Gallery's <i>description</i> is used to describe the contents of
		 	your pictures. NextGEN Enhancer calls this the <i>caption</i>. By pressing the button below, NextGEN Enhancer's
			database table is primed from NextGEN Gallery's pictures table. You normally only have to do this <strong>
			ONCE</strong> after installing this plugin, or you may lose data. The following happens:
			<ul>
				<li></li>
				<li>- Each image present in NextGEN Gallery will get an entry for NextGEN Enhancer</li>
				<li>- The <i>copyright</i> field for each image is set to the value of the copyright setting above</li>
				<li>- The description of each image is used as the <i>caption</i> in NextGEN Enhancer</li>
			</ul><br />
			$warning<br /><br />
			<form method="post" action="admin-post.php" id="prime_database_form">
				<input type="hidden" name="action" value="prime_database" />
				<a id="prime_database_button" class="button-secondary" href="#" title="Prime database table">
					Prime database table
				</a>
			</form>
		</div>
EOF;
			/*
			var_dump ($this -> options_page);
			var_dump (menu_page_url( $this -> options_page, false));
			var_dump (get_plugin_page_hookname ('nextgen-enhancer-admin-menu', 'nextgen-enhancer'));
			*/

			}

			function settings_html ()
			{
				add_settings_field ('nextgen_enhancer_default_copyright','Default copyright',
						array (&$this, 'default_copyright_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_page_prefix','Gallery page prefix',
						array (&$this, 'page_prefix_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_page_suffix','Gallery page suffix',
						array (&$this, 'page_suffix_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_copy_exif', 'Retain EXIF data with exiftool',
						array (&$this, 'copy_exif_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_exiftool_path', 'Exiftool path',
						array (&$this, 'exiftool_path_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_keep_xmpfile', 'Keep Exiftool XMP file',
						array (&$this, 'keep_xmpfile_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_manage_description', 'Automatically manage NextGEN\'s <i>description</i> field',
						array (&$this, 'manage_description_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_hide_ngg_description', 'Hide NextGEN\'s <i>description</i> field',
						array (&$this, 'hide_ngg_description_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_description_template', 'Template for <i>description</i> field for images',
						array (&$this, 'description_template_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_desc_video_template', 'Template for <i>description</i> field for videos',
						array (&$this, 'desc_video_template_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_support_video', 'Video support',
						array (&$this, 'support_video_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_keep_xmlfile', 'Keep video XML file',
						array (&$this, 'keep_xmlfile_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_video_regexp', 'Regular expression for video files',
						array (&$this, 'video_regexp_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_video_player_href', 'HTML reference to Flash video player',
						array (&$this, 'video_player_href_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_video_extra_vheight', 'Extra height for video player',
						array (&$this, 'video_extra_vheight_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
				add_settings_field ('nextgen_enhancer_use_tzzbox', 'Use Tzzbox overlay effect',
						array (&$this, 'use_tzzbox_html'), 'nextgen-enhancer', 'nextgen-enhancer-main');
			}

			function default_copyright_html ()
			{
				$val = $this -> options ['default_copyright'];
				echo <<<EOT
					The default copyright on newly imported images, in <a href="http://php.net/strftime" target="_blank">strftime()</a> format.<br />
					<input type="text" size="25" name="nextgen_enhancer_options[default_copyright]" id="nextgen_enhancer_default_copyright" value="$val" autocomplete="off" />
EOT;
			}

			function page_prefix_html ()
			{
				$val = htmlspecialchars ($this -> options ['page_prefix']);
				echo <<<EOT
					Text inserted above the gallery when creating a new page for your gallery:<br />
					<textarea rows="3" cols="60" name="nextgen_enhancer_options[page_prefix]" id="nextgen_enhancer_page_prefix" autocomplete="off">$val</textarea>
EOT;
			}

			function page_suffix_html ()
			{
				$val = htmlspecialchars ($this -> options ['page_suffix']);
				echo <<<EOT
					Text inserted below the gallery when creating a new page for your gallery:<br />
					<textarea rows="3" cols="60" name="nextgen_enhancer_options[page_suffix]" id="nextgen_enhancer_page_suffix" autocomplete="off">$val</textarea>
EOT;
			}

			function exiftool_path_html ()
			{
				$val = $this -> options ['exiftool_path'];
				echo <<<EOT
					The path to <a href="http://www.sno.phy.queensu.ca/~phil/exiftool/" target="_blank">exiftool</a> by Phil Harvey. On Debian/Ubuntu, install
					<i>libimage-exiftool-perl</i>. The path then is <i>/usr/bin/exiftool</i>.<br />
					<input type="text" size="25" name="nextgen_enhancer_options[exiftool_path]" id="nextgen_enhancer_exiftool_path" value="$val" autocomplete="off" />
EOT;
			}

			function copy_exif_html ()
			{
				$val = (isset ($this -> options ['copy_exif']) ? $this -> options ['copy_exif'] : "");

				$libs = array ("im" => array ("name" => "ImageMagick", "advice" => "disable"), "gd" => array ("name" => "GD", "advice" => "enable"));
				$advice = "NextGEN Gallery has its graphic library set to <strong>". $libs [$this -> ngg_options['graphicLibrary']]['name']."</strong>, so you are ".
					"advised to <strong>". $libs [$this -> ngg_options['graphicLibrary']]['advice']. "</strong> this feature.<br />";

				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOT
					$advice
					<input type="checkbox" name="nextgen_enhancer_options[copy_exif]" id="nextgen_enhancer_copy_exif" value="yes" autocomplete="off" $ch />
					Uncheck to disable retaining EXIF metadata in modified images.
EOT;
			}

			function keep_xmlfile_html ()
			{
				$val = (isset ($this -> options ['keep_xmlfile']) ? $this -> options ['keep_xmlfile'] : "");
				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOT
					<input type="checkbox" name="nextgen_enhancer_options[keep_xmlfile]" id="nextgen_enhancer_keep_xmlfile" value="yes" autocomplete="off" $ch />
					NextGEN Enhancer can get video properties from an XML file. Uncheck this option to delete the XML file after reading it. It is usually
					better to keep this enabled.
EOT;
			}

			function keep_xmpfile_html ()
			{
				$val = (isset ($this -> options ['keep_xmpfile']) ? $this -> options ['keep_xmpfile'] : "");
				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOT
					<input type="checkbox" name="nextgen_enhancer_options[keep_xmpfile]" id="nextgen_enhancer_keep_xmpfile" value="yes" autocomplete="off" $ch />
					Check this to keep the temporary XMP file from Exiftool. Uncheck it to remove the file directly after use. If you intend to perform
					multiple modifications on your images, like resizing, rotating, etc., it may be a a good idea to keep them around, for performance and
					safety.
EOT;
			}

			function manage_description_html ()
			{
				$val = (isset ($this -> options ['manage_description']) ? $this -> options ['manage_description'] : "");
				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOT
					<input type="checkbox" name="nextgen_enhancer_options[manage_description]" id="nextgen_enhancer_manage_description" value="yes" autocomplete="off" $ch />
					Check this to automatically update NGG's <i>description</i> field when adding/editing images. If you enable this, you may want to enable the
					next option as well, to prevent confusion. If you leave this option disabled, you can still edit captions and copyright information, and update
					NextGEN Gallery's description field using a Bulk Action.
EOT;
			}
			function hide_ngg_description_html ()
			{
				$val = (isset ($this -> options ['hide_ngg_description']) ? $this -> options ['hide_ngg_description'] : "");
				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOT
					<input type="checkbox" name="nextgen_enhancer_options[hide_ngg_description]" id="nextgen_enhancer_hide_ngg_description" value="yes" autocomplete="off" $ch />
					Check this to hide the original <i>description</i> box for images when managing a gallery.
EOT;
			}

			function description_template_html ()
			{
				$val = htmlspecialchars ($this -> options ['description_template']);
				echo <<<EOT
					<textarea rows="3" cols="60" name="nextgen_enhancer_options[description_template]" id="nextgen_enhancer_description_template" autocomplete="off">$val</textarea>
EOT;
			}

			function desc_video_template_html ()
			{
				$val = htmlspecialchars ($this -> options ['desc_video_template']);
				echo <<<EOT
					<textarea rows="3" cols="60" name="nextgen_enhancer_options[desc_video_template]" id="nextgen_enhancer_desc_video_template" autocomplete="off">$val</textarea>
EOT;
			}

			function support_video_html ()
			{
				$val = (isset ($this -> options ['support_video']) ? $this -> options ['support_video'] : "");
				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOT
					<input type="checkbox" name="nextgen_enhancer_options[support_video]" id="nextgen_enhancer_support_video" value="yes" autocomplete="off" $ch />
					Check this to support playing videos with a flash player, like JW Player or Flowplayer, as well as management of video properties
					via NextGEN's <i>Manage gallery</i> pages.
EOT;
			}
			function video_regexp_html ()
			{
				$val = htmlspecialchars ($this -> options ['video_regexp']);
				$err = "";
				if (strlen ($val)) {
					$r = @preg_match ($val, 'x');
					if ($r === false) {
						$err = '<br /><strong>WARNING:</strong> the current value doesn\'t seem to be a valid <a href="http://php.net/pcre" target="_blank">PCRE</a>!';
					}
				}
				echo <<<EOT
					<input type="text" size="25" name="nextgen_enhancer_options[video_regexp]" id="nextgen_enhancer_video_regexp" autocomplete="off" value="$val" /><br />
					Files with a name matching this <a href="http://php.net/pcre" target="_blank">regular expression</a> will be marked and played as video files, if
					video support is enabled. $err
EOT;
			}

			function video_player_href_html ()
			{
				$val = htmlspecialchars ($this -> options ['video_player_href']);
				$upload = wp_upload_dir();
				$swf1 = $upload ['baseurl']."/jw-player-plugin-for-wordpress/player/player.swf";
				$swf2 = plugins_url () ."/fv-wordpress-flowplayer/flowplayer/flowplayer.swf";
				// Remove the 'scheme://hostname' part
				$swf1 = preg_replace ('~https?://[^/]+~', '', $swf1);
				$swf2 = preg_replace ('~https?://[^/]+~', '', $swf2);
				echo <<<EOT
					<input type="text" size="60" name="nextgen_enhancer_options[video_player_href]" id="nextgen_enhancer_video_player_href" autocomplete="off" value="$val" /><br />
					<br />
					Please use the tag <code>{fileref}</code> in the URL, where you want the path to the video file to appear. NextGEN Enhancer will replace it with the correct path.
					If you have the <a href="http://wordpress.org/extend/plugins/jw-player-plugin-for-wordpress/" target="_blank">JW Player for Wordpress plugin</a> installed (it
					doesn't have to be activated), a good value would be:<br />
					<code>$swf1?file={fileref}&autostart=true&provider=http</code><br />
					Another example, using the <a href="http://wordpress.org/extend/plugins/fv-wordpress-flowplayer/" target="_blank">FV Wordpress Flowplayer</a> plugin:<br />
					<code>$swf2?config={%22clip%22:{%22url%22:%22{fileref}%22}}</code>
EOT;
			}

			function video_extra_vheight_html ()
			{
				$val = htmlspecialchars ($this -> options ['video_extra_vheight']);
				echo <<<EOT
					<input type="text" size="4" name="nextgen_enhancer_options[video_extra_vheight]" id="nextgen_enhancer_video_extra_vheight" autocomplete="off" value="$val" /> pixels<br />
					When using an overlay like Lightview, extra height can be added to the content container. For example, if your video player has a control bar below the video,
					that takes 30 pixels, enter 30 here.
EOT;
			}

			function use_tzzbox_html ()
			{
				$val = (isset ($this -> options ['use_tzzbox']) ? $this -> options ['use_tzzbox'] : "");
				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOT
					<input type="checkbox" name="nextgen_enhancer_options[use_tzzbox]" id="nextgen_enhancer_use_tzzbox" value="yes" autocomplete="off" $ch />
					Check this to load the Tzzbox overlay effect, that comes with this plugin. You might call it a 'Lightbox clone' and it is designed to
					be a drop-in replacement for Lightview, at least in the way it is used.
EOT;
			}

			function admin_init ()
			{
				$this -> register_settings ();
				$this -> handle_bulk_update ();
			}

			/**
			 * A function to match an array of image IDs against the enhanced table
			 * and return only those that are present
			 */
			function match_enhanced_table ($imagelist)
			{
				global $wpdb;

				if (!is_array ($imagelist)) return array ();

				$rangestr = "('" . implode("','", $imagelist) . "')";
				$sql = "SELECT pid FROM ". $this -> table_name . " WHERE pid IN $rangestr";
				return $wpdb -> get_col ($sql);
			}

			function handle_bulk_update ()
			{
				if (isset ($_POST['page']) && $_POST['page'] == "manage-images") {
					if (isset ($_POST['bulkaction']) && isset ($_POST['doaction']))  {

						check_admin_referer('ngg_updategallery');

						switch ($_POST['bulkaction']) {
							case 'update_description':
								$imagelist = $this -> match_enhanced_table ($_POST ['doaction']);
								foreach ($imagelist as $pid) {
									$this -> update_description ($pid);
								}
								break;
						}
					}
				}
			}

			function register_settings ()
			{
				// Add settings and a settings section named 'nextgen-enhancer-main'
				// All options in one array
				register_setting ('nextgen-enhancer-options', 'nextgen_enhancer_options');
				add_settings_section('nextgen-enhancer-main', 'Settings', array (&$this, 'settings_html'),  'nextgen-enhancer');

				// A handler for the 'Prime Database Table' button
				// This is here, because admin-post.php first does do_action('admin_init'), which points here and we need
				// to register the follow-up action
				add_action ('admin_post_global_description', array (&$this, 'admin_post_global_description'));
				add_action ('admin_post_prime_database', array (&$this, 'admin_post_prime_database'));
				add_action ('admin_post_set_copyright', array (&$this, 'admin_post_set_copyright'));
				add_action ('admin_post_import_video_meta', array (&$this, 'admin_post_import_video_meta'));
			}

			function admin_menu ()
			{
				$page = add_options_page('NextGEN Enhancer Options', 'NextGEN Enhancer', 'manage_options', 'nextgen-enhancer-admin-menu', array (&$this, 'options_page_html'));
				$page = str_replace('admin_page_', '', $page);
				$this -> options_page = str_replace('settings_page_', '', $page);
				$this -> options_page_url = menu_page_url ($this -> options_page, false);
			}

			function admin_post_global_description ()
			{
				global $wpdb;
				$this -> admin_menu ();
				$_SESSION['nextgen_enhancer_errors'] = 0;
				$i = 0;
				$j = 0;

				$sql = "SELECT pid FROM ". $this -> table_name . " ORDER BY pid";
				$pids = $wpdb -> get_col ($sql);

				foreach ($pids as $id) {
					$i += $this -> update_description ($id);
					$j++;
				}
				$_SESSION['nextgen_enhancer_status_msg'] = "Description changed for $i out of $j items.";

				/*
				var_dump ($_SESSION);
				var_dump ($page_url);
				var_dump ($this);
				*/

				header ("Location: ". $this -> options_page_url);
			}

			function admin_post_prime_database ()
			{
				global $wpdb;
				$this -> admin_menu ();
				$page_url = menu_page_url ($this -> options_page, false);
				$copy = strftime ($this -> options ['default_copyright']);
				$_SESSION['nextgen_enhancer_errors'] = 0;

				$sql = "TRUNCATE TABLE ". $this -> table_name;
				$r1 = $wpdb -> query ($sql);

				if ($r1) {
					$sql = $wpdb -> prepare ("INSERT INTO ". $this -> table_name . " (pid, copyright, caption) SELECT pid, %s, description FROM ".
						$wpdb -> prefix . "ngg_pictures ORDER BY pid", $copy);
					$r2 = $wpdb -> query ($sql);

					if (intval($r2) > 0) {
						$_SESSION['nextgen_enhancer_status_msg'] = "Priming of database succeeded. NextGEN Enhancer's table now has $r2 records.";
					}
					else {
						$_SESSION['nextgen_enhancer_status_msg'] = "Priming of database seemed succesful, but no records were created. Don't you have any pictures in NextGEN Gallery?";
					}
				}

				if ($r1 === false || $r2 === false) {
					$_SESSION['nextgen_enhancer_status_msg'] = "SQL query '$sql' failed!";
					$_SESSION['nextgen_enhancer_errors'] = 1;
				}

				header ("Location: $page_url");
			}

			function admin_post_set_copyright ()
			{
				global $wpdb;

				$_SESSION['nextgen_enhancer_errors'] = 0;

				check_admin_referer('ngg_enhancer_copyright_form');

				$images = $this -> match_enhanced_table (explode (",", $_POST ["TB_imagelist"]));
				$rangestr = "('" . implode("','", $images) . "')";
				$sql = $wpdb -> prepare ("UPDATE ". $this -> table_name . " SET copyright=%s WHERE pid IN $rangestr", $_POST ["copyright"]);
				$r = $wpdb -> query ($sql);

				if ($r === false) {
					$_SESSION['nextgen_enhancer_status_msg'] = "SQL query '$sql' failed!";
					$_SESSION['nextgen_enhancer_errors'] = 1;
				}
				else {
					foreach ($images as $pid) {
						$this -> update_description ($pid);
					}
					$_SESSION['nextgen_enhancer_status_msg'] = "Copyright information set to '". htmlspecialchars($_POST ["copyright"]). "' for $r image(s).";
				}

				header ("Location: ". $_POST ["_wp_http_referer"]);

			}

			function admin_post_import_video_meta ()
			{
				global $wpdb;

				$_SESSION['nextgen_enhancer_errors'] = 0;

				check_admin_referer('ngg_enhancer_videometa_form');

				if (isset ($this -> options ['support_video']) && $this -> options ['support_video'] == "yes") {

					// Only work if we have a regexp available
					if (isset ($this -> options ['video_regexp']) && ($re = $this -> options ['video_regexp']) != "") {

						$images = $this -> match_enhanced_table (explode (",", $_POST ["TB_imagelist"]));
						$x = 0;
						foreach ($images as $pid) {
							$image = nggdb::find_image ($pid);
							$n = @preg_match($re, $image -> filename, $matches);
							if ($n == 1) {
								$i = array( 'id' => $pid, 'filename' => $image -> filename);
								$this -> set_video_metadata ($i);

								if (isset ($this -> options  ['manage_description']) && $this -> options  ['manage_description'] == "yes") {
									$this -> update_description ($pid);
								}

								$x++;
							}
							elseif ($n === false) {
								$_SESSION['nextgen_enhancer_status_msg'] = "Your video file regular expression seems broken. Please check your settings.";
								$_SESSION['nextgen_enhancer_errors'] = 1;
								break;
							}
						}
						if ($n !== false) {
							$_SESSION['nextgen_enhancer_status_msg'] = "Imported video meta data from XML for $x videos.";
						}
					}
					else {
						$_SESSION['nextgen_enhancer_status_msg'] = "Your don't have a video file regular expression set. Please check your settings.";
						$_SESSION['nextgen_enhancer_errors'] = 1;
					}
				}
				else {
					$_SESSION['nextgen_enhancer_status_msg'] = "NextGEN Enhancer's video support is disabled. Please check your settings.";
				}
				header ("Location: ". $_POST ["_wp_http_referer"]);
			}

			function admin_notices ()
			{
				global $wpdb;

				$message = false;
				$class = "updated";  // "error" for errors

				if (isset ($_SESSION['nextgen_enhancer_errors']) && $_SESSION['nextgen_enhancer_errors'] > 0) {
					$class = "error";
				}

				if (isset ($_SESSION['nextgen_enhancer_status_msg']) && strlen($message = $_SESSION['nextgen_enhancer_status_msg']) > 0) {
					echo "<div class='$class'><p>$message</p></div>";
					$_SESSION['nextgen_enhancer_status_msg'] = "";
					$_SESSION['nextgen_enhancer_errors'] = 0;
				}
			}

			function update_description ($pid)
			{
				global $wpdb;

				$sql = $wpdb -> prepare ("SELECT p.pid, p.galleryid, p.filename, p.alttext, p.imagedate, e.copyright, e.caption, e.metadata, p.meta_data FROM ".
					$wpdb -> prefix . "ngg_pictures p INNER JOIN ". $this -> table_name . " e ON p.pid = e.pid WHERE p.pid = %d", $pid);
				$row = $wpdb -> get_row ($sql, ARRAY_A);

				$replacements = array();
				$is_video = false;
				$meta = unserialize ($row ["meta_data"]);
				$enhancer_meta = unserialize ($row ["metadata"]);
				list ($gurl, $gpsc) = $this -> getmapurl($meta, '<img src="'.plugins_url('pin.png', __FILE__).'" style="vertical-align: middle" />');
				$gallery = nggdb::find_gallery ($row['galleryid']);

				if (substr ($row ['filename'], -8) == ".mp4.jpg" ||
						substr ($row ['filename'], -8) == ".flv.jpg") {
					$replacements ['{filename}'] = "Video " .str_replace (".jpg", "", $row ["filename"]);
					$desc = $this -> options ['desc_video_template'];
				}
				else {
					$replacements ['{filename}'] = "Photo ". $row ["filename"];
					$desc = $this -> options ['description_template'];
				}

				$replacements ['{maplink}'] = "";
				if ($gurl) $replacements ['{maplink}'] = "&nbsp; $gpsc";

				$replacements ['{fullsizelink}'] = "&nbsp; <a href=\"/photo/". $gallery -> name ."/".$row ['filename']."\" target=\"_blank\">full</a>";
				$replacements ['{caption}'] = htmlspecialchars ($row ['caption']);

				$replacements ['{captionline}'] = "";
				if (($t = $row ["caption"]) != "") {
					$replacements ['{captionline}'] = htmlspecialchars ($row ['caption']) . "<br />";
				}

				$replacements ['{copyright}'] = htmlspecialchars ($row ['copyright']);

				$replacements ['{created}'] = "unknown";
				if (($t = $meta ["created_timestamp"]) != "") {
					$replacements ['{created}'] = $t;
				}

				$replacements ['{camera}'] = "unknown";
				if (($t = $meta ["camera"]) != "") {
					if (($make = $meta ["make"]) != "") {
						$replacements ['{camera}'] = "$make ". str_replace ($make, "", $t);
					}
					else {
						$replacements ['{camera}'] = $t;
					}
				}

				$replacements ['{shutterspeed}'] = "unknown";
				if (($t = $meta ["shutter_speed"]) != "") {
					$replacements ['{shutterspeed}'] = $t;
				}

				$replacements ['{iso}'] = "unknown";
				if (($t = $meta ["iso"]) != "") {
					$replacements ['{iso}'] = $t;
				}

				$replacements ['{focallength}'] = "unknown";
				if (($t = $meta ["focal_length"]) != "") {
					$replacements ['{focallength}'] = $t;
				}

				$replacements ['{aperture}'] = "unknown";
				if (($t = $meta ["aperture"]) != "") {
					$replacements ['{aperture}'] = $t;
				}

				$replacements ['{lightviewoptions}'] = "";
				if (($m = $enhancer_meta) != "") {
					$height = $m ["height"];
					$width = $m ["width"];

					$maxheight = $this -> options ['video_max_height'];
					if ($maxheight && $height > $maxheight) {
						$width = (int) ($maxheight / $height) * $width;
						$height = $maxheight;
					}

					$extra_h = intval ($this -> options ['video_extra_vheight']);
					$height += $extra_h;

					$t = "width: " . $width .",";
					$t .= "height: " . $height;
					//	",".
					//$t .= "menubar: 'bottom'";
					$replacements ['{lightviewoptions}'] = " :: $t";
				}

				foreach ($replacements as $key => $value) {
					$desc = str_replace ($key, $value, $desc);
				}

				$sql = $wpdb -> prepare ("UPDATE ". $wpdb -> prefix . "ngg_pictures SET description = %s WHERE pid = %d", $desc, $pid);
				return $wpdb -> query ($sql);

			}

			function getmapurl ($meta, $linktxt=null)
			{
				$cstr = "Unknown";
				if ($linktxt) $cstr=$linktxt;
				$gurl = null;
				if ($meta['GPSLatitude']) {
					$latref = $meta['GPSLatitudeRef'];
					$lonref = $meta['GPSLongitudeRef'];
					list ($d0, $m0, $s0) = $meta['GPSLatitude'];
					list ($d1, $m1, $s1) = $meta['GPSLongitude'];

					// These evaluations transform '42/1' to 42, '609/100' to 6.09, etc.
					$d0 = eval ("return $d0;");
					$m0 = eval ("return $m0;");
					$s0 = eval ("return $s0;");
					$d1 = eval ("return $d1;");
					$m1 = eval ("return $m1;");
					$s1 = eval ("return $s1;");

					$lat = $d0 + ($m0 / 60) + ($s0 / 3600);
					$lon = $d1 + ($m1 / 60) + ($s1 / 3600);

					if (!$linktxt) $cstr = "$latref $lat $lonref $lon";

					if ($latref == "S") $lat = -$lat;
					if ($lonref == "W") $lon = -$lon;

					$gurl = "http://maps.google.nl/maps?f=q&hl=en&geocode=&ie=UTF8&z=15&q=";
					$gurl .= "$lat+$lon";
				}

				if ($gurl) $gpsc = "<a href=\"$gurl\" target=\"_blank\">$cstr</a>";
				else $gpsc = $cstr;

				return array($gurl, $gpsc);
			}

			function admin_footer ()
			{
				$copy = htmlspecialchars (strftime ($this -> options ['default_copyright']));
				$nonce1 = wp_nonce_field('ngg_enhancer_copyright_form', "_wpnonce", true, false);
				$nonce2 = wp_nonce_field('ngg_enhancer_videometa_form', "_wpnonce", true, false);

				echo <<<EOT
					<div id="set_copyright" style="display: none;">
						<form id="form_set_copyright" method="POST" action="admin-post.php" accept-charset="utf-8">
							$nonce1
							<input type="hidden" name="action" value="set_copyright" />
							<input type="hidden" id="set_copyright_imagelist" name="TB_imagelist" value="" />
							<input type="hidden" id="set_copyright_bulkaction" name="TB_bulkaction" value="" />  
							<input type="text" width="60" name="copyright" value="$copy" /><br /><br />
							<input class="button-primary" type="submit" id="button-set-copyright" value="OK" />
							&nbsp;
							<input class="button-secondary dialog-cancel" type="reset" value=" Cancel " />
						</form>
					</div>
EOT;
				if ($this -> options ['support_video'] == "yes") {
					echo <<<EOT
						<div id="import_video_meta" style="display: none;">
							<form id="form_import_video_meta" method="POST" action="admin-post.php" accept-charset="utf-8">
								$nonce2
								<input type="hidden" name="action" value="import_video_meta" />
								<input type="hidden" id="import_video_meta_imagelist" name="TB_imagelist" value="" />
								<input type="hidden" id="import_video_meta_bulkaction" name="TB_bulkaction" value="" />  
								<strong>About this action</strong><br />
								This will try to find the meta-data XML file for all video files in this gallery. It will
								only act on videos (filename matching the video regexp from on the settings page) and do
								nothing for ordinary images.<br/><br/>
								<input class="button-primary" type="submit" id="button-import-video-meta" value="OK" />
								&nbsp;
								<input class="button-secondary dialog-cancel" type="reset" value=" Cancel " />
							</form>
						</div>
EOT;
				}
				else {
					$page_url = $this -> options_page_url;
					echo <<<EOT
						<div id="import_video_meta" style="display: none;">
							<form id="form_import_video_meta" method="POST" action="admin-post.php" accept-charset="utf-8">
								<strong>Error</strong><br />
								NextGEN Enhancer's video support is disabled. Unable to scan XML files for video properties.
								Consider enabling video support on <a href="$page_url">the options page</a>.<br /><br />
								<input class="button-primary dialog-cancel" type="reset" value=" Close " />
							</form>
						</div>
EOT;
				}
			}

			function ngg_manage_gallery_settings ()
			{
				$page_url = $this -> options_page_url;

				if (!isset ($this -> options ['manage_description']) || $this -> options ['manage_description'] != "yes" ) {
					echo <<<EOT
						<p style='padding-left: 10px'><strong>WARNING</strong>: The <i>Automatically manage NextGEN's description field</i> option for NextGEN Enhancer is
					 	turned <strong>off</strong>. This means that even though you can edit and save captions and copyright information, and the
					 	<i>NextGEN Enhancer	enabled</i> checkbox for each image may be checked, the description field is
						not automatically updated. You can update the description field through a <i>Bulk action</i>, or you
						can enable automatic updates through <a href="$page_url">the options page</a>.</p><br/>
EOT;
				}
				else {
						echo "<p style='padding-left: 10px'>Automatic updates of the description field with NextGEN Enhancer are <strong>enabled</strong>.</p><br/>";
				}
			}

			function ngg_add_new_page ($page, $gid)
			{
				$pre = (isset ($this -> options ['page_prefix']) ? $this -> options ['page_prefix'] : "");
				$suf = (isset ($this -> options ['page_suffix']) ? $this -> options ['page_suffix'] : "");
				$page ['post_content'] = $pre . $page ['post_content'] . $suf;
				return $page;
			}

			/**
			 * Early action to run for NGG AJAX operations. It will be called for each image separately.
			 * $_POST: Array\n(\n    [action] => ngg_ajax_operation\n    [operation] => set_watermark\n    [_wpnonce] => 1545c63963\n    [image] => 1\n)\n, referer: http://test.grendelman.net/wp/wp-admin/admin.php?page=nggallery-manage-gallery&mode=edit&gid=2&paged=1
			 */
			function wp_ajax_ngg_ajax_operation_early ()
			{
				check_ajax_referer( "ngg-ajax" );

				switch ( $_POST['operation'] ) {
					case 'resize_image' :
					case 'rotate_cw' :
					case 'rotate_ccw' :
					case 'set_watermark' :

						$pid = (int) $_POST ['image'];
						if ($pid > 0) {
							$image = array ('id' => $pid);
							$this -> save_xmp_data ($image);
						}
					break;
				}
			}

			/**
			 * Early action to run for NGG AJAX operations. It will be called for each image separately.
			 * NOTE: This doesn't work out of the box with NextGEN Gallery 1.9.1.
			 * admin/ajax.php needs a modification, please see http://code.google.com/p/nextgen-gallery/issues/detail?id=451
			 * die ($result) on line 69 should be changed to echo "$result\n";
			 */
			function wp_ajax_ngg_ajax_operation_late ()
			{
				check_ajax_referer( "ngg-ajax" );

				switch ( $_POST['operation'] ) {
					case 'resize_image' :
					case 'rotate_cw' :
					case 'rotate_ccw' :
					case 'set_watermark' :

						$pid = (int) $_POST ['image'];
						if ($pid > 0) {
							$image = array ('id' => $pid);
							$this -> load_xmp_data ($image);
						}
					break;
				}
			}

			function shortcode_nggenav ($atts)
			{
				global $post;

				$atts = shortcode_atts (array (
						'scope' => 'siblings',
						'limit' => 0
					), $atts);

				if ($atts ['scope'] == 'children') {
					$parent = $post -> ID;
				}
				elseif ( $atts['scope'] == 'parent' ) {
					$parent_post = get_post( $post -> post_parent );
					$parent = $parent_post -> post_parent;
				}
				else {
					$parent = $post -> post_parent;
				}

				$opts = array (
					'meta_key'    => 'ngg_album',
					'sort_column' => 'menu_order,post_title',
					'sort_order'  => 'desc',
					'title_li'    => '',
					'parent'      => $parent,  // only get siblings or children of current page
				);

				$pages = get_pages ($opts);
				$rewrite_pattern = '/Photos? (.*)/';
				$rewrite_replace = '\\1';
				$links = array();
				$i = 0;
				foreach ($pages as $p) {
					$links[] = "<a href = \"" . get_page_link ($p -> ID) ."\">".
						ucfirst (preg_replace ($rewrite_pattern, $rewrite_replace, $p -> post_title)). "</a>";
					$i++;
					if ( (int) $atts['limit'] > 0 && $i >= (int) $atts['limit'] ) {
						if ($i < count( $pages ) ) {
							$links[] = '...';
						}
						break;
					}
				}
				return implode(' | ', $links);
			}

		} // class
	} // if !class_exists

	// Main
	$ngg_enhancer = new nextgen_enhancer ();
?>
