<?php

class CTLTRES_Shortcodes {

	public static $list_slug = 'cres_list';
	public static $attributes_slug = 'cres_attributes';
	
	public static function init() {
		add_shortcode( self::$list_slug, array( __CLASS__, 'render_shortcode_list' ) );
		add_shortcode( self::$attributes_slug, array( __CLASS__, 'render_shortcode_attributes' ) );
	}

	public static function render_shortcode_list( $atts = array() ) {
		if ( isset( $atts['search'] ) ) {
			$atts['search'] = ( $atts['search'] == 'on' );
		}

		$atts = shortcode_atts( array(
			'title'    => null,
			'category' => '',
			'limit'    => 10,
			'search'   => CTLTRES_Configuration::is_search_enabled(),
		), $atts );

		$atts['limit'] = intval( $atts['limit'] );

		if ( empty( $atts['category'] ) ) {
			$atts['category'] = CTLTRES_Resources::get_category();
		}

		if ( is_numeric( $atts['category'] ) ) {
			$atts['category'] = intval( $atts['category'] );
			$see_more_link = get_term_link( $atts['category'], CTLTRES_Resources::$taxonomy_slug );
		} else {
			$atts['category'] = null;
			$see_more_link = get_home_url( null, CTLTRES_Resources::$navigation_slug );
		}

		if ( $atts['title'] == null ) {
			if ( empty( $atts['category'] ) ) {
				$atts['title'] = "Other Resources";
			} else {
				$atts['title'] = "Other " . get_term( $atts['category'], CTLTRES_Resources::$taxonomy_slug )->name;
			}
		}

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
			if ( $atts['search'] ) {
				CTLTRES_Archive::render_search_form( $arguments );
			}

			CTLTRES_Archive::render_resource_list( $arguments );
			?>
		</div>
		<a class="ctltres-list-link" href="<?php echo $see_more_link; ?>">See all resources</a>
		<?php

		return ob_get_clean();
	}

	public static function render_shortcode_attributes( $atts = array() ) {
		$atts = shortcode_atts( array(
			'title'   => null,
			'post_id' => get_the_ID(),
		), $atts );

		if ( $atts['post_id'] > 0 ) {
			if ( $atts['title'] == null ) {
				$atts['title'] = __( "Attributes", 'ctltres' );
			}

			ob_start();
			if ( ! empty( $atts['title'] ) ) {
				?>
				<div>
					<strong class="ctltres-attributes-title"><?php echo $atts['title']; ?></strong>
				</div>
				<?php
			}
			
			CTLTRES_Resources::render_attributes();

			return ob_get_clean();
		}
	}

}

add_action( 'init', array( 'CTLTRES_Shortcodes', 'init' ) );
