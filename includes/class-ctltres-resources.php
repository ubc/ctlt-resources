<?php

/**
 * 
 */
class CTLTRES_Resources {
	
	public static $navigation_slug = "resources";
	public static $post_type_slug = "resource-article";
	public static $taxonomy_slug = "resources";
	public static $metadata = "ctltres_meta";

	/**
	 * @filter init
	 */
	public static function init() {
		$custom_post_type_enabled = CTLTRES_Configuration::is_post_type_enabled();

		register_taxonomy(
			self::$taxonomy_slug,
			CTLTRES_Configuration::get_allowed_post_types(),
			array(
				'label'  => __( "Resource Categories", 'ctltres' ),
				'labels' => array(
					'name'          => __( "Resource Categories", 'ctltres' ),
					'singular_name' => __( "Resource Category", 'ctltres' ),
				),
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'hierarchical'      => true,
				'meta_box_cb'       => false,
			)
		);

		if ( $custom_post_type_enabled ) {
			register_post_type(
				self::$post_type_slug,
				array(
					'label'  => __( "Resources", 'ctltres' ),
					'labels' => array(
						'name'          => __( "Resources", 'ctltres' ),
						'singular_name' => __( "Resource", 'ctltres' ),
						'add_new_item'  => __( "Add New Resource", 'ctltres' ),
						'edit_item'     => __( "Edit Resource", 'ctltres' ),
						'new_item'      => __( "New Resource", 'ctltres' ),
						'view_item'     => __( "View Resource", 'ctltres' ),
						'view_items'    => __( "View Resources", 'ctltres' ),
						'search_items'  => __( "Search Resources", 'ctltres' ),
						'not_found'     => __( "No resources found", 'ctltres' ),
						'not_found_in_trash' => __( "No resources found in Trash", 'ctltres' ),
						'all_items'     => __( "All Resources", 'ctltres' ),
						'archives'      => __( "Resource Archives", 'ctltres' ),
						'attributes'    => __( "Resource Attributes", 'ctltres' ),
						'insert_into_item' => __( "Insert into resource", 'ctltres' ),
						'uploaded_to_this_item' => __( "Uploaded to this resource", 'ctltres' ),
					),
					'description'   => "",
					'public'        => true,
					'menu_position' => 50,
					'supports'      => array( 'title', 'excerpt' ),
					'has_archive'   => true,
					'menu_icon'     => 'dashicons-index-card',
				)
			);
		}

		add_filter( 'the_content', array( __CLASS__, 'filter_content' ) );
	}

	public static function get_category( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$categories = wp_get_post_terms( get_the_ID(), self::$taxonomy_slug, array( 'fields' => 'ids' ) );

		return count( $categories ) > 0 ? $categories[0] : null;
	}

	public static function get_embed_options( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$results = get_post_meta( $post_id, self::get_attribute_metakey( 'embed' ), true );

		return shortcode_atts( array(
			'show_attributes' => true,
			'show_list'       => false,
			'show_search'     => false,
		), $results );
	}

	public static function get_attributes( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$results = array();

		foreach ( CTLTRES_Configuration::get_attribute_fields() as $slug => $attribute ) {
			$results[ $slug ] = get_post_meta( $post_id, self::get_attribute_metakey( $slug ), true );
		}

		return $results;
	}

	public static function get_attribute_metakey( $attribute_slug ) {
		return self::$metadata . '_' . $attribute_slug;
	}

	public static function parse_filter_arguments( $source ) {
		$result = array();
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		foreach ( CTLTRES_Configuration::get_searchable_fields() as $slug ) {
			if ( $slug == 'category' ) {
				if ( in_the_loop() && is_archive( self::$taxonomy_slug ) ) {
					$result['category'] = get_queried_object_id();
				} else if ( isset( $source['category'] ) ) {
					$result['category'] = intval( $source['category'] );
				}
			} else if ( $slug == 'search' ) {
				if ( isset( $source['search'] ) ) {
					$result['search'] = sanitize_text_field( $source['search'] );
				}
			} else if ( isset( $source[ $slug ] ) && ! empty( $source[ $slug ] ) ) {
				$attribute = $attribute_fields[ $slug ];
				$value = $source[ $slug ];

				switch ( $attribute['type'] ) {
					case 'multiselect':
					case 'select':
						$value = in_array( $value, $attribute['options'] ) ? $value : null;
						break;
					case 'url':
						$value = esc_url( $value );
						break;
					case 'number':
						$value = sanitize_text_field( $value );
						$value = intval( $value );
						break;
					default:
						$value = sanitize_text_field( $value );
						break;
				} 

				$result[ $slug ] = $value;
			}
		}

		return $result;
	}

	public static function filter_content( $content ) {
		if ( CTLTRES_Archive::$is_archive ) {
			return $content;
		}

		$category = self::get_category();

		if ( get_post_type() == self::$post_type_slug ) {
			$content = do_shortcode( get_the_excerpt() );
		}

		if ( ! empty( $category ) ) {
			ob_start();

			$embed_options = self::get_embed_options();

			if ( isset( $embed_options['show_attributes'] ) && $embed_options['show_attributes'] ) {
				self::render_attributes();
			}

			if ( isset( $embed_options['show_list'] ) && $embed_options['show_list'] ) {
				$arguments = array(
					'filters' => array(
						'category' => $category,
					),
				);

				if ( isset( $embed_options['show_search'] ) && $embed_options['show_search'] ) {
					CTLTRES_Archive::render_search_form( $arguments );
				}

				CTLTRES_Archive::render_resource_list( $arguments );

				$term_link = get_term_link( $category, CTLTRES_Resources::$taxonomy_slug );
				?>
				<a class="ctltres-list-link" href="<?php echo $term_link; ?>">See more resources</a>
				<?php
			}

			$content .= ob_get_clean();
		}

		return $content;
	}

	public static function render_attributes() {
		$category = self::get_category();

		if ( empty( $category ) ) {
			return;
		}

		$category = get_term( $category, self::$taxonomy_slug );
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();
		$attribute_values = self::get_attributes();

		?>
		<table class="ctltres-attributes">
			<tr>
				<th>Category</th>
				<td>
					<a href="<?php echo get_term_link( $category ); ?>"><?php echo $category->name; ?></a>
				</td>
			<tr>
			<?php
			foreach ( $attribute_fields as $attribute ) {
				if ( array_key_exists( $attribute['slug'], $attribute_values ) ) {
					$value = $attribute_values[ $attribute['slug'] ];

					if ( empty( $value ) ) {
						continue;
					}

					if ( is_array( $value ) ) {
						$value = implode( ", ", $value );
					}

					if ( $attribute['type'] == 'url' ) {
						$value = '<a href="' . $value . '">' . $value . '</a>';
					}

					?>
					<tr>
						<th><?php echo $attribute['name']; ?></th>
						<td><?php echo $value; ?></td>
					<tr>
					<?php
				}
			}
			?>
		</table>
		<?php
	}

}

add_action( 'init', array( 'CTLTRES_Resources', 'init' ) );
