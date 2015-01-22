jQuery ( document ).ready( function( $ ) {
	//Refresh when taxonomy/term is selected
	$( '#reorder-taxonomy select, #reorder-term select' ).change( function( e ) {
		if ( 'none' == $( this ).val() ) return;
		$( this ).parents( 'form:first' ).trigger( 'submit' );
	} );
	
	var reorder_type_ajax_callback = function( response ) {
		response = jQuery.parseJSON( response );
		if ( true == response.more_posts ) {
			$.post( ajaxurl, response, reorder_type_ajax_callback );
		} else {
			$( '#reorder-add-data' ).val( reorder_terms.refreshing_text );
			setTimeout( function() {
				window.location.reload();
			}, 3000 );
		}
	};
	//Handler for re-building custom post types in posts
	$( "#reorder-add-data" ).click( function( e ) {
		e.preventDefault();
		return;
		$( this ).prop( 'disabled', 'disabled' );
		$( this ).val( reorder_terms.loading_text );
		var callback_ajax_args = {
			action: reorder_terms.action,
			start: $( "#term-found-posts" ).val(),
			nonce: reorder_terms.sortnonce,
			taxonomy: $( "#reorder-tax-name" ).val(),
			term_id: $( "#reorder-term-id" ).val(),
			excluded: {},
			post_type: $( '#reorder-post-type' ).val()
		};
		$.post( ajaxurl, callback_ajax_args, reorder_type_ajax_callback );
	} );
} );