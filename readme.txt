=== Reorder by Term ===
Contributors: ronalfy, bigwing
Author URI: https://github.com/ronalfy/reorder-by-term
Plugin URL: https://wordpress.org/plugins/reorder-by-term/
Requires at Least: 3.7
Tested up to: 4.8
Tags: reorder, re-order, posts, terms, taxonomies, term, taxonomy, post type, post-type, ajax, admin, menu_order, ordering
Stable tag: 1.2.2
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://mediaron.com/contribute/

A simple and easy way to reorder your custom post types within terms in WordPress.

== Description ==

We consider Reorder By Term a <strong>developer tool</strong>. If you do not know what `menu_order` or custom queries are, then this plugin is likely not for you.  This is an add-on to <a href="https://wordpress.org/plugins/metronet-reorder-posts/">Reorder Posts</a> and requires <a href="https://wordpress.org/plugins/metronet-reorder-posts/">Reorder Posts 2.1.0 or greater</a>.

Out of the box, WordPress does not support the ability to reorder posts within a term.  There are other plugins out there that do term ordering, but they usually create custom tables (which require crazy custom queries and filters) and/or add a column or two to core tables, which is not sustainable in the long-term should WordPress core decide to update its schema.

Reorder by Term uses custom fields, which means you can reorder by term within each taxonomy AND post type.  This is insanely flexible.

Since WordPress doesn't support this by default, when you install the plugin, you'll have to build the term data.  For a lot of posts and terms, this can take a while, but you can limit the build to post types and taxonomies if, for example, you don't want to touch regular blog posts (with categories and tags).

<h3>Features</h3>
<ul>
<li>Provides a convenient build-terms tool to add the term data to your existing posts.</li>
<li>Automatically modifies/adds the term data when you save a post, change a term slug, or delete a term.</li>
<li>Reorder based on post type, taxonomy, and then term.</li>
<li>Uses custom fields to save data, so you can use <a href="http://codex.wordpress.org/Template_Tags/get_posts">get_posts</a>, <a href="http://codex.wordpress.org/Class_Reference/WP_Query">WP_Query</a>, or <a href="http://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts">pre_get_posts</a> to order your query correctly.</li>
</ul>

<h3>Spread the Word</h3>
If you like this plugin, please help spread the word.  Rate the plugin.  Write about the plugin.  Something :)

<h3>Translations</h3>
 None so far.
 
 If you would like to contribute a translation, please leave a support request with a link to your translation.
 
 <h3>Development</h3>
 
 Development happens on GitHub.

You are welcome to help us out and <a href="https://github.com/ronalfy/reorder-by-term">contribute on GitHub</a>.

<h3>Support</h3>

Please feel free to leave a support request here or create an <a href="https://github.com/ronalfy/reorder-by-term/issues">issue on GitHub</a>.  If you require immediate feedback, feel free to @reply me on Twitter with your support link:  <a href="https://twitter.com/ronalfy">@ronalfy</a>.  Support is always free unless you require some advanced customization out of the scope of the plugin's existing features.   Please rate/review the plugin if we have helped you to show thanks for the support.


== Installation ==

Either install the plugin via the WordPress admin panel, or ... 

1. Upload `reorder-by-term` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

This plugin requires <a href="https://wordpress.org/plugins/metronet-reorder-posts/">Reorder Posts 2.1.0 or greater</a>.

When you first install the plugin, you'll need to build term data.  Head to `Tools->Build Post Term Data`.  From there, you can build the term data based on the taxonomies you select.  You can also start over and clear the existing term data.

Please note that this plugin <strong>does not</strong> change the order of items in the front-end.  This functionality is <strong>not</strong> core WordPress functionality, so it'll require some work on your end to get the posts to display in your theme correctly.

You'll want to make use of <a href="http://codex.wordpress.org/Class_Reference/WP_Query">WP_Query</a>, <a href="http://codex.wordpress.org/Template_Tags/get_posts">get_posts</a>, or <a href="http://codex.wordpress.org/Plugin_API/Action_Reference/pre_get_posts">pre_get_posts</a> to modify query behavior on the front-end of your site.

<a href="https://github.com/ronalfy/reorder-by-term#usage">See usage for some examples.</a>

== Frequently Asked Questions ==

= Why must I build term data? =

For the plugin to work, there must be the correct custom fields present for each post you want reordered by term.  Since this data doesn't exist natively, we must build this data.

The build process should only have to happen once, so start the build, get a cup of coffee, and then start reordering your posts by term.

= Where is the "save" button when re-ordering? =

There isn't one. The changes are saved automatically.

= Do I need to add custom code to get this to work? =

Yes.  Can we theoretically build this into the plugin to get it work automatically?  Sure.  But we won't.

= Does the plugin work with hierarchical post types? =

Of course, but by the nature of terms, all post structures are flat, regardless of hierarchy.

= Does it work in older versions of WordPress? =

This plugin requires WordPress 3.7 or above.  We urge you, however, to always use the latest version of WordPress.

== Screenshots ==
1.  When first installing the plugin, you'll want to head to `Tools->Build Post Term Data` to add some data to your posts to allow reordering within terms.
2.  Convenient Reorder shortcut when browsing terms within a taxonomy.
3.  This is an add-on to the <a href="https://wordpress.org/plugins/metronet-reorder-posts/">Reorder Posts</a> plugin.
4.  Start by selecting a taxonomy...
5.  Then select a term to reorder...
6.  And let the ordering begin!

== Changelog ==

= 1.2.2 =
* Released 2016-08-14
* Major CSS overhaul

= 1.2.0 =
* Released 2016-08-12
* Added screen options to set posts per page

= 1.1.0 =
* Released 2016-08-08
* Added option in `Settings->Reorder Posts` to disable the term query from displaying.
* Added better support for the post object.

= 1.0.0 =
* Updated 2015-04-25 - Ensuring WordPress 4.2 compatibility
* Released 2015-01-27
* Initial Release.  Feedback is much appreciated!

== Upgrade Notice ==

= 1.2.0 =
Added screen options to set posts per page

= 1.1.0 =
Added option in `Settings->Reorder Posts` to disable the term query from displaying.

= 1.0.0 =
Initial Release