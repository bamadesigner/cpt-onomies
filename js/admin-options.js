var $cpt_onomies_changed_form = 0;
jQuery.noConflict()(function(){
	
	// Count how many times the form is changed
	jQuery( 'form#custom-post-type-onomies-edit-cpt' ).change( function() {
		$cpt_onomies_changed_form++;
	});
	
	// Check to make sure info is saved before continuing
	jQuery( 'a:not(.delete_cpt_onomy_custom_post_type):not(.thickbox):not([target="_blank"])' ).live( 'click', function( $event ) {
		
		// If the form has been changed...
		if ( $cpt_onomies_changed_form ) {
			
			// Build the message
			var $message = null;
			
			if ( cpt_onomies_admin_options_L10n.unsaved_message1 != '' )
				$message = cpt_onomies_admin_options_L10n.unsaved_message1
			else
				$message = 'It looks like you might have some unsaved changes.';
			
			if ( cpt_onomies_admin_options_L10n.unsaved_message2 != '' )
				$message += '\n' + cpt_onomies_admin_options_L10n.unsaved_message2
			else
				$message += '\nAre you sure you want to leave?';
			
			// Ask the user to confirm
			var $confirm = confirm( $message );
			
			// If they confirmed, stop the event from happening
			if ( $confirm != true )
				$event.preventDefault();
				
		}
		
	});	
	
	// Show message
	jQuery( '.show_cpt_message' ).live( 'click', function( $event ) {
		
		// Stop the event from happening
		$event.preventDefault();
		
		// Show the message
		alert( jQuery( this ).attr( 'alt' ) );
		
	});
	
	// Show "delete conflicting terms" confirmation
	jQuery( '.delete-conflicting-tax-terms.button' ).on( 'click', function( $event ) {
		
		// Build the message
		var $message = null;
		
		if ( cpt_onomies_admin_options_L10n.delete_conflicting_terms_message1 != '' )
			$message = cpt_onomies_admin_options_L10n.delete_conflicting_terms_message1;
		else
			$message = 'Are you sure you want to delete the conflicting taxonomy terms?';
		
		if ( cpt_onomies_admin_options_L10n.delete_conflicting_terms_message2 != '' )
			$message += '\n\n' + cpt_onomies_admin_options_L10n.delete_conflicting_terms_message2;
		else
			$message += '\n\nThere is NO undo and once you click "OK", all of the terms will be deleted and cannot be restored.';
			
		// Ask the user to confirm
		var $confirm = confirm( $message );
		
		// If they confirmed, stop the event from happening
		if ( $confirm != true )
			$event.preventDefault();
		
	});
		
	// Show delete confirmation
	jQuery( '.delete_cpt_onomy_custom_post_type' ).live( 'click', function( $event ) {
		
		// Build the message
		var $message = null;
		
		if ( cpt_onomies_admin_options_L10n.delete_message1 != '' )
			$message = cpt_onomies_admin_options_L10n.delete_message1;
		else
			$message = 'Are you sure you want to delete this custom post type?';
		
		if ( cpt_onomies_admin_options_L10n.delete_message2 != '' )
			$message += '\n\n' + cpt_onomies_admin_options_L10n.delete_message2;
		else
			$message += '\n\nThere is NO undo and once you click "OK", all of your settings will be gone.';
		
		if ( cpt_onomies_admin_options_L10n.delete_message3 != '' )
			$message += '\n\n' + cpt_onomies_admin_options_L10n.delete_message3;
		else
			$message += '\n\nDeleting your custom post type DOES NOT delete the actual posts.';
		
		if ( cpt_onomies_admin_options_L10n.delete_message4 != '' )
			$message += ' ' + cpt_onomies_admin_options_L10n.delete_message4;
		else
			$message += ' They\'ll be waiting for you if you decide to register this post type again.';
		
		if ( cpt_onomies_admin_options_L10n.delete_message5 != '' )
			$message += ' ' + cpt_onomies_admin_options_L10n.delete_message5;
		else
			$message += ' Just make sure you use the same name.';
		
		// Ask the user to confirm
		var $confirm = confirm( $message );
		
		// If they confirmed, stop the event from happening
		if ( $confirm != true )
			$event.preventDefault();
			
	});
	
	// Change the header label
	var $default_header_label = jQuery( '#custom-post-type-onomies-edit-header .label' ).html();
	var $cpt_label = jQuery( '#custom-post-type-onomies-custom-post-type-label' );
	
	// Make sure the label is checked out the gate
	$cpt_label.custom_post_type_onomies_change_header_label( $default_header_label );
	$cpt_label.keyup( function() {
		jQuery( this ).custom_post_type_onomies_change_header_label( $default_header_label );
	});
	$cpt_label.change( function() {
		jQuery( this ).custom_post_type_onomies_change_header_label( $default_header_label );
	});

	$cpt_label.blur( function() {
		jQuery( this ).custom_post_type_onomies_change_header_label( $default_header_label );
	});
	
	// Create a field name
	var $cpt_name = jQuery( 'input#custom-post-type-onomies-custom-post-type-name' );
	
	// Make sure the name is created out the gate, if need be
	$cpt_name.custom_post_type_onomies_create_name( $cpt_label.val() );
	$cpt_label.change( function() {
		$cpt_name.custom_post_type_onomies_create_name( jQuery( this ).val() );
	});
		
	// Dim post type name when not "active"
	$cpt_name.addClass( 'inactive' );
	$cpt_name.focus( function() {
		jQuery( this ).removeClass( 'inactive' );
	});
	$cpt_name.blur( function() {
		jQuery( this ).addClass( 'inactive' );
	});
	
	// Reset properties
	jQuery( '#custom-post-type-onomies-edit-table .reset_property' ).live( 'click', function() {
		jQuery( this ).closest( 'table' ).find( 'input[type="radio"]:checked' ).removeAttr( 'checked' );
	});
	
	// Take care of any dismiss messages
	jQuery( '.dismiss' ).each( function() {
		jQuery( this ).custom_post_type_onomies_setup_dismiss();
	});
	
	// Take care of the advanced open-close and messages
	jQuery( '#custom-post-type-onomies-edit-table td.advanced' ).each( function() {
		jQuery( this ).children( 'table' ).custom_post_type_onomies_setup_advanced_table();		
	});
	
	// Open the help tab
	jQuery( '.custom_post_type_onomies_show_help_tab' ).live( 'click', function( $event ) {
		
		// Stop the event from happening
		$event.preventDefault();
		
		// Define the help tab
		var $panel = jQuery( '#contextual-help-wrap' );
		if ( ! $panel.length )
			return;

		// Open help tab
		if ( ! $panel.is( ':visible' ) ) {
			
			// Scroll to top of page
			jQuery( 'html,body' ).scrollTop( 0 );
			
			// Define the "Help" tab link
			var $link = jQuery( '#contextual-help-link' );
			
			// Hide any other tab links
			jQuery( '.screen-meta-toggle' ).not( $link.parent() ).css( 'visibility', 'hidden' );

			// Show the help tab
			$panel.parent().show();
			$panel.slideDown( 'fast', function() {
				
				// Make the tab link active
				$link.addClass('screen-meta-active');
				
			});
		
		}
		
	});
	
});

jQuery.fn.custom_post_type_onomies_change_header_label = function( $default_header_label ) {
	
	if ( jQuery( this ).val() != '' )
		jQuery( '#custom-post-type-onomies-edit-header .label' ).html( jQuery( this ).val() );
	else if ( $default_header_label != '' )
		jQuery( '#custom-post-type-onomies-edit-header .label' ).html( $default_header_label );
	
}

jQuery.fn.custom_post_type_onomies_create_name = function( $label_value ) {
	
	// If name is blank, convert label value to name
	if ( jQuery( this ).val() == '' )
		jQuery( this ).val( $label_value.replace( /[^a-zA-Z0-9\_\s]/i, '' ).replace( /\s/, '_' ).toLowerCase() );
	
}

jQuery.fn.custom_post_type_onomies_setup_dismiss = function() {
	
	var $dismiss = jQuery( this );
	var $dismiss_id = jQuery( this ).attr( 'id' );
	var $close = jQuery( '<span class="close">x</span>' );
	
	$close.click( function() {
		
		// Remove message
		jQuery( this ).parent( '.dismiss' ).remove();
		
		// Update user options
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			async: false,
			cache: false,
			data: {
				action: 'custom_post_type_onomy_update_edit_custom_post_type_dismiss',
				custom_post_type_onomies_dismiss_id: $dismiss_id
			}
		});
		
	});
	
	$dismiss.append( $close );
	
}

jQuery.fn.custom_post_type_onomies_setup_advanced_table = function() {
	
	var $advanced = jQuery( this );
	
	if ( $advanced.closest( '#custom-post-type-onomies-edit-table' ).hasClass( 'show' ) )
		$advanced.custom_post_type_onomies_show_advanced_table();
	else
		$advanced.custom_post_type_onomies_hide_advanced_table();
	
}

jQuery.fn.custom_post_type_onomies_show_advanced_table = function() {
	
	// Set the "advanced" table
	var $advanced = jQuery( this );
	
	// "Show" the table
	$advanced.removeClass( 'hide' );
	
	// Get edit table
	var $edit_table = null;
	
	if ( $advanced.closest( '#custom-post-type-onomies-edit-table' ).hasClass( 'site_registration' ) )
		$edit_table = 'site_registration';
	else if ( $advanced.closest( '#custom-post-type-onomies-edit-table' ).hasClass( 'labels' ) )
		$edit_table = 'labels';
	else
		$edit_table = 'options';
		
	// Create close message
	var $close_message = null;
	
	if ( $edit_table == 'site_registration' ) {
	
		if ( cpt_onomies_admin_options_L10n.close_site_registration != '' )
			$close_message = cpt_onomies_admin_options_L10n.close_site_registration;
		else
			$close_message = 'Close Site Registration';
			
	} else if ( $edit_table == 'labels' ) {
	
		if ( cpt_onomies_admin_options_L10n.close_labels != '' )
			$close_message = cpt_onomies_admin_options_L10n.close_labels;
		else
			$close_message = 'Close Labels';
	
	} else if ( $edit_table == 'options' ) {
	
		if ( cpt_onomies_admin_options_L10n.close_advanced_options != '' )
			$close_message = cpt_onomies_admin_options_L10n.close_advanced_options;
		else 
			$close_message = 'Close Advanced Options';
			
	} else {
		
		$close_message = 'Close';
		
	}
		
	$close_message = '<span class="close_advanced">' + $close_message + '</span>';
	
	// Add close message	
	if ( $advanced.parent( '.advanced_message' ).size() > 0 )
		$advanced.parent( '.advanced_message' ).remove();
	$advanced.closest( 'td' ).prepend( '<span class="advanced_message">' + $close_message + '</span>' );
	
	// If they click "close"
	$advanced.closest( 'td' ).children( '.advanced_message' ).children( '.close_advanced' ).click( function() {
		
		// Remove advanced message and close table
		jQuery( this ).parent( '.advanced_message' ).remove();	
		$advanced.custom_post_type_onomies_hide_advanced_table();	
		
		// Update user options
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			async: false,
			cache: false,
			data: {
				action: 'custom_post_type_onomy_update_edit_custom_post_type_closed_edit_tables',
				custom_post_type_onomies_edit_table: $edit_table,
				custom_post_type_onomies_edit_table_show: 'false'
			}
		});
		
	});	
		
}

jQuery.fn.custom_post_type_onomies_hide_advanced_table = function() {

	// Set the "advanced" table
	var $advanced = jQuery( this );
	
	// "Hide" the table
	$advanced.addClass( 'hide' );
	
	// Get edit table
	var $edit_table = null;
	if ( $advanced.closest( '#custom-post-type-onomies-edit-table' ).hasClass( 'site_registration' ) )
		$edit_table = 'site_registration';
	else if ( $advanced.closest( '#custom-post-type-onomies-edit-table' ).hasClass( 'labels' ) )
		$edit_table = 'labels';
	else
		$edit_table = 'options';
		
	// Create message
	var $message = null;
	
	// Add message 1 for the site registration table
	if ( $edit_table == 'site_registration' ) {
		
		if ( cpt_onomies_admin_options_L10n.site_registration_message1 != '' )
			$message = cpt_onomies_admin_options_L10n.site_registration_message1;
		else
			$message = 'If you want to register your custom post type on multiple sites, but not the entire network, this section is for you. However, your list of sites is kind of long so we hid it away as to not clog up your screen.';
			
	}
	
	// Add message 1 for the labels table
	else if ( $edit_table == 'labels' ) {
		
		if ( cpt_onomies_admin_options_L10n.labels_message1 != '' )
			$message = cpt_onomies_admin_options_L10n.labels_message1;
		else
			$message = 'Instead of sticking with the boring defaults, why don\'t you customize the labels used for your custom post type. They can really add a nice touch.';
			
	}
	
	// Add message 1 for the advanced options table
	else if ( $edit_table == 'options' ) {
		
		if ( cpt_onomies_admin_options_L10n.advanced_options_message1 != '' )
			$message = cpt_onomies_admin_options_L10n.advanced_options_message1;
		else
			$message = 'You can make your custom post type as "advanced" as you like but, beware, some of these options can get tricky. Visit the "Help" tab if you get stuck.';
		
	}
		
	// Add links to message
	$message += ' <span class="show_advanced">';

		if ( $edit_table == 'site_registration' ) {
		
			if ( cpt_onomies_admin_options_L10n.site_registration_message2 != '' )
				$message += cpt_onomies_admin_options_L10n.site_registration_message2;
			else
				$message += 'Customize the Sites';
				
		} else if ( $edit_table == 'labels' ) {
			
			if ( cpt_onomies_admin_options_L10n.labels_message2 != '' )
				$message += cpt_onomies_admin_options_L10n.labels_message2;
			else
				$message += 'Customize the Labels';
				
		} else if ( $edit_table == 'options' ) {
		
			if ( cpt_onomies_admin_options_L10n.advanced_options_message2 != '' )
				$message += cpt_onomies_admin_options_L10n.advanced_options_message2;
			else
				$message += 'Edit the Advanced Options';
				
		}
			
	$message += '</span>';
	
	// Add message
	if ( $advanced.parent( '.advanced_message' ).size() > 0 )
		$advanced.parent( '.advanced_message' ).remove();
	$advanced.closest( 'td' ).prepend( '<span class="advanced_message">' + $message + '</span>' );
	
	// If they click "show"
	$advanced.closest( 'td' ).children( '.advanced_message' ).children( '.show_advanced' ).click( function() {
		
		// Remove advanced message and show table
		jQuery( this ).parent( '.advanced_message' ).remove();	
		$advanced.custom_post_type_onomies_show_advanced_table();
		
		// Update user options
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			async: false,
			cache: false,
			data: {
				action: 'custom_post_type_onomy_update_edit_custom_post_type_closed_edit_tables',
				custom_post_type_onomies_edit_table: $edit_table,
				custom_post_type_onomies_edit_table_show: 'true'
			}
		});
		
	});
	
}