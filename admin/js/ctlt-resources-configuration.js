
jQuery(document).ready( function() {
	var attribute_container = jQuery('#ctltres-attribute-container');
	var attribute_template = jQuery('.ctltres-attribute.empty').clone();
	var attribute_template = jQuery('.ctltres-attribute.empty').clone();

	attribute_container.on( 'change', '.ctltres-attribute-name', function() {
		var element = jQuery(this);
		var attribute = element.closest('.ctltres-attribute');

		if ( element.val() != "" ) {
			if ( attribute.hasClass('empty') ) {
				attribute.removeClass('empty');

				var attribute = attribute_template.clone();
				attribute_container.append(attribute);
			}
		} else {
			if ( ! attribute.hasClass('empty') ) {
				attribute.remove();
			}
		}
	} );

	attribute_container.on( 'change', '.ctltres-attribute-type', function() {
		var element = jQuery(this);
		var options = element.siblings('.ctltres-attribute-options');

		if ( [ 'multiselect', 'select' ].indexOf( element.val() ) >= 0 ) {
			options.show();
		} else {
			options.hide();
		}
	} );

	jQuery( 'ol.sortable' ).sortable();
	jQuery( 'ol.sortable' ).disableSelection();
} );
