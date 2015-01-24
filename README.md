Reorder by Term for WordPress
=============

A simple and easy way to reorder your custom post types within terms in WordPress.

Description
----------------------

We consider Reorder By Term a <strong>developer tool</strong>. If you do not know what ```menu_order``` or custom queries are, then this plugin is likely not for you.  This is an add-on to <a href="https://wordpress.org/plugins/metronet-reorder-posts/">Reorder Posts</a> and requires <a href="https://wordpress.org/plugins/metronet-reorder-posts/">Reorder Posts 2.1.0 or greater</a>.

Out of the box, WordPress does not support the ability to reorder posts within a term.  There are other plugins out there that do term ordering, but they usually create custom tables (which require crazy custom queries and filters) and/or add a column or two to core tables, which is not sustainable in the long-term should WordPress core decide to update its schema.

Reorder by Term uses custom fields, which means you can reorder by term within each taxonomy AND post type.  This is insanely flexible.

Since WordPress doesn't support this by default, when you install the plugin, you'll have to build the term data.  For a lot of posts and terms, this can take a while, but you can limit the build to post types and taxonomies if, for example, you don't want to touch regular blog posts (with categories and tags).

Features
----------------------
<ul>
<li>Provides a convenient build-terms tool to add the term data to your existing posts.</li>
<li>Automatically modifies/adds the term data when you save a post, change a term slug, or delete a term.</li>
<li>Reorder based on post type, taxonomy, and then term.</li>
<li>Uses custom fields to save data, so you can use <a href="http://codex.wordpress.org/Template_Tags/get_posts">get_posts</a>, <a href="http://codex.wordpress.org/Class_Reference/WP_Query">WP_Query</a>, or <a href="http://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts">pre_get_posts</a> to order your query correctly.</li>
</ul>

Use Cases
----------------------
<ol>
<li>Set a "Featured" category and reorder posts within that category</li>
<li>Create an employees post type with a departments taxonomy, and reorder employees within each department</li>
<li>Create a products post type and a custom taxonomy, and reorder your products based on its category</li>
</ol>

Your imagination will give you more use-cases.  

Support
----------------------

Please feel free to leave a support request here or create an <a href="https://github.com/ronalfy/reorder-by-term/issues">issue on GitHub</a>.  If you require immediate feedback, feel free to @reply us on Twitter with your support link:  <a href="https://twitter.com/ronalfy">@ronalfy</a>).  Support is always free unless you require some advanced customization out of the scope of the plugin's existing features.  We'll do our best to get with you when we can.  Please rate/review the plugin if we have helped you to show thanks for the support.


Installation
----------------------

Either install the plugin via the WordPress admin panel, or ... 

1. Upload ```reorder-by-term``` to the ```/wp-content/plugins/``` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

This plugin requires <a href="https://wordpress.org/plugins/metronet-reorder-posts/">Reorder Posts 2.1.0 or greater</a>.

When you first install the plugin, you'll need to build term data.  Head to ```Tools->Build Post Term Data```.  From there, you can build the term data based on the taxonomies you select.  You can also start over and clear the existing term data.

Please note that this plugin <strong>does not</strong> change the order of items in the front-end.  This functionality is <strong>not</strong> core WordPress functionality, so it'll require some work on your end to get the posts to display in your theme correctly.

You'll want to make use of <a href="http://codex.wordpress.org/Class_Reference/WP_Query">WP_Query</a>, <a href="http://codex.wordpress.org/Template_Tags/get_posts">get_posts</a>, or <a href="http://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts">pre_get_posts</a> to modify query behavior on the front-end of your site.

Usage
----------------------

One you have reordered the posts on the back-end, you can now display them on the front-end.

The custom field structure is:  ```_reorder_term_{taxonomy}_{term_slug}```

If you have a taxonomy named ```genre``` and a term slug of ```alt-rock```, your query arguments might look like this:

```php
'post_type' => 'post',
'order' => 'ASC',
'post_status' => 'publish',
'posts_per_page' => 50,
'meta_key' => '_reorder_term_genre_alt-rock',
'orderby' => 'meta_value_num title'
```

An example query of the above taxonomy/term would be:
```php
<?php
$term_posts = array(
	'post_type' => 'post',
	'order' => 'ASC',
	'post_status' => 'publish',
	'posts_per_page' => 50,
	'meta_key' => '_reorder_term_genre_alt-rock',
	'orderby' => 'meta_value_num title'
);
$term_get_posts = get_posts( $term_posts );
foreach( $term_get_posts as $post ) {
	echo $post->post_title . '<br />';	
}
?>
```

An example of doing a custom order for a taxonomy archive (assuming post type of ```post```):

```php
add_filter( 'pre_get_posts', 'reorder_terms_taxonomy_genre' );
function reorder_terms_taxonomy_genre( $query ) {
	if ( !$query->is_main_query() || is_admin() ) return;
	
	if ( $query->is_tax( 'genre' ) ) {
		$term_slug = get_query_var( 'genre' );
		$query->set( 'tax_query', array() );
		$query->set( 'meta_key', '_reorder_term_genre_' . $term_slug );
		$query->set( 'orderby', 'meta_value_num title' );
		$query->set( 'order', 'ASC' );
		$query->set( 'post_type', 'post' );
	}	
}
```
