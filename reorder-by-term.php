<?php
/*
Plugin Name: Reorder by Term
Plugin URI: https://wordpress.org/plugins/reorder-by-term/
Description: Reorder Posts by Term
Version: 1.2.2
Author: Ronald Huereca
Author URI: https://github.com/ronalfy/reorder-by-term
Text Domain: reorder-by-term
Domain Path: /languages
*/

/**
 * Reorder Post by Term
 * 
 * @package    WordPress
 * @subpackage Reorder by Term plugin
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
	
	/**
	 * Class constructor
	 * 
	 * Sets definitions
	 * Adds methods to appropriate hooks
	 * 
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access private	
	 */
	private function __construct() {
		//* Localization Code */
		load_plugin_textdomain( 'reorder-by-term', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		if ( !class_exists( 'MN_Reorder' ) || !defined( 'REORDER_ALLOW_ADDONS' ) || ( false === REORDER_ALLOW_ADDONS ) ) {
			add_action( 'admin_notices', array( $this, 'output_error_reorder_plugin' ) );//Output error	
			return;
		}
		
		require( 'class-reorder-term-helper.php' );
		require( 'class-reorder-term-builder.php' );
		
		//Main init class
		add_action( 'metronet_reorder_post_types_loaded', array( $this, 'plugin_init' ) );
		
		//Add post save action
		add_action( 'save_post', array( $this, 'add_custom_fields' ), 10, 2 );
		
		//For when Updating a Term
		add_action( 'edit_terms', array( &$this, 'before_update_term' ), 10, 2 );
		add_action( 'edited_term', array( &$this, 'after_update_term' ), 10, 3 );
		
		//For when deleting a term
		add_action( 'delete_term', array( $this, 'after_delete_term' ), 10, 4 );
		
		// Initialize admin items
		add_action( 'admin_init', array( $this, 'reorder_posts_admin_init' ), 12, 1 );
		
		
	}
	
	/**
	 * Deletes custom field values when a term is deleted
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param int $term_id The Term ID that is being updated - Should no longer exist in DB
	 * @param int $term_tax_id The Taxonomy ID for the slug
	 * @param string $taxonomy_slug The taxonomy slug that the term is attached to
	 * @param object $deleted_term - Result of get_term function
	 * @uses delete_term WordPress action
	 */
	public function after_delete_term( $term_id, $term_tax_id, $taxonomy_slug, $deleted_term ) {
		$meta_key = sprintf( '_reorder_term_%s_%s', $taxonomy_slug, $deleted_term->slug );
		global $wpdb;
		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'meta_key' => $meta_key	
			),
			array(
				'%s'	
			)
		);
	}
	
	/**
	 * Sets class variables by reference so that we can update the custom field data when the term slug is ovewritten
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param int $term_id The Term ID that is being updated
	 * @param string $taxonomy_slug The taxonomy slug that the term is attached to
	 * @uses edit_terms WordPress action
	 */
	public function before_update_term( $term_id, $taxonomy_slug ) {
		$term = get_term( $term_id, $taxonomy_slug, OBJECT);
		$this->before_term_slug = $term->slug;
		$this->before_term_tax = $taxonomy_slug;	
	} //end before_update_term
	
	/**
	 * Updates custom field values for the term/taxonomy relationship
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param int $term_id The Term ID that is being updated
	 * @param int $term_tax_id The Taxonomy ID for the slug
	 * @param string $taxonomy_slug The taxonomy slug that the term is attached to
	 * @uses edited_term WordPress action
	 */
	public function after_update_term( $term_id, $term_tax_id, $taxonomy_slug ) {
		$term = get_term( $term_id, $taxonomy_slug, OBJECT );
		$after_term_slug = $term->slug;

		//Get old custom field meta keys and what to replace with
		$old_meta_key = sprintf( '_reorder_term_%s_%s', $this->before_term_tax, $this->before_term_slug );
		$new_meta_key = sprintf( '_reorder_term_%s_%s', $taxonomy_slug, $after_term_slug );
		if ( $old_meta_key === $new_meta_key ) return;
	
		//Update meta key
		global $wpdb;
		$wpdb->update(
			$wpdb->postmeta,
			array(
				'meta_key' => 	$new_meta_key
			),
			array(
				'meta_key' => $old_meta_key	
			),
			array(
				'%s'	
			),
			array(
				'%s'	
			)
		);
		return;		
	} //end after_update_term
	
	/**
	 * Saves plugin's custom fields with menu order
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @global object $post  The post object
	 * @param int $post_id The Post ID
	 * @uses save_post WordPress action
	 */
	public function add_custom_fields( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) ) return;
		
		//Make sure we have a valid post object
		if ( !is_object( $post ) ) $post = get_post( $post_id );
		if ( !is_object( $post ) ) return;
		
		//Get taxonomies for da post
		$taxonomies = get_object_taxonomies( $post );
		if ( empty( $taxonomies ) ) return;
		
		//Get the terms attached to the post
		$terms = wp_get_object_terms( $post_id, $taxonomies );
		if ( is_wp_error( $terms ) || !is_array( $terms ) ) return;
				
		//Build array of terms
		$custom_fields_to_save = array();
		$custom_field_terms = array();
		foreach( $terms as $term ) {
			$custom_field_meta_key = sprintf( '_reorder_term_%s_%s', $term->taxonomy, $term->slug );
			$custom_field_terms[] = $custom_field_meta_key;
			$term_count = $term->count;
			if ( $term_count > 0 ) {
				$term_count -= 1;	
			}
			$custom_fields_to_save[ $custom_field_meta_key ] = array(
				'term_id' => $term->term_id,
				'term_slug' => $term->slug,
				'taxonomy' => $term->taxonomy,
				'count' => $term_count
			);
		}

		//Get existing custom fields
		$custom_fields = get_post_custom_keys( $post_id );
		if ( !is_array( $custom_fields ) ) $custom_fields = array();
		
		//Loop through custom fields and see if it exists in our save array - if not, remove the post meta key
		foreach( $custom_fields as $key => $custom_field ) {
			if ( !in_array( $custom_field, $custom_field_terms ) && '_reorder_term_' == substr( $custom_field, 0, 14 ) ) {
				delete_post_meta( $post_id, $custom_field );
				unset( $custom_fields[ $key ] );
			}
		}
		
		//Loop through custom fields to save and see if custom field already exists - if so, unset it so we skip it
		foreach( $custom_field_terms as $key => $custom_field_term ) {
			if ( is_array( $custom_fields ) ) {
				if ( in_array( $custom_field_term, $custom_fields ) ) {
					unset( $custom_fields_to_save[ $custom_field_term ] );	
				}	
			}
		}
		
		//Yay, yet another loop through our custom fields to save and save the post meta - new term is high menu_order
		foreach( $custom_fields_to_save as $custom_field_key => $term_info ) {
			/* Dev note - The count is really only useful if someone has already "built" the term 
				posts and/or has an empty WordPress install */
			update_post_meta( $post_id, $custom_field_key, $term_info[ 'count' ] );
		}
		
		//Yay, we're done
		return; //redundant, but I don't care
		
	}
	
	/**
	 * Outputs error when Metronet Reorder Posts isn't installed
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @global string $pagenow  The current admin screen
	 * @uses admin_notices WordPress action
	 */
	public function output_error_reorder_plugin() {
		global $pagenow;
		if ( 'plugins.php' != $pagenow ) return;
		?>
		<div class="error">
			<p><?php printf( __( 'Reorder By Term requires <a href="%s">Reorder Posts</a> 2.1.0 or greater to be installed.', 'reorder-by-term' ), 'https://wordpress.org/plugins/metronet-reorder-posts/' ); ?></p>
		</div>
		<?php	
	}
	
	/**
	 * Outputs error when Metronet Reorder Posts isn't installed
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @uses metronet_reorder_post_types_loaded WordPress action
	 * @param array $post_types Array of post types to initialize
	 */
	public function plugin_init( $post_types = array() ) {
			foreach( $post_types as $post_type ) {
				new Reorder_By_Term_Helper( array( 'post_type' => $post_type ) );	
			}
			new Reorder_By_Term_Builder( $post_types );
	}
	
	/**
	 * Initializes into Reorder Posts settings section to show a term query or not
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.1.0
	 * @access public
	 * @uses admin_init WordPress action
	 */
	public function reorder_posts_admin_init() {
		add_settings_section( 'mn-reorder-by-term', _x( 'Reorder by Term', 'plugin settings heading' , 'reorder-by-term' ), '__return_empty_string', 'metronet-reorder-posts' );
		
		add_settings_field( 'mn-reorder-by-term-advanced', __( 'Show Terms Query', 'reorder-by-term' ), array( $this, 'add_settings_field_term_query' ), 'metronet-reorder-posts', 'mn-reorder-by-term', array( 'desc' => __( 'By default the terms query displays.', 'reorder-by-term' ) ) );
	}
	
	/**
	 * Outputs settings section for showing a term query or not
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.1.0
	 * @access public
	 * @uses MN_Reorder_Admin WordPress object
	 */
	public function add_settings_field_term_query() {
		$options = MN_Reorder_Admin::get_instance()->get_plugin_options();
		
		$selected = 'on';
		if ( isset( $options[ 'rt_show_query' ] ) ) {
			$selected = $options[ 'rt_show_query' ];
		}
				
		printf( '<p><input type="radio" name="metronet-reorder-posts[rt_show_query]" value="on" id="rt_show_query_yes" %s />&nbsp;<label for="rt_show_query_yes">%s</label></p>', checked( 'on', $selected, false ), esc_html__( 'Yes', 'reorder-by-term' ) );
		printf( '<p><input type="radio" name="metronet-reorder-posts[rt_show_query]" value="off" id="rt_show_query_no" %s />&nbsp;<label for="rt_show_query_no">%s</label></p>', checked( 'off', $selected, false ), esc_html__( 'No', 'reorder-by-term' ) );
		
	}
	
}
add_action( 'plugins_loaded', 'reorder_by_term_instantiate' );
function reorder_by_term_instantiate() {
	Reorder_By_Term::get_instance();
} //end slash_edit_instantiate