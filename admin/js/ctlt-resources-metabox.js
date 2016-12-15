
console.log("Load metabox js");

jQuery(document).ready( function() {
	function update_category_selection() {
		if ( jQuery('#ctltres_metabox_category').val() == "-1" ) {
			jQuery('#ctltres_metabox_attributes').hide();
			jQuery('#ctltres_metabox_embed_options').hide();
		} else {
			jQuery('#ctltres_metabox_attributes').show();
			jQuery('#ctltres_metabox_embed_options').show();
		}
	}

	jQuery('#ctltres_metabox_category').change(update_category_selection);
	update_category_selection();
} );