(function($){

	// "most used" tag cloud
    $( '.cpt_onomies_tag_cloud' ).click( function( $event ) {	
    	$event.preventDefault();
		
		// code taken from tagBox.get
		var $id = $( this ).attr( 'id' );
		var $taxonomy = $id.replace( /^([a-z]+)-/i, '' );
		
		$.post( ajaxurl, { 'action':'get-tagcloud', 'tax':$taxonomy }, function( $r, $stat ) {
			
			if ( 0 == $r || 'success' != $stat )
				$r = wpAjax.broken;
			
			// get the tag cloud
			$r = $( '<p id="tagcloud-' + $taxonomy + '" class="the-tagcloud">' + $r + '</p>' );
			
			// setup click term event
			$( 'a', $r ).click( function( $term_event ) {
				$term_event.preventDefault();
				
				// add tag (tag cloud doesn't show for hierarchical so we don't need parent this go 'round)
				var $term = $( this ).html();
				var $term_id = $( this ).attr( 'class' ).replace( /[^0-9]/g, '' );
				
				if ( $term && $term_id ) {
					// find tag checklist
					var $tag_checklist = $( this ).closest( '.inside' ).find( '.cpt_onomies_tags_div' ).find( '.cpt_onomies_tag_checklist' );
					$tag_checklist.custom_post_type_onomies_tag_checklist_add_tag_term( $taxonomy, $term, $term_id, '' );
				}
				
				return false;
			});
	
			// add tag cloud
			$( '#' + $id ).after( $r );
			
		});		
		
		// toggle tag cloud link
		$(this).unbind().click( function() {
			$(this).siblings( '.the-tagcloud' ).toggle();
			return false;
		});
		
		return false;		
	});

})(jQuery);

jQuery.noConflict()(function(){

	// make sure "disabled" dropdowns are actually disabled
	// this attribute is supposed to already be in place but this is a backup check
	jQuery( 'select.category.cpt_onomies' ).each( function() {
		var $taxonomy = jQuery( this ).attr( 'id' ).replace( /^taxonomy-/i, '' );
		// if user cannot assign terms
		if ( !cpt_onomies_admin_post_data.can_assign_terms[ $taxonomy ] )
			jQuery( this ).attr( 'disabled', 'disabled' );	
	});
			
	// Handle autocomplete for CPT-onomies tags
	jQuery( '.cpt_onomies_tags_div' ).each( function() {
	
		var $post_id = jQuery( '#post_ID' ).val();
		var $post_type = jQuery( '#post_type' ).val();
		var $taxonomy = jQuery( this ).attr( 'id' ).replace( /^taxonomy-/i, '' );
				
		// these elements wont "exist" if user does not have capability to assign terms
		var $new_tag_input = jQuery( this ).find( 'input.cpt_onomies_new_tag' );
		var $add_tag_button = jQuery( this ).find( 'input.cpt_onomies_tag_add' );
		
		// this hidden element is used to save the tag id for terms added to the new tag input
		var $hidden_tag_id = 'custom_post_type_onomy_' + $taxonomy + '_selected_term_id';
		var $hidden_tag_parent = 'custom_post_type_onomy_' + $taxonomy + '_selected_term_parent';
		
		// this element stores our list of tags and shows up no matter what
		var $tag_checklist = jQuery( this ).find( '.cpt_onomies_tag_checklist' );
				
		// remove the hidden textarea to remove any $_POST confusion
		jQuery( this ).find( '.nojs-tags' ).remove();
		
		// autocomplete new tags
		if ( $new_tag_input.size() > 0 ) {
		
			$new_tag_input.autocomplete({
				delay: 200,
				source: function( $request, $response ){
					jQuery.ajax({
						url: ajaxurl,
						type: 'POST',
						async: true,
						cache: false,
						dataType: 'json',
						data: {
							action: 'custom_post_type_onomy_meta_box_autocomplete_callback',
							custom_post_type_onomies_taxonomy: $taxonomy,
							custom_post_type_onomies_term: $request.term,
							custom_post_type_onomies_post_type: $post_type,
							custom_post_type_onomies_post_id: $post_id
						},
						success: function( $data ){
							$response( jQuery.map( $data, function( $item ) {
								return {
									value: $item.value,
									label: $item.label,
									parent: $item.parent
								};
							}));
						}
					});
				},
				focus: function( $event, $ui ) {
					
					// change the input value
					// this approach allows us "html entity decode" the label        
					$new_tag_input.val( jQuery( '<div />' ).html( $ui.item.label ).text() );
					
					return false;
					
				},
				select: function( $event, $ui ) {
					
					// change the input value
					// this approach allows us "html entity decode" the label
					$new_tag_input.val( jQuery( '<div />' ).html( $ui.item.label ).text() );
					
					// store the ID
					if ( jQuery( '#' + $hidden_tag_id ).size() == 0 )
						$new_tag_input.after( '<input id="' + $hidden_tag_id + '" type="hidden" value="' + $ui.item.value + '" />' );
					else
						jQuery( '#' + $hidden_tag_id ).val( $ui.item.value );
					
					// if parent, then store parent
					if ( $ui.item.parent != '' && $ui.item.parent != null && $ui.item.parent != undefined ) {
						if ( jQuery( '#' + $hidden_tag_parent ).size() == 0 )
							$new_tag_input.after( '<input id="' + $hidden_tag_parent + '" type="hidden" value="' + $ui.item.parent + '" />' );
						else
							jQuery( '#' + $hidden_tag_parent ).val( $ui.item.parent );
					}
					
					// otherwise, remove the hidden input
					else
						jQuery( '#' + $hidden_tag_parent ).remove();
					
					return false;
					
				}
			}).data( 'ui-autocomplete' )._renderItem = function( $ul, $item ) {
				
				// add our class to the ul
				$ul.addClass( 'cpt_onomies' );
				
				// if parent, add parent
				$parent = '';
				if ( $item.parent != '' && $item.parent != null && $item.parent != undefined )
					$parent = '<span class="parent">' + $item.parent.replace( ',', '/ ' ) + '/</span>';
				
				// return item
	        	return jQuery( '<li></li>' )
	            	.data( 'item.autocomplete', $item )
	            	.append( '<a>' + $parent + $item.label + '</a>' )
	            	.appendTo( $ul );
	            
	        };
	    }
	    
	    // if typing, remove the error message
	    $new_tag_input.click( function() {
	    	// remove the error message
        	jQuery( '#cpt-onomies-add-tag-error-message-' + $taxonomy ).remove();
        });
        $new_tag_input.change( function() {
	    	// remove the error message
        	jQuery( '#cpt-onomies-add-tag-error-message-' + $taxonomy ).remove();
        });
        $new_tag_input.keyup( function() {
	    	// remove the error message
        	jQuery( '#cpt-onomies-add-tag-error-message-' + $taxonomy ).remove();
        });
	                    
        // add new tags
        $add_tag_button.click( function() {
        
        	var $term = $new_tag_input.val();
        	var $term_id = jQuery( '#' + $hidden_tag_id ).val();
        	var $term_parent = jQuery( '#' + $hidden_tag_parent ).val();
        	var $cpt_onomies_add_tag_error_message = '';
        	        	
        	// remove the error message
        	jQuery( '#cpt-onomies-add-tag-error-message-' + $taxonomy ).remove();
        	
        	// remove the hidden tag id
       		jQuery( '#' + $hidden_tag_id ).remove();
        	
        	// remove the hidden tag parent
       		jQuery( '#' + $hidden_tag_parent ).remove();
       		
       		// check to see if term exists if they typed in a term on their own
        	// this will retrieve term id AND also get term name if they typed in a slug
        	if ( $term == '' || ( !$term_id || $term_id == 0 || $term_id == '' ) ) {
        		jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					dataType: 'json',
					async: false,
					cache: false,
					data: {
						action: 'custom_post_type_onomy_check_if_term_exists',
						custom_post_type_onomies_term: $term,
						custom_post_type_onomies_term_id: $term_id,
						custom_post_type_onomies_taxonomy: $taxonomy,
						custom_post_type_onomies_get_parent_title: 'true'
					},
					success: function( $term_exists ) {
						jQuery.each( $term_exists, function( $index, $value ) {
							if ( $index == 'name' )
								$term = $value;
							else if ( $index == 'term_id' )
								$term_id = $value;
							else if ( $index == 'parent' )
								$term_parent = $value;
						});
					}
				});
        	}
       		        	
        	if ( $term && $term_id ) {
        	
        		// clear out the "new tags" input
        		$new_tag_input.val( '' );
        		
        		// we don't let posts create relationships with themselves
        		if ( $post_id == $term_id )
        			$cpt_onomies_add_tag_error_message = cpt_onomies_admin_post_L10n.no_self_relationship;
	        	
	        	// this relationship already exists
	        	else if ( jQuery.inArray( $term_id, $tag_checklist.data( 'selected_term_ids' ) ) >= 0 )
	        		$cpt_onomies_add_tag_error_message = cpt_onomies_admin_post_L10n.relationship_already_exists;
	        		
	        	// add tag
        		else
        			$tag_checklist.custom_post_type_onomies_tag_checklist_add_tag_term( $taxonomy, $term, $term_id, $term_parent );
	        	
	        }
	        	
	        // the term doesn't exist, so add message
	        else {
	        	
	        	// set error message
	        	$cpt_onomies_add_tag_error_message = cpt_onomies_admin_post_L10n.term_does_not_exist + ' <a href="post-new.php?post_type=' + $taxonomy + '" target="_blank">' + cpt_onomies_admin_post_L10n.add_a_term + '</a>';
	        	
	        }
	        
	        // add error message
	        if ( $cpt_onomies_add_tag_error_message != '' ) {
	        
	        	$cpt_onomies_add_tag_error_message = jQuery( '<div id="cpt-onomies-add-tag-error-message-' + $taxonomy + '" class="cpt_onomies_add_tag_error_message">' + $cpt_onomies_add_tag_error_message + ' <span class="close">' + cpt_onomies_admin_post_L10n.close + '</span></div>' );
	        
	        	$cpt_onomies_add_tag_error_message.click( function() {
	        		jQuery( this ).remove();
	        	});
	        	jQuery( this ).after( $cpt_onomies_add_tag_error_message );
	        		
	        }
        	        	
        });
                
        // going to store the selected terms
        $tag_checklist.data( 'selected_term_ids', [] );
       
       	// show loading while we're getting the terms 
        $tag_checklist.addClass( 'loading' );
        
        // add already selected terms
        jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			async: true,
			cache: false,
			data: {
				action: 'custom_post_type_onomy_get_wp_object_terms',
				custom_post_type_onomies_post_ids: $post_id,
				custom_post_type_onomies_taxonomies: $taxonomy,
				custom_post_type_onomies_get_parent_title: 'true'
			},
			success: function( $terms ) {
			
				// make sure we get rid of the loading icon
				$tag_checklist.removeClass( 'loading' );
				
				// if $terms is array and not empty
				if ( jQuery.isArray( $terms ) && $terms.length > 0 ) {		
					jQuery.each( $terms, function( $term_index, $term_info ) {
						var $term = '';
						var $term_id = '';
						var $term_parent = '';
						// get the term information
						jQuery.each( $term_info, function( $name, $value ) {
							if ( $term == '' || $term_id == '' || $term_parent == '' ) {
								if ( $term == '' && $name == 'name' )
									$term = $value;
								else if ( $term_id == '' && $name == 'term_id' )
									$term_id = $value;
								else if ( $term_parent == '' && $name == 'parent' )
									$term_parent = $value;
							}
						});
						// add to cpt_onomies_tag_checklist
						if ( $term != '' && $term_id != '' )
							$tag_checklist.custom_post_type_onomies_tag_checklist_add_tag_term( $taxonomy, $term, $term_id, $term_parent );
					});
				}
				// there are no terms and the user cant assign terms so let's put some text here so it isn't just blank
				else if ( !cpt_onomies_admin_post_data.can_assign_terms[ $taxonomy ] ) {
					if ( cpt_onomies_admin_post_L10n.no_terms_selected[ $taxonomy ] != '' )
						$no_terms_selected = cpt_onomies_admin_post_L10n.no_terms_selected[ $taxonomy ];
					else
						$no_terms_selected = 'There are no terms selected.';
					$tag_checklist.append( '<span class="description">' + $no_terms_selected + ' </span>' );
				}
				
			},
			error: function() {
				// make sure it gets removed no matter what
				$tag_checklist.removeClass( 'loading' );
			},
			complete: function() {
				// make sure it gets removed no matter what
				$tag_checklist.removeClass( 'loading' );
			}
		});
		
	});
			
});

// function invoked by tag checklist
jQuery.fn.custom_post_type_onomies_tag_checklist_add_tag_term = function( $taxonomy, $term, $term_id, $term_parent ) {

	// assign checklist
	var $tag_checklist = jQuery( this );

	// if not, then this term is already selected/added
	if ( jQuery.inArray( $term_id, $tag_checklist.data( 'selected_term_ids' ) ) < 0 ) {
	
		// add this term id to selected term ids
		$tag_checklist.data( 'selected_term_ids' ).push( $term_id );
		
		// create tag
	   	var $tag = jQuery( '<span class="tag"></span>' );
	   		
	   	// add field info
	   	$tag.append( '<input type="hidden" class="term_id" name="_custom_post_type_onomies_relationship[' + $taxonomy + '][]" value="' + $term_id + '" />' );
	   	   	
	   	// if the user has permission to assign terms
	   	var $tag_delete = null;
	   	if ( cpt_onomies_admin_post_data.can_assign_terms[ $taxonomy ] ) {
	   	
		   	// add delete button
		   	$tag_delete = jQuery( '<a id="' + $taxonomy + '-check-num-' + $tag_checklist.find( '.tag' ).size() + '" class="delbutton">X</a>' );
		   	$tag.append( $tag_delete );
		   	
		   	// assign tag delete event
		   	$tag.find( '.delbutton' ).click( function() {
		   		var $parent_tag = jQuery( this ).closest( 'span.tag' );
				
				// remove from data
				$tag_checklist.data( 'selected_term_ids' ).splice( jQuery.inArray( $parent_tag.find( 'input.term_id' ).val(), $tag_checklist.data( 'selected_term_ids' ) ), 1 );
				
				// remove tag
				$parent_tag.remove();
				
				// remove the error message
				jQuery( '#cpt-onomies-add-tag-error-message-' + $taxonomy ).remove();
				
			});
			
		}
	   		           	
	   	// add term
	   	var $tag_term = jQuery( '<span class="term">' + $term + '</span>' );
	   	// add parent
	   	if ( $term_parent != '' && $term_parent != null && $term_parent != undefined ) {
	   		$tag_term.prepend( '<span class="parent">' + $term_parent.replace( ',', '/ ' ) + '/</span>' );
	   	}
	   	$tag.append( $tag_term );
	   		        	
		// add tag
		$tag_checklist.append( $tag );
		
		// make sure tag fits
		var $tag_elements_width = $tag_term.width();
		var $tag_delete_width = 0;
		if ( $tag_delete ) {
			$tag_delete_width = $tag_delete.width() + parseInt( $tag_delete.css( 'margin-right' ) );
			$tag_elements_width += $tag_delete_width;
		}
		
		// if term is too big, adjust term width
		if ( $tag_elements_width > ( $tag.width() - 5 ) ) // 5 is for a little breathing room
			$tag_term.css({ 'width': ( $tag.width() - $tag_delete_width - 5 ) + 'px' });
			
		// if the term is STILL too long, i.e. one word and very long, then break the word-wrap
		if ( $tag_elements_width > ( $tag.width() - 5 ) ) // 5 is for a little breathing room
			$tag_term.css({ 'width': ( $tag.width() - $tag_delete_width - 5 ) + 'px', 'word-wrap': 'break-word' });
			
	}

}