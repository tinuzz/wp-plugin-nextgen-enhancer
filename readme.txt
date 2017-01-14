=== NextGEN Enhancer ===
Contributors: tinuzz
Donate link: https://www.grendelman.net/wp/nextgen-enhancer/
Tags: nextgen, gallery, enhance, photo, video, flash, images, videojs
Requires at least: 3.3
Tested up to: 4.7.1
Stable tag: trunk

This plugins enhances the functionality of NextGEN Gallery version 1.9.0 or higher.

== Description ==

THIS PLUGIN DOES NOT WORK WITH NEXTGEN GALLERY >= 2.0 !!

This plugin enhances the functionality of NextGEN Gallery. Its features:

* Retaining meta-data across image operations, like resizing.
* Automatic management of image descriptions, using various sources of information.
* Video support in galleries. Videos can be played with your favorite flash
  player. No HTML5 yet.
* Page pre- and suffixes for gallery pages. Useful with Hidepost, for example.
* Tzzbox overlay effect, because Lightshadowboxviewclone just isn't good enough.

It needs NextGEN Gallery version 1.9.0 or higher.

A warning up front: THIS PLUGIN IS NOT FOR EVERYBODY.

It makes certain assumptions about how your content is organised, and it makes
changes to your NextGEN Gallery database, although it is configurable in many
ways, and is relatively safe to use.

Please refer to
[github](https://github.com/tinuzz/wp-plugin-nextgen-enhancer/blob/master/README.rdoc)
for more information.

== Installation ==

1. Upload the whole `nextgen-enhancer` directory and its contents
   to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= How safe is it to use this plugin? =

It is relatively safe. Features that modify your NextGEN Gallery database have
to be enabled explicitly, and even then, it is hard to make unwanted modifications
by mistake. However, creating a database backup before enabling this plugin is
always a good idea, and once you start using the plugin, the modifications are
irreversible.

== Screenshots ==

== Changelog ==

= 1.2 - 2017-01-15 =

The first release in more than 3 years. I still use this plugin, and occasionally, I
fix things in it, usually because something has stopped working on my website. It
appears that quite some changes have accumulated over time, so I decided to make a
release. Changes are:

* Replace JW Player with [VideoJS](http://videojs.com/)
* Make 'nggenav' shortcode work on gallery pages, using 'scope=parent' as parameter
* Add 'limit=X' parameter to 'nggenav' shortcode, to limit the number of links
* Don't link to satellite view on Google maps
* Fix a bug where nggdb() code was called as a class method, causing problems in PHP7
* Suplly a pin icon for map links
* Some TzzBox / Shutter improvements

= 1.1 - 2013-12-18 =

* Replace Tzzbox with a fork of [Shutter Reloaded](http://www.laptoptips.ca/javascripts/shutter-reloaded/)
* Implement maximum height for videos

= 1.0.1 - 2012-01-25 =

* Tzzbox: make '-' and '=' hide/unhide the navigation button bar
* Add [nggenav] shortcode
* Increase version number now, because the changes mentioned here were accidentally
  already present in the 1.0 version on wordpress.org since January 23.

= 1.0 - 2012-01-10 =
* First public release

== More information ==

Please refer to
[github](https://github.com/tinuzz/wp-plugin-nextgen-enhancer/blob/master/README.rdoc)
for more information.

Development is primarily tracked on Github, so if you have problems, and feel the
need to report a bug, please do it here:
[issues](https://github.com/tinuzz/wp-plugin-nextgen-enhancer/issues)

