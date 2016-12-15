<?php

/**
 * 
 */
class CTLTRES_Configuration {
	
	public static $parent_slug = 'ctlt-resources';
	public static $menu_slug = 'ctlt-resources';
	public static $settings_group = 'ctlt-resources';

	public static $section_general = 'general';
	public static $setting_enable_post_type = 'enabled_post_type';
	public static $setting_post_types = 'disabled_post_types';
	public static $setting_attributes = 'attributes';

	public static $section_listings = 'listings';
	public static $setting_enable_search = 'enable_search';
	public static $setting_visible_fields = 'visible_fields';
	public static $setting_searchable_fields = 'searchable_fields';

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
		if ( self::is_post_type_enabled() ) {
			self::$parent_slug = "edit.php?post_type=" . CTLTRES_Resources::$post_type_slug;
		} else {
			self::$parent_slug = self::$menu_slug;
		}

		add_action( 'admin_menu', array( __CLASS__, 'create_menus' ) );
		add_action( 'parent_file', array( __CLASS__, 'highlight_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ) );
	}

	public static function is_post_type_enabled() {
		$value = get_option( self::$setting_enable_post_type, false, true );
		return empty( $value ) ? false : $value;
	}

	public static function get_allowed_post_types() {
		// TODO: Should comments be allowed to be resources?
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$disabled_post_types = get_option( self::$setting_post_types, array(), true );

		foreach ( $disabled_post_types as $post_type ) {
			unset( $post_types[$post_type] );
		}

		return $post_types;
	}

	public static function get_attribute_fields() {
		return get_option( self::$setting_attributes, array(), true );
	}

	public static function is_search_enabled() {
		$value = get_option( self::$setting_enable_search, true, true );
		return empty( $value ) ? true : $value;
	}

	public static function get_searchable_fields() {
		return get_option( self::$setting_searchable_fields, array(), true );
	}

	public static function get_visible_fields() {
		return get_option( self::$setting_visible_fields, array(), true );
	}

	public static function enqueue_scripts_and_styles() {
		wp_register_script( 'jquery-sortable-johnny', CTLT_Resources::$directory_url . 'admin/js/jquery-sortable.min.js', array( 'jquery' ) );
		wp_register_script( 'ctltres-configuration', CTLT_Resources::$directory_url . 'admin/js/ctlt-resources-configuration.js', array( 'jquery', 'jquery-sortable-johnny' ) );
		wp_register_style( 'ctltres-configuration', CTLT_Resources::$directory_url . 'admin/css/ctlt-resources-configuration.css' );
	}

	public static function create_menus() {
		if ( self::is_post_type_enabled() ) {
			add_submenu_page(
				self::$parent_slug,
				__( "Resource Settings", 'ctltres' ), // Page title
				__( "Settings", 'ctltres' ), // Menu title
				'manage_options',
				self::$menu_slug,
				array( __CLASS__, 'render_page' )
			);
		} else {
			add_menu_page(
				__( "Resource Settings", 'ctltres' ), // Page title
				__( "Resources", 'ctltres' ), // Menu title
				'manage_options',
				self::$parent_slug,
				array( __CLASS__, 'render_page' ),
				'dashicons-index-card'
			);

			add_submenu_page(
				self::$parent_slug,
				__( "Resource Settings", 'ctltres' ), // Page title
				__( "Settings", 'ctltres' ), // Menu title
				'manage_options',
				self::$menu_slug
			);
		}

		add_submenu_page(
			self::$parent_slug,
			__( "Resource Categories", 'ctltres' ), // Page title
			__( "Categories", 'ctltres' ), // Menu title
			'manage_options',
			'edit-tags.php?taxonomy=' . CTLTRES_Resources::$taxonomy_slug
		);
	}

	public static function highlight_menu( $parent_file ) {
		global $current_screen;

        $taxonomy = $current_screen->taxonomy;
        if ( $taxonomy == CTLTRES_Resources::$taxonomy_slug ) {
            $parent_file = self::$parent_slug;
        }

        return $parent_file;
	}

	public static function register_settings() {
		register_setting( self::$settings_group, self::$setting_enable_post_type, array( __CLASS__, 'validate_setting_enable_post_type' ) );
		register_setting( self::$settings_group, self::$setting_post_types, array( __CLASS__, 'validate_setting_post_types' ) );
		register_setting( self::$settings_group, self::$setting_attributes, array( __CLASS__, 'validate_setting_attributes' ) );
		register_setting( self::$settings_group, self::$setting_enable_search, array( __CLASS__, 'validate_setting_boolean' ) );
		register_setting( self::$settings_group, self::$setting_searchable_fields, array( __CLASS__, 'validate_setting_fields_set' ) );
		register_setting( self::$settings_group, self::$setting_visible_fields, array( __CLASS__, 'validate_setting_fields_set' ) );

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

	public static function render_page() {
		wp_enqueue_script( 'jquery-sortable-johnny' );
		wp_enqueue_script( 'ctltres-configuration' );
		wp_enqueue_style( 'ctltres-configuration' );

		?>
		<form action="options.php" method="POST">
			<?php
			settings_fields( self::$settings_group );
			do_settings_sections( self::$menu_slug );
			submit_button();
			?>
		</form>
		<?php
	}

	public static function render_section_general() {
		// Do nothing
	}

	public static function render_section_listings() {
		// Do nothing
	}

	public static function render_setting_enable_post_type() {
		$enabled = self::is_post_type_enabled();
		
		?>
		<select name="<?php echo self::$setting_enable_post_type; ?>">
			<option value="on" <?php selected( $enabled ); ?>>Enabled</option>
			<option value="off" <?php selected( $enabled, false ); ?>>Disabled</option>
		</select>
		<?php
	}

	public static function render_setting_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$disabled_post_types = get_option( self::$setting_post_types, array(), true );
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

	public static function render_setting_attributes() {
		?>
		<em>Choose fields that can be defined for each resource.</em>
		<br>
		<em>For dropdowns and checkboxes, the allowed values should be a comma seperated list.</em>
		<ul id="ctltres-attribute-container">
			<?php
			foreach ( self::get_attribute_fields() as $attribute ) {
				self::render_setting_attribute_block( $attribute );
			}

			self::render_setting_attribute_block();
			?>
		</ul>
		<em>Visible and Sortable fields will update after your changes are saved.</em>
		<?php
	}

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

	public static function render_setting_searchable_fields() {
		$leftover_fields = array();
		$searchable_fields = array();

		$potential_fields = array(
			'search'   => array( 'name' => "Full Text" ),
			'category' => array( 'name' => "Resource Category" ),
		);

		$potential_fields = array_merge( $potential_fields, self::get_attribute_fields() );

		foreach ( self::get_searchable_fields() as $slug ) {
			$searchable_fields[ $slug ] = $potential_fields[ $slug ]['name'];
		}

		foreach ( $potential_fields as $slug => $attribute ) {
			if ( ! array_key_exists( $slug, $searchable_fields ) ) {
				$leftover_fields[ $slug ] = $attribute['name'];
			}
		}

		?>
		<em>Select which fields are searchable. Drag the fields to reorder them.</em>
		<ol id="ctltres-search-fields" class="sortable">
			<?php
			foreach ( $searchable_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_searchable_fields, true );
			}

			foreach ( $leftover_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_searchable_fields );
			}
			?>
		</ol>
		<?php
	}

	public static function render_setting_visible_fields() {
		$leftover_fields = array();
		$visible_fields = array();

		$potential_fields = array(
			'category' => array( 'name' => "Resource Category" ),
			'date'     => array( 'name' => "Last Modified Date" ),
		);

		$potential_fields = array_merge( $potential_fields, self::get_attribute_fields() );

		foreach ( self::get_visible_fields() as $slug ) {
			$visible_fields[ $slug ] = $potential_fields[ $slug ]['name'];
		}

		foreach ( $potential_fields as $slug => $attribute ) {
			if ( ! array_key_exists( $slug, $visible_fields ) ) {
				$leftover_fields[ $slug ] = $attribute['name'];
			}
		}

		?>
		<em>Select which fields are shown in the resource lists. Drag the fields to reorder them.</em>
		<ol id="ctltres-visible-fields" class="sortable">
			<?php
			foreach ( $visible_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_visible_fields, true );
			}

			foreach ( $leftover_fields as $slug => $name ) {
				self::render_setting_list_checkbox( $name, $slug, self::$setting_visible_fields );
			}
			?>
		</ol>
		<?php
	}

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

	public static function validate_setting_post_types( $enabled_post_types ) {
		$disabled_post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $enabled_post_types as $post_type ) {
			unset( $disabled_post_types[$post_type] );
		}

		return $disabled_post_types;
	}

	public static function validate_setting_attributes( $attribute_data ) {
		$results = array();

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

	public static function validate_setting_fields_set( $data ) {
		$results = array();

		if ( is_array( $data ) ) {
			foreach ( $data as $i => $value ) {
				$results[ $i ] = sanitize_text_field( $value );
			}
		}

		return $results;
	}

	public static function validate_setting_boolean( $value ) {
		return $value == 'on';
	}

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
