<?php
/**
 * Helper class for WikiForum extension, for showing the overview, parsing special text, etc
 *
 * @file
 * @ingroup Extensions
 */
class WikiForum {

	/**
	 * Show an error message for the given title, message, and optional icon
	 *
	 * @param string $errorTitleMsg message key
	 * @param string $errorMessageMsg message key
	 * @param string $errorIcon icon finename (optional)
	 * @return string HTML
	 */
	static function showErrorMessage( $errorTitleMsg, $errorMessageMsg, $errorIcon = 'exclamation.png' ) {
		global $wgExtensionAssetsPath;

		$errorTitle = wfMessage( $errorTitleMsg )->text();
		$errorMessage = wfMessage( $errorMessageMsg )->text();

		$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/resources/images/' . $errorIcon . '" /> ';

		$output	= '<br /><table class="mw-wikiforum-frame"><tr><td>' . $icon . $errorTitle . '<p class="mw-wikiforum-descr">' . $errorMessage . '</p></td></tr></table>';

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
		global $wgExtensionAssetsPath;

		$output = '';

		$dbr = wfGetDB( DB_REPLICA );
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
			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/resources/images/database_add.png" title="' . wfMessage( 'wikiforum-add-category' )->text() . '" /> ';
			$menuLink = $icon . '<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addcategory' ] ) ) . '">' .
				wfMessage( 'wikiforum-add-category' )->text() . '</a>';
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

		if ( strlen( $what ) > 1 ) {
			$i = 0;

			$title = wfMessage( 'wikiforum-search-hits', $i )->parse();
			$output .= WikiForumGui::showSearchHeader( $title );

			$dbr = wfGetDB( DB_REPLICA );
			// buildLike() will escape the query properly, add the word LIKE and the "double quotes"
			$likeString = $dbr->buildLike( $dbr->anyString(), $what, $dbr->anyString() );

			$limit = intval( wfMessage( 'wikiforum-max-threads-per-page' )->inContentLanguage()->plain() );

			$threadData = $dbr->select(
				'wikiforum_threads',
				'*',
				"(wft_thread_name $likeString OR wft_text $likeString)",
				__METHOD__,
				[ 'ORDER BY' => 'wft_posted_timestamp DESC', 'LIMIT' => $limit ]
			);

			foreach ( $threadData as $sql ) {
				$thread = WFThread::newFromSQL( $sql );
				$output .= $thread->showHeaderForSearch();

				$i++;
			}

			$replyData = $dbr->select(
				'wikiforum_replies',
				'*',
				"wfr_reply_text $likeString",
				__METHOD__,
				[ 'ORDER BY' => 'wfr_posted_timestamp DESC', 'LIMIT' => $limit ]
			);

			foreach ( $replyData as $sql ) {
				$reply = WFReply::newFromSQL( $sql );
				$output .= $reply->showForSearch();

				$i++;
			}

			$output .= '</table>' . WikiForumGui::showFrameFooter();
		} else {
			return self::showErrorMessage( 'wikiforum-error-search', 'wikiforum-error-search-missing-query' );
		}
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

		if ( $user->isAnon() ) { // Do no further processing for anons, since anons cannot have groups.
			return Linker::link( Title::makeTitle( NS_USER_TALK, $username ), $username );
		}

		$retVal = Linker::link( Title::makeTitle( NS_USER, $username ), $username );

		$groups = $user->getEffectiveGroups();
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

		Hooks::run( 'WikiForumSig', [ &$groupText, $user ] );

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
			'WikiForum::threadLinkFromID', // array( $this, 'getThreadTitle' ),
			$text
		);
		return $text;
	}

	/**
	 * Get a link to a thread from it's ID, for parseLinks() above
	 *
	 * @param int $id
	 * @return string|int
	 */
	static function threadLinkFromID( $id ) {
		$thread = WFThread::newFromID( $id );
		if ( $thread ) {
			return '<i>' . $thread->showLink() . '</i>';
		} else { // fallback, got to return something
			return $id;
		}
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
			'<blockquote><p class="posted">\1</p><span>&raquo;</span>',
			$text
		);
		$text = str_replace(
			'[quote]',
			'<blockquote><span>&raquo;</span>',
			$text
		);
		$text = str_replace(
			'[/quote]',
			'<span>&laquo;</span></blockquote>',
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
}
