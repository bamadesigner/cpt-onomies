<?php

global $wpdb;

// if uninstall not called from the WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();
	
// delete individual site options and postmeta
$blogs = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM " . $wpdb->blogs . " ORDER BY blog_id", NULL ) );
foreach( $blogs as $this_blog_id ) {

	// set blog id so $wpdb will know which table to tweak
	$wpdb->set_blog_id( $this_blog_id );
	
	// delete site options
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'custom_post_type_onomies_custom_post_types' ) );
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'custom_post_type_onomies_other_custom_post_types' ) );
	
	// delete post meta
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_custom_post_type_onomies_relationship' ) );
	
}

// delete network options
delete_site_option( 'custom_post_type_onomies_custom_post_types' );

// delete user options
$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->usermeta . " WHERE meta_key IN ( 'custom_post_type_onomies_dismiss', 'wp_custom_post_type_onomies_show_edit_tables', 'custom_post_type_onomies_show_edit_tables' )", NULL ) );
	
?>