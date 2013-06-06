=== CPT-onomies: Using Custom Post Types as Taxonomies ===
Contributors: bamadesigner
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=bamadesigner%40gmail%2ecom&lc=US&item_name=Rachel%20Carden%20%28CPT%2donomies%29&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: custom post type, custom, post, post type, types, tax, taxonomy, taxonomies, cpt-onomy, cpt-onomies, cptonomies, custom post type taxonomies, custom post type as taxonomy, custom post types as taxonomies, relationships, relate, multisite
Requires at least: 3.1
Tested up to: 3.5.1
Stable tag: 1.3

Use your custom post types as taxonomies. Create powerful relationships between your posts and, therefore, powerful content.

== Description ==

*CPT-onomies* is a **multisite compatible** WordPress plugin that allows you to create very powerful taxonomies and, therefore, very powerful relationships between your posts.

CPT-onomies are Custom-Post-Type-powered taxonomies that function just like regular WordPress taxonomies, even allowing you to use core WordPress taxonomy functions, such as [get_terms()](http://codex.wordpress.org/Function_Reference/get_terms "get_terms()") and [wp_get_object_terms()](http://codex.wordpress.org/Function_Reference/wp_get_object_terms "wp_get_object_terms()"). *[Check out the CPT-onomy documentation](http://rachelcarden.com/cpt-onomies/documentation "CPT-onomy documentation") to see which core WordPress taxonomy functions work and when you'll need to access the plugin's CPT-onomy functions.*

CPT-onomies includes a custom post type manager, allowing you to create custom post types and register CPT-onomies **without touching one line of code!**

**If you're running a WordPress multisite network**, you can register your custom post types/CPT-onomies across your entire network OR on a site-by-site selection. All from one screen!

*If you're already using a plugin, or theme, that creates custom post types, don't worry, CPT-onomies is all-inclusive.* **Any registered custom post type can be used as a CPT-onomy.**

= What Is A CPT-onomy? =

A CPT-onomy is a Custom-Post-Type-powered taxonomy that functions just like a regular WordPress taxonomy, using your post titles as your taxonomy terms. "Attach", or register, your CPT-onomy to any post type and create relationships between your posts, just as you would create taxonomy relationships. Need to associate a CPT-onomy term with its post? No problem! **The CPT-onomy term's term ID is the same as the post ID.**

= Is CPT-onomy An Official WordPress Term? =

No. It's just a fun word I made up.

= Need Custom Post Types But Not (Necessarily) CPT-onomies? =

CPT-onomies offers an extensive, **and multisite compatible**, custom post type manager, allowing you to create and completely customize your custom post types within the admin.

= Why CPT-onomies? =

It doesn't take long to figure out that custom post types can be a pretty powerful tool for creating and managing numerous types of content. For example, you might use the custom post types "Movies" and "Actors" to build a movie database but what if you wanted to group your "movies" by its "actors"? You could create a custom "actors" taxonomy but then you would have to manage your list of actors in two places: your "actors" custom post type and your "actors" taxonomy. This can be a pretty big hassle, especially if you have an extensive custom post type.

**This is where CPT-onomies steps in.** Register your custom post type, 'Actors', as a CPT-onomy and CPT-onomies will build your 'actors' taxonomy for you, using your actors' post titles as the terms. Pretty cool, huh?

= Using CPT-onomies =

What's really great about CPT-onomies is that they function just like any other taxonomy, allowing you to use WordPress taxonomy functions, like [get_terms()](http://codex.wordpress.org/Function_Reference/get_terms "get_terms()"), [get_the_terms()](http://codex.wordpress.org/Function_Reference/get_the_terms "get_the_terms()") and [wp_get_object_terms()](http://codex.wordpress.org/Function_Reference/wp_get_object_terms "wp_get_object_terms()"), to access the CPT-onomy information you need. CPT-onomies will also work with tax queries when using [The Loop](http://rachelcarden.com/cpt-onomies/documentation/The_Loop/ "The WordPress Loop"), help you build [custom CPT-onomy archive pages](http://rachelcarden.com/cpt-onomies/documentation/custom-archive-pages/ "Custom CPT-onomy Archive Pages"), allow you to [programmatically register your CPT-onomies](http://rachelcarden.com/cpt-onomies/documentation/register_cpt_onomy/), and includes a tag cloud widget for your sidebar. [Check out the CPT-onomies documentation](http://rachelcarden.com/cpt-onomies/documentation/ "CPT-onomies Documentation") for more information.

If you're not sure what a taxonomy is, how to use one, or if it's right for your needs, be sure to do some research. [The WordPress Codex page for taxonomies](http://codex.wordpress.org/Taxonomies) is a great place to start!

***Note:** Unfortunately, not every taxonomy function can be used at this time. [Check out the CPT-onomy documentation](http://rachelcarden.com/cpt-onomies/documentation "CPT-onomy documentation") to see which WordPress taxonomy functions work and when you'll need to access the plugin's CPT-onomy functions.*

== Installation ==

1. Upload 'cpt-onomies' to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to *Settings > CPT-onomies*
1. Create a new custom post type or edit an existing custom post type
1. Register your custom post type as a CPT-onomy by "attaching" it to a post type, under "Register this Custom Post Type as a CPT-onomy" on the edit screen
1. Use your CPT-onomy just like any other taxonomy (refer to the [CPT-onomy documentation](http://rachelcarden.com/cpt-onomies/documentation "CPT-onomy documentation") for help)

== Frequently Asked Questions ==

= Why does CPT-onomies not work with all of the WordPress taxonomy functions? =

Good question. While CPT-onomies strives to mimic taxonomy functionality as much as possible, CPT-onomies are not stored in the database in the same manner as taxonomies. The simplest answer to "Why not?" is that I believe my current method of *"not duplicating post information to resave as taxonomy information"* is in the best all-around interest of not only my plugin, but also your web site. With that said, I am constantly searching for new ways to "hook" into WordPress to improve CPT-onomy/taxonomy integration and, when impossible, will [continue to provide workarounds](http://rachelcarden.com/cpt-onomies/documentation/ "CPT-onomies Documentation").

= How do I associate a CPT-onomy term with it's matching post? =

Another good question, with a very simple answer: the term ID. CPT-onomies return the same information as taxonomies, including a term ID. A CPT-onomy term's term ID is the same as its post's post ID.

= I'm not able to save my custom post type because the page keeps telling me "That post type name already exists." =

This is a jQuery "bug" that only seems to plague a few. I've noticed that this validation standstill will occur if you have any text printed outside the `<body>` element on your page. If that's not the case, and the problem still lingers after you've upgraded to version 1.1, you can dequeue the validation script by placing the following code in your functions.php file:

`<?php
add_action( 'admin_head', 'my_website_admin_head' );
function my_website_admin_head() {
	wp_dequeue_script( 'custom-post-type-onomies-admin-options-validate' );
}
?>`

= When assigning my CPT-onomy terms, I see a checklist but I would like to use the autocomplete box (or a select dropdown). =

***As of version 1.3, you can change the format for your meta boxes via the settings page!*** The following filter still works, though, so feel free to use as you please. It will overwrite the settings.

If you have a hierarchical CPT-onomy, the default selection format is a checklist. But if you would rather use the autocomplete box, or a select dropdown, CPT-onomies allows you to hook into the meta box (via a filter) and overwrite the default selection format.

Here's an example of the filter. More information, check out the "Help" tab in the CPT-onomies settings or [visit the FAQ on my web site](http://rachelcarden.com/cpt-onomies/faq/ "visit the FAQ on my web site").

`<?php
add_filter( 'custom_post_type_onomies_meta_box_format', 'my_website_custom_post_type_onomies_meta_box_format', 1, 3 );
function my_website_custom_post_type_onomies_meta_box_format( $format, $taxonomy, $post_type ) {
   // when editing a post with the post type 'movies',
   // we want to assign the 'actors' CPT-onomy terms with an autocomplete box
   if ( $post_type == 'movies' && $taxonomy == 'actors' )
      return 'autocomplete';
   // no matter the post type, we want to assign the 'actors' CPT-onomy terms with a select dropdown
   elseif ( $taxonomy == 'actors' )
      return 'dropdown';
   // no matter the post type, we want to assign the 'directors' CPT-onomy terms with a checklist
   elseif ( $taxonomy == 'directors' )
      return 'checklist';
   // WordPress filters must always return a value
   return $format;
}
?>`

= I added support for "Thumbnail" to my custom post type, but the "Featured Image" box does not show up =

You also have to add theme support for post thumbnails to your functions.php file:

`<?php add_theme_support( 'post-thumbnails' ); ?>`

If FAQ didn't cover your problem, refer to the following resources:

* [CPT-onomies Support Forums](http://wordpress.org/support/plugin/cpt-onomies "CPT-onomies Support Forums")
* [CPT-onomies Documentation](http://rachelcarden.com/cpt-onomies/documentation "CPT-onomies Documentation")

== Screenshots ==

1. CPT-onomies offers an extensive custom post type manager, allowing you to create new custom post types or use custom post types created by themes and other plugins.
2. CPT-onomies lets you manage and customize your custom post types without touching one line of code.
3. Create your custom post types to power your CPT-onomy.
4. Assign your CPT-onomy terms just like any other taxonomy.
5. The admin allows you to sort and filter your posts by CPT-onomy terms.

== Changelog ==

= 1.3 =
* Added multisite custom post type manager.
* Added setting to assign the meta box format, i.e. autocomplete, checklist or dropdown.
* Added "Show Admin Column" to the CPT-onomy settings.
* Deprecated the ability to make the CPT-onomy admin columns sortable in order to align with new, default WP taxonomy admin column functionality.
* Deprecated the 'custom_post_type_onomies_add_cpt_onomy_admin_sortable_column' filter.
* Added support for the "Gravity Forms + Custom Post Types" plugin.
* Added the ability to only include/assign specific terms by passing term IDs to a filter. See documentation for more information.
* Added wp_set_post_terms() to the CPT-onomy class.

= 1.2.1 =
* Cleaned up/fixed a bug with $cpt_onomy->wp_set_object_terms().
* Fixed a bug when assigning terms to CPTs with long names.
* Fixed a bug when excluding terms from being assigned to new posts via checklist.
* Fixed bug that sometimes caused fatal error during activation.
* Added the exclude terms filter to the bulk and quick edit.

= 1.2 =
* Minor bug fixes.
* Custom archive pages can be created pretty easily. See documentation for more information.
* Non-public custom post types can now be used as CPT-onomies.
* Added the ability to customize settings by removing options and setting default property values using various filters. See documentation for more information.
* Added the ability to exclude terms from being assigned by passing term IDs to filter. See documentation for more information.
* Added the ability to remove assign CPT-onomy terms meta box from edit screen via filter. See documentation for more information.
* Added the ability to remove CPT-onomy dropdown filter from admin manage custom post type screen via filter. See documentation for more information.
* Added the ability to remove CPT-onomy column (and/or it's sortability) from admin manage custom post type screen via filter. See documentation for more information.
* Fixed a bug with the capability type setting. *BE SURE TO RE-SAVE YOUR SETTINGS IF YOU USE THIS PROPERTY.*
* Fixed a bug with the 'read_private_posts' CPT capability. *BE SURE TO RE-SAVE YOUR SETTINGS IF YOU USE THIS PROPERTY.*
* Changed cpt_onomy.php filename to cpt-onomy.php to match cpt-onomies.php. I'm not sure why I gave it an underscore to begin with.
* Added the ability to set CPT-onomy term description using 'term_description' or '{$taxonomy}_description' filter'.

= 1.1.1 =
* Fixed bug with autocomplete box.
* Fixed bug when editing "other" custom post types.
* Fixed bug with custom CPT-onomy archive slug.

= 1.1 =
* Added support to programmatically register CPT-onomies.
* Added support for autocomplete and dropdown CPT-onomy term selection.
* Added support to customize the CPT-onomy archive page slug.
* Added support to change term link in tag cloud widget.
* Added support to exclude term ids from wp_get_object_terms().
* Added get_term_ancestors() to the CPT-onomy class.
* Added support for Internationalization.
* Tweaked the UI.
* Fixed a few bugs.

= 1.0.3 =
* **DO NOT UPDATE IF YOU ARE NOT USING WORDPRESS 3.1 OR NEWER!!** If you're using a version older than 3.1., and having issues, download CPT-onomies 1.0.2. for bug fixes.
* Added support for Bulk Edit and Quick Edit.
* Added the ability to sort and filter by CPT-onomy on the admin "Edit Posts" screen.
* Fixed a bug where tax queries wouldn't work with CPT-onomies AND taxonomies.
* Fixed a bug with wp_get_object_terms() not working for multiple object IDs.
* Fixed a bug with custom 'Has Archive' slugs not registering correctly.
* Added backwards compatability/fixed bug for adding the help tab prior to WordPress version 3.3.

= 1.0.2 =
* Fixed a few bugs with the "Restrict User's Capability to Assign Term Relationships" feature.
* The WordPress function, wp_count_terms(), now works with CPT-onomies and doesn't require the CPT-onomy class.
* Added get_objects_in_term() to the CPT-onomy class.
* Added previous_post_link(), next_post_link(), adjacent_post_link(), prev_post_rel_link(), next_post_rel_link(), get_adjacent_post_rel_link() and get_adjacent_post() to the CPT-onomy class with the ability to designate "in the same CPT-onomy".
* Added support for tax queries when using The Loop.

= 1.0.1 =
* Fixed bug that didn't delete relationships when CPT-onomy "term" is deleted.

= 1.0 =
* Plugin launch!

== Upgrade Notice ==

= 1.3 =
* Added multisite custom post type manager.
* Added setting to assign the meta box format, i.e. autocomplete, checklist or dropdown.
* Added "Show Admin Column" to the CPT-onomy settings.
* Deprecated the ability to make the CPT-onomy admin columns sortable in order to align with new, default WP taxonomy admin column functionality.
* Deprecated the 'custom_post_type_onomies_add_cpt_onomy_admin_sortable_column' filter.
* Added support for the "Gravity Forms + Custom Post Types" plugin.
* Added the ability to only include/assign specific terms by passing term IDs to a filter. See documentation for more information.
* Added wp_set_post_terms() to the CPT-onomy class.

= 1.2.1 =
* Cleaned up/fixed a bug with $cpt_onomy->wp_set_object_terms().
* Fixed a bug when assigning terms to CPTs with long names.
* Fixed a bug when excluding terms from being assigned to new posts via checklist.
* Fixed bug that sometimes caused fatal error during activation.
* Added the exclude terms filter to the bulk and quick edit.

= 1.2 =
* Minor bug fixes.
* Custom archive pages can be created pretty easily. See documentation for more information.
* Non-public custom post types can now be used as CPT-onomies.
* Added the ability to customize settings by removing options and setting default property values using various filters. See documentation for more information.
* Added the ability to exclude terms from being assigned by passing term IDs to filter. See documentation for more information.
* Added the ability to remove assign CPT-onomy terms meta box from edit screen via filter. See documentation for more information.
* Added the ability to remove CPT-onomy dropdown filter from admin manage custom post type screen via filter. See documentation for more information.
* Added the ability to remove CPT-onomy column (and/or it's sortability) from admin manage custom post type screen via filter. See documentation for more information.
* Fixed a bug with the capability type setting. *BE SURE TO RE-SAVE YOUR SETTINGS IF YOU USE THIS PROPERTY.*
* Fixed a bug with the 'read_private_posts' CPT capability. *BE SURE TO RE-SAVE YOUR SETTINGS IF YOU USE THIS PROPERTY.*
* Changed cpt_onomy.php filename to cpt-onomy.php to match cpt-onomies.php. I'm not sure why I gave it an underscore to begin with.
* Added the ability to set CPT-onomy term description using 'term_description' or '{$taxonomy}_description' filter'.

= 1.1.1 =
* Fixed bug with autocomplete box.
* Fixed bug when editing "other" custom post types.
* Fixed bug with custom CPT-onomy archive slug.

= 1.1 =
* Added support to programmatically register CPT-onomies.
* Added support for autocomplete and dropdown CPT-onomy term selection.
* Added support to customize the CPT-onomy archive page slug.
* Added support to change term link in tag cloud widget.
* Added support to exclude term ids from wp_get_object_terms().
* Added get_term_ancestors() to the CPT-onomy class.
* Added support for Internationalization.
* Tweaked the UI.
* Fixed a few bugs.

= 1.0.3 =
**DO NOT UPDATE IF YOU ARE NOT USING WORDPRESS 3.1 OR NEWER!!** If you're using a version older than 3.1., and having issues, download CPT-onomies 1.0.2. for bug fixes. Added support for Bulk/Quick Edit and Admin Sort/Filter. Fixed a few bugs.

= 1.0.2 =
* Fixed a few bugs with the "Restrict User's Capability to Assign Term Relationships" feature.
* The WordPress function, wp_count_terms(), now works with CPT-onomies and doesn't require the CPT-onomy class.
* Added get_objects_in_term() to the CPT-onomy class.
* Added previous_post_link(), next_post_link(), adjacent_post_link(), prev_post_rel_link(), next_post_rel_link(), get_adjacent_post_rel_link() and get_adjacent_post() to the CPT-onomy class with the ability to designate "in the same CPT-onomy".
* Added support for tax queries when using The Loop.

= 1.0.1 =
Fixed bug that didn't delete relationships when CPT-onomy "term" is deleted.