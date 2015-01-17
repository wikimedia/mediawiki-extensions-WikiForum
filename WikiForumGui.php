<?php
/**
 * Graphical User Interface (GUI) methods used by WikiForum extension.
 *
 * All class methods are static.
 *
 * @file
 * @ingroup Extensions
 */
class WikiForumGui {
	/**
	 * Show the header for thread and search pages
	 *
	 * @return string, html
	 */
	static function showFrameHeader() {
		return '<table class="mw-wikiforum-frame" cellspacing="10"><tr><td class="mw-wikiforum-innerframe">';
	}

	/**
	 * Show the footer for thread and search pages
	 *
	 * @return string, HTML
	 */
	static function showFrameFooter() {
		return '</td></tr></table>';
	}

	/**
	 * Show the search box
	 *
	 * @return string
	 */
	static function showSearchbox() {
		global $wgExtensionAssetsPath;

		$url = htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( array( 'wfaction' => 'search' ) ) );

		$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/zoom.png" id="mw-wikiforum-searchbox-picture" title="' . wfMessage( 'search' )->text() . '" />';

		$output = '<div id="mw-wikiforum-searchbox"><form method="post" action="' . $url . '">' .
			'<div id="mw-wikiforum-searchbox-border">' . $icon .
			'<input type="text" value="" name="query" id="txtSearch" /></div>
		</form></div>';

		return $output;
	}

	/**
	 * Builds the header row -- the breadcrumb navigation
	 * (Overview > Category name > Forum > Thread)
	 *
	 * @param $links string: the actual overview/category/etc links
	 * @param $additionalLinks string: more links to add on the other side - 'Add a new forum'-type links
	 * @return string: HTML
	 */
	static function showHeaderRow( $links, $additionalLinks = '' ) {
		global $wgUser, $wgWikiForumAllowAnonymous;

		$output = '<table class="mw-wikiforum-headerrow"><tr><td class="mw-wikiforum-leftside">';
		$output .= $links;

		if ( strlen( $additionalLinks ) > 0 && ( $wgWikiForumAllowAnonymous || $wgUser->isLoggedIn() ) ) {
			$output .= '</td><td class="mw-wikiforum-rightside">' . $additionalLinks;
		}

		$output .= '</td></tr></table>';
		return $output;
	}

	/**
	 * Gets the footer row, in other words: pagination links.
	 *
	 * @param $page Integer: number of the current page
	 * @param $maxissues Integer: amount of replies, fetched from the DB
	 * @param $limit Integer: limit; this is also used for the SQL query
	 * @param $params array: URL params to be passed, should have a thread or forum number
	 * @return string HTML
	 */
	static function showFooterRow( $page, $maxissues, $limit, $params ) {
		$output = '';
		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		if ( $maxissues / $limit > 1 ) {
			$output = '<table class="mw-wikiforum-footerrow"><tr><td class="mw-wikiforum-leftside">' .
				wfMessage( 'wikiforum-pages' )->text() . wfMessage( 'word-separator' )->plain();

			for ( $i = 1; $i < ( $maxissues / $limit ) + 1; $i++ ) {
				$urlParams = array_merge( array( 'page' => $i ), $params );

				if ( $i <= 9 ) {
					$pageNumber = '0' . $i;
				} else {
					$pageNumber = $i;
				}

				if ( $i != $page + 1 ) {
					$output .= '<a href="' . htmlspecialchars( $specialPage->getFullURL( $urlParams ) ) . '">' . $pageNumber . '</a>';
				} else {
					$output .= '[' . $pageNumber . ']';
				}

				$output .= wfMessage( 'word-separator' )->plain();
			}
			$output .= '</td><td class="mw-wikiforum-rightside">';
			$output .= '</td></tr></table>';
		}
		return $output;
	}

	/**
	 * Show the header for Forum and Category pages
	 *
	 * @param string $title1
	 * @param string $title2
	 * @param string $title3
	 * @param string $title4
	 * @param string $title5: optional, admin icons if given
	 * @return string, HTML
	 */
	static function showMainHeader( $title1, $title2, $title3, $title4, $title5 = '' ) {
		return self::showFrameHeader() . '<table class="mw-wikiforum-title">' .
			self::showMainHeaderRow( $title1, $title2, $title3, $title4, $title5 );
	}

	/**
	 * Show the header for the <WikiForumList> tag
	 *
	 * @param string $title1
	 * @param string $title2
	 * @param string $title3
	 * @param string $title4
	 * @return string, HTML
	 */
	static function showListTagHeader( $title1, $title2, $title3, $title4 ) {
		return '<table class="mw-wikiforum-mainpage" cellspacing="0">' .
			self::showMainHeaderRow( $title1, $title2, $title3, $title4 );
	}

	/**
	 * Show the header row. Only called from other GUI methods.
	 *
	 * @param string $title1
	 * @param string $title2
	 * @param string $title3
	 * @param string $title4
	 * @param string $title5: optional, admin icons if given
	 * @return string, HTML
	 */
	private static function showMainHeaderRow( $title1, $title2, $title3, $title4, $title5 = '' ) {
		$output = '<tr class="mw-wikiforum-title"><th class="mw-wikiforum-title">' . $title1 . '</th>';

		if ( $title5 ) {
			$output .= '<th class="mw-wikiforum-admin"><p class="mw-wikiforum-valuetitle">' . $title5 . '</p></th>';
		}
		$output .= '<th class="mw-wikiforum-value"><p class="mw-wikiforum-valuetitle">' . $title2 . '</p></th>
			<th class="mw-wikiforum-value"><p class="mw-wikiforum-valuetitle">' . $title3 . '</p></th>
			<th class="mw-wikiforum-lastpost"><p class="mw-wikiforum-valuetitle">' . $title4 . '</p></th></tr>';

		return $output;
	}

	/**
	 * Show the footer for Forum and Category pages
	 *
	 * @return string, HTML
	 */
	static function showMainFooter() {
		return '</table>' . self::showFrameFooter();
	}

	/**
	 * Show the footer for the <WikiForumList> tag
	 *
	 * @return string, HTML
	 */
	static function showListTagFooter() {
		return '</table>';
	}

	/**
	 * Only for search results: show the header row
	 *
	 * @param string $title
	 * @return string
	 */
	static function showSearchHeader( $title ) {
		return self::showFrameHeader() . '
			<table style="width:100%">
				<tr>
					<th class="mw-wikiforum-thread-top" colspan="2">' .
						$title .
					'</th>
				</tr>';
	}

	/**
	 * Show the bottom line of a thread or reply
	 *
	 * @param string $posted
	 * @param string $buttons: optional, admin icons if given
	 * @return string, HTML
	 */
	static function showBottomLine( $posted, $buttons = '' ) {
		global $wgUser;

		$output = '<table cellspacing="0" cellpadding="0" class="mw-wikiforum-posted">' .
			'<tr><td class="mw-wikiforum-leftside">' . $posted . '</td>';

		if ( $wgUser->isLoggedIn() ) {
			$output .= '<td class="mw-wikiforum-rightside">' . $buttons . '</td>';
		}

		$output .= '</tr></table>';

		return $output;
	}

	/**
	 * Get the editor form for writing a new thread, a reply, etc.
	 *
	 * @param $showCancel: show the cancel button?
	 * @param $params Array: URL parameter(s) to be passed to the form (i.e. array( 'thread' => $threadId ))
	 * @param $input String: used to add extra input fields
	 * @param $height String: height of the textarea, i.e. '10em'
	 * @param $text_prev
	 * @param $saveButton String: save button text
	 * @return String: HTML
	 */
	static function showWriteForm( $showCancel, $params, $input, $height, $text_prev, $saveButton ) {
		global $wgOut, $wgUser, $wgWikiForumAllowAnonymous;

		$output = '';

		if ( $wgWikiForumAllowAnonymous || $wgUser->isLoggedIn() ) {
			$wgOut->addModules( 'mediawiki.action.edit' ); // Required for the edit buttons to display

			$output = '<form name="frmMain" method="post" action="' . htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( $params ) ) . '" id="writereply">
			<table class="mw-wikiforum-frame" cellspacing="10">' . $input . '
				<tr>
					<td>' . EditPage::getEditToolbar() . '</td>
				</tr>
				<tr>
					<td><textarea name="text" id="wpTextbox1" style="height: ' . $height . ';">' . $text_prev . '</textarea></td>
				</tr>';
			if ( WikiForumClass::useCaptcha() ) {
				$output .= '<tr><td>' . WikiForumClass::getCaptcha() . '</td></tr>';
			}
			$output .= '<tr>
					<td>
						<input type="submit" value="' . $saveButton . '" accesskey="s" title="' . $saveButton . ' [s]" />';
			if ( $showCancel ) {
				$output .= ' <input type="button" value="' . wfMessage( 'cancel' )->text() . '" accesskey="c" onclick="javascript:history.back();" title="' . wfMsg( 'cancel' ) . ' [c]" />';
			}
			$output .= '</td>
					</tr>
				</table>
			</form>' . "\n";
		}
		return $output;
	}

	/**
	 * Get the main form for forums and categories
	 *
	 * @param string $url: url to send form to, with GET params
	 * @param string $extraRow: row to add in after title input, for forums but not categories
	 * @param string $formTitle: title for the form
	 * @param string $titlePlaceholder: placeholder value for the title input
	 * @param string $titleValue: value for the title input
	 * @return string: HTML the form
	 */
	static function showTopLevelForm( $url, $extraRow = '', $formTitle, $titlePlaceholder, $titleValue ) {
		return '
		<form name="frmMain" method="post" action="' . $url . '" id="form">
			<table class="mw-wikiforum-frame" cellspacing="10">
				<tr>
					<th class="mw-wikiforum-title">' . $formTitle . '</th>
				</tr>
				<tr>
					<td>
						<p>' . wfMessage( 'wikiforum-name' )->text() . '</p>
						<input type="text" name="name" style="width: 100%" value="' . $titleValue . '" placeholder="' . $titlePlaceholder . '" />
					</td>
				</tr>
					' . $extraRow . '
				<tr>
					<td>
						<input type="submit" value="' . wfMessage( 'wikiforum-save' )->text() . '" accesskey="s" title="' . wfMessage( 'wikiforum-save' )->text() . '" [s]" />
						<input type="button" value="' . wfMessage( 'cancel' )->text() . '" accesskey="c" onclick="javascript:history.back();" title="' . wfMessage( 'cancel' )->text() . ' [c]" />
					</td>
				</tr>
			</table>
		</form>';
	}

	/**
	 * Show the user and timestamp of when something was first posted. (With link)
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string
	 */
	static function showPostedInfo( $timestamp, User $user ) {
		$userLink = WikiForumClass::showUserLink( $user );
		return self::showInfo( 'wikiforum-posted', $timestamp, $userLink, $user->getName() );
	}

	/**
	 * Show the user and timestamp of when something was first posted, without any link. (Apparently needed for quoting)
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string
	 */
	static function showPlainPostedInfo( $timestamp, User $user ) {
		return self::showInfo( 'wikiforum-posted', $timestamp, $user->getName(), $user->getName() );
	}

	/**
	 * Show the user and timestamp of when something was edited
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string
	 */
	static function showEditedInfo( $timestamp, User $user ) {
		$userLink = WikiForumClass::showUserLink( $user );
		return self::showInfo( 'wikiforum-edited', $timestamp, $userLink, $user->getName() );
	}

	/**
	 * Show the user and timestamp of the last post in a container
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string
	 */
	static function showByInfo( $timestamp, User $user ) {
		$userLink = WikiForumClass::showUserLink( $user );
		return self::showInfo( 'wikiforum-by', $timestamp, $userLink, $user->getName() );
	}

	/**
	 * Show an 'info' link, with user and timestamp of an action. Do not use, use show*Info() methods above.
	 *
	 * @param string $message
	 * @param string $timestamp
	 * @param string $userLink
	 * @param string $userText
	 * @return string
	 */
	private static function showInfo( $message, $timestamp, $userLink, $userText ) {
		global $wgLang;

		return wfMessage(
			$message,
			$wgLang->timeanddate( $timestamp ),
			$userLink,
			$userText,
			$wgLang->date( $timestamp ),
			$wgLang->time( $timestamp )
		)->text();

	}
}