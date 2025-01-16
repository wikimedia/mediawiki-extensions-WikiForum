<?php

use MediaWiki\MediaWikiServices;

class WFReply extends ContextSource {

	private $data;
	public $thread;

	/**
	 * @param stdClass $sql
	 */
	private function __construct( $sql ) {
		$this->data = $sql;
	}

	/**
	 * Return a new WFReply instance for the given reply ID
	 *
	 * @param int $id ID to get the reply for
	 * @return self|false
	 */
	public static function newFromID( $id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$data = $dbr->selectRow(
			'wikiforum_replies',
			'*',
			[ 'wfr_reply_id' => $id ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Return a new WFReply instance for the given reply text content
	 *
	 * @param string $text text to get the reply for
	 * @return self|false
	 */
	public static function newFromText( $text ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$data = $dbr->selectRow(
			'wikiforum_replies',
			'*',
			[ 'wfr_reply_text' => $text ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Returns a WFReply instance for the given SQL row
	 *
	 * @param stdClass $sql row from the DB. Not resultWrapper!
	 * @return self
	 */
	public static function newFromSQL( $sql ) {
		return new self( $sql );
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
	 * @return int the id
	 */
	function getId() {
		return $this->data->wfr_reply_id;
	}

	/**
	 * Get the actor ID of the user who posted the reply
	 *
	 * @return int the ID
	 */
	function getPostedById() {
		return $this->data->wfr_actor;
	}

	/**
	 * Get the user who posted the reply
	 *
	 * @return User
	 */
	function getPostedBy() {
		return WikiForum::getUserFromDB( $this->data->wfr_actor, $this->data->wfr_user_ip );
	}

	/**
	 * Get the user who edited the reply, if it has been edited
	 *
	 * @return User|false
	 */
	function getEditedBy() {
		if ( $this->hasBeenEdited() ) {
			return WikiForum::getUserFromDB( $this->data->wfr_edit_actor, $this->data->wfr_edit_user_ip );
		} else {
			return false;
		}
	}

	/**
	 * Get this reply's parent thread
	 *
	 * @return WFThread the thread
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
	 * @return bool true if it has, false if it has not
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
	 * @return string HTML
	 */
	function delete() {
		$user = $this->getUser();

		if (
			$user->isAnon() ||
			(
				$user->getActorId() != $this->getPostedById() &&
				!$user->isAllowed( 'wikiforum-moderator' )
			)
		) {
			return WikiForum::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
		}

		if ( !$user->matchEditToken( $this->getRequest()->getVal( 'wpToken' ) ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-delete', 'sessionfailure' );
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$result = $dbw->delete(
			'wikiforum_replies',
			[ 'wfr_reply_id' => $this->getId() ],
			__METHOD__
		);

		if ( !$result ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
		}

		return $this->getThread()->show();
	}

	/**
	 * Edit the reply
	 *
	 * @param string $text new reply text
	 * @return string HTML of thread
	 */
	function edit( $text ) {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( strlen( $text ) == 0 ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-reply' );
		}

		if ( $text == $this->getText() ) {
			return true; // nothing to edit
		}

		if (
			$user->isAnon() ||
			(
				(
					$user->getActorId() != $this->getPostedById() &&
					$this->getThread()->isClosed()
				) ||
				!$user->isAllowed( 'wikiforum-moderator' )
			)
		) {
			return WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-rights' );
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-edit', 'sessionfailure' );
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$result = $dbw->update(
			'wikiforum_replies',
			[
				'wfr_reply_text' => $text,
				'wfr_edit_timestamp' => $dbw->timestamp( wfTimestampNow() ),
				'wfr_edit_actor' => $user->getActorId(),
				'wfr_edit_user_ip' => $request->getIP(),
			],
			[ 'wfr_reply_id' => $this->getId() ],
			__METHOD__
		);

		return $this->getThread()->show();
	}

	/**
	 * Show this reply
	 *
	 * @return string HTML the reply
	 */
	function show() {
		$posted = $this->showPostedInfo();

		if ( $this->getEditedTimestamp() > 0 ) {
			$posted .= '<br /><i>' . $this->showEditedInfo() . '</i>';
		}

		$avatar = WikiForum::showAvatar( $this->getPostedBy() );

		return Html::openElement( 'tr' ) . Html::rawElement(
			'td',
			[
				'class' => 'mw-wikiforum-thread-sub',
				'colspan' => '2',
				'id' => 'reply_' . $this->getId(),
			],
			( $avatar . WikiForum::parseIt( $this->getText() ) . WikiForumGui::showBottomLine( $posted, $this->getUser(), $this->showButtons() ) )
		) . Html::closeElement( 'tr' );
	}

	/**
	 * Show the reply for the search results page
	 *
	 * @return string
	 */
	function showForSearch() {
		$posted = $this->showPostedInfo();
		$posted .= '<br />' . $this->msg( 'wikiforum-search-thread', $this->getThread()->showLink( $this->getId() ) )->text();
		$avatar = WikiForum::showAvatar( $this->getPostedBy() );

		return Html::openElement( 'tr' ) . Html::rawElement(
			'td',
			[
				'class' => 'mw-wikiforum-thread-sub',
				'colspan' => '2',
				'id' => 'reply_' . $this->getId(),
			],
			( $avatar . WikiForum::parseIt( $this->getText() ) . WikiForumGui::showBottomLine( $posted, $this->getUser() ) )
		) . Html::closeElement( 'tr' );
	}

	/**
	 * Show the reply buttons: quote, edit and delete.
	 *
	 * @return string HTML
	 */
	function showButtons() {
		$thread = $this->getThread();
		$user = $this->getUser();

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );
		$editButtons = Html::rawElement(
			'a',
			[ 'href' => $specialPage->getFullURL( [ 'thread' => $thread->getId(), 'quotereply' => $this->getId() ] ) . '#writereply' ],
			WikiForum::getIconHTML( 'wikiforum-quote' )
		);

		if (
			(
				( $user->getActorId() == $thread->getPostedById() || $user->getActorId() == $this->getPostedById() )
				&& !$thread->isClosed()
			)
			||
			$user->isAllowed( 'wikiforum-moderator' )
		) {
			$editButtons .= ' ' .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'wfaction' => 'editreply', 'reply' => $this->getId() ] ) . '#writereply' ],
				WikiForum::getIconHTML( 'wikiforum-edit-reply' )
			) .
			' ' .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'wfaction' => 'deletereply', 'reply' => $this->getId() ] ) ],
				WikiForum::getIconHTML( 'wikiforum-delete-reply' )
			);
		}

		return $editButtons;
	}

	/**
	 * Add a reply with the text $text to the thread with ID = $threadId.
	 *
	 * @param WFThread $thread thread to reply to
	 * @param string $text reply text
	 * @return string HTML of thread
	 */
	static function add( WFThread $thread, $text ) {
		global $wgWikiForumAllowAnonymous, $wgWikiForumLogInRC;

		$timestamp = wfTimestampNow();
		$request = $thread->getRequest();
		$user = $thread->getUser();

		if ( !$wgWikiForumAllowAnonymous && !$user->isRegistered() ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
		}

		if ( strlen( $text ) == 0 ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-reply' );
		}

		if ( $thread->isClosed() ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-thread-closed' );
		}

		if ( WikiForum::useCaptcha( $user ) ) {
			$captcha = ConfirmEditHooks::getInstance();
			$captcha->setTrigger( 'wikiforum' );
			if ( !$captcha->passCaptchaFromRequest( $request, $user ) ) {
				$output = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-captcha' );
				$thread->preloadText = $text;
				$output .= $thread->show();
				return $output;
			}
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'sessionfailure' );
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$doublepost = $dbr->selectRow(
			'wikiforum_replies',
			'wfr_reply_id',
			[
				'wfr_reply_text' => $text,
				'wfr_actor' => $user->getActorId(),
				'wfr_thread' => $thread->getId(),
				// @todo FIXME: This would need $dbw->timestamp() but the results of calling that
				// on either $timestamp or the completed calculation seem odd...namely that
				// $dbw->timestamp( $timestamp - ( 24 * 3600 ) ) is NOT the same as $timestamp - ( 24 * 3600 )
				// even on MySQL/MariaDB?! I don't even...
				'wfr_posted_timestamp > ' . ( $timestamp - ( 24 * 3600 ) )
			],
			__METHOD__
		);

		if ( $doublepost ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-double-post' );
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$result = $dbw->insert(
			'wikiforum_replies',
			[
				'wfr_reply_text' => $text,
				'wfr_posted_timestamp' => $timestamp,
				'wfr_actor' => $user->getActorId(),
				'wfr_thread' => $thread->getId()
			],
			__METHOD__
		);

		if ( !$result ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-general' );
		}

		$dbw->update(
			'wikiforum_threads',
			[
				'wft_reply_count = wft_reply_count + 1',
				'wft_last_post_timestamp' => $timestamp,
				'wft_last_post_actor' => $user->getActorId(),
				'wft_last_post_user_ip' => $request->getIP(),
			],
			[ 'wft_thread' => $thread->getId() ],
			__METHOD__
		);

		$dbw->update(
			'wikiforum_forums',
			[ 'wff_reply_count = wff_reply_count + 1' ],
			[ 'wff_forum' => $thread->getForum()->getId() ],
			__METHOD__
		);

		$logEntry = new ManualLogEntry( 'forum', 'add-reply' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( SpeciaLPage::getTitleFor( 'WikiForum' ) );
		$shortText = $thread->getLanguage()->truncateForDatabase( $text, 50 );
		$logEntry->setComment( $shortText );
		$logEntry->setParameters( [
			'4::thread-name' => $thread->getName(),
		] );
		$logid = $logEntry->insert();
		if ( $wgWikiForumLogInRC ) {
			$logEntry->publish( $logid );
		}

		return $thread->show();
	}

	/**
	 * Show the editor for this reply
	 *
	 * @return string HTML, the editor
	 */
	function showEditor() {
		return self::showGeneralEditor(
			[
				'wfaction' => 'savereply',
				'reply' => $this->getId()
			],
			$this->getUser(),
			$this->getText(),
			true
		);
	}

	/**
	 * Show the reply editor
	 *
	 * @param array $params URL params to be passed to form
	 * @param User $user
	 * @param string $text_prev value to preload the editor with
	 * @param bool $showCancel
	 * @return string
	 */
	static function showGeneralEditor( $params, User $user, $text_prev = '', $showCancel = false ) {
		return WikiForumGui::showWriteForm( $showCancel, $params, '', '10em', $text_prev, wfMessage( 'wikiforum-save-reply' )->text(), $user );
	}
}
