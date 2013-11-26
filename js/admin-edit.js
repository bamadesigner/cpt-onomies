jQuery.noConflict()(function(){
	
	// hide/show dropdowns if hiding/showing column
	jQuery( '.hide-column-tog' ).live( 'click', function() {
		var $t = jQuery( this ), $column = $t.val();
		var $dropdown = 'dropdown_' + $column;
		if ( $t.prop( 'checked' ) )
			jQuery( '#'+$dropdown ).show();
		else
			jQuery( '#'+$dropdown ).hide();
	});
	
});

(function($) {
		
	function cpt_onomy_populate_quick_edit() {
	    
		// we create a copy of the original inline edit post, overwrite and then "call" the copy
		var $wp_inline_edit = inlineEditPost.edit;
	    inlineEditPost.edit = function( id ) {
						
			// "call" the original edit function
	        $wp_inline_edit.apply( this, arguments );
			
			// get the post ID	
			var $id = 0;
			if ( typeof( id ) == 'object' )
				$id = parseInt( this.getId( id ) );
			
			if ( $id > 0 ) {
				
				// define the edit row
				var $quick_edit_row = $( '#edit-'+$id );
				if ( $quick_edit_row.size() > 0 ) {
				
					// get the post type
					var $post_type = jQuery( 'input[name="post_type"]' ).val();
					
					// get the taxonomies
					var $taxonomies = $quick_edit_row.cpt_onomy_bulk_quick_edit_get_taxonomies();
					
					// get this object's taxonomies "include" term IDs
					// with "include", we have to iterate through each taxonomy
					$.each( $taxonomies, function( $index, $taxonomy ) {
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							dataType: 'json',
							async: true,
							cache: false,
							data: {
								action: 'custom_post_type_onomy_get_cpt_onomy_terms_include_term_ids',
								custom_post_type_onomies_taxonomy: $taxonomy,
								custom_post_type_onomies_post_type: $post_type,
								custom_post_type_onomies_post_id: $id
							},
							success: function( $term_ids ) {
								// if $term_ids is array and not empty
								if ( $.isArray( $term_ids ) && $term_ids.length > 0 ) {
								
									// remove from checklists
									$quick_edit_row.find( 'ul.cpt-onomy-checklist.cpt-onomy-' + $taxonomy ).each( function() {
									
										$( this ).cpt_onomy_remove_from_quick_edit_checklist( $term_ids, true );
										
									});
																									
								}
							}
						});
					});
					
					// get this object's taxonomies "exclude" term IDs
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						dataType: 'json',
						async: true,
						cache: false,
						data: {
							action: 'custom_post_type_onomy_get_cpt_onomy_terms_exclude_term_ids',
							custom_post_type_onomies_taxonomies: $taxonomies,
							custom_post_type_onomies_post_type: $post_type,
							custom_post_type_onomies_post_id: $id
						},
						success: function( $term_ids ) {
							// if $term_ids is array and not empty
							if ( $.isArray( $term_ids ) && $term_ids.length > 0 ) {
							
								// remove from checklists
								$quick_edit_row.find( 'ul.cpt-onomy-checklist' ).each( function() {
									
									$( this ).cpt_onomy_remove_from_quick_edit_checklist( $term_ids, false );
									
								});
															
							}
						}
					});
					
					// get this object's term IDs
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						dataType: 'json',
						async: true,
						cache: false,
						data: {
							action: 'custom_post_type_onomy_populate_bulk_quick_edit',
							custom_post_type_onomies_post_ids: $id,
							custom_post_type_onomies_taxonomies: $taxonomies,
							custom_post_type_onomies_wp_get_object_terms_fields: 'ids'
						},
						success: function( $term_ids ) {
							// if $term_ids is array and not empty
							if ( $.isArray( $term_ids ) && $term_ids.length > 0 ) {
														
								// populate the checklists
								$quick_edit_row.find( 'ul.cpt-onomy-checklist' ).each( function() {
									
									$( this ).cpt_onomy_populate_quick_edit_checklist( $term_ids );
									
								});
								
							}
						}
					});
					
				}
				
			}
			
	    };
	}
	
	function cpt_onomy_save_quick_edit() {
	    
		// we create a copy of the original inline edit post, overwrite and then "call" the copy
		var $wp_inline_edit_save = inlineEditPost.save;
	    inlineEditPost.save = function( id ) {
		
			// "call" the original save function
			$wp_inline_edit_save.apply( this, arguments );
			
			// get the post type
			var $post_type = $( 'input[name="post_type"]' ).val();
			
			// get the post ID
			var $id = 0;
			if ( typeof( id ) == 'object' )
				$id = parseInt( this.getId( id ) );
				
			// we don't have to do this stuff for 'post' or 'page'
			if ( !( $post_type == 'post' || $post_type == 'page' ) && $id > 0 ) {
								
				// get the table's column info
				var $table_columns = new Array();
				$( 'table.wp-list-table thead th' ).each( function() {
					$.each( $( this ).attr( 'class' ).split( ' ' ), function( $index, $value ) {
						if ( $value.match( /^(column\-)/i ) )
							$table_columns.push( $value );
					});
				});
				
				// when WordPress ajax is complete
				$( 'table.wp-list-table' ).ajaxComplete( function() {
					
					// define the post row
					var $post_row = $( inlineEditPost.what + $id );
					// store which columns need to be populated later
					var $populate_columns = new Array();
				
					// WordPress adds "default" columns that might have been removed
					// so remove columns that shouldn't be here
					$post_row.children( 'td' ).each( function() {
						var $column_td = $( this );
						$.each( $column_td.attr( 'class' ).split( ' ' ), function( $index, $value ) {
							if ( $value.match( /^(column\-)/i ) ) {
								if ( $.inArray( $value, $table_columns ) < 0 )
									$column_td.remove();
							}
						});
					});
					
					var $loop_test = false;
					do {
						$loop_test = false;
						var $column_index = 1;
						$post_row.children( 'td' ).each( function() {
							var $column_td = $( this );
							$.each( $column_td.attr( 'class' ).split( ' ' ), function( $index, $value ) {
								if ( $value.match( /^(column\-)/i ) ) {
									if ( $value != $table_columns[ $column_index ] ) {
										
										// save column info so it can be populated later
										$populate_columns.push( $table_columns[ $column_index ] );
										
										// get column name
										var $column_name = $table_columns[ $column_index ].replace( /^(column\-)/i, '' );
																
										// add column														
										var $new_column = '<td class="' + $column_name + ' ' + $table_columns[ $column_index ] + '">&nbsp;</td>';
										
										// add before
										$new_column = $( $new_column ).insertBefore( $column_td );
										
										// hide, if necessary
										if ( $( 'table.wp-list-table thead th.'+$table_columns[ $column_index ] ).is( ':hidden' ) )
											$new_column.hide();
										
										$loop_test = true;
										
									}
								}
							});
							$column_index++;
						});
					}
					while ( $loop_test );
										
					// populate columns
					$.each( $populate_columns, function( $index, $value ) {
												
						// get the column info				
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							async: true,
							cache: false,
							data: {
								action: 'custom_post_type_onomy_quick_edit_populate_custom_columns',
								custom_post_type_onomies_post_id: $id,
								custom_post_type_onomies_post_type: $post_type,
								custom_post_type_onomies_column_name: $value.replace( /^(column\-)/i, '' )
							},
							success: function( $column_text ) {
								// if not blank, add column info
								if ( $column_text != '' )
									$post_row.children( 'td.'+$value ).html( $column_text );
							}
						});
												
					});
									
				});
				
			}
		
		}
		
	}
	
	function cpt_onomy_exclude_from_bulk_edit() {
	    
		// we create a copy of the original inline edit post, overwrite and then "call" the copy
		var $wp_inline_set_bulk = inlineEditPost.setBulk;
	    inlineEditPost.setBulk = function() {
						
			// "call" the original function
	        $wp_inline_set_bulk.apply( this, arguments );
	        
	        // define the bulk edit row
			var $bulk_edit_row = $( '#bulk-edit' );
			if ( $bulk_edit_row.size() > 0 ) {
			
				// get the post type
				var $post_type = jQuery( 'input[name="post_type"]' ).val();
					
				// get the taxonomies
				var $taxonomies = $bulk_edit_row.cpt_onomy_bulk_quick_edit_get_taxonomies();
				
				// get the "include" term IDs
		        // with "include", we have to iterate through each taxonomy
		        $.each( $taxonomies, function( $index, $taxonomy ) {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						dataType: 'json',
						async: true,
						cache: false,
						data: {
							action: 'custom_post_type_onomy_get_cpt_onomy_terms_include_term_ids',
							custom_post_type_onomies_taxonomy: $taxonomy,
							custom_post_type_onomies_post_type: $post_type
						},
						success: function( $term_ids ) {
							// if $term_ids is array and not empty
							if ( $.isArray( $term_ids ) && $term_ids.length > 0 ) {
							
								// remove from checklists
								$bulk_edit_row.find( 'ul.cpt-onomy-checklist.cpt-onomy-' + $taxonomy ).each( function() {
								
									$( this ).cpt_onomy_remove_from_quick_edit_checklist( $term_ids, true );
									
								});
															
							}
						}
					});
				});
				
				// get the "exclude" term IDs
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					dataType: 'json',
					async: true,
					cache: false,
					data: {
						action: 'custom_post_type_onomy_get_cpt_onomy_terms_exclude_term_ids',
						custom_post_type_onomies_taxonomies: $taxonomies,
						custom_post_type_onomies_post_type: $post_type
					},
					success: function( $term_ids ) {
						// if $term_ids is array and not empty
						if ( $.isArray( $term_ids ) && $term_ids.length > 0 ) {
	
							// remove from checklists
							$bulk_edit_row.find( 'ul.cpt-onomy-checklist' ).each( function() {
								
								$( this ).cpt_onomy_remove_from_quick_edit_checklist( $term_ids, false );
								
							});
														
						}
					}
				});
				
			}
			
		};
	}
		
	function cpt_onomy_save_bulk_edit() {
		
		$( '#bulk_edit' ).live( 'click', function() {
						
			// define the bulk edit row
			var $bulk_row = $( '#bulk-edit' );
			
			// get the selected post ids
			var $post_ids = new Array();
			$bulk_row.find( '#bulk-titles' ).children().each( function() {
				$post_ids.push( $( this ).attr( 'id' ).replace( /^(ttle)/i, '' ) );
			});
			
			// find each CPT-onomy checklist and get the checked IDs
			$bulk_row.find( 'ul.cpt-onomy-checklist' ).each( function() {
				
				// get CPT-onomy name
				var $taxonomy = '';
				$.each( $( this ).attr( 'class' ).split( ' ' ), function( $index, $value ) {
					if ( $value != 'cpt-onomy-checklist' && $value.match( /^(cpt\-onomy\-)/i ) )
						$taxonomy = $value.replace( /^(cpt\-onomy\-)/i, '' )
				});
				if ( $taxonomy != '' ) {
					
					// get the checked IDs
					var $checked_ids = new Array();
					$( this ).find( 'input:checked' ).each( function() {
						$checked_ids.push( $( this ).attr( 'value' ) );
					});
					
					// save the data
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						async: false,
						cache: false,
						data: {
							action: 'custom_post_type_onomy_save_bulk_edit',
							custom_post_type_onomies_post_ids: $post_ids,
							custom_post_type_onomies_taxonomy: $taxonomy,
							custom_post_type_onomies_checked_ids: $checked_ids
						}
					});
				}
			});
											
		});
		
	}
	
	// quick edit
	if ( inlineEditPost ) {
	    cpt_onomy_populate_quick_edit();
	   	cpt_onomy_save_quick_edit();
	   	cpt_onomy_exclude_from_bulk_edit();
	}
	else {
	    jQuery( cpt_onomy_populate_quick_edit );
	    jQuery( cpt_onomy_save_quick_edit );
	}
	
	// bulk edit
	cpt_onomy_save_bulk_edit();
	
})(jQuery);

jQuery.fn.cpt_onomy_bulk_quick_edit_get_taxonomies = function() {
	var $taxonomies = new Array();
	jQuery( this ).find( 'ul.cpt-onomy-checklist' ).each( function() {
		jQuery.each( jQuery( this ).attr( 'class' ).split( ' ' ), function( index, value ) {
			if ( value != 'cpt-onomy-checklist' && value.match( /^(cpt\-onomy\-)/i ) )
				$taxonomies.push( value.replace( /^(cpt\-onomy\-)/i, '' ) );
		});
	});
	return $taxonomies;
}

jQuery.fn.cpt_onomy_remove_from_quick_edit_checklist = function( $term_ids, $include ) {
	jQuery( this ).children( 'li' ).each( function() {
		
		// retrieve item info
		var $list_item = jQuery( this );
		var $list_item_id = parseInt( $list_item.attr( 'id' ).match( /[0-9]+/ ) );
		
		// remove item
		// if $include is true, we're removing everything NOT in $term_ids
		// otherwise, remove if in $term_ids
		if ( ( $include && jQuery.inArray( $list_item_id, $term_ids ) == -1 )
			|| ( ! $include && jQuery.inArray( $list_item_id, $term_ids ) > -1 ) )
			$list_item.remove();
			
	});
}

jQuery.fn.cpt_onomy_populate_quick_edit_checklist = function( $term_ids ) {
	jQuery( this ).children( 'li' ).each( function() {
		
		var $list_item = jQuery( this );
		var $input = $list_item.find( 'input#in-'+$list_item.attr( 'id' ) );
		
		// check the checkbox
		if ( jQuery.inArray( $input.attr( 'value' ), $term_ids ) > -1 )
			$input.attr( 'checked', 'checked' );
		
		// take care of the children
		if ( $list_item.children( 'ul' ).size() > 0 )
			$list_item.children( 'ul' ).cpt_onomy_populate_quick_edit_checklist( $term_ids );
		
	});
}