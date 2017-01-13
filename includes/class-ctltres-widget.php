<?php

/**
 * This class handles the custom resources widget.
 * The widget displays a list of resources
 */
class CTLTRES_Widget extends WP_Widget {
	
	// A slug for the widget
	private static $slug = 'ctltres_widget';

	public static function init() {
		// Register the widget
		register_widget( __CLASS__ );
	}

	/**
	 * Define the attributes for the widget
	 */
	public function __construct() {
		parent::__construct( self::$slug, __( "Resources List", 'ctltres' ), array( 
			'classname' => self::$slug,
			'description' => "Renders a list of resources. (From the CTLT Resource plugin)",
		) );
	}

	/**
	 * Renders the content of the widget
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'];
			echo apply_filters( 'widget_title', $instance['title'] );
			echo $args['after_title'];
		}

		// Define the filters for this widget.
		// The widget options may have defined some filters, so those need to be parsed.
		$arguments = array(
			'show_attributes' => $instance['show_attributes'],
			'filters' => CTLTRES_Resources::parse_filter_arguments( $instance ),
		);
		
		// If search is enabled, render it.
		if ( $instance['search'] ) {
			CTLTRES_Archive::render_search_form( $arguments );
		}

		// If this widget is configured to show at least 1 resource, then render them.
		if ( $instance['limit'] > 0 ) {
			CTLTRES_Archive::render_resource_list( $arguments );
		}

		// Include a link to see the full list of resources.
		$see_more_link = get_home_url( null, CTLTRES_Resources::$navigation_slug );
		?>
		<a class="ctltres-list-link" href="<?php echo $see_more_link; ?>">See all resources</a>
		<?php
		
		echo $args['after_widget'];
	}

	/**
	 * Renders the widget's options form on the admin page.
	 */
	public function form( $instance ) {
		// Basic options
		$this->text_field( 'Title', 'title', __( "Resources", 'ctltres' ), $instance );
		$this->text_field( 'Limit', 'limit', 5, $instance, 'number' );
		?>
		<p>
			<?php
			$this->check_field( 'Enable Search', 'search', false, $instance );
			$this->check_field( 'Show Attributes', 'show_attributes', false, $instance );
			?>
		</p>

		<strong>Filters</strong>
		<?php
		// Create the attribute filter options.
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		foreach ( CTLTRES_Configuration::get_searchable_fields() as $slug ) {
			if ( $slug == 'category' ) {
				$this->category_field( $instance );
			} else if ( $slug == 'search' ) {
				// Do nothing
				// We do not allow a default text filter.
				// TODO: Maybe we should?
			} else {
				$attribute_field = $attribute_fields[ $slug ];

				switch ( $attribute_field['type'] ) {
					case 'multiselect':
					case 'select':
						$options = array_merge( array( "Any" ), $attribute_field['options'] );
						$this->select_field( $attribute_field['name'], $slug, $options, $instance );
						break;
					case 'number':
						$this->text_field( $attribute_field['name'], $slug, null, $instance, 'number' );
						break;
					default:
						$this->text_field( $attribute_field['name'], $slug, null, $instance );
						break;
				}
			}
		}
	}

	/**
	 * Renders an admin field where you can select a resource category.
	 */
	private function category_field( $instance ) {
		$title = "Category";
		$slug = 'category';

		$id = esc_attr( $this->get_field_id( $slug ) );
		$name = esc_attr( $this->get_field_name( $slug ) );
		$value = isset( $instance[ $slug ] ) && is_numeric( $instance[ $slug ] ) ? $instance[ $slug ] : -1;

		?>
		<p>
			<label for="<?php echo $id; ?>"><?php _e( $title . ':', 'ctltres' ); ?></label>
			<?php
			wp_dropdown_categories( array(
				'taxonomy'         => CTLTRES_Resources::$taxonomy_slug,
				'show_option_none' => "Any",
				'class'            => 'widefat',
				'id'               => $id,
				'name'             => $name,
				'hierarchical'     => 1,
				'value_field'      => 'term_id',
				'selected'         => $value,
			) );
			?>
		</p>
		<?php
	}

	/**
	 * Renders an admin field where you can input text.
	 */
	private function text_field( $title, $slug, $default, $instance, $type = 'text' ) {
		$id = esc_attr( $this->get_field_id( $slug ) );
		$name = esc_attr( $this->get_field_name( $slug ) );
		$value = isset( $instance[ $slug ] ) ? $instance[ $slug ] : $default;

		?>
		<p>
			<label for="<?php echo $id; ?>"><?php _e( $title . ':', 'ctltres' ); ?></label>
			<input class="widefat" id="<?php echo $id; ?>" name="<?php echo $name; ?>" type="<?php echo $type; ?>" value="<?php echo esc_attr( $value ); ?>">
		</p>
		<?php
	}

	/**
	 * Renders an admin field where you can select from a set of options
	 */
	private function select_field( $title, $slug, $options, $instance ) {
		$id = esc_attr( $this->get_field_id( $slug ) );
		$name = esc_attr( $this->get_field_name( $slug ) );
		$value = isset( $instance[ $slug ] ) ? $instance[ $slug ] : null;

		?>
		<p>
			<label for="<?php echo $id; ?>"><?php _e( $title . ':', 'ctltres' ); ?></label>
			<select id="<?php echo $id; ?>" class="widefat" name="<?php echo $name; ?>">
				<?php
				foreach ( $options as $option ) {
					?>
					<option value="<?php echo $option; ?>" <?php selected( $option, $value ); ?>><?php echo $option; ?></option>
					<?php
				}
				?>
			</select>
		</p>
		<?php
	}

	/**
	 * Renders an admin field where you can check a box.
	 */
	private function check_field( $title, $slug, $default_on, $instance ) {
		$id = esc_attr( $this->get_field_id( $slug ) );
		$name = esc_attr( $this->get_field_name( $slug ) );

		if ( isset( $instance[ $slug ] ) ) {
			$value = $instance[ $slug ];
			$value = ( $default_on ? $value != 'off' : $value == 'on' );
		} else {
			$value = $default_on;
		}

		?>
		<input id="<?php echo $id; ?>" name="<?php echo $name; ?>" type="checkbox" value="on" <?php checked( $value ); ?>>
		<label for="<?php echo $id ?>"><?php _e( $title, 'ctltres' ); ?></label>
		<br>
		<?php 
	}

	/**
	 * Validate and save the widget options.
	 */
	public function update( $new_instance, $old_instance ) {
		// Validate the basic options.
		$result = array(
			'title'           => empty( $new_instance['title'] ) ? '' : strip_tags( $new_instance['title'] ),
			'search'          => isset( $new_instance['search'] ) && $new_instance['search'] == 'on',
			'show_attributes' => isset( $new_instance['show_attributes'] ) && $new_instance['show_attributes'] == 'on',
			'limit'           => intval( $new_instance['limit'] ),
		);

		// Parse the attribute fields.
		$result = array_merge( $result, CTLTRES_Resources::parse_filter_arguments( $new_instance ) );

		return $result;
	}

}

add_action( 'widgets_init', array( 'CTLTRES_Widget', 'init' ) );
