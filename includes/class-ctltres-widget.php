<?php

class CTLTRES_Widget extends WP_Widget {
	
	private static $slug = 'ctltres_widget';

	public static function init() {
		register_widget( __CLASS__ );
	}

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		parent::__construct( self::$slug, __( "Resources List", 'ctltres' ), array( 
			'classname' => self::$slug,
			'description' => "Renders a list of resources. (From the CTLT Resource plugin)",
		) );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'];
			echo apply_filters( 'widget_title', $instance['title'] );
			echo $args['after_title'];
		}

		$arguments = array(
			'show_attributes' => $instance['show_attributes'],
			'filters' => CTLTRES_Resources::parse_filter_arguments( $instance ),
		);
		
		if ( $instance['search'] ) {
			CTLTRES_Archive::render_search_form( $arguments );
		}

		if ( $instance['limit'] > 0 ) {
			CTLTRES_Archive::render_resource_list( $arguments );
		}

		$see_more_link = get_home_url( null, CTLTRES_Resources::$navigation_slug );
		?>
		<a class="ctltres-list-link" href="<?php echo $see_more_link; ?>">See all resources</a>
		<?php
		
		echo $args['after_widget'];
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
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
		$attribute_fields = CTLTRES_Configuration::get_attribute_fields();

		foreach ( CTLTRES_Configuration::get_searchable_fields() as $slug ) {
			if ( $slug == 'category' ) {
				$this->category_field( $instance );
			} else if ( $slug == 'search' ) {
				// Do nothing
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
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		$result = array(
			'title'           => empty( $new_instance['title'] ) ? '' : strip_tags( $new_instance['title'] ),
			'search'          => isset( $new_instance['search'] ) && $new_instance['search'] == 'on',
			'show_attributes' => isset( $new_instance['show_attributes'] ) && $new_instance['show_attributes'] == 'on',
			'limit'           => intval( $new_instance['limit'] ),
		);

		$result = array_merge( $result, CTLTRES_Resources::parse_filter_arguments( $new_instance ) );

		return $result;
	}

}

add_action( 'widgets_init', array( 'CTLTRES_Widget', 'init' ) );
