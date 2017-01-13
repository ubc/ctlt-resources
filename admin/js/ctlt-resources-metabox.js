/**
 * This javascript is attached to the resource metabox, and appears on the edit page for any post type.
 */

jQuery(document).ready( function() {
	function update_category_selection() {
		if ( jQuery('#ctltres_metabox_category').val() == "-1" ) {
			// If no category has been selected, hide all the options.
			jQuery('#ctltres_metabox_attributes').hide();
			jQuery('#ctltres_metabox_embed_options').hide();
		} else {
			// If any category has been selected, show all the options.
			jQuery('#ctltres_metabox_attributes').show();
			jQuery('#ctltres_metabox_embed_options').show();
		}
	}

	// Set up an event trigger
	jQuery('#ctltres_metabox_category').change(update_category_selection);

	// Set up the intial state, so that any prepopulated value is taken into account.
	update_category_selection();
} );