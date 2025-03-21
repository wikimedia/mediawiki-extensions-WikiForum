<?php
/**
 * Helper class for WikiForum extension, for showing the overview, parsing special text, etc.
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class WikiForum {

	/**
	 * Show an error message for the given title, message, and optional icon
	 *
	 * @param string $errorTitleMsg message key
	 * @param string $errorMessageMsg message key
	 * @param string $errorIcon icon CSS class fragment (optional)
	 * @return string HTML
	 */
	static function showErrorMessage( $errorTitleMsg, $errorMessageMsg, $errorIcon = 'error' ) {
		$errorTitle = wfMessage( $errorTitleMsg );
		$errorMessage = wfMessage( $errorMessageMsg )->parse();

		$icon = self::getIconHTML( 'wikiforum-' . $errorIcon, $errorTitle );
		$output	= '<br /><div class="mw-wikiforum-frame mw-wikiforum-error-msg">' . $icon . ' ' . $errorTitle->parse() . '<p class="mw-wikiforum-descr">' . $errorMessage . '</p></div>';

		return $output;
	}

	/**
	 * Show an overview of all available categories and their forums.
	 * Used in the special page class.
	 *
	 * @param User $user
	 * @return string HTML
	 */
	static function showOverview( User $user ) {
		$output = '';

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sqlCategories = $dbr->select(
			'wikiforum_category',
			'*',
			[],
			__METHOD__,
			[ 'ORDER BY' => 'wfc_sortkey ASC, wfc_category ASC' ]
		);

		if ( $sqlCategories->numRows() ) {
			$output .= WikiForumGui::showSearchbox();
		} else {
			$output .= wfMessage( 'wikiforum-forum-is-empty' )->parse(); // brand new installation, nothing here yet
		}

		foreach ( $sqlCategories as $sql ) {
			$cat = WFCategory::newFromSQL( $sql );

			$output .= $cat->showMain();
		}

		// Forum admins are allowed to add new categories
		if ( $user->isAllowed( 'wikiforum-admin' ) ) {
			$icon = self::getIconHTML( 'wikiforum-add-category' ) . ' ';
			$menuLink = $icon . Html::element(
				'a',
				[ 'href' => SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addcategory' ] ) ],
				wfMessage( 'wikiforum-add-category' )->text()
			);
			$output .= WikiForumGui::showHeaderRow( '', $user, $menuLink );
		}

		return $output;
	}

	/**
	 * Show the search results page.
	 *
	 * @param string $what the search query string.
	 * @param User $user
	 * @return string HTML output
	 */
	static function showSearchResults( $what, User $user ) {
		$output = WikiForumGui::showSearchbox();
		$output .= WikiForumGui::showHeaderRow( '', $user );

		if ( strlen( $what ) <= 1 ) {
			return self::showErrorMessage( 'wikiforum-error-search', 'wikiforum-error-search-missing-query' );
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		// buildLike() will escape the query properly, add the word LIKE and the "double quotes"
		$likeString = $dbr->buildLike( $dbr->anyString(), $what, $dbr->anyString() );

		$limit = intval( wfMessage( 'wikiforum-max-threads-per-page' )->inContentLanguage()->plain() );

		$threadData = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'wikiforum_threads' )
			->where( "wft_thread_name $likeString OR wft_text $likeString" )
			->orderBy( 'wft_posted_timestamp DESC' )
			->limit( $limit )
			->caller( __METHOD__ )->fetchResultSet();

		$replyData = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'wikiforum_replies' )
			->where( "wfr_reply_text $likeString" )
			->orderBy( 'wfr_posted_timestamp DESC' )
			->limit( $limit )
			->caller( __METHOD__ )->fetchResultSet();

		$i = 0;

		foreach ( $threadData as $sql ) {
			$thread = WFThread::newFromSQL( $sql );
			$outputRows .= $thread->showHeaderForSearch();
			$i++;
		}

		foreach ( $replyData as $sql ) {
			$reply = WFReply::newFromSQL( $sql );
			$outputRows .= $reply->showForSearch();
			$i++;
		}

		$output = Html::rawElement( 'div', [ 'class' => 'mw-wikiforum-frame' ],
			Html::rawElement( 'table', [ 'style' => 'width:100%' ],
				Html::rawElement( 'tr', [],
					Html::element( 'th',
						[ 'class' => 'mw-wikiforum-thread-top', 'colspan' => '2' ],
						wfMessage( 'wikiforum-search-hits', $i )->text()
					)
				)
				. $outputRows
			)
		);

		return $output;
	}

	/**
	 * Return a user object from fields from the DB
	 *
	 * @param int $actorID
	 * @param string $userIP
	 * @return User|bool
	 */
	public static function getUserFromDB( $actorID, $userIP ) {
		if ( $actorID ) {
			return User::newFromActorId( $actorID );
		} else {
			return User::newFromName( $userIP, false );
		}
	}

	/**
	 * Get the link to the specified user's userpage (and group membership)
	 *
	 * @param User $user user object
	 * @return string HTML
	 */
	public static function showUserLink( User $user ) {
		$username = $user->getName();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		if ( $user->isAnon() ) { // Do no further processing for anons, since anons cannot have groups.
			return $linkRenderer->makeLink(
				Title::makeTitle( NS_USER_TALK, $username ),
				$username
			);
		}

		$retVal = $linkRenderer->makeLink(
			Title::makeTitle( NS_USER, $username ),
			$username
		);

		$groups = MediaWikiServices::getInstance()
			->getUserGroupManager()
			->getUserEffectiveGroups( $user );
		$groupText = '';

		if ( in_array( 'sysop', $groups ) ) {
			$groupText .= wfMessage( 'word-separator' )->plain() .
				wfMessage(
					'parentheses',
					UserGroupMembership::getLink( 'sysop', RequestContext::getMain(), 'html', $username )
				)->text();

		} elseif ( in_array( 'forumadmin', $groups ) ) {
			$groupText .= wfMessage( 'word-separator' )->plain() .
				wfMessage(
					'parentheses',
					UserGroupMembership::getLink( 'forumadmin', RequestContext::getMain(), 'html', $username )
				)->text();
		}

		MediaWikiServices::getInstance()->getHookContainer()->run( 'WikiForumSig', [ &$groupText, $user ] );

		$retVal .= $groupText;

		return $retVal;
	}

	/**
	 * Show the HTML for the avatar for the given user
	 *
	 * @param User $user
	 * @return string HTML, the avatar
	 */
	static function showAvatar( User $user ) {
		$avatar = '';
		if ( class_exists( 'wAvatar' ) ) {
			$avatarObj = new wAvatar( $user->getId(), 'l' );
			$avatar = '<div class="wikiforum-avatar-image">';
			$avatar .= $avatarObj->getAvatarURL();
			$avatar .= '</div>';
		}
		return $avatar;
	}

	/**
	 * @param string $text
	 * @return string HTML
	 */
	static function parseIt( $text ) {
		global $wgOut;

		$text = $wgOut->parseAsContent( $text );
		$text = self::parseLinks( $text );
		$text = self::parseQuotes( $text );

		return $text;
	}

	/**
	 * Replace links like '[thread#21]' with actual HTML links to the given thread
	 *
	 * @param string $text
	 * @return string
	 */
	static function parseLinks( $text ) {
		$text = preg_replace_callback(
			'/\[thread#(.*?)\]/i',
			static function ( $id ) {
				$thread = WFThread::newFromID( $id );
				// fallback, got to return something
				return $thread ? '<i>' . $thread->showLink() . '</i>' : $id;
			},
			$text
		);
		return $text;
	}

	/**
	 * Parse quotes like '[quote=Author][/quote]' with actual HTML blockquotes
	 *
	 * @param string $text
	 * @return string
	 */
	static function parseQuotes( $text ) {
		$text = preg_replace(
			'/\[quote=(.*?)\]/',
			'<blockquote class="mw-wikiforum-bq"><p class="mw-wikiforum-bq-posted">\1</p><span class="mw-wikiforum-bq-marks">&raquo;</span>',
			$text
		);
		$text = str_replace(
			'[quote]',
			'<blockquote class="mw-wikiforum-bq"><span class="mw-wikiforum-bq-marks">&raquo;</span>',
			$text
		);
		$text = str_replace(
			'[/quote]',
			'<span class="mw-wikiforum-bq-marks">&laquo;</span></blockquote>',
			$text
		);
		return $text;
	}

	/**
	 * Should we require the user to pass a captcha?
	 *
	 * @param User $user
	 * @return bool
	 */
	public static function useCaptcha( User $user ) {
		global $wgCaptchaClass, $wgCaptchaTriggers;
		return $wgCaptchaClass &&
			isset( $wgCaptchaTriggers['wikiforum'] ) &&
			$wgCaptchaTriggers['wikiforum'] &&
			!$user->isAllowed( 'skipcaptcha' );
	}

	/**
	 * Return the HTML for the captcha
	 *
	 * @param OutputPage $out
	 * @return string
	 */
	public static function getCaptcha( $out ) {
		global $wgRequest;

		// NOTE: make sure we have a session. May be required for CAPTCHAs to work.
		$wgRequest->getSession()->persist();
		$output = wfMessage( "captcha-sendemail" )->parseAsBlock();

		$captcha = ConfirmEditHooks::getInstance();
		$captcha->setTrigger( 'wikiforum' );
		$captcha->setAction( 'post' );

		$formInformation = $captcha->getFormInformation();
		$formMetainfo = $formInformation;
		unset( $formMetainfo['html'] );
		$captcha->addFormInformationToOutput( $out, $formMetainfo );

		$output .= $formInformation['html'];

		return $output;
	}

	/**
	 * Return the HTML to display a CSS icon/sprite
	 *
	 * @param string $key Name of the icon in the CSS (usually the same as the message key)
	 * @param ?Message $titleMsg Message Title text, if not found with the same key as the icon
	 */
	public static function getIconHTML( string $key, ?Message $titleMsg = null ): string {
		if ( !$titleMsg ) {
			$titleMsg = wfMessage( $key );
		}
		return Html::element(
			'span',
			[
				'class' => 'mw-wikiforum-icon mw-' . $key . '-icon',
				'title' => $titleMsg->text()
			],
			''
		);
	}
}
