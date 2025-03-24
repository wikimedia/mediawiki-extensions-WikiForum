<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

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
	 * Show the search box
	 *
	 * @return string
	 */
	static function showSearchbox() {
		$url = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'search' ] );

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-wikiforum-searchbox' ],
			Html::rawElement(
				'form',
				[ 'method' => 'post', 'action' => $url ],
				(
					Html::rawElement(
						'label',
						[ 'for' => 'mw-wikiforum-searchbox-text' ],
						WikiForum::getIconHTML( 'wikiforum-searchbox', wfMessage( 'search' ) )
					) .
					Html::element(
						'input',
						[
							'type' => 'text',
							'name' => 'query',
							'value' => '',
							'id' => 'mw-wikiforum-searchbox-text',
							'placeholder' => wfMessage( 'search' )->text()
						]
					)
				)
			)
		);
	}

	/**
	 * Builds the header row -- the breadcrumb navigation
	 * (Overview > Category name > Forum > Thread)
	 *
	 * @param string $links the actual overview/category/etc links
	 * @param UserIdentity $user
	 * @param string $additionalLinks more links to add on the other side - 'Add a new forum'-type links
	 * @return string HTML
	 */
	static function showHeaderRow( $links, UserIdentity $user, $additionalLinks = '' ) {
		global $wgWikiForumAllowAnonymous;

		$output = '<table class="mw-wikiforum-headerrow"><tr><td class="mw-wikiforum-leftside">';
		$output .= $links;

		if ( strlen( $additionalLinks ) > 0 && ( $wgWikiForumAllowAnonymous || $user->isRegistered() ) ) {
			$output .= '</td><td class="mw-wikiforum-rightside">' . $additionalLinks;
		}

		$output .= '</td></tr></table>';
		return $output;
	}

	/**
	 * Gets the footer row, in other words: pagination links.
	 *
	 * @param int $page number of the current page
	 * @param int $maxissues amount of replies, fetched from the DB
	 * @param int $limit limit; this is also used for the SQL query
	 * @param array $params URL params to be passed, should have a thread or forum number
	 * @return string HTML
	 */
	static function showFooterRow( $page, $maxissues, $limit, $params ) {
		$output = '';
		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		if ( $maxissues / $limit > 1 ) {
			for ( $i = 1; $i < ( $maxissues / $limit ) + 1; $i++ ) {
				$urlParams = array_merge( [ 'page' => $i ], $params );

				if ( $i <= 9 ) {
					$pageNumber = '0' . $i;
				} else {
					$pageNumber = $i;
				}

				$output = '<table class="mw-wikiforum-footerrow"><tr><td class="mw-wikiforum-leftside">' .
					wfMessage( 'wikiforum-pages' )->numParams( $pageNumber )->parse() .
					wfMessage( 'word-separator' )->parse();

				if ( $i != $page + 1 ) {
					$output .= '<a href="' . htmlspecialchars( $specialPage->getFullURL( $urlParams ) ) . '">' . $pageNumber . '</a>';
				} else {
					$output .= '[' . $pageNumber . ']';
				}

				$output .= wfMessage( 'word-separator' )->parse();
			}
			$output .= '</td><td class="mw-wikiforum-rightside">';
			$output .= '</td></tr></table>';
		}
		return $output;
	}

	/**
	 * Show the header row for Forum and Category pages, <WikiForumList> tag
	 *
	 * @note Caller(s) should escape the $titleN variables!
	 *
	 * @param string $title1
	 * @param string $title2
	 * @param string $title3
	 * @param string $title4
	 * @param string $title5 optional, admin icons if given
	 * @return string HTML
	 */
	static function showMainHeaderRow( $title1, $title2, $title3, $title4, $title5 = '' ) {
		$output = '<tr class="mw-wikiforum-header-row"><th class="mw-wikiforum-title">' . $title1 . '</th>';

		if ( $title5 ) {
			$output .= '<th class="mw-wikiforum-admin"><p class="mw-wikiforum-valuetitle">' . $title5 . '</p></th>';
		}
		$output .= '<th class="mw-wikiforum-value"><p class="mw-wikiforum-valuetitle">' . $title2 . '</p></th>
			<th class="mw-wikiforum-value"><p class="mw-wikiforum-valuetitle">' . $title3 . '</p></th>
			<th class="mw-wikiforum-lastpost"><p class="mw-wikiforum-valuetitle">' . $title4 . '</p></th></tr>';

		return $output;
	}

	/**
	 * Only for search results: show the header row
	 *
	 * @param string $title
	 * @return string
	 */
	static function showSearchHeader( $title ) {
		return '<div class="mw-wikiforum-frame">' . '
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
	 * @param User $user
	 * @param string $buttons optional, admin icons if given
	 * @return string HTML
	 */
	static function showBottomLine( $posted, User $user, $buttons = '' ) {
		$output = '<table cellspacing="0" cellpadding="0" class="mw-wikiforum-posted">' .
			'<tr><td class="mw-wikiforum-leftside">' . $posted . '</td>';

		if ( $user->isRegistered() ) {
			$output .= '<td class="mw-wikiforum-rightside">' . $buttons . '</td>';
		}

		$output .= '</tr></table>';

		return $output;
	}

	/**
	 * Get the editor form for writing a new thread, a reply, etc.
	 *
	 * @param bool $showCancel show the cancel button?
	 * @param array $params URL parameter(s) to be passed to the form (i.e. array( 'thread' => $threadId ))
	 * @param string $input used to add extra input fields
	 * @param string $height height of the textarea, i.e. '10em'
	 * @param string $text_prev
	 * @param string $saveButton save button text
	 * @param User $user
	 * @return string HTML
	 */
	static function showWriteForm( $showCancel, $params, $input, $height, $text_prev, $saveButton, User $user ) {
		global $wgWikiForumAllowAnonymous;

		$output = '';

		$requestContext = RequestContext::getMain();
		$out = $requestContext->getOutput();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikiEditor' ) ) {
			if ( MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $user, 'usebetatoolbar' ) ) {
				$out->addModuleStyles( 'ext.wikiEditor.styles' );
				$out->addModules( 'ext.wikiEditor' );
			}

			$toolbar = '';
		} else {
			$toolbar = EditPage::getEditToolbar();
		}

		if ( $wgWikiForumAllowAnonymous || $user->isRegistered() ) {
			$out->addModules( 'mediawiki.action.edit' ); // Required for the edit buttons to display

			$output = '<form name="frmMain" method="post" action="' . htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( $params ) ) . '" id="writereply">
			<table class="mw-wikiforum-frame" cellspacing="10">' . $input . '
				<tr>
					<td>' . $toolbar . '</td>
				</tr>
				<tr>
					<td><textarea name="text" id="wpTextbox1" style="height: ' . $height . ';">' . $text_prev . '</textarea></td>
				</tr>';
			if ( WikiForum::useCaptcha( $user ) ) {
				$output .= '<tr><td>' . WikiForum::getCaptcha( $out ) . '</td></tr>';
			}
			$output .= '<tr>
					<td>
						<input type="hidden" name="wpToken" value="' . $user->getEditToken() . '" />
						<input type="submit" value="' . $saveButton . '" accesskey="s" title="' . $saveButton . ' [s]" />';
			if ( $showCancel ) {
				$output .= ' <input type="button" value="' . wfMessage( 'cancel' )->escaped() . '" accesskey="c" onclick="javascript:history.back();" title="' . wfMessage( 'cancel' )->escaped() . ' [c]" />';
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
	 * @param string $url url to send form to, with GET params
	 * @param string $extraRow row to add in after title input, for forums but not categories
	 * @param string $formTitle title for the form
	 * @param string $titlePlaceholder placeholder value for the title input
	 * @param string $titleValue value for the title input
	 * @return string HTML the form
	 */
	static function showTopLevelForm( $url, $extraRow, $formTitle, $titlePlaceholder, $titleValue ) {
		return '
		<form name="frmMain" method="post" action="' . $url . '" id="form">
			<table class="mw-wikiforum-frame" cellspacing="10">
				<tr>
					<th class="mw-wikiforum-title">' . $formTitle . '</th>
				</tr>
				<tr>
					<td>
						<p>' . wfMessage( 'wikiforum-name' )->escaped() . '</p>
						<input type="text" name="name" style="width: 100%" value="' . $titleValue . '" placeholder="' . $titlePlaceholder . '" />
					</td>
				</tr>
					' . $extraRow . '
				<tr>
					<td>
						<input type="hidden" name="wpToken" value="' . RequestContext::getMain()->getUser()->getEditToken() . '" />
						<input type="submit" value="' . wfMessage( 'wikiforum-save' )->escaped() . '" accesskey="s" title="' . wfMessage( 'wikiforum-save' )->escaped() . '" [s]" />
						<input type="button" value="' . wfMessage( 'cancel' )->escaped() . '" accesskey="c" onclick="javascript:history.back();" title="' . wfMessage( 'cancel' )->escaped() . ' [c]" />
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
		$userLink = WikiForum::showUserLink( $user );
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
		$userLink = WikiForum::showUserLink( $user );
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
		$userLink = WikiForum::showUserLink( $user );
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
		$lang = RequestContext::getMain()->getLanguage();

		return wfMessage(
			$message,
			$lang->timeanddate( $timestamp ),
			$userLink,
			$userText,
			$lang->date( $timestamp ),
			$lang->time( $timestamp )
		)->text();
	}
}
