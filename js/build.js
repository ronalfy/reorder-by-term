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
		
		var include_taxonomies = $( "input[name='include_tax[]']:checked" ).map(function( index,element ) { return $( element ).val(); } ).toArray();

		//Get taxonomies
		$.post( ajaxurl, { action: 'reorder_build_get_taxonomies', nonce: jQuery( document.getElementById( '_reorder_build_terms' ) ).val(), taxonomies: include_taxonomies }, function( tax_response ) {
			tax_response =  jQuery.parseJSON( tax_response );
						
			var $status_label = $( document.getElementById( 'build-term-status-container' ) ).addClass( 'updated' ).find( '#build-term-status-label' ).html( tax_response.return_label );
			
			var tax_length = tax_response.taxonomies.length;
			var tax_current_index = 0;
			var tax_ajax_callback = function( tax_ajax_args ) {
				//Show HTML Status
				if ( tax_response.taxonomies[ tax_current_index ].visible == false ) {
					tax_response.taxonomies[ tax_current_index ].visible = true;
					jQuery( document.getElementById( 'reorder_tax_' + tax_response.taxonomies[ tax_current_index ].name ) ).show();
				}
				
				//Ajax call - begin looping through taxonomy terms
				$.post( ajaxurl, tax_ajax_args, function( response ) {
					response = jQuery.parseJSON( response );
					//Update label
					jQuery( document.getElementById( 'reorder_tax_' + tax_response.taxonomies[ tax_current_index ].name ) ).find( '.term_count' ).html( response.term_count );
					
					//See if there are any terms left in the taxonomy
					if ( response.terms_left ) {
						tax_ajax_args.term_offset = response.term_offset;
						tax_ajax_args.post_ids = response.post_ids;
						tax_ajax_callback( tax_ajax_args );
					} else {
						tax_current_index++;
						if ( tax_current_index == tax_length ) {
							$status_label.html( $status_label.html() + reorder_term_build.process_done );
						} else {
							tax_ajax_args.taxonomy = tax_response.taxonomies[ tax_current_index ].name;
							tax_ajax_args.term_count = tax_response.taxonomies[ tax_current_index ].count;
							tax_ajax_args.term_offset = 0;
							tax_ajax_callback( tax_ajax_args );
						}
					}					
				} );
			};
			//Build default ajax args
			var ajax_args = {
				action: 'reorder_build_term_data',
				nonce: jQuery( document.getElementById( '_reorder_build_terms' ) ).val(),
				term_offset: 0,
				taxonomy: tax_response.taxonomies[ tax_current_index ].name,
				term_count: tax_response.taxonomies[ tax_current_index].count,
				post_ids: new Array()
				
			};
			//Build HTML Placeholders
			$.each( tax_response.taxonomies, function( index, value ) {
				var beginning_html = $status_label.html();
				var new_html = '<div id="reorder_tax_{tax_name}" style="display:none">' + reorder_term_build.process_update + '</div>';
				new_html = new_html.replace( /{tax_name}/g, value.name );
				new_html = new_html.replace( '{term_count}', '<span class="term_count">' + value.count + '</span>' );
				$status_label.html( beginning_html + new_html );
			} );

			tax_ajax_callback( ajax_args );
		} );
	} );
} );