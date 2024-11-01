=== WP-Gallery-Remote ===
Contributors: cb2206
Donate link: http://blog.thebartels.de/projects/wp-gallery-remote
Tags: gallery, gallery2, images, remote
Requires at least: 2.5
Tested up to: 2.6
Stable tag: trunk

WP-Gallery-Remote includes albums and images from any Gallery installation using Gallery’s Gallery Remote Protocol.

== Description ==

WP-Gallery-Remote includes albums and images from any Gallery installation using Gallery’s Gallery Remote Protocol. Images are display as thumbnails in you posts and pages. Thereby you can choose between a plain output of the thumbnails and a CSS and Javascript based carousel mode. Have a look at the screenshots to see how that looks like.
If the [Lightbox Plugin](http://www.m3nt0r.de/blog/lightbox-wordpress-plugin/ "Lightbox Plugin") is available and activated, clicking on a thumbnail opens the respective image using the lightbox effect. If the plugin is not available or activated, the image is shown in a new browser window.

Features

* displays images and albums of any Gallery installation which has enabled the Gallery-Remote-Protocol
* support for multiple wpgr tags in one post/page (v1.1)
* support for multiple Gallery installations (v1.2)
* two output types: plain and carousel (v1.2)
* Lightbox integration
* supports caching of fetched album and image meta data (can be enabled/disabled globally and per post/page)
* include/exclude filter to only show some images from an album
* global and per post/page options to display album title and subalbums
* uses Gallery’s image captions
* uses CURL or CURL emulation (v1.4)

== Installation ==

Requirements

* !! PHP 5 !!  <-- IMPORTANT, Wp-Gallery-Remote does NOT work with PHP4!
* allow_url_fopen must be activated (On) in your server's php.ini (some hoster deactivate this setting by default!)
* Gallery Installation
* activated [Gallery-Remote module](http://codex.gallery2.org/Gallery2:Modules:remote "Gallery Remote module")
* activated Wordpress [Lightbox Plugin](http://www.m3nt0r.de/blog/lightbox-wordpress-plugin/ "Lightbox Plugin")

Installation

* have the above requirements fulfilled
* Unzip the archive to `wp-content/plugins` of your Wordpress installation
* Activate the plugin in the plugins area of your Wordpress plugin admin page
* Go to: Options -> WP-Gallery-Remote
* At least set the path to your Gallery installation (=> `main.php`) for the Default Gallery
* Use the new WP-GR button in your post/page editor to include albums and images from your Gallery into your posts/pages.

Upgrade

* deactivate WP-Gallery-Remote
* upload files and subfolder of new version to `wp-content/plugins/wp-gallery-remote`
* activate WP-Gallery-Remote
* Go to Options -> WP-Gallery-Remote and check your settings

All caches will be deleted during an upgrade. So, it might be useful to use the rebuild caches functionality on the options page.

Deinstallation

* Deactivate the plugin (album and image caches get deleted; configuration is kept for later re-use)

== Frequently Asked Questions ==

= I don't want to use WP-Gallery-Remote anymore. What about all my blog posts which include WP-Gallery-Remote tags? =

The WP-Gallery-Remote archive includes a file called wpgr2picasa.zip. The included script can be used to migrate any pictures linked in you wordpress installation using WP-Gallery-Remote to Picasa and update your blog posts with links to picasa accordingly. The script does the following steps:
* login to your wordpress blog
* fetch all posts
* parse each post for one or more wp-gallery-remote tags
* if found, the respective images are fetched from your Gallery installation...
* ...and uploaded to your picasa webalbums account
* wp-gallery-remote tags without image filter are exchanged with an thumbnail and link of your picasa album
* wp-gallery-remote tags with image filter are exchanged with thumbnails and links to the selected pictures
* each changed post is saved

Usage:
* unzip wpgr2picasa.zip to any folder and open that folder
* open wpgr2picasa.php in a text editor and maintain links and user data for wordpress, picasa and gallery at the top of the file
* execute the script either in a browser or on the command line (php wpgr2picasa.php)

Please note, that I cannot provide support for this migration script. It worked fine for me; try it on your own risk.
DO A FULL BACKUP OF YOUR GALLERY AND WORDPRESS INSTALLATION!

Download:

= What is the difference between WP-Gallery-Remote and WPG2? Do they compete? =

Advantages of WP-Gallery-Remote:
* Very simple to setup (extract, activate, enter Gallery infos, ready)
* Integration of photos from a Gallery, which you do not own (but which is accesible via the Gallery Remote Protocol)
* Support for  multiple Galleries
* Lightbox effect
* carousel view

Advantages of WPG2:
* possibility for "truly" integrated photoblog (sidebar integration and so on)
* user authentication

= Help! WP-Gallery-Remote says, that it cannot connect to my Gallery. What is wrong? =

Please make sure, that you
* are using PHP5 (PHP4 is not supported)
* have activated allow_url_fopen (On) in your php.ini (some hoster deactivate it by default!)
* are using Wordpress 2.2.x
* are using Gallery2 from http://gallery.menalto.com
* have activated the Gallery Remote module in Gallery2
* have entered the complete path to you Gallery2 installation including `main.php` (e.g. http://www.example.com/gallery2/main.php)

Please let me know, if it still does not work and I can have a look.

= The order of images do not change after changing the order of IDs in the image filter. What is wrong? =

Nothing is wrong. The images are always displayed in the order, in which they are in Gallery. So, if you want to reorder them, you have to do that in your Gallery.

= Does WP-Gallery-Remote support logging in to my Gallery? (user authentication) =

Gallery provides a guest account, which is used for all users who do not explicitly login to Gallery (= public viewing). WP-Gallery-Remote currently does not support logging in as another user than guest.
Logging in to Gallery as another user does not even make really sense, as WP-Gallery-Remote fetches URLs to your Gallery's images via the Gallery Remote Protocol and outputs these to your post or page. If the respective albums are only available to certain Gallery users, only those can access the URLs. And this is normally not the case for public blogs.

If you are searching for a solution, which synchronizes users between Gallery and Wordpress, you might take a look into [WPG2](http://wpg2.galleryembedded.com/ "WPG2").

= I have one Gallery, but want to choose for each WP-Gallery-Remote tag, whether to use plain or carousel as output type. Is that possible? =

Yes, this is possible by setting up another Gallery in WP-Gallery-Remote's options, which points to the same URL but uses another output type.

Go to Options -> WP-Gallery-Remote in Wordpress' admin area and click the `New` button in the Gallery section. Enter a name, e.g. `My Gallery 2`, and set the same URL as for your other Gallery. Additionally, set the output type to the output type you would like to have.
If you now write a new post or page and open the WP-Gallery-Remote Album Chooser by clicking the respective WPGR button in Wordpress' editor, you can select that Gallery with the output type which you would like to use.

= Since WP-Gallery-Remote 2.0 the ID of the selected Gallery is added to the {wp-gallery-remote...} tags in my posts. What happens to the tags in my previous posts which do not have this Gallery ID as a parameter? =

Nothing. :-) 
If you upgrade from 1.1 to a newer version, the plugin automatically sets your previously used Gallery installation as the default Gallery which will be used, if no Gallery ID parameter is given in the {wp-gallery-remote...} tags of your posts.

= Is it possible to use the carousel output type in my RSS feeds? =

No, this is unfortunately not possible, because including carousel's javascripts is tricky and many RSS feed readers do not support that.

== Screenshots ==

Screenshots can be found [here](http://gallery.thebartels.de/v/cb/wp-gallery-remote/ "WP-Gallery-Remote screenshots").

== Changelog ==
* v1.4
	* switched from fopen to CURL for accessing Gallery; libcurlemu (http://code.blitzaffe.com/pages/phpclasses/files/libcurl_emulator_52-7) is used to support environments without CURL support
	* image cache was not resetted after timeout (thanks Kerri!)

* v1.3
	* added check for allow_url_fopen on admin page
	* added option to open Gallery album by clicking the album's title
	* album chooser now adds now only those parameters to the wpgr tag, which have been changed - allows global change of settings for older posts
	* added support for multi-language via gettext/pot files (feel free to translate and to send me the translation files for inclusion)
	* added per album and per tag option to choose what happens, if the user clicks on a thumbnail
		* open via lightbox effect
		* open image in new window
		* open image in Gallery in new window
		
	* bugfix: rewrote Gallery data parsing method - speedup + fix of potential security risk
	* bugfix: post specific parameters were used for following posts as well

* v1.2.1
	* bugfix: array_search() PHP warning on admin page
	* bugfix: array_search() PHP warning, if displaying subalbums
	* added fading effect to messages on admin page

* v1.2
	* support for multiple Gallery installations
	* added new output type: carousel
	* output type (plain / carousel) can be selected per Gallery on WPGR's admin page
	* output type 'plain' is always used in RSS feeds
	* redesigned WPGR's admin page: multiple Galleries support, better layout, tooltips, frontend & backend checks for correct values, 'test Gallery connection' button, confirmation dialog for rebuilt caches
	* clicking on a picture in a rss feed, opens the post in a new window and directly pops up the respective image using lightbox - can be deactivated in global options
	* added possibility to define formatting for div container of plain output (per Gallery + per WPGR tag)
	
	* debug window shows overall time to render all WPGR tags of current page
	* changed subfolder structure and renamed some files
	* mce-plugin: added spaces in front of generated tag parameters to allow word wrapping in tinymce
	* if lightbox plugin is not available or not activated, images are opened in new window
	
	* bugfix: Lightbox gallery IDs were sometimes not unique, so that images of different WPGR tags were merged into one Lightbox gallery
	* bugfix: JS and CSS files were always loaded, even if they were not used
	* bugfix: double quotes and apostrophes in image captions cause some trouble
	* and bunch of small fixes...
	
* v1.1
	* support for multiple wpgr tags per post/page
	* bugfix: image filenames were not generated correctly, if no resized version available in Gallery
	* bugfix: request parameter ‘current_album’ was parsed even if subalbums were deactivated
	* bugfix: request parameter ‘current_album’ was parsed, if on home screen (post list)
	* bugfix: fixed array_key_exists error message on first image fetch after image cache deletion
	* bugfix: cache rebuilt on admin page was not working correctly for image cache

* v1.0
	* initial public release


== License ==

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
  
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
