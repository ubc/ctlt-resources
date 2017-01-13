<?php

/**
 * This class handles the resource settings menu, as well as helper functions to access those settings.
 */
class CTLTRES_Configuration {
	
	// A slug for the parent page.
	public static $parent_slug = 'ctlt-resources';
	// A slug for the resource settings page
	public static $menu_slug = 'ctlt-resources';
	// A slug for the settings group, for the Settings API
	public static $settings_group = 'ctlt-resources';

	// A slug for the general section
	public static $section_general = 'general';
	// A slug for storing the setting to enable the custom Resource post type
	public static $setting_enable_post_type = 'enabled_post_type';
	// A slug for storing the list of disabled post types
	public static $setting_post_types = 'disabled_post_types';
	// A slug for storing the list of resource attributes
	public static $setting_attributes = 'attributes';

	// A slug for the resource listings section
	public static $section_listings = 'listings';
	// A slug for storing the setting to enable search fields
	public static $setting_enable_search = 'enable_search';
	// A slug for storing the list of visible fields in listings
	public static $setting_visible_fields = 'visible_fields';
	// A slug for storing the list of searchable fields
	public static $setting_searchable_fields = 'searchable_fields';

	// The list of allowed resource attribute types
	public static $attribute_types = array(
		'select'      => "Dropdown",
		'multiselect' => "Checkboxes",
		'number'      => "Number",
		'url'         => "Link",
		'text'        => "Text",
	);

	/**
	 * @filter init
	 */
	public static function init() {
		// If the custom post type is enabled, then we need a slightly different menu structure.
		if ( self::is_post_type_enabled() ) {
			// All the menus will be housed under the custom post type, instead of as their own menu set.
			self::$parent_slug = "edit.php?post_type=" . CTLTRES_Resources::$post_type_slug;
		} else {
			// Otherwise, the resource settings page, is the parent.
			self::$parent_slug = self::$menu_slug;
		}

		add_action( 'admin_menu', array( __CLASS__, 'create_menus' ) );
		add_action( 'parent_file', array( __CLASS__, 'highlight_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ) );
	}

	/**
	 * Returns whether the custom Resources post type is enabled.
	 */
	public static function is_post_type_enabled() {
		$value = get_option( self::$setting_enable_post_type, false, true );
		return empty( $value ) ? false : $value;
	}

	/**
	 * @return a list of post types which can be designated as resources.
	 */
	public static function get_allowed_post_types() {
		// TODO: Should comments be allowed to be resources?

		// Get a list of all possible post types
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		// Get a list of which post types have been disabled.
		$disabled_post_types = get_option( self::$setting_post_types, array(), true );

		// Remove all disabled post types
		foreach ( $disabled_post_types as $post_type ) {
			unset( $post_types[$post_type] );
		}

		return $post_types;
	}

	/**
	 * @return the set of all fields which can be defined for each resource.
	 */
	public static function get_attribute_fields() {
		return get_option( self::$setting_attributes, array(), true );
	}

	/**
	 * @return whether search is enabled by default, or not.
	 */
	public static function is_search_enabled() {
		$value = get_option( self::$setting_enable_search, true, true );
		return $value != false;
	}

	/**
	 * @return a list of slugs for all searchable fields.
	 */
	public static function get_searchable_fields() {
		return get_option( self::$setting_searchable_fields, array(), true );
	}

	/**
	 * @return a list of slugs for all fields which should be visible in the listings.
	 */
	public static function get_visible_fields() {
		return get_option( self::$setting_visible_fields, array(), true );
	}

	/**
	 * @filter admin_enqueue_scripts
	 */
	public static function enqueue_scripts_and_styles() {
		wp_register_script( 'jquery-sortable-johnny', CTLT_Resources::$directory_url . 'admin/js/jquery-sortable.min.js', array( 'jquery' ) );
		wp_register_script( 'ctltres-configuration', CTLT_Resources::$directory_url . 'admin/js/ctlt-resources-configuration.js', array( 'jquery', 'jquery-sortable-johnny' ) );
		wp_register_style( 'ctltres-configuration', CTLT_Resources::$directory_url . 'admin/css/ctlt-resources-configuration.css' );
	}

	/**
	 * Register all the admin pages
	 *
	 * @filter admin_menu
	 */
	public static function create_menus() {
		if ( self::is_post_type_enabled() ) {
			// If the Resource custom post type is enabled, then we will be creating our menus as children to that menu.
			
			// Add the page for managing settings.
			add_submenu_page(
				self::$parent_slug,
				__( "Resource Settings", 'ctltres' ), // Page title
				__( "Settings", 'ctltres' ), // Menu title
				'manage_options',
				self::$menu_slug,
				array( __CLASS__, 'render_page' )
			);
		} else {
			// Otherwise create a new top level menu
			add_menu_page(
				__( "Resource Settings", 'ctltres' ), // Page title
				__( "Resources", 'ctltres' ), // Menu title
				'manage_options',
				self::$parent_slug,
				array( __CLASS__, 'render_page' ),
				'dashicons-index-card'
			);

			// Add the page for managing settings
			add_submenu_page(
				self::$parent_slug,
				__( "Resource Settings", 'ctltres' ), // Page title
				__( "Settings", 'ctltres' ), // Menu title
				'manage_options',
				self::$menu_slug
			);
		}

		// Add a page for managing categories
		add_submenu_page(
			self::$parent_slug,
			__( "Resource Categories", 'ctltres' ), // Page title
			__( "Categories", 'ctltres' ), // Menu title
			'manage_options',
			'edit-tags.php?taxonomy=' . CTLTRES_Resources::$taxonomy_slug
		);
	}

	/**
	 * This hook allows us to make sure that the appropriate menu item is highlighted when we are managing categories.
	 *
	 * @filter parent_file
	 */
	public static function highlight_menu( $parent_file ) {
		global $current_screen;

        $taxonomy = $current_screen->taxonomy;
        if ( $taxonomy == CTLTRES_Resources::$taxonomy_slug ) {
        	// If the current screen is showing our resource categories taxonomy, then set the parent menu to our menu.
            $parent_file = self::$parent_slug;
        }

        return $parent_file;
	}

	/**
	 * Register all settings for this plugin.
	 *
	 * @filter admin_init
	 */
	public static function register_settings() {
		// Register the settings with wordpress.
		register_setting( self::$settings_group, self::$setting_enable_post_type, array( __CLASS__, 'validate_setting_enable_post_type' ) );
		register_setting( self::$settings_group, self::$setting_post_types, array( __CLASS__, 'validate_setting_post_types' ) );
		register_setting( self::$settings_group, self::$setting_attributes, array( __CLASS__, 'validate_setting_attributes' ) );
		register_setting( self::$settings_group, self::$setting_enable_search, array( __CLASS__, 'validate_setting_boolean' ) );
		register_setting( self::$settings_group, self::$setting_searchable_fields, array( __CLASS__, 'validate_setting_fields_set' ) );
		register_setting( self::$settings_group, self::$setting_visible_fields, array( __CLASS__, 'validate_setting_fields_set' ) );

		// Then create the UI

		// TAGGING CONFIG
		add_settings_section(
			self::$section_general,
			__( "Resources Tagging", 'ctlt-resources' ),
			array( __CLASS__, 'render_section_general' ),
			self::$menu_slug
		);
		
		add_settings_field(
			self::$setting_enable_post_type,
			__( "Enable Custom Post Type", 'ctlt-resources' ),
			array( __CLASS__, 'render_setting_enable_post_type' ),
			self::$menu_slug,
			self::$section_general
		);
		
		add_settings_field(
			self::$setting_post_types,
			__( "Allowed Post Types", 'ctlt-resources' ),
			array( __CLASS__, 'render_setting_post_types' ),
			self::$menu_slug,
			self::$section_general
		);
	 	
	 	add_settings_field(
			self::$setting_attributes,
			__( "Attribute Fields", 'ctlt-resources' ),
			array( __CLASS__, 'render_setting_attributes' ),
			self::$menu_slug,
			self::$section_general
		);

		// LISTING CONFIG
		add_settings_section(
			self::$section_listings,
			__( "Resource Listings", 'ctlt-resources' ),
			array( __CLASS__, 'render_section_listings' ),
			self::$menu_slug
		);
		
		add_settings_field(
			self::$setting_attributes,
			__( "Enable Search by Default", 'ctlt-resources' ),
			array( __CLASS__, 'render_setting_enable_search' ),
			self::$menu_slug,
			self::$section_listings
		);
		
		add_settings_field(
			self::$setting_searchable_fields,
			__( "Searchable Fields", 'ctlt-resources' ),
			array( __CLASS__, 'render_setting_searchable_fields' ),
			self::$menu_slug,
			self::$section_listings
		);
		
		add_settings_field(
			self::$setting_visible_fields,
			__( "Visible Fields", 'ctlt-resources' ),
			array( __CLASS__, 'render_setting_visible_fields' ),
			self::$menu_slug,
			self::$section_listings
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		// Enqueue all relevant javascript and css
		wp_enqueue_script( 'jquery-sortable-johnny' );
		wp_enqueue_script( 'ctltres-configuration' );
		wp_enqueue_style( 'ctltres-configuration' );

		//Create the settings form.
		?>
		<form action="options.php" method="POST">
			<?php
			// This creates all meta fields
			settings_fields( self::$settings_group );
			// This renders each section of our page.
			do_settings_sections( self::$menu_slug );
			// Create a submit button
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Renders a description for the general section.
	 */
	public static function render_section_general() {
		// Do nothing
	}

	/**
	 * Renders a description for the listings section.
	 */
	public static function render_section_listings() {
		// Do nothing
	}

	/**
	 * Renders the UI for the "enable post type" option
	 */
	public static function render_setting_enable_post_type() {
		$enabled = self::is_post_type_enabled();
		
		?>
		<select name="<?php echo self::$setting_enable_post_type; ?>">
			<option value="on" <?php selected( $enabled ); ?>>Enabled</option>
			<option value="off" <?php selected( $enabled, false ); ?>>Disabled</option>
		</select>
		<?php
	}

	/**
	 * Renders the UI for the "allowed post types" option
	 */
	public static function render_setting_post_types() {
		// Get a list of all possible post types
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		// Get a list of which post types are allowed.
		$allowed_post_types = self::get_allowed_post_types();

		?>
		<em>Select which post types can be tagged as a Resource</em>
		<br>
		<?php

		foreach ( $post_types as $post_type ) {
			?>
			<label>
				<input name="<?php echo self::$setting_post_types; ?>[]" type="checkbox" value="<?php echo $post_type->name; ?>" <?php checked( in_array( $post_type->name, $allowed_post_types ) ); ?>></input>
				<span><?php echo $post_type->label; ?></label>
				<span>(<?php echo $post_type->name; ?>)</label>
			</label>
			<br>
			<?php
		}
	}

	/**
	 * Renders the UI for the "attribute fields" option
	 */
	public static function render_setting_attributes() {
		?>
		<em>Choose fields that can be defined for each resource.</em>
		<br>
		<em>For dropdowns and checkboxes, the allowed values should be a comma seperated list.</em>
		<ul id="ctltres-attribute-container">
			<?php
			// Render all existing attribute fields
			foreach ( self::get_attribute_fields() as $attribute ) {
				self::render_setting_attribute_block( $attribute );
			}

			// Render a template for a new attribute field.
			self::render_setting_attribute_block();
			?>
		</ul>
		<em>The visible and sortable fields below will update after your changes are saved.</em>
		<?php
	}

	/**
	 * Render one attribute for the "attribute fields" option
	 */
	public static function render_setting_attribute_block( $args = array() ) {
		$empty = empty( $args ) ? 'empty' : '';
		$args = shortcode_atts( array(
			'name' => '',
			'type' => '',
			'slug' => '',
			'options' => '',
		), $args );

		$options_visibility = in_array( $args['type'], array( 'select', 'multiselect' ) ) ? '' : 'style="display:none;"';
		$options = is_array( $args['options'] ) ? implode( ",", $args['options'] ) : "";

		?>
		<li class="ctltres-attribute <?php echo $empty; ?>">
			<input class="ctltres-attribute-name medium-text" name="<?php echo self::$setting_attributes; ?>[name][]" type="text" placeholder="Name" value="<?php echo $args['name']; ?>"></input>
			<select class="ctltres-attribute-type" name="<?php echo self::$setting_attributes; ?>[type][]">
				<option value="">Choose a Type</option>
				<?php
				foreach ( self::$attribute_types as $key => $label ) {
					?>
					<option value="<?php echo $key; ?>" <?php selected( $args['type'], $key ); ?>><?php echo $label; ?></option>
					<?php
				}
				?>
			</select>
			<input class="ctltres-attribute-slug medium-text" name="<?php echo self::$setting_attributes; ?>[slug][]" type="text" placeholder="Slug" value="<?php echo $args['slug']; ?>"></input>
			<input class="ctltres-attribute-options medium-text" name="<?php echo self::$setting_attributes; ?>[options][]" type="text" placeholder="Allowed Values" value="<?php echo $options; ?>" <?php echo $options_visibility; ?>></input>
		</li>
		<?php
	}

	/**
	 * Renders the UI for the "enable search" option
	 */
	public static function render_setting_enable_search() {
		$enabled = self::is_search_enabled();
		
		?>
		<select name="<?php echo self::$setting_enable_search; ?>">
			<option value="on" <?php selected( $enabled ); ?>>Enabled</option>
			<option value="off" <?php selected( ! $enabled ); ?>>Disabled</option>
		</select>
		<br>
		<em>This option sets the default. It can be overridden when using shortcodes.</em>
		<?php
	}

	/**
	 * Renders the UI for the "searchable fields" option
	 */
	public static function render_setting_searchable_fields() {
		// Create a list of the potentially searchable fields
		$potential_fields = array(
			'search'   => array( 'name' => "Full Text" ),
			'category' => array( 'name' => "Resource Category" ),
		);

		$potential_fields = array_merge( $potential_fields, self::get_attribute_fields() );

		// Create a list of the fields which have already been defined as searchable.
		$searchable_fields = array();

		foreach ( self::get_searchable_fields() as $slug ) {
			$searchable_fields[ $slug ] = $potential_fields[ $slug ]['name'];
		}

		// Create a list of the current unsearchable, but potentially searchable, fields.
		$leftover_fields = array();

		foreach ( $potential_fields as $slug => $attribute ) {
			if ( ! array_key_exists( $slug, $searchable_fields ) ) {
				$leftover_fields[ $slug ] = $attribute['name'];
			}
		}

		// Render the fields
		?>
		<em>Select which fields are searchable. Drag the fields to reorder them.</em>
		<ol id="ctltres-search-fields" class="sortable">
			<?php
			// Render the searchable fields
			foreach ( $searchable_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_searchable_fields, true );
			}

			// Render the currently unsearchable fields
			foreach ( $leftover_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_searchable_fields );
			}
			?>
		</ol>
		<?php
	}

	/**
	 * Renders the UI for the "visible fields" option for listings
	 */
	public static function render_setting_visible_fields() {
		// Create a list of the potentially visible fields
		$potential_fields = array(
			'category' => array( 'name' => "Resource Category" ),
			'date'     => array( 'name' => "Last Modified Date" ),
		);

		$potential_fields = array_merge( $potential_fields, self::get_attribute_fields() );

		// Create a list of the fields which have already been defined as visible.
		$visible_fields = array();

		foreach ( self::get_visible_fields() as $slug ) {
			$visible_fields[ $slug ] = $potential_fields[ $slug ]['name'];
		}

		// Create a list of the fields which are currently not visible, but could potentially be visible.
		$leftover_fields = array();

		foreach ( $potential_fields as $slug => $attribute ) {
			if ( ! array_key_exists( $slug, $visible_fields ) ) {
				$leftover_fields[ $slug ] = $attribute['name'];
			}
		}

		// Render the html
		?>
		<em>Select which fields are shown in the resource lists. Drag the fields to reorder them.</em>
		<ol id="ctltres-visible-fields" class="sortable">
			<?php
			// Render the list of visible fields
			foreach ( $visible_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_visible_fields, true );
			}

			// Render the list of not currently visible fields
			foreach ( $leftover_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_visible_fields );
			}
			?>
		</ol>
		<?php
	}

	/**
	 * Renders a checkbox field for one of the field lists (either sortable or visible fields)
	 */
	public static function render_setting_list_checkbox( $display_name, $field_slug, $setting_slug, $checked = false ) {
		?>
		<li>
			<label>
				<input type="checkbox" name="<?php echo $setting_slug; ?>[]" value="<?php echo $field_slug; ?>" <?php checked( $checked ); ?>></input>
				<span><?php echo $display_name; ?></span>
			</label>
		</li>
		<?php
	}

	/**
	 * Validate the output of the 'enabled post types' setting.
	 * This function will instead return a list of which post types have been disabled.
	 * We want to store the disabled post types, so that when a new post type is added, it is enabled by default.
	 */
	public static function validate_setting_post_types( $enabled_post_types ) {
		// Get a list of all possible post types.
		$disabled_post_types = get_post_types( array( 'public' => true ), 'names' );

		// Remove the enabled post types from this list.
		foreach ( $enabled_post_types as $post_type ) {
			unset( $disabled_post_types[$post_type] );
		}

		return $disabled_post_types;
	}

	/**
	 * Parse and validate the attribute fields
	 */
	public static function validate_setting_attributes( $attribute_data ) {
		$results = array();

		// For each array of field data, pull out the data, and store it in $results
		for ( $i = 0; $i < count( $attribute_data['name'] ); $i++ ) {
			$name = $attribute_data['name'][$i];

			if ( ! empty( $name ) ) {
				$slug = sanitize_title( $attribute_data['slug'][$i], sanitize_title( $name ), 'save' );

				$results[ $slug ] = array(
					'name'       => $name,
					'slug'       => $slug,
					'type'       => array_key_exists( $attribute_data['type'][$i], self::$attribute_types ) ? $attribute_data['type'][$i] : 'text',
					'options'    => array_map( 'trim', explode( ",", $attribute_data['options'][$i] ) ),
				);
			}
		}

		return $results;
	}

	/**
	 * Validate that the data is a set of fields.
	 * This validation function is used by visible fields, and sortable fields.
	 */
	public static function validate_setting_fields_set( $data ) {
		$results = array();

		if ( is_array( $data ) ) {
			// For this type, we just do a simple text field sanitization on each array element.
			foreach ( $data as $i => $value ) {
				$results[ $i ] = sanitize_text_field( $value );
			}
		}

		return $results;
	}

	/**
	 * Convert the string output of a form element, into a boolean. Default to false.
	 */
	public static function validate_setting_boolean( $value ) {
		return $value == 'on';
	}

	/**
	 * Convert the string output of a form element, into a boolean. Default to false.
	 * Additionally, if the custom post type enabled setting has been changed, we need to flush our rewrite rules.
	 */
	public static function validate_setting_enable_post_type( $value ) {
		$value = ( $value == 'on' );

		if ( $value != self::is_post_type_enabled() ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}

		return $value;
	}

}

add_action( 'init', array( 'CTLTRES_Configuration', 'init' ) );
