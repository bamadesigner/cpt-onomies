<?php

/*
 * Hooks into the "Gravity Forms + Custom Post Types" plugin, which is an add on
 * to the "Gravity Forms" plugin, which "Allows a simple way to map a Gravity Form
 * post entry to a custom post type. Also include custom taxonomies." This plugin
 * allows you to create taxonomy relationships via a front-end form.
 *
 * However, because this plugin uses wp_set_object_terms() which CPT-onomies is
 * not (yet) able to hook into, CPT-onomies can be added to the form but the
 * relationships are not created when the form is submitted. This hook allows
 * CPT-onomies to create the relationships for the plugin.
 *
 * For More Information:
 * Gravity Forms: http://www.gravityforms.com/
 * Gravity Forms + Custom Post Types - http://themergency.com/plugins/gravity-forms-custom-post-types/
 */ 
add_action( 'gform_post_submission', 'custom_post_type_onomies_gform_post_submission_save_taxonomies', 10, 2);
function custom_post_type_onomies_gform_post_submission_save_taxonomies( $entry, $form ) {
	// Check if the class exists and the submission contains a WordPress post
    if ( class_exists( 'GFCPTAddon1_5' ) && isset ( $entry['post_id'] ) ) {
    	$GFCPTAddon1_5 = new GFCPTAddon1_5();
    	foreach( $form['fields'] as &$field ) {
    		if ( $taxonomy = $GFCPTAddon1_5->get_field_taxonomy( $field ) )
    			custom_post_type_onomies_gform_post_submission_save_taxonomy_field( $field, $entry, $taxonomy );
    	}
    }
}

/*
 * Takes taxonomy information from a submitted "Gravity Forms + Custom Post Types"
 * form and sets CPT-onomy relationships.
 */
function custom_post_type_onomies_gform_post_submission_save_taxonomy_field( &$field, $entry, $taxonomy ) {
	global $cpt_onomies_manager, $cpt_onomy;
	// make sure the taxonomy is a registered CPT-onomy
	if ( $cpt_onomies_manager->is_registered_cpt_onomy( $taxonomy ) ) {
		if ( array_key_exists( 'type', $field ) && $field[ 'type' ] == 'checkbox' ) {
			$term_ids = array();
			foreach ( $field[ 'inputs' ] as $input ) {
				$term_id = (int) $entry[ (string) $input[ 'id' ] ];
                if ( $term_id > 0 )
                	$term_ids[] = $term_id;
            }
            if ( ! empty ( $term_ids ) )
            	$cpt_onomy->wp_set_object_terms( $entry[ 'post_id' ], $term_ids, $taxonomy, true );
        } else if ( array_key_exists( 'type', $field ) && $field[ 'type' ] == 'text' ) {
        	$terms = $entry[ $field[ 'id' ] ];
            if ( ! empty( $terms ) )
            	$cpt_onomy->wp_set_post_terms( $entry[ 'post_id' ], $terms, $taxonomy );
        } else {
        	$term_id = (int) $entry[ $field[ 'id' ] ];
        	if ( $term_id > 0 )
        		$cpt_onomy->wp_set_object_terms( $entry[ 'post_id' ], $term_id, $taxonomy, true );
        }
	}
}

?>