<?php

/**
 * Registers the plugin's widgets.
 *
 * @since 1.0
 */
function cpt_onomies_register_widgets() {
	register_widget( 'WP_Widget_CPTonomy_Tag_Cloud' );
}
add_action( 'widgets_init', 'cpt_onomies_register_widgets' );

/**
 * CPT-onomy tag cloud widget class
 *
 * This widget was created because the plugin cannot hook into the WordPress tag cloud widget without receiving errors.
 * The widget, however, contains the same functionality.
 *
 * This class mimics the WordPress class WP_Widget_Tag_Cloud.
 *
 * @since 1.0
 */
class WP_Widget_CPTonomy_Tag_Cloud extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'description' => sprintf( __( 'If you are using a custom post type as a taxonomy, a.k.a %s, this will show your most used tags in cloud format.' ), 'cpt-onomies' ), '"CPT-onomy"' );
		parent::__construct( 'cpt_onomy_tag_cloud', sprintf( __( '%s Tag Cloud', 'cpt-onomies' ), 'CPT-onomy' ), $widget_ops );
	}

	/**
	 * This function creates and prints the widget's HTML for the front-end.
	 *
	 * As of 1.1, you are allowed to select whether you want the
	 * term to link to the archive page or the actual post.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager, $cpt_onomy
	 * @param   array $args - arguments to customize the widget
	 * @param   array $instance - the widget's saved information
	 */
	function widget( $args, $instance ) {
		global $cpt_onomies_manager, $cpt_onomy;

		if ( isset( $instance['taxonomy'] ) ) {

			$current_taxonomy = $instance['taxonomy'];

			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $current_taxonomy ) ) {

				// Get tag cloud.
				$tag_cloud = $cpt_onomy->wp_tag_cloud( apply_filters( 'widget_tag_cloud_args', array(
					'taxonomy' => $current_taxonomy,
					'echo' => false,
					'link' => ( isset( $instance['term_link'] ) && 'cpt_post' == $instance['term_link'] ) ? 'cpt_post' : 'view',
					)
				));

				// If empty, and they dont' want to show if empty, then don't show.
				if ( $instance['show_if_empty'] || ( ! $instance['show_if_empty'] && ! empty( $tag_cloud ) ) ) {

					if ( ! empty( $instance['title'] ) ) {
						$title = $instance['title'];
					} else {
						$tax = get_taxonomy( $current_taxonomy );
						$title = $tax->labels->name;
					}

					$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

					echo $args['before_widget'];

					if ( $title ) {
						echo $args['before_title'] . $title . $args['after_title'];
					}

					echo '<div class="tagcloud">' . $tag_cloud . "</div>\n";

					echo $args['after_widget'];

				}
			}
		}
	}

	/**
	 * This function updates the widget's settings.
	 *
	 * @since   1.0
	 * @param   array $new_instance - new settings to overwrite old settings.
	 * @param   array $old_instance - old settings.
	 * @return  array - the updated instance.
	 */
	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags( stripslashes( $new_instance['title'] ) );
		$instance['taxonomy'] = stripslashes( $new_instance['taxonomy'] );
		$instance['show_if_empty'] = stripslashes( $new_instance['show_if_empty'] );
		$instance['term_link'] = stripslashes( $new_instance['term_link'] );
		return $instance;
	}

	/**
	 * This function prints the widget's form in the admin.
	 *
	 * As of 1.1, you are allowed to select whether you want the
	 * term to link to the archive page or the actual post.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager
	 * @param   $instance - widget settings
	 */
	function form( $instance ) {
		global $cpt_onomies_manager;

		$defaults = array(
			'show_if_empty' => 1,
			'term_link'     => 'view',
		);
		$instance = wp_parse_args( $instance, $defaults );
		$current_taxonomy = ( ! empty( $instance['taxonomy'] ) && $cpt_onomies_manager->is_registered_cpt_onomy( $instance['taxonomy'] ) ) ? $instance['taxonomy'] : null;

		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'cpt-onomies' ) ?></label>
		<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php if ( isset( $instance['title'] ) ) { echo esc_attr( $instance['title'] ); } ?>" /></p>

		<p><label for="<?php echo $this->get_field_id( 'taxonomy' ); ?>"><?php _e( 'Taxonomy:', 'cpt-onomies' ) ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'taxonomy' ); ?>" name="<?php echo $this->get_field_name( 'taxonomy' ); ?>">
			<?php

			foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy => $tax ) :

				if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) || empty( $tax->labels->name ) ) {
					continue;
				}

				?>
				<option value="<?php echo esc_attr( $taxonomy ); ?>" <?php selected( $taxonomy, $current_taxonomy ); ?>><?php echo $tax->labels->name; ?></option>
				<?php

			endforeach;

			?>
		</select></p>
		<p><label for="<?php echo $this->get_field_id( 'show_if_empty' ); ?>"><?php _e( 'Show tag cloud if empty:', 'cpt-onomies' ) ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'show_if_empty' ); ?>" name="<?php echo $this->get_field_name( 'show_if_empty' ); ?>">
			<option value="1" <?php selected( $instance['show_if_empty'], 1 ); ?>><?php _e( 'Yes', 'cpt-onomies' ); ?></option>
			<option value="0" <?php selected( $instance['show_if_empty'], 0 ); ?>><?php _e( 'No', 'cpt-onomies' ); ?></option>
		</select></p>
		<p><label for="<?php echo $this->get_field_id( 'term_link' ); ?>"><?php _e( 'The term links to:', 'cpt-onomies' ) ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'term_link' ); ?>" name="<?php echo $this->get_field_name( 'term_link' ); ?>">
			<option value="view" <?php selected( $instance['term_link'], 'view' ); ?>><?php _e( 'Term archive page', 'cpt-onomies' ); ?></option>
			<option value="cpt_post" <?php selected( $instance['term_link'], 'cpt_post' ); ?>><?php printf( __( '%s post page', 'cpt-onomies' ), 'CPT' ); ?></option>
		</select></p>
		<?php

	}

}
