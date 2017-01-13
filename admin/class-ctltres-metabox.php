<?php

/**
 * This class creates and manages the meta box that appears on any post type's edit page.
 */
class CTLTRES_Metabox {
	// This id is used to uniquely identify our metabox.
	public static $metabox_id = 'ctltres_metabox';
	// The name attribute used for HTML fields and POST parsing
	public static $field_name = 'ctltres_resource_fields';
	// A reserved key for the resource's category field
	public static $category_name = 'ctltres_resource_category';
	// A reserved key for the resource's embed options
	public static $embed_options_name = 'ctltres_resource_embed';
	// The key used for the nonce for this meta box
	public static $nonce_name = 'ctltres_metabox_nonce';

	/**
	 * @filter init
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Register all necessary scripts for the meta box.
	 *
	 * @filter enqueue_scripts
	 */
	public static function enqueue_scripts() {
		wp_register_script( 'ctltres-metabox', CTLT_Resources::$directory_url . 'admin/js/ctlt-resources-metabox.js', array ( 'jquery' ) );
	}

	/**
	 * Add a resource meta box to all post types which it has been enabled for.
	 *
	 * @filter add_meta_boxes
	 * 
	 * TODO: At the moment a meta box will not appear on Media post types, but we should change it so that it can.
	 */
	public static function add_meta_box() {
		if ( get_post_type() == CTLTRES_Resources::$post_type_slug ) {
			// For the resources post type, the context is a bit different.
			// We have this custom meta box definition to account for that.
			add_meta_box( 
				self::$metabox_id,
				__( 'Attributes', 'ctltres' ),
				array( __CLASS__, 'render_meta_box' ),
				CTLTRES_Resources::$post_type_slug,
				'normal',
				'default'
			);
		} else {
			add_meta_box( 
				self::$metabox_id,
				__( 'Resource', 'ctltres' ),
				array( __CLASS__, 'render_meta_box' ),
				CTLTRES_Configuration::get_allowed_post_types(),
				'side',
				'default'
			);
		}
	}

	/**
	 * This functon renders the meta box
	 */
	public static function render_meta_box() {
		// Print the nonce field
		wp_nonce_field( plugin_basename( __FILE__ ), self::$nonce_name );
		// Enqueue the relevant script.
		wp_enqueue_script( 'ctltres-metabox' );

		if ( get_post_type() != CTLTRES_Resources::$post_type_slug ) {
			// If this post type is no a resource, then we need to add a little extra context to explain the meta box.
			?>
			<p><em>Select the options below to tag this <?php echo get_post_type(); ?> as a resource.</em></p>
			<?php
		}

		?>
		<p><strong>Category</strong></p>
		<label class="screen-reader-text" for="ctltres_type">Category</label>
		<?php
		// Get the category that this resource has been assigned to (if any)
		$category = CTLTRES_Resources::get_category();

		// TODO: Check if there are no taxonomy terms, and if so direct the user to create some.

		// If there are no taxonomy terms defined display a warning.
		if ( count( get_terms( CTLTRES_Resources::$taxonomy_slug ) ) ) {
			$taxonomy_url = admin_url( 'edit-tags.php?taxonomy=' . CTLTRES_Resources::$taxonomy_slug );
			?>
			<p>
				You must <a href="<?php echo $taxonomy_url; ?>">create a Resource category</a> before you can create a valid resource.
			</p>
			<?php
			return;
		}

		// Create options to display a dropdown menu with all the possible categories
		$dropdown_options = array(
			'taxonomy'         => CTLTRES_Resources::$taxonomy_slug,
			'id'               => 'ctltres_metabox_category',
			'name'             => self::$field_name . "[" . self::$category_name . "]",
			'hierarchical'     => 1,
			'hide_empty'       => false,
			'value_field'      => 'term_id',
			'selected'         => $category != null && is_numeric( $category ) ? $category : -1,
		);

		if ( get_post_type() != CTLTRES_Resources::$post_type_slug ) {
			// If this is not a resource post type, then allow the option for the post type to not be tagged as a resource.
			$dropdown_options['show_option_none'] = "Not a Resource";
		}

		// Display the dropdown
		wp_dropdown_categories( $dropdown_options );

		// If there is no category defined, then we need to hide the options.
		$visibility = empty( $category ) ? 'style="display:none;"' : '';

		?>
		<section id="ctltres_metabox_embed_options" <?php echo $visibility; ?>>
			<p>
				<?php
				// Display the embed options
				$embed_options = CTLTRES_Resources::get_embed_options();

				self::render_embed_options_checkbox( 'show_attributes', $embed_options, "Show resource attributes table" );
				self::render_embed_options_checkbox( 'show_list', $embed_options, "Show list of similar resources" );
				self::render_embed_options_checkbox( 'show_search', $embed_options, "Show search form with resource list" );
				?>
				<em>
					The above content can also be embedded using the [cres_attributes] and [cres_list] shortcodes.
				</em>
			</p>
		</section>

		<section id="ctltres_metabox_attributes" <?php echo $visibility; ?>>
			<?php
			// Display the resource attribute fields.
			$data = CTLTRES_Resources::get_attributes();

			foreach ( CTLTRES_Configuration::get_attribute_fields() as $slug => $attribute ) {
				$value = array_key_exists( $slug, $data ) ? $data[ $slug ] : null;
				self::render_attribute_field( $attribute, $value );
			}
			?>
		</section>
		<?php
	}

	/**
	 * Print a checkbox for an embed option
	 */
	public static function render_embed_options_checkbox( $slug, $values, $text ) {
		$name = self::$field_name . "[" . self::$embed_options_name . "]" . "[" . $slug . "]";

		?>
		<label>
			<input type="checkbox" value="on" name="<?php echo $name; ?>" <?php checked( $values[ $slug ] ); ?>></input>
			<span><?php echo $text; ?></span>
		</label>
		<br>
		<?php
	}

	/**
	 * Print the html for an attribute field
	 */
	public static function render_attribute_field( $field, $value = '' ) {
		?>
		<p><strong><?php echo $field['name']; ?></strong></p>
		<?php
		$name = self::$field_name . "[" . $field['slug'] . "]";

		switch ( $field['type']) {
			case 'select':
				?>
				<select name="<?php echo $name; ?>">
					<?php
					foreach ( $field['options'] as $option ) {
						?>
						<option value="<?php echo $option; ?>" <?php selected( $value, $option ); ?>><?php echo $option; ?></option>
						<?php
					}
					?>
				</select>
				<?php
				break;
			case 'multiselect':
				foreach ( $field['options'] as $option ) {
					?>
					<label>
						<input type="checkbox" name="<?php echo $name; ?>[]" value="<?php echo $option; ?>" <?php checked( is_array( $value ) && in_array( $option, $value ) ); ?>></input>
						<span><?php echo $option; ?></span>
					</label>
					<br>
					<?php
				}
				break;
			case 'url':
				?>
				<input type="url" name="<?php echo $name; ?>" value="<?php echo $value; ?>"></input>
				<?php
				break;
			case 'number':
				?>
				<input type="number" name="<?php echo $name; ?>" value="<?php echo $value; ?>"></input>
				<?php
				break;
			default:
				?>
				<input type="text" name="<?php echo $name; ?>" value="<?php echo $value; ?>"></input>
				<?php
				break;
		}
	}

	/**
	 * Saves all data which was defined by the meta box.
	 *
	 * @filter save_post
	 */
	public static function save_meta_box() {
		// Get the post id of the post we are editing.
		$post_id = get_the_ID();

		// Make sure that the nonce is valid
		if ( ! isset( $_POST[ self::$nonce_name ] ) || ! wp_verify_nonce( $_POST[ self::$nonce_name ], plugin_basename(__FILE__) )) {
			return $post_id;
		}

		// Make sure that we aren't auto-saving
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Get all the defined fields
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();
		// Get the data to be saved
		$data = $_POST[ self::$field_name ];

		// Loop through all valid fields and save the related data.
		foreach ( $attribute_fields as $slug => $attribute ) {
			// Make sure that the value is set, and if not set it to null.
			$value = isset( $data[ $slug ] ) ? $data[ $slug ] : null;
			// Call the validation function for this field
			$value = self::validate_attribute( $attribute, $value );
			// Store the result in post meta.
			update_post_meta( $post_id, CTLTRES_Resources::get_attribute_metakey( $slug ), $value );
		}

		// Save the selected resource category
		if ( isset( $data[ self::$category_name ] ) ) {
			// If a valid term was chosen, then save it.
			wp_set_object_terms( $post_id, intval( $data[ self::$category_name ] ), CTLTRES_Resources::$taxonomy_slug );
		} else {
			// if there is no value set, then clear the selected resource category.
			wp_set_object_terms( $post_id, array(), CTLTRES_Resources::$taxonomy_slug );
		}

		// Parse the embed options
		$embed_options = isset( $data[ self::$embed_options_name ] ) ? $data[ self::$embed_options_name ] : array();

		// Define the embed options defaults
		$embed_options = shortcode_atts( array(
			'show_attributes' => 'off',
			'show_list'       => 'off',
			'show_search'     => 'off',
		), $embed_options );

		// Convert the embed options to booleans
		foreach ( $embed_options as $slug => $value ) {
			$embed_options[ $slug ] = ( $value == 'on' );
		}

		// Save the embed options in our post meta.
		update_post_meta( $post_id, CTLTRES_Resources::get_attribute_metakey( 'embed' ), $embed_options );
	}

	/**
	 * Validate user input for the meta box.
	 */
	public static function validate_attribute( $attribute, $value ) {
		switch ( $attribute['type'] ) {
			case 'multiselect':
				if ( is_array( $value ) && count( $value ) > 0) {
					// For each selected value
					foreach ( $value as $i => $val ) {
						if ( ! in_array( $val, $attribute['options'] ) ) {
							// Deselect any invalid values
							unset( $value[$i] );
						}
					}

					// Return the result
					return $value;
				} else {
					// If the array is invalid, or empty, then return an empty array.
					return array();
				}
			case 'select':
				// Make sure that the selected value is one of the valid options.
				return in_array( $value, $attribute['options'] ) ? $value : "";
			case 'url':
				// Escape the url characters
				return esc_url( $value );
			case 'number':
				// Parse the string value into an integer
				return intval( $value );
			default:
				// If it doesn't fall into any other category, just do a general text sanitization.
				return sanitize_text_field( $value );
		}
	}

}

add_action( 'init', array( 'CTLTRES_Metabox', 'init' ) );
