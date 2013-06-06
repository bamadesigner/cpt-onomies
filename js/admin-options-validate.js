jQuery.noConflict()(function(){
		
	// validate form
	jQuery( 'form#custom-post-type-onomies-edit-cpt' ).validate({
		onkeyup: false,
		ignore: []
	});
	
	// create invalid post type name message
	$invalid_post_type_name = null
	if ( cpt_onomies_admin_options_L10n.invalid_post_type_name != '' )
		$invalid_post_type_name = cpt_onomies_admin_options_L10n.invalid_post_type_name;
	else
		$invalid_post_type_name = 'Your post type name is invalid.';
	
	// validate custom post type name to make sure it contains valid characters
	jQuery.validator.addMethod( 'custom_post_type_onomies_validate_name_characters', function( value, element ) {
		return this.optional( element ) || ( value.length <= 20 && !value.match( /([^a-z0-9\_])/ ) );
	}, $invalid_post_type_name );
	
	// create post type name exists message
	$post_type_name_exists = null;
	if ( cpt_onomies_admin_options_L10n.post_type_name_exists != '' )
		$post_type_name_exists = cpt_onomies_admin_options_L10n.post_type_name_exists;
	else
		$post_type_name_exists = 'That post type name already exists. Please choose another name.';
		
	// validate custom post type name to make sure post type doesnt already exist
	jQuery.validator.addMethod( 'custom_post_type_onomies_validate_name', function( value, element ) {
		var validator = this, response;
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			async: false,
			cache: false,
			data: {
				action: 'custom_post_type_onomy_validate_if_post_type_exists',
				custom_post_type_onomies_is_network_admin: jQuery( "#custom-post-type-onomies-is-network-admin" ).val(),
				original_custom_post_type_onomies_cpt_name: jQuery( "#custom-post-type-onomies-custom-post-type-original-name" ).val(),
				custom_post_type_onomies_cpt_name: value
			},
			success: function( data ) {
				response = ( data == 'true' ) ? true : false;
			},
			complete: function() {
				response = ( response == null ) ? true : response;
			}
		});		
		return response;
	}, $post_type_name_exists );
	
});