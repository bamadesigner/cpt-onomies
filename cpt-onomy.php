<?php

// Instantiate the class
global $cpt_onomy;
$cpt_onomy = new CPT_TAXONOMY();

/**
 * Holds the functions needed for using a custom post type as a taxonomy.
 *
 * @since 1.0
 */
class CPT_TAXONOMY {
	
	/**
	 * Adds WordPress hooks (actions and filters).
	 *
	 * @since 1.0
	 */
	public function __construct() {
		
		// Function filters
		add_filter( 'get_terms', array( $this, 'get_terms' ), 1, 3 );
		add_filter( 'get_object_terms', array( $this, 'get_object_terms' ), 1, 4 );
		
		// Other filters
		add_filter( 'get_terms_args', array( $this, 'adjust_get_terms_args' ), 1, 2 );
		add_filter( 'get_the_terms', array( $this, 'get_the_terms' ), 1, 3 );
		
	}
	public function CPT_TAXONOMY() { $this->__construct(); }
	
	/**
	 * This function takes an object's information and creates a term object.
	 *
	 * As of version 1.2, you can hook into the 'term_description' or
	 * '{$taxonomy}_description' filter to add a description to your terms.
	 *
	 * The variable type (object or array) for the returned $term will match the set type of the passed $object.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager
	 * @param   array|object $object - the information for the object you are converting
	 * @param   boolean $get_count - whether to get the term count
	 * @return  array|object - the information for the term you have created.
	 */
	private function convert_object_to_cpt_onomy_term( $object, $get_count = true ) {
		global $cpt_onomies_manager;
		
		// If its empty, then there's no point
		if ( empty( $object ) ) {
			return $object;
		}
		
		// Make sure the term is an object
		$term = (object) $object;
		
		// Make sure its a CPT-onomy
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $term->post_type ) ) {
			return $object;
		}
			
		/**
		 * sanitize_term_field() lets you apply the 'term_description'
		 * or '{$taxonomy}_description' filter to tweak the description,
		 * if desired. Maybe you want the description to be a custom field?
		 * or the post content. Just return that info in the filter!
		 */
		$term = array(
			'term_id' => $term->ID,
			'name' => apply_filters( 'the_title', $term->post_title, $term->ID ),
			'slug' => $term->post_name,
			'term_group' => $term->post_parent,
			'term_taxonomy_id' => 0,
			'taxonomy' => $term->post_type,
			'description' => sanitize_term_field( 'description', '', $term->ID, $term->post_type, 'display' ), 
			'parent' => $term->post_parent
		);
		
		if ( $get_count ) {
			$term['count'] = $this->get_term_count( $term['term_id'], $term['taxonomy'] );
		}
			
		if ( is_object( $object ) ) {
			return (object) $term;
		}
			
		return $term;
		
	}
	
	/**
	 * Since setting the argument 'fields' to 'count' will not work with CPT-onomies,
	 * this gets rid of that field and adds a custom count argument that's applied
	 * in our get_terms() filter function. This allows the WP function wp_count_terms()
	 * to work with CPT-onomies. 
	 *
	 * This function is applied to the filter 'get_terms_args'.
	 * The filter 'get_terms_args' was not added to get_terms() until 3.1 so this 
	 * function will not work before WordPress version 3.1.
	 *
	 * @since   1.0.2
	 * @uses    $cpt_onomies_manager
	 * @param   array $args - original get_terms() arguments
	 * @param   array $taxonomies - the taxonomies we're getting terms from
	 * @return  array - the filtered $args
	 */
	public function adjust_get_terms_args( $args, $taxonomies ) {
		global $cpt_onomies_manager;
		
		// This function only filters registered CPT-onomies
		$cpt_taxonomies = array();
		foreach( $taxonomies as $taxonomy ) {
			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
				$cpt_taxonomies[] = $taxonomy;
			}
		}
		
		// This means there are no CPT-onomies so wrap things up
		if ( empty( $cpt_taxonomies ) ) {
			return $args;
		}
		
		// Change 'fields' to 'ids' and add a custom count argument
		if ( isset( $args[ 'fields' ] ) && $args[ 'fields' ] == 'count' ) {
			$args[ 'fields' ] = 'ids';
			$args[ 'cpt_onomy_get_count' ] = true;
		}
		
		return $args;
		
	}

	/**
	 *
	 * Whenever get_the_terms() is called, we need to
	 * clear the cache that WordPress stores.
	 *
	 * @since   1.3.5
	 * @param   array|WP_Error $terms    List of attached terms, or WP_Error on failure.
	 * @param   int            $post_id  Post ID.
	 * @param   string         $taxonomy Name of the taxonomy.
	 * @return  array|WP_Error $terms    List of attached terms, or WP_Error on failure.
	 */
	public function get_the_terms( $terms, $post_id, $taxonomy ) {

		// Clear the cache that WordPress adds
		wp_cache_delete( $post_id, "{$taxonomy}_relationships" );

		return $terms;
	}
	
	/**
	 * This function mimics the WordPress function get_term()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * @since 1.0
	 * @uses $cpt_onomies_manager
	 * @param int|object $term If integer, will get from database. If object will apply filters and return $term.
	 * @param string $taxonomy Taxonomy name that $term is part of.
	 * @param string $output Constant OBJECT, ARRAY_A, or ARRAY_N
	 * @param string $filter Optional, default is raw or no WordPress defined filter will applied.
	 * @return mixed|null|WP_Error Term Row from database. Will return null if $term is empty. If taxonomy does not
	 * 		exist then WP_Error will be returned.
	 */
	public function get_term( $term, $taxonomy, $output = OBJECT, $filter = 'raw' ) {
		global $cpt_onomies_manager;
		$null = null;
	
		if ( empty( $term ) ) {
			$error = new WP_Error( 'invalid_term', __( 'Empty Term', 'cpt-onomies' ) );
			return $error;
		}
	
		if ( ! taxonomy_exists( $taxonomy ) ) {
			$error = new WP_Error( 'invalid_taxonomy', __( 'Invalid Taxonomy', 'cpt-onomies' ) );
			return $error;
		}
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return get_term( $term, $taxonomy, $output, $filter );
		}
			
		if ( is_object( $term ) && empty( $term->filter ) ) {
			wp_cache_add( $term->term_id, $term, $taxonomy );
			$_term = $term;
		} else {
			if ( is_object( $term ) ) {
				$term = $term->term_id;
			}
			if ( ! $term = (int) $term ) {
				return $null;
			}
			if ( ! $_term = wp_cache_get( $term, $taxonomy ) ) {
				$_term = $this->convert_object_to_cpt_onomy_term( get_post( $term ) );
				if ( ! $_term ) {
					return $null;
				}
				wp_cache_add( $term, $_term, $taxonomy );
			}
		}
	
		$_term = apply_filters( 'get_term', $_term, $taxonomy );
		$_term = apply_filters( "get_$taxonomy", $_term, $taxonomy );
		$_term = sanitize_term( $_term, $taxonomy, $filter );
	
		if ( $output == OBJECT ) {
			return $_term;
		} elseif ( $output == ARRAY_A ) {
			$__term = get_object_vars( $_term );
			return $__term;
		} elseif ( $output == ARRAY_N ) {
			$__term = array_values( get_object_vars( $_term ) );
			return $__term;
		} else {
			return $_term;
		}
		
	}
	
	/**
	 * This function mimics the WordPress function get_term_by()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * @since   1.0
	 * @uses    $wpdb, $cpt_onomies_manager
	 * @param   string $field Either 'slug', 'name', or 'id'
	 * @param   string|int $value Search for this term value
	 * @param   string $taxonomy Taxonomy Name
	 * @param   string $output Constant OBJECT, ARRAY_A, or ARRAY_N
	 * @param   string $filter Optional, default is raw or no WordPress defined filter will applied.
	 * @param   int $parent allows to get a term by its parent's term id
	 * @return  mixed Term Row from database. Will return false if $taxonomy does not exist or $term was not found.
	 */
	public function get_term_by( $field, $value, $taxonomy, $output = OBJECT, $filter = 'raw', $parent = 0 ) {
		global $wpdb, $cpt_onomies_manager;
	
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return get_term_by( $field, $value, $taxonomy, $output, $filter );
		}
			
		if ( $parent > 0 ) {
			$parent = " AND wpposts.post_parent = " . $parent;
		} else {
			$parent = null;
		}
	
		if ( 'slug' == $field ) {
			
			$value = sanitize_title( $value );
			
			if ( empty( $value ) ) {
				return false;
			}
			
			// Get eligible post types
			$eligible_post_types = ( $tax = get_taxonomy( $taxonomy ) ) && isset( $tax->object_type ) ? $tax->object_type : array();
				
			// Find term and term count
			$query = "SELECT (SELECT COUNT(*) FROM {$wpdb->postmeta} wpcountmeta INNER JOIN {$wpdb->posts} wpcountposts ON wpcountposts.ID = wpcountmeta.post_id AND wpcountposts.post_status = 'publish' AND wpcountposts.post_type IN ('" . implode( "','", $eligible_post_types ) . "') WHERE wpcountmeta.meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "' AND wpcountmeta.meta_value = wpposts.ID) AS count, wpposts.* FROM {$wpdb->posts} wpposts WHERE wpposts.post_type = '{$taxonomy}' AND wpposts.post_name = '{$value}' and wpposts.post_status = 'publish'" . $parent;
			
		} else if ( 'name' == $field ) {
			
			// Assume already escaped
			$value = stripslashes( $value );
			
			// Get eligible post types
			$eligible_post_types = ( $tax = get_taxonomy( $taxonomy ) ) && isset( $tax->object_type ) ? $tax->object_type : array();
			
			// Find term and term count
			$query = "SELECT (SELECT COUNT(*) FROM {$wpdb->postmeta} wpcountmeta INNER JOIN {$wpdb->posts} wpcountposts ON wpcountposts.ID = wpcountmeta.post_id AND wpcountposts.post_status = 'publish' AND wpcountposts.post_type IN ('" . implode( "','", $eligible_post_types ) . "') WHERE wpcountmeta.meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "' AND wpcountmeta.meta_value = wpposts.ID) AS count, wpposts.* FROM {$wpdb->posts} wpposts WHERE wpposts.post_type = '{$taxonomy}' AND wpposts.post_title = '{$value}' and wpposts.post_status = 'publish'" . $parent;
		
		} else {
			
			$term = $this->get_term( (int) $value, $taxonomy, $output, $filter );
			
			if ( is_wp_error( $term ) ) {
				return false;
			}
			
			return $term;
		
		}
		
		// Get the term
		$term = $wpdb->get_row( $query );
		
		if ( ! $term ) {
			return false;
		}
		
		// Save the term count and remove from $term before conversion
		$term_count = $term->count;
		unset( $term->count );
		
		// Dont get the count, we'll add from before
		$term = $this->convert_object_to_cpt_onomy_term( $term, false );
		
		if ( ! $term ) {
			return false;
		}
		
		// Add count
		$term->count = $term_count;
	
		wp_cache_add( $term->term_id, $term, $taxonomy );
	
		$term = apply_filters( 'get_term', $term, $taxonomy );
		$term = apply_filters( "get_$taxonomy", $term, $taxonomy );
		$term = sanitize_term( $term, $taxonomy, $filter );
	
		if ( $output == OBJECT ) {
			return $term;
		} elseif ( $output == ARRAY_A ) {
			return get_object_vars( $term );
		} elseif ( $output == ARRAY_N ) {
			return array_values( get_object_vars( $term ) );
		}
			
		return $term;
		
	}
	
	/**
	 * As of 1.0.2, wp_count_terms() works with CPT-onomies so this
	 * function is now deprecated and will send you to the WordPress function.
	 *
	 * As of version 1.0.3, the WordPress minimum version is 3.1 and the filter
	 * 'get_terms_args' that allows CPT-onomies to work with wp_count_terms() was 
	 * added in 3.1 so everyone is sent to the WordPress function.
	 * 
	 * @param   string $taxonomy Taxonomy name
	 * @param   array|string $args Overwrite defaults. See get_terms()
	 * @return  int How many terms are in $taxonomy
	 */
	public function wp_count_terms( $taxonomy, $args = array() ) {
		return wp_count_terms( $taxonomy, $args );			
	}
	
	/**
	 * Returns an array of term counts for a specific
	 * CPT-onomy indexed by the term ID.
	 *
	 * Stores the term counts in the WP cache to help
	 * with query load.
	 *
	 * @since 1.3.3
	 * @param csv|string - $taxonomies - CPT-onomy names
	 * @param csv|array - $term_ids - you can pass specific term IDs instead of all terms (will not use cache though)
	 * @return array|false - array of term counts indexed by term ID or false if error
	 */
	private function get_terms_count( $taxonomies, $term_ids = NULL ) {
		global $cpt_onomies_manager, $wpdb;
		
		// Make sure the taxonomies is an array
		if ( ! empty( $taxonomies ) && ! is_array( $taxonomies ) ) {
			$taxonomies = explode( ',', $taxonomies );
		}
		
		// Make sure they are valid CPT-onomies
		foreach( $taxonomies as $index => $taxonomy ) {
			
			// If it's not a valid CPT-onomy...
			if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
				
				// Remove this taxonomy
				unset( $taxonomies[ $index ] );
				
			}
			
		}
		
		// If we have no valid taxonomies then there's no point in continuing
		if ( ! $taxonomies ) {
			return false;
		}
			
		// We're gonna store the term counts in an array indexed by term ID
		$terms_count = array();
		
		// Make sure term IDs is an array
		if ( ! empty( $term_ids ) && ! is_array( $term_ids ) ) {
			$term_ids = explode( ',', $term_ids );
		}
		
		// This will hold taxonomies that need to be queried
		$taxonomies_to_query = array();
			
		// First, see if we can get from the cache
		foreach( $taxonomies as $taxonomy ) {
			
			// Checking the cache...
			if ( ( $terms_count_from_cache = wp_cache_get( $taxonomy, 'cpt_onomies_terms_count' ) )
				&& $terms_count_from_cache !== false
				&& is_array( $terms_count_from_cache ) ) {
					
				// If we only want specific term IDs...
				if ( ! empty( $term_ids ) ) {
					
					// Then only get specific term IDs from the cache
					foreach( $terms_count_from_cache as $term_id => $term_id_count ) {
						if ( ! in_array( $term_id, $term_ids ) ) {
							unset( $terms_count_from_cache[ $term_id ] );
						}
					}
					
				}
					
				// Add the counts from the cache
				$terms_count += $terms_count_from_cache;
				
			} else {
				
				// We need to query this taxonomy
				$taxonomies_to_query[] = $taxonomy;
				
			}
			
		}
		
		// These taxonomies weren't cached so we need to query the database
		if ( $taxonomies_to_query ) {
			
			// Build an array of eligible post types for these CPT-onomies
			$eligible_post_types = array();
			
			// Get eligible post types for each taxonomy
			foreach( $taxonomies_to_query as $index => $taxonomy ) {
				
				// Get eligible post types
				$tax_eligible_post_types = ( $tax = get_taxonomy( $taxonomy ) ) && isset( $tax->object_type ) ? $tax->object_type : NULL;
				
				// If we have some, then merge with the array
				if ( $tax_eligible_post_types ) {
					$eligible_post_types = array_merge( $eligible_post_types, $tax_eligible_post_types );
				}
				
			}
			
			// Make sure the eligible post types are unique
			$eligible_post_types = array_unique( $eligible_post_types );
			
			// If we have no eligible post types then there's no point in continuing
			if ( $eligible_post_types ) {
		
				// Build the terms count query
				$terms_count_query = "SELECT meta.meta_value AS ID, terms.post_type AS taxonomy, COUNT(meta.meta_value) AS count
	
					FROM {$wpdb->postmeta} meta 
					
						INNER JOIN {$wpdb->posts} objects
							ON objects.ID = meta.post_id
							AND objects.post_type IN ( '" . implode( "','", $eligible_post_types ) . "' )
							AND objects.post_status = 'publish'";
							
						// If we have no specific term IDs then we have to join the posts table to match taxonomy/post type
						if ( empty( $term_ids ) ) {
							
							$terms_count_query .= " INNER JOIN {$wpdb->posts} terms 
								ON terms.ID = meta.meta_value
								AND terms.post_type IN ( '" . implode( "','", $taxonomies_to_query ) . "' )
								AND objects.post_status = 'publish'";
								
						}
							
					$terms_count_query .= " WHERE meta.meta_key = %s";
					
						// If we have term IDs
						if ( ! empty( $term_ids ) )
							$terms_count_query .= " AND meta.meta_value IN ( '" . implode( "','", $term_ids ) . "' )";
						
					$terms_count_query .= " GROUP BY meta.meta_value";
				
				// Get term count from the database
				if ( $terms_count_from_db = $wpdb->get_results( $wpdb->prepare( $terms_count_query, CPT_ONOMIES_POSTMETA_KEY ) ) ) {
					
					// If no specific term IDs, separate count by taxonomy for the cache
					$terms_count_by_taxonomy = array();
					
					// If we have a posts count, we need to rearrange the array
					foreach( $terms_count_from_db as $terms_count_index => $terms_count_item ) {
						
						// Get the term count
						$term_count = isset( $terms_count_item->count ) && $terms_count_item->count > 0 ? $terms_count_item->count : 0;
						
						// Store count by term ID
						$terms_count[ $terms_count_item->ID ] = $term_count;
						
						// Store count by taxonomy and term ID
						$terms_count_by_taxonomy[ $terms_count_item->taxonomy ][ $terms_count_item->ID ] = $term_count;
						
					}
					
					// If no specific term IDs, set the cache
					if ( empty( $term_ids ) && ! empty( $terms_count_by_taxonomy ) ) {
						
						foreach( $terms_count_by_taxonomy as $taxonomy => $taxonomy_terms_count ) {
							
							// Store the terms count for this taxonomy
							wp_cache_set( $taxonomy, $taxonomy_terms_count, 'cpt_onomies_terms_count' );
							
						}
						
					}
					
				}
				
			}
			
		}
		
		return $terms_count;
		
	}
	
	/**
	 * This function determines how many times a term has been assigned to an object.
	 *
	 * @since   1.0
	 * @uses    $wpdb, $cpt_onomies_manager
	 * @param   int $term_id - the ID of the term you're counting
	 * @param   string $taxonomy - the taxonomy the term belongs to
	 * @return  int - the number of times a term has been assigned to an object
	 */
	public function get_term_count( $term_id, $taxonomy ) {
		global $wpdb, $cpt_onomies_manager;
		
		// Must have a term ID and be a CPT-onomy
		if ( is_numeric( $term_id ) && $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			
			// We're only counting the posts who are supposed to be associated with this taxonomy
			if ( ( $eligible_post_types = ( $tax = get_taxonomy( $taxonomy ) ) && isset( $tax->object_type ) ? $tax->object_type : array() )
				&& ! empty( $eligible_post_types ) ) {
			
				return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} wpcountmeta INNER JOIN {$wpdb->posts} wpposts_object ON wpposts_object.ID = wpcountmeta.post_id AND wpposts_object.post_status = 'publish' AND wpposts_object.post_type IN ( '" . implode( "','", $eligible_post_types ) . "' ) INNER JOIN {$wpdb->posts} wpposts_term ON wpposts_term.ID = wpcountmeta.meta_value AND wpposts_term.post_status = 'publish' AND wpposts_term.post_type = %s WHERE wpcountmeta.meta_key = %s AND wpcountmeta.meta_value = %d", $taxonomy, CPT_ONOMIES_POSTMETA_KEY, $term_id ) );
			}
		
		}
		
		return 0;
		
	}
		
	/**
	 * This function mimics the WordPress function get_term_children()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * As of WordPress 3.3.2, CPT-onomies will work with get_term_children()
	 * but I'm not a fan of how WordPress stores the children ids in an option. 
	 * 
	 * @since 1.0
	 * @uses $wpdb, $cpt_onomies_manager
	 * @param string $term_id ID of Term to get children
	 * @param string $taxonomy Taxonomy Name
	 * @return array|WP_Error List of Term Objects. WP_Error returned if $taxonomy does not exist
	 */
	public function get_term_children( $term_id, $taxonomy ) {
		global $wpdb, $cpt_onomies_manager;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'Invalid Taxonomy', 'cpt-onomies' ) );
		}
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return get_term_children( $term_id, $taxonomy );
		}
	
		$term_id = intval( $term_id );
		
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM " . $wpdb->posts . " WHERE post_parent = %d AND post_status = 'publish' AND post_type = %s", $term_id, $taxonomy ) );
		
		if ( empty( $children ) ) {
			return array();
		}
		
		foreach ( $children as $child_id ) {
			$children = array_merge( $children, $this->get_term_children( $child_id, $taxonomy ) );			
		}
		
		return $children;
		
	}

	/**
	 * Get an array of ancestor IDs for a given term.
	 *
	 * @since   1.1
	 * @uses    $cpt_onomies_manager
	 * @param   int $term_id - The ID of the term for which we'll be retrieving ancestors
	 * @param   string $taxonomy - the taxonomy name
	 * @return  array|WP_Error List of Term Objects. WP_Error returned if $taxonomy does not exist
	 */
	public function get_term_ancestors( $term_id = 0, $taxonomy = '' ) {
		global $cpt_onomies_manager;
		
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'Invalid Taxonomy', 'cpt-onomies' ) );
		}
			
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return get_ancestors( $term_id, $taxonomy );
		}

		$term_id = (int) $term_id;
	
		$ancestors = array();
	
		if ( empty( $term_id ) ) {
			return apply_filters( 'get_ancestors', $ancestors, $term_id, $taxonomy );
		}
		
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$term = $this->get_term( $term_id, $taxonomy );
			while ( ! is_wp_error( $term ) && ! empty( $term->parent ) && ! in_array( $term->parent, $ancestors ) ) {
				$ancestors[] = (int) $term->parent;
				$term = $this->get_term( $term->parent, $taxonomy );
			}
		}
	
		return apply_filters( 'get_ancestors', $ancestors, $term_id, $taxonomy );
		
	}
	
	/**
	 * This function mimics the WordPress function term_exists()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * @since 1.0
	 * @uses $wpdb, $cpt_onomies_manager
	 * @param int|string $term The term to check
	 * @param string $taxonomy The taxonomy name to use
	 * @param int $parent ID of parent term under which to confine the exists search.
	 * @return mixed Get the term id or Term Object, if exists.
	 */
	public function term_exists( $term, $taxonomy = '', $parent = 0 ) {
		global $wpdb, $cpt_onomies_manager;
		
		if ( is_int( $term ) ) {
			
			if ( 0 == $term ) {
				return 0;
			}
				
			/**
			 * This function only processes registered CPT-onomies.
			 * If this is a normal taxonomy, then use the WordPress function.
			 */
			if ( ! empty( $taxonomy ) && ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
				return term_exists( $term, $taxonomy, $parent );
			}
			
			else if ( ! empty( $taxonomy ) ) {
				return $this->get_term( $term, $taxonomy );
			}
			
			// Get term info and convert
			$term = $this->convert_object_to_cpt_onomy_term( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->posts . " WHERE ID = %d AND post_status = 'publish'", $term ) ) );
			
			if ( ! $term ) {
				return 0;
			}
			
			// Make sure this term belongs to a CPT-onomy
			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $term->taxonomy ) ) {
				return $term;
			}
			
			return 0;
				
		}
	
		$term = trim( stripslashes( $term ) );
	
		if ( '' === $slug = sanitize_title($term) ) {
			return 0;
		}
			
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! empty( $taxonomy ) && ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return term_exists( $term, $taxonomy, $parent );
		}
		
		else if ( ! empty( $taxonomy ) ) {
						
			// Check for parent
			$parent = (int) $parent;
			if ( $parent > 0 ) {
				$parent = ' AND wp_posts.post_parent = ' . $parent;
			} else {
				$parent = null;
			}
			
			// Get CPT-onomy's eligible post types
			$eligible_post_types = ( $tax = get_taxonomy( $taxonomy ) ) && isset( $tax->object_type ) ? $tax->object_type : array();
				
			// Check for name first
			// Get term info and term count
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT (SELECT COUNT(*) FROM {$wpdb->postmeta} wp_count_meta INNER JOIN {$wpdb->posts} wp_count_posts ON wp_count_posts.ID = wp_count_meta.post_id AND wp_count_posts.post_type IN ('" . implode( "','", $eligible_post_types ) . "') AND wp_count_posts.post_status = 'publish' WHERE wp_count_meta.meta_value = wp_posts.ID) AS count, wp_posts.* FROM {$wpdb->posts} wp_posts WHERE wp_posts.post_title = %s AND wp_posts.post_status = 'publish'" . $parent . " AND wp_posts.post_type = %s AND wp_posts.post_status = 'publish'", $term, $taxonomy ) );
			
			// Check for slug
			// Get term info and term count
			if ( empty( $result ) ) {
				$result = $wpdb->get_row( $wpdb->prepare( "SELECT (SELECT COUNT(*) FROM {$wpdb->postmeta} wp_count_meta INNER JOIN {$wpdb->posts} wp_count_posts ON wp_count_posts.ID = wp_count_meta.post_id AND wp_count_posts.post_type IN ('" . implode( "','", $eligible_post_types ) . "') AND wp_count_posts.post_status = 'publish' WHERE wp_count_meta.meta_value = wp_posts.ID) AS count, wp_posts.* FROM {$wpdb->posts} wp_posts WHERE wp_posts.post_name = %s" . $parent . " AND wp_posts.post_type = %s AND wp_posts.post_status = 'publish'", $term, $taxonomy ) );
			}
			
			if ( ! empty( $result ) && $cpt_onomies_manager->is_registered_cpt_onomy( $result->post_type ) ) {
			
				// Save count and remove from term for conversion
				$term_count = $result->count;
				unset( $result->count );
				
				// Convert term
				// Dont get count, we'll add it back
				$term = $this->convert_object_to_cpt_onomy_term( $result, false );
				
				if ( ! $term ) {
					return 0;
				}
				
				// Add count
				$term->count = $term_count;
				
				return $term;
				
			}
						
		} else {
						
			// Check for parent
			$parent = (int) $parent;
			if ( $parent > 0 ) {
				$parent = ' AND post_parent = ' . $parent;
			} else {
				$parent = null;
			}
			
			// Check for name first
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->posts . " WHERE post_title = %s" . $parent . " AND post_status = 'publish'", $term ) );
			
			// Check for slug
			if ( empty( $result ) ) {
				$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->posts . " WHERE post_name = %s" . $parent . " AND post_status = 'publish'", $term ) );
			}
			
			if ( ! empty( $result ) && $cpt_onomies_manager->is_registered_cpt_onomy( $result->post_type ) ) {
				return $this->convert_object_to_cpt_onomy_term( $result );
			}
			
		}
		
		return 0;
	}
	
	/**
	 * This function mimics the WordPress function get_term_link()
	 * because we cannot hook into the function without receiving errors
	 * and returning an incorrect link due to the rewrite war between
	 * custom post types and taxonomies AND because we create our own
	 * rewrite rules.
	 *
	 * @since 1.0
	 * @uses $wp_rewrite, $cpt_onomies_manager
	 * @param object|int|string $term
	 * @param string $taxonomy (optional if $term is object)
	 * @return string|WP_Error HTML link to taxonomy term archive on success, WP_Error if term does not exist.
	 */
	public function get_term_link( $term, $taxonomy ) {
		global $wp_rewrite, $cpt_onomies_manager;
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return get_term_link( $term, $taxonomy );
		}
			
		if ( ! is_object( $term ) ) {
			if ( is_int( $term ) ) {
				$term = $this->get_term( $term, $taxonomy );
			} else {
				$term = $this->get_term_by( 'slug', $term, $taxonomy );
			}
		}
	
		if ( ! is_object( $term ) ) {
			$term = new WP_Error( 'invalid_term', __( 'Empty Term', 'cpt-onomies' ) );
		}
	
		if ( is_wp_error( $term ) ) {
			return $term;
		}
			
		$taxonomy = $term->taxonomy;
	
		$termlink = NULL;
		$slug = $term->slug;
		$t = get_taxonomy( $taxonomy );		
		
		// Link to CPT-onomy archive page
		if ( isset( $t->cpt_onomy_archive_slug ) && ! empty( $t->cpt_onomy_archive_slug ) ) {
			
			$termlink = $t->cpt_onomy_archive_slug;
			
			if ( $t->hierarchical ) {
				$hierarchical_slugs = array();
				$ancestors = get_ancestors( $term->term_id, $taxonomy );
				foreach ( (array)$ancestors as $ancestor ) {
					$ancestor_term = $this->get_term( $ancestor, $taxonomy );
					$hierarchical_slugs[] = $ancestor_term->slug;
				}
				$hierarchical_slugs = array_reverse( $hierarchical_slugs );
				$hierarchical_slugs[] = $slug;

				// Replace the variables ($post_type and $term)
				$slug = implode( '/', $hierarchical_slugs );
			
			}
							
			// Replace the variables ($post_type and $term)
			$termlink = str_replace( array( '$post_type', '$term_slug', '$term_id' ), array( $taxonomy, $slug, $term->term_id ), $termlink );
				
			$termlink = home_url( user_trailingslashit( $termlink, 'category' ) );
				
		}
			
		// If no archive page, link to CPT post
		else {
			$termlink = get_permalink( $term->term_id );
		}
		
		// Back Compat filters.
		if ( 'post_tag' == $taxonomy ) {
			$termlink = apply_filters( 'tag_link', $termlink, $term->term_id );
		} else if ( 'category' == $taxonomy ) {
			$termlink = apply_filters( 'category_link', $termlink, $term->term_id );
		}
	
		return apply_filters( 'term_link', $termlink, $term, $taxonomy );
		
	}
	
	/**
	 * This function mimics the WordPress function get_edit_term_link()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager
	 * @param   int $term_id Term ID
	 * @param   string $taxonomy Taxonomy
	 * @param   string $object_type The object type
	 * @return  string
	 */
	public function get_edit_term_link( $term_id, $taxonomy, $object_type = '' ) {
		global $cpt_onomies_manager; 
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return get_edit_term_link( $term_id, $taxonomy, $object_type );
		}
			
		$post_type = get_post_type_object( $taxonomy );
		if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
			return;
		}
	
		$term = $this->get_term( $term_id, $taxonomy );
		
		if ( ! $term ) {
			return;
		}
	
		$args = array(
			'post' => $term->term_id,
			'action' => 'edit'
		);
	
		if ( $object_type ) {
			$args['post_type'] = $object_type;
		}
	
		$location = add_query_arg( $args, admin_url( 'post.php' ) );
	
		return apply_filters( 'get_edit_term_link', $location, $term_id, $taxonomy, $object_type );
		
	}
	
	/**
	 * This function mimics the WordPress function previous_post_link()
	 * because we cannot use that function properly.
	 *
	 * In the WordPress function, previous_post_link(), you are only allowed
	 * to use 'category' for your taxonomy but this function adds a new parameter that 
	 * allows you to designate which CPT-onomy you would like to use.
	 *
	 * @since 1.0.2
	 * @uses $cpt_onomies_manager
	 * @param string $format Optional. Link anchor format.
	 * @param string $link Optional. Link permalink format.
	 * @param bool $in_same_cpt_onomy Optional. Whether link should be in a same CPT-onomy.
	 * @param array|string $excluded_term_ids Optional. Array or comma-separated list of excluded term IDs.
	 * @param string $cpt_onomy - name of the CPT-onomy for $in_same_cpt_onomy
	 */
	function previous_post_link( $format='&laquo; %link', $link='%title', $in_same_cpt_onomy = false, $excluded_term_ids = '', $cpt_onomy = '' ) {
		global $cpt_onomies_manager;

		if ( empty( $format ) ) {
			$format = '&laquo; %link';
		}

		if ( empty( $cpt_onomy ) || ! $cpt_onomies_manager->is_registered_cpt_onomy( $cpt_onomy ) ) {
			previous_post_link( $format, $link, $in_same_cpt_onomy, $excluded_term_ids );
		} else {
			$this->adjacent_post_link( $format, $link, $in_same_cpt_onomy, $excluded_term_ids, true, $cpt_onomy );
		}

	}
	
	/**
	 * This function mimics the WordPress function next_post_link()
	 * because we cannot use that function properly.
	 *
	 * In the WordPress function, next_post_link(), you are only allowed
	 * to use 'category' for your taxonomy but this function adds a new parameter that 
	 * allows you to designate which CPT-onomy you would like to use.
	 *
	 * @since 1.0.2
	 * @uses $cpt_onomies_manager
	 * @param string $format Optional. Link anchor format.
	 * @param string $link Optional. Link permalink format.
	 * @param bool $in_same_cpt_onomy Optional. Whether link should be in a same CPT-onomy.
	 * @param array|string $excluded_term_ids Optional. Array or comma-separated list of excluded term IDs.
	 * @param string $cpt_onomy - name of the CPT-onomy for $in_same_cpt_onomy
	 */
	function next_post_link( $format='%link &raquo;', $link='%title', $in_same_cpt_onomy = false, $excluded_term_ids = '', $cpt_onomy = '' ) {
		global $cpt_onomies_manager;
		
		// Make sure we have a format
		if ( empty( $format ) ) {
			$format = '%link &raquo;';
		}
		
		// If it's empty or not a valid CPT-onomy, then run the default WordPress function
		if ( empty( $cpt_onomy ) || ! $cpt_onomies_manager->is_registered_cpt_onomy( $cpt_onomy ) ) {
			next_post_link( $format, $link, $in_same_cpt_onomy, $excluded_term_ids );
		} else {
			$this->adjacent_post_link( $format, $link, $in_same_cpt_onomy, $excluded_term_ids, false, $cpt_onomy );
		}
		
	}
	
	/**
	 * This function mimics the WordPress function adjacent_post_link()
	 * because we cannot use that function properly.
	 *
	 * In the WordPress function, adjacent_post_link(), you are only allowed
	 * to use 'category' for your taxonomy but this function adds a new parameter that 
	 * allows you to designate which CPT-onomy you would like to use.
	 *
	 * @since   1.0.2
	 * @uses    $cpt_onomies_manager
	 * @param   string $format Link anchor format.
	 * @param   string $link Link permalink format.
	 * @param   bool $in_same_cpt_onomy Optional. Whether link should be in a same CPT-onomy.
	 * @param   array|string $excluded_term_ids Optional. Array or comma-separated list of excluded term IDs.
	 * @param   bool $previous Optional, default is true. Whether to display link to previous or next post.
	 * @param   string $cpt_onomy - name of the CPT-onomy for $in_same_cpt_onomy
	 */
	function adjacent_post_link( $format, $link, $in_same_cpt_onomy = false, $excluded_term_ids = '', $previous = true, $cpt_onomy = '' ) {
		global $cpt_onomies_manager;
		
		// If it's empty or not a valid CPT-onomy, then run the default WordPress function
		if ( empty( $cpt_onomy ) || ! $cpt_onomies_manager->is_registered_cpt_onomy( $cpt_onomy ) ) {
			
			adjacent_post_link( $format, $link, $in_same_cpt_onomy, $excluded_term_ids, $previous );
			
		} else {
			
			if ( $previous && is_attachment() ) {
				$post = &get_post( $GLOBALS['post']->post_parent );
			} else {
				$post = $this->get_adjacent_post( $in_same_cpt_onomy, $excluded_term_ids, $previous, $cpt_onomy );
			}
			
			if ( ! $post ) {
				return;
			}
		
			$title = $post->post_title;
		
			if ( empty( $post->post_title ) ) {
				$title = $previous ? __( 'Previous Post', 'cpt-onomies' ) : __( 'Next Post', 'cpt-onomies' );
			}
		
			$title = apply_filters( 'the_title', $title, $post->ID );
			$date = mysql2date( get_option( 'date_format' ), $post->post_date );
			$rel = $previous ? 'prev' : 'next';
		
			$string = '<a href="'.get_permalink($post).'" rel="'.$rel.'">';
			$link = str_replace('%title', $title, $link);
			$link = str_replace('%date', $date, $link);
			$link = $string . $link . '</a>';
		
			$format = str_replace('%link', $link, $format);
		
			$adjacent = $previous ? 'previous' : 'next';
			
			echo apply_filters( "{$adjacent}_post_link", $format, $link );
			
		}
	}
	
	/**
	 * This function mimics the WordPress function prev_post_rel_link()
	 * because we cannot use that function properly.
	 *
	 * In the WordPress function, prev_post_rel_link(), you are only allowed
	 * to use 'category' for your taxonomy but this function adds a new parameter that 
	 * allows you to designate which CPT-onomy you would like to use.
	 *
	 * @since 1.0.2
	 * @uses $cpt_onomies_manager
	 * @param string $title Optional. Link title format.
	 * @param bool $in_same_cpt_onomy Optional. Whether link should be in a same CPT-onomy.
	 * @param array|string $excluded_term_ids Optional. Array or comma-separated list of excluded term IDs.
	 * @param string $cpt_onomy - name of the CPT-onomy for $in_same_cpt_onomy
	 */
	function prev_post_rel_link( $title = '%title', $in_same_cpt_onomy = false, $excluded_term_ids = '', $cpt_onomy = '' ) {
		global $cpt_onomies_manager;
		if ( empty( $cpt_onomy ) || ! $cpt_onomies_manager->is_registered_cpt_onomy( $cpt_onomy ) ) {
			prev_post_rel_link( $title, $in_same_cpt_onomy, $excluded_term_ids );
		}
		echo $this->get_adjacent_post_rel_link( $title, $in_same_cpt_onomy, $excluded_term_ids, true, $cpt_onomy );
	}
	
	/**
	 * This function mimics the WordPress function next_post_rel_link()
	 * because we cannot use that function properly.
	 *
	 * In the WordPress function, next_post_rel_link(), you are only allowed
	 * to use 'category' for your taxonomy but this function adds a new parameter that 
	 * allows you to designate which CPT-onomy you would like to use.
	 *
	 * @since 1.0.2
	 * @uses $cpt_onomies_manager
	 * @param string $title Optional. Link title format.
	 * @param bool $in_same_cpt_onomy Optional. Whether link should be in a same CPT-onomy.
	 * @param array|string $excluded_term_ids Optional. Array or comma-separated list of excluded term IDs.
	 * @param string $cpt_onomy - name of the CPT-onomy for $in_same_cpt_onomy
	 */
	function next_post_rel_link( $title = '%title', $in_same_cpt_onomy = false, $excluded_term_ids = '', $cpt_onomy = '' ) {
		global $cpt_onomies_manager;
		if ( empty( $cpt_onomy ) || ! $cpt_onomies_manager->is_registered_cpt_onomy( $cpt_onomy ) ) {
			next_post_rel_link( $title, $in_same_cpt_onomy, $excluded_term_ids );
		} else {
			echo $this->get_adjacent_post_rel_link( $title, $in_same_cpt_onomy, $excluded_term_ids, false, $cpt_onomy );
		}
	}
	
	/**
	 * This function mimics the WordPress function get_adjacent_post_rel_link()
	 * because we cannot use that function properly.
	 *
	 * In the WordPress function, get_adjacent_post_rel_link(), you are only allowed
	 * to use 'category' for your taxonomy but this function adds a new parameter that 
	 * allows you to designate which CPT-onomy you would like to use.
	 *
	 * @since 1.0.2
	 * @uses $cpt_onomies_manager
	 * @param string $title Optional. Link title format.
	 * @param bool $in_same_cpt_onomy Optional. Whether link should be in a same CPT-onomy.
	 * @param array|string  $excluded_term_ids Optional. Array or comma-separated list of excluded term IDs.
	 * @param bool $previous Optional, default is true. Whether to display link to previous or next post.
	 * @param string $cpt_onomy - name of the CPT-onomy for $in_same_cpt_onomy
	 * @return string
	 */
	function get_adjacent_post_rel_link( $title = '%title', $in_same_cpt_onomy = false, $excluded_term_ids = '', $previous = true, $cpt_onomy = '' ) {
		global $cpt_onomies_manager;
				
		if ( empty( $cpt_onomy ) || ! $cpt_onomies_manager->is_registered_cpt_onomy( $cpt_onomy ) ) {
			return get_adjacent_post_rel_link( $title, $in_same_cpt_onomy, $excluded_term_ids, $previous );
		}
			
		if ( $previous && is_attachment() && is_object( $GLOBALS[ 'post' ] ) ) {
			$post = &get_post( $GLOBALS['post']->post_parent );
		} else {
			$post = $this->get_adjacent_post( $in_same_cpt_onomy, $excluded_term_ids, $previous, $cpt_onomy );
		}
	
		if ( empty( $post ) ) {
			return;
		}
	
		if ( empty( $post->post_title ) ) {
			$post->post_title = $previous ? __( 'Previous Post', 'cpt-onomies' ) : __( 'Next Post', 'cpt-onomies' );
		}
	
		$date = mysql2date(get_option('date_format'), $post->post_date);
	
		$title = str_replace( '%title', $post->post_title, $title );
		$title = str_replace( '%date', $date, $title );
		$title = apply_filters( 'the_title', $title, $post->ID );
	
		$link = $previous ? "<link rel='prev' title='" : "<link rel='next' title='";
		$link .= esc_attr( $title );
		$link .= "' href='" . get_permalink($post) . "' />\n";
	
		$adjacent = $previous ? 'previous' : 'next';
		return apply_filters( "{$adjacent}_post_rel_link", $link );
	}
	
	/**
	 * This function mimics the WordPress function get_adjacent_post()
	 * because we cannot use that function properly.
	 *
	 * In the WordPress function, get_adjacent_post(), you are only allowed
	 * to use 'category' for your taxonomy but this function adds a new parameter that 
	 * allows you to designate which CPT-onomy you would like to use.
	 *
	 * @since   1.0.2
	 * @uses    $post, $wpdb, $cpt_onomies_manager
	 * @param   bool $in_same_cpt_onomy Optional. Whether post should be in a same CPT-onomy.
	 * @param   array|string $excluded_term_ids Optional. Array or comma-separated list of excluded term IDs.
	 * @param   bool $previous Optional. Whether to retrieve previous post.
	 * @param   string $cpt_onomy - name of the CPT-onomy for $in_same_cpt_onomy
	 * @return  mixed Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
	 */
	function get_adjacent_post( $in_same_cpt_onomy = false, $excluded_term_ids = '', $previous = true, $cpt_onomy = '' ) {
		global $post, $wpdb, $cpt_onomies_manager;
	
		if ( empty( $post ) ) {
			return null;
		}
			
		if ( empty( $cpt_onomy ) || ! $cpt_onomies_manager->is_registered_cpt_onomy( $cpt_onomy ) ) {
			return get_adjacent_post( $in_same_cpt_onomy, $excluded_term_ids, $previous );
		}
	
		$current_post_date = $post->post_date;

		$join = '';
		$posts_in_ex_terms_sql = '';
		
		if ( $in_same_cpt_onomy || ! empty( $excluded_term_ids ) ) {
			
			$join = " INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key ='" . CPT_ONOMIES_POSTMETA_KEY . "' INNER JOIN {$wpdb->posts} AS p2 ON pm.meta_value = p2.ID AND p2.post_type = '" . $cpt_onomy . "'";
			
			if ( $in_same_cpt_onomy ) {
				$join .= " AND pm.meta_value IN ( '" . implode( "','", wp_get_object_terms( $post->ID, $cpt_onomy, array( 'fields' => 'ids' ) ) ) . "' )";
			}
	
			if ( ! empty( $excluded_term_ids ) ) {
				
				if ( ! is_array( $excluded_term_ids ) ) {

					// Back-compat, $excluded_term_ids used to be IDs separated by " and "
					if ( strpos( $excluded_term_ids, ' and ' ) !== false ) {
						_deprecated_argument( __FUNCTION__, '3.3', sprintf( __( 'Use commas instead of %s to separate excluded categories.', 'cpt-onomies' ), "'and'" ) );
						$excluded_term_ids = explode( ' and ', $excluded_term_ids );
					} else {
						$excluded_term_ids = explode( ',', $excluded_term_ids );
					}

				}
	
				$excluded_term_ids = array_map( 'intval', $excluded_term_ids );
	
				if ( ! empty( $excluded_term_ids ) ) {
					$posts_in_ex_terms_sql = " AND ( SELECT COUNT(*) FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.ID AND pm2.meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "' AND pm2.meta_value NOT IN (" . implode(',', $excluded_term_ids) . ") ) = ( SELECT COUNT(*) FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.ID AND pm.meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "' )";
				}
			
			}
			
		}
	
		$adjacent = $previous ? 'previous' : 'next';
	  	$op = $previous ? '<' : '>';
	  	$order = $previous ? 'DESC' : 'ASC';
	  
	  	$join  = apply_filters( "get_{$adjacent}_post_join", $join, $in_same_cpt_onomy, $excluded_term_ids );
	  	$where = apply_filters( "get_{$adjacent}_post_where", $wpdb->prepare("WHERE p.post_date $op %s AND p.post_type = %s AND p.post_status = 'publish' $posts_in_ex_terms_sql", $current_post_date, $post->post_type), $in_same_cpt_onomy, $excluded_term_ids );
	  	$sort  = apply_filters( "get_{$adjacent}_post_sort", "ORDER BY p.post_date $order LIMIT 1" );
	  
	  	$query = "SELECT p.* FROM $wpdb->posts AS p $join $where $sort";
	  	$query_key = 'adjacent_post_' . md5($query);
	  	$result = wp_cache_get($query_key, 'counts');

		if ( false !== $result ) {
			return $result;
		}
	  
	  	$result = $wpdb->get_row( "SELECT p.* FROM $wpdb->posts AS p $join $where $sort" );
	  	if ( null === $result ) {
		    $result = '';
	    }
	  
	  	wp_cache_set($query_key, $result, 'counts');
	  	
	  	return $result;
	  	
	}
	
	/**
	 * This function mimics the WordPress function get_the_term_list()
	 * because we cannot hook into the function because get_the_term_list()
	 * uses get_term_link() which is also incompatible with CPT-onomies at this time.
	 *
	 * @since 1.0
	 * @uses $cpt_onomies_manager
	 * @param int $id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $before Optional. Before list.
	 * @param string $sep Optional. Separate items using this.
	 * @param string $after Optional. After list.
	 * @return string
	 */
	public function get_the_term_list( $id = 0, $taxonomy, $before = '', $sep = '', $after = '' ) {
		global $cpt_onomies_manager;
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return get_the_term_list( $id, $taxonomy, $before, $sep, $after );
		}
			
		$terms = get_the_terms( $id, $taxonomy );
	
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
	
		if ( empty( $terms ) ) {
			return false;
		}
	
		foreach ( $terms as $term ) {
			$link = $this->get_term_link( $term, $taxonomy );
			if ( is_wp_error( $link ) ) {
				return $link;
			}
			$term_links[] = '<a href="' . $link . '" rel="tag">' . $term->name . '</a>';
		}
		
		$term_links = apply_filters( "term_links-$taxonomy", $term_links );
	
		return $before . join( $sep, $term_links ) . $after;
	}
	
	/**
	 * This function mimics the WordPress function the_terms()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * @since   1.0
	 * @uses    $cpt_onomies_manager
	 * @param   int $id Post ID.
	 * @param   string $taxonomy Taxonomy name.
	 * @param   string $before Optional. Before list.
	 * @param   string $sep Optional. Separate items using this.
	 * @param   string $after Optional. After list.
	 * @return  null|bool False on WordPress error. Returns null when displaying.
	 */
	public function the_terms( $id = 0, $taxonomy, $before = '', $sep = ', ', $after = '' ) {
		global $cpt_onomies_manager;
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return the_terms( $id, $taxonomy, $before, $sep, $after );
		}
			
		$term_list = $this->get_the_term_list( $id, $taxonomy, $before, $sep, $after );
	
		if ( is_wp_error( $term_list ) ) {
			return false;
		}
	
		echo apply_filters( 'the_terms', $term_list, $taxonomy, $before, $sep, $after );
		
	}
	
	/**
	 * This function hooks into WordPress get_terms() and allows the plugin
	 * to change what terms are retrieved. This function is invoked in numerous places
	 * and is called whenever the WordPress function get_terms() is used.
	 * 
	 * This function is applied to the filter 'get_terms'.
	 *
	 * Do not call this function on its own, instead use the core WordPress function: get_terms().
	 *
	 * @since 1.0
	 * @uses $cpt_onomies_manager, $current_screen, $post
	 * @param array $terms - the terms created by WordPress get_terms() that we will now filter
	 * @param string|array $taxonomies - the taxonomies we're pulling terms from
	 * @param array $args - arguments used to customize which terms are returned (!! means its not supported... yet)
	 		'orderby'		Default is 'name'. Can be name, count, term_group, slug or nothing
							(will use term_id), Passing a custom value other than these will cause 
							it to order based on the custom value.
			'order'			Default is ASC. Can use DESC.
			'hide_empty'	Default is true. Will not return empty terms, which means terms whose 
							count is 0 according to the given taxonomy.
			'exclude'		Default is an empty array. An array, comma- or space-delimited string 
							of term ids to exclude from the return array. If 'include' is non-empty, 
							'exclude' is ignored.
		!!	'exclude_tree' 	Default is an empty array. An array, comma- or space-delimited string 
							of term ids to exclude from the return array, along with all of their 
							descendant terms according to the primary taxonomy. If 'include' is non-empty,
							'exclude_tree' is ignored.
			'include'		Default is an empty array.  An array, comma- or space-delimited string of 
							term ids to include in the return array.
			'number'		The maximum number of terms to return. Default is to return them all.
			'offset'		The number by which to offset the terms query. Default is 0.
			'fields'		Default is 'all', which returns an array of term objects. If 'fields' is 
							'ids' or 'names', returns an array of integers or strings, respectively.
			'slug'			Returns terms whose "slug" matches this value. Default is empty string.
		!!	'hierarchical'	Default is true. Whether to include terms that have non-empty descendants (even if 'hide_empty' is set to true).
			'search'		Returned terms' names will contain the value of 'search', case-insensitive. Default is an empty string.
		!!	'name__like'	Returned terms' names will begin with the value of 'name__like', case-insensitive. Default is empty string.
			'pad_counts'	Default is false. If set to true will include the quantity of a term's children in the quantity of each term's "count" object variable.
		!!	'get'			If set to 'all' instead of its default empty string, returns terms regardless of ancestry or whether the terms are empty.
		!!	'child_of'		When used should be set to the integer of a term ID. Its default is 0. If set to a non-zero value, all returned terms 
							will be descendants of that term according to the given taxonomy.  Hence 'child_of' is set to 0 if more than one taxonomy 
							is passed in $taxonomies, because multiple taxonomies make term ancestry ambiguous.
		!!	'parent'		When used should be set to the integer of a term ID. Its default is the empty string '', which has a different meaning 
							from the integer 0. If set to an integer value, all returned terms will have as an immediate ancestor the term whose ID is 
							specified by that integer according to the given taxonomy. The 'parent' argument is different from 'child_of' in that a 
							term X is considered a 'parent' of term Y only if term X is the father of term Y, not its grandfather or great-grandfather, etc.
	 * @return array - the terms after they have been filtered
	 */
	public function get_terms( $terms, $taxonomies, $args ) {
		global $cpt_onomies_manager, $current_screen, $post, $wpdb;
									
		// If taxonomy name is string, convert to array
		if ( ! is_array( $taxonomies ) ) {
			$taxonomies = array( $taxonomies );
		}
		
		// This function only filters registered CPT-onomies
		$cpt_taxonomies = array();
		foreach( $taxonomies as $taxonomy ) {
			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
				$cpt_taxonomies[] = $taxonomy;
			}
		}

		// This means there are no CPT-onomies so wrap things up
		if ( empty( $cpt_taxonomies ) ) {
			return $terms;
		}
		
		/**
		 * Since these parameters are not included in get_terms(),
		 * we have to make sure they're included in our filter.
		 */
		$defaults = array( 'show_count' => false );
		$args = wp_parse_args( $args, $defaults );
		
		/**
		 * Since 'fields' = 'count' will cause get_terms to 'return'
		 * before we can filter, the adjust_get_terms_args() filter
		 * changes 'count' to 'ids' and adds a custom count argument.
		 * This is included here as a backup.
		 */
		if ( isset( $args[ 'fields' ] ) && $args[ 'fields' ] == 'count' ) {
			$args[ 'fields' ] = 'ids';
			$args[ 'cpt_onomy_get_count' ] = true;
		}
			
		extract( $args, EXTR_SKIP );
				
		// Fix arguments for get_posts vs. get_terms
				
		// WordPress supported orderby - 'count', 'name', 'slug', 'none', 'id' - (still need to add support for 'term_group')
		if ( strtolower( $orderby ) == 'none' || strtolower( $orderby ) == 'id' ) {
			$orderby = 'id';
		} else if ( ! in_array( strtolower( $orderby ), array( 'count', 'slug', 'term_group' ) ) ) {
			$orderby = 'title'; //Default is 'name'/'title'
		}
		
		// WordPress supported order - 'asc' and 'desc' (default is asc)
		$order = ( isset( $order ) && ( in_array( strtolower( $order ), array( 'asc', 'desc' ) ) ) ) ? strtolower( $order ) : 'asc';
		
		// Default is -1
		$numberposts = ( isset( $number ) && is_numeric( $number ) && ( $number > 0 ) ) ? $number : -1;
		
		// Default is 0
		$offset = ( isset( $offset ) && is_numeric( $offset ) && ( $offset > 0 ) ) ? $offset : 0;
		
		// WordPress supported fields - 'all', 'ids', 'names' (default is all)
		if ( in_array( strtolower( $fields ), array( 'ids', 'names', 'id=>parent' ) ) ) {
			$fields = strtolower( $fields );
		} else {
			$fields = 'all';
		}
		
		/**
		 * Clear out any existing terms and start over.
		 * This is helpful if, somehow, some actual terms got assigned to the taxonomy.
		 */
		if ( ! empty( $terms ) && $fields != 'id=>parent' ) {
			$new_terms = array();
			foreach( $terms as $term ) {
				if ( ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, $cpt_taxonomies ) ) {
					$new_terms[] = $term;
				}
			}
			$terms = $new_terms;
		}
										
		// Get terms for each CPT-onomy
		foreach( $cpt_taxonomies as $taxonomy ) {
			
			// Get the custom post type posts (which are the terms)
			$cpt_posts = get_posts( array(
				'get_cpt_onomy_terms'   => true,
				'suppress_filters'      => true,
				'post_type'             => $taxonomy,
				'post_status'           => 'publish',
				'orderby'               => $orderby,
				'order'                 => $order,
				'numberposts'           => ( in_array( $orderby, array( 'id', 'title' ) ) ) ? $numberposts : -1, // We'll need all posts for other parameters for later sorting
				'exclude'               => $exclude,
				'include'               => $include,
				'name'                  => $slug,
				's'                     => $search
			) );
			
			// If we have posts, then we have terms
			if ( ! empty( $cpt_posts ) ) {
				
				// We don't want to show the current "term" if on the edit post screen in the admin
				$current = ( is_admin() && $current_screen && $current_screen->base == 'post' && $current_screen->parent_base == 'edit' && $current_screen->post_type == $taxonomy && isset( $post->ID ) ) ? $post->ID : NULL;
				
				// Store the count for the CPT-onomy's terms
				$cpt_posts_count = $this->get_terms_count( $taxonomy );
				
				// Loop through the posts and setup the terms
				foreach ( $cpt_posts as $this_post ) {
					
					// Dont show current "term"
					if ( empty( $current ) || ( ! empty( $current ) && $current != $this_post->ID ) ) {
						
						// Dont get count when converting because we'll set it next
						$this_term = $this->convert_object_to_cpt_onomy_term( $this_post, false );
						
						// If no term, then continue to next post
						if ( ! $this_term ) {
							continue;
						}
						
						// Assign count
						$this_term->count = isset( $cpt_posts_count[ $this_post->ID ] ) && $cpt_posts_count[ $this_post->ID ] > 0 ? $cpt_posts_count[ $this_post->ID ] : 0;
						
						if ( ! $hide_empty || ( $hide_empty && isset( $this_term->count ) && $this_term->count > 0 ) ) {
							
							switch( $fields ) {
								case 'ids':
									$this_term = $this_term->term_id;
									break;
								case 'names':
									$this_term = $this_term->name;
									break;
							}
							
							// 'id=>parent' is a beast all its own
							if ( $fields == 'id=>parent' ) {
								$terms[ $this_term->term_id ] = $this_term->parent;
							} else {
								$terms[] = $this_term;
							}
						
						}
						
					}
				
				}
				
			}
			
		}
		
		/**
		 * They just want the count.
		 * This argument is defined in $this->adjust_get_terms_args().
		 */
		if ( isset( $cpt_onomy_get_count ) && $cpt_onomy_get_count ) {
			return count( $terms );
		}
		
		/**
		 * If true, we have to do manual sorting.
		 * if false, it's already taken care of.
		 */
		$manual_sort = false;
		
		// This means we have a mixture of taxonomies and CPT-onomies
		if ( ! empty( $cpt_taxonomies ) && ( count( $taxonomies ) > 1 || $taxonomies != $cpt_taxonomies ) ) {
			$manual_sort = true;
		}

		// We have to manual sort certain $orderby parameters because they do not work in get_posts()
		else if ( in_array( $orderby, array( 'count', 'slug', 'term_group' ) ) ) {
			$manual_sort = true;
		}
				
		// 'id=>parent' is a beast all its own
		if ( $manual_sort && $fields != 'id=>parent' ) {
					
			/**
			 * Sort orderby.
			 * If 'ids' or 'names', then we have a simpler sort.
			 */
			if ( in_array( $fields, array( 'ids', 'names' ) ) ) {
				
				natcasesort( $terms );
			
			} else {

				switch( $orderby ) {
					case 'id':
						usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_term_id' );
						break;
					case 'name':
					case 'title':
						usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_name' );
						break;
					case 'count':
						usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_count' );
						break;
					case 'slug':
						usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_slug' );
						break;
					case 'term_group':
						break;
				}

			}
			
			// Sort order
			if ( strtolower( $order ) == 'desc' ) {
				$terms = array_reverse( $terms );
			}
			
		}
		
		// Offset
		if ( $offset > 0 ) {
			$terms = array_slice( $terms, $offset );
		}
			
		/**
		 * Number of posts.
		 * Don't limit when hierarchical.
		 */
		if ( $numberposts > 0 ) {
			$terms = array_slice( $terms, 0, $numberposts );
		}
		
		return $terms;
		
	}
	
	/**
	 * This function mimics the WordPress function get_objects_in_term()
	 * because we cannot use that function properly.
	 *
	 * @since   1.0.2
	 * @uses    $wpdb, $cpt_onomies_manager
	 * @param   int|array $term_ids Term id or array of term ids of terms that will be used
	 * @param   string|array $taxonomies String of taxonomy name or Array of string values of taxonomy names
	 * @param   array|string $args Change the order of the object_ids, either ASC or DESC
	 * @return  WP_Error|array If the taxonomy does not exist, then WP_Error will be returned. On success
	 *	the array can be empty meaning that there are no $object_ids found or it will return the $object_ids found.
	 */
	function get_objects_in_term( $term_ids, $taxonomies, $args = array() ) {
		global $wpdb, $cpt_onomies_manager;
		
		if ( ! is_array( $term_ids ) ) {
			$term_ids = array( $term_ids );
		}
	
		if ( ! is_array( $taxonomies ) ) {
			$taxonomies = array( $taxonomies );
		}
	
		// This function only filters registered CPT-onomies
		$cpt_taxonomies = array();
		foreach( $taxonomies as $taxonomy ) {
			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
				$cpt_taxonomies[] = $taxonomy;
			}
		}

		// This means there are no CPT-onomies so wrap things up
		if ( empty( $cpt_taxonomies ) ) {
			return get_objects_in_term( $term_ids, $taxonomies, $args );
		} else {
			$taxonomies = $cpt_taxonomies;
		}
	
		$defaults = array( 'order' => 'ASC' );
		$args = wp_parse_args( $args, $defaults );
		extract( $args, EXTR_SKIP );
					
		$order = ( 'desc' == strtolower( $order ) ) ? 'DESC' : 'ASC';
	
		$term_ids = array_map( 'intval', $term_ids );
		
		$object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT {$wpdb->postmeta}.post_id FROM {$wpdb->postmeta} INNER JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.meta_value AND {$wpdb->posts}.post_type IN ('" . implode( "','", $taxonomies ) . "') WHERE {$wpdb->postmeta}.meta_key = %s AND {$wpdb->postmeta}.meta_value IN ('" . implode( "','", $term_ids ) . "') ORDER BY {$wpdb->postmeta}.post_id " . $order, CPT_ONOMIES_POSTMETA_KEY ) );
	
		if ( ! $object_ids ) {
			return array();
		}
	
		return $object_ids;
		
	}
	
	 /**
	  * This function hooks into WordPress get_object_terms() and allows the plugin
	  * to change what terms are retrieved for a particular object. This function is invoked
	  * in numerous places and is called whenever the WordPress function wp_get_object_terms() is used.
	  *
	  * Retrieves the terms associated with the given object(s), in the supplied taxonomies.
	  *
	  * This function is applied to the filter 'get_object_terms'.
	  * Used to be applied to 'wp_get_object_terms' filter. Updated in v1.3.5.
	  *
	  * Do not call this function on its own, instead use the core WordPress function: wp_get_object_terms().
	  *
	  * Version 1.1 added the ability to designate terms ids to 'exclude' in the $args array.
	  * Will only work if 'fields' is set to 'ids', 'all' or 'all_with_object_id'.
	  * 		'exclude' - (array) Default is an empty array. An array, comma- or space-delimited string
	  * 		of term ids to exclude in the return array.
	  *
	  * @since  1.0
	  * @uses   $wpdb, $cpt_onomies_manager
	  * @param  array $terms - An array of terms for the given object or objects.
	  * @param  array $object_id_array - Array of object IDs for which `$terms` were retrieved.
	  * @param  array $taxonomy_array - Array of taxonomies from which `$terms` were retrieved.
	  * @param  array $args - An array of arguments for retrieving terms for the given
	  *             object(s). See wp_get_object_terms() for details.
	  * @return array|WP_Error - The requested term data or empty array, f no terms found.
	  *             WP_Error if $taxonomy does not exist.
	  */
	function get_object_terms( $terms, $object_ids, $taxonomies, $args = array() ) {
		global $wpdb, $cpt_onomies_manager;
		
		/**
		 * When bulk edit, we don't want to return CPT-onomy terms
		 * because bulk edit will add them as regular taxonomy information.
		 */
		if ( isset( $_REQUEST[ 'is_bulk_quick_edit' ] ) && isset( $_REQUEST[ 'bulk_edit' ] ) && 'Update' == $_REQUEST[ 'bulk_edit' ] ) {
			return $terms;
		}
		
		/**
		 * Does not support $fields = 'tt_ids' since our CPT-onomies
		 * are not actual taxonomies and dont have taxonomy term ids.
		 */
		if ( 'tt_ids' == $args[ 'fields' ] ) {
			return $terms;
		}

		// This allows for a string with one object id or an array with multiple object ids
		if ( ! is_array( $object_ids ) ) {
			$object_ids = explode( ',', str_replace( ' ', '', $object_ids ) );
		}
		$object_ids = array_map( 'intval', $object_ids );

		// Make sure we have object IDs and taxonomies
		if ( empty( $object_ids ) || empty( $taxonomies ) ) {
			return $terms;
		}
			
		// If taxonomy name is string, convert to array
		if ( ! is_array( $taxonomies ) ) {
			$taxonomies = array( $taxonomies );
		}
				
		// This function only filters registered CPT-onomies
		$cpt_taxonomies = array();
		foreach( $taxonomies as $taxonomy ) {
			if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
				$cpt_taxonomies[] = $taxonomy;
			}
		}

		// This means there are no CPT-onomies so wrap things up
		if ( empty( $cpt_taxonomies ) ) {
			return $terms;
		}

		// Designate the defaults
		$defaults = array(
			'orderby'   => 'name',
			'order'     => 'ASC',
			'fields'    => 'all',
			'parent'    => '',
			'update_term_meta_cache' => true,
			'meta_query'=> '',
		);

		// Parse arguments with the defaults
		$args = wp_parse_args( $args, $defaults );
		
		/**
		 * Clear out any existing terms and start over.
		 * This is helpful if, somehow, some actual terms got assigned to the taxonomy.
		 */
		if ( ! empty( $terms ) ) {
			$new_terms = array();
			foreach( $terms as $term ) {
				if ( ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, $cpt_taxonomies ) ) {
					$new_terms[] = $term;
				}
			}
			$terms = $new_terms;
		}

		// I'm not sure why this runs but is pulled from wp_get_object_terms()
		if ( count( $taxonomies ) > 1 ) {
			
			foreach ( $taxonomies as $index => $taxonomy ) {
				$t = get_taxonomy( $taxonomy );
				if ( isset( $t->args ) && is_array( $t->args ) && $args != array_merge( $args, $t->args ) ) {
					unset( $taxonomies[ $index ] );
					$terms = array_merge( $terms, wp_get_object_terms( $object_ids, $taxonomy, array_merge( $args, $t->args ) ) );
				}
			}
			
		} else {
			
			$t = get_taxonomy( $taxonomies[0] );
			if ( isset( $t->args ) && is_array( $t->args ) ) {
				$args = array_merge( $args, $t->args );
			}
		
		}
		
		// If there are terms to 'exclude', clean up the parameters
		$exclude = ( isset( $args[ 'exclude' ] ) ) ? wp_parse_id_list( $args[ 'exclude' ] ) : array();
									
		// Get the terms
		switch( $args['fields'] ) {
	
			case 'ids':

				// Get IDs
				$cpt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value 
					FROM {$wpdb->postmeta} wpmeta 
					INNER JOIN {$wpdb->posts} wpposts ON
						wpposts.ID = wpmeta.meta_value AND
						wpposts.post_type IN ('" . implode( "','", $cpt_taxonomies ) . "') AND
						wpposts.post_status = 'publish'
					WHERE wpmeta.post_id IN (" . implode( ',', $object_ids ) . ") AND wpmeta.meta_key = %s", CPT_ONOMIES_POSTMETA_KEY ) );

				// Process each ID
				foreach( $cpt_ids as $cpt_id ) {

					// Don't add if already stored OR if set in $exclude
					if ( ! in_array( $cpt_id, $terms ) && ! in_array( $cpt_id, $exclude ) ) {
						$terms[] = $cpt_id;
					}

				}
				break;
				
			case 'names':

				// Get the posts
				$cpt_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title
					FROM {$wpdb->posts} wpposts 
					INNER JOIN {$wpdb->postmeta} wpmeta ON
						wpmeta.meta_value = wpposts.ID AND
						wpmeta.meta_key = %s AND
						wpmeta.post_id IN (" . implode( ',', $object_ids ) . ")
					WHERE wpposts.post_type IN ('" . implode( "','", $cpt_taxonomies ) . "') AND wpposts.post_status = 'publish'", CPT_ONOMIES_POSTMETA_KEY ) );

				// Process each post
				foreach ( $cpt_posts as $this_post ) {

					$filtered_name = apply_filters( 'the_title', $this_post->post_title, $this_post->ID );
					if ( ! in_array( $filtered_name, $terms ) ) {
						$terms[] = $filtered_name;
					}

				}
				break;
				
			case 'slugs':

				// Get slugs
				$cpt_slugs = $wpdb->get_col( $wpdb->prepare( "SELECT post_name
					FROM {$wpdb->posts} wpposts 
					INNER JOIN {$wpdb->postmeta} wpmeta ON
						wpmeta.meta_value = wpposts.ID AND
						wpmeta.meta_key = %s AND
						wpmeta.post_id IN (" . implode( ',', $object_ids ) . ")
					WHERE wpposts.post_type IN ('" . implode( "','", $cpt_taxonomies ) . "') AND wpposts.post_status = 'publish'", CPT_ONOMIES_POSTMETA_KEY ) );

				// Process each slug
				foreach( $cpt_slugs as $cpt_slug ) {
					if ( ! in_array( $cpt_slug, $terms ) ) {
						$terms[] = $cpt_slug;
					}
				}

				break;
				
			case 'all_with_object_id':
			
				// Build the query
				// Get object ID, term count and post info
				$cpt_posts_query = "SELECT meta.post_id AS object_id, terms.*
					FROM {$wpdb->postmeta} meta
					INNER JOIN {$wpdb->posts} terms 
						ON terms.ID = meta.meta_value 
						AND terms.post_type IN ('" . implode( "','", $cpt_taxonomies ) . "')
						AND terms.post_status = 'publish'";
							
					// Exclude certain "terms"
					if ( ! empty( $exclude ) ) {
						$cpt_posts_query .= " AND terms.ID NOT IN ( " . implode( ',', $exclude ) . " )";
					}
							
				$cpt_posts_query .= " WHERE meta.meta_key = %s AND meta.post_id IN (" . implode( ',', $object_ids ) . ")";
				
				// Get the posts
				$cpt_posts = $wpdb->get_results( $wpdb->prepare( $cpt_posts_query, CPT_ONOMIES_POSTMETA_KEY ) );

				// If we have posts...
				if ( ! empty( $cpt_posts ) ) {

					// Get the count for all of the CPT-onomy terms
					$cpt_posts_count = $this->get_terms_count( $cpt_taxonomies );
					
					// Loop through each post and setup the term
					foreach ( $cpt_posts as $this_post ) {
						
						if ( ! empty( $this_post ) ) {
						
							// Save object ID and remove before conversion
							$object_id = $this_post->object_id;
							unset( $this_post->object_id );
																												
							// Convert to term
							// Don't need count because we're adding it next
							$term = $this->convert_object_to_cpt_onomy_term( $this_post, false );
							
							// If no term then continue to next post
							if ( ! $term ) {
								continue;
							}
							
							// Add object ID back to the mix
							$term->object_id = $object_id;
							
							// Add term count
							$term->count = isset( $cpt_posts_count[ $this_post->ID ] ) && $cpt_posts_count[ $this_post->ID ] > 0 ? $cpt_posts_count[ $this_post->ID ] : 0;
														
							// Add to terms
							$terms[] = $term;
						
						}
						
					}
					
				}
				
				break;
				
			case 'all':
			default:
			
				// Build the query
				// Get term count and post info
				$cpt_posts_query = "SELECT (SELECT COUNT(*) FROM {$wpdb->postmeta} wpcountmeta INNER JOIN {$wpdb->posts} wpcountmetaposts ON wpcountmetaposts.ID = wpcountmeta.post_id AND wpcountmetaposts.post_status = 'publish' WHERE wpcountmeta.meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "' AND wpcountmeta.meta_value = wpposts.ID) AS count,
					wpposts.* FROM {$wpdb->posts} wpposts
					INNER JOIN {$wpdb->postmeta} wpmeta ON
						wpmeta.meta_value = wpposts.ID AND
						wpmeta.meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "' AND
						wpmeta.post_id IN (" . implode( ',', $object_ids ) . ")";
						
						// Exclude certain "terms"
						if ( ! empty( $exclude ) ) {
							$cpt_posts_query .= " AND wpposts.ID NOT IN ( " . implode( ',', $exclude ) . " )";
						}
						
					$cpt_posts_query .= " WHERE wpposts.post_type IN ('" . implode( "','", $cpt_taxonomies ) . "') AND wpposts.post_status = 'publish'";
					
				// Get the posts
				$cpt_posts = $wpdb->get_results( $cpt_posts_query );
				
				if ( ! empty( $cpt_posts ) ) {
					
					foreach ( $cpt_posts as $this_post ) {
					
						if ( ! empty( $this_post ) ) {
						
							// Save count and remove before conversion
							$term_count = $this_post->count;
							unset( $this_post->count );
							
							// Convert to term
							// Don't need count because we're adding it next
							$term = $this->convert_object_to_cpt_onomy_term( $this_post, false );
							
							// If no term then continue to next post
							if ( ! $term ) {
								continue;
							}
							
							// Add count back to the mix
							$term->count = $term_count;
						
							// Add to terms
							$terms[] = $term;
							
						}
					
					}
				
				}
				break;	
			
		}
		
		/**
		 * Sort orderby.
		 * If 'ids' or 'names', then we have a simpler sort.
		 */
		if ( in_array( $args['fields'], array( 'ids', 'names', 'slugs' ) ) ) {
			sort( $terms );
		} else {

			switch( $args['orderby'] ) {

				case 'id':
					usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_term_id' );
					break;

				case 'name':
				case 'title':
					usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_name' );
					break;

				case 'count':
					usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_count' );
					break;

				case 'slug':
					usort( $terms, 'cpt_onomies_sort_cpt_onomy_term_by_slug' );
					break;

				case 'term_group':
					break;

				case 'term_order':
					break;
			}

		}
			
		// Sort order
		if ( 'desc' == strtolower( $args['order'] ) ) {
			$terms = array_reverse( $terms );
		}
				
		if ( ! $terms ) {
			$terms = array();
		}
						
		return $terms;
	}
	
	/**
	 * This function sets the terms for a post.
	 *
	 * This function mimics the WordPress function wp_set_post_terms()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * Invokes $cpt_onomy->wp_set_object_terms().
	 *
	 * @since   1.3
	 * @uses    $cpt_onomies_manager
	 * @param   int $post_id Post ID.
	 * @param   string|array $terms The terms to set for the post. Can be an array or a comma separated string.
	 * @param   string $taxonomy Taxonomy name.
	 * @param   bool $append If true, don't delete existing terms, just add on. If false, replace the terms with the new terms.
	 * @return  mixed Array of affected term IDs. WP_Error or false on failure.
	*/
	public function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
		global $cpt_onomies_manager;
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return wp_set_post_terms( $post_id, $terms, $taxonomy, $append );
		}
		
		$post_id = (int) $post_id;
		
		if ( ! $post_id ) {
			return false;
		}
			
		if ( empty( $terms ) ) {
			$terms = array();
		}
			
		if ( ! is_array( $terms ) ) {
			$comma = _x( ',', 'tag delimiter' );
			if ( ',' !== $comma ) {
				$terms = str_replace( $comma, ',', $terms );
			}
			$terms = explode( ',', trim( $terms, " \n\t\r\0\x0B," ) );
		}
		
		/**
		 * Hierarchical taxonomies must always pass IDs rather than names
		 * so that children with the same names but different parents aren't confused.
		 */
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$terms = array_unique( array_map( 'intval', $terms ) );
		}
			
		return $this->wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
	}
	
	/**
	 * This function creates a relationship between an object and a CPT-onomy term.
	 *
	 * This function mimics the WordPress function wp_set_object_terms()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * You can designate that you only want specific terms to be assigned by returning their
	 * term IDs using the 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids'
	 * filter which passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 *
	 * You can disable specific terms from being assigned by returning their
	 * term IDs using the 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids'
	 * filter which passes three parameters: the $taxonomy, the $post_type and the $post_id.
	 *
	 * The relationship is created by adding the $term_id as post meta.
	 *
	 * As of 1.3.1, runs 'cpt_onomy_created_object_term_relationship' action to allow user
	 * to run code when relationships are created. Action provides 4 arguments: the term id,
	 * the taxonomy/CPT-onomy, the object id/post id and object post type.
	 *
	 * @since   1.0
	 * @uses    $wpdb, $cpt_onomies_manager
	 * @param   int $object_id The object to relate to.
	 * @param   array|int|string $terms The slug or id of the term, will replace all existing related terms in this taxonomy.
	 * @param   array|string $taxonomy The context in which to relate the term to the object.
	 * @param   bool $append If false will delete difference of terms.
	 * @return  array|WP_Error Affected Term IDs
	 * @filters 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids' - $taxonomy, $object_post_type, $object_id
	 * @filters 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids' - $taxonomy, $object_post_type, $object_id
	 * @filters 'cpt_onomy_created_object_term_relationship' - $term_id, $taxonomy, $object_id, $object_post_type
	*/
	public function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
		global $wpdb, $cpt_onomies_manager;
		
		/**
		 * This function only processes registered CPT-onomies.
		 * If this is a normal taxonomy, then use the WordPress function.
		 */
		if ( ! $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
			return wp_set_object_terms( $object_id, $terms, $taxonomy, $append );
		}
			
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'Invalid Taxonomy', 'cpt-onomies' ) );
		}
			
		/*
		 * current_user_can() doesn't work when running CRON jobs because
		 * wp_get_current_user() doesn't appear to work with CRON and therefore
		 * it cannot detect the current user.
		 */
		$tax = get_taxonomy( $taxonomy );
		if ( ! defined( 'DOING_CRON' ) && ! current_user_can( $tax->cap->assign_terms ) ) {
			return new WP_Error( $tax->cap->assign_terms, __( 'You are not allowed to assign terms for this taxonomy.', 'cpt-onomies' ) );
		}
			
		$object_id = (int) $object_id;
		$object_post_type = get_post_type( $object_id );
		
		// Make sure these posts are allowed to have a relationship
		if ( ! is_object_in_taxonomy( $object_post_type, $taxonomy ) ) {
			return new WP_Error( 'taxonomy_relationship', __( 'This post type object and taxonomy are not allowed to have a relationship.', 'cpt-onomies' ) );
		}
		
		// Make sure terms is an array
		if ( ! is_array( $terms ) ) {
			$terms = str_replace( ' ', ',', str_replace( ', ', ',', $terms ) );
			$terms = explode( ',', $terms );
		}
		
		/**
		 * Allows you to designate that you only want specific term IDs to be assigned.
		 * Can be array, space-separated or comma separated string.
		 */
		$include_term_ids = apply_filters( 'custom_post_type_onomies_assigning_cpt_onomy_terms_include_term_ids', array(), $taxonomy, $object_post_type, $object_id );
		
		// Make sure $include_term_ids is an array
		if ( ! is_array( $include_term_ids ) ) {
			$include_term_ids = str_replace( ' ', ',', str_replace( ', ', ',', $include_term_ids ) );
			$include_term_ids = explode( ',', $include_term_ids );
		}
		
		// Make sure the 'include' does not include the current object ID
		if ( in_array( $object_id, $include_term_ids ) ) {
			foreach( $include_term_ids as $term_id_index => $term_id ) {
				if ( $object_id == $term_id ) {
					unset( $include_term_ids[ $term_id_index ] );
				}
			}
		}
		
		/**
		 * Allows you to exclude term IDs from being assigned.
		 * Can be array, space-separated or comma separated string.
		 */
		$exclude_term_ids = apply_filters( 'custom_post_type_onomies_assigning_cpt_onomy_terms_exclude_term_ids', array(), $taxonomy, $object_post_type, $object_id );
		
		// Make sure $exclude_term_ids is an array
		if ( ! is_array( $exclude_term_ids ) ) {
			$exclude_term_ids = str_replace( ' ', ',', str_replace( ', ', ',', $exclude_term_ids ) );
			$exclude_term_ids = explode( ',', $exclude_term_ids );
		}
	
		// Make sure the 'excludes' includes the current object ID
		if ( ! in_array( $object_id, $exclude_term_ids ) ) {
			$exclude_term_ids[] = $object_id;
		}
		
		// We need the term IDs for $append logic at the end
		$term_ids = array();
				
		foreach ( (array) $terms as $term ) {
			
			if ( ! strlen( trim( $term ) ) ) {
				continue;
			}
				
			if ( is_numeric( $term ) ) {
				$term = (int) $term;
			}
						
			if ( ! $term_info = $this->term_exists( $term, $taxonomy ) ) {
				
				// Skip if a non-existent term ID is passed.
				if ( is_int( $term ) ) {
					continue;
				}
					
			}
			
			if ( is_wp_error( $term_info ) ) {
				return $term_info;
			}
			
			// Only assign included terms
			if ( $include_term_ids && ! in_array( $term_info->term_id, $include_term_ids ) ) {
				continue;
			}
			
			// Do not assign excluded terms
			if( $exclude_term_ids && in_array( $term_info->term_id, $exclude_term_ids ) ) {
				continue;
			}
				
			// Keep track of all the term IDs for $append logic at the end
			$term_ids[] = $term_info->term_id;
			
			// Make sure the relationship doesn't already exist
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $object_id, CPT_ONOMIES_POSTMETA_KEY, $term_info->term_id ) ) ) {
				continue;
			}
			
			/**
			 * Create object/term relationship.
			 * Call action that allows user to run code when relationships are set.
			 */
			if ( add_post_meta( $object_id, CPT_ONOMIES_POSTMETA_KEY, $term_info->term_id, false ) ) {
				do_action( 'cpt_onomy_created_object_term_relationship', $term_info->term_id, $taxonomy, $object_id, $object_post_type );
			}
			
		}
			
		// Delete all pre-existing term relationships
		if ( ! $append ) {
		
			/**
			 * We don't have to retrieve 'all' of the term info here because
			 * we're only dealing with CPT-onomies and not mingling with taxonomies.
			 */
			$old_term_ids = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids', 'orderby' => 'id' ) );
			
			$delete_terms = array_diff( $old_term_ids, $term_ids );
			if ( $delete_terms ) {
				foreach( $delete_terms as $delete_term_id ) {
					$this->wp_delete_object_term_relationship( $object_id, $delete_term_id );
				}
			}
			
		}
	
		return $term_ids;
	}
	
	/**
	 * This function deletes a relationship between an object and a CPT-onomy term.
	 *
	 * If you want to delete ALL relationships, use $this->wp_delete_object_term_relationships().
	 *
	 * As of 1.3.1, runs 'cpt_onomy_deleted_object_term_relationship' action to allow user
	 * to run code when relationships are deleted. Action provides 4 arguments: the term id,
	 * the taxonomy/CPT-onomy, the object id/post id and object post type.
	 *
	 * @since   1.0
	 * @uses    $wpdb, $cpt_onomies_manager
	 * @param   int $object_id - the ID of the object
	 * @param   int $term_id - if wanting to delete a specific relationship, provide the term ID
	 * @return  boolean|WP_Error - true if relationships are deleted, otherwise false
	 * @filters 'cpt_onomy_deleted_object_term_relationship' - $term_id, $taxonomy, $object_id, $object_post_type
	 */
	public function wp_delete_object_term_relationship( $object_id, $term_id ) {
		global $wpdb, $cpt_onomies_manager;

		$object_id = (int) $object_id;
		$term_id = (int) $term_id;
		$taxonomy = get_post_type( $term_id );
		
		// Make sure this is a CPT-onomy
		if ( $object_id && $term_id && $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
		
			// Make sure the relationship exists
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $object_id, CPT_ONOMIES_POSTMETA_KEY, $term_id ) ) ) {
				return false;
			}
			
			/**
			 * Delete object/term relationship.
			 * Call action that allows user to run code when relationships are deleted.
			 */
			if ( delete_post_meta( $object_id, CPT_ONOMIES_POSTMETA_KEY, $term_id ) ) {
				do_action( 'cpt_onomy_deleted_object_term_relationship', $term_id, $taxonomy, $object_id, get_post_type( $object_id ) );
				return true;
			}
						
		}
			
		return false;
	}
	
	/**
	 * This function mimics the WordPress function wp_delete_object_term_relationships()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * As of 1.3.1, runs 'cpt_onomy_deleted_object_term_relationship' action to allow user
	 * to run code when relationships are deleted. Action provides 4 arguments: the term id,
	 * the taxonomy/CPT-onomy, the object id/post id and object post type.
	 *
	 * @since 1.0
	 * @uses $wpdb, $cpt_onomies_manager
	 * @param int $object_id The term Object Id that refers to the term
	 * @param string|array $taxonomies List of Taxonomy Names or single Taxonomy name. If not set, deletes ALL relationships
	 * @return boolean|WP_Error - true if relationships are deleted, otherwise false
	 * @filters 'cpt_onomy_deleted_object_term_relationship' - $term_id, $taxonomy, $object_id, $object_post_type
	 */
	public function wp_delete_object_term_relationships( $object_id, $taxonomies = NULL ) {
		global $wpdb, $cpt_onomies_manager;
	
		$object_id = (int) $object_id;
	
		// Delete ALL relationships
		if ( empty( $taxonomies ) ) {
			return delete_post_meta( $object_id, CPT_ONOMIES_POSTMETA_KEY );
		}
		
		else {
			
			if ( ! is_array( $taxonomies ) ) {
				$taxonomies = array( $taxonomies );
			}
				
			// This function only filters registered CPT-onomies
			$cpt_taxonomies = array();
			foreach( $taxonomies as $taxonomy ) {
				if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
					$cpt_taxonomies[] = $taxonomy;
				}
			}
			
			// This means there are no CPT-onomies so wrap things up
			if ( empty( $cpt_taxonomies ) ) {
				return wp_delete_object_term_relationships( $object_id, $taxonomies );
			} else {
				$taxonomies = $cpt_taxonomies;
			}
			
			// Add "quotes"
			foreach( $taxonomies as $index => $taxonomy ) {
				$taxonomies[ $index ] = "'" . $taxonomy . "'";
			}
			$taxonomies = implode( ',', $taxonomies );
			
			// Get IDs of terms being deleted to use for 'cpt_onomy_deleted_object_term_relationship' action
			if ( $term_ids_being_deleted = $wpdb->get_col( $wpdb->prepare( "SELECT wpmeta.meta_value FROM {$wpdb->postmeta} wpmeta INNER JOIN {$wpdb->posts} wpp ON wpp.ID = wpmeta.meta_value WHERE wpp.post_type IN (" . $taxonomies . ") AND wpmeta.post_id = %d AND wpmeta.meta_key = %s", $object_id, CPT_ONOMIES_POSTMETA_KEY ) ) ) {
			
				// Delete object relationships with specific taxonomies
				if ( $wpdb->query( $wpdb->prepare( "DELETE wpmeta.* FROM {$wpdb->postmeta} wpmeta INNER JOIN {$wpdb->posts} wpp ON wpp.ID = wpmeta.meta_value WHERE wpp.post_type IN (" . $taxonomies . ") AND wpmeta.post_id = %d AND wpmeta.meta_key = %s", $object_id, CPT_ONOMIES_POSTMETA_KEY ) ) ) {
				
					// Action allows user to run code when relationships are deleted
					foreach( $term_ids_being_deleted as $deleted_term_id ) {
						do_action( 'cpt_onomy_deleted_object_term_relationship', $deleted_term_id, get_post_type( $deleted_term_id ), $object_id, get_post_type( $object_id ) );
					}		
				
					return true;
					
				}					
					
			}
			
			return false;
			
		}
		
	}
	
	/**
	 * This function mimics the WordPress function wp_tag_cloud()
	 * because we cannot hook into the function without receiving errors.
	 *
	 * As of 1.1, you can define 'link' as 'cpt_post' if you want the term's
	 * link to take you to the CPT's post page instead of the term archive.
	 * 
	 * @since   1.0
	 * @uses    $cpt_onomy
	 * @param   array|string $args Optional. Override default arguments.
 	 * @return  array Generated tag cloud, only if no failures and 'array' is set for the 'format' argument.
	 */
	public function wp_tag_cloud( $args = NULL ) {
		global $cpt_onomy;

		$defaults = array(
			'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 45,
			'format' => 'flat', 'separator' => "\n", 'orderby' => 'name', 'order' => 'ASC',
			'exclude' => '', 'include' => '', 'link' => 'view', 'taxonomy' => 'post_tag', 'echo' => true
		);
		$args = wp_parse_args( $args, $defaults );

		$tags = get_terms( $args[ 'taxonomy' ], array_merge( $args, array( 'orderby' => 'count', 'order' => 'DESC' ) ) ); // Always query top tags
	
		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			return;
		}
	
		foreach ( $tags as $key => $tag ) {

			if ( 'edit' == $args[ 'link' ] ) {
				$link = $cpt_onomy->get_edit_term_link( $tag->term_id, $tag->taxonomy );
			} elseif ( 'cpt_post' == $args[ 'link' ] ) {
				$link = get_permalink( $tag->term_id );
			} else {
				$link = $cpt_onomy->get_term_link( intval( $tag->term_id ), $tag->taxonomy );
			}

			if ( is_wp_error( $link ) ) {
				return false;
			}
	
			$tags[ $key ]->link = $link;
			$tags[ $key ]->id = $tag->term_id;
		}
	
		$return = wp_generate_tag_cloud( $tags, $args ); // Here's where those top tags get sorted according to $args
	
		$return = apply_filters( 'wp_tag_cloud', $return, $args );
	
		if ( 'array' == $args[ 'format' ] || empty( $args[ 'echo' ] ) ) {
			return $return;
		}
	
		echo $return;
		
	}
	
}

/**
 * These functions sort an array of terms.
 *
 * @since 1.0
 */
function cpt_onomies_sort_cpt_onomy_term_by_name( $a, $b ) {
	if ( strtolower( $a->name ) == strtolower( $b->name ) ) {
		return 0;
	}
	return ( strtolower( $a->name ) < strtolower( $b->name ) ) ? -1 : 1;
}
function cpt_onomies_sort_cpt_onomy_term_by_term_id( $a, $b ) {
	if ( $a->term_id == $b->term_id ) {
		return 0;
	}
	return ( $a->term_id < $b->term_id ) ? -1 : 1;
}
function cpt_onomies_sort_cpt_onomy_term_by_count( $a, $b ) {
	if ( $a->count == $b->count ) {
		return 0;
	}
	return ( $a->count < $b->count ) ? -1 : 1;
}
function cpt_onomies_sort_cpt_onomy_term_by_slug( $a, $b ) {
	if ( strtolower( $a->slug ) == strtolower( $b->slug ) ) {
		return 0;
	}
	return ( strtolower( $a->slug ) < strtolower( $b->slug ) ) ? -1 : 1;
}