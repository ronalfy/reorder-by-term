<?php
/**
 * Reorder Post by Term Builder Class
 * 
 * @package    WordPress
 * @subpackage Reorder by Term plugin
 */
final class Reorder_By_Term_Builder  {
	private $post_types;
	public function __construct( $post_types ) {
		if ( !is_admin() ) return;
		
		//Set post types class variable
		if ( !is_array( $post_types ) || empty( $post_types ) ) $post_types = array();
		foreach( $post_types as $key => $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			$show_ui = (bool)isset( $post_type_obj->show_ui ) ? $post_type_obj->show_ui : false;
			if ( !$show_ui || 'attachment' === $key  ) unset( $post_types[ $key ] );
		}
		$this->post_types = array_keys( $post_types );
		
		//Register actions
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'wp_ajax_reorder_build_get_taxonomies', array( $this, 'ajax_get_taxonomy_data' ) );
		add_action( 'wp_ajax_reorder_build_term_data', array( $this, 'ajax_build_term_data' ) );
				
	}
	
	private function get_taxonomies() {
		$return_taxonomies = array();
		$taxonomies = get_object_taxonomies( $this->post_types, 'names' );
		foreach( $taxonomies as $taxonomy_name ) {
			if ( in_array( $taxonomy_name, array( 'nav_menu', 'link_category' ) ) ) continue;
			$return_taxonomies[] = $taxonomy_name;	
		}
		$return_taxonomies = apply_filters( 'reorder_term_build_get_taxonomies', $return_taxonomies ); //Allows filtering to limit or extend taxonomies - expects an indexed array of taxonomies
		return $return_taxonomies;
	}
	
	public function ajax_get_taxonomy_data() {
		//Permissions check
		if ( !current_user_can( 'edit_pages' ) || !wp_verify_nonce( $_POST[ 'nonce' ], 'reorder-build-terms' ) ) die( '' );
		
		$wp_taxonomies = (array)$_POST[ 'taxonomies' ];
		
		$taxonomies = array();
		foreach( $wp_taxonomies as $taxonomy_name ) {
			if ( in_array( $taxonomy_name, array( 'nav_menu', 'link_category' ) ) ) continue;
			$taxonomies[] = $taxonomy_name;	
		}
		
		//Prepare for returning
		$total_tax_count = $total_term_count = 0;
		$return_taxonomies = array();
		foreach( $taxonomies as $taxonomy ) {
			$term_count = wp_count_terms( $taxonomy, array( 'hide_empty' => true ) );
			if ( 0 == $term_count ) continue; //Skip taxonomies with zero terms
			$total_term_count += $term_count;
			$total_tax_count += 1;
			$return_taxonomies[] = array(
				'name' => $taxonomy,
				'count' => $term_count,
				'visible' => false
			);	
		}
		$return = array(
			'return_label' => esc_js( sprintf( __( 'Found %d taxonomies and %d terms.', 'reorder-by-term' ), $total_tax_count, $total_term_count ) ),
			'taxonomies' => $return_taxonomies
		);
		die( json_encode( $return ) );
	} //end ajax_get_taxonomy_data
	
	public function ajax_build_term_data() {
		//Permissions check
		if ( !current_user_can( 'edit_pages' ) || !wp_verify_nonce( $_POST[ 'nonce' ], 'reorder-build-terms' ) ) die( '' );
		
		//Get data
		$term_count = absint( $_POST[ 'term_count' ] );
		$term_offset = absint( $_POST[ 'term_offset' ] );
		$taxonomy = sanitize_text_field( $_POST[ 'taxonomy' ] );
		$post_ids = isset( $_POST[ 'post_ids' ] ) ? (array)$_POST[ 'post_ids' ] : array();
		
		//Get terms
		$terms = get_terms( $taxonomy, array(
			'offset' => $term_offset,
			'number' => 1,
			'hide_empty' => true,
			'hierarchical' => false
		) );
		
		//Loop through terms (should only be one) and get posts and build post meta
		$posts_return = array();
		if ( !empty( $terms ) ) {
			foreach( $terms as $term ) {
				$term_slug = $term->slug;
				
				//Get post ids to process
				if ( empty( $post_ids ) ) {
					$posts_original = get_objects_in_term( $term->term_id, $taxonomy );
				} else {
					$posts_original = array_filter( $post_ids, 'absint' );	
				}

				$posts_return = $posts_original;
				//Only get 50 posts at a time
				if ( count( $posts_original ) > 0 ) {
					$i = 0;
					foreach( $posts_original as $post_id ) {
						$post_type = get_post_type( $post_id );
						if ( in_array( $post_type, $this->post_types ) ) {
							$meta_key = sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug );
							if ( !get_post_meta( $post_id, $meta_key, true ) ) {
								add_post_meta( $post_id, $meta_key, 0, true );
							}
						}
						unset( $posts_return[ $i ] );
						$i++;
						if ( $i >= 50 ) {
							break;
						}
					}
				}
			}
		}
		$posts_return = array_values( $posts_return );
				
		//Build return ajax args
		$return_ajax_args = array(
			'taxonomy' => $taxonomy,
			'terms_left' => true
		);
		if ( count( $posts_return ) > 0 ) {
			$return_ajax_args[ 'term_offset' ] = $term_offset;
			$return_ajax_args[ 'post_ids' ] = $posts_return;
			$return_ajax_args[ 'more_posts' ] = true;
		} else {
			$term_offset += 1;
			$return_ajax_args[ 'term_offset' ] = $term_offset;
			$return_ajax_args[ 'post_ids' ] = array();
			$return_ajax_args[ 'more_posts' ] = false;
		}
		if ( empty( $terms ) || $term_offset >= $term_count   ) {
			$return_ajax_args[ 'terms_left' ] = false;	
		}
		$return_ajax_args[ 'term_count' ] = $term_count - $term_offset;
		
		//Return args
		die( json_encode( $return_ajax_args ) );
		
	} //end wp_ajax_reorder_build_term_data
	
	public function register_admin_page() {
		$page_hook = add_management_page( __( 'Reorder by Term', 'reorder-by-term' ), __( 'Build Post Term Data', 'reorder-by-term' ), 'edit_pages', 'reorder-by-term', array( $this, 'output_menu_html' ) );
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
					$wpdb->query( $sql );
					?>
					<div class="updated"><p><strong><?php esc_html_e( 'Term data has been deleted', 'reorder-by-term' ); ?></strong></p></div>
					<?php
				}
			?>
			<h3><?php esc_html_e( 'Build Term Data', 'reorder-by-term' ); ?></h3>
			<div class="error"><p><strong><?php esc_html_e( 'For a site with a lot of non-empty terms and posts, this could take a while.', 'reorder-by-term' ); ?></strong></p></div>
			<p><?php esc_html_e( 'If you have just installed this plugin, or have had it de-activated for a while, it is highly recommended you build the terms.  This feature will go through each term one-by-one and add the appropriate post meta that will allow you to reorder posts by term.', 'reorder-by-term' ); ?></p>
			<?php
			$taxonomies = $this->get_taxonomies();
			if ( !is_array( $taxonomies ) || empty( $taxonomies ) ) :
			printf( '<p><strong>%s</strong></p>', esc_html__( 'There are no terms to build.  Please check your Reorder Posts settings and make sure you have not disabled all post types.', 'reorder-by-term' ) );
			else:
				?>
				<h4><?php esc_html_e( 'Include Taxonomies', 'reorder-by-term' ); ?></h4>
				<?php
				foreach( $taxonomies as $taxonomy ) {
					printf( '<input type="checkbox" id="%1$s" name="include_tax[]" value="%2$s" %3$s>&nbsp;<label for="%1$s">%4$s</label><br />', esc_attr( 'include_tax_' . $taxonomy ), esc_attr( $taxonomy ), checked( true, true, false ), esc_html( $taxonomy ) );	
				}
				?>
				<?php wp_nonce_field( 'reorder-build-terms', '_reorder_build_terms' ); ?>
				<?php submit_button( __( 'Build Terms', 'reorder-by-term' ), 'primary', 'rebuild_terms_submit' ); ?>
				<div id="build-term-status-container"><p><strong id="build-term-status-label"></strong></p></div>
			<?php endif; ?>
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
			'build_done' => __( 'Done', 'reorder-by-term' ),
			'process_update' => sprintf( __( 'Processing %s with %s terms left to process', 'reorder-by-term' ), '{tax_name}', '{term_count}' ),
			'process_done' => __( 'Done processing', 'reorder-by-term' )
		) );
	}
} //end class Reorder_By_Term_Builder
