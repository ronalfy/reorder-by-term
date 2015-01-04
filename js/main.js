jQuery ( document ).ready( function( $ ) {
	$( '#reorder-taxonomy select, #reorder-term select' ).change( function( e ) {
		if ( 'none' == $( this ).val() ) return;
		$( this ).parents( 'form:first' ).trigger( 'submit' );
	} );
} );