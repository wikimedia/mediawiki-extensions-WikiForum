<?php

use MediaWiki\Context\ContextSource;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class WFThread extends ContextSource {

	private $data;
	public $forum;
	private $replies;
	public $preloadText;
	/** @var bool|null True if should be auto-locked, false if not, null if not checked yet */
	private $shouldAutoLock = null;

	/**
	 * @param stdClass $sql
	 */
	private function __construct( $sql ) {
		$this->data = $sql;
	}

	/**
	 * Get the WFThread object for the thread with the given ID number
	 *
	 * @param int $id ID to find
	 * @return self|false
	 */
	public static function newFromID( $id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$data = $dbr->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread' => $id ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Get the WFThread object from a row from the DB
	 *
	 * @param stdClass $sql the row. Not a ResultWrapper! (Either use $dbr->fetchObject(), or loop through the resultWrapper!)
	 * @return self
	 */
	public static function newFromSQL( $sql ) {
		return new self( $sql );
	}

	/**
	 * Find a thread when you know the title.
	 *
	 * @param string $titleText thread title
	 * @return self|false Thread, or false on failure
	 */
	public static function newFromName( $titleText ) {
		// Titles are stored with spaces in the DB but the query will otherwise
		// use friggin' underscores...
		$titleText = str_replace( '_', ' ', $titleText );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$data = $dbr->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread_name' => $titleText ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Whether or not the thread is sticky
	 *
	 * @return bool
	 */
	function isSticky() {
		return $this->data->wft_sticky == true;
	}

	/**
	 * Whether or not the thread is closed
	 *
	 * @return bool
	 */
	function isClosed() {
		// Check if manually closed
		if ( $this->data->wft_closed_timestamp > 0 ) {
			return true;
		}

		// Check if should be auto-locked and create job if needed
		// If handleAutoLock returns true, the thread should be considered closed
		return $this->handleAutoLock();
	}

	/**
	 * Check if this thread meets the conditions for auto-lock based on inactivity time (internal method).
	 * This method performs the actual check and is called by handleAutoLock() and LockInactiveThreadJob.
	 *
	 * @internal This is an internal method. Use handleAutoLock() for checking with job creation.
	 * @return bool True if the thread should be auto-locked, false otherwise
	 */
	function checkAutoLockConditions() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$autoLockHours = $config->get( 'WikiForumAutoLockInactiveHours' );

		// Check if auto-lock is enabled
		if ( !$autoLockHours || $autoLockHours <= 0 ) {
			return false;
		}

		// Thread is already manually locked
		if ( $this->getClosedTimestamp() > 0 ) {
			return false;
		}

		// Get all activity timestamps (creation, last post/reply, thread edit, replies edit)
		$postedTimestamp = $this->getPostedTimestamp();
		$lastPostTimestamp = $this->getLastPostTimestamp();
		$lastEditTimestamp = $this->getEditedTimestamp();

		// Get the latest edit timestamp from all replies in this thread
		$lastReplyEditTimestamp = $this->getLastReplyEditTimestamp();

		// Determine the most recent activity timestamp (max of all activity types)
		$activityTimestamps = [];
		if ( $postedTimestamp ) {
			$activityTimestamps[] = $postedTimestamp;
		}
		if ( $lastPostTimestamp ) {
			$activityTimestamps[] = $lastPostTimestamp;
		}
		if ( $lastEditTimestamp ) {
			$activityTimestamps[] = $lastEditTimestamp;
		}
		if ( $lastReplyEditTimestamp ) {
			$activityTimestamps[] = $lastReplyEditTimestamp;
		}

		// If no activity timestamp is available, cannot determine inactivity
		if ( !$activityTimestamps ) {
			return false;
		}

		// Use the most recent activity
		$lastActivityTimestamp = max( $activityTimestamps );

		// Calculate inactivity threshold
		$thresholdTimestamp = wfTimestamp( TS_MW, time() - ( $autoLockHours * 3600 ) );

		// Check if the thread is inactive (no activity since threshold)
		if ( $lastActivityTimestamp >= $thresholdTimestamp ) {
			// Thread is still active
			return false;
		}

		// Thread should be locked
		return true;
	}

	/**
	 * Lock this thread (internal method).
	 * Always checks that thread is not already locked to avoid race conditions.
	 * This method performs the actual locking operation and is called by close() and LockInactiveThreadJob.
	 *
	 * @internal This is an internal method. Use close() for user-initiated locks.
	 * @param int|null $actorId Actor ID of user locking the thread (0 or null for system lock)
	 * @param string $userIp IP address of user locking the thread (empty string for system lock)
	 * @return bool True on success, false on failure
	 */
	function doLock( $actorId = 0, $userIp = '' ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$result = $dbw->update(
			'wikiforum_threads',
			[
				'wft_closed_timestamp' => $dbw->timestamp( wfTimestampNow() ),
				'wft_closed_actor' => $actorId ?? 0,
				'wft_closed_user_ip' => $userIp
			],
			[
				'wft_thread' => $this->getId()
			],
			__METHOD__
		);

		if ( $result ) {
			// Update object data to reflect the lock
			$this->data->wft_closed_timestamp = wfTimestampNow();
			$this->data->wft_closed_actor = $actorId ?? 0;
			$this->data->wft_closed_user_ip = $userIp;
		}

		return $result;
	}

	/**
	 * Handle auto-lock for this thread: check conditions and create a job if needed.
	 *
	 * @return bool True if job was created or should be created, false otherwise
	 */
	function handleAutoLock() {
		// Return a cached result if already checked
		if ( $this->shouldAutoLock !== null ) {
			return $this->shouldAutoLock;
		}

		$this->shouldAutoLock = $this->checkAutoLockConditions();

		if ( $this->shouldAutoLock ) {
			// Thread should be locked, create job
			try {
				$jobSpec = new JobSpecification(
					'lockInactiveThread',
					[ 'threadId' => $this->getId() ],
					[ 'removeDuplicates' => true ]
				);
				MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobSpec );
			} catch ( Exception $e ) {
				// Log error or just ignore - the job is a background task
				wfDebug( __METHOD__ . ': Failed to push lockInactiveThread job: ' . $e->getMessage() );
			}
		}

		return $this->shouldAutoLock;
	}

	/**
	 * Show the user and time of the last post in the thread, if there is a post
	 *
	 * @return string
	 */
	function showLastPostInfo() {
		if ( $this->getReplyCount() > 0 ) {
			return WikiForumGui::showByInfo(
				$this->data->wft_last_post_timestamp,
				WikiForum::getUserFromDB( $this->data->wft_last_post_actor, $this->data->wft_last_post_user_ip )
			);
		} else {
			return '';
		}
	}

	/**
	 * Show the user and time of when this thread was first posted
	 *
	 * @return string
	 */
	function showPostedInfo() {
		return WikiForumGui::showPostedInfo(
			$this->data->wft_posted_timestamp,
			$this->getPostedBy()
		);
	}

	/**
	 * Show the user and time of when this thread was first posted, without the link. (Apparently needed for quoting)
	 *
	 * @return string
	 */
	function showPlainPostedInfo() {
		return WikiForumGui::showPlainPostedInfo( $this->data->wft_posted_timestamp, $this->getPostedBy() );
	}

	/**
	 * Show the user and time of when this thread was edited
	 *
	 * @return string
	 */
	function showEditedInfo() {
		return WikiForumGui::showEditedInfo(
			$this->data->wft_edit_timestamp,
			$this->getEditedBy()
		);
	}

	/**
	 * Get the URL to this thread
	 *
	 * NOTE: This returns an unescaped URL. When using in HTML, pass it to Html helpers
	 * which will automatically escape it (e.g., Html::element with 'href' attribute).
	 * For direct string concatenation in HTML, use htmlspecialchars().
	 *
	 * @param int $reply auto scroll to reply, optional
	 * @return string URL (not HTML-escaped)
	 */
	function getURL( $reply = false ) {
		$fragment = '';

		if ( $reply ) {
			$fragment = 'reply_' . $reply;
		}

		return SpecialPage::getTitleFor( 'WikiForum', $this->getName(), $fragment )->getFullURL();
	}

	/**
	 * Get the HTML for a link to this thread
	 *
	 * @param int|bool $reply Optional: Reply to scroll to (through url #fragment)
	 * @return string HTML the link (safe for output)
	 * @return-taint escaped
	 */
	function showLink( $reply = false ) {
		// getName() and getURL() return plain text, Html::element() will escape them
		return Html::element(
			'a',
			[ 'href' => $this->getURL( $reply ) ],
			$this->getName()
		);
	}

	/**
	 * Get the number of replies this thread has
	 *
	 * @return int
	 */
	function getReplyCount() {
		return $this->data->wft_reply_count;
	}

	/**
	 * Get the number of times this thread has been viewed
	 *
	 * @return int
	 */
	function getViewCount() {
		return $this->data->wft_view_count;
	}

	/**
	 * Get the actor ID of the user who originally posted this thread
	 *
	 * @return int
	 */
	function getPostedById() {
		return $this->data->wft_actor;
	}

	/**
	 * Get the user who's edited this thread
	 *
	 * @return User
	 */
	function getEditedBy() {
		return WikiForum::getUserFromDB( $this->data->wft_edit_actor, $this->data->wft_edit_user_ip );
	}

	/**
	 * Get the user who originally posted this thread
	 *
	 * @return User
	 */
	function getPostedBy() {
		return WikiForum::getUserFromDB( $this->data->wft_actor, $this->data->wft_user_ip );
	}

	/**
	 * Get the name/title of this thread
	 *
	 * @return string Plain text (not HTML-escaped)
	 * @return-taint tainted
	 */
	function getName() {
		return $this->data->wft_thread_name;
	}

	/**
	 * Get the actual text of this thread
	 *
	 * @return string Plain text (not HTML-escaped)
	 * @return-taint tainted
	 */
	function getText() {
		return $this->data->wft_text;
	}

	/**
	 * Get this thread's parent forum
	 *
	 * @return WFForum
	 */
	function getForum() {
		if ( !$this->forum ) {
			$this->forum = WFForum::newFromID( $this->data->wft_forum );
		}
		return $this->forum;
	}

	/**
	 * Get this thread's ID number
	 *
	 * @return int id
	 */
	function getId() {
		return $this->data->wft_thread;
	}

	/**
	 * Get the timestamp of when this thread was originally posted
	 *
	 * @return string
	 */
	function getPostedTimestamp() {
		return $this->data->wft_posted_timestamp;
	}

	/**
	 * Get the timestamp of when this thread was edited
	 *
	 * @return string
	 */
	function getEditedTimestamp() {
		return $this->data->wft_edit_timestamp;
	}

	/**
	 * Get the timestamp of when this thread was closed
	 *
	 * @return string
	 */
	function getClosedTimestamp() {
		return $this->data->wft_closed_timestamp;
	}

	/**
	 * Get the timestamp of the last post in this thread
	 *
	 * @return string
	 */
	function getLastPostTimestamp() {
		return $this->data->wft_last_post_timestamp;
	}

	/**
	 * Get the timestamp of the latest edit of any reply in this thread
	 *
	 * @return string Empty string if no replies were edited
	 */
	function getLastReplyEditTimestamp() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$result = $dbr->selectRow(
			'wikiforum_replies',
			[ 'MAX(wfr_edit_timestamp) AS max_edit_timestamp' ],
			[
				'wfr_thread' => $this->getId(),
				'wfr_edit_timestamp > 0',
			],
			__METHOD__
		);

		if ( $result && $result->max_edit_timestamp ) {
			return $result->max_edit_timestamp;
		}

		return '';
	}

	/**
	 * Gets an array of this thread's replies
	 *
	 * @param int|null $limit optional LIMIT (when set, result is not cached)
	 * @param int|null $offset optional OFFSET (used only when $limit is set)
	 * @return WFReply[]
	 */
	function getReplies( $limit = null, $offset = null ) {
		$options = [ 'ORDER BY' => 'wfr_posted_timestamp ASC' ];
		if ( $limit !== null ) {
			$options['LIMIT'] = max( 1, (int)$limit );
			$options['OFFSET'] = max( 0, (int)( $offset ?? 0 ) );
		}

		if ( $limit !== null ) {
			// Paginated request: do not use cache, run fresh query
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
			$sqlReplies = $dbr->select(
				'wikiforum_replies',
				'*',
				[ 'wfr_thread' => $this->getId() ],
				__METHOD__,
				$options
			);
			$replies = [];
			foreach ( $sqlReplies as $sql ) {
				$reply = WFReply::newFromSQL( $sql );
				$reply->thread = $this;
				$replies[] = $reply;
			}
			return $replies;
		}

		if ( !$this->replies ) {
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

			$sqlReplies = $dbr->select(
				'wikiforum_replies',
				'*',
				[ 'wfr_thread' => $this->getId() ],
				__METHOD__,
				$options
			);

			$replies = [];

			foreach ( $sqlReplies as $sql ) {
				$reply = WFReply::newFromSQL( $sql );
				$reply->thread = $this; // saves thread making DB query to find
				$replies[] = $reply;
			}

			$this->replies = $replies;
		}
		return $this->replies;
	}

	/**
	 * Add a reply to this thread
	 *
	 * @param string $text user-supplied reply text
	 * @return bool true if success, or false on failure
	 */
	function addReply( $text ) {
		return WFReply::add( $this, $text );
	}

	/**
	 * Deletes the thread
	 *
	 * @return string HTML
	 */
	function delete() {
		$request = $this->getRequest();
		$user = $this->getUser();

		if (
			$user->isAnon() ||
			( $user->getActorId() != $this->getPostedBy()->getActorId() && !$user->isAllowed( 'wikiforum-moderator' ) )
		) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-delete', 'sessionfailure' );
			return $error . $this->show();
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->delete(
			'wikiforum_threads',
			[ 'wft_thread' => $this->getId() ],
			__METHOD__
		);

		// Update threads/replies counters
		$replyCount = $this->getReplyCount();

		// When the thread we're about to delete is deleted, we also need
		// to update the information about the latest post & its author
		$row = $dbw->selectRow(
			'wikiforum_threads',
			[
				'wft_last_post_actor',
				'wft_last_post_user_ip',
				'wft_last_post_timestamp',
			],
			[ 'wft_forum' => $this->getForum()->getId() ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		// Update the forum table so that the data shown on Special:WikiForum is up to date
		$lastPostTimestamp = $row->wft_last_post_timestamp ?? null;
		// Use set() with numeric keys for SQL expressions (safe because $replyCount is int)
		$dbw->update(
			'wikiforum_forums',
			[
				"wff_reply_count = wff_reply_count - " . (int)$replyCount,
				'wff_thread_count = wff_thread_count - 1',
				'wff_last_post_actor' => $row->wft_last_post_actor ?? null,
				'wff_last_post_user_ip' => $row->wft_last_post_user_ip ?? '',
				'wff_last_post_timestamp' => $lastPostTimestamp ? $dbw->timestamp( $lastPostTimestamp ) : ''
			],
			[ 'wff_forum' => $this->getForum()->getId() ],
			__METHOD__
		);

		return $this->getForum()->show();
	}

	/**
	 * Reopens the thread
	 *
	 * @return string HTML
	 */
	function reopen() {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-moderator' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-thread-reopen', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-thread-reopen', 'sessionfailure' );
			return $error . $this->show();
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$now = wfTimestampNow();
		$result = $dbw->update(
			'wikiforum_threads',
			[
				'wft_closed_timestamp' => '',
				'wft_closed_actor' => 0,
				// Update last post timestamp to current time so thread is considered active after reopening
				'wft_last_post_timestamp' => $dbw->timestamp( $now )
			],
			[ 'wft_thread' => $this->getId() ],
			__METHOD__
		);

		$this->data->wft_closed_timestamp = 0;
		$this->data->wft_closed_actor = 0;
		$this->data->wft_last_post_timestamp = $now;

		return $this->show();
	}

	/**
	 * Closes the thread
	 *
	 * @return string HTML
	 */
	function close() {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-moderator' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-thread-close', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-thread-close', 'sessionfailure' );
			return $error . $this->show();
		}

		$this->doLock(
			$user->getActorId(),
			$request->getIP()
		);

		return $this->show();
	}

	/**
	 * Make the thread sticky
	 *
	 * @return string HTML -- whatever sticky() returned
	 */
	function makeSticky() {
		return $this->sticky( 1 );
	}

	/**
	 * Stop the thread being sticky
	 *
	 * @return string HTML -- whatever sticky() returned
	 */
	function removeSticky() {
		return $this->sticky( 0 );
	}

	/**
	 * Changes the thread's sticky value. Do not use, use makeSticky() and removeSticky() instead
	 *
	 * @param int $value 0/1 (for db)
	 * @return string HTML
	 */
	private function sticky( $value ) {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-sticky', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		/* This actually won't work because there _isn't_ a form involved w/ this request...
		it's just a pure GET with the appropriate URL parameter(s) set. So we need to change
		that to make this part of the codebase /truly/ immune to CSRF.
		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-sticky', 'sessionfailure' );
			return $error . $this->show();
		}
		*/

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$result = $dbw->update(
			'wikiforum_threads',
			[ 'wft_sticky' => $value ],
			[ 'wft_thread' => $this->getId() ],
			__METHOD__
		);

		$this->data->wft_sticky = $value;

		return $this->show();
	}

	/**
	 * Edit the title and/or text of the thread
	 *
	 * @param string $title user supplied new title
	 * @param string $text user supplied new text
	 * @return string HTML
	 */
	function edit( $title, $text ) {
		$request = $this->getRequest();
		$user = $this->getUser();

		$title = trim( $title );

		if (
			( $text && $title && strlen( $text ) == 1 ) ||
			strlen( $title ) == 1
		) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-text-or-title' );
			return $error . $this->show();
		}

		// Catch characters that would be invalid for the purposes of a page title and prevent people from
		// using those in thread titles, just as you'd prevent them elsewhere in MW.
		// Thread titles are used in URLs via SpecialPage::getTitleFor(), so they must be valid page titles
		// @see https://phabricator.wikimedia.org/T384146
		$titleObj = Title::newFromText( $title );
		if ( $titleObj === null ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-bad-title' );
			return $error . $this->show();
		}

		$oldForum = $this->getForum();
		$oldForumId = $oldForum->getId();
		$newForumId = $request->getInt( 'forum', $oldForumId );
		$isMovingThread = ( $newForumId != $oldForumId );

		// Validate new forum exists if moving
		if ( $isMovingThread ) {
			$newForum = WFForum::newFromID( $newForumId );
			if ( !$newForum ) {
				$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-forum-not-found' );
				return $error . $this->show();
			}
		}

		if ( $this->getName() == $title && $this->getText() == $text && !$isMovingThread ) {
			return $this->show(); // nothing to do
		}

		if (
			$user->isAnon() ||
			(
				$user->getActorId() != $this->getPostedById() &&
				!$user->isAllowed( 'wikiforum-moderator' )
			)
		) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-general-title', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}

		// Security check: only moderators can move threads
		// (prevents manual parameter manipulation even if dropdown is hidden)
		if ( $isMovingThread && !$user->isAllowed( 'wikiforum-moderator' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'sessionfailure' );
			return $error . $this->show();
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		// Update thread data
		$updateData = [
			'wft_thread_name' => $title,
			'wft_text' => $text,
			'wft_edit_timestamp' => $dbw->timestamp( wfTimestampNow() ),
			'wft_edit_actor' => $user->getActorId(),
			'wft_edit_user_ip' => $request->getIP(),
		];

		// If moving thread, update forum reference
		if ( $isMovingThread ) {
			$updateData['wft_forum'] = $newForumId;
		}

		$result = $dbw->update(
			'wikiforum_threads',
			$updateData,
			[ 'wft_thread' => $this->getId() ],
			__METHOD__
		);

		// Update forum counters if thread was moved
		if ( $isMovingThread ) {
			$replyCount = $this->getReplyCount();

			// Decrease counters in old forum (exclude the thread being moved)
			$oldForumLastPost = $dbw->selectRow(
				'wikiforum_threads',
				[
					'wft_last_post_actor',
					'wft_last_post_user_ip',
					'wft_last_post_timestamp',
				],
				[ 'wft_forum' => $oldForumId, 'wft_thread <> ' . $dbw->addQuotes( $this->getId() ) ],
				__METHOD__,
				[ 'ORDER BY' => 'wft_last_post_timestamp DESC', 'LIMIT' => 1 ]
			);

			$oldForumLastPostTimestamp = $oldForumLastPost ? $oldForumLastPost->wft_last_post_timestamp : null;
			// Use set() with numeric keys for SQL expressions (safe because $replyCount is int)
			$dbw->update(
				'wikiforum_forums',
				[
					"wff_reply_count = wff_reply_count - " . (int)$replyCount,
					'wff_thread_count = wff_thread_count - 1',
					'wff_last_post_actor' => $oldForumLastPost ? $oldForumLastPost->wft_last_post_actor : null,
					'wff_last_post_user_ip' => $oldForumLastPost ? $oldForumLastPost->wft_last_post_user_ip : '',
					'wff_last_post_timestamp' => $oldForumLastPostTimestamp ? $dbw->timestamp( $oldForumLastPostTimestamp ) : ''
				],
				[ 'wff_forum' => $oldForumId ],
				__METHOD__
			);

			// Increase counters in new forum (thread is already moved at this point)
			$newForumLastPost = $dbw->selectRow(
				'wikiforum_threads',
				[
					'wft_last_post_actor',
					'wft_last_post_user_ip',
					'wft_last_post_timestamp',
				],
				[ 'wft_forum' => $newForumId ],
				__METHOD__,
				[ 'ORDER BY' => 'wft_last_post_timestamp DESC', 'LIMIT' => 1 ]
			);

			$newForumLastPostTimestamp = $newForumLastPost ? $newForumLastPost->wft_last_post_timestamp : null;
			// Use set() with numeric keys for SQL expressions (safe because $replyCount is int)
			$dbw->update(
				'wikiforum_forums',
				[
					"wff_reply_count = wff_reply_count + " . (int)$replyCount,
					'wff_thread_count = wff_thread_count + 1',
					'wff_last_post_actor' => $newForumLastPost ? $newForumLastPost->wft_last_post_actor : null,
					'wff_last_post_user_ip' => $newForumLastPost ? $newForumLastPost->wft_last_post_user_ip : '',
					'wff_last_post_timestamp' => $newForumLastPostTimestamp ? $dbw->timestamp( $newForumLastPostTimestamp ) : ''
				],
				[ 'wff_forum' => $newForumId ],
				__METHOD__
			);

			// Update thread's forum reference in object
			$this->data->wft_forum = $newForumId;
			$this->forum = null; // Reset cached forum so it will be reloaded
		}

		$this->data->wft_thread_name = $title;
		$this->data->wft_text = $text;
		$this->data->wft_edit_timestamp = wfTimestampNow();
		$this->data->wft_edit_actor = $user->getActorId();
		$this->data->wft_edit_user_ip = $request->getIP();

		return $this->show();
	}

	/**
	 * Get a descriptive icon for the current thread.
	 * "New" icon for new threads (threads that are not over $dayDefinitionNew
	 * days old), sticky icon for stickied threads,
	 * locked icon for locked threads and an ordinary thread icon for
	 * everything else.
	 *
	 * @return string HTML img tag
	 */
	function getIcon() {
		// Threads that are this many days old or newer are considered "new"
		$dayDefinitionNew = intval( $this->msg( 'wikiforum-day-definition-new' )->inContentLanguage()->plain() );

		$olderTimestamp = wfTimestamp( TS_MW, strtotime( '-' . $dayDefinitionNew . ' days' ) );

		if ( $this->isSticky() ) {
			return WikiForum::getIconHTML( 'wikiforum-sticky' );
		} elseif ( $this->isClosed() ) {
			return WikiForum::getIconHTML( 'wikiforum-thread-closed' );
		} elseif ( $this->getPostedTimestamp() > $olderTimestamp ) {
			return WikiForum::getIconHTML( 'wikiforum-new-thread' );
		} else {
			return WikiForum::getIconHTML( 'wikiforum-thread' );
		}
	}

	/**
	 * Show this thread, with headers, replies, frames, et al.
	 *
	 * @return string HTML of thread
	 */
	function show() {
		$request = $this->getRequest();
		$out = $this->getOutput();
		$user = $this->getUser();

		// Load JavaScript for close/reopen links (CSRF-safe POST submission)
		$out->addModules( 'ext.wikiForum.close-reopen-links' );

		$output = '';

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$menuLink = '';

		if ( $user->isAllowed( 'wikiforum-admin' ) ) {
			// @see T312733
			$out->addModules( 'ext.wikiForum.admin-sticky-links' );

			if ( $this->isSticky() ) {
				$menuLink =
					WikiForum::getIconHTML( 'wikiforum-remove-sticky' ) .
					' ' .
					Html::element(
						'a',
						[
							'href' => $specialPage->getFullURL( [ 'wfaction' => 'removesticky', 'thread' => $this->getId() ] ),
							'class' => 'wikiforum-thread-remove-sticky',
							'data-wikiforum-thread-id' => $this->getId(),
						],
						$this->msg( 'wikiforum-remove-sticky' )->text()
					) .
					' ';
			} else {
				$menuLink =
					WikiForum::getIconHTML( 'wikiforum-make-sticky' ) .
					' ' .
					Html::element(
						'a',
						[
							'href' => $specialPage->getFullURL( [ 'wfaction' => 'makesticky', 'thread' => $this->getId() ] ),
							'class' => 'wikiforum-thread-make-sticky',
							'data-wikiforum-thread-id' => $this->getId(),
						],
						$this->msg( 'wikiforum-make-sticky' )->text()
					) .
					' ';
			}
		}

		// Add delete links module for threads and replies
		$out->addModules( 'ext.wikiForum.delete-links' );

		// Replying is only possible to open threads
		if ( !$this->isClosed() ) {
			$menuLink .=
				WikiForum::getIconHTML( 'wikiforum-write-reply' ) .
				' ' .
				Html::element(
					'a',
					[ 'href' => '#writereply' ],
					$this->msg( 'wikiforum-write-reply' )->text()
				);
		}

		$output .= WikiForumGui::showSearchbox();

		if ( $this->isClosed() ) {
			$output .= WikiForum::showErrorMessage( 'wikiforum-thread-closed', 'wikiforum-error-thread-closed', 'lock.png' );
		}

		$output .= WikiForumGui::showHeaderRow( $this->showHeaderLinks(), $user, $menuLink );

		// Add topic name to the title
		$out->setPageTitle( $this->msg( 'wikiforum-topic-name', $this->getName() )->text() );
		$out->setHTMLTitle( $this->msg( 'wikiforum-topic-name', $this->getName() )->text() );

		$output .= $this->showHeader();

		// limiting
		$maxPerPage = intval( $this->msg( 'wikiforum-max-replies-per-page' )->inContentLanguage()->plain() );
		$currentPage = max( 1, $request->getInt( 'page', 1 ) );

		if ( $maxPerPage > 0 ) {
			$offset = ( $currentPage - 1 ) * $maxPerPage;
			$replies = $this->getReplies( $maxPerPage, $offset );
		} else {
			$replies = $this->getReplies();
		}

		foreach ( $replies as $reply ) {
			$output .= $reply->show();
		}

		$output .= $this->showFooter();

		if ( $maxPerPage > 0 ) {
			$replyCount = (int)$this->getReplyCount();
			$output .= WikiForumGui::showFooterRow(
				$currentPage,
				$replyCount,
				$maxPerPage,
				[ 'thread' => $this->getId() ]
			);
		}

		if ( !$this->isClosed() ) {
			$quoteReply = $request->getInt( 'quotereply', 0 );
			$quoteThread = $request->getBool( 'quotethread', false );
			$output .= $this->showNewReplyForm( $quoteReply, $quoteThread );
		}

		if ( !MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			$dbw->update(
				'wikiforum_threads',
				[ 'wft_view_count = wft_view_count + 1' ],
				[ 'wft_thread' => $this->getId() ],
				__METHOD__
			);
		}

		return $output;
	}

	/**
	 * Show an item (row) for a list (table), for this thread. Used on forum pages and the <WikiForumList> tag
	 * Do not use. Use showListItem() and showTagListItem() below.
	 *
	 * @param bool $ignoreSticky don't use CSS classes to highlight sticky threads
	 * @param string $extraInfo any extra information to show after the posted info
	 * @return string
	 */
	private function showListItemMain( $ignoreSticky = false, $extraInfo = '' ) {
		$desc = Html::rawElement(
			'p',
			[ 'class' => 'mw-wikiforum-thread' ],
			(
				$this->getIcon() . ' ' . $this->showLink() .
				Html::rawElement(
					'p',
					[ 'class' => 'mw-wikiforum-descr' ],
					$this->showPostedInfo() . $extraInfo
				)
			)
		);

		$isSticky = !$ignoreSticky && $this->isSticky();
		$output =
			Html::openElement(
				'tr',
				[ 'class' => ( $isSticky ? 'mw-wikiforum-sticky' : 'mw-wikiforum-normal' ) ]
			) .
			Html::rawElement(
				'td',
				[ 'class' => 'mw-wikiforum-title' ],
				$desc
			) .
			Html::element(
				'td',
				[ 'class' => 'mw-wikiforum-value' ],
				(string)$this->getReplyCount()
			) .
			Html::element(
				'td',
				[ 'class' => 'mw-wikiforum-value' ],
				(string)$this->getViewCount()
			) .
			Html::rawElement(
				'td',
				[ 'class' => 'mw-wikiforum-value' ],
				$this->showLastPostInfo()
			) .
			Html::closeElement( 'tr' );

		return $output;
	}

	/**
	 * Show a row for this thread, for a forum page
	 *
	 * @return string
	 */
	function showListItem() {
		return $this->showListItemMain();
	}

	/**
	 * Show a row for this thread, for the <WikiForumList> tag
	 *
	 * @return string
	 */
	function showTagListItem() {
		$categoryLink = $this->getForum()->getCategory()->showLink();
		$forumLink = $this->getForum()->showPlainLink();

		$extraInfo = '<br />' . $this->msg( 'wikiforum-forum' )->rawParams( $categoryLink, $forumLink )->parse();

		return $this->showListItemMain( true, $extraInfo );
	}

	/**
	 * Shows the edit/delete buttons for the topic author, moderators and also
	 * close (lock) and reopen (unlock) buttons for moderators.
	 *
	 * @return string HTML
	 */
	function showButtons() {
		$user = $this->getUser();

		$editButtons = '';

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$editButtons .= Html::rawElement(
			'a',
			[ 'href' => $specialPage->getFullURL( [ 'thread' => $this->getId(), 'quotethread' => $this->getId() ] ) . '#writereply' ],
			WikiForum::getIconHTML( 'wikiforum-quote' )
		);

		$forum = $this->getForum();

		if (
			$user->getActorId() == $this->getPostedById() ||
			$user->isAllowed( 'wikiforum-moderator' )
		) {
			$editButtons .= ' ' . Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'wfaction' => 'editthread', 'thread' => $this->getId() ] ) ],
				WikiForum::getIconHTML( 'wikiforum-edit-thread' )
			);
			$editButtons .= ' ' . Html::rawElement(
				'a',
				[
					'href' => '#',
					'class' => 'wikiforum-delete-thread-link',
					'data-wikiforum-thread-id' => $this->getId(),
					'role' => 'button',
					'title' => $this->msg( 'wikiforum-delete-thread' )->text()
				],
				WikiForum::getIconHTML( 'wikiforum-delete-thread' )
			);

			// Only moderators can lock and reopen threads
			if ( $user->isAllowed( 'wikiforum-moderator' ) ) {
				if ( !$this->isClosed() ) {
					$editButtons .= ' ' . Html::rawElement(
						'a',
						[
							'href' => $specialPage->getFullURL( [ 'wfaction' => 'closethread', 'thread' => $this->getId() ] ),
							'class' => 'wikiforum-close-thread-link',
							'data-wikiforum-thread-id' => $this->getId(),
							'role' => 'button',
							'title' => $this->msg( 'wikiforum-close-thread' )->text()
						],
						WikiForum::getIconHTML( 'wikiforum-close-thread' )
					);
				} else {
					$editButtons .= ' ' . Html::rawElement(
						'a',
						[
							'href' => $specialPage->getFullURL( [ 'wfaction' => 'reopenthread', 'thread' => $this->getId() ] ),
							'class' => 'wikiforum-reopen-thread-link',
							'data-wikiforum-thread-id' => $this->getId(),
							'role' => 'button',
							'title' => $this->msg( 'wikiforum-reopen-thread' )->text()
						],
						WikiForum::getIconHTML( 'wikiforum-reopen-thread' )
					);
				}
			}
		}

		return $editButtons;
	}

	/**
	 * Check whether a thread with the given title exists
	 *
	 * @param string $title
	 * @return bool
	 */
	static function titleExists( $title ) {
		return self::newFromName( $title ) == true;
	}

	/**
	 * Add a new thread
	 *
	 * @param WFForum $forum forum to add thread to
	 * @param string $title thread title
	 * @param string $text thread text
	 * @return string HTML
	 */
	static function add( WFForum $forum, $title, $text ) {
		global $wgWikiForumAllowAnonymous, $wgWikiForumLogInRC;

		$request = $forum->getRequest();
		$user = $forum->getUser();

		if ( !$wgWikiForumAllowAnonymous && $user->isAnon() ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
		}

		$title = trim( $title );

		if ( strlen( $text ) == 0 || strlen( $title ) == 0 ) { // show form again, return it
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . $forum->showNewThreadForm( $title, $text );
		}

		if ( self::titleExists( $title ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-title-already-exists' );
		}

		// Thread titles are used in URLs via SpecialPage::getTitleFor(), so they must be valid page titles
		// @see https://phabricator.wikimedia.org/T384146
		if ( preg_replace( '/[' . Title::legalChars() . ']/', '', $title ) ) { // removes all legal chars, then sees if string has length
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-bad-title' );
		}

		if ( $forum->isAnnouncement() && !$user->isAllowed( 'wikiforum-moderator' ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
		}

		if ( WikiForum::useCaptcha( $user ) ) {
			$captcha = ConfirmEditHooks::getInstance();
			$captcha->setTrigger( 'wikiforum' );
			if ( !$captcha->passCaptchaFromRequest( $request, $user ) ) {
				$output = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-captcha' );
				$output .= self::showGeneralEditor(
					$title,
					'',
					$text,
					[
						'wfaction' => 'savenewthread',
						'forum' => $forum->getId()
					],
					$user
				);
				return $output;
			}
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'sessionfailure' );
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$timestamp = wfTimestampNow();

		$result = $dbw->insert(
			'wikiforum_threads',
			[
				'wft_thread_name' => $title,
				'wft_text' => $text,
				'wft_posted_timestamp' => $dbw->timestamp( $timestamp ),
				'wft_actor' => $user->getActorId(),
				'wft_user_ip' => $request->getIP(),
				'wft_forum' => $forum->getId(),
				'wft_last_post_timestamp' => $dbw->timestamp( $timestamp )
			],
			__METHOD__
		);

		$thread = self::newFromName( $title );
		$thread->forum = $forum; // saves an extra DB query

		$dbw->update( // update thread counters
			'wikiforum_forums',
			[
				'wff_thread_count = wff_thread_count + 1',
				'wff_last_post_actor' => $user->getActorId(),
				'wff_last_post_user_ip' => $request->getIP(),
				'wff_last_post_timestamp' => $dbw->timestamp( $timestamp )
			],
			[ 'wff_forum' => $forum->getId() ],
			__METHOD__
		);

		$logEntry = new ManualLogEntry( 'forum', 'add-thread' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiForum' ) );
		$shortText = $forum->getLanguage()->truncateForDatabase( $text, 50 );
		$logEntry->setComment( $shortText );
		$logEntry->setParameters( [
			'4::thread-name' => $title
		] );
		$logid = $logEntry->insert();
		if ( $wgWikiForumLogInRC ) {
			$logEntry->publish( $logid );
		}

		if ( $result ) {
			return $thread->show();
		} else {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-general' );
		}
	}

	// GUI METHODS

	/**
	 * Show the header row links - the breadcrumb navigation
	 * (Overview > Category name > Forum > Thread)
	 *
	 * @return string
	 */
	function showHeaderLinks() {
		$specialPageObj = SpecialPage::getTitleFor( 'WikiForum' );

		$output = $this->getForum()->showHeaderLinks();

		return $output . ' &gt; ' . $this->showLink();
	}

	/**
	 * Get the thread header. This is used only for the starting post.
	 *
	 * @return string
	 */
	function showHeader() {
		$posted = $this->showPostedInfo();
		if ( $this->getEditedTimestamp() > 0 ) {
			$posted .= '<br /><i>' . $this->showEditedInfo() . '</i>';
		}

		return '<div class="mw-wikiforum-frame">
				<table class="mw-wikiforum-thread-list">
					<tr>
						<th class="mw-wikiforum-thread-top" style="text-align: right;">[#' . $this->getId() . ']</th>
					</tr>
					<tr>
						<td class="mw-wikiforum-thread-main" colspan="2">' . WikiForum::showAvatar( $this->getPostedBy() ) .
							WikiForum::parseIt( $this->getText() ) . WikiForumGui::showBottomLine( $posted, $this->getUser(), $this->showButtons() ) . '
						</td>
					</tr>';
	}

	function showHeaderForSearch() {
		$posted = $this->showPostedInfo();
		$posted .= '<br />' . $this->msg( 'wikiforum-search-thread' )->rawParams( $this->showLink() )->parse();

		return '<tr>
					<td class="mw-wikiforum-thread-main" colspan="2">' . WikiForum::showAvatar( $this->getPostedBy() ) .
						WikiForum::parseIt( $this->getText() ) . WikiForumGui::showBottomLine( $posted, $this->getUser() ) . '
					</td>
				</tr>';
	}

	/**
	 * Show the thread's footer
	 *
	 * @return string
	 */
	function showFooter() {
		return '</table></div>';
	}

	/**
	 * Show the editor for this thread
	 *
	 * @return string
	 */
	function showEditForm() {
		$currentForum = $this->getForum();
		return self::showGeneralEditor(
			$this->getName(),
			'',
			$this->getText(),
			[
				'wfaction' => 'savethread',
				'thread' => $this->getId()
			],
			$this->getUser(),
			$currentForum ? $currentForum->getId() : null
		);
	}

	/**
	 * Show the editor for adding a thread/editing one
	 *
	 * @param string $titleValue Value to preload the title input with. Will be escaped by Html::element().
	 * @param-taint $titleValue escapes_html
	 * @param string $titlePlaceholder The placeholder text for the title input. Will be escaped by Html::element().
	 *                                  Should be plain text from a message, not pre-escaped HTML.
	 * @param-taint $titlePlaceholder escapes_html
	 * @param string $textValue Value to preload the text field with. Will be escaped by Html::element().
	 * @param-taint $textValue escapes_html
	 * @param array $params Array of URL params to pass to the form
	 * @param User $user
	 * @param int|null $currentForumId Current forum ID for thread (null for new threads)
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	static function showGeneralEditor( $titleValue, $titlePlaceholder, $textValue, $params, User $user, $currentForumId = null ) {
		$input =
			Html::rawElement( 'div', [],
				Html::element(
					'input',
					[
						'type' => 'text',
						'name' => 'name',
						'class' => 'mw-wikiforum-title-input',
						'placeholder' => $titlePlaceholder,
						'value' => $titleValue,
					]
				)
			);

		// Add forum dropdown for moderators and administrators when editing existing thread
		if ( $currentForumId !== null && ( $user->isAllowed( 'wikiforum-moderator' ) || $user->isAllowed( 'wikiforum-admin' ) ) ) {
			$forumsGrouped = self::getAllForumsGrouped();
			$optionElements = [];
			foreach ( $forumsGrouped as $categoryId => $categoryData ) {
				// Html::element() will escape the content automatically, so don't escape here
				$categoryName = $categoryData['name'];
				foreach ( $categoryData['forums'] as $forumId => $forumName ) {
					$displayName = $categoryName . ' > ' . $forumName;
					$optionAttrs = [ 'value' => $forumId ];
					if ( $forumId == $currentForumId ) {
						$optionAttrs['selected'] = 'selected';
					}
					$optionElements[] = Html::element( 'option', $optionAttrs, $displayName );
				}
			}

			$forumSelect = Html::rawElement( 'div', [ 'class' => 'mw-wikiforum-forum-select' ],
				Html::rawElement( 'label', [ 'for' => 'mw-wikiforum-forum-select' ],
					wfMessage( 'wikiforum-move-thread' )->escaped() . ': '
				) .
				Html::rawElement( 'select',
					[
						'name' => 'forum',
						'id' => 'mw-wikiforum-forum-select',
						'class' => 'mw-wikiforum-forum-select'
					],
					implode( '', $optionElements )
				)
			);
			$input .= $forumSelect;
		}

		return WikiForumGui::showWriteForm( true, $params, $input, 'mw-wikiforum-edit-thread', $textValue, 'wikiforum-save-thread', $user );
	}

	/**
	 * Get all forums grouped by categories for dropdown selection
	 *
	 * @return array Array with structure: [category_id => ['name' => category_name, 'forums' => [forum_id => forum_name]]]
	 */
	static function getAllForumsGrouped() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		// Get all categories
		$sqlCategories = $dbr->select(
			'wikiforum_category',
			'*',
			[],
			__METHOD__,
			[ 'ORDER BY' => 'wfc_sortkey ASC, wfc_category ASC' ]
		);

		$result = [];
		foreach ( $sqlCategories as $catRow ) {
			$category = WFCategory::newFromSQL( $catRow );
			$result[$category->getId()] = [
				'name' => $category->getName(),
				'forums' => []
			];
		}

		// Get all forums
		$sqlForums = $dbr->select(
			'wikiforum_forums',
			'*',
			[],
			__METHOD__,
			[ 'ORDER BY' => 'wff_sortkey ASC, wff_forum ASC' ]
		);

		foreach ( $sqlForums as $forumRow ) {
			$forum = WFForum::newFromSQL( $forumRow );
			$categoryId = $forum->getCategory()->getId();
			if ( isset( $result[$categoryId] ) ) {
				$result[$categoryId]['forums'][$forum->getId()] = $forum->getName();
			}
		}

		return $result;
	}

	/**
	 * Show the editor for adding a new reply to this thread
	 *
	 * @param int $quoteReply the ID of the reply to quote in the editor. 0 for not quoting a reply
	 * @param bool $quoteThread true-quote this thread in the editor. false-don't
	 * @return string HTML the editor
	 */
	function showNewReplyForm( $quoteReply, $quoteThread ) {
		$textValue = '';

		if ( $quoteReply ) {
			$reply = WFReply::newFromID( $quoteReply );
			if ( $reply ) {
				$posted = $reply->showPlainPostedInfo();
				// $textValue contains HTML from showPlainPostedInfo() and plain text from getText()
				// showWriteForm will escape $text_prev parameter, so this is safe
				$textValue = '[quote=' . $posted . ']' . $reply->getText() . '[/quote]';
			}
		} elseif ( $quoteThread ) {
			$posted = $this->showPlainPostedInfo();
			// $textValue contains HTML from showPlainPostedInfo() and plain text from getText()
			// showWriteForm will escape $text_prev parameter, so this is safe
			$textValue = '[quote=' . $posted . ']' . $this->getText() . '[/quote]';
		} elseif ( $this->preloadText ) {
			$textValue = $this->preloadText;
		}

		// showWriteForm escapes $text_prev parameter, so mixed HTML/text in $textValue is safe
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped showWriteForm escapes $text_prev
		return WFReply::showGeneralEditor(
			[
				'wfaction' => 'savenewreply',
				'thread' => $this->getId()
			],
			$this->getUser(),
			$textValue
		);
	}

}
