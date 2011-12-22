jQuery(document).ready(function($){

	$("#prime_database_button").click(function(e) {
		if (confirm("This will (re)initialize your NextGEN Enhancer table. Are you sure?")) {
			$("#prime_database_form").submit();
			return true;
		}
		return false;
	});

	$("#global_description_button").click(function(e) {
		if (confirm("This will recompile all your image/video descriptions. Are you sure?")) {
			$("#global_description_form").submit();
			return true;
		}
		return false;
	});
});
