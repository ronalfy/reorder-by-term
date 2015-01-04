<?php
final class Reorder_By_Term_Helper  {
	private $post_type;
	private $posts_per_page;
	private $offset;
	private $reorder_page;
	private $tab_url;
	
	public function __construct( $args ) {
		// Parse arguments
		$defaults = array(
			'post_type'   => '',
			'posts_per_page' => 50,
			'offset' => 48
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
		
		add_action( 'metronet_reorder_menu_url_' . $this->post_type, array( $this, 'set_reorder_url' ) );
		add_action( 'reorder_by_term_interface_' . $this->post_type, array( $this, 'output_interface' ) );
		add_action( 'metronet_reorder_posts_add_menu_' . $this->post_type, array( $this, 'script_init' ) );
		add_filter( 'metronet_reorder_posts_tabs_' . $this->post_type, array( $this, 'add_tab' ) );
	
	}
	
	public function print_scripts() {
		//Overwrite action variable by de-registering sort script and adding it back in
		if ( isset( $_GET[ 'tab' ] ) && 'reorder-term' == $_GET[ 'tab' ] ) {
			wp_deregister_script( 'reorder_posts' );
			wp_enqueue_script( 'reorder_posts', REORDER_URL . '/scripts/sort.js', array( 'reorder_nested' ) ); //CONSTANT REORDER_URL defined in Metronet Reorder Posts
			wp_enqueue_script( 'reorder_terms', plugins_url( '/js/main.js', __FILE__ ), array( 'reorder_posts' ) );
			wp_localize_script( 'reorder_posts', 'reorder_posts', array(
				'action' => 'term_sort',
				'expand' => esc_js( __( 'Expand', 'metronet-reorder-posts' ) ),
				'collapse' => esc_js( __( 'Collapse', 'metronet-reorder-posts' ) ),
				'sortnonce' =>  wp_create_nonce( 'sortnonce' ),
				'hierarchical' => is_post_type_hierarchical( $this->post_type ) ? 'true' : 'false',
			) );	
		}
	}
	
	public function set_reorder_url( $url ) {
		$this->reorder_page = $url;
	}
	
	public function script_init( $menu_hook ) {
		add_action( 'admin_print_scripts-' . $menu_hook, array( $this, 'print_scripts' ), 20 );	
	}
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
	public function output_interface() {
		//Output Taxonomies
		$selected_tax = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : false;
		$taxonomies = get_object_taxonomies( $this->post_type, 'objects' );
		?>
		<h3><?php esc_html_e( 'Select a Taxonomy', 'reorder-by-term' ); ?></h3>
		<form id="reorder-taxonomy" method="get" action="<?php echo esc_url( $this->reorder_page ); ?>">
		<?php 
		foreach( $_GET as $key => $value ) {
				if ( 'term' == $key || 'taxonomy' == $key ) continue;
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
						if ( 'term' == $key ) continue;
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
		$main_offset = 48;
		$posts_per_page = 50;
		if ( $reorder_class ) {
			$post_status = $reorder_class->get_post_status();
			$order = $reorder_class->get_post_order();	
			$offset = $reorder_class->get_offset();
			$posts_per_page = $reorder_class->get_posts_per_page();
		}
		$page = isset( $_GET[ 'paged' ] ) ? absint( $_GET[ 'paged' ] ) : 0;
		if ( $page == 0 ) {
			$offset = 0;	
		} elseif ( $page > 1 ) {
			$offset = $main_offset * ( $page - 1 );
		}
		printf( '<input type="hidden" id="reorder-offset" value="%s" />', absint( $offset ) );
		$post_query_args = array(
			'post_type' => $post_type,
			'order' => $order,
			'post_status' => $post_status,
			'posts_per_page' => 1,
			'tax_query' => array(
				array(
					'taxonomy' => $tax,
					'terms' => $term_id
				)	
			),
			'orderby' => 'menu_order title',
		);
		$tax_query_args = $post_query_args;
		$tax_query_args[ 'meta_key' ] = sprintf( '_reorder_term_%s_%s', $tax, $term_slug );
		$tax_query_args[ 'orderby' ] = 'meta_value_num';
		$tax_query_args[ 'posts_per_page' ] = $this->posts_per_page;
		$tax_query_args[ 'offset' ] = $offset;
		
		//Perform Queries
		$post_query_results = new WP_Query( $post_query_args );
		$tax_query_results = new WP_Query( $tax_query_args );
		
		//Get post counts for both queries
		$post_query_post_count = $post_query_results->found_posts;
		$tax_query_post_count = $tax_query_results->found_posts;
		
		if ( $post_query_post_count > $tax_query_post_count ) {
			
		} else {
			//Output Main Interface	
		}
		
		die( '<pre>' . print_r( $post_query_post_count, true ) );
	} //end output_posts
}	
	
