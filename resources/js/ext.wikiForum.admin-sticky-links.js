$( () => {

	$( '.wikiforum-thread-make-sticky,.wikiforum-thread-remove-sticky' ).on( 'click', function ( e ) {
		// Don't follow the link
		e.preventDefault();

		let id = $( this ).data( 'wikiforum-thread-id' );

		const stickiness = ( $( this ).hasClass( 'wikiforum-thread-make-sticky' ) ? 'set' : 'remove' );

		// P A R A N O I A !
		id = Number( id );

		( new mw.Api() ).postWithToken( 'csrf', {
			action: 'wikiforum-set-thread-stickiness',
			stickiness: stickiness,
			id: id,
			format: 'json'
		} ).done( () => {
			// Currently the API response is 'OK' which is kinda meh.
			// So is the HTML that would get returned by the non-API PHP classes...
			// eslint-disable-next-line no-alert
			alert( 'OK!' ); // FIXME
			// Shitty, but such is life...
			// One day this can hopefully do a fancy animation or something instead,
			// but right now security is the main goal here, not fanciness.
			location.reload();
		} );
	} );
} );
