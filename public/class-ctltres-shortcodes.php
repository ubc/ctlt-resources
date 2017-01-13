<?php

class CTLTRES_Shortcodes {

	// The shortcode key for the list shortcode
	public static $list_slug = 'cres_list';
	// The shortcode key for the attributes shortcode
	public static $attributes_slug = 'cres_attributes';
	
	public static function init() {
		add_shortcode( self::$list_slug, array( __CLASS__, 'render_shortcode_list' ) );
		add_shortcode( self::$attributes_slug, array( __CLASS__, 'render_shortcode_attributes' ) );
	}

	/**
	 * Renders the list shortcode
	 *
	 * TODO: Expand this to allow filters to be defined
	 */
	public static function render_shortcode_list( $atts = array() ) {
		// Convert search to boolean, if it is defined.
		if ( isset( $atts['search'] ) ) {
			// The point of this is that search="" will result in a false, and not be overridden
			$atts['search'] = ( $atts['search'] == 'on' );
		}

		// Filter the shortcode attributes
		$atts = shortcode_atts( array(
			'title'    => null,
			'category' => '',
			'limit'    => 10,
			'search'   => CTLTRES_Configuration::is_search_enabled(),
		), $atts );

		// Convert the limit to an integer
		$atts['limit'] = intval( $atts['limit'] );

		// If no category is defined, try to populate it automatically.
		if ( empty( $atts['category'] ) ) {
			$atts['category'] = CTLTRES_Resources::get_category();
		}

		if ( is_numeric( $atts['category'] ) ) {
			// If a category is defined, then parse it, and set the see more link appropriately
			$atts['category'] = intval( $atts['category'] );
			$see_more_link = get_term_link( $atts['category'], CTLTRES_Resources::$taxonomy_slug );
		} else {
			// If no category is defined, that means we want to see all categories.
			$atts['category'] = null;
			$see_more_link = get_home_url( null, CTLTRES_Resources::$navigation_slug );
		}

		// If the title is not defined, set one automatically.
		if ( $atts['title'] == null ) {
			if ( empty( $atts['category'] ) ) {
				$atts['title'] = "Other Resources";
			} else {
				// If there is a category, use the category's name.
				$atts['title'] = "Other " . get_term( $atts['category'], CTLTRES_Resources::$taxonomy_slug )->name;
			}
		}

		//Set up the arguments for our resource list
		$arguments = array(
			'limit'   => $atts['limit'],
			'filters' => CTLTRES_Resources::parse_filter_arguments( $atts ),
		);

		ob_start();
		?>
		<div>
			<strong class="ctltres-list-title"><?php echo $atts['title']; ?></strong>
		</div>
		<div class="ctltres-list-content">
			<?php
			// If search is enabled, render it
			if ( $atts['search'] ) {
				CTLTRES_Archive::render_search_form( $arguments );
			}

			// Render the resources list
			CTLTRES_Archive::render_resource_list( $arguments );
			?>
		</div>
		<a class="ctltres-list-link" href="<?php echo $see_more_link; ?>">See all resources</a>
		<?php

		return ob_get_clean();
	}

	/**
	 * Renders the attributes shortcode
	 */
	public static function render_shortcode_attributes( $atts = array() ) {
		// Filter the shortcode attributes
		$atts = shortcode_atts( array(
			'title'   => null,
			'post_id' => get_the_ID(),
		), $atts );

		// Make sure that we have a post to display for.
		if ( $atts['post_id'] > 0 ) {
			// If no title is defined, then fill one in automatically.
			if ( $atts['title'] == null ) {
				$atts['title'] = __( "Attributes", 'ctltres' );
			}

			ob_start();
			// If the title is not empty, render it
			if ( ! empty( $atts['title'] ) ) {
				?>
				<div>
					<strong class="ctltres-attributes-title"><?php echo $atts['title']; ?></strong>
				</div>
				<?php
			}
			
			// Render the attributes for this post (if the post is not a resource, nothing should be displayed)
			CTLTRES_Resources::render_attributes();

			return ob_get_clean();
		}
	}

}

add_action( 'init', array( 'CTLTRES_Shortcodes', 'init' ) );
