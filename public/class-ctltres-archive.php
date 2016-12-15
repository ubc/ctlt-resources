<?php

/**
 * 
 */
class CTLTRES_Archive {

	public static $is_archive = false;
	public static $archive_category = null;

	private static $executing_resource_query = false;
	
	/**
	 * @filter init
	 */
	public static function init() {
		if ( CTLT_Resources::$is_being_activated ) {
			global $wp;

		    $wp->add_query_var('ctltres');
			add_rewrite_rule( '^' . CTLTRES_Resources::$navigation_slug . '/?$', 'index.php?ctltres=all-categories', 'top' );
			add_rewrite_rule( '^' . CTLTRES_Resources::$navigation_slug . '/([^/]+)/?$', 'index.php?ctltres=$matches[1]', 'top' );

			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}

		add_filter( 'query_vars', array( __CLASS__, 'filter_query_vars' ) );
		add_action( 'parse_query', array( __CLASS__, 'parse_query' ) ) ;
		add_filter( 'template_include', array( __CLASS__, 'filter_template' ) ) ;
	}

	public static function filter_query_vars( $vars ) {
		$vars[] = 'ctltres';
		return $vars;
	}

	public static function parse_query() {
		global $wp_query;

		if ( ! self::$is_archive ) {
			self::$archive_category = get_query_var( 'ctltres', null );
			self::$is_archive = ( self::$archive_category != null );

			if ( self::$archive_category == 'all-categories' ) {
				self::$archive_category = null;
			}
		}
	}

	public static function filter_template( $template ) {
		if ( self::$is_archive ) {
			$template = locate_template( 'archive.php' );

			add_filter( 'the_content', array( __CLASS__, 'get_archive_content' ) );
			add_filter( 'get_the_archive_title', array( __CLASS__, 'get_archive_title' ) );

			query_posts( array(
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			) );

			remove_all_actions( '__after_loop' );
		}

		return $template;
	}

	public static function get_archive_content( $content ) {
		if ( in_the_loop() ) {
			$data = $_REQUEST;
			$category = get_term_by( 'slug', self::$archive_category, CTLTRES_Resources::$taxonomy_slug );

			if ( ! empty( $category ) ) {
				$data['category'] = $category->term_id;
			}

			$args = array(
				'filters' => CTLTRES_Resources::parse_filter_arguments( $data ),
			);

			ob_start();
			if ( CTLTRES_Configuration::is_search_enabled() ) {
				self::render_search_form( $args );
			}

			if ( ! empty( $args['filters'] ) ) {
				?>
				<div class="ctltres-clear-filters">
					<a href="<?php echo home_url( CTLTRES_Resources::$navigation_slug ); ?>" class="btn btn-primary button button-primary">Clear Filters</a>
				</div>
				<?php
			}

			self::render_resource_list( $args );

			?>
			<style>
				.post header,
				.post footer,
				.entry-header,
				.entry-footer
					{ display: none; }
			</style>
			<?php

			$content = ob_get_clean();
		}

		return $content;
	}

	public static function get_archive_title( $title ) {
		if ( self::$is_archive ) {
			$title = "Resources";
		}

		return $title;
	}

	public static function render_search_form( $args = array() ) {
		$searchable_fields = CTLTRES_Configuration::get_searchable_fields();
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		?>
		<form action="<?php echo home_url( CTLTRES_Resources::$navigation_slug ); ?>" method="GET">
			<?php
			foreach ( $searchable_fields as $slug ) {
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
						$attribute = $attribute_fields[ $slug ];
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

	public static function render_search_field( $attribute, $value = null ) {
		switch ( $attribute['type'] ) {
			case 'multiselect':
			case 'select':
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
				?>
				<input type="<?php echo $attribute->type; ?>" name="<?php echo $attribute['slug']; ?>" placeholder="Filter <?php echo $attribute['name']; ?>" value="<?php echo $value; ?>"></input>
				<?php
				break;
		}
	}

	public static function render_resource_list( $args = array() ) {
		$filters = isset( $args['filters'] ) ? $args['filters'] : array();

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

		if ( isset( $filters['search'] ) && ! empty( $filters['search'] ) ) {
			$parameters['s'] = $filters['search'];
		}

		if ( isset( $filters['category'] ) && is_numeric( $filters['category'] ) && $filters['category'] > 0 ) {
			$parameters['tax_query'][] = array(
				'taxonomy'         => CTLTRES_Resources::$taxonomy_slug,
				'field'            => 'term_id',
				'terms'            => $filters['category'],
				'include_children' => true,
				'operator'         => 'IN',
			);
		} else {
			$terms = get_terms( array(
				'taxonomy' => CTLTRES_Resources::$taxonomy_slug,
			) );

			foreach ( $terms as $i => $term ) {
				$terms[ $i ] = $term->term_id;
			}

			$parameters['tax_query'][] = array(
				'taxonomy' => CTLTRES_Resources::$taxonomy_slug,
				'terms'    => $terms,
				'operator' => 'IN',
			);
		}

		$show_attributes = ( ! isset( $args['show_attributes'] ) || $args['show_attributes'] );

		unset( $filters['search'] );
		unset( $filters['category'] );

		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		foreach ( $filters as $slug => $value ) {
			$attribute = $attribute_fields[ $slug ];

			$parameters['meta_query'][] = array(
				'key'     => CTLTRES_Resources::get_attribute_metakey( $slug ),
				'value'   => $attribute['type'] == 'multiselect' ? serialize( $value ) : $value,
				'compare' => $attribute['type'] == 'multiselect' ? 'LIKE' : '=',
				'type'    => $attribute['type'] == 'number' ? 'NUMERIC' : 'CHAR',
			);
		}

		$query = new WP_Query( $parameters );

		if ( $query->have_posts() ) {
			self::$executing_resource_query = true;
			?>
			<table>
				<?php
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

				while ( $query->have_posts() ) {
					$query->the_post();

					$attributes = CTLTRES_Resources::get_attributes();
					?>
					<tr>
						<td>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</td>
						<?php
						if ( $show_attributes ) {
							foreach ( CTLTRES_Configuration::get_visible_fields() as $slug ) {
								if ( $slug == 'category' ) {
									$term_id = CTLTRES_Resources::get_category();
									$text = get_term( $term_id, CTLTRES_Resources::$taxonomy_slug )->name;
								} elseif ( $slug == 'date' ) {
									$text = get_the_modified_date();
								} else {
									$text = $attributes[ $slug ];

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
			?>
			<table>
				<tr>
					<td>No Resources Found</td>
				</tr>
			</table>
			<?php
		}

		wp_reset_postdata();
	}

}

add_action( 'init', array( 'CTLTRES_Archive', 'init' ) );
