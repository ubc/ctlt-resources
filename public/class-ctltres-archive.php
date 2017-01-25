<?php

/**
 * This class creates the resource archive page, including any necessary rewrite rules.
 */
class CTLTRES_Archive {

	public static $is_archive = false;
	public static $archive_category = null;

	private static $executing_resource_query = false;
	
	/**
	 * @filter init
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'filter_query_vars' ) );
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ) ) ;
		add_filter( 'template_include', array( __CLASS__, 'filter_template' ) ) ;
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		self::add_rewrite_rules();
	}

	/**
	 * @filter wp_enqueue_scripts
	 */
	public static function enqueue_styles() {
		wp_register_style( 'ctltres-archive', CTLT_Resources::$directory_url . 'public/css/ctlt-resources-archive.css' );
	}

	/**
	 * Adds the rewrite rules needed to make the archives render
	 */
	public static function add_rewrite_rules() {
		// TODO: This can probably be done more efficiently. We don't need to flush on every load, do we?
		global $wp;

		$wp->add_query_var('ctltres');
		add_rewrite_rule( '^' . CTLTRES_Resources::$navigation_slug . '/?$', 'index.php?ctltres=all-categories', 'top' );
		add_rewrite_rule( '^' . CTLTRES_Resources::$navigation_slug . '/([^/]+)/?$', 'index.php?ctltres=$matches[1]', 'top' );

		// Check if the plugin is currently being activated.
		//if ( CTLT_Resources::$is_being_activated ) {
			// If so flush the rewrite rules
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		//}
	}

	/**
	 * We need Wordpress to recognize our custom query var, so that we know when we are looking at an archive.
	 *
	 * @filter query_vars
	 */
	public static function filter_query_vars( $vars ) {
		$vars[] = 'ctltres';
		return $vars;
	}

	/**
	 * This function extracts the intended category for this archive, from the query vars.
	 *
	 * @filter parse_query
	 */
	public static function parse_query() {
		global $wp_query;

		// Make sure that we don't parse twice
		if ( ! self::$is_archive ) {
			// Extract the archive category
			self::$archive_category = get_query_var( 'ctltres', null );
			self::$is_archive = ( self::$archive_category != null );

			// If we are looking for all categories, then null the archive category value.
			if ( self::$archive_category == 'all-categories' ) {
				self::$archive_category = null;
			}
		}
	}

	/**
	 * Redirect Wordpress to use a template of our choice for the archive.
	 *
	 * @filter template_include
	 */
	public static function filter_template( $template ) {
		if ( self::$is_archive ) {
			// Let's make it use the single template.
			$template = locate_template( 'single.php' );

			// Since this method is a bit hackish, we need to hook into two filters to make sure that Wordpress displays the intended content.
			add_filter( 'the_content', array( __CLASS__, 'get_archive_content' ) );
			add_filter( 'the_excerpt', array( __CLASS__, 'get_archive_content' ) );
			add_filter( 'get_the_archive_title', array( __CLASS__, 'get_archive_title' ) );

			// Change the query so it only renders one post. We will change that content of that singular post.
			query_posts( array(
				'posts_per_page'      => 1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			) );

			// Remove all pagination, we will do our own pagination.
			remove_all_actions( '__after_loop' );
		}

		return $template;
	}

	/**
	 * Render the content for our archive, overriding any other content Wordpress might want to insert.
	 *
	 * @filter the_content
	 */
	public static function get_archive_content( $content ) {
		// First make sure that we are in the main loop.
		if ( in_the_loop() ) {
			$data = $_REQUEST;

			// Get the term for our category.
			$category = get_term_by( 'slug', self::$archive_category, CTLTRES_Resources::$taxonomy_slug );

			// If our category is not empty, add it to our query parameters so that it is included as a filter.
			if ( ! empty( $category ) ) {
				$data['category'] = $category->term_id;
			}

			// Parse any filters from the query data.
			$args = array(
				'filters' => CTLTRES_Resources::parse_filter_arguments( $data ),
			);

			ob_start();

			wp_enqueue_style( 'ctltres-archive' );

			?>
			<div id="ctltres-archive-content">
				<?php
					// If the search form is enabled, render it.
					if ( CTLTRES_Configuration::is_search_enabled() ) {
						self::render_search_form( $args );
					}

					// If there is at least one filter, render a clear filters button.
					if ( ! empty( $args['filters'] ) ) {
						?>
						<div class="ctltres-clear-filters">
							<a href="<?php echo home_url( CTLTRES_Resources::$navigation_slug ); ?>" class="btn btn-primary button button-primary">Clear Filters</a>
						</div>
						<?php
					}

					// Render the resources
					self::render_resource_list( $args );
				?>
			</div>
			<?php

			$content = ob_get_clean();
		}

		return $content;
	}

	/**
	 * Replace the archive title
	 *
	 * @filter the_title
	 */
	public static function get_archive_title( $title ) {
		if ( self::$is_archive ) {
			// If we are looking at a resource archive, we want our own title.
			$title = "Resources";
		}

		return $title;
	}

	/**
	 * Renders a resource search form.
	 */
	public static function render_search_form( $args = array() ) {
		// Get a list of slugs for fields which are searchable.
		$searchable_fields = CTLTRES_Configuration::get_searchable_fields();
		// Get a all attribute fields.
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		?>
		<form action="<?php echo home_url( CTLTRES_Resources::$navigation_slug ); ?>" method="GET">
			<?php
			// Render the searchable fields.
			foreach ( $searchable_fields as $slug ) {
				// If there is a filter value, extract it.
				$value = array_key_exists( $slug, $args['filters'] ) ? $args['filters'][ $slug ] : null;

				switch ( $slug ) {
					case 'category':
						wp_dropdown_categories( array(
							'taxonomy'        => CTLTRES_Resources::$taxonomy_slug,
							'name'            => 'category',
							'show_option_all' => "All Categories",
							'hierarchical'    => 1,
							'hide_empty'      => true,
							'value_field'     => 'term_id',
							'selected'        => is_numeric( $value ) ? $value : -1,
						) );
						break;
					case 'search':
						?>
						<input name="search" type="text" placeholder="Search Text" value="<?php echo $value; ?>"></input>
						<?php
						break;
					default:
						// If this field is not category or search, then it is an attribute.
						// Get the actual field, using our slug
						$attribute = $attribute_fields[ $slug ];
						// Render the search field.
						self::render_search_field( $attribute, $value );
				}
			}
			?>
			<br>
			<input type="submit" class="btn btn-primary button button-primary" value="Search"></input>
		</form>
		<br>
		<?php
	}

	/**
	 * Renders a field for the resource search form.
	 */
	public static function render_search_field( $attribute, $value = null ) {
		switch ( $attribute['type'] ) {
			case 'multiselect':
			case 'select':
				// Select fields should be rendered as a dropdown.
				?>
				<select name="<?php echo $attribute['slug']; ?>">
					<option value="">- Filter <?php echo $attribute['name']; ?> -</option>
					<?php
					foreach ( $attribute['options'] as $option ) {
						?>
						<option value="<?php echo $option; ?>" <?php selected( $value, $option ); ?>><?php echo $option; ?></option>
						<?php
					}
					?>
				</select>
				<?php
				break;
			default:
				// All other fields are a simple text box.
				?>
				<input type="<?php echo $attribute->type; ?>" name="<?php echo $attribute['slug']; ?>" placeholder="Filter <?php echo $attribute['name']; ?>" value="<?php echo $value; ?>"></input>
				<?php
				break;
		}
	}

	/**
	 * Renders a list of resources.
	 */
	public static function render_resource_list( $args = array() ) {
		// Extract the predefined filters
		$filters = isset( $args['filters'] ) ? $args['filters'] : array();

		// Construct query arguments for the resource list.
		$parameters = array(
			'posts_per_page' => isset( $args['limit'] ) ? $args['limit'] : -1,
			'post_type'      => CTLTRES_Configuration::get_allowed_post_types(),
			'tax_query' => array(
				'relation' => 'OR',
			),
			'meta_query' => array(
				'relation' => 'AND',
			),
		);

		// If the search filter is defined, add it to our arguments.
		if ( isset( $filters['search'] ) && ! empty( $filters['search'] ) ) {
			$parameters['s'] = $filters['search'];
		}

		// If the category filter is defined, add it to our arguments.
		if ( isset( $filters['category'] ) && is_numeric( $filters['category'] ) && $filters['category'] > 0 ) {
			$parameters['tax_query'][] = array(
				'taxonomy'         => CTLTRES_Resources::$taxonomy_slug,
				'field'            => 'term_id',
				'terms'            => $filters['category'],
				'include_children' => true,
				'operator'         => 'IN',
			);
		} else {
			// Otherwise, restrict the search to posts that have any category from the Resource category taxonomy.

			// Get a list of all terms
			$terms = get_terms( array(
				'taxonomy' => CTLTRES_Resources::$taxonomy_slug,
			) );

			// Simplify the list to only have term IDs
			foreach ( $terms as $i => $term ) {
				$terms[ $i ] = $term->term_id;
			}

			// Add the appropriate taxonomy query.
			$parameters['tax_query'][] = array(
				'taxonomy' => CTLTRES_Resources::$taxonomy_slug,
				'terms'    => $terms,
				'operator' => 'IN',
			);
		}

		// Remove the special filters
		unset( $filters['search'] );
		unset( $filters['category'] );

		// Get all the attribute fields
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		// Add arguments for all attribute fields that have defined filters.
		foreach ( $filters as $slug => $value ) {
			$attribute = $attribute_fields[ $slug ];

			// Add a post meta query, for each attribute
			$parameters['meta_query'][] = array(
				'key'     => CTLTRES_Resources::get_attribute_metakey( $slug ),
				'value'   => $attribute['type'] == 'multiselect' ? serialize( $value ) : $value,
				'compare' => $attribute['type'] == 'multiselect' ? 'LIKE' : '=',
				'type'    => $attribute['type'] == 'number' ? 'NUMERIC' : 'CHAR',
			);
		}

		// Check if attributes should be shown in this archive
		$show_attributes = ( ! isset( $args['show_attributes'] ) || $args['show_attributes'] );

		$query = new WP_Query( $parameters );

		if ( $query->have_posts() ) {
			// Set this value so that we don't accidentally interfere with our own query.
			self::$executing_resource_query = true;

			?>
			<table>
				<?php
				// If appropriate, show the attribute titles
				if ( $show_attributes ) {
					?>
					<tr>
						<th>Title</th>
						<?php
						foreach ( CTLTRES_Configuration::get_visible_fields() as $slug ) {
							if ( $slug == 'category' ) {
								$text = "Category";
							} elseif ( $slug == 'date' ) {
								$text = "Date";
							} else {
								$text = $attribute_fields[ $slug ]['name'];
							}
							
							?>
							<th><?php echo $text; ?></th>
							<?php
						}
					?>
					</tr>
					<?php
				}

				// Loop through each resource in the query, and render it in our table.
				while ( $query->have_posts() ) {
					$query->the_post();

					?>
					<tr>
						<td>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</td>
						<?php
						// If appropriate, render the attributes.
						if ( $show_attributes ) {
							$attributes = CTLTRES_Resources::get_attributes();

							foreach ( CTLTRES_Configuration::get_visible_fields() as $slug ) {
								if ( $slug == 'category' ) {
									$term_id = CTLTRES_Resources::get_category();
									$text = get_term( $term_id, CTLTRES_Resources::$taxonomy_slug )->name;
								} elseif ( $slug == 'date' ) {
									$text = get_the_modified_date();
								} else {
									$text = $attributes[ $slug ];

									// If the value is an array, convert it to a comma seperated list.
									if ( is_array( $text ) ) {
										$text = implode( ", ", $text );
									}
								}

								?>
								<td><?php echo $text; ?></td>
								<?php
							}
						}
						?>
					</tr>
					<?php
				}
				?>
			</table>
			<?php
			self::$executing_resource_query = false;
		} else {
			// If there are no resources, print that out.
			?>
			<table>
				<tr>
					<td>No Resources Found</td>
				</tr>
			</table>
			<?php
		}

		// Now reset our query object
		wp_reset_postdata();
	}

}

add_action( 'init', array( 'CTLTRES_Archive', 'init' ) );
