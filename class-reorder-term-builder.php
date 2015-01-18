<?php
/**
 * Reorder Post by Term Builder Class
 * 
 * @package    WordPress
 * @subpackage Reorder by Term plugin
 */
final class Reorder_By_Term_Builder  {
	public function __construct() {
		if ( !is_admin() ) return;
		
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'wp_ajax_reorder_build_get_taxonomies', array( $this, 'ajax_get_taxonomy_data' ) );
		add_action( 'wp_ajax_reorder_build_term_data', array( $this, 'ajax_build_term_data' ) );
		
	}
	
	public function ajax_get_taxonomy_data() {
		//Permissions check
		if ( !current_user_can( 'edit_pages' ) || !wp_verify_nonce( $_POST[ 'nonce' ], 'reorder-build-terms' ) ) die( '' );
		
		$wp_taxonomies = get_taxonomies();
		$taxonomies = array();
		foreach( $wp_taxonomies as $taxonomy_name ) {
			if ( in_array( $taxonomy_name, array( 'nav_menu', 'link_category' ) ) ) continue;
			$taxonomies[] = $taxonomy_name;	
		}
		$taxonomies = apply_filters( 'reorder_term_build_get_taxonomies', $taxonomies ); //Allows filtering to limit or extend taxonomies - expects an indexed array of taxonomies
		
		//Prepare for returning
		$total_tax_count = $total_term_count = 0;
		$return_taxonomies = array();
		foreach( $taxonomies as $taxonomy ) {
			$term_count = wp_count_terms( $taxonomy );
			$total_term_count += $term_count;
			$total_tax_count += 1;
			$return_taxonomies[] = array(
				'name' => $taxonomy,
				'count' => $term_count
			);	
		}
		$return = array(
			'return_label' => esc_js( sprintf( __( 'Found %d taxonomies and %d terms.', 'reorder-by-term' ), $total_tax_count, $total_term_count ) ),
			'taxonomies' => $return_taxonomies
		);
		die( json_encode( $return ) );
	} //end ajax_get_taxonomy_data
	
	public function register_admin_page() {
		$page_hook = add_management_page( __( 'Reorder by Term', 'reorder-by-term' ), __( 'Reorder by Term', 'reorder-by-term' ), 'edit_pages', 'reorder-by-term', array( $this, 'output_menu_html' ) );
		add_action( 'admin_print_scripts-' . $page_hook, array( $this, 'print_scripts' ) );
	}
	
	public function output_menu_html() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Reorder by Term', 'reorder-by-term' ); ?></h2>
			<?php
				if ( isset( $_POST[ 'delete_terms_submit' ] ) && current_user_can( 'edit_pages' ) && wp_verify_nonce(  $_REQUEST[ '_reorder_erase_term_data' ], 'reorder-erase-term-data' ) ) {
					global $wpdb;
					$sql = "delete from $wpdb->postmeta where left(meta_key, 14) = '_reorder_term_'";
					$wpdb->query($sql);
					?>
					<div class="updated"><p><strong><?php esc_html_e( 'Term data has been deleted', 'reorder-by-term' ); ?></strong></p></div>
					<?php
				}
			?>
			<h3><?php esc_html_e( 'Build Term Data', 'reorder-by-term' ); ?></h3>
			<div class="error"><p><strong><?php esc_html_e( 'For a site with a lot of posts, this could take a while.', 'reorder-by-term' ); ?></strong></p></div>
			<p><?php esc_html_e( 'If you have just installed this plugin, or have had it de-activated for a while, it is highly recommended you build the terms.  This feature will go through each term one-by-one and add the appropriate post meta that will allow you to reorder posts by term.', 'reorder-by-term' ); ?></p>
			<?php wp_nonce_field( 'reorder-build-terms', '_reorder_build_terms' ); ?>
			<?php submit_button( __( 'Build Terms', 'reorder-by-term' ), 'primary', 'rebuild_terms_submit' ); ?>
			<div id="build-term-status-container"><p><strong id="build-term-status-label"></strong></p></div>
			<h3><?php esc_html_e( 'Delete Term Data', 'reorder-by-term' ); ?></h3>
			<div class="error"><p><strong><?php esc_html_e( 'This will delete all of the term data created by this plugin.', 'reorder-by-term' ); ?></strong></p></div>
			<form action="" method="post">
				<?php wp_nonce_field( 'reorder-erase-term-data', '_reorder_erase_term_data' ); ?>
				<?php submit_button( __( 'Delete Term Data', 'reorder-by-term' ), 'primary', 'delete_terms_submit' ); ?>
			</form>
			
		</div><!-- .wrap -->
		<?php	
	}
	public function print_scripts() {
		wp_enqueue_script( 'reorder_by_term_builder', plugins_url( '/js/build.js', __FILE__ ), array( 'jquery' ), '20150117', true );
		wp_localize_script( 'reorder_by_term_builder', 'reorder_term_build', array(
			'delete_confirm' => __( 'Delete term data?  This cannot be undone.', 'reorder-by-term' ),
			'build_submit_message' => __( 'Building Term Data.  DO NOT REFRESH.', 'reorder-by-term' ),
			'build_done' => __( 'Done', 'reorder-by-term' )
		) );
		//die( 'yo' );	
	}
} //end class Reorder_By_Term_Builder