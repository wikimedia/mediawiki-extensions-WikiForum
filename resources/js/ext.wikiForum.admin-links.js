/**
 * AJAX-ful and CSRF-safe deleting of categories and forums as well as sorting 'em up and/or down
 */
$( function () {
	// Delete a WikiForum category or forum
	// @see WFCategory#showAdminIcons, WFForum#showAdminIcons
	$( '.wikiforum-delete-category-link,.wikiforum-delete-forum-link' ).on( 'click', function ( e ) {
		// Don't follow the link
		e.preventDefault();

		var isCategory = $( this ).hasClass( 'wikiforum-delete-category-link' ) ? true : false;
		// tableRow is the element we're deleting
		var id, tableRow;

		if ( isCategory ) {
			// lolololol this is so unholy...but it works. How strange is that?
			tableRow = $( this ).parent().parent().parent().parent().parent().parent();
			id = $( this ).data( 'wikiforum-category-id' );
		} else {
			tableRow = $( this ).parent().parent();
			id = $( this ).data( 'wikiforum-forum-id' );
		}

		// P A R A N O I A !
		id = Number( id );

		( new mw.Api() ).postWithToken( 'csrf', {
			action: 'wikiforum-admin-delete',
			iscategory: isCategory,
			id: id,
			format: 'json'
		} ).done( function () {
			// Currently the API response is 'OK' which is kinda meh.
			// So is the HTML that would get returned by the non-API PHP classes...
			tableRow.hide( 1000 );
		} );
	} );

	// Sort a category or forum up/down
	$( '.wikiforum-down-link,.wikiforum-up-link' ).on( 'click', function ( e ) {
		// Don't follow the link
		e.preventDefault();

		var direction = $( this ).hasClass( 'wikiforum-down-link' ) ? 'down' : 'up';
		var isCategory = $( this ).hasClass( 'wikiforum-category-sort-link' ) ? true : false;
		var id;

		if ( isCategory ) {
			id = $( this ).data( 'wikiforum-category-id' );
		} else {
			id = $( this ).data( 'wikiforum-forum-id' );
		}

		// P A R A N O I A !
		id = Number( id );

		// The element we're moving
		var tableRow = $( this ).parent().parent();

		( new mw.Api() ).postWithToken( 'csrf', {
			action: 'wikiforum-sort',
			direction: direction,
			iscategory: isCategory,
			id: id,
			format: 'json'
		} ).done( function () {
			// Currently the API response is 'OK' which is kinda meh.
			// So is the HTML that would get returned by the non-API PHP classes...
			// This one was tested on categories (but not yet on forums - TESTME!)
			tableRow.next().after( tableRow );
		} );
	} );
} );
