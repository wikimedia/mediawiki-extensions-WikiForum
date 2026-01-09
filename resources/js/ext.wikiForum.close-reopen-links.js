/**
 * CSRF-safe closing and reopening of threads
 */
$( () => {
	// Close or reopen a WikiForum thread
	// Use event delegation to handle dynamically added elements
	$( document ).on( 'click', '.wikiforum-close-thread-link,.wikiforum-reopen-thread-link', function ( e ) {
		// Don't follow the link
		e.preventDefault();

		const isReopen = $( this ).hasClass( 'wikiforum-reopen-thread-link' );
		const action = isReopen ? 'reopenthread' : 'closethread';
		const threadId = $( this ).data( 'wikiforum-thread-id' );

		// P A R A N O I A !
		const threadIdNum = Number( threadId );

		// Get the URL from the link's href
		const url = $( this ).attr( 'href' );

		// Create a form and submit it with POST
		const form = $( '<form>' ).attr( {
			method: 'POST',
			action: url
		} );

		// Add CSRF token
		const token = mw.user.tokens.get( 'csrfToken' );
		$( '<input>' ).attr( {
			type: 'hidden',
			name: 'wpToken',
			value: token
		} ).appendTo( form );

		// Add action and thread ID
		$( '<input>' ).attr( {
			type: 'hidden',
			name: 'wfaction',
			value: action
		} ).appendTo( form );

		$( '<input>' ).attr( {
			type: 'hidden',
			name: 'thread',
			value: threadIdNum
		} ).appendTo( form );

		// Append form to body and submit
		form.appendTo( 'body' ).submit();
	} );
} );
