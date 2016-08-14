<?php
/**
 * Reorder Post by Term Helper Class
 * 
 * @package    WordPress
 * @subpackage Reorder by Term plugin
 */
final class Reorder_By_Term_Helper  {
	private $post_type;
	private $posts_per_page;
	private $offset;
	private $reorder_page;
	private $tab_url;
	
	/**
	 * Class constructor
	 * 
	 * Sets definitions
	 * Adds methods to appropriate hooks
	 * 
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param array $args    If not set, then uses $defaults instead
	 */
	public function __construct( $args ) {
		
		// Get posts per page
		$user_id = get_current_user_id();
		$posts_per_page = get_user_meta( $user_id, 'reorder_items_per_page', true );
		if ( ! is_numeric( $posts_per_page ) ) {
			$posts_per_page = 50;
		}
		$offset = $posts_per_page - 2;
		
		// Parse arguments
		$defaults = array(
			'post_type'   => '',
			'posts_per_page' => $posts_per_page,
			'offset' => $offset
		);
		$args = wp_parse_args( $args, $defaults );

		// Set variables
		$this->post_type   = $args[ 'post_type' ];
		
		//Get offset and posts_per_page
		$this->posts_per_page = absint( $args[ 'posts_per_page' ] ); //todo - filterable?
		$this->offset = absint( $args[ 'offset' ] ); //todo - filterable?
		if ( $this->offset > $this->posts_per_page ) {
			$this->offset = $this->posts_per_page;	
		}
		
		//Add-on actions/filters
		add_action( 'metronet_reorder_menu_url_' . $this->post_type, array( $this, 'set_reorder_url' ) );
		add_action( 'reorder_by_term_interface_' . $this->post_type, array( $this, 'output_interface' ) );
		add_action( 'metronet_reorder_posts_add_menu_' . $this->post_type, array( $this, 'script_init' ) );
		add_filter( 'metronet_reorder_posts_tabs_' . $this->post_type, array( $this, 'add_tab' ) );
	
		//Ajax actions
		//add_action( 'wp_ajax_term_build', array( $this, 'ajax_build_term_posts' ) ); //no longer used - code left in just in case we want to enable this feature
		add_action( 'wp_ajax_reorder_term_sort', array( $this, 'ajax_term_sort' ) );
		
	}
	
	public function add_reorder_link_to_term_page( $actions, $term ) {
		if ( 0 === $term->count ) return $actions;
		$post_type = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';
		if ( $post_type !== $this->post_type ) return $actions;
		
		$term_id = $term->term_id;
		$taxonomy = $term->taxonomy;		
		$reorder_url = add_query_arg( array( 'tab' => 'reorder-term', 'taxonomy' => $taxonomy, 'term' => $term_id ), $this->reorder_page );
		$actions[ 'reorder_' . $this->post_type ] = sprintf( '<a href="%s">%s</a>', esc_url( $reorder_url ), esc_html__( 'Reorder', 'reorder-by-term' ) );
		return $actions;
	}
	
	/**
	 * Sorts the pages by term and updates the custom field order
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 */
	public function ajax_term_sort() {
		global $wpdb;
		
		if ( !current_user_can( 'edit_pages' ) ) die( '' );
		
		// Verify nonce value, for security purposes
		if ( !wp_verify_nonce( $_POST['nonce'], 'sortnonce' ) ) die( '' );
		
		//Get Ajax Vars
		$post_parent = 0;
		$menu_order_start = isset( $_POST[ 'start' ] ) ? absint( $_POST[ 'start' ] ) : 0;
		$post_id = isset( $_POST[ 'post_id' ] ) ? absint( $_POST[ 'post_id' ] ) : 0;
		$post_menu_order = isset( $_POST[ 'menu_order' ] ) ? absint( $_POST[ 'menu_order' ] ) : 0;
		$posts_to_exclude = isset( $_POST[ 'excluded' ] ) ? array_filter( $_POST[ 'excluded' ], 'absint' ) : array();
		$post_type = isset( $_POST[ 'post_type' ] ) ? sanitize_text_field( $_POST[ 'post_type' ] ) : false;
		$attributes = isset( $_POST[ 'attributes' ] ) ? $_POST[ 'attributes' ] : array();
		
		$taxonomy = $term_slug = false;
		//Get the tax and term slug
		foreach( $attributes as $attribute_name => $attribute_value ) {
			if ( 'data-taxonomy' == $attribute_name ) {
				$taxonomy = sanitize_text_field( $attribute_value );
			} elseif ( 'data-term' == $attribute_name ) {
				$term_slug = sanitize_text_field( $attribute_value );	
			}
		}
		
		if ( !$post_type || !$taxonomy || !$term_slug  ) die( '' );
		
		//Build Initial Return 
		$return = array();
		$return[ 'more_posts' ] = false;
		$return[ 'action' ] = 'reorder_term_sort';
		$return[ 'post_parent' ] = $post_parent;
		$return[ 'nonce' ] = sanitize_text_field( $_POST[ 'nonce' ] );
		$return[ 'post_id'] = $post_id;
		$return[ 'menu_order' ] = $post_menu_order;
		$return[ 'post_type' ] = $post_type;
		$return[ 'attributes' ] = $attributes;
		
		//Update post if passed - Should run only on beginning of first iteration
		if( $post_id > 0 && !isset( $_POST[ 'more_posts' ] ) ) {
			update_post_meta( $post_id, sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug ), $post_menu_order );	
			$posts_to_exclude[] = $post_id;
		}
		
		//Build query
		$reorder_class = isset( $mn_reorder_instances[ $post_type ] ) ? $mn_reorder_instances[ $post_type ] : false;
		$post_status = 'publish';
		$order = 'ASC';
		if ( $reorder_class ) {
			$post_status = $reorder_class->get_post_status();
			$order = $reorder_class->get_post_order();	
		}
		
		//Build Query
		$post_query_args = array(
			'post_type' => $post_type,
			'order' => $order,
			'post_status' => $post_status,
			'posts_per_page' => 50,
			'post__not_in' => $posts_to_exclude,
			'meta_key' => sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug ),
			'orderby' => 'meta_value_num title',
			'meta_type' => 'NUMERIC',
		);
		$posts = new WP_Query( $post_query_args );
		$start = $menu_order_start;
		if ( $posts->have_posts() ) {
			foreach( $posts->posts as $post ) {
				//Increment start if matches menu_order and there is a post to change
				if ( $start == $post_menu_order && $post_id > 0 ) {
					$start++;	
				}
				
				if ( $post_id != $post->ID ) {
					//Update post and counts
					update_post_meta( $post->ID, sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug ), $start );	
				}
				$posts_to_exclude[] = $post->ID;
				$start++;
			}
			$return[ 'excluded' ] = $posts_to_exclude;
			$return[ 'start' ] = $start;
			if ( $posts->max_num_pages > 1 ) {
				$return[ 'more_posts' ] = true;	
			} else {
				$return[ 'more_posts' ] = false;	
			}
			die( json_encode( $return ) );
		} else {
			die( json_encode( $return ) );
		}
	}
	
	/**
	 * Builds the initial found of posts to have a custom field order - Makes sure the posts within a taxonomy/term have a custom field present
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @global object $wpdb  The primary global database object used internally by WordPress
	 */
	public function ajax_build_term_posts() {
		return; //no longer used - code left in in case we want to re-add this feature
		global $mn_reorder_instances;
		
		if ( !current_user_can( 'edit_pages' ) ) die( '' );
		
		// Verify nonce value, for security purposes
		if ( !wp_verify_nonce( $_POST['nonce'], 'reorder-term-build' ) ) die( '' );
		
		//Get variables
		$posts_to_exclude = isset( $_POST[ 'excluded' ] ) ? array_filter( $_POST[ 'excluded' ], 'absint' ) : array();
		$taxonomy = isset( $_POST[ 'taxonomy' ] ) ? sanitize_text_field( $_POST[ 'taxonomy' ] ) : '';
		$term_id = isset( $_POST[ 'term_id' ] ) ? absint( $_POST[ 'term_id' ] ) : 0;
		$menu_order_start = isset( $_POST[ 'start' ] ) ? absint( $_POST[ 'start' ] ) : 0;
		$post_type = isset( $_POST[ 'post_type' ] ) ? sanitize_text_field( $_POST[ 'post_type' ] ) : 0;
		
		//Get Term Meta
		$term = get_term_by( 'id', $term_id, $taxonomy );
		if ( !$term ) die( '' );
		$term_slug = $term->slug;		
		
		//Build Initial Return 
		$return = array();
		$return[ 'more_posts' ] = false;
		$return[ 'action' ] = 'term_build';
		$return[ 'nonce' ] = sanitize_text_field( $_POST[ 'nonce' ] );
		$return[ 'taxonomy'] = $taxonomy;
		$return[ 'term_id' ] = $term_id;
		$return[ 'post_type' ] = $post_type;
		
		//Should run only on beginning of first iteration
		if( !isset( $_POST[ 'more_posts' ] ) ) {
			global $wpdb;
			/*	Dev note:
				This is to get rid of a use-case that someone installed the plugin and
				saved a post with a term, but has yet to reorder these terms.
				This attempts to get rid of any saved meta keys and re-order from scratch.
				If someone has an existing install, and happens to go through all the posts with 
				terms attached and re-saves them, theoretically this query should never run.
			*/
			//Get rid of any previous stored meta keys for taxonomy/term
			$sql_meta_key = sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug );
			$sql = $wpdb->prepare( "delete from $wpdb->postmeta where meta_key = %s", $sql_meta_key );
			$wpdb->query( $sql );
		}
		
		//Build query
		$reorder_class = isset( $mn_reorder_instances[ $post_type ] ) ? $mn_reorder_instances[ $post_type ] : false;
		$post_status = 'publish';
		$order = 'ASC';
		if ( $reorder_class ) {
			$post_status = $reorder_class->get_post_status();
			$order = $reorder_class->get_post_order();	
		}
		//Get posts that do not have the custom field
		$post_query_args = array(
			'post_type' => $post_type,
			'order' => $order,
			'post_status' => $post_status,
			'posts_per_page' => 50,
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'terms' => $term_id
				)	
			),
			'orderby' => 'title',
			'meta_query' => array(
				array(
					'key' => sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug ),
					'compare' => 'NOT EXISTS'
				)	
			),
			'post__not_in' => $posts_to_exclude
		);
		$posts = new WP_Query( $post_query_args );
		$start = $menu_order_start;
		if ( $posts->have_posts() ) {
			foreach( $posts->posts as $post ) {
				update_post_meta( $post->ID, sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug ), $start );
				$posts_to_exclude[] = $post->ID;
				$start++;
			}
			$return[ 'excluded' ] = $posts_to_exclude;
			$return[ 'start' ] = $start;
			if ( $posts->max_num_pages > 1 ) {
				$return[ 'more_posts' ] = true;	
			} else {
				$return[ 'more_posts' ] = false;	
			}
			die( json_encode( $return ) );
		} else {
			die( json_encode( $return ) );
		}
		
	}
	
	/**
	 * Adjust the found posts for the offset
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @returns string $found_posts Number of posts
	 * @uses found_posts WordPress filter
	 */
	public function adjust_offset_pagination( $found_posts, $query ) {
		//This sometimes will have a bug of showing an extra page, but it doesn't break anything, so leaving it for now.
		if( $found_posts > $this->posts_per_page ) {
			$num_pages = $found_posts / $this->offset;
			$found_posts = (string)round( $num_pages * $this->posts_per_page );
		}
		return $found_posts;
	}
	
	/**
	 * Print out our scripts
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access public
	 */
	public function print_scripts() {
		//Overwrite action variable by de-registering sort script and adding it back in
		if ( isset( $_GET[ 'tab' ] ) && 'reorder-term' == $_GET[ 'tab' ] ) {
			//Main Reorder Script
			wp_deregister_script( 'reorder_posts' );
			wp_enqueue_script( 'reorder_posts', REORDER_URL . '/scripts/sort.js', array( 'reorder_nested' ) ); //CONSTANT REORDER_URL defined in Metronet Reorder Posts
			wp_localize_script( 'reorder_posts', 'reorder_posts', array(
				'action' => 'reorder_term_sort',
				'expand' => esc_js( __( 'Expand', 'metronet-reorder-posts' ) ),
				'collapse' => esc_js( __( 'Collapse', 'metronet-reorder-posts' ) ),
				'sortnonce' =>  wp_create_nonce( 'sortnonce' ),
				'hierarchical' => false,
			) );	
			
			//Main Term Script
			wp_enqueue_script( 'reorder_terms', plugins_url( '/js/main.js', __FILE__ ), array( 'reorder_posts' ) );
			wp_localize_script( 'reorder_terms', 'reorder_terms', array(
				'action' => 'term_build',
				'loading_text' => __( 'Loading...  Do not Refresh', 'reorder-by-term' ),
				'refreshing_text' => __( 'Refreshing...', 'reorder-by-term' ),
				'sortnonce' =>  wp_create_nonce( 'reorder-term-build' ),
			) );
			
		}
	}
	
	/**
	 * Sets the menu location URL for Reorder Posts
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param string $url The menu location URL
	 * @uses metronet_reorder_menu_url_{post_type} WordPress action
	 */
	public function set_reorder_url( $url ) {
		$this->reorder_page = $url;

		
	}
	
	/**
	 * Add our own scripts to the Reorder menu item
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param string $menu_hook Menu hook to latch onto
	 * @uses metronet_reorder_posts_add_menu_{post_type} WordPress filter
	 */
	public function script_init( $menu_hook ) {
		add_action( 'admin_print_scripts-' . $menu_hook, array( $this, 'print_scripts' ), 20 );	
		
		//Taxonomy Page filters
		$taxonomies = get_object_taxonomies( $this->post_type );
		foreach( $taxonomies as $taxonomy ) {
			//Filter for adding Reorder link
			add_filter( "{$taxonomy}_row_actions", array( $this, 'add_reorder_link_to_term_page' ), 10, 2 );	
		}
	}
	
	/**
	 * Add a custom tab to the Reorder screen
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param array $tabs Current tabs
	 * @return array $tabs Updated tabs
	 * @uses metronet_reorder_posts_tabs_{post_type} WordPress filter
	 */
	public function add_tab( $tabs = array() ) {
		//Make sure there are taxonomies attached to this post
		$taxonomies = get_object_taxonomies( $this->post_type );
		if ( empty( $taxonomies ) ) return $tabs;
		
		$this->tab_url = add_query_arg( array( 'tab' => 'reorder-term' ), $this->reorder_page );	
		
		//Return Tab
		$tabs[] = array(
			'url' => $this->tab_url,
			'label' => __( 'Reorder by Term', 'reorder-by-term' ),
			'get' => 'reorder-term' /*$_GET variable*/,
			'action' => 'reorder_by_term_interface_' . $this->post_type
		);
		return $tabs;
	}
	
	/**
	 * Output the main HTML interface of taxonomy/terms/posts
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @uses reorder_by_term_interface_{post_type} WordPress action
	 */
	public function output_interface() {
		//Output Taxonomies
		$selected_tax = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : false;
		$taxonomies = get_object_taxonomies( $this->post_type, 'objects' );
		?>
		<h3><?php esc_html_e( 'Select a Taxonomy', 'reorder-by-term' ); ?></h3>
		<form id="reorder-taxonomy" method="get" action="<?php echo esc_url( $this->reorder_page ); ?>">
		<?php 
		foreach( $_GET as $key => $value ) {
				if ( 'term' == $key || 'taxonomy' == $key || 'paged' == $key ) continue;
				printf( '<input type="hidden" value="%s" name="%s" />', esc_attr( $value ), esc_attr( $key ) );
		}
		?>
		<select name="taxonomy">
			<?php
			printf( '<option value="none">%s</option>', esc_html__( 'Select a taxonomy', 'reorder-by-term' ) );
			foreach( $taxonomies as  $tax_name => $taxonomy ) {
				$label = $taxonomy->label;
				printf( '<option value="%s" %s>%s</option>', esc_attr( $tax_name ), selected( $tax_name, $selected_tax, false ),  esc_html( $label ) );
			}				
			?>
		</select>
		</form>
		<?php
		//Output Terms
		if ( $selected_tax ) {
			$terms = get_terms( $selected_tax );
			$selected_term = isset( $_GET[ 'term' ] ) ? absint( $_GET[ 'term' ] ) : 0;
			if ( !is_wp_error( $terms ) && !empty( $terms ) ) {
				
				?>
				<h3><?php esc_html_e( 'Select a Term', 'reorder-by-term' ); ?></h3>
				<form id="reorder-term" method="get" action="<?php echo esc_url( $this->reorder_page ); ?>">
				<?php 
				foreach( $_GET as $key => $value ) {
						if ( 'term' == $key || 'paged' == $key ) continue;
						printf( '<input type="hidden" value="%s" name="%s" />', esc_attr( $value ), esc_attr( $key ) );
				}
				?>
				
				<select name="term">
					<?php
					printf( '<option value="none">%s</option>', esc_html__( 'Select a term', 'reorder-by-term' ) );
					foreach( $terms as  $term ) {
						$label = $term->name;
						printf( '<option value="%s" %s>%s</option>', esc_attr( $term->term_id ), selected( $selected_term, $term->term_id, false ),  esc_html( $label ) );
					}				
					?>
				</select>
				</form>
				
				<?php 
				if( term_exists( $selected_term, $selected_tax ) ) {
					$this->output_posts( $this->post_type, $selected_tax, $selected_term );	
				}
			} else {
				printf( '<div class="error"><p>%s</p></div>', esc_html__( 'No terms were found', 'reorder-by-type' ) );	
			}
		} 
		
	}// end output_interface
	
	
	/**
	 * Helper function for outputting the posts found within the taxonomy/term
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access public
	 * @param string $post_type The current post type
	 * @param string $tax The current taxonomy
	 * @param int $term_id The term ID
	 * @uses output_interface method
	 */
	private function output_posts( $post_type, $tax, $term_id ) {
		global $mn_reorder_instances;
		
		//Get Term Meta
		$term = get_term_by( 'id', $term_id, $tax );
		if ( !$term ) {
			printf( '<div class="error"><p>%s</p></div>', esc_html__( 'Invalid Term', 'reorder-by-type' ) );
			return;
		}
		$term_slug = $term->slug;		
		
		//Build queries
		$reorder_class = isset( $mn_reorder_instances[ $post_type ] ) ? $mn_reorder_instances[ $post_type ] : false;
		$post_status = 'publish';
		$order = 'ASC';
		$main_offset = $this->offset;
		$posts_per_page = $this->posts_per_page;
		if ( $reorder_class ) {
			$post_status = $reorder_class->get_post_status();
			$order = $reorder_class->get_post_order();	
		}
		$page = isset( $_GET[ 'paged' ] ) ? absint( $_GET[ 'paged' ] ) : 0;
		$offset = 0;
		if ( $page == 0 ) {
			$offset = 0;	
		} elseif ( $page > 1 ) {
			$offset = $main_offset * ( $page - 1 );
		}
		printf( '<input type="hidden" id="reorder-offset" value="%s" />', absint( $offset ) );
		printf( '<input type="hidden" id="reorder-tax-name" value="%s" />', esc_attr( $tax ) );
		printf( '<input type="hidden" id="reorder-term-id" value="%s" />', absint( $term_id ) );
		printf( '<input type="hidden" id="reorder-post-type" value="%s" />', esc_attr( $post_type ) );
		
		$post_query_args = array(
			'post_type' => $post_type,
			'order' => $order,
			'post_status' => $post_status,
			'posts_per_page' => 1,
			'tax_query' => array(
				array(
					'taxonomy' => $tax,
					'terms' => $term_id,
					'include_children' => false
				)	
			),
			'orderby' => 'menu_order title',
			'offset' => $offset
		);
		$tax_query_args = $post_query_args;
		unset( $tax_query_args[ 'tax_query' ] );
		$tax_query_args[ 'meta_type' ] = 'NUMERIC';
		$tax_query_args[ 'meta_key' ] = sprintf( '_reorder_term_%s_%s', $tax, $term_slug );
		$tax_query_args[ 'orderby' ] = 'meta_value_num title';
		$tax_query_args[ 'posts_per_page' ] = $posts_per_page;
		
		//Perform Queries
		add_filter( 'found_posts', array( $this, 'adjust_offset_pagination' ), 10, 2 );
		$post_query_results = new WP_Query( $post_query_args );
		$tax_query_results = new WP_Query( $tax_query_args );
		remove_filter( 'found_posts', array( $this, 'adjust_offset_pagination' ), 10, 2 );
		
		//Get post counts for both queries
		$post_query_post_count = $post_query_results->found_posts;
		$tax_query_post_count = $tax_query_results->found_posts;
		printf( '<input type="hidden" id="term-found-posts" value="%s" />', esc_attr( $tax_query_post_count ) );
		
		if ( $post_query_post_count >= 1000 ) {
			printf( '<div class="error"><p>%s</p></div>', sprintf( __( 'There are over %s posts found.  We do not recommend you sort these posts for performance reasons.', 'metronet_reorder_posts' ), number_format( $post_query_post_count ) ) );
		}
		if ( $post_query_post_count > $tax_query_post_count ) {
			//Output interface for adding custom field data to posts
			?>
			<h3><?php esc_html_e( 'Posts were found!', 'reorder-by-term' ); ?> </h3>
			<div class="updated"><p><?php esc_html_e( 'We found posts to display, however, we need to build the term data to reorder them correctly.', 'reorder-by-term' ); ?>&nbsp;<?php printf( '<a href="%s">%s</a>', esc_url( admin_url( 'tools.php?page=reorder-by-term' ) ), esc_html__( 'Build the Term Data Now.', 'reorder-by-term' ) ); ?></p></div>
			<?php //submit_button( __( 'Add data to posts', 'reorder-by-term' ), 'primary', 'reorder-add-data' ); ?>
			<?php
		} else {
			//Output Main Interface
			if( $tax_query_results->have_posts() ) {
				printf( '<h3>%s</h3>', esc_html__( 'Reorder', 'metronet-reorder-posts' ) );
				?>
				<div><img src="<?php echo esc_url( admin_url( 'images/loading.gif' ) ); ?>" id="loading-animation" /></div>
				<?php
				echo '<ul id="post-list">';
				while( $tax_query_results->have_posts() ) {
					global $post;
					$tax_query_results->the_post();
					$this->output_row( $post, $tax, $term_slug );	
				}
				echo '</ul><!-- #post-list -->';
				
				//Show pagination links
				if( $tax_query_results->max_num_pages > 1 ) {
					echo '<div id="reorder-pagination">';
					$current_url = add_query_arg( array( 'paged' => '%#%' ) );
					$pagination_args = array(
						'base' => $current_url,
						'total' => $tax_query_results->max_num_pages,
						'current' => ( $page == 0 ) ? 1 : $page
					);
					echo paginate_links( $pagination_args );
					echo '</div>';
				}
				$options = MN_Reorder_Admin::get_instance()->get_plugin_options();
				if ( ! isset( $options[ 'rt_show_query' ] ) || 'on' === $options[ 'rt_show_query' ] ):
				printf( '<h3>%s</h3>', esc_html__( 'Reorder Terms Query', 'reorder-by-term' ) );
				printf( '<p>%s</p>', esc_html__( 'You will need custom code to query by term.  Here are some example query arguments.', 'reorder-by-term' ) );
				$meta_key = sprintf( '_reorder_term_%s_%s', $tax, $term_slug );
$query = "
'post_type' => '{$post_type}',
'order' => '{$order}',
'post_status' => '{$post_status}',
'posts_per_page' => 50,
'meta_key' => '{$meta_key }',
'orderby' => 'meta_value_num title'
";
				printf( '<blockquote><pre><code>%s</code></pre></blockquote>', esc_html( print_r( $query, true ) ) );
				endif;
			} else {
				echo sprintf( '<h3>%s</h3>	', esc_html__( 'There is nothing to sort at this time', 'metronet-reorder-posts' ) );	
			}	
		}
	} //end output_posts
	
	/**
	 * Outputs a post to the screen
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since 1.0.0
	 * @access private
	 * @param object $post The WordPress Post object
	 * @param string $taxonomy The current taxonomy
	 * @param string $term_slug The term Slug
	 * @uses output_posts method
	 */
	private function output_row( $post, $taxonomy, $term_slug ) {
		global $post;
		setup_postdata( $post );
		$menu_order = get_post_meta( $post->ID, sprintf( '_reorder_term_%s_%s', $taxonomy, $term_slug ), true );
		?>
		<li id="list_<?php the_id(); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" data-term="<?php echo esc_attr( $term_slug ); ?>" data-id="<?php the_id(); ?>" data-menu-order="<?php echo absint( $menu_order ); ?>" data-parent="0" data-post-type="<?php echo esc_attr( $post->post_type ); ?>">
			<div class="row">
				<div class="row-content non-hierarchical">
					<?php the_title(); ?><?php echo ( defined( 'REORDER_DEBUG' ) && REORDER_DEBUG == true ) ? ' - Menu Order:' . absint( $menu_order ) : ''; ?>
				</div><!-- .row-content -->
			</div><!-- .row -->
		</li>
		<?php
	} //end output_row
}	