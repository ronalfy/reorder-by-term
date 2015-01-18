jQuery( document ).ready( function( $ ) {
	//Confirmation for delete term data functionality
	jQuery( document.getElementById( 'delete_terms_submit' ) ).click( function( e ) {
		if ( !confirm( reorder_term_build.delete_confirm ) ) {
			e.preventDefault();	
		}
	} );
	
	jQuery( document.getElementById( 'rebuild_terms_submit' ) ).click( function( e ) {
		$( this ).prop( 'disabled', 'disabled' );
		$( this ).val( reorder_term_build.build_submit_message );
		
		//Get taxonomies
		$.post( ajaxurl, { action: 'reorder_build_get_taxonomies', nonce: jQuery( document.getElementById( '_reorder_build_terms' ) ).val() }, function( response ) {
			response =  jQuery.parseJSON( response );
			
			var $status_label = $( document.getElementById( 'build-term-status-container' ) ).addClass( 'updated' ).find( '#build-term-status-label' ).html( response.return_label );
			$.each( response.taxonomies, function( index, value ) {
				var beginning_html = $status_label.html();
				var new_html = '<br /><span id="reorder_tax_{tax_name}">Processing {tax_name} with {term_count} terms left to process</span>';
				new_html = new_html.replace( /{tax_name}/g, value.name );
				new_html = new_html.replace( '{term_count}', value.count );
				
				$status_label.html( beginning_html + new_html );
				
			} );
		} );
	} );
} );