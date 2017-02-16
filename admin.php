<?php

// Instantiate the class.
global $cpt_onomies_admin;
$cpt_onomies_admin = new CPT_ONOMIES_ADMIN();

/**
 * Holds the functions needed for the admin.
 *
 * @since 1.0
 */
class CPT_ONOMIES_ADMIN {

	/**
	 * Stores data received from the
	 * 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids'
	 * and 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids'
	 * filters that are invoked when printing the CPT-onomy meta boxes.
	 */
	public $assigning_terms_include_term_ids = array();
	public $assigning_terms_exclude_term_ids = array();

	/**
	 * Adds WordPress hooks (actions and filters).
	 *
	 * This function is only run in the admin.
	 *
	 * @since 1.0
	 */
	public function __construct() {

		// We're only running these suckers in the admin.
		if ( is_admin() ) {

			// If the user visits edit-tags.php to manage the terms, we set them straight.
			add_action( 'admin_init', array( $this, 'deny_edit_tags' ) );

			// Register all of the admin scripts.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_register_styles_and_scripts' ), 10 );

			// Returns wp_object_terms().
			add_action( 'wp_ajax_custom_post_type_onomy_get_wp_object_terms', array( $this, 'ajax_get_wp_object_terms' ) );

			// Checks to see if term exists.
			add_action( 'wp_ajax_custom_post_type_onomy_check_if_term_exists', array( $this, 'ajax_check_if_term_exists' ) );

			// Add CPT-onomy "edit" meta boxes.
			add_action( 'add_meta_boxes', array( $this, 'add_cpt_onomy_meta_boxes' ), 10, 2 );

			// Takes care of autocomplete meta boxes.
			add_action( 'wp_ajax_custom_post_type_onomy_meta_box_autocomplete_callback', array( $this, 'ajax_meta_box_autocomplete_callback' ) );

			// Runs when any post is saved.
			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

			// Runs when any post is deleted.
			add_action( 'delete_post', array( $this, 'delete_post' ) );

			// Bulk/quick edit.
			add_action( 'bulk_edit_custom_box', array( $this, 'bulk_quick_edit_custom_box' ), 100, 2 );
			add_action( 'quick_edit_custom_box', array( $this, 'bulk_quick_edit_custom_box' ), 100, 2 );
			add_action( 'wp_ajax_custom_post_type_onomy_get_cpt_onomy_terms_include_term_ids', array( $this, 'ajax_get_cpt_onomy_terms_include_term_ids' ) );
			add_action( 'wp_ajax_custom_post_type_onomy_get_cpt_onomy_terms_exclude_term_ids', array( $this, 'ajax_get_cpt_onomy_terms_exclude_term_ids' ) );
			add_action( 'wp_ajax_custom_post_type_onomy_populate_bulk_quick_edit', array( $this, 'ajax_get_wp_object_terms' ) );
			add_action( 'wp_ajax_custom_post_type_onomy_save_bulk_edit', array( $this, 'ajax_save_bulk_edit' ) );
			add_action( 'wp_ajax_custom_post_type_onomy_quick_edit_populate_custom_columns', array( $this, 'ajax_quick_edit_populate_custom_columns' ) );

			// Add column filters.
			add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );

			// Add custom admin columns.
			add_filter( 'manage_pages_columns', array( $this, 'add_cpt_onomy_admin_column' ), 100, 1 );
			add_filter( 'manage_posts_columns', array( $this, 'add_cpt_onomy_admin_column' ), 100, 2 );

			// Define sortable columns.
			add_action( 'load-edit.php', array( $this, 'add_cpt_onomy_admin_sortable_columns_filter' ) );

		}
	}

	/**
	 * The usual admin page for managing terms is edit-tags.php but we do not
	 * want users to access this page. $cpt_onomies_manager->user_has_term_capabilities()
	 * removes the ability access this page and throws up a 'Cheatin' uh?' message
	 * but this function replaces that message with some helpful text on where to go
	 * to edit the terms.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager, $pagenow
	 */
	public function deny_edit_tags() {
		global $cpt_onomies_manager, $pagenow;

		// Only on the edit tags page.
		if ( 'edit-tags.php' == $pagenow ) {

			// Make sure we have a taxonomy.
			if ( ! empty( $_REQUEST['taxonomy'] )
			    && $cpt_onomies_manager->is_registered_cpt_onomy( $_REQUEST['taxonomy'] ) ) {

				// Get the taxonomy and post type info.
				$taxonomy         = $_REQUEST['taxonomy'];
				$tax              = get_taxonomy( $taxonomy );
				$custom_post_type = get_post_type_object( $taxonomy );

				/*
				 * If the user is capable of editing the post to begin with.
				 *
				 * Otherwise, don't get their hopes up.
				 */
				if ( current_user_can( $custom_post_type->cap->edit_posts ) ) {
					wp_die( sprintf( __( 'Since "%1$s" is a registered %2$s, you manage it\'s "terms" by managing the posts created under the custom post type "%3$s". So go ahead... %4$smanage the posts%5$s.', 'cpt-onomies' ), $tax->labels->name, 'CPT-onomy', $tax->labels->name, '<a href="' . add_query_arg( array( 'post_type' => $taxonomy ), admin_url( 'edit.php' ) ) . '">', '</a>' ) );
				} else {
					wp_die( sprintf( __( 'Since "%1$s" is a registered %2$s, you manage it\'s "terms" by managing the posts created under the custom post type "%3$s". Unfortunately, you don\'t have permission to edit these posts. Sorry. If this is a mistake, contact your administrator.', 'cpt-onomies' ), $tax->labels->name, 'CPT-onomy', $tax->labels->name ) . ' <a href="' . admin_url() . '">' . __( 'Go to the dashboard', 'cpt-onomies' ) . '</a>' );
				}
			}
		}
	}

	/**
	 * Bringing all of the styles and scripts into one function helps to
	 * keep everything more organized and allows for easier sharing.
	 *
	 * @since   1.1
	 * @uses    $current_screen
	 * @param   string $page - the current page
	 */
	public function admin_register_styles_and_scripts( $page ) {
		global $current_screen;

		// Several pages in the admin need this script.
		wp_register_script( 'jquery-form-validation', plugins_url( 'assets/js/jquery.validate.min.js', __FILE__ ), array( 'jquery' ), null, true );

		// Enqueue scripts depending on page.
		switch ( $page ) {

			case 'edit.php':
				wp_enqueue_script( 'custom-post-type-onomies-admin-edit', plugins_url( 'assets/js/admin-edit.min.js', __FILE__ ), array( 'jquery', 'inline-edit-post' ), null, true );
				break;

			case 'post.php':
			case 'post-new.php':
				wp_enqueue_style( 'custom-post-type-onomies-admin-post', plugins_url( 'assets/css/admin-post.min.css', __FILE__ ), false, null );
				wp_enqueue_script( 'custom-post-type-onomies-admin-post', plugins_url( 'assets/js/admin-post.min.js', __FILE__ ), array( 'jquery', 'post', 'jquery-ui-autocomplete' ), null, true );

				// Our localized info.
				$cpt_onomies_admin_post_data = array();
				$cpt_onomies_admin_post_translation = array(
					'term_does_not_exist'   => sprintf( __( 'The term you are trying to add does not exist. %s terms, a.k.a posts, must already exist to be available for selection.', 'cpt-onomies' ), 'CPT-onomy' ),
					'add_a_term'            => __( 'Add a term', 'cpt-onomies' ),
					'add_the_term'          => __( 'Add the term', 'cpt-onomies' ),
					'no_self_relationship'  => __( 'Kind of silly to create a relationship between a post and itself, eh?', 'cpt-onomies' ),
					'relationship_already_exists' => __( 'This relationship already exists.', 'cpt-onomies' ),
					'close'                 => __( 'Close', 'cpt-onomies' ),
				);

				/*
				 * We need to know if the user has permission to edit specific
				 * taxonomies AND we'll get the label name while we're at it.
				 */
				foreach ( get_object_taxonomies( $current_screen->post_type, 'objects' ) as $taxonomy => $tax ) {

					// Get the permission.
					$cpt_onomies_admin_post_data['can_assign_terms'][ $taxonomy ] = current_user_can( $tax->cap->assign_terms );

					// Get the label name.
					$cpt_onomies_admin_post_translation['no_terms_selected'][ $taxonomy ] = sprintf( __( 'There are no %s selected.', 'cpt-onomies' ), strtolower( $tax->labels->name ) );

				}

				// Add our info to the scripts.
				wp_localize_script( 'custom-post-type-onomies-admin-post', 'cpt_onomies_admin_post_data', $cpt_onomies_admin_post_data );
				wp_localize_script( 'custom-post-type-onomies-admin-post', 'cpt_onomies_admin_post_L10n', $cpt_onomies_admin_post_translation );

				break;

		}
	}

	/**
	 * Allows ajax to invoke the get_cpt_onomy_terms_include_term_ids() function.
	 *
	 * Prints an array of term ids.
	 *
	 * @since 1.3
	 */
	public function ajax_get_cpt_onomy_terms_include_term_ids() {

		// Get the taxonomy and post type info.
		$taxonomy = ( isset( $_POST['custom_post_type_onomies_taxonomy'] ) && ! empty( $_POST['custom_post_type_onomies_taxonomy'] ) ) ? $_POST['custom_post_type_onomies_taxonomy'] : array();
		$post_type = ( isset( $_POST['custom_post_type_onomies_post_type'] ) && ! empty( $_POST['custom_post_type_onomies_post_type'] ) ) ? $_POST['custom_post_type_onomies_post_type'] : null;
		$post_id = ( isset( $_POST['custom_post_type_onomies_post_id'] ) && ! empty( $_POST['custom_post_type_onomies_post_id'] ) ) ? $_POST['custom_post_type_onomies_post_id'] : 0;
		$include_term_ids = array();

		// If we have a taxonomy...
		if ( ! empty( $taxonomy ) ) {

			// Get the include IDs.
			$taxonomy_include_term_ids = $this->get_cpt_onomy_terms_include_term_ids( $taxonomy, $post_type, $post_id );
			if ( ! empty( $taxonomy_include_term_ids ) ) {
				$include_term_ids = array_merge( $include_term_ids, $taxonomy_include_term_ids );
			}
		}

		// Print terms.
		echo json_encode( $include_term_ids );

		die();
	}

	/**
	 * The 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids' filter
	 * allows you to designate that you only want to include/print specific terms, and therefore
	 * only want those specific terms to be able to be assigned, in the admin by returning their
	 * term IDs. This function invokes that filter when needed, cleans up the data, stores the
	 * data in a global class variable and returns the data.
	 *
	 * The data returned to the filter can be an array, space-separated or comma separated string.
	 * The filter passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 *
	 * @since   1.3
	 * @param   string $taxonomy - the name of the CPT-onomy
	 * @param   string $post_type - the name of the post type the CPT-onomy is being assigned to
	 * @param   int $post_id - the ID for the post the CPT-onomy is being assigned to
	 * @return  array - the ids for the included cpt_onomy terms
	 * @filters 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids' - $taxonomy, $post_type, $post_id
	 */
	public function get_cpt_onomy_terms_include_term_ids( $taxonomy = null, $post_type = null, $post_id = 0 ) {

		$include_term_ids = apply_filters( 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids', array(), $taxonomy, $post_type, $post_id );

		// Make sure its an array.
		if ( ! is_array( $include_term_ids ) ) {
			$include_term_ids = str_replace( ' ', ',', str_replace( ', ', ',', $include_term_ids ) );
			$include_term_ids = explode( ',', $include_term_ids );
		}

		// Make sure the 'include' does not include the current post ID.
		if ( in_array( $post_id, $include_term_ids ) ) {
			foreach ( $include_term_ids as $term_id_index => $term_id ) {
				if ( $post_id == $term_id ) {
					unset( $include_term_ids[ $term_id_index ] );
				}
			}
		}

		// Store and return the include term data.
		return $this->assigning_terms_include_term_ids[ $taxonomy ][ $post_type ][ $post_id ] = array_unique( $include_term_ids );
	}

	/**
	 * Allows ajax to invoke the get_cpt_onomy_terms_exclude_term_ids() function.
	 *
	 * Prints an array of term ids.
	 *
	 * @since 1.2.1
	 */
	public function ajax_get_cpt_onomy_terms_exclude_term_ids() {

		// Get taxonomy and post type info
		$taxonomies = ( isset( $_POST['custom_post_type_onomies_taxonomies'] ) && ! empty( $_POST['custom_post_type_onomies_taxonomies'] ) ) ? $_POST['custom_post_type_onomies_taxonomies'] : array();
		$post_type = ( isset( $_POST['custom_post_type_onomies_post_type'] ) && ! empty( $_POST['custom_post_type_onomies_post_type'] ) ) ? $_POST['custom_post_type_onomies_post_type'] : null;
		$post_id = ( isset( $_POST['custom_post_type_onomies_post_id'] ) && ! empty( $_POST['custom_post_type_onomies_post_id'] ) ) ? $_POST['custom_post_type_onomies_post_id'] : 0;
		$exclude_term_ids = array();

		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_exclude_term_ids = $this->get_cpt_onomy_terms_exclude_term_ids( $taxonomy, $post_type, $post_id );
			if ( ! empty( $taxonomy_exclude_term_ids ) ) {
				$exclude_term_ids = array_merge( $exclude_term_ids, $taxonomy_exclude_term_ids );
			}
		}

		// Print terms
		echo json_encode( $exclude_term_ids );

		die();
	}

	/**
	 * The 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids' filter
	 * allows you to exclude specific terms from being printed, and therefore assigned,
	 * in the admin by returning their term IDs. This function invokes that filter when
	 * needed, cleans up the data, stores the data in a global class variable and returns the data.
	 *
	 * The data returned to the filter can be an array, space-separated or comma separated string.
	 * The filter passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 *
	 * @since   1.2.1
	 * @param   string $taxonomy - the name of the CPT-onomy
	 * @param   string $post_type - the name of the post type the CPT-onomy is being assigned to
	 * @param   int $post_id - the ID for the post the CPT-onomy is being assigned to
	 * @return  array - the ids for the excluded cpt_onomy terms
	 * @filters 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids' - $taxonomy, $post_type, $post_id
	 */
	public function get_cpt_onomy_terms_exclude_term_ids( $taxonomy = null, $post_type = null, $post_id = 0 ) {

		$exclude_term_ids = apply_filters( 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids', array(), $taxonomy, $post_type, $post_id );

		// Make sure its an array.
		if ( ! is_array( $exclude_term_ids ) ) {
			$exclude_term_ids = str_replace( ' ', ',', str_replace( ', ', ',', $exclude_term_ids ) );
			$exclude_term_ids = explode( ',', $exclude_term_ids );
		}

		// Make sure the 'excludes' includes the current post ID.
		if ( ! in_array( $post_id, $exclude_term_ids ) ) {
			$exclude_term_ids[] = $post_id;
		}

		// Store and return the excluded term data.
		return $this->assigning_terms_exclude_term_ids[ $taxonomy ][ $post_type ][ $post_id ] = array_unique( $exclude_term_ids );
	}

	/**
	 * Used in collaboration with the CPT-onomy autocomplete term selection,
	 * if the CPT-onomy is hierarchical, in order to display a term's parents
	 * in the autocomplete dropdown and in the selected terms' checklist.
	 *
	 * @since   1.1
	 * @param   int $term_parent - the term's parent term id
	 * @return  string the complete parent title
	 */
	public function build_term_parent_title_with_csv( $term_parent ) {

		if ( $term_parent > 0 ) {

			$post_parent_id = $term_parent;
			$term_parent = '';

			do {

				$post_parent = get_post( $post_parent_id );
				$post_parent_id = $post_parent->post_parent;

				if ( $term_parent ) {
					$term_parent .= ',';
				}

				$term_parent .= $post_parent->post_title;

			} while ( $post_parent_id > 0 );

			return $term_parent;
		} else {
			return null;
		}
	}

	/**
	 * Returns an object's term info to an AJAX call.
	 *
	 * Allows you to designate if you want the parent titles
	 * instead of term id. This comes in handy with the autcomplete
	 * term selection for hierarchical CPT-onomies so we can show
	 * the parent title along with the term name.
	 *
	 * This function replaced populate_bulk_quick_edit().
	 *
	 * @since 1.1
	 */
	public function ajax_get_wp_object_terms() {

		$post_ids = ( isset( $_POST['custom_post_type_onomies_post_ids'] ) && ! empty( $_POST['custom_post_type_onomies_post_ids'] ) ) ? $_POST['custom_post_type_onomies_post_ids'] : array();
		$taxonomies = ( isset( $_POST['custom_post_type_onomies_taxonomies'] ) && ! empty( $_POST['custom_post_type_onomies_taxonomies'] ) ) ? $_POST['custom_post_type_onomies_taxonomies'] : array();
		$get_parent_title = ( isset( $_POST['custom_post_type_onomies_get_parent_title'] ) && ! empty( $_POST['custom_post_type_onomies_get_parent_title'] ) ) ? true : false;
		$terms_fields = ( isset( $_POST['custom_post_type_onomies_wp_get_object_terms_fields'] ) && ! empty( $_POST['custom_post_type_onomies_wp_get_object_terms_fields'] ) && in_array( $_POST['custom_post_type_onomies_wp_get_object_terms_fields'], array( 'ids' ) ) ) ? $_POST['custom_post_type_onomies_wp_get_object_terms_fields'] : null;

		if ( ! empty( $post_ids ) && ! empty( $taxonomies ) ) {

			if ( ! is_array( $post_ids ) ) {
				$post_ids = array( $post_ids );
			}

			if ( ! is_array( $taxonomies ) ) {
				$taxonomies = array( $taxonomies );
			}

			// Set any arguments.
			$args = array();

			// If we want specific fields.
			if ( $terms_fields ) {
				$args = array( 'fields' => $terms_fields );
			}

			// Get terms.
			$terms = wp_get_object_terms( $post_ids, $taxonomies, $args );

			// Get parent title, if desired AND if post type is hierarchical.
			if ( $get_parent_title ) {
				foreach ( $terms as $term_index => $term ) {
					$terms[ $term_index ]->parent = ( is_post_type_hierarchical( $term->taxonomy ) ) ? $this->build_term_parent_title_with_csv( $term->parent ) : '';
				}
			}

			// Print terms.
			echo json_encode( $terms );

		}

		die();
	}

	/**
	 * Allows us to check if a term exists via AJAX.
	 * Returns an object of term info.
	 *
	 * Also allows you to designate that you want the parent's
	 * name instead of the parent's term id.
	 *
	 * @since   1.1
	 * @uses    $cpt_onomy
	 */
	public function ajax_check_if_term_exists() {
		global $cpt_onomy;

		$term = ( isset( $_POST['custom_post_type_onomies_term'] ) && ! empty( $_POST['custom_post_type_onomies_term'] ) ) ? $_POST['custom_post_type_onomies_term'] : '';
		$term_id = ( isset( $_POST['custom_post_type_onomies_term_id'] ) && ! empty( $_POST['custom_post_type_onomies_term_id'] ) && (int) $_POST['custom_post_type_onomies_term_id'] > 0 ) ? (int) $_POST['custom_post_type_onomies_term_id'] : 0;
		$taxonomy = ( isset( $_POST['custom_post_type_onomies_taxonomy'] ) && ! empty( $_POST['custom_post_type_onomies_taxonomy'] ) ) ? $_POST['custom_post_type_onomies_taxonomy'] : '';
		$get_parent_title = ( isset( $_POST['custom_post_type_onomies_get_parent_title'] ) && ! empty( $_POST['custom_post_type_onomies_get_parent_title'] ) ) ? true : false;

		if ( ( $term || $term_id > 0 ) && $taxonomy ) {
			$term_exists = false;

			if ( $term_id > 0 ) {
				$term_exists = $cpt_onomy->term_exists( $term_id, $taxonomy );
			}

			if ( ! $term_exists && $term ) {
				$term_exists = $cpt_onomy->term_exists( $term, $taxonomy );
			}

			if ( ! $term_exists ) {
				echo json_encode( array() );
			} elseif ( is_numeric( $term_exists ) ) {
				echo json_encode( (object) array( 'term_id' => $term_exists ) );
			} elseif ( is_object( $term_exists ) || is_array( $term_exists ) ) {

				// Get parent title, if desired.
				if ( $get_parent_title ) {
					$term_exists->parent = $this->build_term_parent_title_with_csv( $term_exists->parent );
				}

				echo json_encode( $term_exists );

			} else {
				echo json_encode( array() );
			}
		} else {
			echo json_encode( array() );
		}

		die();
	}

	/**
	 * The jQuery field autocomplete callback
	 *
	 * This function returns results for the CPT-onomy autocomplete term selection.
	 *
	 * You can designate that you only want to include specific terms in the results by returning
	 * their term IDs using the 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids'
	 * filter which passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 * The "include" filter overwrites the "exclude" filter.
	 *
	 * You can disable specific terms from being listed in the results by returning their
	 * term IDs using the 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids'
	 * filter which passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 * While the "include" filter overwrites the "exclude" filter, if exclude terms are in the
	 * include terms, they will be removed.
	 *
	 * @since   1.1
	 * @uses    $wpdb
	 */
	public function ajax_meta_box_autocomplete_callback() {
		global $wpdb;

		// Get the taxonomy and post type info.
		$taxonomy = ( isset( $_POST['custom_post_type_onomies_taxonomy'] ) && ! empty( $_POST['custom_post_type_onomies_taxonomy'] ) ) ? $_POST['custom_post_type_onomies_taxonomy'] : null;
		$term = ( isset( $_POST['custom_post_type_onomies_term'] ) && ! empty( $_POST['custom_post_type_onomies_term'] ) ) ? $_POST['custom_post_type_onomies_term'] : null;
		$post_type = ( isset( $_POST['custom_post_type_onomies_post_type'] ) && ! empty( $_POST['custom_post_type_onomies_post_type'] ) ) ? $_POST['custom_post_type_onomies_post_type'] : 0;
		$post_id = ( isset( $_POST['custom_post_type_onomies_post_id'] ) && ! empty( $_POST['custom_post_type_onomies_post_id'] ) ) ? $_POST['custom_post_type_onomies_post_id'] : 0;

		if ( $taxonomy && $term ) {

			// Get available terms.
			$available_terms = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title AS label, post_parent AS parent FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_title ASC", $taxonomy ) );
			if ( $available_terms ) {

				/*
				 * Allows you to use the
				 * 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids'
				 * filter to designate that you only want specific terms to be
				 * printed and therefore assigned. The term ids are stored
				 * in an array that is used to customize each printed format.
				 * 'Include' overwrites 'exclude'.
				 */
				$include_term_ids = $this->get_cpt_onomy_terms_include_term_ids( $taxonomy, $post_type, $post_id );

				/*
				 * Allows you to use the
				 * 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids'
				 * filter to exclude specific terms from being printed
				 * and therefore assigned. The term ids are stored in an
				 * array that is used to customize each printed format.
				 * While 'include' overwrites 'exclude', if exclude terms
				 * are in the include array, they will be removed.
				 */
				$exclude_term_ids = $this->get_cpt_onomy_terms_exclude_term_ids( $taxonomy, $post_type, $post_id );

				$results = array();
				foreach ( $available_terms as $this_term ) {

					// Whether or not we want the element displayed.
					$add_term_to_results = true;

					// Test against 'include' and 'exclude'.
					if ( $include_term_ids && ! in_array( $this_term->ID, $include_term_ids ) ) {
						$add_term_to_results = false;
					}

					if ( $exclude_term_ids && in_array( $this_term->ID, $exclude_term_ids ) ) {
						$add_term_to_results = false;
					}

					// We don't want to display children of terms we filtered out.
					if ( $this_term->parent ) {
						foreach ( get_post_ancestors( $this_term->ID ) as $ancestor ) {
							if ( in_array( $ancestor, $exclude_term_ids ) ) {
								$add_term_to_results = false;
								break;
							}
						}
					}

					if ( $add_term_to_results ) {

						// Go ahead and apply the filter before it's "searched".
						$this_term->label = apply_filters( 'the_title', $this_term->label, $this_term->ID );

						/*
						 * We don't want to display the current post.
						 * If a match was found, add it to the suggestions.
						 */
						if ( stripos( $this_term->label, $term ) !== false ) {

							$results[] = array(
								'value'     => $this_term->ID,
								'label'     => $this_term->label,
								'parent'    => ( is_post_type_hierarchical( $taxonomy ) ) ? $this->build_term_parent_title_with_csv( $this_term->parent ) : '',
							);

						}
					}
				}

				// Print terms.
				echo json_encode( $results );

			}
		}

		die();
	}

	/**
	 * If a CPT-onomy is attached to a post type, the plugin adds a meta box to
	 * the post edit screen so the user can assign/manage the taxonomy's terms.
	 *
	 * You can remove the box by returning false to the
	 * 'custom_post_type_onomies_add_cpt_onomy_admin_meta_box' filter, which passes
	 * two parameters: the $taxonomy and the $post_type.
	 *
	 * This function is invoked by the action 'add_meta_boxes'.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager
	 * @param   string $post_type - the current post's post type
	 * @param   object $post - the current post's information
	 * @filters 'custom_post_type_onomies_add_cpt_onomy_admin_meta_box' - $taxonomy, $post_type
	 */
	public function add_cpt_onomy_meta_boxes( $post_type, $post ) {
		global $cpt_onomies_manager;

		// Loop through all the taxonomies tied to this post type.
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy => $tax ) {

			// Make sure its a registered CPT-onomy.
			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {

				/*
				 * This filter allows you to remove the meta box by returning false.
				 *
				 * If 'show_ui' is false, do not add meta box.
				 */
				if ( apply_filters( 'custom_post_type_onomies_add_cpt_onomy_admin_meta_box', ( post_type_exists( $taxonomy ) ? get_post_type_object( $taxonomy )->show_ui : true ), $taxonomy, $post_type ) ) {

					// What's the meta box title? - default is taxonomy label.
					$meta_box_title = isset( $tax->meta_box_title ) && ! empty( $tax->meta_box_title ) ? $tax->meta_box_title : $tax->label;

					// Add the meta box.
					add_meta_box( 'custom-post-type-onomies-' . $taxonomy, apply_filters( 'custom_post_type_onomies_meta_box_title', $meta_box_title, $taxonomy, $post_type ), array( $this, 'print_cpt_onomy_meta_box' ), $post_type, 'side', 'core', array( 'taxonomy' => $taxonomy ) );

				}
			}
		}
	}

	/**
	 * This function is invoked when a CPT-onomy meta box is attached to a post type's edit post screen.
	 * This 'callback' function prints the html for the meta box.
	 *
	 * The meta box consists of a checklist that allows the user to assign/manage the taxonomy's terms.
	 * This function mimics a meta box for an ordinary custom taxonomy.
	 *
	 * Version 1.1 brought support for 'autocomplete' and 'dropdown' selection format,
	 * on top of the already existent 'checklist'.
	 *
	 * CPT-onomies follows default WordPress behavior, providing a checklist for hierarchical
	 * CPT-onomies and the autocomplete box for non-hierarchical CPT-onomies. You can change the
	 * format by hooking into the 'custom_post_type_onomies_meta_box_format' filter, which passes
	 * two parameters: the $taxonomy and the $post_type.
	 *
	 * You can designate that you only want specific terms listed in the results by returning their
	 * term IDs using the 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids'
	 * filter which passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 *
	 * You can disable specific terms from being listed in the results by returning their
	 * term IDs using the 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids'
	 * filter which passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 *
	 * This code mimics the WordPress function post_categories_meta_box().
	 *
	 * This function is invoked by the action 'add_meta_boxes'.
	 *
	 * @since   1.0
	 * @param   object $post - the current post's information
	 * @param   array $box - information about the metabox
	 * @filters 'custom_post_type_onomies_meta_box_format' - $taxonomy, $post_type
	 */
	public function print_cpt_onomy_meta_box( $post, $metabox ) {

		// Add nonce.
		wp_nonce_field( 'assigning_custom_post_type_onomies_taxonomy_relationships', 'custom_post_type_onomies_nonce' );

		// Define variables.
		$post_type = ( isset( $post->post_type ) && ! empty( $post->post_type ) && post_type_exists( $post->post_type ) ) ? $post->post_type : null;
		$taxonomy = ( isset( $metabox['args']['taxonomy'] ) && ! empty( $metabox['args']['taxonomy'] ) && taxonomy_exists( $metabox['args']['taxonomy'] ) ) ? $metabox['args']['taxonomy'] : null;

		if ( $post_type && $taxonomy ) {

			// Get taxonomy info
			$tax = get_taxonomy( $taxonomy );

			// If 'meta_box_format' is not defined, use default WordPress setting
			if ( ! ( $format = ( isset( $tax->meta_box_format ) && ! empty( $tax->meta_box_format ) ) ? $tax->meta_box_format : null ) ) {
				$format = is_post_type_hierarchical( $taxonomy ) ? 'checklist' : 'autocomplete';
			}

			// Allow the user to change the format - 'autocomplete', 'dropdown', 'checklist' - default
			$format = apply_filters( 'custom_post_type_onomies_meta_box_format', $format, $taxonomy, $post_type );

			// Does the user have permission to assign terms?
			$disabled = ! current_user_can( $tax->cap->assign_terms ) ? ' disabled="disabled"' : '';

			/*
			 * Allows you to use the
			 * 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids'
			 * filter to designate that you only want specific terms
			 * to be printed and therefore assigned. The term ids are
			 * stored in an array that is used to customize each printed
			 * format. 'Include' overwrites 'exclude'.
			 */
			$include_term_ids = $this->get_cpt_onomy_terms_include_term_ids( $taxonomy, $post_type, $post->ID );

			/*
			 * Allows you to use the
			 * 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids'
			 * filter to exclude specific terms from being printed
			 * and therefore assigned. The term ids are stored in an
			 * array that is used to customize each printed format.
			 * While 'include' overwrites 'exclude', if exclude terms
			 * are in the include array, they will be removed.
			 */
			$exclude_term_ids = $this->get_cpt_onomy_terms_exclude_term_ids( $taxonomy, $post_type, $post->ID );

			// Add field for testing "editability" when we save the information.
			?>
			<input type="hidden" name="assign_cpt_onomies_<?php echo $taxonomy; ?>_rel" value="1" />
			<?php

			switch ( $format ) {

				case 'autocomplete':

					?>
					<div id="taxonomy-<?php echo $taxonomy; ?>" class="cpt_onomies_tags_div">
						<div class="jaxtag">
							<div class="nojs-tags hide-if-js">
								<p><?php echo $tax->labels->add_or_remove_items; ?></p>
								<textarea name="<?php echo CPT_ONOMIES_POSTMETA_KEY; ?>[<?php echo $taxonomy; ?>]" rows="3" cols="20" class="the-tags" id="tax-input-<?php echo $taxonomy; ?>"<?php echo $disabled; ?>><?php echo get_terms_to_edit( $post->ID, $taxonomy ); ?></textarea>
							</div>
							<?php

							if ( current_user_can( $tax->cap->assign_terms ) ) :

								?>
								<div class="ajaxtag hide-if-no-js">
									<label class="screen-reader-text" for="new-tag-<?php echo $taxonomy; ?>"><?php echo $metabox['title']; ?></label>
									<div class="taghint"><?php echo $tax->labels->add_new_item; ?></div>
									<p>
										<input type="text" id="new-tag-<?php echo $taxonomy; ?>" name="cpt_onomies_new_tag[<?php echo $taxonomy; ?>]" class="cpt_onomies_new_tag form-input-tip" size="16" autocomplete="off" value="" />
										<input type="button" class="button cpt_onomies_tag_add" value="<?php esc_attr_e( 'Add', 'cpt-onomies' ); ?>" tabindex="3" />
									</p>
								</div>
								<?php

							endif;

							?>
						</div>
						<div class="cpt_onomies_tag_checklist<?php if ( ! current_user_can( $tax->cap->assign_terms ) ) { echo ' alone'; } ?>"></div>
					</div>
					<?php

					if ( current_user_can( $tax->cap->assign_terms ) ) :

						?>
						<p class="hide-if-no-js"><a href="#titlediv" class="cpt_onomies_tag_cloud" id="link-<?php echo $taxonomy; ?>"><?php echo $tax->labels->choose_from_most_used; ?></a></p>
						<?php

					endif;

					break;

				case 'dropdown':

					// Get ALL info and then extract IDs because of ID conflict with regular taxonomies.
					$selected_terms = wp_get_object_terms( $post->ID, $taxonomy );

					// We only need the first term for a dropdown.
					$selected_term = $selected_terms ? array_shift( $selected_terms )->term_id : 0;

					/*
					 * Because the dropdown function only has 'exclude', if 'include' is set,
					 * we have to get all of the terms and exclude everything but what's in 'include'
					 */
					$dropdown_exclude_term_ids = array();
					if ( $include_term_ids ) {

						// Get all terms for this taxonomy that are not in 'include'.
						foreach ( get_terms( $taxonomy, array( 'hide_empty' => false, 'fields' => 'ids' ) ) as $term_id ) {
							if ( ! in_array( $term_id, $include_term_ids ) ) {
								$dropdown_exclude_term_ids[] = $term_id;
							}
						}
					}

					// Make sure 'exclude' term ids are included.
					if ( $exclude_term_ids ) {
						$dropdown_exclude_term_ids = array_unique( array_merge( $dropdown_exclude_term_ids, $exclude_term_ids ) );
					}

					$dropdown = wp_dropdown_categories( array(
						'show_option_none'  => sprintf( __( 'No %s are selected', 'cpt-onomies' ), $tax->labels->all_items ),
						'orderby'           => 'name',
						'order'             => 'ASC',
						'show_count'        => false,
						'hide_empty'        => false,
						'exclude'           => $dropdown_exclude_term_ids,
						'echo'              => false,
						'selected'          => $selected_term,
						'hierarchical'      => is_post_type_hierarchical( $taxonomy ),
						'name'              => CPT_ONOMIES_POSTMETA_KEY . '[' . $taxonomy . '][]',
						'id'                => 'taxonomy-' . $taxonomy,
						'class'             => 'category cpt_onomies',
						'taxonomy'          => $taxonomy,
						'hide_if_empty'     => false,
					));

					/*
					 * Need to add disabled to select element
					 * as a backup, this attribute is also checked in admin-post.js
					 */
					if ( $disabled ) {
						$dropdown = preg_replace( '/^\<select/', '<select' . $disabled, $dropdown );
					}

					// Print dropdown.
					echo $dropdown;

					break;

				case 'checklist':
				default:

					?>
					<div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv cpt_onomies">
						<ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
							<li class="tabs"><a href="#<?php echo $taxonomy; ?>-all" tabindex="3"><?php echo $tax->labels->all_items; ?></a></li>
							<li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop" tabindex="3"><?php _e( 'Most Used', 'cpt-onomies' ); ?></a></li>
						</ul>

						<div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display:none;">
							<ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
								<?php $popular_ids = wp_popular_terms_checklist( $taxonomy ); ?>
							</ul>
						</div>

						<div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
							<ul id="<?php echo $taxonomy; ?>checklist" class="list:<?php echo $taxonomy?> categorychecklist form-no-clear">
								<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => $taxonomy, 'popular_cats' => $popular_ids, 'walker' => new CPTonomy_Walker_Terms_Checklist() ) ); ?>
							</ul>
						</div>
					</div>
					<?php
					break;

			}
		}
	}

	/**
	 * This function is run when any post is saved by the action 'save_post'.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager, $cpt_onomy
	 * @param   int $post_id - the ID of the current post
	 * @param   object $post - the current post's information
	 * @return  nada
	 */
	public function save_post( $post_id, $post ) {
		global $cpt_onomies_manager, $cpt_onomy;

		// Pointless if $_POST is empty (this happens on bulk edit).
		if ( empty( $_POST ) ) {
			return;
		}

		// Verify nonce.
		if ( ! ( isset( $_POST['is_bulk_quick_edit'] ) || ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'update-' . $post->post_type . '_' . $post_id ) ) || ( isset( $_POST['custom_post_type_onomies_nonce'] ) && wp_verify_nonce( $_POST['custom_post_type_onomies_nonce'], 'assigning_custom_post_type_onomies_taxonomy_relationships' ) ) ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't save for revisions.
		if ( isset( $post->post_type ) && 'revision' == $post->post_type ) {
			return;
		}

		// Check CPT-onomies.
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy => $tax ) {

			/*
			 * Make sure cpt-onomy was visible, otherwise we might be
			 * deleting relationships for taxonomies that weren't even "editable".
			 */
			$assign_key = "assign_cpt_onomies_{$taxonomy}_rel";
			if ( ! isset( $_POST[ $assign_key ] ) ) {
				continue;
			}

			// Make sure its a valid CPT-onomy.
			if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
				continue;
			}

			// Check permissions.
			if ( ! current_user_can( $tax->cap->assign_terms ) ) {
				continue;
			}

			/*
			 * Set object terms.
			 *
			 * Otherwise, delete taxonomy relationships.
			 */
			if ( isset( $_POST[ CPT_ONOMIES_POSTMETA_KEY ][ $taxonomy ] ) ) {

				// Need to make sure its an array.
				if ( ! is_array( $_POST[ CPT_ONOMIES_POSTMETA_KEY ][ $taxonomy ] ) ) {
					$_POST[ CPT_ONOMIES_POSTMETA_KEY ][ $taxonomy ] = explode( ',', $_POST[ CPT_ONOMIES_POSTMETA_KEY ][ $taxonomy ] );
				}

				// Set the terms.
				$cpt_onomy->wp_set_object_terms( $post_id, $_POST[ CPT_ONOMIES_POSTMETA_KEY ][ $taxonomy ], $taxonomy );

			} else {
				$cpt_onomy->wp_delete_object_term_relationships( $post_id, $taxonomy );
			}
		}
	}

	/**
	 * This function is run when any post is deleted.
	 *
	 * This function is invoked by the action 'delete_post'.
	 *
	 * @since 1.0.1
	 * @uses $wpdb
	 * @param int $post_id - the ID of the post being deleted
	 */
	public function delete_post( $post_id ) {
		global $wpdb;

		// Delete all relationships tied to this term.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d", CPT_ONOMIES_POSTMETA_KEY, $post_id ) );

	}

	/**
	 * Adds CPT-onomies to the "Bulk Edit" and "Quick Edit" screens. The function is called
	 * for each custom column who's name tells us which CPT-onomy checklist to print.
	 *
	 * This function is invoked by the actions 'bulk_edit_custom_box' and 'quick_edit_custom_box'.
	 *
	 * @since   1.0.3
	 * @uses    $cpt_onomies_manager
	 * @param   string $column_name - the name of the column (which tells us which taxonomy to show)
	 * @param   string $post_type - the current post's post type
	 */
	public function bulk_quick_edit_custom_box( $column_name, $post_type ) {
		global $cpt_onomies_manager;

		// Get the taxonomy name from the column name.
		$taxonomy = strtolower( str_replace( 'taxonomy-', '', $column_name ) );

		// If the taxonomy is a CPT-onomy.
		if ( $taxonomy && taxonomy_exists( $taxonomy )
			&& $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) :

			// Get the taxonomy object.
			$tax = get_taxonomy( $taxonomy );

			?>
			<fieldset class="inline-edit-col-center inline-edit-<?php echo $taxonomy; ?>">
				<div class="inline-edit-col">
	                <span class="title inline-edit-<?php echo $taxonomy; ?>-label"><?php echo esc_html( $tax->labels->name ) ?>
	                    <span class="catshow">[<?php _e( 'more', 'cpt-onomies' ); ?>]</span>
	                    <span class="cathide" style="display:none;">[<?php _e( 'less', 'cpt-onomies' ); ?>]</span>
	                </span>
	                <ul class="cat-checklist cpt-onomy-checklist cpt-onomy-<?php echo esc_attr( $taxonomy ); ?>">
	                    <?php

	                    // Print the checklist.
	                    wp_terms_checklist( null, array(
	                    	'taxonomy'  => $taxonomy,
		                    'walker'    => new CPTonomy_Walker_Terms_Checklist(),
	                    ));

	                    ?>
	                </ul>
	                <input type="hidden" name="is_bulk_quick_edit" value="true" />
	                <input type="hidden" name="<?php echo "assign_cpt_onomies_{$taxonomy}_rel"; ?>" value="true" />
				</div>
			</fieldset>
			<?php

		endif;
	}

	/**
	 * This ajax function is run on the "Edit Posts" screen when a "Bulk Edit" is saved.
	 * It retrieves the "checked" CPT-onomy information and saves the object/term relationships.
	 *
	 * Bulk edits do not delete relationships if they are not checked. It only "appends" term relationships,
	 * i.e. assigning checked relationships if they do not already exist.
	 *
	 * @since 1.0.3
	 * @uses $cpt_onomy
	 */
	public function ajax_save_bulk_edit() {
		global $cpt_onomy;

		// Get taxonomy and post type info.
		$post_ids = ( isset( $_POST['custom_post_type_onomies_post_ids'] ) && ! empty( $_POST['custom_post_type_onomies_post_ids'] ) ) ? $_POST['custom_post_type_onomies_post_ids'] : array();
		$taxonomy = ( isset( $_POST['custom_post_type_onomies_taxonomy'] ) && ! empty( $_POST['custom_post_type_onomies_taxonomy'] ) ) ? $_POST['custom_post_type_onomies_taxonomy'] : null;
		$checked_ids = ( isset( $_POST['custom_post_type_onomies_checked_ids'] ) && ! empty( $_POST['custom_post_type_onomies_checked_ids'] ) ) ? $_POST['custom_post_type_onomies_checked_ids'] : array();

		if ( ! empty( $post_ids ) && ! empty( $taxonomy ) ) {
			$tax = get_taxonomy( $taxonomy );

			// Check permissions.
			if ( current_user_can( $tax->cap->assign_terms ) ) {
				foreach ( $post_ids as $post_id ) {

					/*
					 * Set object terms.
					 *
					 * "append" is set to true so it doesn't
					 * delete relationships, only creates.
					 */
					if ( ! empty( $checked_ids ) ) {
						$cpt_onomy->wp_set_object_terms( $post_id, $checked_ids, $taxonomy, true );
					}
				}
			}
		}
		die();
	}

	/**
	 * This ajax function is run on the "Edit Posts" screen when a "Quick Edit" is saved.
	 *
	 * If the post type is not 'post' or 'page', the custom columns are NOT added back to
	 * the row so CPT-onomies takes care of this for you. After CPT-onomies adds the column,
	 * this function is run to populate the column.
	 *
	 * @since 1.0.3
	 */
	public function ajax_quick_edit_populate_custom_columns() {
		global $post;

		// Get taxonomy and post type info.
		$post_id = ( isset( $_POST['custom_post_type_onomies_post_id'] ) && ! empty( $_POST['custom_post_type_onomies_post_id'] ) && is_numeric( $_POST['custom_post_type_onomies_post_id'] ) ) ? $_POST['custom_post_type_onomies_post_id'] : 0;
		$post_type = ( isset( $_POST['custom_post_type_onomies_post_type'] ) && ! empty( $_POST['custom_post_type_onomies_post_type'] ) ) ? $_POST['custom_post_type_onomies_post_type'] : null;
		$column_name = ( isset( $_POST['custom_post_type_onomies_column_name'] ) && ! empty( $_POST['custom_post_type_onomies_column_name'] ) ) ? $_POST['custom_post_type_onomies_column_name'] : null;

		if ( $post_id && ! empty( $post_type ) && ! empty( $column_name ) ) {

			/*
			 * Since the ajax will not retrieve comment info.
			 *
			 * Otherwise, the ajax will retrieve column info
			 * for posts and pages only.
			 */
			if ( 'comments' == $column_name ) :

				?>
				<div class="post-com-count-wrapper">
					<?php

					// We have to set $post so the comment count will pick up the post ID.
					$post->ID = $post_id;
					$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
					$pending_comments = isset( $wp_list_table->comment_pending_count[ $post_id ] ) ? $wp_list_table->comment_pending_count[ $post_id ] : 0;
					$wp_list_table->comments_bubble( $post_id, $pending_comments );

					?>
				</div>
				<?php

			else :

				if ( is_post_type_hierarchical( $post_type ) ) {
					do_action( 'manage_pages_custom_column', $column_name, $post_id );
				} else {
					do_action( 'manage_posts_custom_column', $column_name, $post_id );
				}

				do_action( "manage_{$post_type}_posts_custom_column", $column_name, $post_id );

			endif;
		}
		die();
	}

	/**
	 * Adds dropdown(s) to the "Edit Posts" screen which allow you to filter your posts by
	 * your CPT-onomies. CPT-onomies "hides" the dropdown if it's matching column is hidden.
	 *
	 * You can remove the dropdown(s) by return false to the
	 * 'custom_post_type_onomies_add_cpt_onomy_admin_dropdown_filter', which passes
	 * two parameters: the $taxonomy and the $post_type.
	 *
	 * This function is invoked by the action 'restrict_manage_posts'.
	 *
	 * @since   1.0.3
	 * @uses    $cpt_onomy, $cpt_onomies_manager, $wp_list_table, $post_type
	 * @filters 'custom_post_type_onomies_add_cpt_onomy_admin_dropdown_filter' - $taxonomy, $post_type
	 */
	public function restrict_manage_posts() {
		global $cpt_onomy, $cpt_onomies_manager, $wp_list_table, $post_type;

		list( $columns, $hidden ) = $wp_list_table->get_column_info();
		foreach ( $columns as $column_name => $column_display_name ) {

			/*
			 * The filter drop down is added if you have the column added
			 * but you still have the capability to remove the dropdown
			 * via filter, if desired.
			 */

			// Get the taxonomy name from the column name.
			$taxonomy = strtolower( str_replace( 'taxonomy-', '', $column_name ) );

			// Make sure its a registered CPT-onomy.
			if ( $taxonomy && $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {

				// Get taxonomy information.
				$tax = get_taxonomy( $taxonomy );

				// This filter allows you to remove the dropdown by returning false.
				if ( apply_filters( 'custom_post_type_onomies_add_cpt_onomy_admin_dropdown_filter', ( isset( $tax->show_admin_column ) && ! $tax->show_admin_column ) ? false : true, $taxonomy, $post_type ) ) {

					// Get post type info.
					$post_type_object = get_post_type_object( $taxonomy );

					// Get selected term.
					$selected = ( isset( $_REQUEST[ $taxonomy ] ) ) ? $_REQUEST[ $taxonomy ] : null;

					// If slug, then get term id.
					if ( ! is_numeric( $selected ) ) {
						$term = $cpt_onomy->get_term_by( 'slug', $selected, $taxonomy );
						if ( $term ) {
							$selected = $term->term_id;
						}
					}

					// Print dropdown.
					wp_dropdown_categories( array(
						'show_option_all'   => sprintf( __( 'View all %s', 'cpt-onomies' ), $post_type_object->labels->all_items ),
						'hierarchical'      => true,
						'show_count'        => false,
						'orderby'           => 'name',
						'selected'          => $selected,
						'name'              => $taxonomy,
						'id'                => 'dropdown_custom_post_type_onomies_' . $taxonomy,
						'class'             => 'postform ' . ( ( in_array( $column_name, $hidden ) ) ? ' hide-all' : '' ),
						'taxonomy'          => $taxonomy,
						'hide_if_empty'     => true,
					));

				}
			}
		}
	}

	/**
	 *
	 * If a CPT-onomy is attached to a post type, the plugin adds a column
	 * to the post type's edit screen which lists each post's assigned terms.
	 *
	 * As of version 1.3.5, removed the backwards compatibility
	 * but keeping this function so we can keep the filters in action.
	 *
	 * You can remove the column by returning false to the
	 * 'custom_post_type_onomies_add_cpt_onomy_admin_column' filter, which passes
	 * two parameters: the $taxonomy and the $post_type.
	 *
	 * This function adds the columns to the screen.
	 * $this->edit_cpt_onomy_admin_column() adds the assigned terms to each column.
	 *
	 * This function is applied to the filter 'manage_pages_columns' and 'manage_posts_columns'.
	 * The 'posts' filter sends 2 parameters ($columns and $post_type)
	 * but the 'pages' only send $columns so I define $post_type to cover 'pages'.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager
	 * @param   array $columns - the column info already created by WordPress
	 * @param   string $post_type - the name of the post type being managed/edited
	 * @return  array - the columns info after it has been filtered
	 * @filters custom_post_type_onomies_add_cpt_onomy_admin_column - $taxonomy, $post_type
	 */
	public function add_cpt_onomy_admin_column( $columns, $post_type = 'page' ) {
		global $cpt_onomies_manager;

		// Process each taxonomy assigned to the current post type.
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy => $tax ) {

			// Make sure its a registered CPT-onomy.
			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {

				// If the column already exists, i.e. added by WordPress...
				if ( array_key_exists( 'taxonomy-' . $taxonomy, $columns ) ) {

					// Define the column key.
					$column_key = "taxonomy-{$taxonomy}";

					/*
					 * This filter allows you to remove the column by returning false.
					 *
					 * Otherwise, if we're keeping the column, we can customize the title.
					 */
					if ( ! apply_filters( 'custom_post_type_onomies_add_cpt_onomy_admin_column', true, $taxonomy, $post_type ) ) {

						// Remove the column.
						unset( $columns[ $column_key ] );

					} else {

						// Get the new column title/header - defaults to taxonomy's label.
						if ( $new_column_title = apply_filters( 'custom_post_type_onomies_cpt_onomy_admin_column_title', ( isset( $tax->admin_column_title ) && ! empty( $tax->admin_column_title ) ? $tax->admin_column_title : $tax->label ), $taxonomy, $post_type ) ) {

							// Set the new column title.
							$columns[ $column_key ] = $new_column_title;

						}
					}
				}
			}
		}
		return $columns;
	}

	/**
	 * Adds the filter for adding/managing sortable
	 * CPT-onomy admin columns.
	 *
	 * I deprecated the ability to make the CPT-onomy admin
	 * columns sortable in version 1.3 to align with new,
	 * default WP taxonomy admin column functionality.
	 * Re-instated the sortable columns in version 1.3.2
	 * due to its popularity.
	 *
	 * This function is invoked by the action 'load-edit.php'.
	 *
	 * @reinstated in 1.3.2, first introduced in 1.0.3, deprecated in 1.3
	 * @uses $current_screen
	 */
	public function add_cpt_onomy_admin_sortable_columns_filter() {
		global $current_screen;
		if ( $current_screen && isset( $current_screen->id ) ) {
			add_filter( "manage_{$current_screen->id}_sortable_columns", array( $this, 'add_cpt_onomy_admin_sortable_columns' ) );
		}
	}

	/**
	 * Tells WordPress to make our CPT-onomy admin columns sortable.
	 *
	 * You can disable the columns from being sortable by returning false to the
	 * 'custom_post_type_onomies_add_cpt_onomy_admin_sortable_column' filter, which
	 * passes two parameters: the $taxonomy and the $post_type.
	 *
	 * If you want to remove the column altogether, set "Show Admin Column"
	 * to false in your CPT-onomy settings, or return false to the
	 * 'custom_post_type_onomies_add_cpt_onomy_admin_column' filter, which
	 * passes the same two parameters: $taxonomy and $post_type.
	 *
	 * This function is invoked by the filter "manage_{$current_screen->id}_sortable_columns".
	 *
	 * @reinstated in 1.3.2, first introduced in 1.0.3, deprecated in 1.3
	 * @uses $cpt_onomies_manager, $current_screen
	 * @param array $sortable_columns - the sortable columns info already created by WordPress
	 * @return array - the sortable columns info after it has been filtered
	 * @filters 'custom_post_type_onomies_add_cpt_onomy_admin_sortable_column' - $taxonomy, $post_type
	 */
	public function add_cpt_onomy_admin_sortable_columns( $sortable_columns ) {
		global $cpt_onomies_manager, $current_screen;

		// Get the current post type.
		if ( $post_type = isset( $current_screen->post_type ) ? $current_screen->post_type : null ) {

			// Process each taxonomy assigned to the current post type.
			foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy => $tax ) {

				// Make sure its a registered CPT-onomy and get the taxonomy's query variable
				if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy )
					&& ( $query_var = isset( $tax->query_var ) ? $tax->query_var : null ) ) {

					/*
					 * This filter allows you to remove the column by returning false.
					 * All CPT-onomy admin columns are default-ly added as sortable.
					 */
					if ( apply_filters( 'custom_post_type_onomies_add_cpt_onomy_admin_sortable_column', true, $taxonomy, $post_type ) ) {
						$column_key = "taxonomy-{$taxonomy}";
						$sortable_columns[ $column_key ] = $query_var;
					}
				}
			}
		}
		return $sortable_columns;
	}

}

/**
 * Custom walker used for wp_terms_checklist() so we can edit the input name.
 *
 * @since 1.0
 */
class CPTonomy_Walker_Terms_Checklist extends Walker {
	var $tree_type = 'category';
	var $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	/**
	 * Added this function in version 1.2 in order to allow
	 * users to exclude term ids from the checklist.
	 *
	 * @uses    $cpt_onomies_admin, $post_type, $post
	 * @param   object $element Data object
	 * @param   array $children_elements List of elements to continue traversing.
	 * @param   int $max_depth Max depth to traverse.
	 * @param   int $depth Depth of current element.
	 * @param   array $args
	 * @param   string $output Passed by reference. Used to append additional content.
	 * @return  null Null on failure with no changes to parameters.
	 */
	function display_element( $element, &$children_elements, $max_depth, $depth = 0, $args, &$output ) {
		global $cpt_onomies_admin, $post_type, $post;

		// Make sure we have an element.
		if ( ! $element ) {
			return;
		}

		$id_field = $this->db_fields['id'];

		/*
		 * This data was retrieved from the filter
		 * 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids'
		 * when we printed the CPT-onomy meta boxes.
		 */
		$include_term_ids = isset( $cpt_onomies_admin->assigning_terms_include_term_ids[ $element->taxonomy ][ $post_type ][ $post->ID ] ) ? $cpt_onomies_admin->assigning_terms_include_term_ids[ $element->taxonomy ][ $post_type ][ $post->ID ] : array();

		/*
		 * This data was retrieved from the filter
		 * 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids'
		 * when we printed the CPT-onomy meta boxes.
		 */
		$exclude_term_ids = isset( $cpt_onomies_admin->assigning_terms_exclude_term_ids[ $element->taxonomy ][ $post_type ][ $post->ID ] ) ? $cpt_onomies_admin->assigning_terms_exclude_term_ids[ $element->taxonomy ][ $post_type ][ $post->ID ] : array();

		// Whether or not we want the element displayed.
		$display_element = true;
		if ( $include_term_ids && ! in_array( $element->$id_field, $include_term_ids ) ) {
			$display_element = false;
		}

		if ( $exclude_term_ids && in_array( $element->$id_field, $exclude_term_ids ) ) {
			$display_element = false;
		}

		if ( $display_element ) {

			// Display this element.
			if ( is_array( $args[0] ) ) {
				$args[0]['has_children'] = ! empty( $children_elements[ $element->$id_field ] );
			}

			$cb_args = array_merge( array( &$output, $element, $depth ), $args );
			call_user_func_array( array( $this, 'start_el' ), $cb_args );

			$id = $element->$id_field;

			// Descend only when the depth is right and there are childrens for this element.
			if ( ( 0 == $max_depth || $max_depth > $depth + 1 ) && isset( $children_elements[ $id ] ) ) {

				foreach ( $children_elements[ $id ] as $child ) {

					if ( ! isset( $newlevel ) ) {
						$newlevel = true;

						// Start the child delimiter.
						$cb_args = array_merge( array( &$output, $depth ), $args );
						call_user_func_array( array( $this, 'start_lvl' ), $cb_args );

					}

					$this->display_element( $child, $children_elements, $max_depth, $depth + 1, $args, $output );

				}
				unset( $children_elements[ $id ] );
			}

			if ( isset( $newlevel ) && $newlevel ) {

				// End the child delimiter.
				$cb_args = array_merge( array( &$output, $depth ), $args );
				call_user_func_array( array( $this, 'end_lvl' ), $cb_args );

			}

			// End this element.
			$cb_args = array_merge( array( &$output, $element, $depth ), $args );
			call_user_func_array( array( $this, 'end_el' ), $cb_args );

		}
	}

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		if ( ! empty( $args['taxonomy'] ) ) {
			$class = in_array( $object->term_id, $args['popular_cats'] ) ? ' class="popular-category"' : '';
			$output .= "\n<li id='{$args['taxonomy']}-{$object->term_id}'{$class}>" . '<label class="selectit"><input value="' . $object->term_id . '" type="checkbox" name="' . CPT_ONOMIES_POSTMETA_KEY . '[' . $args['taxonomy'] . '][]" id="in-' . $args['taxonomy'] . '-' . $object->term_id . '"' . checked( in_array( $object->term_id, $args['selected_cats'] ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters( 'the_category', $object->name ) ) . '</label>';
		}
	}

	function end_el( &$output, $category, $depth = 0, $args = array() ) {
		$output .= "</li>\n";
	}
}
