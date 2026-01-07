/**
 * AJAX-ful and CSRF-safe deleting of threads and replies
 */
$( () => {
	// Delete a WikiForum thread or reply
	// Use event delegation to handle dynamically added elements
	$( document ).on( 'click', '.wikiforum-delete-thread-link,.wikiforum-delete-reply-link', function ( e ) {
		// Don't follow the link
		e.preventDefault();

		const isReply = $( this ).hasClass( 'wikiforum-delete-reply-link' ) ?
			true :
			false;
		const confirmMessage = isReply ?
			mw.message( 'wikiforum-confirm-delete-reply' ).text() :
			mw.message( 'wikiforum-confirm-delete-thread' ).text();

		// Ask for confirmation
		// eslint-disable-next-line no-alert
		if ( !confirm( confirmMessage ) ) {
			return;
		}

		let id = isReply ?
			$( this ).data( 'wikiforum-reply-id' ) :
			$( this ).data( 'wikiforum-thread-id' );

		// P A R A N O I A !
		id = Number( id );

		( new mw.Api() ).postWithToken( 'csrf', {
			action: 'wikiforum-delete-thread',
			isreply: isReply,
			id: id,
			format: 'json'
		} ).then( () => {
			// Reload the page to show updated state
			location.reload();
		} ).catch( () => {
			// Show error message
			// mw.notify requires mediawiki.notification module, but we'll use alert for simplicity
			// eslint-disable-next-line no-alert
			alert( mw.message( 'wikiforum-error-delete' ).text() );
		} );
	} );
} );
