<?php
final class Reorder_By_Term_Helper  {
	private $post_type;
	private $posts_per_page;
	private $offset;
	private $reorder_page;
	
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
		add_filter( 'metronet_reorder_posts_tabs_' . $this->post_type, array( $this, 'add_tab' ) );
	
	}
	public function set_reorder_url( $url ) {
		$this->reorder_page = $url;
	}
	public function add_tab( $tabs = array() ) {
		//Make sure there are taxonomies attached to this post
		$taxonomies = get_object_taxonomies( $this->post_type );
		if ( empty( $taxonomies ) ) return $tabs;
		
		//Return Tab
		$tabs[] = array(
			'url' => add_query_arg( array( 'tab' => 'reorder-term' ), $this->reorder_page ) /* URL to the tab */,
			'label' => __( 'Reorder by Term', 'reorder-by-term' ),
			'get' => 'reorder-term' /*$_GET variable*/,
			'action' => 'reorder_by_term_interface_' . $this->post_type
		);
		return $tabs;
	}
	public function output_interface() {
		$selected_tax = isset( $_GET[ 'taxonomy' ] ) ? $_GET[ 'taxonomy' ] : '';
		$taxonomies = get_object_taxonomies( $this->post_type, 'objects' );
		?>
		<h3><?php esc_html_e( 'Select a Taxonomy', 'reorder-by-term' ); ?></h3>
		<select id="reorder-taxonomy">
			<?php
			foreach( $taxonomies as  $tax_name => $taxonomy ) {
				$label = $taxonomy->label;
				printf( '<option value="%s">%s</option>', esc_attr( $tax_name ), $label );
			}				
			?>
		</select>
		<?php
	}
}	
	
