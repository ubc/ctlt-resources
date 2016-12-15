<?php

/**
 * 
 */
class CTLTRES_Metabox {
	// This id is used to uniquely identify our metabox.
	public static $metabox_id = 'ctltres_metabox';
	public static $field_name = 'ctltres_resource_fields';
	public static $category_name = 'ctltres_resource_category';
	public static $embed_options_name = 'ctltres_resource_embed';
	public static $nonce_name = 'ctltres_metabox_nonce';

	/**
	 * @filter init
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	public static function enqueue_scripts() {
		wp_register_script( 'ctltres-metabox', CTLT_Resources::$directory_url . 'admin/js/ctlt-resources-metabox.js', array ( 'jquery' ) );
	}

	// TODO: Add this to Media post types as well
	public static function add_meta_box() {
		if ( get_post_type() == CTLTRES_Resources::$post_type_slug ) {
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

	public static function render_meta_box() {
		// TODO: Check if there are no taxonomy terms, and if so direct the user to create some.
		wp_nonce_field( plugin_basename( __FILE__ ), self::$nonce_name );
		wp_enqueue_script( 'ctltres-metabox' );

		if ( get_post_type() != CTLTRES_Resources::$post_type_slug ) {
			?>
			<p><em>Select the options below to tag this <?php echo get_post_type(); ?> as a resource.</em></p>
			<?php
		}
		?>
		<p><strong>Category</strong></p>
		<label class="screen-reader-text" for="ctltres_type">Category</label>
		<?php
		$category = CTLTRES_Resources::get_category();

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
			$dropdown_options['show_option_none'] = "Not a Resource";
		}

		wp_dropdown_categories( $dropdown_options );

		$visibility = empty( $category ) ? 'style="display:none;"' : '';

		?>
		<p id="ctltres_metabox_embed_options" <?php echo $visibility; ?>>
			<?php
			$embed_options = CTLTRES_Resources::get_embed_options();

			self::render_embed_options_checkbox( 'show_attributes', $embed_options, "Show resource attributes table" );
			self::render_embed_options_checkbox( 'show_list', $embed_options, "Show list of similar resources" );
			self::render_embed_options_checkbox( 'show_search', $embed_options, "Show search form with resource list" );
			?>
			<em>
				The above content can also be embedded using the [cres_attributes] and [cres_list] shortcodes.
			</em>
		</p>

		<p id="ctltres_metabox_attributes" <?php echo $visibility; ?>>
			<?php
			$data = CTLTRES_Resources::get_attributes();

			foreach ( CTLTRES_Configuration::get_attribute_fields() as $slug => $attribute ) {
				$value = array_key_exists( $slug, $data ) ? $data[ $slug ] : null;
				self::render_attribute_field( $attribute, $value );
			}
			?>
		</p>
		<?php
	}

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

	public static function save_meta_box() {
		$post_id = get_the_ID();

		if ( ! isset( $_POST[ self::$nonce_name ] ) || ! wp_verify_nonce( $_POST[ self::$nonce_name ], plugin_basename(__FILE__) )) {
			return $post_id;
		}

		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $post_id;
		}

		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();
		$data = $_POST[ self::$field_name ];

		foreach ( $attribute_fields as $slug => $attribute ) {
			$value = isset( $data[ $slug ] ) ? $data[ $slug ] : null;
			$value = self::validate_attribute( $attribute, $value );
			update_post_meta( $post_id, CTLTRES_Resources::get_attribute_metakey( $slug ), $value );
		}

		if ( isset( $data[ self::$category_name ] ) ) {
			wp_set_object_terms( $post_id, intval( $data[ self::$category_name ] ), CTLTRES_Resources::$taxonomy_slug );
		} else {
			wp_set_object_terms( $post_id, array(), CTLTRES_Resources::$taxonomy_slug );
		}

		$embed_options = isset( $data[ self::$embed_options_name ] ) ? $data[ self::$embed_options_name ] : array();

		$embed_options = shortcode_atts( array(
			'show_attributes' => 'off',
			'show_list'       => 'off',
			'show_search'     => 'off',
		), $embed_options );

		foreach ( $embed_options as $slug => $value ) {
			$embed_options[ $slug ] = ( $value == 'on' );
		}

		update_post_meta( $post_id, CTLTRES_Resources::get_attribute_metakey( 'embed' ), $embed_options );
	}

	public static function validate_attribute( $attribute, $value ) {
		switch ( $attribute['type'] ) {
			case 'multiselect':
				if ( is_array( $value ) && count( $value ) > 0) {
					foreach ( $value as $i => $val ) {
						if ( ! in_array( $val, $attribute['options'] ) ) {
							unset( $value[$i] );
						}
					}

					return $value;
				} else {
					return array();
				}
			case 'select':
				return in_array( $value, $attribute['options'] ) ? $value : "";
			case 'url':
				return esc_url( $value );
			case 'number':
				return intval( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

}

add_action( 'init', array( 'CTLTRES_Metabox', 'init' ) );
