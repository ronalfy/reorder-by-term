<?php
/*
Plugin Name: Reorder by Term
Plugin URI: https://wordpress.org/plugins/reorder-by-term/
Description: Reorder Posts by Term
Version: 1.0.0
Author: Ronald Huereca
Author URI: https://github.com/ronalfy/reorder-by-term
Text Domain: reorder-by-term
Domain Path: /languages
*/

final class Reorder_By_Term {
	private static $instance = null;
	private $has_dependency = false;
	
	//Singleton
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	} //end get_instance
	
	private function __construct() {
		//* Localization Code */
		load_plugin_textdomain( 'reorder-by-term', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		if ( !class_exists( 'MN_Reorder' ) ) {
			add_action( 'admin_notices', array( $this, 'output_error_reorder_plugin' ) );//Output error	
			return;
		}
		
		require( 'class-reorder-term-helper.php' );
		
		//Main init class
		add_action( 'metronet_reorder_post_types_loaded', array( $this, 'plugin_init' ) );
	}
	
	public function output_error_reorder_plugin() {
		global $pagenow;
		if ( 'plugins.php' != $pagenow ) return;
		?>
		<div class="error">
			<p><?php printf( __( 'Reorder By Term requires <a href="%s">Reorder Posts</a> to be installed.', 'reorder-by-term' ), 'https://wordpress.org/plugins/metronet-reorder-posts/' ); ?></p>
		</div>
		<?php	
	}
	
	public function plugin_init( $post_types = array() ) {
			foreach( $post_types as $post_type ) {
				new Reorder_By_Term_Helper( array( 'post_type' => $post_type ) );	
			}
	}
	
}
add_action( 'plugins_loaded', 'reorder_by_term_instantiate' );
function reorder_by_term_instantiate() {
	Reorder_By_Term::get_instance();
} //end slash_edit_instantiate