<?php

/**
 * This class manages the Resource Category taxonomy, the Resource custom post type, and functions related to post types that have been tagged as resources.
 */
class CTLTRES_Resources {
	
	// A slug for the custom page
	public static $navigation_slug = "resources";
	// A slug for the custom post type
	public static $post_type_slug = "resource-article";
	// A slug for the taxonomy
	public static $taxonomy_slug = "resources";
	// A slug where all resource related meta data is stored.
	public static $metadata = "ctltres_meta";

	/**
	 * @filter init
	 */
	public static function init() {
		// Register the resource category taxonomy
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

		// If enabled, register the Resource custom post type.
		if ( CTLTRES_Configuration::is_post_type_enabled() ) {
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

	/**
	 * Get the category which is assigned to the given post id, if any.
	 * If no post id is provided, one will be inferred using get_the_ID.
	 */
	public static function get_category( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$categories = wp_get_post_terms( get_the_ID(), self::$taxonomy_slug, array( 'fields' => 'ids' ) );

		return count( $categories ) > 0 ? $categories[0] : null;
	}

	/**
	 * Get the configured embed options for the given post id.
	 * If no post id is provided, one will be inferred using get_the_ID.
	 */
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

	/**
	 * Get the defined attributes for the given post id.
	 * If no post id is provided, one will be inferred using get_the_ID.
	 */

	public static function get_attributes( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$results = array();

		// For each attribute field, extract the value from our post meta.
		foreach ( CTLTRES_Configuration::get_attribute_fields() as $slug => $attribute ) {
			$results[ $slug ] = get_post_meta( $post_id, self::get_attribute_metakey( $slug ), true );
		}

		return $results;
	}

	/**
	 * @return a meta key for accessing ctlt-resources related data.
	 */
	public static function get_attribute_metakey( $attribute_slug ) {
		return self::$metadata . '_' . $attribute_slug;
	}

	/**
	 * Parses search filter arguments from a given array.
	 */
	public static function parse_filter_arguments( $source ) {
		$result = array();
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		// For each searchable field
		foreach ( CTLTRES_Configuration::get_searchable_fields() as $slug ) {
			// Look through the source, and define the filter, if it exists there.

			if ( $slug == 'category' ) {
				// Category is a special search filter, that lets you filter by the Resource Category taxonomy
				if ( in_the_loop() && is_archive( self::$taxonomy_slug ) ) {
					// If we are currently in a taxonomy archive, then use the category which we are already searching.
					// TODO: We might want to remove this clause, because it can hijack your query.
					$result['category'] = get_queried_object_id();
				} else if ( isset( $source['category'] ) ) {
					// Otherwise, use the value in the source
					$result['category'] = intval( $source['category'] );
				}
			} else if ( $slug == 'search' ) {
				// Search is a special filter, which does a text search.
				if ( isset( $source['search'] ) ) {
					// Sanitize the input.
					$result['search'] = sanitize_text_field( $source['search'] );
				}
			} else if ( isset( $source[ $slug ] ) && ! empty( $source[ $slug ] ) ) {
				// All other search filters are related to a specific attribute field.
				$attribute = $attribute_fields[ $slug ];
				$value = $source[ $slug ];

				// Sanitize the input
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

	/**
	 * Add the automatically embedded UI to any resource, as defined by that resource's embed options.
	 */
	public static function filter_content( $content ) {
		// If this is a resource archive, don't do anything
		if ( CTLTRES_Archive::$is_archive ) {
			return $content;
		}

		// Get the category which has been assigned to this post
		$category = self::get_category();

		// If this is a Resource post type, then we also need to make the excerpt be it's content.
		if ( get_post_type() == self::$post_type_slug ) {
			$content = do_shortcode( get_the_excerpt() );
		}

		// Check that this post is actually a resource, meaning it has a defined category.
		if ( ! empty( $category ) ) {
			ob_start();

			// Get the embed options.
			$embed_options = self::get_embed_options();

			// Show the resource's attributes, if appropriate.
			if ( isset( $embed_options['show_attributes'] ) && $embed_options['show_attributes'] ) {
				self::render_attributes();
			}

			// Show a list of related resources, if allowed.
			if ( isset( $embed_options['show_list'] ) && $embed_options['show_list'] ) {
				// A basic filter for the list, which makes it show resources with the same category.
				$arguments = array(
					'filters' => array(
						'category' => $category,
					),
				);

				// If search is enabled, render that as well.
				if ( isset( $embed_options['show_search'] ) && $embed_options['show_search'] ) {
					CTLTRES_Archive::render_search_form( $arguments );
				}

				// Render the list.
				CTLTRES_Archive::render_resource_list( $arguments );

				// Also provide a link to the full resource list.
				$term_link = get_term_link( $category, CTLTRES_Resources::$taxonomy_slug );
				?>
				<a class="ctltres-list-link" href="<?php echo $term_link; ?>">See more resources</a>
				<?php
			}

			// Append all this to content.
			$content .= ob_get_clean();
		}

		return $content;
	}

	/**
	 * Render a list of the resource's attributes
	 */
	public static function render_attributes() {
		$category = self::get_category();

		// If there is no category, this is not a resource, so do nothing.
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
			// Render each attribute value alongside it's name.
			foreach ( $attribute_fields as $attribute ) {
				if ( array_key_exists( $attribute['slug'], $attribute_values ) ) {
					$value = $attribute_values[ $attribute['slug'] ];

					// If the attribute is empty, don't display it.
					if ( empty( $value ) ) {
						continue;
					}

					// If it is an array, then convert it to a comma seperated list.
					if ( is_array( $value ) ) {
						$value = implode( ", ", $value );
					}

					// If the attribute is a url, wrap it in a link.
					if ( $attribute['type'] == 'url' ) {
						$value = '<a href="' . $value . '">' . $value . '</a>';
					}

					// Display the result.
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
