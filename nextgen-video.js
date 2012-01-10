jQuery(document).ready(function($) {

	/* Replace the href of video thumbnails to the .swf of a flash player,
 	 * replacing {fileref} with appropriate values
 	 */
	$(".ngg-gallery-thumbnail a").filter('[title^="Video"]').attr("href", function (i, val) {
		return ngg_video_player_href.replace('{fileref}', ngg_gallery_path + '/' + $(this).children("img").attr("title"));
	});
});
