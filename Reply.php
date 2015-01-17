<?php

class WFReply extends ContextSource {

	private $data;
	public $thread;

	private function __construct( $sql ) {
		$this->data = $sql;
	}

	/**
	 * Return a new WFReply instance for the given reply ID
	 *
	 * @param int $id: ID to get the reply for
	 * @return WFReply
	 */
	public static function newFromID( $id ) {
		$dbr = wfGetDB( DB_SLAVE );

		$data = $dbr->selectRow(
			'wikiforum_replies',
			'*',
			array( 'wfr_reply_id' => $id ),
			__METHOD__
		);

		if ( $data ) {
			return new WFReply( $data );
		} else {
			return false;
		}
	}

	/**
	 * Return a new WFReply instance for the given reply text content
	 *
	 * @param string $text: text to get the reply for
	 * @return WFReply
	 */
	public static function newFromText( $text ) {
		$dbr = wfGetDB( DB_SLAVE );

		$data = $dbr->selectRow(
			'wikiforum_replies',
			'*',
			array( 'wfr_reply_text' => $text ),
			__METHOD__
		);

		if ( $data ) {
			return new WFReply( $data );
		} else {
			return false;
		}
	}

	/**
	 * Returns a WFReply instance for the given SQL row
	 *
	 * @param stdClass $sql: row from the DB. Not resultWrapper!
	 * @return WFReply
	 */
	public static function newFromSQL( $sql ) {
		return new WFReply( $sql );
	}

	/**
	 * Get the timestamp of when this thread was edited (if it was edited)
	 *
	 * @return string
	 */
	function getEditedTimestamp() {
		return $this->data->wfr_edit_timestamp;
	}

	/**
	 * Get this thread's text
	 *
	 * @return string
	 */
	function getText() {
		return $this->data->wfr_reply_text;
	}

	/**
	 * Get the reply's ID number
	 *
	 * @return int: the id
	 */
	function getId() {
		return $this->data->wfr_reply_id;
	}

	/**
	 * Get the ID of the user who posted the reply
	 *
	 * @return int: the ID
	 */
	function getPostedById() {
		return $this->data->wfr_user;
	}

	/**
	 * Get the user who posted the reply
	 *
	 * @return User
	 */
	function getPostedBy() {
		return WikiForumClass::getUserFromDB( $this->data->wfr_user, $this->data->wfr_user_ip );
	}

	/**
	 * Get the user who edited the reply, if it has been edited
	 *
	 * @return User
	 */
	function getEditedBy() {
		if ( $this->hasBeenEdited() ) {
			return WikiForumClass::getUserFromDB( $this->data->wfr_edit_user, $this->data->wfr_edit_user_ip );
		} else {
			return false;
		}
	}

	/**
	 * Get this reply's parent thread
	 *
	 * @return WFThread: the thread
	 */
	function getThread() {
		if ( !$this->thread ) {
			$this->thread = WFThread::newFromID( $this->data->wfr_thread );
		}
		return $this->thread;
	}

	/**
	 * Has the reply been edited?
	 *
	 * @return boolean: true if it has, false if it has not
	 */
	function hasBeenEdited() {
		return $this->data->wfr_edit_timestamp == true;
	}

	/**
	 * Show the user and timestamp of when this reply was first posted
	 *
	 * @return string
	 */
	function showPostedInfo() {
		return WikiForumGui::showPostedInfo( $this->data->wfr_posted_timestamp, $this->getPostedBy() );
	}

	/**
	 * Similar to showPostedInfo(), but without the link to the user. This is needed
	 * for quoting, where you don't want the HTML of the link displayed
	 *
	 * @return string
	 */
	function showPlainPostedInfo() {
		return WikiForumGui::showPlainPostedInfo( $this->data->wfr_posted_timestamp, $this->getPostedBy() );
	}

	/**
	 * Show the user and timestamp of when this reply was edited
	 *
	 * @return string
	 */
	function showEditedInfo() {
		return WikiForumGui::showEditedInfo( $this->data->wfr_edit_timestamp, $this->getEditedBy() );
	}

	/**
	 * Deletes the reply
	 *
	 * @return string: HTML
	 */
	function delete() {
		$user = $this->getUser();

		if (
			$user->isAnon() ||
			(
				$user->getId() != $this->getPostedById() &&
				!$user->isAllowed( 'wikiforum-moderator' )
			)
		) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->delete(
			'wikiforum_replies',
			array( 'wfr_reply_id' => $this->getId() ),
			__METHOD__
		);

		if ( !$result ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
		}

		return $this->getThread()->show();
	}


	/**
	 * Edit the reply
	 *
	 * @param $text String: new reply text
	 * @return string: HTML of thread
	 */
	function edit( $text ) {
		$user = $this->getUser();

		if ( strlen( $text ) == 0 ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-reply' );
		}

		if ( $text == $this->getText() ) {
			return true; // nothing to edit
		}

		if (
			$user->isAnon() ||
			(
				(
					$user->getId() != $this->getPostedById() &&
					$this->getThread()->isClosed()
				) ||
				!$user->isAllowed( 'wikiforum-moderator' )
			)
		) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-rights' );
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->update(
			'wikiforum_replies',
			array(
				'wfr_reply_text' => $text,
				'wfr_edit_timestamp' => wfTimestampNow(),
				'wfr_edit_user' => $user->getId(),
				'wfr_edit_user_ip' => $this->getRequest()->getIP(),
			),
			array( 'wfr_reply_id' => $this->getId() ),
			__METHOD__
		);

		return $this->getThread()->show();
	}

	/**
	 * Show this reply
	 *
	 * @return string: HTML the reply
	 */
	function show() {
		$posted = $this->showPostedInfo();

		if ( $this->getEditedTimestamp() > 0 ) {
			$posted .= '<br /><i>' . $this->showEditedInfo() . '</i>';
		}

		$avatar = WikiForumClass::showAvatar( $this->getPostedBy() );

		return '<tr><td class="mw-wikiforum-thread-sub" colspan="2" id="reply_' . $this->getId() . '">' . $avatar .
			WikiForumClass::parseIt( $this->getText() ) . WikiForumGui::showBottomLine( $posted, $this->showButtons() ) . '</td></tr>';
	}

	/**
	 * Show the reply for the search results page
	 *
	 * @return string
	 */
	function showForSearch() {
		$posted = $this->showPostedInfo();
		$posted .= '<br />' . wfMessage( 'wikiforum-search-thread', $this->getThread()->showLink( $this->getId() ) )->text();
		$avatar = WikiForumClass::showAvatar( $this->getPostedBy() );

		return '<tr><td class="mw-wikiforum-thread-sub" colspan="2" id="reply_' . $this->getId() . '">' . $avatar .
			WikiForumClass::parseIt( $this->getText() ) . WikiForumGui::showBottomLine( $posted, '' ) . '</td></tr>';
	}

	/**
	 * Show the reply buttons: quote, edit and delete.
	 *
	 * @return string: HTML
	 */
	function showButtons() {
		global $wgExtensionAssetsPath;

		$thread = $this->getThread();
		$user = $this->getUser();

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );
		$editButtons = '<a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'thread' => $thread->getId(), 'quotereply' => $this->getId() ) ) ) . '#writereply">';
		$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comments_add.png" title="' . wfMessage( 'wikiforum-quote' )->text() . '" />';

		if (
			(
				( $user->getId() == $thread->getPostedById() || $user->getId() == $this->getPostedById() )
				&& !$thread->isClosed()
			)
			||
			$user->isAllowed( 'wikiforum-moderator' )
		) {
			$editButtons .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'editreply', 'reply' => $this->getId() ) ) ) . '#writereply">';
			$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comment_edit.png" title="' . wfMessage( 'wikiforum-edit-reply' )->text() . '" />';
			$editButtons .= '</a> <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'deletereply', 'reply' => $this->getId() ) ) ) . '">';
			$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comment_delete.png" title="' . wfMessage( 'wikiforum-delete-reply' )->text() . '" />';
		}

		$editButtons .= '</a>';

		return $editButtons;
	}

	/**
	 * Add a reply with the text $text to the thread with ID = $threadId.
	 *
	 * @param WFThread $thread: thread to reply to
	 * @param string $text: reply text
	 * @return string: HTML of thread
	 */
	static function add( WFThread $thread, $text ) {
		global $wgRequest, $wgUser, $wgWikiForumAllowAnonymous, $wgWikiForumLogInRC, $wgLang;

		$timestamp = wfTimestampNow();

		if ( !$wgWikiForumAllowAnonymous && !$wgUser->isLoggedIn() ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
		}

		if ( strlen( $text ) == 0 ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-reply' );
		}

		if ( $thread->isClosed() ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-thread-closed' );
		}

		if ( WikiForumClass::useCaptcha() ) {
			$captcha = ConfirmEditHooks::getInstance();
			$captcha->trigger = 'wikiforum';
			if ( !ConfirmEditHooks::getInstance()->passCaptcha() ) {
				$output = WikiForumClass::showErrorMessage('wikiforum-error-add', 'wikiforum-error-captcha');
				$thread->preloadText = $text;
				$output .= $thread->show();
				return $output;
			}
		}

		$dbr = wfGetDB( DB_SLAVE );
		$doublepost = $dbr->selectRow(
			'wikiforum_replies',
			'wfr_reply_id',
			array(
				'wfr_reply_text' => $text,
				'wfr_user' => $wgUser->getId(),
				'wfr_thread' => $thread->getId(),
				'wfr_posted_timestamp > ' . ( $timestamp - ( 24 * 3600 ) )
			),
			__METHOD__
		);

		if ( $doublepost ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-double-post' );
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->insert(
			'wikiforum_replies',
			array(
				'wfr_reply_text' => $text,
				'wfr_posted_timestamp' => $timestamp,
				'wfr_user' => $wgUser->getId(),
				'wfr_thread' => $thread->getId()
			),
			__METHOD__
		);

		if ( !$result ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-general' );
		}

		$dbw->update(
			'wikiforum_threads',
			array(
				'wft_reply_count = wft_reply_count + 1',
				'wft_last_post_timestamp' => $timestamp,
				'wft_last_post_user' => $wgUser->getId(),
				'wft_last_post_user_ip' => $wgRequest->getIP(),
			),
			array( 'wft_thread' => $thread->getId() ),
			__METHOD__
		);

		$dbw->update(
			'wikiforum_forums',
			array( 'wff_reply_count = wff_reply_count + 1' ),
			array( 'wff_forum' => $thread->getForum()->getId() ),
			__METHOD__
		);

		$logEntry = new ManualLogEntry( 'forum', 'add-reply' );
		$logEntry->setPerformer( $wgUser );
		$logEntry->setTarget( SpeciaLPage::getTitleFor( 'WikiForum' ) );
		$shortText = $wgLang->truncate( $text, 50 );
		$logEntry->setComment( $shortText );
		$logEntry->setParameters( array(
				'4::thread-name' => $thread->getName(),
		) );
		$logid = $logEntry->insert();
		if ( $wgWikiForumLogInRC ) {
			$logEntry->publish( $logid );
		}

		return $thread->show();
	}

	/**
	 * Show the editor for this reply
	 *
	 * @return string: HTML, the editor
	 */
	function showEditor() {
		return WFReply::showGeneralEditor(
			array(
				'wfaction' => 'savereply',
				'reply' => $this->getId()
			),
			$this->getText(),
			true
		);
	}

	/**
	 * Show the reply editor
	 *
	 * @param array $params: URL params to be passed to form
	 * @param string $textValue: value to preload the editor with
	 * @return string
	 */
	static function showGeneralEditor( $params, $text_prev = '', $showCancel = false ) {
		return WikiForumGui::showWriteForm( $showCancel, $params, '', '10em', $text_prev, wfMessage( 'wikiforum-save-reply' )->text() );
	}
}