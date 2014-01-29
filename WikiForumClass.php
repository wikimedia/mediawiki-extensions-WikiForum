<?php
/**
 * Main class for WikiForum extension, contains all the logic to manage forums,
 * categories, individual topics, etc.
 *
 * @todo FIXME: if this class isn't split into multiple classes soon, it'll be
 * OVER 9000 lines long in no time...
 *
 * @file
 * @ingroup Extensions
 */
class WikiForumClass {
	private $result = true; // boolean; if true, everything went well

	private $errorTitle = ''; // error page title
	private $errorIcon = ''; // error icon name
	private $errorMessage = ''; // error message

	/**
	 * Find a thread's ID number when you know the title.
	 *
	 * @param $titleText String: thread title
	 * @return Integer: thread ID number
	 */
	public static function findThreadIDByTitle( $titleText ) {
		// Titles are stored with spaces in the DB but the query will otherwise
		// use friggin' underscores...
		// @todo FIXME: come to think of it, this *is* awfully hacky...
		// Maybe construct a Title object out of $titleText and use its
		// getDBkey() method here instead?
		$titleText = str_replace( '_', ' ', $titleText );

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow(
			'wikiforum_threads',
			'wft_thread',
			// Database::makeList calls Database::addQuotes so that we don't
			// need to do it here yet this still is safe
			array( 'wft_thread_name' => $titleText ),
			__METHOD__
		);

		if ( $res ) {
			$threadId = $res->wft_thread;
		} else {
			// Something went wrong...
			$threadId = 0;
		}

		return $threadId;
	}

	/**
	 * Deletes the reply with ID = $replyId.
	 *
	 * @param $replyId Integer: ID number of the reply to delete
	 * @return Boolean: was reply deletion successful or not?
	 */
	function deleteReply( $replyId ) {
		global $wgRequest, $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$reply = $dbr->fetchObject( $dbr->select(
			'wikiforum_replies',
			array( 'wfr_reply_id', 'wfr_user' ),
			array( 'wfr_deleted' => 0, 'wfr_reply_id' => intval( $replyId ) ),
			__METHOD__
		) );

		if (
			$reply->wfr_reply_id > 0 && $wgUser->getId() > 0 &&
			(
				$wgUser->getId() == $reply->wfr_user ||
				$wgUser->isAllowed( 'wikiforum-moderator' )
			) &&
			!wfReadOnly()
		)
		{
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->update(
				'wikiforum_replies',
				array(
					'wfr_deleted' => wfTimestampNow(),
					'wfr_deleted_user' => $wgUser->getId(),
					'wfr_deleted_user_text' => $wgUser->getName(),
					'wfr_deleted_user_ip' => $wgRequest->getIP(),
				),
				array(
					'wfr_reply_id' => $reply->wfr_reply_id
				),
				__METHOD__
			);
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-delete' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	/**
	 * Deletes the thread with ID = $threadId.
	 *
	 * @param $threadId Integer: ID number of the thread to delete
	 * @return Boolean: was thread deletion successful or not?
	 */
	function deleteThread( $threadId ) {
		global $wgRequest, $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$thread = $dbr->fetchObject( $dbr->select(
			'wikiforum_threads',
			array( 'wft_thread', 'wft_user', 'wft_forum' ),
			array( 'wft_deleted' => 0, 'wft_thread' => $threadId ),
			__METHOD__
		) );

		if (
			$thread->wft_thread > 0 && $wgUser->getId() > 0 &&
			( $wgUser->getId() == $thread->wft_user || $wgUser->isAllowed( 'wikiforum-moderator' ) ) &&
			!wfReadOnly()
		)
		{
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->update(
				'wikiforum_threads',
				array(
					'wft_deleted' => wfTimestampNow(),
					'wft_deleted_user' => $wgUser->getId(),
					'wft_deleted_user_text' => $wgUser->getName(),
					'wft_deleted_user_ip' => $wgRequest->getIP(),
				),
				array(
					'wft_thread' => $thread->wft_thread
				),
				__METHOD__
			);
			// Update threads/replies counters
			$replyCount = $dbw->selectField(
				'wikiforum_threads',
				'wft_reply_count',
				array( 'wft_thread' => $thread->wft_thread ),
				__METHOD__
			);
			// When the thread we're about to delete is deleted, we also need
			// to update the information about the latest post & its author
			$new = $dbw->select(
				'wikiforum_threads',
				array(
					'wft_last_post_user',
					'wft_last_post_user_text',
					'wft_last_post_user_ip',
					'wft_last_post_timestamp',
				),
				array(
					'wft_forum' => $thread->wft_forum,
					'wft_deleted' => 0 // 0 means not deleted
				),
				__METHOD__,
				array( 'LIMIT' => 1 )
			);
			$row = $dbw->fetchRow( $new );
			// Update the forum table so that the data shown on
			// Special:WikiForum is up to date
			$dbw->update(
				'wikiforum_forums',
				array(
					"wff_reply_count = wff_reply_count - $replyCount",
					'wff_thread_count = wff_thread_count - 1',
					'wff_last_post_user' => $row['wft_last_post_user'],
					'wff_last_post_user_text' => $row['wft_last_post_user_text'],
					'wff_last_post_user_ip' => $row['wft_last_post_user_ip'],
					'wff_last_post_timestamp' => $row['wft_last_post_timestamp']
				),
				array( 'wff_forum' => $thread->wft_forum ),
				__METHOD__
			);
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-delete' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	/**
	 * Deletes the category with ID = $categoryId.
	 *
	 * @param $categoryId Integer: ID number of the category to delete
	 * @return Boolean: was category deletion successful or not?
	 */
	function deleteCategory( $categoryId ) {
		global $wgRequest, $wgUser;

		if (
			$wgUser->isAllowed( 'wikiforum-admin' ) &&
			!wfReadOnly()
		)
		{
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->update(
				'wikiforum_category',
				array(
					'wfc_deleted' => wfTimestampNow(),
					'wfc_deleted_user' => $wgUser->getId(),
					'wfc_deleted_user_text' => $wgUser->getName(),
					'wfc_deleted_user' => $wgRequest->getIP(),
				),
				array( 'wfc_category' => $categoryId ),
				__METHOD__
			);
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-delete' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	/**
	 * Deletes the forum with ID = $forumId.
	 *
	 * @param $forumId Integer: ID number of the forum to delete
	 * @return Boolean: was forum deletion successful or not?
	 */
	function deleteForum( $forumId ) {
		global $wgRequest, $wgUser;

		if (
			$wgUser->isAllowed( 'wikiforum-admin' ) &&
			!wfReadOnly()
		)
		{
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->update(
				'wikiforum_forums',
				array(
					'wff_deleted' => wfTimestampNow(),
					'wff_deleted_user' => $wgUser->getId(),
					'wff_deleted_user_name' => $wgUser->getName(),
					'wff_deleted_user_ip' => $wgRequest->getIP(),
				),
				array( 'wff_forum' => $forumId ),
				__METHOD__
			);
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-delete' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	/**
	 * Reopens the thread with ID = $threadId.
	 *
	 * @param $threadId Integer: ID number of the thread to reopen
	 * @return Boolean: was thread reopening successful or not?
	 */
	function reopenThread( $threadId ) {
		global $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$thread = $dbr->selectRow(
			'wikiforum_threads',
			'wft_thread',
			array(
				'wft_closed > 0',
				'wft_deleted' => 0,
				'wft_thread' => $threadId
			),
			__METHOD__
		);

		if (
			$thread->wft_thread > 0 &&
			$wgUser->isAllowed( 'wikiforum-moderator' ) &&
			!wfReadOnly()
		)
		{
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->update(
				'wikiforum_threads',
				array(
					'wft_closed' => 0,
					'wft_closed_user' => 0
				),
				array( 'wft_thread' => $thread->wft_thread ),
				__METHOD__
			);
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-thread-reopen' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	/**
	 * Closes the thread with ID = $threadId.
	 *
	 * @param $threadId Integer: ID number of the thread to close
	 * @return Boolean: was thread closed successfully or not?
	 */
	function closeThread( $threadId ) {
		global $wgRequest, $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$thread = $dbr->selectRow(
			'wikiforum_threads',
			'wft_thread',
			array(
				'wft_closed' => 0,
				'wft_deleted' => 0,
				'wft_thread' => $threadId
			),
			__METHOD__
		);

		if (
			$thread->wft_thread > 0 &&
			$wgUser->isAllowed( 'wikiforum-moderator' ) &&
			!wfReadOnly()
		)
		{
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->update(
				'wikiforum_threads',
				array(
					'wft_closed' => wfTimestampNow(),
					'wft_closed_user' => $wgUser->getId(),
					'wft_closed_user_text' => $wgUser->getName(),
					'wft_closed_user_ip' => $wgRequest->getIP()
				),
				array( 'wft_thread' => $thread->wft_thread ),
				__METHOD__
			);
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-thread-close' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	/**
	 * Makes the thread with ID = $threadId sticky.
	 *
	 * @param $threadId Integer: ID number of the thread to mark as sticky
	 * @param $value
	 * @return Boolean: was thread deletion successful or not?
	 */
	function makeSticky( $threadId, $value ) {
		global $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$thread = $dbr->fetchObject( $dbr->select(
			'wikiforum_threads',
			'wft_thread',
			array( 'wft_deleted' => 0, 'wft_thread' => $threadId ),
			__METHOD__
		) );

		if (
			$thread->wft_thread > 0 &&
			$wgUser->isAllowed( 'wikiforum-admin' ) &&
			!wfReadOnly()
		)
		{
			if ( $value == false ) {
				$value = 0;
			}
			$dbw = wfGetDB( DB_MASTER );
			$result = $dbw->update(
				'wikiforum_threads',
				array( 'wft_sticky' => $value ),
				array( 'wft_thread' => $thread->wft_thread ),
				__METHOD__
			);
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-sticky' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	function pasteThread( $threadId, $forumId ) {
		global $wgUser;

		$dbr = wfGetDB( DB_SLAVE );

		$thread = $dbr->select(
			'wikiforum_threads',
			'wft_thread',
			array( 'wft_deleted' => 0, 'wft_thread' => $threadId ),
			__METHOD__
		);
		$forum = $dbr->select(
			'wikiforum_forums',
			'wff_forum',
			array( 'wff_deleted' => 0, 'wff_forum' => $forumId ),
			__METHOD__
		);

		if ( $thread->wft_thread > 0 && $forum->wff_forum > 0 && $wgUser->isAllowed( 'wikiforum-moderator' ) ) {
			if ( $thread->wft_forum != $forum->wff_forum ) {
				$dbw = wfGetDB( DB_MASTER );
				$result = $dbw->update(
					'wikiforum_threads',
					array( 'wft_forum' => $forum->wff_forum ),
					array( 'wft_thread' => $thread->wft_thread ),
					__METHOD__
				);
			} else {
				$result = true;
			}
		} else {
			$result = false;
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-move-thread' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
		}
		return $result;
	}

	function editThread( $threadId, $title, $text ) {
		global $wgRequest, $wgUser;

		if (
			$text && $title && strlen( $text ) > 1 &&
			strlen( $title ) > 1
		)
		{
			$dbr = wfGetDB( DB_SLAVE );

			$thread = $dbr->fetchObject( $dbr->select(
				'wikiforum_threads',
				array( 'wft_thread', 'wft_thread_name', 'wft_text', 'wft_user' ),
				array( 'wft_deleted' => 0, 'wft_thread' => $threadId ),
				__METHOD__
			) );

			if ( $thread->wft_thread > 0 ) {
				if (
					$thread->wft_thread_name != $title ||
					$thread->wft_text != $text
				)
				{
					if (
						$wgUser->getId() > 0 &&
						(
							$wgUser->getId() == $thread->wft_user ||
							$wgUser->isAllowed( 'wikiforum-moderator' )
						) &&
						!wfReadOnly()
					)
					{
						$dbw = wfGetDB( DB_MASTER );
						$result = $dbw->update(
							'wikiforum_threads',
							array(
								'wft_thread_name' => $title,
								'wft_text' => $text,
								'wft_edit_timestamp' => wfTimestampNow(),
								'wft_edit_user' => $wgUser->getId(),
								'wft_edit_user_text' => $wgUser->getName(),
								'wft_edit_user_ip' => $wgRequest->getIP(),
							),
							array( 'wft_thread' => $thread->wft_thread ),
							__METHOD__
						);
					} else {
						$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
						$result = false;
					}
				} else {
					$result = true; // no changes
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-error-not-found' )->text();
				$result = false;
			}

			if ( $result == false ) {
				$this->errorTitle = wfMessage( 'wikiforum-error-edit' )->text();
				if ( $this->errorMessage == '' ) {
					$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
				}
			}
		} else {
			if (
				!$text && !$title ||
				strlen( $text ) == 0 && strlen( $title ) == 0
			)
			{
				$result = false;
			} else {
				$this->result = false;
				$result = false;
			}
		}
		return $result;
	}

	function addThread( $forumId, $title, $text ) {
		global $wgRequest, $wgUser, $wgWikiForumAllowAnonymous, $wgWikiForumLogInRC, $wgLang;

		if ( $wgWikiForumAllowAnonymous || $wgUser->isLoggedIn() ) {
			if (
				$forumId > 0 && strlen( $text ) > 1 &&
				strlen( $title ) > 1 &&
				$title != wfMessage( 'wikiforum-thread-title' )->inContentLanguage()->plain() &&
				// We can't add a thread that has the same title as a pre-existing
				// thread, obviously
				!$this->doesThreadExist( $title )
			)
			{
				$dbr = wfGetDB( DB_SLAVE );

				$overview = $dbr->fetchObject( $dbr->select(
					array( 'wikiforum_forums', 'wikiforum_category' ),
					array( 'wff_forum', 'wff_announcement' ),
					array(
						'wfc_deleted' => 0,
						'wff_deleted' => 0,
						'wfc_category = wff_category',
						'wff_forum' => $forumId
					),
					__METHOD__
				) );

				if ( $overview->wff_forum > 0 && !wfReadOnly() ) {
					$dbw = wfGetDB( DB_MASTER );
					$timestamp = wfTimestampNow();
					if ( $overview->wff_announcement == false || $wgUser->isAllowed( 'wikiforum-moderator' ) ) {
						$doublepost = $dbr->selectRow(
							'wikiforum_threads',
							'wft_thread AS id',
							array(
								'wft_deleted' => 0,
								'wft_thread_name' => $title,
								'wft_text' => $text,
								'wft_user' => intval( $wgUser->getId() ),
								'wft_forum' => $forumId,
								'wft_posted_timestamp > ' . ( $timestamp - ( 24 * 3600 ) )
							),
							__METHOD__
						);

						if ( $doublepost === false ) {
							$result = $dbw->insert(
								'wikiforum_threads',
								array(
									'wft_thread_name' => $title,
									'wft_text' => $text,
									'wft_posted_timestamp' => $timestamp,
									'wft_user' => $wgUser->getId(),
									'wft_user_text' => $wgUser->getName(),
									'wft_user_ip' => $wgRequest->getIP(),
									'wft_forum' => $forumId,
									'wft_last_post_timestamp' => $timestamp
								),
								__METHOD__
							);
							if ( $result == true ) {
								$dbw->update(
									'wikiforum_forums',
									array(
										'wff_thread_count = wff_thread_count + 1',
										'wff_last_post_timestamp' => $timestamp,
										'wff_last_post_user' => $wgUser->getId(),
										'wff_last_post_user_text' => $wgUser->getName(),
										'wff_last_post_user_ip' => $wgRequest->getIP()
									),
									array( 'wff_forum' => $forumId ),
									__METHOD__
								);
								$logEntry = new ManualLogEntry( 'forum', 'add-thread' );
								$logEntry->setPerformer( $wgUser );
								$logEntry->setTarget( SpeciaLPage::getTitleFor( 'wikiforum' ) );
								$shortText = $wgLang->truncate( $text, 50 );
								$logEntry->setComment( $shortText );
								$logEntry->setParameters( array(
										'4::thread-name' => $title
								) );
								$logid = $logEntry->insert();
								if ( $wgWikiForumLogInRC ) {
									$logEntry->publish( $logid );
								}
								$this->result = true;
							} else {
								$this->result = false;
							}
						} else {
							$this->errorMessage = wfMessage( 'wikiforum-error-double-post' )->text();
							$this->result = false;
						}
					} else {
						$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
						$this->result = false;
					}
				} else {
					$this->result = false;
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-error-no-text-or-title' )->text();
				$this->result = false;
			}
		} else {
			$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
			$this->result = false;
		}

		if ( $this->result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-add' )->text();
			if ( $this->errorMessage == '' ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
			}
		}
		return $this->result;
	}

	/**
	 * Edit the reply with ID = $replyId.
	 *
	 * @param $replyId Integer: internal ID number of the reply
	 * @param $text String: new reply text
	 * @return Boolean: did everything go well (true) or not (false)?
	 */
	function editReply( $replyId, $text ) {
		global $wgUser, $wgRequest;

		if ( $text && strlen( $text ) > 1 ) {
			$dbr = wfGetDB( DB_SLAVE );

			$reply = $dbr->fetchObject( $dbr->select(
				'wikiforum_replies',
				array( 'wfr_thread', 'wfr_reply_id', 'wfr_reply_text', 'wfr_user' ),
				array( 'wfr_reply_id' => $replyId ),
				__METHOD__
			) );
			$thread = $dbr->fetchObject( $dbr->select(
				'wikiforum_threads',
				array( 'wft_thread', 'wft_closed' ),
				array( 'wft_deleted' => 0, 'wft_thread' => $reply->wfr_thread ),
				__METHOD__
			) );

			if ( $reply->wfr_reply_id > 0 && $thread->wft_thread > 0 ) {
				if ( $reply->wfr_reply_text != $text ) {
					if (
						$wgUser->getId() > 0 &&
						( ( $wgUser->getId() == $reply->wfr_user &&
							$thread->wft_closed == 0 ) ||
							$wgUser->isAllowed( 'wikiforum-moderator' )
						) && !wfReadOnly()
					)
					{
						$dbw = wfGetDB( DB_MASTER );
						$result = $dbw->update(
							'wikiforum_replies',
							array(
								'wfr_reply_text' => $text,
								'wfr_edit_timestamp' => wfTimestampNow(),
								'wfr_edit_user' => $wgUser->getId(),
								'wfr_edit_user_text' => $wgUser->getName(),
								'wfr_edit_user_ip' => $wgRequest->getIP(),
							),
							array( 'wfr_reply_id' => $reply->wfr_reply_id ),
							__METHOD__
						);
					} else {
						$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
						$result = false;
					}
				} else {
					$result = true;
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-error-not-found' )->text();
				$result = false;
			}
		} else {
			$form = $wgRequest->getBool( 'form' );
			if ( isset( $form ) && $form == true ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-no-reply' )->text();
				$result = false;
			} else {
				$result = true;
			}
		}

		if ( $result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-edit' )->text();
			if ( $this->errorMessage == '' ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
			}
		}
		return $result;
	}

	/**
	 * Add a reply with the text $text to the thread with ID = $threadId.
	 *
	 * @param $threadId Integer: internal ID number of the thread to reply to
	 * @param $text String: reply text
	 */
	function addReply( $threadId, $text ) {
		global $wgRequest, $wgUser, $wgWikiForumAllowAnonymous, $wgWikiForumLogInRC, $wgLang;

		$timestamp = wfTimestampNow();

		if ( $wgWikiForumAllowAnonymous || $wgUser->isLoggedIn() ) {
			if ( $threadId > 0 ) {
				if ( strlen( $text ) > 1 ) {
					$dbr = wfGetDB( DB_SLAVE );

					$thread = $dbr->selectRow(
						'wikiforum_threads',
						'wft_thread',
						array(
							'wft_deleted' => 0,
							'wft_closed' => 0,
							'wft_thread' => $threadId
						),
						__METHOD__
					);

					if ( $thread->wft_thread > 0 && !wfReadOnly() ) {
						$dbw = wfGetDB( DB_MASTER );
						$doublepost = $dbr->selectRow(
							'wikiforum_replies',
							'wfr_reply_id AS id',
							array(
								'wfr_deleted' => 0,
								'wfr_reply_text' => $text,
								'wfr_user' => $wgUser->getId(),
								'wfr_user_text' => $wgUser->getName(),
								'wfr_thread' => $thread->wft_thread,
								'wfr_posted_timestamp > ' . ( $timestamp - ( 24 * 3600 ) )
							),
							__METHOD__
						);

						if ( $doublepost === false ) {
							$result = $dbw->insert(
								'wikiforum_replies',
								array(
									'wfr_reply_text' => $text,
									'wfr_posted_timestamp' => $timestamp,
									'wfr_user' => $wgUser->getId(),
									'wfr_user_text' => $wgUser->getName(),
									'wfr_thread' => $thread->wft_thread
								),
								__METHOD__
							);

							if ( $result == true ) {
								$dbw->update(
									'wikiforum_threads',
									array(
										'wft_reply_count = wft_reply_count + 1',
										'wft_last_post_timestamp' => $timestamp,
										'wft_last_post_user' => $wgUser->getId(),
										'wft_last_post_user_text' => $wgUser->getName(),
										'wft_last_post_user_ip' => $wgRequest->getIP(),
									),
									array( 'wft_thread' => $threadId ),
									__METHOD__
								);

								$pkForum = $dbr->selectRow(
									'wikiforum_threads',
									array( 'wft_forum, wft_thread_name' ),
									array( 'wft_thread' => $threadId ),
									__METHOD__
								);
								$dbw->update(
									'wikiforum_forums',
									array( 'wff_reply_count = wff_reply_count + 1' ),
									array( 'wff_forum' => $pkForum->wft_forum ),
									__METHOD__
								);

								$logEntry = new ManualLogEntry( 'forum', 'add-reply' );
								$logEntry->setPerformer( $wgUser );
								$logEntry->setTarget( SpeciaLPage::getTitleFor( 'WikiForum' ) );
								$shortText = $wgLang->truncate( $text, 50 );
								$logEntry->setComment( $shortText );
								$forum = $pkForum->wft_thread_name;
								$logEntry->setParameters( array(
										'4::thread-name' => $forum,
								) );
								$logid = $logEntry->insert();
								if ( $wgWikiForumLogInRC ) {
									$logEntry->publish( $logid );
								}

								$this->result = true;
							} else {
								$this->result = false;
							}
						} else {
							$this->errorMessage = wfMessage( 'wikiforum-error-double-post' )->text();
							$this->result = false;
						}
					} else {
						$this->errorMessage = wfMessage( 'wikiforum-error-thread-closed' )->text();
						$this->result = false;
					}
				} else {
					$this->errorMessage = wfMessage( 'wikiforum-error-no-reply' )->text();
					$this->result = false;
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-error-not-found' )->text();
				$this->result = false;
			}
		} else {
			$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
			$this->result = false;
		}

		if ( $this->result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-add' )->text();
			if ( $this->errorMessage == '' ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
			}
		}
		return $this->result;
	}

	function addCategory( $categoryName ) {
		global $wgRequest, $wgUser, $wgWikiForumLogInRC, $wgLang;

		if (
			$wgUser->isAllowed( 'wikiforum-admin' ) &&
			!wfReadOnly()
		)
		{
			if ( strlen( $categoryName ) > 0 ) {
				$dbr = wfGetDB( DB_SLAVE );
				$sortkey = $dbr->selectRow(
					'wikiforum_category',
					'MAX(wfc_sortkey) AS the_key',
					array( 'wfc_deleted' => 0 ),
					__METHOD__
				);

				$dbw = wfGetDB( DB_MASTER );
				$this->result = $dbw->insert(
					'wikiforum_category',
					array(
						'wfc_category_name' => $categoryName,
						'wfc_sortkey' => ( $sortkey->the_key + 1 ),
						'wfc_added_timestamp' => wfTimestampNow(),
						'wfc_added_user' => $wgUser->getId(),
						'wfc_added_user_text' => $wgUser->getName(),
						'wfc_added_user_ip' => $wgRequest->getIP(),
					),
					__METHOD__
				);
				$categoryID = $dbr->selectField(
					'wikiforum_category',
					'wfc_category',
					array( 'wfc_category_name' => $categoryName ),
					__METHOD__
				);
				$categoryURL = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( array( 'category' => $categoryID ) );
				$logEntry = new ManualLogEntry( 'forum', 'add-category' );
				$logEntry->setPerformer( $wgUser );
				$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiForum' ) );
				$logEntry->setParameters( array(
						'4::category-url' => $categoryURL,
						'5::category-name' => $categoryName
				) );
				$logid = $logEntry->insert();
				if ( $wgWikiForumLogInRC ) {
					$logEntry->publish( $logid );
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-no-text-or-title' )->text();
				$this->result = false;
			}
		} else {
			$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
			$this->result = false;
		}

		if ( $this->result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-add' )->text();
			if ( $this->errorMessage == '' ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
			}
		}
		return $this->result;
	}

	function editCategory( $id, $categoryName ) {
		global $wgRequest, $wgUser;

		if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
			if ( strlen( $categoryName ) > 0 ) {
				$dbr = wfGetDB( DB_SLAVE );

				$category = $dbr->fetchObject( $dbr->select(
					'wikiforum_category',
					array( 'wfc_category', 'wfc_category_name' ),
					array( 'wfc_deleted' => 0, 'wfc_category' => $id ),
					__METHOD__
				) );

				if (
					$category->wfc_category > 0 &&
					$category->wfc_category_name != $categoryName &&
					!wfReadOnly()
				)
				{
					$dbw = wfGetDB( DB_MASTER );
					$this->result = $dbw->update(
						'wikiforum_category',
						array(
							'wfc_category_name' => $categoryName,
							'wfc_edited' => wfTimestampNow(),
							'wfc_edited_user' => $wgUser->getId(),
							'wfc_edited_user_text' => $wgUser->getName(),
							'wfc_edited_user_ip' => $wgRequest->getIP()
						),
						array( 'wfc_category' => $category->wfc_category ),
						__METHOD__
					);
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-no-text-or-title' )->text();
				$this->result = false;
			}
		} else {
			$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
			$this->result = false;
		}

		if ( $this->result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-edit' )->text();
			if ( $this->errorMessage == '' ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
			}
		}
		return $this->result;
	}

	function addForum( $categoryId, $forumName, $description, $announcement ) {
		global $wgRequest, $wgUser, $wgWikiForumLogInRC, $wgLang;

		if ( strlen( $forumName ) < 0 ) {
			$this->errorMessage = wfMessage( 'wikiforum-no-text-or-title' )->text();
			$this->result = false;

			return $this->result;
		}

		if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
			$dbr = wfGetDB( DB_SLAVE );

			$sortKey = $dbr->selectRow(
				'wikiforum_forums',
				'MAX(wff_sortkey) AS the_key',
				array(
					'wff_deleted' => 0,
					'wff_category' => $categoryId
				),
				__METHOD__
			);

			$category = $dbr->selectRow(
				'wikiforum_category',
				'wfc_category',
				array(
					'wfc_deleted' => 0,
					'wfc_category' => $categoryId
				),
				__METHOD__
			);

			if ( $category->wfc_category > 0 && !wfReadOnly() ) {
				$dbw = wfGetDB( DB_MASTER );
				$this->result = $dbw->insert(
					'wikiforum_forums',
					array(
						'wff_forum_name' => $forumName,
						'wff_description' => $description,
						'wff_category' => $category->wfc_category,
						'wff_sortkey' => ( $sortKey->the_key + 1 ),
						'wff_added_timestamp' => wfTimestampNow(),
						'wff_added_user' => $wgUser->getId(),
						'wff_added_user_text' => $wgUser->getName(),
						'wff_added_user_ip' => $wgRequest->getIP(),
						'wff_announcement' => $announcement
					),
					__METHOD__
				);
				$forumID = $dbr->selectField(
						'wikiforum_forums',
						'wff_forum',
						array( 'wff_forum_name' => $forumName ),
						__METHOD__
				);
				$forumURL = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( array( 'forum' => $forumID ) );

				$logEntry = new ManualLogEntry( 'forum', 'add-forum' );
				$logEntry->setPerformer( $wgUser );
				$logEntry->setTarget( SpeciaLPage::getTitleFor( 'WikiForum' ) );
				$shortText = $wgLang->truncate( $description, 50 );
				$logEntry->setComment( $shortText );
				$logEntry->setParameters( array(
					'4::forum-url' => $forumURL,
					'5::forum-name' => $forumName
				) );
				$logid = $logEntry->insert();
				if ( $wgWikiForumLogInRC ) {
					$logEntry->publish( $logid );
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-error-not-found' )->text();
				$this->result = false;
			}
		} else {
			$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
			$this->result = false;
		}

		if ( $this->result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-add' )->text();
			if ( $this->errorMessage == '' ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
			}
		}

		return $this->result;
	}

	/**
	 * Edit a forum.
	 *
	 * @param $id Integer: internal forum ID number
	 * @param $forumName String: forum name as supplied by the user
	 * @param $description String: forum description as supplied by the user
	 * @param $announcement
	 * @return Boolean: did everything go well (true) or not (false)?
	 */
	function editForum( $id, $forumName, $description, $announcement ) {
		global $wgRequest, $wgUser;

		if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
			if ( strlen( $forumName ) > 0 ) {
				$dbr = wfGetDB( DB_SLAVE );
				$forum = $dbr->fetchObject( $dbr->select(
					'wikiforum_forums',
					array(
						'wff_forum', 'wff_forum_name', 'wff_description',
						'wff_announcement'
					),
					array( 'wff_deleted' => 0, 'wff_forum' => $id ),
					__METHOD__
				) );

				if (
					$forum->wff_forum > 0 &&
					(
						$forum->wff_forum_name != $forumName ||
						$forum->wff_description != $description ||
						$forum->wff_announcement != $announcement
					) && !wfReadOnly()
				)
				{
					$dbw = wfGetDB( DB_MASTER );
					$this->result = $dbw->update(
						'wikiforum_forums',
						array(
							'wff_forum_name' => $forumName,
							'wff_description' => $description,
							'wff_edited_timestamp' => wfTimestampNow(),
							'wff_edited_user' => $wgUser->getId(),
							'wff_edited_user_text' => $wgUser->getName(),
							'wff_edited_user_ip' => $wgRequest->getIP(),
							'wff_announcement' => $announcement
						),
						array( 'wff_forum' => $forum->wff_forum ),
						__METHOD__
					);
				}
			} else {
				$this->errorMessage = wfMessage( 'wikiforum-no-text-or-title' )->text();
				$this->result = false;
			}
		} else {
			$this->errorMessage = wfMessage( 'wikiforum-error-no-rights' )->text();
			$this->result = false;
		}

		if ( $this->result == false ) {
			$this->errorTitle = wfMessage( 'wikiforum-error-add' )->text();
			if ( $this->errorMessage == '' ) {
				$this->errorMessage = wfMessage( 'wikiforum-error-general' )->text();
			}
		}
		return $this->result;
	}

	function sortKeys( $id, $type, $direction_up ) {
		global $wgUser;

		if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
			$dbr = wfGetDB( DB_SLAVE );

			if ( $type == 'category' ) {
				// in the past, both tables had a SortKey column
				// nowadays the columns are prefixed with an abbreviation
				// of the table name, which is the standard MW convention
				// it also means that we need to figure out wtf the
				// abbreviation is later on in the code...this will make it
				// possible
				$columnPrefix = 'wfc_';

				$fieldname = 'wfc_category';
				$tablename = 'wikiforum_category';
				$sqlData = $dbr->select(
					$tablename,
					array( $fieldname, 'wfc_sortkey' ),
					array( 'wfc_deleted' => 0 ),
					__METHOD__,
					array( 'ORDER BY' => 'wfc_sortkey ASC' )
				);
			} else {
				$columnPrefix = 'wff_';

				$fieldname = 'wff_forum';
				$tablename = 'wikiforum_forums';
				$forum = $dbr->selectRow(
					$tablename,
					'wff_category',
					array( 'wff_deleted' => 0, 'wff_forum' => $id ),
					__METHOD__
				);
				$sqlData = $dbr->select(
					$tablename,
					array( $fieldname, 'wff_sortkey' ),
					array(
						'wff_deleted' => 0,
						'wff_category' => $forum->wff_category
					),
					__METHOD__,
					array( 'ORDER BY' => 'wff_sortkey ASC' )
				);
			}

			$i = 0;
			$new_array = array();
			$name = $columnPrefix . 'sortkey';
			foreach ( $sqlData as $entry ) {
				$entry->$name = $i;
				array_push( $new_array, $entry );
				$i++;
			}
			for ( $i = 0; $i < sizeof( $new_array ); $i++ ) {
				if ( $new_array[$i]->$fieldname == $id ) {
					if ( $direction_up == true && $i > 0 ) {
						$new_array[$i]->$name--;
						$new_array[$i - 1]->$name++;
					} elseif ( $direction_up == false && $i + 1 < sizeof( $new_array ) ) {
						$new_array[$i]->$name++;
						$new_array[$i + 1]->$name--;
					}
					$i = sizeof( $new_array );
				}
			}
			$dbw = wfGetDB( DB_MASTER );
			if ( !wfReadOnly() ) {
				foreach ( $new_array as $entry ) {
					$result = $dbw->update(
						$tablename,
						array( $columnPrefix . 'sortkey' => $entry->$name ),
						array( $fieldname => $entry->$fieldname ),
						__METHOD__
					);
				}
			}
		}
	}

	/**
	 * Show an overview of all available categories and their forums.
	 * Used in the special page class.
	 *
	 * @return HTML
	 */
	function showOverview() {
		global $wgOut, $wgUser, $wgLang, $wgExtensionAssetsPath;

		$output = $this->showFailure();

		$dbr = wfGetDB( DB_SLAVE );
		$sqlCategories = $dbr->select(
			'wikiforum_category',
			'*',
			array( 'wfc_deleted' => 0 ),
			__METHOD__,
			array( 'ORDER BY' => 'wfc_sortkey ASC, wfc_category ASC' )
		);

		// If there is nothing (i.e. this is a brand new installation),
		// display a message instead of an empty page to the users trying
		// to access Special:WikiForum
		// Otherwise give 'em a search box to search the forum because
		// Special:Search doesn't work for WikiForum
		if ( !$dbr->numRows( $sqlCategories ) ) {
			$output .= wfMessage( 'wikiforum-forum-is-empty' )->parse();
		} else {
			$output .= WikiForumGui::getSearchbox();
		}

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		foreach ( $sqlCategories as $cat ) {
			$sqlForums = $dbr->select(
				array( 'wikiforum_forums', 'user' ),
				array( '*', 'user_name' ),
				array( 'wff_deleted' => 0, ' wff_category' => $cat->wfc_category ),
				__METHOD__,
				array( 'ORDER BY' => 'wff_sortkey ASC, wff_forum ASC' ),
				array( 'user' => array( 'LEFT JOIN', 'user_id = wff_last_post_user' ) )
			);

			$menuLink = '';
			$categoryLink = '';

			if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder_add.png" title="' . wfMessage( 'wikiforum-add-forum' )->text() . '" /> ';
				$menuLink = $icon . '<a href="' . $specialPage->escapeFullURL( array( 'addforum' => $cat->wfc_category ) ) . '">' .
					wfMessage( 'wikiforum-add-forum' ) . '</a>';

				$categoryLink = $this->showAdminIcons(
					'category', $cat->wfc_category, true, true
				);
				$output .= WikiForumGui::getHeaderRow( 0, '', 0, '', $menuLink );
			}
			$output .= WikiForumGui::getMainHeader(
				$cat->wfc_category_name,
				wfMessage( 'wikiforum-threads' )->text(),
				wfMessage( 'wikiforum-replies' )->text(),
				wfMessage( 'wikiforum-latest-thread' )->text(),
				$categoryLink
			);

			foreach ( $sqlForums as $forum ) {
				$forum_link = $this->showAdminIcons(
					'forum', $forum->wff_forum, true, true
				);
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder.png" title="' .
					wfMessage( 'wikiforum-forum-name', $forum->wff_forum_name )->text() . '" /> ';

				$last_post = '';
				if ( $forum->wff_last_post_timestamp > 0 ) {
					$last_post = wfMessage(
						'wikiforum-by',
						$wgLang->timeanddate( $forum->wff_last_post_timestamp ),
						WikiForumClass::getUserLink(
							$forum->wff_last_post_user,
							$forum->wff_last_post_user_text,
							$forum->wff_last_post_user_ip
						),
						$forum->user_name,
						$wgLang->date( $forum->wff_last_post_timestamp ),
						$wgLang->time( $forum->wff_last_post_timestamp )
					)->text();
				}

				$output .= WikiForumGui::getMainBody(
					'<p class="mw-wikiforum-issue">' . $icon .
						'<a href="' . $specialPage->escapeFullURL( array( 'forum' => $forum->wff_forum ) ) . '">' .
							$forum->wff_forum_name . '</a></p>' .
						'<p class="mw-wikiforum-descr">' . $forum->wff_description . '</p>',
					$forum->wff_thread_count,
					$forum->wff_reply_count,
					$last_post,
					$forum_link,
					false
				);
			}
			$output .= WikiForumGui::getMainFooter();
		}

		// Forum admins are allowed to add new categories
		if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/database_add.png" title="' . wfMessage( 'wikiforum-add-category' )->text() . '" /> ';
			$menuLink = $icon . '<a href="' . $specialPage->escapeFullURL( array( 'addcategory' => true ) ) . '">' .
				wfMessage( 'wikiforum-add-category' )->text() . '</a>';
			$output .= WikiForumGui::getHeaderRow( 0, '', 0, '', $menuLink );
		}

		return $output;
	}

	function showCategory( $categoryId ) {
		global $wgOut, $wgLang, $wgUser, $wgExtensionAssetsPath;

		$output = $this->showFailure();
		$dbr = wfGetDB( DB_SLAVE );

		$sqlData = $dbr->select(
			'wikiforum_category',
			array( 'wfc_category', 'wfc_category_name' ),
			array( 'wfc_deleted' => 0, 'wfc_category' => $categoryId ),
			__METHOD__
		);
		$data_overview = $dbr->fetchObject( $sqlData );

		if ( $data_overview ) {
			$sqlForums = $dbr->select(
				array( 'wikiforum_forums', 'user' ),
				array( '*', 'user_name' ),
				array(
					'wff_deleted' => 0,
					'wff_category' => $data_overview->wfc_category
				),
				__METHOD__,
				array( 'ORDER BY' => 'wff_sortkey ASC, wff_forum ASC' ),
				array( 'user' => array( 'LEFT JOIN', 'user_id = wff_last_post_user' ) )
			);

			$menuLink = '';
			$categoryLink = '';
			$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

			// Forum admins are allowed to add new forums
			if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder_add.png" title="' . wfMessage( 'wikiforum-add-forum' )->text() . '" /> ';
				$menuLink = $icon . '<a href="' . $specialPage->escapeFullURL( array( 'addforum' => $data_overview->wfc_category ) ) . '">' .
					wfMessage( 'wikiforum-add-forum' )->text() . '</a>';
				$categoryLink = $this->showAdminIcons(
					'category', $data_overview->wfc_category, false, false
				);
			}

			$output .= WikiForumGui::getSearchbox();
			$output .= WikiForumGui::getHeaderRow(
				$data_overview->wfc_category,
				$data_overview->wfc_category_name,
				0,
				'',
				$menuLink
			);
			$output .= WikiForumGui::getMainHeader(
				$data_overview->wfc_category_name,
				wfMessage( 'wikiforum-threads' )->text(),
				wfMessage( 'wikiforum-replies' )->text(),
				wfMessage( 'wikiforum-latest-thread' )->text(),
				$categoryLink
			);

			foreach ( $sqlForums as $forum ) {
				$forum_link = $this->showAdminIcons(
					'forum', $forum->wff_forum, true, true
				);

				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder.png" title="' .
					wfMessage( 'wikiforum-forum-name', $forum->wff_forum_name )->text() . '" /> ';

				$last_post = '';
				// If there are replies, indicate that somehow...
				// This message will be shown only when there is one reply or
				// more
				if ( $forum->wff_last_post_timestamp > 0 ) {
					$last_post = wfMessage(
						'wikiforum-by',
						$wgLang->timeanddate( $forum->wff_last_post_timestamp ),
						WikiForumClass::getUserLink(
							$forum->wff_last_post_user,
							$forum->wff_last_post_user_text,
							$forum->wff_last_post_user_ip
						),
						$forum->user_name,
						$wgLang->date( $forum->wff_last_post_timestamp ),
						$wgLang->time( $forum->wff_last_post_timestamp )
					)->text();
				}

				$output .= WikiForumGui::getMainBody(
					'<p class="mw-wikiforum-issue">' . $icon .
						'<a href="' . $specialPage->escapeFullURL( array( 'forum' => $forum->wff_forum ) ) . '">' .
							$forum->wff_forum_name . '</a></p>' .
						'<p class="mw-wikiforum-descr">' . $forum->wff_description . '</p>',
					$forum->wff_thread_count,
					$forum->wff_reply_count,
					$last_post,
					$forum_link,
					false
				);
			}
			$output .= WikiForumGui::getMainFooter();
		} else {
			$this->errorTitle = wfMessage( 'wikiforum-cat-not-found' )->text();
			$this->errorMessage = wfMessage(
				'wikiforum-cat-not-found-text',
				'<a href="' . $specialPage->escapeFullURL() . '">' .
					wfMessage( 'wikiforum-overview' )->text() . '</a>' // @todo FIXME
			);
		}
		// Jack: unnecessary duplication of the "Overview" link on the bottom
		// of the page, thus removed
		// $output .= WikiForumGui::getHeaderRow( 0, '', 0, '', '' );
		$output .= $this->showFailure();
		return $output;
	}

	function showForum( $forumId ) {
		global $wgOut, $wgLang, $wgUser, $wgRequest, $wgExtensionAssetsPath;

		$output = $this->showFailure();
		$dbr = wfGetDB( DB_SLAVE );

		$f_movethread = $wgRequest->getInt( 'movethread' );

		// sorting
		if ( $wgRequest->getVal( 'sd' ) == 'up' ) {
			$sort_direction = 'ASC';
		} else {
			$sort_direction = 'DESC';
		}

		if ( $wgRequest->getVal( 'st' ) == 'answers' ) {
			$sort_type = 'wft_reply_count';
		} elseif ( $wgRequest->getVal( 'st' ) == 'calls' ) {
			$sort_type = 'wft_view_count';
		} elseif ( $wgRequest->getVal( 'st' ) == 'thread' ) {
			$sort_type = 'wft_thread_name';
		} else {
			$sort_type = 'wft_last_post_timestamp';
		}
		// end sorting

		$maxThreadsPerPage = intval( wfMessage( 'wikiforum-max-threads-per-page' )->inContentLanguage()->plain() );

		// limiting
		if ( $maxThreadsPerPage && $wgRequest->getVal( 'lc' ) > 0 ) {
			$limit_count = $wgRequest->getVal( 'lc' );
		} elseif ( $maxThreadsPerPage > 0 ) {
			$limit_count = $maxThreadsPerPage;
		}

		if ( is_numeric( $wgRequest->getVal( 'lp' ) ) ) {
			$limit_page = $wgRequest->getVal( 'lp' ) - 1;
		} else {
			$limit_page = 0;
		}
		// end limiting

		$sqlData = $dbr->select(
			array( 'wikiforum_forums', 'wikiforum_category' ),
			array(
				'wff_forum', 'wff_forum_name', 'wfc_category',
				'wfc_category_name', 'wff_announcement'
			),
			array(
				'wff_deleted' => 0,
				'wfc_deleted' => 0,
				'wff_category = wfc_category',
				'wff_forum' => $forumId
			),
			__METHOD__
		);
		$data_overview = $dbr->fetchObject( $sqlData );

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		if ( $data_overview ) {
			$options['ORDER BY'] = 'wft_sticky DESC, ' . $sort_type . ' ' . $sort_direction;
			if ( $limit_count > 0 ) {
				$options['LIMIT'] = $limit_count;
				$options['OFFSET'] = $limit_page * $limit_count;
			}
			$forumIdNumber = $data_overview->wff_forum;
			$sqlThreads = $dbr->select(
				array( 'wikiforum_threads', 'user' ),
				array( '*', 'user_name' ),
				array( 'wft_deleted' => 0, 'wft_forum' => $forumIdNumber ),
				__METHOD__,
				$options,
				array( 'user' => array( 'LEFT JOIN', 'user_id = wft_user' ) )
			);

			$button['up'] = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/bullet_arrow_up.png" alt="" />';
			$button['down'] = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/bullet_arrow_down.png" alt="" />';

			// Non-moderators cannot post in an announcement-only forum
			if ( $data_overview->wff_announcement == true && !$wgUser->isAllowed( 'wikiforum-moderator' ) ) {
				$write_thread = '';
			} else {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/note_add.png" title="' . wfMessage( 'wikiforum-write-thread' )->text() . '" /> ';
				$write_thread = $icon . '<a href="' . $specialPage->escapeFullURL( array( 'writethread' => $forumIdNumber ) ) . '">' .
					wfMessage( 'wikiforum-write-thread' ) . '</a>';
			}

			$output .= WikiForumGui::getSearchbox();
			$output .= WikiForumGui::getHeaderRow(
				$data_overview->wfc_category,
				$data_overview->wfc_category_name,
				$forumIdNumber,
				$data_overview->wff_forum_name,
				$write_thread
			);

			// @todo FIXME: the logic here seems wonky from the end-users point
			// of view and this code is horrible...
			// The <br />s are here to fix ShoutWiki bug #176
			// @see http://bugzilla.shoutwiki.com/show_bug.cgi?id=174
			$output .= WikiForumGui::getMainHeader(
				$data_overview->wff_forum_name . ' <a href="' .
					$specialPage->escapeFullURL( array( 'st' => 'thread', 'sd' => 'up', 'forum' => $forumIdNumber ) ) . '">' . $button['up'] .
					'</a><a href="' .
					$specialPage->escapeFullURL( array( 'st' => 'thread', 'sd' => 'down', 'forum' => $forumIdNumber ) ) . '">' . $button['down'] . '</a>',
				wfMessage( 'wikiforum-replies' )->text() . ' <br /><a href="' .
				$specialPage->escapeFullURL( array( 'st' => 'answers', 'sd' => 'up', 'forum' => $forumIdNumber ) ) . '">' . $button['up'] .
				'</a><a href="' . $specialPage->escapeFullURL( array( 'st' => 'answers', 'sd' => 'down', 'forum' => $forumIdNumber ) ) . '">' . $button['down'] . '</a>',
				wfMessage( 'wikiforum-views' )->text() . ' <br /><a href="' .
					$specialPage->escapeFullURL( array( 'st' => 'calls', 'sd' => 'up', 'forum' => $forumIdNumber ) ) . '">' . $button['up'] . '</a><a href="' .
					$specialPage->escapeFullURL( array( 'st' => 'calls', 'sd' => 'down', 'forum' => $forumIdNumber ) ) . '">' . $button['down'] . '</a>',
				wfMessage( 'wikiforum-latest-reply' )->text() . ' <br /><a href="' .
					$specialPage->escapeFullURL( array( 'st' => 'last', 'sd' => 'up', 'forum' => $forumIdNumber ) ) . '">' . $button['up'] .
					'</a><a href="' . $specialPage->escapeFullURL( array( 'st' => 'last', 'sd' => 'up', 'forum' => $forumIdNumber ) ) . '">' . $button['down'] . '</a>',
				false
			);

			$threads_exist = false;
			foreach ( $sqlThreads as $thread ) {
				$threads_exist = true;
				$icon = $this->getThreadIcon(
					$thread->wft_posted_timestamp,
					$thread->wft_closed,
					$thread->wft_sticky
				);

				$last_post = '';
				if ( $thread->wft_reply_count > 0 ) {
					$last_post = wfMessage(
						'wikiforum-by',
						$wgLang->timeanddate( $thread->wft_last_post_timestamp ),
						WikiForumClass::getUserLink(
							$thread->wft_last_post_user,
							$thread->wft_last_post_user_text,
							$thread->wft_last_post_user_ip
						),
						$thread->wft_last_post_user_text,
						$wgLang->date( $thread->wft_last_post_timestamp ),
						$wgLang->time( $thread->wft_last_post_timestamp )
					)->text();
				}

				if ( $thread->wft_sticky == true ) {
					$sticky = 'sticky';
				} else {
					$sticky = false;
				}

				$threadSPobj = SpecialPage::getTitleFor( 'WikiForum', $thread->wft_thread_name );
				$output .= WikiForumGui::getMainBody(
					'<p class="mw-wikiforum-thread">' . $icon .
						Linker::link( $threadSPobj, $thread->wft_thread_name ) .
					'<p class="mw-wikiforum-descr">' .
						wfMessage(
							'wikiforum-posted',
							$wgLang->timeanddate( $thread->wft_posted_timestamp ),
							WikiForumClass::getUserLink(
								$thread->wft_user,
								$thread->wft_user_text,
								$thread->wft_user_ip
							),
							$thread->wft_user_text,
							$wgLang->date( $thread->wft_posted_timestamp ),
							$wgLang->time( $thread->wft_posted_timestamp )
						)->text() . '</p></p>',
					$thread->wft_reply_count,
					$thread->wft_view_count,
					$last_post,
					false,
					$sticky
				);

			}
			if ( $threads_exist == false ) {
				$output .= WikiForumGui::getSingleLine( wfMessage( 'wikiforum-no-threads' )->text(), 4 );
			}
			$output .= WikiForumGui::getMainFooter();

			$countReplies = '';

			if ( $limit_count > 0 ) {
				$countReplies = $dbr->selectRow(
					'wikiforum_threads',
					array( 'COUNT(*) AS count' ),
					array(
						'wft_deleted' => 0,
						'wft_forum' => intval( $data_overview->wff_forum )
					),
					__METHOD__
				);
				$output .= WikiForumGui::getFooterRow(
					$limit_page,
					( isset( $countReplies->count ) ? $countReplies->count : 0 ),
					$limit_count,
					$data_overview->wff_forum
				);
			}
		} else {
			$this->errorTitle = wfMessage( 'wikiforum-forum-not-found' )->text();
			$this->errorMessage = wfMessage(
				'wikiforum-forum-not-found-text',
				'<a href="' . $specialPage->escapeFullURL() . '">' .
					wfMessage( 'wikiforum-overview' )->text() . '</a>' // @todo FIXME
			)->text();
		}

		/* Hide the "Move thread" link as it seems broken. See also pasteThread() function in this class.
		$pastethread_link = '';
		if ( $f_movethread > 0 ) {
			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/paste_plain.png" title="' . wfMessage( 'wikiforum-paste-thread' )->text() . '" /> ';
			$pastethread_link = $icon . Linker::link(
				$specialPage,
				wfMessage( 'wikiforum-paste-thread' )->text(),
				array(),
				array( 'pastethread' => $f_movethread )
			) . wfMessage( 'word-separator' )->plain();
			$output .= WikiForumGui::getHeaderRow( 0, '', 0, '', $pastethread_link );
		}
		*/

		$output .= $this->showFailure();
		return $output;
	}

	function showThread( $threadId ) {
		global $wgOut, $wgRequest, $wgUser, $wgLang, $wgExtensionAssetsPath;

		$output = $this->showFailure();
		$dbr = wfGetDB( DB_SLAVE );

		$sqlData = $dbr->select(
			array( 'wikiforum_forums', 'wikiforum_category', 'wikiforum_threads', 'user' ),
			array(
				'wft_thread', 'wft_thread_name', 'wft_text', 'wff_forum',
				'wff_forum_name', 'wfc_category', 'wfc_category_name',
				'user_name', 'user_id', 'wft_sticky', 'wft_edit_timestamp',
				'wft_edit_user', 'wft_edit_user_text', 'wft_edit_user_ip',
				'wft_posted_timestamp', 'wft_user', 'wft_user_text', 'wft_user_ip',
				'wft_closed', 'wft_closed_user', 'wft_closed_user_text',
				'wft_closed_user_ip',
			),
			array(
				'wff_deleted' => 0,
				'wfc_deleted' => 0,
				'wft_deleted' => 0,
				'wff_category = wfc_category',
				'wff_forum = wft_forum',
				'wft_thread' => $threadId
			),
			__METHOD__,
			array(),
			array( 'user' => array( 'LEFT JOIN', 'user_id = wft_user' ) )
		);

		$data_overview = $dbr->fetchObject( $sqlData );

		$maxRepliesPerPage = intval( wfMessage( 'wikiforum-max-replies-per-page' )->inContentLanguage()->plain() );

		// limiting
		if ( $maxRepliesPerPage && $wgRequest->getVal( 'lc' ) > 0 ) {
			$limit_count = $wgRequest->getVal( 'lc' );
		} elseif ( $maxRepliesPerPage > 0 ) {
			$limit_count = $maxRepliesPerPage;
		}

		if ( is_numeric( $wgRequest->getVal( 'lp' ) ) ) {
			$limit_page = $wgRequest->getVal( 'lp' ) - 1;
		} else {
			$limit_page = 0;
		}
		// end limiting

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		if ( $data_overview ) {
			$queryOptions['ORDER BY'] = 'wfr_posted_timestamp ASC';
			if ( $limit_count > 0 ) {
				$queryOptions['LIMIT'] = $limit_count;
				$queryOptions['OFFSET'] = $limit_page * $limit_count;
			}
			$replies = $dbr->select(
				array( 'wikiforum_replies', 'user' ),
				array( '*', 'user_name', 'user_id' ),
				array(
					'wfr_deleted' => 0,
					'wfr_thread' => $data_overview->wft_thread
				),
				__METHOD__,
				$queryOptions,
				array( 'user' => array( 'LEFT JOIN', 'user_id = wfr_user' ) )
			);

			if ( !wfReadOnly() ) {
				$dbw = wfGetDB( DB_MASTER );
				$dbw->update(
					'wikiforum_threads',
					array( 'wft_view_count = wft_view_count + 1' ),
					array( 'wft_thread' => $data_overview->wft_thread ),
					__METHOD__
				);
			}

			$editButtons = $this->showThreadButtons(
				$data_overview->wft_user,
				$data_overview->wft_closed,
				$data_overview->wft_thread,
				$data_overview->wff_forum
			);

			$menuLink = '';

			if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
				if ( $data_overview->wft_sticky == 1 ) {
					$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/tag_blue_delete.png" title="' . wfMessage( 'wikiforum-remove-sticky' )->text() . '" /> ';
					$menuLink = $icon . '<a href="' . $specialPage->escapeFullURL( array( 'removesticky' => $data_overview->wft_thread ) ) . '">' .
						wfMessage( 'wikiforum-remove-sticky' )->text() . '</a> ';
				} else {
					$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/tag_blue_add.png" title="' . wfMessage( 'wikiforum-make-sticky' )->text() . '" /> ';
					$menuLink = $icon . '<a href="' . $specialPage->escapeFullURL( array( 'makesticky' => $data_overview->wft_thread ) ) . '">' .
							wfMessage( 'wikiforum-make-sticky' )->text() . '</a> ';
				}
			}

			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comment_add.png" title="' . wfMessage( 'wikiforum-write-reply' )->text() . '" /> ';
			// Replying is only possible to open threads
			if ( $data_overview->wft_closed == 0 ) {
				$menuLink .= $icon . '<a href="#writereply">' .
					wfMessage( 'wikiforum-write-reply' )->text() . '</a>';
			}

			$posted = wfMessage(
				'wikiforum-posted',
				$wgLang->timeanddate( $data_overview->wft_posted_timestamp ),
				WikiForumClass::getUserLink(
					$data_overview->wft_user,
					$data_overview->wft_user_text,
					$data_overview->wft_user_ip
				),
				$data_overview->wft_user_text,
				$wgLang->date( $data_overview->wft_posted_timestamp ),
				$wgLang->time( $data_overview->wft_posted_timestamp )
			)->text();
			if ( $data_overview->wft_edit_timestamp > 0 ) {
				$posted .= '<br /><i>' .
					wfMessage(
						'wikiforum-edited',
						$wgLang->timeanddate( $data_overview->wft_edit_timestamp ),
						WikiForumClass::getUserLink(
							$data_overview->wft_edit_user,
							$data_overview->wft_edit_user_text,
							$data_overview->wft_edit_user_ip
						),
						$data_overview->wft_edit_user_text,
						$wgLang->date( $data_overview->wft_edit_timestamp ),
						$wgLang->time( $data_overview->wft_edit_timestamp )
					)->text() . '</i>';
			}

			$output .= WikiForumGui::getSearchbox();
			$output .= WikiForumGui::getHeaderRow(
				$data_overview->wfc_category,
				$data_overview->wfc_category_name,
				$data_overview->wff_forum,
				$data_overview->wff_forum_name,
				$menuLink
			);

			// Add topic name to the title
			// @todo FIXME: this is lame and doesn't apply to the <title> attribute
			$wgOut->setPageTitle( wfMessage( 'wikiforum-topic-name', $data_overview->wft_thread_name )->text() );
			$wgOut->setHTMLTitle( wfMessage( 'wikiforum-topic-name', $data_overview->wft_thread_name )->text() );

			$output .= WikiForumGui::getThreadHeader(
				htmlspecialchars( $data_overview->wft_thread_name ),
				$this->parseIt( $data_overview->wft_text ),// $wgOut->parse( $data_overview->wft_text ),
				$posted,
				$editButtons,
				$data_overview->wft_thread,
				$data_overview->user_id
			);

			foreach ( $replies as $reply ) {
				$editButtons = $this->showReplyButtons(
					$reply->wfr_user,
					$reply->wfr_reply_id,
					$data_overview->wft_thread,
					$data_overview->wft_closed
				);

				$posted = wfMessage(
					'wikiforum-posted',
					$wgLang->timeanddate( $reply->wfr_posted_timestamp ),
					WikiForumClass::getUserLink(
						$reply->wfr_user,
						$reply->wfr_user_text,
						$reply->wfr_user_ip
					),
					$reply->user_name,
					$wgLang->date( $reply->wfr_posted_timestamp ),
					$wgLang->time( $reply->wfr_posted_timestamp )
				)->text();
				if ( $reply->wfr_edit_timestamp > 0 ) {
					$posted .= '<br /><i>' .
						wfMessage(
							'wikiforum-edited',
							$wgLang->timeanddate( $reply->wfr_edit_timestamp ),
							WikiForumClass::getUserLink(
								$reply->wfr_edit_user,
								$reply->wfr_edit_user_text,
								$reply->wfr_edit_user_ip
							),
							$reply->wfr_edit_user_text,
							$wgLang->date( $reply->wfr_edit_timestamp ),
							$wgLang->time( $reply->wfr_edit_timestamp )
						)->text() . '</i>';
				}

				$output .= WikiForumGui::getReply(
					$this->parseIt( $reply->wfr_reply_text ),// $wgOut->parse( $reply->wfr_reply_text ),
					$posted,
					$editButtons,
					$reply->wfr_reply_id,
					$reply->user_id
				);
			}

			$output .= WikiForumGui::getThreadFooter();

			if ( $limit_count > 0 ) {
				$countReplies = $dbr->selectRow(
					'wikiforum_replies',
					'COUNT(*) AS count',
					array(
						'wfr_deleted' => 0,
						'wfr_thread' => $data_overview->wft_thread
					),
					__METHOD__
				);
				$output .= WikiForumGui::getFooterRow(
					$limit_page,
					$countReplies->count,
					$limit_count,
					$data_overview->wff_forum,
					$threadId
				);
			}

			$mod_editcomment = $wgRequest->getInt( 'editcomment' );
			$mod_form = $wgRequest->getBool( 'form' );
			if (
				$data_overview->wft_closed == 0 ||
				( isset( $mod_editcomment ) && $mod_editcomment > 0 &&
					$mod_form != true &&
					$wgUser->isAllowed( 'wikiforum-moderator' )
				)
			)
			{
				$output .= $this->showEditor( $data_overview->wft_thread, 'addcomment' );
			} else {
				$this->errorTitle = wfMessage( 'wikiforum-thread-closed' )->text();
				$this->errorMessage = wfMessage( 'wikiforum-error-thread-closed' )->text();
				$this->errorIcon = 'lock.png';// 'icon_thread_closed';
			}
		} else {
			$this->errorTitle = wfMessage( 'wikiforum-thread-not-found' )->text();
			$this->errorMessage = wfMessage(
				'wikiforum-thread-not-found-text',
				'<a href="' . $specialPage->escapeFullURL() . '">' .
					wfMessage( 'wikiforum-overview' )->text() . '</a>' // @todo FIXME
			)->text();
		}

		/* This "move thread" feature doesn't seem to be working so I'm commenting it out
		$movethread_link = '';
		if ( $wgUser->isAllowed( 'wikiforum-moderator' ) ) {
			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/note_go.png" title="' . wfMessage( 'wikiforum-move-thread' )->text() . '" /> ';
			$movethread_link = $icon . '<a href="' . $specialPage->escapeFullURL( array( 'movethread' => $data_overview->wft_thread ) ) . '">' . wfMessage( 'wikiforum-move-thread' )->text() . '</a> ';
			$output .= WikiForumGui::getHeaderRow( 0, '', 0, '', $movethread_link );
		}
		*/

		$output .= $this->showFailure();

		return $output;
	}

	/**
	 * Show the search results page.
	 *
	 * @param $what String: ENGLISH MOTHERFUCKER! DO YOU SPEAK IT?
	 *                      I mean...the search query string.
	 * @return HTML output
	 */
	function showSearchResults( $what ) {
		global $wgOut, $wgRequest, $wgUser, $wgLang;

		$output = $this->showFailure();
		$output .= WikiForumGui::getSearchbox();
		$output .= WikiForumGui::getHeaderRow( 0, '', 0, '', '' );

		if ( strlen( $what ) > 1 ) {
			$i = 0;

			$dbr = wfGetDB( DB_SLAVE );
			// buildLike() will escape the query properly, add the word LIKE
			// and the "double quotes"
			$likeString = $dbr->buildLike( $dbr->anyString(), $what, $dbr->anyString() );

			$threadsTable = $dbr->tableName( 'wikiforum_threads' );
			$repliesTable = $dbr->tableName( 'wikiforum_replies' );

			$sqlData = $dbr->query(
				"SELECT wfr_posted_timestamp, wfr_user, wfr_user_text, wfr_user_ip, wft_thread_name, wft_thread, wfr_reply_text AS Search, wfr_reply_id
				FROM $repliesTable, $threadsTable
				LEFT JOIN " . $dbr->tableName( 'user' ) . ' ON user_id = wft_user
				WHERE wfr_deleted = 0 AND wft_deleted = 0 AND wft_thread = wfr_thread AND wfr_reply_text ' . $likeString . "
				UNION ALL
					SELECT wft_posted_timestamp, wft_user, wft_user_text, wft_user_ip, wft_thread_name, wft_thread, wft_text AS Search, 0 FROM $threadsTable
					LEFT JOIN " . $dbr->tableName( 'user' ) . ' ON user_id = wft_user
					WHERE wft_deleted = 0 AND (wft_text ' . $likeString . ' OR wft_thread_name ' . $likeString . ')
					ORDER BY wfr_posted_timestamp DESC LIMIT 0, 30',
				__METHOD__
			);

			$output_temp = '';

			foreach ( $sqlData as $result ) {
				$anchor = '';
				if ( $result->wfr_reply_id > 0 ) {
					$anchor = '#reply_' . $result->wfr_reply_id;
				}

				$url = SpecialPage::getTitleFor( 'WikiForum', $result->wft_thread_name )->escapeFullURL();

				$posted = wfMessage(
					'wikiforum-posted',
					$wgLang->timeanddate( $result->wfr_posted_timestamp ),
					WikiForumClass::getUserLink(
						$result->wfr_user,
						$result->wfr_user_text,
						$result->wfr_user_ip
					),
					$result->wfr_user_text,
					$wgLang->date( $result->wfr_posted_timestamp ),
					$wgLang->time( $result->wfr_posted_timestamp )
				)->text() . '<br />' . wfMessage(
						'wikiforum-search-thread',
						'<a href="' . $url . $anchor . '">' .
							$result->wft_thread_name .
						'</a>'
					)->text();

				$output_temp .= WikiForumGui::getReply(
					$this->parseIt( $result->Search ),
					$posted,
					false,
					$result->wfr_reply_id,
					$result->wfr_user
				);
				$i++;
			}

			$title = wfMessage( 'wikiforum-search-hits', $i )->parse();
			$output .= WikiForumGui::getReplyHeader( $title );
			$output .= $output_temp;
			$output .= WikiForumGui::getThreadFooter();
		} else {
			$this->errorTitle = wfMessage( 'wikiforum-error-search' )->text();
			$this->errorMessage = wfMessage( 'wikiforum-error-search-missing-query' )->text();
		}
		$output .= $this->showFailure();
		return $output;
	}

	/**
	 * Preview a reply.
	 */
	function previewIssue( $type, $id, $previewTitle, $previewText ) {
		global $wgRequest, $wgUser, $wgLang;

		$output = $this->showFailure();

		$title = wfMessage( 'wikiforum-preview' )->text();
		if ( $previewTitle ) {
			$title = wfMessage( 'wikiforum-preview-with-title', $previewTitle )->text();
		}
		$posted = wfMessage(
			'wikiforum-posted',
			$wgLang->timeanddate( wfTimestampNow() ),
			WikiForumClass::getUserLink( $wgUser->getId(), $wgUser->getName(), $wgRequest->getIP() ),
			$wgUser->getName(),
			$wgLang->date( wfTimestampNow() ),
			$wgLang->time( wfTimestampNow() )
		)->text();
		if ( $type == 'addcomment' || $type == 'editcomment' ) {
			$output .= WikiForumGui::getReplyHeader( $title );
			$output .= WikiForumGui::getReply(
				$this->parseIt( $previewText ),
				$posted,
				false,
				0,
				$wgUser->getId()
			);
		} else {
			$output .= WikiForumGui::getThreadHeader(
				$title,
				$this->parseIt( $previewText ),
				$posted,
				false,
				false,
				$wgUser->getId()
			);
		}
		$output .= WikiForumGui::getThreadFooter();

		$this->result = false;

		$output .= $this->showEditor( $id, $type );

		// Jack this adds an useless "Overview" link to the bottom of the page
		// (below the save reply/cancel buttons) so I took the liberty of
		// removing it
		# $output .= WikiForumGui::getHeaderRow( 0, '', 0, '', '' );
		$output .= $this->showFailure();
		return $output;
	}

	function writeThread( $forumId ) {
		global $wgRequest, $wgUser;

		$output = $this->showFailure();
		$dbr = wfGetDB( DB_SLAVE );

		$mod_editthread = $wgRequest->getInt( 'editthread' );
		if ( $mod_editthread ) { // are we editing an existing thread?
			$sqlData = $dbr->select(
				array(
					'wikiforum_forums', 'wikiforum_category',
					'wikiforum_threads'
				),
				array(
					'wff_forum', 'wff_forum_name', 'wfc_category',
					'wfc_category_name', 'wff_announcement'
				),
				array(
					'wff_deleted' => 0,
					'wfc_deleted' => 0,
					'wft_deleted' => 0,
					'wff_category = wfc_category',
					'wff_forum = wft_forum',
					'wft_thread' => $forumId
				),
				__METHOD__
			);
		} else { // no, we're not editing an existing thread, we're starting a new one
			$sqlData = $dbr->select(
				array( 'wikiforum_forums', 'wikiforum_category' ),
				array(
					'wff_forum', 'wff_forum_name', 'wfc_category',
					'wfc_category_name', 'wff_announcement'
				),
				array(
					'wff_deleted' => 0,
					'wfc_deleted' => 0,
					'wff_category = wfc_category',
					'wff_forum' => $forumId
				),
				__METHOD__
			);
		}
		$overview = $dbr->fetchObject( $sqlData );

		// Everyone (privileged) can post to non-announcement forums and mods
		// can post even to announcement-only forums
		if (
			$overview->wff_announcement == false ||
			$wgUser->isAllowed( 'wikiforum-moderator' )
		)
		{
			$output .= WikiForumGui::getHeaderRow(
				$overview->wfc_category,
				$overview->wfc_category_name,
				$overview->wff_forum,
				$overview->wff_forum_name,
				''
			);
			$output .= $this->showEditor( $overview->wff_forum, 'addthread' );
			$output .= $this->showFailure();
		}

		return $output;
	}

	function showEditorCatForum( $id, $type, $values ) {
		$title_prev = '';
		$text_prev = '';

		$dbr = wfGetDB( DB_SLAVE );
		$save_button = wfMessage( 'wikiforum-save' )->plain();

		if ( isset( $values['text'] ) && strlen( $values['text'] ) > 0 ) {
			$text_prev = $values['text'];
		}

		// define variable to prevent E_NOTICE; the type of the variable
		// is checked in WikiForumGui::getFormCatForum()
		$overview = '';

		if ( $type == 'addcategory' ) {
			$categoryName = wfMessage( 'wikiforum-add-category' )->text();
		} elseif ( $type == 'editcategory' ) {
			$overview = $dbr->fetchObject( $dbr->select(
				'wikiforum_category',
				array( 'wfc_category', 'wfc_category_name' ),
				array( 'wfc_deleted' => 0, 'wfc_category' => intval( $id ) ),
				__METHOD__
			) );
			$id = $overview->wfc_category;
			$title_prev = $overview->wfc_category_name;
			$categoryName = wfMessage( 'wikiforum-edit-category' )->text();
		} elseif ( $type == 'addforum' ) {
			$categoryName = wfMessage( 'wikiforum-add-forum' )->text();
		} elseif ( $type == 'editforum' ) {
			$overview = $dbr->fetchObject( $dbr->select(
				'wikiforum_forums',
				array(
					'wff_forum', 'wff_forum_name', 'wff_description',
					'wff_announcement'
				),
				array( 'wff_deleted' => 0, 'wff_forum' => $id ),
				__METHOD__
			) );
			$id = $overview->wff_forum;
			$title_prev = $overview->wff_forum_name;
			if ( strlen( $text_prev ) == 0 ) {
				$text_prev = $overview->wff_description;
			}
			$categoryName = wfMessage( 'wikiforum-edit-forum' )->text();
		}
		$action = array( $type => $id );

		$output = WikiForumGui::getFormCatForum(
			$type, $categoryName, $action,
			$title_prev, $text_prev, $save_button, $overview
		);
		$output .= $this->showFailure();
		return $output;
	}

	function showEditor( $id, $type ) {
		global $wgRequest, $wgLang;

		$text_prev = '';

		$dbr = wfGetDB( DB_SLAVE );

		if ( $this->result == false ) {
			$text_prev = $wgRequest->getVal( 'frmText' );
			$title_prev	= $wgRequest->getVal( 'frmTitle' );
		} else {
			$title_prev = wfMessage( 'wikiforum-thread-title' )->text();
		}

		if ( $type == 'addthread' || $type == 'editthread' ) {
			$mod_editthread = $wgRequest->getInt( 'editthread' );
			$mod_preview = $wgRequest->getBool( 'butPreview' );

			if ( $mod_editthread && $mod_editthread > 0 ) {
				if ( !$text_prev || !$title_prev || $mod_preview == true ) {
					$data_thread = $dbr->fetchObject( $dbr->select(
						'wikiforum_threads',
						array( 'wft_thread', 'wft_thread_name', 'wft_text' ),
						array(
							'wft_deleted' => 0,
							'wft_thread' => intval( $mod_editthread )
						),
						__METHOD__
					) );
					$action = array(
						'editthread' => $data_thread->wft_thread
					);
					if ( !$text_prev ) {
						$text_prev = $data_thread->wft_text;
					}
					if ( $title_prev == wfMessage( 'wikiforum-thread-title' )->text() ) {
						$title_prev = $data_thread->wft_thread_name;
					}
				}
			} else {
				$action = array( 'addthread' => $id );
			}

			$height = '25em';
			$save_button = wfMessage( 'wikiforum-save-thread' )->text();
			$input = WikiForumGui::getInput( $title_prev );
		} else { // add reply
			$mod_comment = $wgRequest->getInt( 'editcomment' );
			$mod_form = $wgRequest->getBool( 'form' );
			$mod_preview = $wgRequest->getBool( 'butPreview' );
			$mod_quotec = $wgRequest->getInt( 'quotecomment' );
			$mod_quotet = $wgRequest->getInt( 'quotethread' );

			// quote
			if ( isset( $mod_quotec ) && $mod_quotec > 0 ) {
				$reply = $dbr->fetchObject( $dbr->select(
					array( 'wikiforum_replies', 'user' ),
					array( 'wfr_reply_text', 'wfr_posted_timestamp', 'user_name' ),
					array( 'wfr_deleted' => 0, 'wfr_reply_id' => $mod_quotec ),
					__METHOD__,
					array(),
					array( 'user' => array( 'LEFT JOIN', 'user_id = wfr_user' ) )
				) );
				if ( $reply ) {
					$posted = wfMessage(
						'wikiforum-posted',
						$wgLang->timeanddate( $reply->wfr_posted_timestamp ),
						// Jack: removed getUserLink() call here to make quoting work
						// if the call is present, then pressing the quote button
						// will generate shit like [quote=Posted on foo by Bar
						// <a href="/wiki/User:Bar" title="Bar">Bar</a> (
						// <a href="/wiki/Project:Administrators">administrator</a>)
						// which is *not* what we want
						$reply->user_name,
						$reply->user_name,
						$wgLang->date( $reply->wfr_posted_timestamp ),
						$wgLang->time( $reply->wfr_posted_timestamp )
					)->text();
					$text_prev = '[quote=' . $posted . ']' .
						$reply->wfr_reply_text . '[/quote]';
				}
			} elseif ( isset( $mod_quotet ) && $mod_quotet > 0 ) {
				$thread = $dbr->selectRow(
					array( 'wikiforum_threads', 'user' ),
					array( 'wft_text', 'wft_posted_timestamp', 'user_name' ),
					array( 'wft_deleted' => 0, 'wft_thread' => $mod_quotet ),
					__METHOD__,
					array(),
					array( 'user' => array( 'LEFT JOIN', 'user_id = wft_user' ) )
				);
				if ( $thread ) {
					$posted = wfMessage(
						'wikiforum-posted',
						$wgLang->timeanddate( $thread->wft_posted_timestamp ),
						// see the explanation above
						$thread->user_name,
						$thread->user_name,
						$wgLang->date( $thread->wft_posted_timestamp ),
						$wgLang->time( $thread->wft_posted_timestamp )
					)->text();
					$text_prev = '[quote=' . $posted . ']' .
						$thread->wft_text . '[/quote]';
				}
			}
			// end quote

			if (
				isset( $mod_comment ) && $mod_comment > 0 &&
				( $mod_form != true || $mod_preview == true )
			)
			{
				if ( $mod_preview == true ) {
					$id = $wgRequest->getInt( 'thread' );
				}
				$dbr = wfGetDB( DB_SLAVE );
				$reply = $dbr->fetchObject( $dbr->select(
					'wikiforum_replies',
					array( 'wfr_reply_id', 'wfr_reply_text' ),
					array( 'wfr_deleted' => 0, 'wfr_reply_id' => $mod_comment ),
					__METHOD__
				) );
				$action = array(
					'thread' => $id,
					'form' => true,
					'editcomment' => $reply->wfr_reply_id
				);
				if ( $mod_preview != true ) {
					$text_prev = $reply->wfr_reply_text;
				}
			} else {
				$action = array( 'addcomment' => $id, 'thread' => $id );
			}
			$height = '10em';
			$input = '';
			$save_button = wfMessage( 'wikiforum-save-reply' )->text();
		}
		return WikiForumGui::getWriteForm(
			$type, $action, $input, $height, $text_prev, $save_button
		);
	}

	/**
	 * Show the reply buttons: new, edit and delete.
	 *
	 * @param $postedBy Integer: user ID of the person who started the topic
	 * @param $replyID Integer: ID number of the reply
	 * @param $threadID Integer: thread ID number
	 * @param $closed
	 * @return HTML
	 */
	private function showReplyButtons( $postedBy, $replyID, $threadID, $closed ) {
		global $wgUser, $wgExtensionAssetsPath;

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );
		$editButtons = '<a href="' . $specialPage->escapeFullURL( array(
			'thread' => $threadID,
			'quotecomment' => $replyID
		) ) . '#writereply">';
		$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comments_add.png" title="' . wfMessage( 'wikiforum-quote' )->text() . '" />';

		if (
			( $wgUser->getId() == $postedBy && $closed == 0 ) ||
			$wgUser->isAllowed( 'wikiforum-moderator' )
		)
		{
			$editButtons .= ' <a href="' . $specialPage->escapeFullURL( array( 'thread' => $threadID, 'editcomment' => $replyID ) ) . '#writereply">';
			$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comment_edit.png" title="' . wfMessage( 'wikiforum-edit-reply' )->text() . '" />';
			$editButtons .= '</a> <a href="' . $specialPage->escapeFullURL( array( 'thread' => $threadID, 'deletecomment' => $replyID ) ) . '">';
			$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comment_delete.png" title="' . wfMessage( 'wikiforum-delete-reply' )->text() . '" />';
		}

		$editButtons .= '</a>';

		return $editButtons;
	}

	/**
	 * Shows the edit/delete buttons for the topic author, moderators and also
	 * close (lock) and reopen (unlock) buttons for moderators.
	 *
	 * @param $postedBy Integer: user ID of the person who posted the thread
	 * @param $closed
	 * @param $threadID Integer: thread ID number
	 * @param $forumID Integer: forum ID number
	 * @return HTML
	 */
	private function showThreadButtons( $postedBy, $closed, $threadID, $forumID ) {
		global $wgUser, $wgExtensionAssetsPath;

		$editButtons = '';

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$editButtons .= '<a href="' . $specialPage->escapeFullURL( array( 'thread' => $threadID, 'quotethread' => $threadID ) ) . '#writereply">';
		$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/comments_add.png" title="' . wfMessage( 'wikiforum-quote' )->text() . '" />';
		$editButtons .= '</a>';

		if (
			$wgUser->getId() == $postedBy ||
			$wgUser->isAllowed( 'wikiforum-moderator' )
		)
		{
			$editButtons .= ' <a href="' . $specialPage->escapeFullURL( array( 'editthread' => $threadID ) ) . '">';
			$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/note_edit.png" title="' . wfMessage( 'wikiforum-edit-thread' )->text() . '" />';
			$editButtons .= '</a> <a href="' . $specialPage->escapeFullURL( array( 'forum' => $forumID, 'deletethread' => $threadID ) ) . '">';
			$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/note_delete.png" title="' . wfMessage( 'wikiforum-delete-thread' )->text() . '" />';
			$editButtons .= '</a> ';

			// Only moderators can lock and reopen threads
			if ( $wgUser->isAllowed( 'wikiforum-moderator' ) ) {
				if ( $closed == 0 ) {
					$editButtons .= ' <a href="' . $specialPage->escapeFullURL( array( 'closethread' => $threadID ) ) . '">';
					$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/lock_add.png" title="' . wfMessage( 'wikiforum-close-thread' )->text() . '" />';
					$editButtons .= '</a>';
				} else {
					$editButtons .= ' <a href="' . $specialPage->escapeFullURL( array( 'reopenthread' => $threadID ) ) . '">';
					$editButtons .= '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/lock_open.png" title="' . wfMessage( 'wikiforum-reopen-thread' )->text() . '" />';
					$editButtons .= '</a>';
				}
			}
		}

		return $editButtons;
	}

	/**
	 * Show icons for administrative functions (edit, delete, sort up/down).
	 *
	 * @param $type String: either category or forum
	 * @param $id Integer
	 * @param $sortup Boolean
	 * @param $sortdown Boolean
	 * @return HTML
	 */
	private function showAdminIcons( $type, $id, $sortup, $sortdown ) {
		global $wgUser, $wgExtensionAssetsPath;

		$link = '';

		if ( $wgUser->isAllowed( 'wikiforum-admin' ) ) {
			// Quick hack for fetching the correct icon
			if ( $type == 'category' ) {
				$iconName = 'database';
			} elseif ( $type == 'forum' ) {
				$iconName = 'folder';
			}

			$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

			// For grep: wikiforum-edit-forum, wikiforum-edit-category,
			// wikiforum-delete-forum, wikiforum-delete-category
			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/' . $iconName . '_edit.png" title="' . wfMessage( 'wikiforum-edit-' . $type )->text() . '" />';
			$link = ' <a href="' . $specialPage->escapeFullURL( array( 'edit' . $type => $id ) ) . '">' . $icon . '</a>';

			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/' . $iconName . '_delete.png" title="' . wfMessage( 'wikiforum-delete-' . $type )->text() . '" />';
			$link .= ' <a href="' . $specialPage->escapeFullURL( array( 'delete' . $type => $id ) ) . '">' . $icon . '</a>';

			if ( $sortup == true ) {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/arrow_up.png" title="' . wfMessage( 'wikiforum-sort-up' )->text() . '" />';
				$link .= ' <a href="' . $specialPage->escapeFullURL( array( $type . 'up' => $id ) ) . '">' . $icon . '</a>';
			}

			if ( $sortdown == true ) {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/arrow_down.png" title="' . wfMessage( 'wikiforum-sort-down' )->text() . '" />';
				$link .= ' <a href="' . $specialPage->escapeFullURL( array( $type . 'down' => $id ) ) . '">' . $icon . '</a>';
			}
		}

		return $link;
	}

	/**
	 * Get a descriptive icon for the current thread.
	 * "New" icon for new threads (threads that are not over $dayDefinitionNew
	 * days old), sticky icon for stickied threads,
	 * locked icon for locked threads and an ordinary thread icon for
	 * everything else.
	 *
	 * @param $posted Integer: timestamp; when this thread was posted
	 * @param $closed Integer: if greater than zero, the thread is closed
	 * @param $sticky Boolean: if true, the thread is stickied
	 * @return HTML: img tag
	 */
	public static function getThreadIcon( $posted, $closed, $sticky ) {
		global $wgExtensionAssetsPath;

		// Threads that are this many days old or newer are considered "new"
		$dayDefinitionNew = intval( wfMessage( 'wikiforum-day-definition-new' )->inContentLanguage()->plain() );

		$olderTimestamp = wfTimestamp( TS_MW, strtotime( '-' . $dayDefinitionNew . ' days' ) );

		$imagePath = $wgExtensionAssetsPath . '/WikiForum/icons';
		if ( $sticky == 1 ) {
			return '<img src="' . $imagePath . '/tag_blue.png" title="' . wfMessage( 'wikiforum-sticky' )->text() . '" /> ';
		} elseif ( $closed > 0 ) {
			return '<img src="' . $imagePath . '/lock.png" title="' . wfMessage( 'wikiforum-thread-closed' )->text() . '" /> ';
		} elseif ( $posted > $olderTimestamp ) {
			return '<img src="' . $imagePath . '/new.png" title="' . wfMessage( 'wikiforum-new-thread' )->text() . '" /> ';
		} else {
			return '<img src="' . $imagePath . '/note.png" title="' . wfMessage( 'wikiforum-thread' )->text() . '" /> ';
		}
	}

	/**
	 * If errorTitle and errorIcon class member variables are set, show an
	 * error message.
	 *
	 * @return HTML
	 */
	private function showFailure() {
		global $wgExtensionAssetsPath;

		$output = '';

		if ( strlen( $this->errorTitle ) > 0 ) {
			if ( strlen( $this->errorIcon ) > 0 ) {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/' . $this->errorIcon . '" /> ';
			} else {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/exclamation.png" title="' . wfMessage( 'wikiforum-thread-closed' )->text() . '" /> ';
			}
			$output	= '<br /><table class="mw-wikiforum-frame"><tr><td>' . $icon .
				$this->errorTitle . '<p class="mw-wikiforum-descr">' . $this->errorMessage .
				'</p></td></tr></table>';
		}
		$this->errorTitle = '';
		$this->errorMessage = '';
		return $output;
	}

	// Helper functions, moved from WikiForumHelperClass.php
	/**
	 * Get the link to the specified user's userpage (and group membership
	 * for administrators and ShoutWiki staff).
	 *
	 * @param $uid Integer: user ID
	 * @param $username String: username
	 * @param $ip String: IP address
	 * @return HTML
	 */
	public static function getUserLink( $uid, $username, $ip ) {
		global $wgContLang, $wgUser;

		$userObj = User::newFromId( $uid );

		// Do no further processing for anons, since anons cannot have
		// groups.
		if ( !$userObj instanceof User ) {
			return Linker::link(
				Title::makeTitle( NS_USER, $ip ),
				$ip
			);
		}

		$username = $userObj->getName();

		if ( $username ) {
			$retVal = Linker::link(
				Title::makeTitle( NS_USER, $username ),
				$username
			);

			$groups = $userObj->getEffectiveGroups();
			// @todo FIXME: this code is horrible, it basically does
			// special-casing for staff and sysop groups
			// instead it should handle only staff as a special case
			// and treat bureaucrat, sysop etc. normally
			//
			// Staff tag for staff members
			// This is a horrible, ShoutWiki-specific hack
			$isStaff = in_array( 'staff', $groups );
			if ( $isStaff && function_exists( 'wfStaffSig' ) ) {
				global $wgOut;
				$staffSig = $wgOut->parse( '<staff />' );
				// copied from extensions/Comments/CommentClass.php
				// really bad hack because we want to parse=firstline, but
				// don't want wrapping <p> tags
				if ( substr( $staffSig, 0 , 3 ) == '<p>' ) {
					$staffSig = substr( $staffSig, 3 );
				}

				if ( substr( $staffSig, strlen( $staffSig ) - 4, 4 ) == '</p>' ) {
					$staffSig = substr( $staffSig, 0, strlen( $staffSig ) - 4 );
				}
				// end copied bad hack
				$retVal = $retVal . $staffSig;
			} elseif ( in_array( 'sysop', $groups ) && !$isStaff ) {
				// we <3 i18n
				$retVal = $retVal . wfMessage( 'word-separator' )->plain() .
					wfMessage(
						'parentheses',
						User::makeGroupLinkHTML( 'sysop', User::getGroupMember( 'sysop', $username ) )
					)->text();
			} elseif ( in_array( 'forumadmin', $groups ) && !$isStaff ) {
				// this madness has to stop...really...
				$retVal = $retVal . wfMessage( 'word-separator' )->plain() .
					wfMessage(
						'parentheses',
						User::makeGroupLinkHTML( 'forumadmin', User::getGroupMember( 'forumadmin', $username ) )
					)->text();
			}
			return $retVal;
		} else {
			// This is just a failsafe, we should _never_ be hitting this anyway...
			return wfMessage( 'wikiforum-anonymous' )->text();
		}
	}

	/**
	 * "Prepare" smilies by wrapping them in <nowiki>.
	 *
	 * @param $text String: text to search for smilies
	 * @return $text String: input text with smilies wrapped inside <nowiki>
	 */
	function prepareSmilies( $text ) {
		global $wgWikiForumSmilies;

		if ( is_array( $wgWikiForumSmilies ) ) {
			foreach ( $wgWikiForumSmilies as $key => $icon ) {
				$text = str_replace(
					$key,
					'<nowiki>' . $key . '</nowiki>',
					$text
				);
			}
		}
		return $text;
	}

	function getSmilies( $text ) {
		global $wgExtensionAssetsPath;
		global $wgWikiForumSmilies;

		// damn unclear code => need a better preg_replace patter to simplify
		if ( is_array( $wgWikiForumSmilies ) && !empty( $wgWikiForumSmilies ) ) {
			$path = $wgExtensionAssetsPath . '/WikiForum';
			foreach ( $wgWikiForumSmilies as $key => $icon ) {
				$text = str_replace(
					$key,
					'<img src="' . $path . '/' . $icon . '" title="' . $key . '"/>',
					$text
				);
				$text = str_replace(
					'&lt;nowiki&gt;<img src="' .  $path . '/' . $icon . '" title="' . $key . '"/>&lt;/nowiki&gt;',
					$key,
					$text
				);
				$text = preg_replace(
					'/\&lt;nowiki\&gt;(.+)\&lt;\/nowiki\&gt;/iUs',
					'\1',
					$text
				);
			}
		}
		return $text;
	}

	function parseIt( $text ) {
		global $wgOut;

		// add smilies for reply text
		$text = $this->prepareSmilies( $text );
		$text = $wgOut->parse( $text );
		$text = $this->parseLinks( $text );
		$text = $this->parseQuotes( $text );
		$text = $this->getSmilies( $text );

		return $text;
	}

	function parseLinks( $text ) {
		$text = preg_replace_callback(
			'/\[thread#(.*?)\]/i',
			array( $this, 'getThreadTitle' ),
			$text
		);
		return $text;
	}

	function parseQuotes( $text ) {
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

	public static function deleteTags( $text ) {
		$text = preg_replace(
			'/\<WikiForumThread id=(.*?)\/\>/',
			'&lt;WikiForumThread id=\1/&gt;',
			$text
		);
		$text = preg_replace(
			'/\<WikiForumList(.*)\/>/',
			'&lt;WikiForumList \1/&gt;',
			$text
		);
		return $text;
	}

	/**
	 * Get the title of a thread when we know its ID number.
	 *
	 * @param $id Integer: thread ID number
	 * @return String: thread title
	 */
	function getThreadTitle( $id ) {
		if ( is_numeric( $id[1] ) && $id[1] > 0 ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'wikiforum_threads',
				'wft_thread_name',
				array(
					'wft_deleted' => 0,
					'wft_thread' => $id[1]
				),
				__METHOD__
			);

			if ( $res ) {
				$specialPage = SpecialPage::getTitleFor( 'WikiForum', $res->wft_thread_name );
				return '<i>' . Linker::link(
					$specialPage,
					$overview->wft_thread_name
				) . '</i>';
			} else {
				return wfMessage(
					'brackets',
					wfMessage( 'wikiforum-thread-deleted' )->text()
				)->text();
			}
		}
		return $id[0];
	}

	/**
	 * Simple thread existence check. Checks if there is a thread with the
	 * given title and if so, returns true; otherwise returns false.
	 *
	 * @param $title String: title to check for existence
	 * @return Boolean: true when there's a thread with the supplied title
	 */
	function doesThreadExist( $title ) {
		$dbr = wfGetDB( DB_SLAVE );
		$threadId = $dbr->selectField(
			'wikiforum_threads',
			'wft_thread',
			array(
				'wft_deleted' => 0,
				'wft_thread_name' => $title
			),
			__METHOD__
		);
		if ( $threadId ) {
			// This thread exists already
			return true;
		} else {
			// Doesn't exist and can be created
			return false;
		}
	}
}
