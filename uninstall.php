<?php
/*
	Dev note - Will not cleanly uninstall on multisite - See:  http://codex.wordpress.org/Function_Reference/register_uninstall_hook
*/
if ( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) {
    exit();
}
global $wpdb;

$sql = "delete from $wpdb->postmeta where left(meta_key, 14) = '_reorder_term_'";
$wpdb->query($sql);