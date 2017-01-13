/**
 * This class defines 
 */

jQuery(document).ready( function() {
	var attribute_container = jQuery('#ctltres-attribute-container');
	// Get a copy of the attribute template, so that we can clone it later.
	var attribute_template = jQuery('.ctltres-attribute.empty').clone();

	// We track the name field to determine if a field is created or deleted
	attribute_container.on( 'change', '.ctltres-attribute-name', function() {
		var element = jQuery(this);
		var attribute = element.closest('.ctltres-attribute');

		if ( element.val() != "" ) {
			// If the new value of the name box is not empty
			if ( attribute.hasClass('empty') ) {
				// And if it was empty before
				// Then we have established a new attribute
				attribute.removeClass('empty');

				// Create a new template attribute if the user wants to create another attribute.
				var attribute = attribute_template.clone();
				attribute_container.append(attribute);
			}
		} else {
			if ( ! attribute.hasClass('empty') ) {
				// If a field previously had a name, and that name has been deleted then the attribute also needs to be deleted
				attribute.remove();
			}
		}
	} );

	// When the attribute type changes, we have to show/hide any relevant additional options.
	attribute_container.on( 'change', '.ctltres-attribute-type', function() {
		var element = jQuery(this);
		var options = element.siblings('.ctltres-attribute-options');

		// Right now, there is just one kind of additional option, which is a comma-seperated list for select elements
		if ( [ 'multiselect', 'select' ].indexOf( element.val() ) >= 0 ) {
			// Show the options section, if we are dealing with a multiselect or select
			options.show();
		} else {
			// Otherwise, hide the additional options.
			options.hide();
		}
	} );

	// Make the attributes sortable
	jQuery( 'ol.sortable' ).sortable();
	jQuery( 'ol.sortable' ).disableSelection();
} );
