<?php

/* Instantiate the class. */
global $cpt_onomies_manager;
$cpt_onomies_manager = new CPT_ONOMIES_MANAGER();

/**
 * Holds the functions needed for managing the custom post types and taxonomies.
 *
 * @since 1.0
 */
class CPT_ONOMIES_MANAGER {
	
	public $user_settings = array(
		'network_custom_post_types'	=> array(),
		'custom_post_types'			=> array(),
		'other_custom_post_types'	=> array()
	);
	
	/**
	 * Retrieves the user's plugin options and defines $user_settings.
	 * Registers the custom post types and taxonomies.
	 *
	 * Adds WordPress hooks (actions and filters).
	 *
	 * @since 1.0
	 */
	public function __construct() {
		
		// get network user settings (only if multisite AND plugin is network activated)
		// had to take code from is_plugin_active_for_network() because the function is not loaded in time
		$this->user_settings[ 'network_custom_post_types' ] = ( is_multisite() && ( $plugins = get_site_option( 'active_sitewide_plugins' ) ) && isset( $plugins[ CPT_ONOMIES_PLUGIN_FILE ] ) && ( $network_custom_post_types = get_site_option( CPT_ONOMIES_UNDERSCORE . '_custom_post_types' ) ) ) ? $network_custom_post_types : array();
		
		// get site user settings
		$this->user_settings[ 'custom_post_types' ] = ( $custom_post_types = get_option( CPT_ONOMIES_UNDERSCORE . '_custom_post_types' ) ) ? $custom_post_types : array();
		$this->user_settings[ 'other_custom_post_types' ] = ( $other_custom_post_types = get_option( CPT_ONOMIES_UNDERSCORE . '_other_custom_post_types' ) ) ? $other_custom_post_types : array();
				
		// register custom query vars
		add_filter( 'query_vars', array( &$this, 'register_custom_query_vars' ) );
		
		// revert the query vars
		add_action( 'parse_request', array( &$this, 'revert_query_vars' ), 100 );
		
		// manage user capabilities
		add_filter( 'user_has_cap', array( &$this, 'user_has_term_capabilities' ), 10, 3 );
		
		// tweak the query
		add_filter( 'request', array( &$this, 'change_query_vars' ) );
		add_action( 'pre_get_posts', array( &$this, 'add_cpt_onomy_term_queried_object' ), 1 );
		add_filter( 'posts_clauses', array( &$this, 'posts_clauses' ), 100, 2 );
		
		// clean up the query
		add_action( 'pre_get_posts', array( &$this, 'clean_get_posts_terms_query' ), 100 );
		
		// register custom post types and taxonomies
		add_action( 'init', array( &$this, 'register_custom_post_types_and_taxonomies' ), 100 );
		
	}
	public function CPT_ONOMIES_MANAGER() { $this->__construct(); }
	
	/**
	 * Adds the custom query variable 'cpt_onomy_archive' to WordPress's
	 * WP_Query class which allows the plugin to create custom rewrites and queries.
	 *
	 * This function is applied to the filter 'query_vars'.
	 *
	 * @since 1.0
	 * @param array $vars - the query variables already created by WordPress
	 * @return array - the filtered query variables 
	 */
	public function register_custom_query_vars( $vars ) {
		array_push( $vars, 'cpt_onomy_archive' );
		return $vars;
	}
	
	/**
	 * As of version 1.0.3, this function cleans up queries for front and back end tax queries.
	 *
	 * For front-end CPT-onomy archive pages, it removes 'name' so WordPress does not think this 
	 * is a single post AND it defines which post types to show, i.e. which post types are attached
	 * to the CPT-onomy.
	 *
	 * This function is also run on the admin "edit posts" screen so you can filter posts by a CPT-onomy.
	 * It removes 'name' so WordPress does not think we are looking for a post with that 'name'.
	 *
	 * This function is applied to the filter 'request'.
	 * 	 
	 * @since 1.0
	 * @uses $cpt_onomy, $pagenow, $post_type
	 * @param array $query - the query variables already created by WordPress
	 * @return array - the filtered query variables
	 */
	public function change_query_vars( $query ) {
		global $cpt_onomy, $pagenow, $post_type;
		if ( isset( $query[ 'cpt_onomy_archive' ] ) && $query[ 'cpt_onomy_archive' ] ) {
		
			// make sure CPT-onomy AND term exists, otherwise, why bother
			$change_query_vars = false;
			foreach( get_taxonomies( array(), 'objects' ) as $taxonomy => $tax ) {
				if ( isset( $query[ $taxonomy ] ) && !empty( $query[ $taxonomy ] ) && $this->is_registered_cpt_onomy( $taxonomy ) ) {
				
					// make sure the term exists
					$cpt_onomy_term_var = explode( "/", $query[ $taxonomy ] );
										
					// get parent
					$parent_term_id = $parent_term = 0;
					if ( count( $cpt_onomy_term_var ) > 1 ) {
						foreach( $cpt_onomy_term_var as $index => $term_var ) {
							if ( $index > 0 ) {
								$parent_term = $cpt_onomy->get_term_by( 'slug', $cpt_onomy_term_var[ $index - 1 ], $taxonomy, OBJECT, 'raw', ( isset( $parent_term ) && isset( $parent_term->term_id ) ) ? $parent_term->term_id : 0 );
								if ( isset( $parent_term->term_id ) ) $parent_term_id = $parent_term->term_id;
							}						
						}
					}
					
					// if term id, then we need to get the term by id
					$get_term_by = 'slug';
					if ( is_numeric( $cpt_onomy_term_var[ count( $cpt_onomy_term_var ) - 1 ] ) )
						$get_term_by = 'id';
											
					// if the term doesn't exist, we're not going to change the query vars
					if ( $cpt_onomy_term = $cpt_onomy->get_term_by( $get_term_by, $cpt_onomy_term_var[ count( $cpt_onomy_term_var ) - 1 ], $taxonomy, NULL, NULL, $parent_term_id ) ) {
						
						// we're going to want to change the query vars
						$change_query_vars = true;
					
						// to avoid confusion with other children of the same name, change term to term id
						if ( $cpt_onomy_term->parent )
							$query[ $taxonomy ] = $cpt_onomy_term->term_id;
							
					}
					else {
						$change_query_vars = false;
						break;
					}
						
				}			
			}
			
			if ( $change_query_vars ) {
														
				// the 'name' variable makes WordPress think this is a single post with the assigned 'name'
				unset( $query[ 'name' ] );
						
			}
			
		}
		// for filtering by CPT-onomy on admin edit posts screen
		else if ( is_admin()  && $pagenow == 'edit.php' && isset( $post_type ) ) {
			foreach( get_taxonomies( array(), 'objects' ) as $taxonomy => $tax ) {
				if ( isset( $_REQUEST[ $taxonomy ] ) && !empty( $_REQUEST[ $taxonomy ] ) && $this->is_registered_cpt_onomy( $taxonomy ) )  {
									
					if ( is_numeric( $_REQUEST[ $taxonomy ] ) )
						$cpt_onomy_term = $cpt_onomy->get_term( (int) $_REQUEST[ $taxonomy ], $taxonomy );
					else
						$cpt_onomy_term = $cpt_onomy->get_term_by( 'slug', $_REQUEST[ $taxonomy ], $taxonomy );
					
					// the 'name' variable makes WordPress think we are looking for a post with that 'name'
					if ( !empty( $cpt_onomy_term ) )
						unset( $query[ 'name' ] );

				}
			}
		}	
		return $query;
	}
	
	/**
	 * This function is used to revert any query variables that WordPress
	 * might have changed, if we deem necessary.
	 *
	 * For example, if our custom query/rewrite designates a 'post_type'
	 * but WordPress changes the 'post_type' query variable because we're
	 * also querying a CPT-onomy with the same name as as post type, we
	 * want to revert the 'post_type' to our designated value.
	 *
	 * This function also allows us to designate that we only want to
	 * query the post types our CPT-onomes are "attached" to.
	 *
	 * This function is applied to the action 'parse_request'.
	 * 	 
	 * @since 1.2
	 * @param array $query - the query variables already created by WordPress
	 */	
	public function revert_query_vars( $query ) {
		if ( isset( $query->query_vars[ 'cpt_onomy_archive' ] ) && $query->query_vars[ 'cpt_onomy_archive' ] && ( isset( $query->matched_query ) && ( $matched_query = $query->matched_query ) ) ) {
			// we want the post type objects that all queried CPT-onomies have in common
			// we also want to store which CPT-onomies are actually being queried
			$cpt_onomy_objects = $queried_cpt_onomies = array();
			$cpt_onomy_index = 0;
			foreach( get_taxonomies( array(), 'objects' ) as $taxonomy => $tax ) {
				// only want the CPT-onomies that are being queried
				if ( array_key_exists( $taxonomy, $query->query_vars ) && $this->is_registered_cpt_onomy( $taxonomy ) ) {
					$queried_cpt_onomies[ $taxonomy ] = $tax;
					// get array started
					if ( $cpt_onomy_index == 0 )
						$cpt_onomy_objects = array_merge( $cpt_onomy_objects, $tax->object_type );
					// intersect the arrays
					else
						$cpt_onomy_objects = array_intersect( $cpt_onomy_objects, $tax->object_type );
					$cpt_onomy_index++;
				}
			}
			if ( $cpt_onomy_index > 0 ) {
				// if only one CPT-onomy is being queried, then we want to use its post types
				if ( count( $queried_cpt_onomies ) == 1 && isset( $queried_cpt_onomies[0]->object_type ) )
					$custom_post_type = $queried_cpt_onomies[0]->object_type;
				// otherwise, we'll use the ones our multiple CPT-onomies have in common
				else
					$custom_post_type = !empty( $cpt_onomy_objects ) ? $cpt_onomy_objects : NULL;
				// if our custom query/rewrite defines a 'post_type', it overwrites the rest
				foreach( explode( '&', $matched_query ) as $parameter ) {
					$parameter = explode( '=', $parameter );
					if ( isset( $parameter[0] ) && strtolower( $parameter[0] ) == 'post_type'
						&& isset( $parameter[1] ) && ( $set_post_type = $parameter[1] ) ) {
						// we want to use the post type in our custom query/rewrite
						$custom_post_type = $set_post_type;
						break;
					}
				}
				// convert to array for testing
				// want to remove any post types who are not publicy queryable
				if ( !is_array( $custom_post_type ) ) $custom_post_type = array( $custom_post_type );
				foreach( $custom_post_type as $post_type_index => $post_type ) {
					$post_type_exists = post_type_exists( $post_type );
					if ( !$post_type_exists || ( $post_type_exists && get_post_type_object( $post_type )->exclude_from_search ) )
						unset( $custom_post_type[ $post_type_index ] );
				}
				// if just one custom post type, then convert to string
				if ( is_array( $custom_post_type ) && count( $custom_post_type ) == 1 )
					$custom_post_type = array_shift( $custom_post_type );
				// re-assign the 'post_type' query variable
				if ( isset( $custom_post_type ) && !empty( $custom_post_type ) )
					$query->query_vars[ 'post_type' ] = $custom_post_type;
				// there are no post types that are searchable and attached so kill the query
				else
					$query->query_vars[ 'cpt_onomies_kill_query' ] = true;
			}			
		}
	}
	
	/**
	 * This function is used for CPT-onomy archive pages (on the front-end of the site)
	 * in order to trick WordPress into thinking this is a legit taxonomy archive page.
	 * 
	 * This function was created because we cannot hook into WordPress get_term_by(), without receiving an error.
	 * get_term_by() is responsible for passing the term's information to the query, which tells
	 * WordPress this is a taxonomy archive page, so this function creates the term information and
	 * passes it to the query.
	 *
	 * The CPT-onomy archive page query works without the queried object, but it is still required for
	 * other aspects of the page that use the queried object information, i.e. the page title.
	 *
	 * This function is applied to the action 'pre_get_posts'.
	 * 	 
	 * @since 1.0
	 * @uses $cpt_onomy
	 * @param array $query - the query variables already created by WordPress
	 */
	public function add_cpt_onomy_term_queried_object( $query ) {
		global $cpt_onomy;
		// for CPT-onomy archive page on front-end
		if ( isset( $query->query[ 'cpt_onomy_archive' ] ) && !empty( $query->query[ 'cpt_onomy_archive' ] ) ) {		
			// make sure CPT-onomy AND term exists, otherwise, why bother
			foreach( get_taxonomies( array(), 'objects' ) as $taxonomy => $tax ) {
				if ( isset( $query->query[ $taxonomy ] ) && !empty( $query->query[ $taxonomy ] ) && $this->is_registered_cpt_onomy( $taxonomy ) ) {
									
					// make sure the term exists
					if ( is_numeric( $query->query[ $taxonomy ] ) )
						$cpt_onomy_term = $cpt_onomy->get_term( $query->query[ $taxonomy ], $taxonomy );
					else
						$cpt_onomy_term = $cpt_onomy->get_term_by( 'slug', $query->query[ $taxonomy ], $taxonomy );
						
					if ( !empty( $cpt_onomy_term ) ) {
					
						// make sure WordPress knows this is not a post type archive
						$query->is_post_type_archive = false;
					
						// add queried object and queried object ID
						$query->queried_object = $cpt_onomy_term;
						$query->queried_object_id = $cpt_onomy_term->term_id;
						
						break;
						
					}
					
				}			
			}    
		}
	}
	
	/**
	 * As of version 1.0.3, this function detects tax queries in the front and back end and
	 * adjusts the posts query accordingly.
	 *
	 * This function is invoked by the filter 'posts_clauses'.
	 * 
	 * @since 1.0.3
	 * @uses $wpdb, $cpt_onomy
	 * @param array $clauses - the clauses variables already created by WordPress
	 * @param WP_Query object $query - all of the query info
	 * @return array - the clauses info after it has been filtered
	 */
	public function posts_clauses( $clauses, $query ) {
		global $wpdb, $cpt_onomy;
		if ( isset( $query->query[ 'cpt_onomies_kill_query' ] ) && $query->query[ 'cpt_onomies_kill_query' ] ) {
			$clauses[ 'where' ] .= " AND 0=1";
		}
		else {
			if ( isset( $query->tax_query ) ) {	
				
				$is_registered_cpt_onomy = false;
				$taxonomies = array( 'join' => '', 'where' => array() );
				$new_where = array();
				$c = $t = 1;
				foreach ( $query->tax_query->queries as $this_query ) {
				
					$taxonomy = $this_query[ 'taxonomy' ];
				
					if ( ! taxonomy_exists( $taxonomy )  )
						continue;
						
					if ( ! ( $is_registered_cpt_onomy = $this->is_registered_cpt_onomy( $taxonomy ) ) )
						continue;
			
					$this_query[ 'terms' ] = array_unique( (array) $this_query[ 'terms' ] );
						
					if ( empty( $this_query[ 'terms' ] ) )
						continue;
						
					// if terms are ID, change field
					foreach ( $this_query[ 'terms' ] as $term ) {
						if ( is_numeric( $term ) ) {
							$this_query[ 'field' ] = 'id';
							break;
						}
					}
				
					// CPT-onomies
					if ( $is_registered_cpt_onomy ) {
						switch ( $this_query[ 'field' ] ) {
							case 'slug':
							case 'name':						
								$terms = "'" . implode( "','", array_map( 'sanitize_title_for_query', $this_query[ 'terms' ] ) ) . "'";
								$terms = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE " . ( ( strtolower( $this_query[ 'field' ] ) == 'slug' ) ? 'post_name' : 'post_title' ) . " IN ($terms) AND post_type = '{$this_query[ 'taxonomy' ]}'" );
								break;		
							default:
								$terms = array_map( 'intval', $this_query[ 'terms' ] );						
						}
					}
					// taxonomies
					else {
						switch ( $this_query[ 'field' ] ) {
							case 'slug':
							case 'name':
								$terms = "'" . implode( "','", array_map( 'sanitize_title_for_query', $this_query[ 'terms' ] ) ) . "'";
								$terms = $wpdb->get_col( "
									SELECT $wpdb->term_taxonomy.term_taxonomy_id
									FROM $wpdb->term_taxonomy
									INNER JOIN $wpdb->terms USING (term_id)
									WHERE taxonomy = '{$taxonomy}'
									AND $wpdb->terms.{$this_query[ 'field' ]} IN ($terms)
								" );
								break;
							default:
								$terms = implode( ',', array_map( 'intval', $this_query[ 'terms' ] ) );
								$terms = $wpdb->get_col( "
									SELECT term_taxonomy_id
									FROM $wpdb->term_taxonomy
									WHERE taxonomy = '{$taxonomy}'
									AND term_id IN ($terms)
								" );
						}					
					}
					
					if ( 'AND' == $this_query[ 'operator' ] && count( $terms ) < count( $this_query[ 'terms' ] ) )
						return;
										
					$this_query[ 'terms' ] = $terms;
							
					if ( is_taxonomy_hierarchical( $taxonomy ) && $this_query[ 'include_children' ] ) {
						
						$children = array();
						foreach ( $this_query[ 'terms' ] as $term ) {
							
							// for hierarchical CPT-onomies	
							if ( $is_registered_cpt_onomy )
								$children = array_merge( $children, $cpt_onomy->get_term_children( $term, $taxonomy ) );
							// taxonomies
							else
								$children = array_merge( $children, get_term_children( $term, $this_query[ 'taxonomy' ] ) );
								
							$children[] = $term;
						}
						$this_query[ 'terms' ] = $children;
						
					}
					
					extract( $this_query );
					
					$primary_table = $wpdb->posts;
					$primary_id_column = 'ID';
					
					sort( $terms );
		
					if ( 'IN' == $operator ) {
						
						if ( empty( $terms ) )
							continue;
							
						$terms = implode( ',', $terms );
						
						// CPT-onomies
						if ( $is_registered_cpt_onomy ) {
		
							$alias = $c ? 'cpt_onomy_pm' . $c : $wpdb->postmeta;
			
							$clauses[ 'join' ] .= " INNER JOIN $wpdb->postmeta";
							$clauses[ 'join' ] .= $c ? " AS $alias" : '';
							$clauses[ 'join' ] .= " ON ($wpdb->posts.ID = $alias.post_id AND $alias.meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "')";
									
							$new_where[] = "$alias.meta_value $operator ($terms)";
							
							$c++;
							
						}
						// taxonomies
						else {
						
							$alias = $t ? 'cpt_onomy_tt' . $t : $wpdb->term_relationships;
	
							$taxonomies[ 'join' ] .= " INNER JOIN $wpdb->term_relationships";
							$taxonomies[ 'join' ] .= $t ? " AS $alias" : '';
							$taxonomies[ 'join' ] .= " ON ($primary_table.$primary_id_column = $alias.object_id)";
							
							$new_where[] = $taxonomies[ 'where' ][] = "$alias.term_taxonomy_id $operator ($terms)";
							
							$t++;
							
						}
						
					} elseif ( 'NOT IN' == $operator ) {
		
						if ( empty( $terms ) )
							continue;
		
						$terms = implode( ',', $terms );
						
						// CPT-onomies
						if ( $is_registered_cpt_onomy ) {
		
							$new_where[] = "$wpdb->posts.ID NOT IN (
								SELECT post_id
								FROM $wpdb->postmeta
								WHERE meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "'
								AND meta_value IN ($terms)
							)";
							
						}
						// taxonomies
						else {
							
							$new_where[] = $taxonomies[ 'where' ][] = "$primary_table.$primary_id_column NOT IN (
								SELECT object_id
								FROM $wpdb->term_relationships
								WHERE term_taxonomy_id IN ($terms)
							)";
					
						}
						
					} elseif ( 'AND' == $operator ) {
		
						if ( empty( $terms ) )
							continue;
		
						$num_terms = count( $terms );
		
						$terms = implode( ',', $terms );
						
						// CPT-onomies
						if ( $is_registered_cpt_onomy ) {
		
							$new_where[] = "(
								SELECT COUNT(1)
								FROM $wpdb->postmeta
								WHERE meta_key = '" . CPT_ONOMIES_POSTMETA_KEY . "'
								AND meta_value IN ($terms)
								AND post_id = $wpdb->posts.ID
							) = $num_terms";
							
						}
						// taxonomies
						else {
							
							$new_where[] = $taxonomies[ 'where' ][] = "(
								SELECT COUNT(1)
								FROM $wpdb->term_relationships
								WHERE term_taxonomy_id IN ($terms)
								AND object_id = $primary_table.$primary_id_column
							) = $num_terms";
					
						}
						
					}
					
				}
				
				// only add taxonomies 'join' if it doesn't already exist
				if ( $clauses[ 'join' ] && $taxonomies[ 'join' ] && strpos( $clauses[ 'join' ], $taxonomies[ 'join' ] ) === false )
					$clauses[ 'join' ] .= $taxonomies[ 'join' ];
				
				// remove old taxonomies 'where' so we can add new 'where'
				if ( $taxonomies[ 'where' ] ) {
					
					$tax_where = " AND ( ";
						foreach ( $taxonomies[ 'where' ] as $where_index => $add_where ) {
							if ( $where_index > 0 )
								$tax_where .= " " . $query->tax_query->relation . " ";
							$tax_where .= $add_where;
						}
					$tax_where .= " )";
					
					$clauses[ 'where' ] = str_replace( $tax_where, '', $clauses[ 'where' ] );
					
				}
				
				if ( !empty( $new_where ) )  {
					
					// remove the post_name (WP adds this if the post type is hierarhical. I'm not sure why)
					$clauses[ 'where' ] = preg_replace( '/wp\_posts\.post\_name\s=\s\'([^\']*)\'\sAND\s/i', '', $clauses[ 'where' ] );
					
					// remove 0 = 1
					$clauses[ 'where' ] = preg_replace( '/0\s\=\s1\sAND\s/i', '', $clauses[ 'where' ] );
												
					$clauses[ 'where' ] .= " AND ( ";
						foreach ( $new_where as $where_index => $add_where ) {
							if ( $where_index > 0 )
								$clauses[ 'where' ] .= " " . $query->tax_query->relation . " ";
							$clauses[ 'where' ] .= $add_where;
						}
					$clauses[ 'where' ] .= " )";
						
				}
								
			}
		}
		return $clauses;
	}
	
	/**
	 * Because retrieving CPT-onomy terms involves get_posts(), we have to set some
	 * measures in place to remove any filters or queries that might affect retrieving
	 * the CPT-onomy terms.
	 *
	 * It detects the query variable 'get_cpt_onomy_terms' before editing the query. 
	 *
	 * This function is applied to the action 'pre_get_posts'.
	 * 	 
	 * @since 1.0.3
	 * @param array $query - the query variables already created by WordPress
	 */
	public function clean_get_posts_terms_query( $query ) {
		if ( isset( $query->query_vars[ 'get_cpt_onomy_terms' ] ) ) {
			
			// remove all tax queries
			$query->set( 'taxonomy', NULL );
			$query->set( 'term', NULL );
			if ( isset( $query->tax_query ) )
				$query->tax_query = NULL;
			if ( isset( $query->query[ 'taxonomy' ] ) )
				$query->query_vars[ 'taxonomy' ] = NULL;
			if ( isset( $query->query[ 'term' ] ) )
				$query->query[ 'term' ] = NULL;
			
			// remove all meta queries
			$query->set( 'meta_key', NULL );
			$query->set( 'meta_value', NULL );
			if ( isset( $query->meta_query ) )
				$query->meta_query = NULL;
			if ( isset( $query->query[ 'meta_key' ] ) )
				$query->query_vars[ 'meta_key' ] = NULL;
			if ( isset( $query->query[ 'meta_value' ] ) )
				$query->query[ 'meta_value' ] = NULL;
				
		}
	}
	
	/**
	 * This function hooks into WordPress current_user_can() whenever WordPress
	 * is checking that the user can 'assign_$taxonomy_terms', 'manage_$taxonomy_terms', 
	 * 'edit_$taxonomy_terms' or 'delete_$taxonomy_terms'.
	 *
	 * If assign, it checks user settings to see if user role has permission to assign.
	 * If 'manage', 'edit' or 'delete, it tells WordPress NO!
	 *
	 * This function is applied to the filter 'user_has_cap'.
	 *
	 * @since 1.0
	 * @param array $allcaps - all of the user's preset capabilities
	 * @param array $caps - the capabilities we're testing
	 * @param array $args - additional arguments passed to the function
	 * @return array - the filtered $allcaps
	 */
	public function user_has_term_capabilities( $allcaps, $caps, $args ) {
		// no one can manage, edit, or delete CPT-onomy terms
		foreach( $caps as $this_cap ) {
			
			// if user has capability manually assigned, then allow
			// otherwise, check user settings
			if ( preg_match( '/assign\_([a-z\_]+)\_terms/i', $this_cap ) && !isset( $allcaps[ $this_cap ] ) ) {
				
				// get taxonomy
				$taxonomy = preg_replace( '/(assign_|_terms)/i', '', $this_cap );
				
				// if registered CPT-onomy
				if ( taxonomy_exists( $taxonomy ) && $this->is_registered_cpt_onomy( $taxonomy ) ) {
				
					// get taxonomy info
					$tax = get_taxonomy( $taxonomy );
					
					// default
					$allow = false;
					
					// no capabilities are assigned so everyone has permission
					if ( !isset( $tax->restrict_user_capabilities ) || empty( $tax->restrict_user_capabilities ) )
						$allow = true;
					
					// the capability is restricted to specific roles
					else {
																
						// get user roles to see if user has capability to assign taxonomy
						// $args contains the user id
						$user = new WP_User( $args[1] );
						foreach ( $user->roles as $role ) {
														
							// test to see if role is selected
							if ( in_array( $role, $tax->restrict_user_capabilities ) ) {
								$allow = true;
								break;
							}
								
						}
								
					}
					
					// assign the required capability
					if ( $allow )
						$allcaps[ $this_cap ] = 1;
					else
						unset( $allcaps[ $this_cap ] );					
					
				}
					
			}
			
			// NO ONE is allowed to manage, edit or delete
			else if ( preg_match( '/(manage|edit|delete)\_([a-z\_]+)\_terms/i', $this_cap ) ) {
				
				// get taxonomy
				$taxonomy = preg_replace( '/(manage_|edit_|delete_|_terms)/i', '', $this_cap );
								
				// if registered CPT-onomy
				if ( taxonomy_exists( $taxonomy ) && $this->is_registered_cpt_onomy( $taxonomy ) )
					unset( $allcaps[ $this_cap ] );
					
			}
			
		}
		return $allcaps;
	}
	
	/**
	 * Detects if a custom post is overwriting a network-registered post type
	 * registered by this plugin.
	 * 
	 * @since 1.3
	 * @uses $blog_id
	 * @param string $cpt_key - the key, or alias, for the custom post type you are checking
	 * @return boolean - whether this custom post type is overwriting a network-registered post type registered by this plugin
	 */
	public function overwrote_network_cpt( $cpt_key ) {
		global $blog_id;
		if ( isset( $this->user_settings[ 'network_custom_post_types' ] ) && isset( $this->user_settings[ 'network_custom_post_types' ][ $cpt_key ] )
			&& ( ( ! isset( $this->user_settings[ 'network_custom_post_types' ][ $cpt_key ][ 'site_registration' ] ) || ( isset( $this->user_settings[ 'network_custom_post_types' ][ $cpt_key ][ 'site_registration' ] ) && empty( $this->user_settings[ 'network_custom_post_types' ][ $cpt_key ][ 'site_registration' ] ) ) )
				|| ( isset( $this->user_settings[ 'network_custom_post_types' ][ $cpt_key ][ 'site_registration' ] ) && in_array( $blog_id, $this->user_settings[ 'network_custom_post_types' ][ $cpt_key ][ 'site_registration' ] ) ) ) && $this->is_registered_cpt( $cpt_key ) && ! $this->is_registered_network_cpt( $cpt_key ) )
			return true;		
		return false;
	}
	
	/**
	 * This functions checks to see if a custom post type is a network-registered
	 * custom post type registered by this plugin. When this plugin registers
	 * a network-registered custom post type, it adds the argument 'cpt_onomies_network_cpt'
	 * and 'created_by_cpt_onomies' for testing purposes.
	 *
	 * @since 1.3
	 * @param string $cpt_key - the key, or alias, for the custom post type you are checking
	 * @return boolean - whether this custom post type is a network-registered post type registered by this plugin
	 */
	public function is_registered_network_cpt( $cpt_key ) {
		if ( ! empty( $cpt_key ) && post_type_exists( $cpt_key ) && ( $post_type = get_post_type_object( $cpt_key ) )
			&& isset( $post_type->cpt_onomies_network_cpt ) && $post_type->cpt_onomies_network_cpt
			&& isset( $post_type->created_by_cpt_onomies ) && $post_type->created_by_cpt_onomies )
			return true;
		return false;
	}
	
	/**
	 * This functions checks to see if a custom post type is a custom post type
	 * registered by this plugin. When this plugin registers a custom post type,
	 * it adds the argument 'created_by_cpt_onomies' for testing purposes.
	 *
	 * @since 1.0
	 * @param string $cpt_key - the key, or alias, for the custom post type you are checking
	 * @return boolean - whether this custom post type is a post type registered by this plugin
	 */
	public function is_registered_cpt( $cpt_key ) {
		if ( ! empty( $cpt_key ) && post_type_exists( $cpt_key ) && ( $post_type = get_post_type_object( $cpt_key ) )
			&& isset( $post_type->created_by_cpt_onomies ) && $post_type->created_by_cpt_onomies )
			return true;
		return false;
	}
	
	/**
	 * This functions checks to see if a taxonomy is a taxonomy
	 * registered by this plugin. When this plugin registers a taxonomy,
	 * it adds the argument 'cpt_onomy' for testing purposes.
	 * 
	 * @since 1.0
	 * @param string $tax - the key, or alias, for the taxonomy you are checking
	 * @return boolean - whether this taxonomy is a taxonomy registered by this plugin
	 */
	public function is_registered_cpt_onomy( $taxonomy ) {
		if ( !empty( $taxonomy ) && taxonomy_exists( $taxonomy ) ) {
			$tax = get_taxonomy( $taxonomy );
			if ( isset( $tax->cpt_onomy ) && $tax->cpt_onomy == true )
				return true;
		}
		return false;
	}
		 
	/**
	 *
	 * Registers the user's custom post type as a CPT-onomy.
	 * The custom post type must already be registered in order 
	 * to register the CPT-onomy.
	 *
	 * Because custom post types and taxonomies with the same name share
	 * the same $wp_rewrite permastruct, we cannot define the taxonomy's
	 * rewrite property (custom post types must win the rewrite battle).
	 * Instead, we add our own rewrite rule to display the CPT-onomy archive page.
	 *
	 * As of 1.1, users can define their own CPT-onomy archive page slug.
	 * 
	 * @since 1.1
	 * @author Rachel Carden (@bamadesigner)
	 * @author Travis Smith (@wp_smith) - Thanks for your help Travis!!
	 * @param string $taxonomy - Name of taxonomy object
	 * @param array|string $object_type - Name of the object type for the taxonomy object
	 * @param array|string $args - arguments used to customize the CPT-onomy
	 		'label' (string)						Name of the CPT-onomy shown in the menu. Usually plural.
	 												If not set, the custom post type's label will be used.
	 		'labels' (array)						An array of labels for this CPT-onomy. You can see accepted values
	 												in the function get_taxonomy_labels() in 'wp-includes/taxonomy.php'.
	 												By default, tag labels are used for non-hierarchical types and category
	 												labels for hierarchical ones. If not set, will use WordPress defaults.
	 		'public' (boolean)						If the CPT-onomy should be publicly queryable.
	 												If not set, defaults to custom post type's public definition.
	 		'has_cpt_onomy_archive' (boolean)		Sets whether the CPT-onomy will have an archive page. Defaults to true.
	 		'cpt_onomy_archive_slug' (string)		The slug for the CPT-onomy archive page. 'has_cpt_onomy_archive' must be true.
	 												Accepts variables $post_type, $term_slug and $term_id in string format as placeholders.
	 												Default is '$post_type/tax/$term_slug'.
	 		'restrict_user_capabilities' (array)	User roles who have capability to assign CPT-onomy terms.
	 												If empty, ALL user roles will have the capability.
	 												Default is array( 'administrator', 'editor', 'author' ).
	 * @return null - Returns early if taxonomy already exists or if post type does not exist
	 */
	public function register_cpt_onomy( $taxonomy, $object_type, $args = array() ) {
	
		// if taxonomy already exists (and is not a CPT-onomy) OR matching post type doesn't exist
		// this allows you to overwrite your CPT-onomy registered by the plugin, if desired
		if ( ( taxonomy_exists( $taxonomy ) && !$this->is_registered_cpt_onomy( $taxonomy ) ) || !post_type_exists( $taxonomy ) )
			return;
			
		// make sure $object_type is an array
		if ( !is_array( $object_type ) )
			$object_type = array_unique( array( $object_type ) );
			
		// check to make sure the object types exist
		if ( !empty( $object_type ) ) {
			foreach( $object_type as $object_type_index => $type ) {
				if ( !post_type_exists( $type ) )
					unset( $object_type[ $object_type_index ] );
			}
		}

		// we're not going to register if we have no object types
		if ( empty( $object_type ) )
			return;
		
		// get the matching custom post type info
		$custom_post_type = get_post_type_object( $taxonomy );
		
		// Define the CPT-onomy defaults
	 	$cpt_onomy_defaults = array(
	 		'label'						=> $label = strip_tags( $custom_post_type->label ),
	 		'labels'					=> '', // if no labels are provided, WordPress uses their own
	 		'public'					=> $custom_post_type->public,
	 		'meta_box_format'			=> NULL,
	 		'show_admin_column'			=> true,
	 		'has_cpt_onomy_archive'		=> true,
	 		'cpt_onomy_archive_slug'	=> '$post_type/tax/$term_slug',
	 		'restrict_user_capabilities'=> array( 'administrator', 'editor', 'author' )
	 	);
	 	
	 	// Merge defaults with incoming $args then extract
	 	extract( wp_parse_args( $args, $cpt_onomy_defaults ) );
	 	
	 	// clean up the arguments for registering
	 	// Some CPT-onomy arguments MUST have a set value, no room for customization right now
	 	// we have to clear out the args 'rewrite' because post types and taxonomies with the same name
	 	// share the same $wp_rewrite permastruct and custom post types MUST win the rewrite war.
		// we will add our own rewrite rule for the CPT-onomy archive page
	 	$cpt_onomy_args = array(
			'cpt_onomy'					=> true,
			'created_by_cpt_onomies'	=> isset( $created_by_cpt_onomies ) ? $created_by_cpt_onomies : false,
			'label'						=> $label,
			'labels'					=> $labels,
			'public'					=> $public,
			'hierarchical'				=> $custom_post_type->hierarchical,
			'show_in_nav_menus'			=> false,
			'show_ui'					=> false,
			'show_tagcloud'				=> false,
			'show_admin_column'			=> $show_admin_column,
			'rewrite'					=> false,
			'meta_box_format'			=> $meta_box_format,
			'restrict_user_capabilities'=> $restrict_user_capabilities,
			'capabilities'				=> array(
				'manage_terms' => 'manage_' . $taxonomy . '_terms',
				'edit_terms' => 'edit_' . $taxonomy . '_terms',
				'delete_terms' => 'delete_' . $taxonomy . '_terms',
				'assign_terms' => 'assign_' . $taxonomy . '_terms'
			)
		);
		
		// add rewrite rule (default is true) to display CPT-onomy archive page - default is '{post type}/tax/{term slug}'
		// we must add our own rewrite rule instead of defining the 'rewrite' property because
		// post types and taxonomies with the same name share the same $wp_rewrite permastruct
		// and post types must win the rewrite war.
		if ( !( isset( $has_cpt_onomy_archive ) && !$has_cpt_onomy_archive ) ) {
		
			// make sure we have a slug
			if ( !isset( $cpt_onomy_archive_slug ) || empty( $cpt_onomy_archive_slug ) )
				$cpt_onomy_archive_slug = $cpt_onomy_defaults[ 'cpt_onomy_archive_slug' ];
												
			// add the slug to the CPT-onomy arguments so it will be added to $wp_taxonomies
			// throughout website, if this parameter is set, then "show CPT-onomy archive page" is also set
			$cpt_onomy_args[ 'cpt_onomy_archive_slug' ] = $cpt_onomy_archive_slug;
								
			// replace the variables ($post_type and $term)
			$cpt_onomy_archive_slug = str_replace( array( '$post_type', '$term_slug', '$term_id' ), array( $taxonomy, '([^\s]*)', '([^\s]*)' ), $cpt_onomy_archive_slug );
								
			// get rid of any slashes at the beginning AND end
			$cpt_onomy_archive_slug = preg_replace( '/^([\/]+)/', '', $cpt_onomy_archive_slug );
			$cpt_onomy_archive_slug = preg_replace( '/([\/]+)$/', '', $cpt_onomy_archive_slug );
			
			// add rewrite rule
			add_rewrite_rule( '^' . $cpt_onomy_archive_slug . '/?', 'index.php?'.$taxonomy . '=$matches[1]&cpt_onomy_archive=1', 'top' );
					
		}
							 				
		// Go for launch!
		register_taxonomy( $taxonomy, $object_type, $cpt_onomy_args );
	
	}
	
	/**
	 * This function takes your custom post type arguments from the settings
	 * and prepares them for registration.
	 *
	 * @since 1.3
	 * @param string $cpt_key - Name of the custom post type you are registering
	 * @param array $cpt - Custom post type settings used to mold arguments
	 * @param array $args - Already defined arguments.
	 * @return array of custom post type arguments, ready for registration
	 */
	public function create_custom_post_type_arguments_for_registration( $cpt_key, $cpt = array(), $args = array() ) {
		
		// create label
		// if no label, set to 'Posts'
		$args[ 'label' ] = isset( $cpt[ 'label' ] ) ? strip_tags( $cpt[ 'label' ] ) : 'Posts';
					
		// create labels
		$labels = array( 'name' => $args[ 'label' ] );
		if ( isset( $cpt[ 'singular_name' ] ) && ! empty( $cpt[ 'singular_name' ] ) )
			$labels[ 'singular_name' ] = strip_tags( $cpt[ 'singular_name' ] );
		if ( isset( $cpt[ 'add_new' ] ) && ! empty( $cpt[ 'add_new' ] ) ) 
			$labels[ 'add_new' ] = ( $cpt[ 'add_new' ] );
		if ( isset( $cpt[ 'add_new_item' ] ) && ! empty( $cpt[ 'add_new_item' ] ) )
			$labels[ 'add_new_item' ] = strip_tags( $cpt[ 'add_new_item' ] );
		if ( isset( $cpt[ 'edit_item' ] ) && ! empty( $cpt[ 'edit_item' ] ) )
			$labels[ 'edit_item' ] = strip_tags( $cpt[ 'edit_item' ] );
		if ( isset( $cpt[ 'new_item' ] ) && ! empty( $cpt[ 'new_item' ] ) )
			$labels[ 'new_item' ] = strip_tags( $cpt[ 'new_item' ] );
		if ( isset( $cpt[ 'all_items' ] ) && ! empty( $cpt[ 'all_items' ] ) )
			$labels[ 'all_items' ] = strip_tags( $cpt[ 'all_items' ] );
		if ( isset( $cpt[ 'view_item' ] ) && ! empty( $cpt[ 'view_item' ] ) )
			$labels[ 'view_item' ] = strip_tags( $cpt[ 'view_item' ] );
		if ( isset( $cpt[ 'search_items' ] ) && ! empty( $cpt[ 'search_items' ] ) )
			$labels[ 'search_items' ] = strip_tags( $cpt[ 'search_items' ] );
		if ( isset( $cpt[ 'not_found' ] ) && ! empty( $cpt[ 'not_found' ] ) )
			$labels[ 'not_found' ] = strip_tags( $cpt[ 'not_found' ] );
		if ( isset( $cpt[ 'not_found_in_trash' ] ) && ! empty( $cpt[ 'not_found_in_trash' ] ) )
			$labels[ 'not_found_in_trash' ] = strip_tags( $cpt[ 'not_found_in_trash' ] );
		if ( isset( $cpt[ 'parent_item_colon' ] ) && ! empty( $cpt[ 'parent_item_colon' ] ) )
			$labels[ 'parent_item_colon' ] = strip_tags( $cpt[ 'parent_item_colon' ] );
		if ( isset( $cpt[ 'menu_name' ] ) && ! empty( $cpt[ 'menu_name' ] ) )
			$labels[ 'menu_name' ] = strip_tags( $cpt[ 'menu_name' ] );
		
		// define the labels
		$args[ 'labels' ] = $labels;
		
		// WP default = false, plugin default = true
		$args[ 'public' ] = ( isset( $cpt[ 'public' ] ) && !$cpt[ 'public' ] ) ? false : true;
		
		// boolean (optional) default = false
		// this must be defined for use with register_taxonomy()
		$args[ 'hierarchical' ] = ( isset( $cpt[ 'hierarchical' ] ) && $cpt[ 'hierarchical' ] ) ? true : false;
									
		/*
		 * array (optional) default = array( 'title', 'editor' )
		 *
		 * As of WordPress 3.5, boolean false can be passed as
		 * 'supports' value instead of an array to prevent default
		 * (title and editor) behavior. So if 'supports' array is
		 * empty in settings, then we will define 'supports' as false
		 * so the post type will actually support nothing instead
		 * of applying the default behavior.
		 */
		if ( isset( $cpt[ 'supports' ] ) && ! empty( $cpt[ 'supports' ] ) )
			$args[ 'supports' ] = $cpt[ 'supports' ];
		else
			$args[ 'supports' ] = false;
			
		// array (optional) no default
		if ( isset( $cpt[ 'taxonomies' ] ) && ! empty( $cpt[ 'taxonomies' ] ) ) {
			if ( ! is_array( $cpt[ 'taxonomies' ] ) )
				$cpt[ 'taxonomies' ] = array( $cpt[ 'taxonomies' ] );
			$args[ 'taxonomies' ] = $cpt[ 'taxonomies' ];
		}
		
		// boolean (optional) default = public
		if ( isset( $cpt[ 'show_ui' ] ) )
			$args[ 'show_ui' ] = ( !$cpt[ 'show_ui' ] ) ? false : true;
		// boolean (optional) default = public
		if ( isset( $cpt[ 'show_in_nav_menus' ] ) )
			$args[ 'show_in_nav_menus' ] = ( !$cpt[ 'show_in_nav_menus' ] ) ? false : true;
		// boolean (optional) default = public
		if ( isset( $cpt[ 'publicly_queryable' ] ) )
			$args[ 'publicly_queryable' ] = ( !$cpt[ 'publicly_queryable' ] ) ? false : true;
		// boolean (optional) default = opposite of public
		if ( isset( $cpt[ 'exclude_from_search' ] ) )
			$args[ 'exclude_from_search' ] = ( $cpt[ 'exclude_from_search' ] ) ? true : false;
		// boolean (optional) default = false
		if ( isset( $cpt[ 'map_meta_cap' ] ) )
			$args[ 'map_meta_cap' ] = ( $cpt[ 'map_meta_cap' ] ) ? true : false;
		// boolean (optional) default = true
		if ( isset( $cpt[ 'can_export' ] ) )
			$args[ 'can_export' ] = ( !$cpt[ 'can_export' ] ) ? false : true;
										
		// integer (optional) default = NULL
		if ( isset( $cpt[ 'menu_position' ] ) && ! empty( $cpt[ 'menu_position' ] ) && is_numeric( $cpt[ 'menu_position' ] ) )
			$args[ 'menu_position' ] = intval( $cpt[ 'menu_position' ] );
		
		// string (optional) default is blank
		if ( isset( $cpt[ 'description' ] ) && ! empty( $cpt[ 'description' ] ) )
			$args[ 'description' ] = strip_tags( $cpt[ 'description' ] );
		// string (optional) default = NULL
		if ( isset( $cpt[ 'menu_icon' ] ) && ! empty( $cpt[ 'menu_icon' ] ) )
			$args[ 'menu_icon' ] = $cpt[ 'menu_icon' ];
		// string (optional) no default
		if ( isset( $cpt[ 'register_meta_box_cb' ] ) && ! empty( $cpt[ 'register_meta_box_cb' ] ) )
			$args[ 'register_meta_box_cb' ] = $cpt[ 'register_meta_box_cb' ];
		// string (optional) default = EP_PERMALINK
		if ( isset( $cpt[ 'permalink_epmask' ] ) && ! empty( $cpt[ 'permalink_epmask' ] ) )
			$args[ 'permalink_epmask' ] = $cpt[ 'permalink_epmask' ];
			
		// string or array (optional) default = "post"
		if ( isset( $cpt[ 'capability_type' ] ) && ! empty( $cpt[ 'capability_type' ] ) )
			$args[ 'capability_type' ] = $cpt[ 'capability_type' ];
			
		// boolean or string (optional)
		// default = true (which is opposite of WP default so we must include the setting)
		// if set to string 'true', then store as true
		// else if not set to false, store string	
		if ( isset( $cpt[ 'has_archive' ] ) && ! empty( $cpt[ 'has_archive' ] ) && strtolower( $cpt[ 'has_archive' ] ) != 'true' ) {
			if ( strtolower( $cpt[ 'has_archive' ] ) == 'false' ) $args[ 'has_archive' ] = false;
			else if ( strtolower( $cpt[ 'has_archive' ] ) != 'true' ) $args[ 'has_archive' ] = $cpt[ 'has_archive' ];
		}
		else
			$args[ 'has_archive' ] = true;
		
		// boolean or string (optional) default = true
		// if set to string 'false', then store as false
		// else if set to true, store string	
		if ( isset( $cpt[ 'query_var' ] ) && ! empty( $cpt[ 'query_var' ] ) ) {
			if ( strtolower( $cpt[ 'query_var' ] ) == 'false' ) $args[ 'query_var' ] = false;
			else if ( strtolower( $cpt[ 'query_var' ] ) != 'true' ) $args[ 'query_var' ] = $cpt[ 'query_var' ];							
		}	
		
		// boolean or string (optional) default = NULL
		// if set to string 'false', then store as false
		// if set to string 'true', then store as true
		// if set to another string, store string
		if ( isset( $cpt[ 'show_in_menu' ] ) && ! empty( $cpt[ 'show_in_menu' ] ) ) {
			if ( strtolower( $cpt[ 'show_in_menu' ] ) == 'false' ) $args[ 'show_in_menu' ] = false;
			else if ( strtolower( $cpt[ 'show_in_menu' ] ) == 'true' ) $args[ 'show_in_menu' ] = true;
			else $args[ 'show_in_menu' ] = $cpt[ 'show_in_menu' ];							
		}
		
		// array (optional) default = capability_type is used to construct 
		// if you include blank capabilities, it messes up that capability
		if ( isset( $cpt[ 'capabilities' ] ) && ! empty( $cpt[ 'capabilities' ] ) ) {
			foreach( $cpt[ 'capabilities' ] as $capability_key => $capability ) {
				if ( ! empty( $capability ) ) $args[ 'capabilities' ][ $capability_key ] = $capability;
			}
		}
		
		// boolean or array (optional) default = true and use post type as slug 
		if ( isset( $cpt[ 'rewrite' ] ) && ! empty( $cpt[ 'rewrite' ] ) ) {
			if ( isset( $cpt[ 'rewrite' ][ 'enable_rewrite' ] ) && !$cpt[ 'rewrite' ][ 'enable_rewrite' ] )
				$args[ 'rewrite' ] = false;
			else {
				// remove "enable rewrite" and include the rest
				unset( $cpt[ 'rewrite' ][ 'enable_rewrite' ] );
				if ( isset( $cpt[ 'rewrite' ] ) && ! empty( $cpt[ 'rewrite' ] ) )
					$args[ 'rewrite' ] = $cpt[ 'rewrite' ];								
			}
		}
		
		return $args;
								
	}
	
	/**
	 * If your custom post type is created in the network admin, some settings
	 * have to use a serialized string to help set/combine network and site definitions.
	 * 
	 * As of 1.3., those settings include the CPT-onomy "Restrict User's Capability to
	 * Assign Term Relationships" setting and the CPT "Taxonomies" setting.
	 *
	 * This function takes the serialized string and creates/returns the proper
	 * argument for custom post type registration.
	 *
	 * @since 1.3
	 * @uses $blog_id
	 * @param string $argument - the original argument pulled from settings
	 * @return array of prepared argument, ready for registration
	 */
	private function unserialize_network_custom_post_type_argument( $argument ) {
		global $blog_id;
		
		// if it's already an array, there's no point
		if ( is_array( $argument ) )
			return $argument;
	
		// remove any and all white space
		$argument = preg_replace( '/\s/i', '', $argument );
	
		// divide by ';' which separates site definitions
		$argument = explode( ';', $argument );
		
		// going to need to store some info
		$network_property = $site_property = $overwrite = array();
		
		// separate network/site definitions
		foreach( $argument as $user_role ) {
		
			// see if there is a blog id
			$site_definition = explode( ':', $user_role );
			
			// network definition
			if ( count( $site_definition ) == 1 )
				$network_property = array_merge( $network_property, explode( ',', array_shift( $site_definition ) ) );
				
			// site definition
			else {
						
				if ( ( $site_blog_id = array_shift( $site_definition ) )
					&& $site_blog_id > 0 ) {
					
					$site_property[ $site_blog_id ] = explode( ',', array_shift( $site_definition ) );
					
					// figure out if this is supposed to overwrite the network definition
					if ( isset( $site_definition ) && ! empty( $site_definition ) && ( $site_definition = array_shift( $site_definition ) ) && 'overwrite' == $site_definition )
						$overwrite[ $site_blog_id ] = true;
					
				}
					
			}
				
	
		}
		
		// if there is a site definition for the current blog
		if ( isset( $site_property[ $blog_id ] ) ) {
		
			// site definition takes precedence
			if ( isset( $overwrite ) && isset( $overwrite[ $blog_id ] ) && $overwrite[ $blog_id ] )
				$network_property = $site_property[ $blog_id ];
			// merge site and network definitions
			else
				$network_property = array_merge( $network_property, $site_property[ $blog_id ] );
			
		}
				
		return $network_property;
	
	}
	 	
	/**
	 * Registers the user's custom post types.
	 *
	 * If 'Use Custom Post Type as Taxonomy' is set, registers a CPT-onomy
	 * and adds a rewrite rule to display the CPT-onomy archive page. As of 1.1,
	 * the user can customize the archive page slug. The default is {cpt name}/tax/{term slug}.
	 *
	 * This function is invoked by the action 'init'.
	 *
	 * @since 1.0
	 * @uses $blog_id
	 */
	public function register_custom_post_types_and_taxonomies() {
		global $blog_id;
		
		// going to save register CPT-onomy info so we can register
		// all the CPT-onomies at the same time, after the CPTs are registered
		$register_cpt_onomies = array();
		
		// register the network CPTs
		if ( isset( $this->user_settings[ 'network_custom_post_types' ] ) ) {
		
			// take this one CPT at a time
			foreach( $this->user_settings[ 'network_custom_post_types' ] as $cpt_key => $cpt ) {
				if ( ( ! isset( $cpt[ 'site_registration' ] ) || ( isset( $cpt[ 'site_registration' ] ) && empty( $cpt[ 'site_registration' ] ) ) )
					|| ( isset( $cpt[ 'site_registration' ] ) && in_array( $blog_id, $cpt[ 'site_registration' ] ) ) ) {
					
					// In previous versions, we had to register the post type last in order for it to win the rewrite war
					// As of 1.1, the post type must be registered first in order to register the CPT-onomy and the
					// post type will still win the rewrite war
					
					// make sure post type is not deactivated and does not already exist		
					if ( ! isset( $cpt[ 'deactivate' ] ) && ! post_type_exists( $cpt_key ) ) {
					
						// unserialize 'taxonomies' for network settings, since they are a text input
						// site property is an array of checkboxes and doesn't need to be tampered with
						if ( isset( $cpt[ 'taxonomies' ] ) && ! empty( $cpt[ 'taxonomies' ] )
							&& ! is_array( $cpt[ 'taxonomies' ] ) )
							$cpt[ 'taxonomies' ] = $this->unserialize_network_custom_post_type_argument( $cpt[ 'taxonomies' ] );
					
						// create the arguments
						if ( $args = $this->create_custom_post_type_arguments_for_registration( $cpt_key, $cpt, array( 'created_by_cpt_onomies' => true, 'cpt_onomies_network_cpt' => true ) ) ) {
						
							// register this puppy
							register_post_type( $cpt_key, $args );
							
							// If designated, register CPT-onomy
							if ( isset( $cpt[ 'attach_to_post_type' ] ) && !empty( $cpt[ 'attach_to_post_type' ] ) ) {
							
								// unserialize 'restrict_user_capabilities' for network settings, since they are a text input
								// site property is an array of checkboxes and doesn't need to be tampered with
								if ( isset( $cpt[ 'restrict_user_capabilities' ] ) && ! empty( $cpt[ 'restrict_user_capabilities' ] )
									&& ! is_array( $cpt[ 'restrict_user_capabilities' ] ) )
									$cpt[ 'restrict_user_capabilities' ] = $this->unserialize_network_custom_post_type_argument( $cpt[ 'restrict_user_capabilities' ] );
									
								$register_cpt_onomies[ $cpt_key ] = array(
									'attach_to_post_type' => $cpt[ 'attach_to_post_type' ],
									'cpt_onomy_args' => array(
										'label' => isset( $args[ 'labels' ][ 'name' ] ) ? strip_tags( $args[ 'labels' ][ 'name' ] ) : 'Posts',
										'public' => isset( $args[ 'public' ] ) ? $args[ 'public' ] : true,
										'meta_box_format' => ( isset( $cpt[ 'meta_box_format' ] ) && ! empty( $cpt[ 'meta_box_format' ] ) ) ? $cpt[ 'meta_box_format' ] : NULL,
										'show_admin_column' => ( isset( $cpt[ 'show_admin_column' ] ) && ! $cpt[ 'show_admin_column' ] ) ? false : true,
										'has_cpt_onomy_archive' => ( isset( $cpt[ 'has_cpt_onomy_archive' ] ) && ! $cpt[ 'has_cpt_onomy_archive' ] ) ? false : true,
										'cpt_onomy_archive_slug' => ( isset( $cpt[ 'cpt_onomy_archive_slug' ] ) && ! empty( $cpt[ 'cpt_onomy_archive_slug' ] ) ) ? $cpt[ 'cpt_onomy_archive_slug' ] : NULL,
										'restrict_user_capabilities' => ( isset( $cpt[ 'restrict_user_capabilities' ] ) && ! empty( $cpt[ 'restrict_user_capabilities' ] ) ) ? $cpt[ 'restrict_user_capabilities' ] : array(),
										'created_by_cpt_onomies' => true
									)
								);
								
							}
								
						}
						
					}
					
				}
			}
			
		}
		
		// register site CPTs
		if ( isset( $this->user_settings[ 'custom_post_types' ] ) ) {
		
			// take this one CPT at a time
			foreach( $this->user_settings[ 'custom_post_types' ] as $cpt_key => $cpt ) {
			
				// In previous versions, we had to register the post type last in order for it to win the rewrite war
				// As of 1.1, the post type must be registered first in order to register the CPT-onomy and the
				// post type will still win the rewrite war
						
				// make sure post type is not deactivated
				if ( ! isset( $cpt[ 'deactivate' ] ) ) {
				
					// make sure the CPT does not already exist
					// (unless its a network-registered CPT, which you're allowed to overwrite on a site level)
					$post_type_exists = post_type_exists( $cpt_key );
					if ( ! $post_type_exists || ( $post_type_exists && $this->is_registered_network_cpt( $cpt_key ) ) ) {
					
						// create the arguments
						if ( $args = $this->create_custom_post_type_arguments_for_registration( $cpt_key, $cpt, array( 'created_by_cpt_onomies' => true ) ) ) {
						
							// register this puppy
							register_post_type( $cpt_key, $args );
													
							// If designated, register CPT-onomy
							if ( isset( $cpt[ 'attach_to_post_type' ] ) && ! empty( $cpt[ 'attach_to_post_type' ] ) ) {
							
								$register_cpt_onomies[ $cpt_key ] = array(
									'attach_to_post_type' => $cpt[ 'attach_to_post_type' ],
									'cpt_onomy_args' => array(
										'label' => isset( $args[ 'labels' ][ 'name' ] ) ? strip_tags( $args[ 'labels' ][ 'name' ] ) : 'Posts',
										'public' => isset( $args[ 'public' ] ) ? $args[ 'public' ] : true,
										'meta_box_format' => ( isset( $cpt[ 'meta_box_format' ] ) && ! empty( $cpt[ 'meta_box_format' ] ) ) ? $cpt[ 'meta_box_format' ] : NULL,
										'show_admin_column' => ( isset( $cpt[ 'show_admin_column' ] ) && ! $cpt[ 'show_admin_column' ] ) ? false : true,
										'has_cpt_onomy_archive' => ( isset( $cpt[ 'has_cpt_onomy_archive' ] ) && ! $cpt[ 'has_cpt_onomy_archive' ] ) ? false : true,
										'cpt_onomy_archive_slug' => ( isset( $cpt[ 'cpt_onomy_archive_slug' ] ) && ! empty( $cpt[ 'cpt_onomy_archive_slug' ] ) ) ? $cpt[ 'cpt_onomy_archive_slug' ] : NULL,
										'restrict_user_capabilities' => ( isset( $cpt[ 'restrict_user_capabilities' ] ) && ! empty( $cpt[ 'restrict_user_capabilities' ] ) ) ? $cpt[ 'restrict_user_capabilities' ] : array(),
										'created_by_cpt_onomies' => true
									)
								);
								
							}
							// overwriting network CPT so we have to remove existing CPT-onomy
							else if ( isset( $register_cpt_onomies[ $cpt_key ] ) && $this->overwrote_network_cpt( $cpt_key ) )
								unset( $register_cpt_onomies[ $cpt_key ] );
							
						}
					
					}
					
				}
				
			}
			
		}
		
		// register the CPT-onomies AFTER all the CPTs are registered
		foreach( $register_cpt_onomies as $cpt_key => $cpt_onomy_info ) {
			
			// let's get this sucker registered!							
			$this->register_cpt_onomy( $cpt_key, $cpt_onomy_info[ 'attach_to_post_type' ], $cpt_onomy_info[ 'cpt_onomy_args' ] );
			
		}
					
		// register OTHER custom post types as taxonomies
		if ( ! empty( $this->user_settings[ 'other_custom_post_types' ] ) ) {	
			foreach( $this->user_settings[ 'other_custom_post_types' ] as $cpt_key => $cpt_settings ) {
			
				// If designated, register CPT-onomy
				if ( post_type_exists( $cpt_key )
					&& ! $this->is_registered_cpt( $cpt_key )
					&& isset( $cpt_settings[ 'attach_to_post_type' ] ) && ! empty( $cpt_settings[ 'attach_to_post_type' ] ) ) {
				
					// get post type object
					$custom_post_type = get_post_type_object( $cpt_key );
					
					// create the arguments
					$cpt_onomy_args = array(
						'label' => strip_tags( $custom_post_type->label ),
						'public' => $custom_post_type->public,
						'meta_box_format' => ( isset( $cpt_settings[ 'meta_box_format' ] ) && ! empty( $cpt_settings[ 'meta_box_format' ] ) ) ? $cpt_settings[ 'meta_box_format' ] : NULL,
						'show_admin_column' => ( isset( $cpt_settings[ 'show_admin_column' ] ) && ! $cpt_settings[ 'show_admin_column' ] ) ? false : true,
						'has_cpt_onomy_archive' => ( isset( $cpt_settings[ 'has_cpt_onomy_archive' ] ) && ! $cpt_settings[ 'has_cpt_onomy_archive' ] ) ? false : true,
						'cpt_onomy_archive_slug' => ( isset( $cpt_settings[ 'cpt_onomy_archive_slug' ] ) && ! empty( $cpt_settings[ 'cpt_onomy_archive_slug' ] ) ) ? $cpt_settings[ 'cpt_onomy_archive_slug' ] : NULL,
						'restrict_user_capabilities' => ( isset( $cpt_settings[ 'restrict_user_capabilities' ] ) && ! empty( $cpt_settings[ 'restrict_user_capabilities' ] ) ) ? $cpt_settings[ 'restrict_user_capabilities' ] : array(),
						'created_by_cpt_onomies' => true
					);
										
					// let's get this sucker registered!							
					$this->register_cpt_onomy( $cpt_key, $cpt_settings[ 'attach_to_post_type' ], $cpt_onomy_args );
					
				}
				
			}
		}
		
	}	
}

?>