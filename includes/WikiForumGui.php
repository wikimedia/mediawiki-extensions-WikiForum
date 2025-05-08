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
		$output = Html::openElement( 'tr', [ 'class' => 'mw-wikiforum-header-row' ] )
			. Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-title' ], $title1 );

		if ( $title5 ) {
			$output .= Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-admin' ], $title5 );
		}
		$output .= Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-value' ], $title2 )
			. Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-value' ], $title3 )
			. Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-lastpost' ], $title4 )
			. Html::closeElement( 'tr' );

		return $output;
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
	 * @param string $boxClass CSS class to assign to the outer div
	 * @param string $text_prev
	 * @param string $saveButton "Save" button message key name
	 * @param User $user
	 * @return string HTML
	 */
	static function showWriteForm( $showCancel, $params, $input, $boxClass, $text_prev, $saveButton, User $user ) {
		global $wgWikiForumAllowAnonymous;

		if ( !( $wgWikiForumAllowAnonymous || $user->isRegistered() ) ) {
			return '';
		}

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

		$captcha = '';
		if ( WikiForum::useCaptcha( $user ) ) {
			$captcha = Html::rawElement( 'div',
				[ 'class' => 'mw-wikiforum-captcha' ],
				WikiForum::getCaptcha( $out )
			);
		}

		$out->addModules( 'mediawiki.action.edit' ); // Required for the edit buttons to display

		$output = Html::openElement( 'form',
				[
					'name' => 'frmMain',
					'method' => 'post',
					'action' => SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( $params ),
					'id' => "writereply",
				]
			) .
			Html::openElement( 'div', [ 'class' => 'mw-wikiforum-edit-reply mw-wikiforum-frame ' . $boxClass ] ) .
			$input .
			$toolbar .
			Html::rawElement( 'div', [],
				Html::Element( 'textarea',
					[
						'name' => 'text',
						'id' => 'wpTextbox1',
					],
					$text_prev
				)
			) .
			$captcha .
			Html::rawElement( 'div',
				[ 'class' => 'mw-wikiforum-replybuttons' ],
				Html::element( 'input',
					[
						'type' => 'hidden',
						'name' => 'wpToken',
						'value' => $user->getEditToken(),
					]
				) .
				Html::element( 'button',
					[
						'type' => 'submit',
						'accesskey' => 's',
						'title' => wfMessage( $saveButton )->text() . ' [s]',
					],
					 wfMessage( $saveButton )->text()
				) .
				( $showCancel ? Html::element( 'button',
					[
						'accesskey' => 'c',
						'onclick' => 'javascript:history.back();',
						'title' => wfMessage( 'cancel' )->text() . ' [c]',
					],
					wfMessage( 'cancel' )->text()
				) : '' )
			) .
			Html::closeElement( 'div' ) .
			Html::closeElement( 'form' );

		return $output;
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
