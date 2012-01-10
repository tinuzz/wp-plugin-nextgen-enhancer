jQuery(document).ready(function($){

	var nn = "";

	// Add the string 'Caption' to the table header
	$("#alt_title_desc").append(" / Caption");

	$(".alt_title_desc.column-alt_title_desc").each(function (arr){
		$(this).children('textarea[name^="description"]').each (function (a) {
			nn = $(this).attr("name").replace ('description', 'caption');
			id = $(this).attr("name").replace ('description[', 'caption').replace(']', '');

			// Hide original description textarea, if so desired
			if (hide_ngg_description) {
				$(this).css ({"visibility": "hidden", "height": "0px", "margin": "0px", "padding": "0px"});

				// Strip the word 'Description' from the table header
				$("#alt_title_desc").html ($("#alt_title_desc").html().replace("/ Description ", ""));
			}
		});
		$(this).append('<textarea style="width: 95%; margin-top: 2px;" name="' + nn + '" id="' + id + '"/>');
	});

	// Add copyright field
	$(".tags.column-tags").each(function (arr){
		$(this).children('textarea[name^="tags"]').each (function (a) {
			nn = $(this).attr("name").replace ('tags', 'copyright');
			id = $(this).attr("name").replace ('tags[', 'copyright').replace(']', '');
		});
		$(this).append('&copy; <input type="text" style="width: 86%; margin-top: 2px;" name="' + nn + '" id="' + id + '"/>');
	});

	$("#exclude").append(' / Enhancer <input type="checkbox" id="enhancer_global_checkbox" />');
	$(".exclude.column-exclude").append("<br/><br/>NextGEN Enhancer<br/>enabled: &nbsp; ");

	// Add NextGEN Enhancer on/off selector
	$(".exclude.column-exclude").each(function (arr){
		$(this).children('input[name^="exclude"]').each (function (a) {
			nn = $(this).attr("name").replace ('exclude', 'enhancer');
			id = $(this).attr("name").replace ('exclude[', 'enhancer').replace(']', '');
		});
		$(this).append('<input type="checkbox" name="' + nn + '" id="' + id + '"/>');
	});

	// Fill in the captions and copyrights from the global var
	$.each(captions, function (k, v) {
		element = 'caption' + v.pid;
		$("#" + element).val(v.caption);
		element2 = 'copyright' + v.pid;
		$("#" + element2).val(v.copyright);
		element3 = 'enhancer' + v.pid;
		$("#" + element3).attr("checked", true);
	});

	$("#bulkaction").append('<option value="update_description">Update description with NGG Enhancer</option>');
	$("#bulkaction").append('<option value="set_copyright">Set copyright information</option>');
	$("#bulkaction").append('<option value="import_video_meta">Import video meta from XML</option>');

	// An action for the 'Enhancer enabled' global checkbox
	$("#enhancer_global_checkbox").change(function (e) {
		ch = $(this).attr("checked");
		if (ch == "checked") {
			ch = true;
		}
		else {
			ch = false
		}
		$('[name^="enhancer"]').attr("checked", ch);
	});

	// Find the 'Apply' button, remove the default click handler and replace it with our own
	$('[name="showThickbox"]').prop("onclick", null).click(function (e) {
		actionId = jQuery('#bulkaction').val();

		switch (actionId) {
			case "set_copyright":

				// Mimic NextGEN's behaviour and handle the click
				var numchecked = getNumChecked(document.getElementById('updategallery'));
				if (numchecked > 0) {
					showDialog('set_copyright', 'Set copyright to...');
					return false;
				}
				else {
					alert('No images selected');
					return false;
				}
				break;

			case "import_video_meta":

				// Mimic NextGEN's behaviour and handle the click
				var numchecked = getNumChecked(document.getElementById('updategallery'));
				if (numchecked > 0) {
					showDialog('import_video_meta', 'Import video metadata...');
					return false;
				}
				else {
					alert('No images selected');
					return false;
				}
				break;

			default:
				// Let NextGEN handle the click
				if (!checkSelected()) {
					return false;
				}
		}
	});

	// When the caption or copyright changes, check the 'enabled' checkbox.
	$('[name^="caption"]').change( function (a) {
		id = $(this).attr("name").replace ('caption[', '').replace(']', '');
		$("#enhancer" + id).attr("checked", true);
	});
	$('[name^="copyright"]').change( function (a) {
		id = $(this).attr("name").replace ('copyright[', '').replace(']', '');
		$("#enhancer" + id).attr("checked", true);
	});

}); // ready(function($)
