<?php

class WFThread extends ContextSource {

	private $data;
	public $forum;
	private $replies;
	public $preloadText;

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
		$dbr = wfGetDB( DB_REPLICA );

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

		$dbr = wfGetDB( DB_REPLICA );
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
		return $this->data->wft_closed_timestamp > 0;
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
	 * @param int $reply auto scroll to reply, optional
	 * @return string
	 */
	function getURL( $reply = false ) {
		$fragment = '';

		if ( $reply ) {
			$fragment = 'reply_' . $reply;
		}

		return htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum', $this->getName(), $fragment )->getFullURL() );
	}

	/**
	 * Get the HTML for a link to this thread
	 *
	 * @param int|bool $reply Optional: Reply to scroll to (through url #fragment)
	 * @return string HTML the link
	 */
	function showLink( $reply = false ) {
		return '<a href="' . $this->getURL() . '">' . $this->getName() . '</a>';
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
	 * @return string
	 */
	function getName() {
		return $this->data->wft_thread_name;
	}

	/**
	 * Get the actual text of this thread
	 *
	 * @return string
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
	 * Gets an array of this thread's replies
	 *
	 * @return WFReply[]
	 */
	function getReplies() {
		if ( !$this->replies ) {
			$dbr = wfGetDB( DB_REPLICA );

			$sqlReplies = $dbr->select(
				'wikiforum_replies',
				'*',
				[ 'wfr_thread' => $this->getId() ],
				__METHOD__,
				[ 'ORDER BY' => 'wfr_posted_timestamp ASC' ]
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
		$user = $this->getUser();

		if (
			$user->isAnon() ||
			( $user->getActorId() != $this->getPostedBy()->getActorId() && !$user->isAllowed( 'wikiforum-moderator' ) )
		) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		$dbw = wfGetDB( DB_MASTER );
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
		$dbw->update(
			'wikiforum_forums',
			[
				"wff_reply_count = wff_reply_count - $replyCount",
				'wff_thread_count = wff_thread_count - 1',
				'wff_last_post_actor' => $row->wft_last_post_actor ?? null,
				'wff_last_post_user_ip' => $row->wft_last_post_user_ip ?? null,
				'wff_last_post_timestamp' => $dbw->timestampOrNull( $row->wft_last_post_timestamp ?? null )
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
		if ( !$this->getUser()->isAllowed( 'wikiforum-moderator' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-thread-reopen', 'wikiforum-error-general' );
			return $error . $this->show();
		}
		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->update(
			'wikiforum_threads',
			[
				'wft_closed_timestamp' => '',
				'wft_closed_actor' => 0
			],
			[ 'wft_thread' => $this->getId() ],
			__METHOD__
		);

		$this->data->wft_closed_timestamp = 0;
		$this->data->wft_closed_actor = 0;

		return $this->show();
	}

	/**
	 * Closes the thread
	 *
	 * @return string HTML
	 */
	function close() {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-moderator' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-thread-close', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->update(
			'wikiforum_threads',
			[
				'wft_closed_timestamp' => $dbw->timestamp( wfTimestampNow() ),
				'wft_closed_actor' => $user->getActorId(),
				'wft_closed_user_ip' => $this->getRequest()->getIP()
			],
			[ 'wft_thread' => $this->getId() ],
			__METHOD__
		);

		$this->data->wft_closed_timestamp = wfTimestampNow();
		$this->data->wft_closed_actor = $user->getActorId();
		$this->data->wft_closed_user_ip = $this->getRequest()->getIP();

		return $this->show();
	}

	/**
	 * Make the thread sticky
	 *
	 * @return bool success?
	 */
	function makeSticky() {
		return $this->sticky( 1 );
	}

	/**
	 * Stop the thread being sticky
	 *
	 * @return bool success?
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
		if ( !$this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-sticky', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		$dbw = wfGetDB( DB_MASTER );
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
		$user = $this->getUser();

		if (
			$text && $title && strlen( $text ) == 1 ||
			strlen( $title ) == 1
		) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-text-or-title' );
			return $error . $this->showEditor();
		}

		if ( $this->getName() == $title && $this->getText() == $text ) {
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

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->update(
			'wikiforum_threads',
			[
				'wft_thread_name' => $title,
				'wft_text' => $text,
				'wft_edit_timestamp' => $dbw->timestamp( wfTimestampNow() ),
				'wft_edit_actor' => $user->getActorId(),
				'wft_edit_user_ip' => $this->getRequest()->getIP(),
			],
			[ 'wft_thread' => $this->getId() ],
			__METHOD__
		);

		$this->data->wft_thread_name = $title;
		$this->data->wft_text = $text;
		$this->data->wft_edit_timestamp = wfTimestampNow();
		$this->data->wft_edit_actor = $user->getActorId();
		$this->data->wft_edit_user_ip = $this->getRequest()->getIP();

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
		$extensionAssetsPath = $this->getConfig()->get( 'ExtensionAssetsPath' );

		// Threads that are this many days old or newer are considered "new"
		$dayDefinitionNew = intval( $this->msg( 'wikiforum-day-definition-new' )->inContentLanguage()->plain() );

		$olderTimestamp = wfTimestamp( TS_MW, strtotime( '-' . $dayDefinitionNew . ' days' ) );

		$imagePath = $extensionAssetsPath . '/WikiForum/resources/images';
		if ( $this->isSticky() ) {
			return '<img src="' . $imagePath . '/tag_blue.png" title="' . $this->msg( 'wikiforum-sticky' )->text() . '" /> ';
		} elseif ( $this->isClosed() ) {
			return '<img src="' . $imagePath . '/lock.png" title="' . $this->msg( 'wikiforum-thread-closed' )->text() . '" /> ';
		} elseif ( $this->getPostedTimestamp() > $olderTimestamp ) {
			return '<img src="' . $imagePath . '/new.png" title="' . $this->msg( 'wikiforum-new-thread' )->text() . '" /> ';
		} else {
			return '<img src="' . $imagePath . '/note.png" title="' . $this->msg( 'wikiforum-thread' )->text() . '" /> ';
		}
	}

	/**
	 * Show this thread, with headers, replies, frames, et al.
	 *
	 * @return string HTML of thread
	 */
	function show() {
		$extensionAssetsPath = $this->getConfig()->get( 'ExtensionAssetsPath' );
		$request = $this->getRequest();
		$out = $this->getOutput();
		$user = $this->getUser();

		$output = '';

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$menuLink = '';

		if ( $user->isAllowed( 'wikiforum-admin' ) ) {
			if ( $this->isSticky() ) {
				$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/tag_blue_delete.png" title="' . $this->msg( 'wikiforum-remove-sticky' )->text() . '" /> ';
				$menuLink = $icon . '<a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'removesticky', 'thread' => $this->getId() ] ) ) . '">' .
					$this->msg( 'wikiforum-remove-sticky' )->text() . '</a> ';
			} else {
				$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/tag_blue_add.png" title="' . $this->msg( 'wikiforum-make-sticky' )->text() . '" /> ';
				$menuLink = $icon . '<a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'makesticky', 'thread' => $this->getId() ] ) ) . '">' .
					$this->msg( 'wikiforum-make-sticky' )->text() . '</a> ';
			}
		}

		$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/comment_add.png" title="' . $this->msg( 'wikiforum-write-reply' )->text() . '" /> ';
		// Replying is only possible to open threads
		if ( !$this->isClosed() ) {
			$menuLink .= $icon . '<a href="#writereply">' . $this->msg( 'wikiforum-write-reply' )->text() . '</a>';
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

		if ( is_numeric( $request->getVal( 'page' ) ) ) {
			$limit_page = $request->getVal( 'page' ) - 1;
		} else {
			$limit_page = 0;
		}

		$replies = $this->getReplies();

		if ( $maxPerPage > 0 ) {
			$replies = array_slice( $replies, $limit_page * $maxPerPage, $maxPerPage );
		}

		foreach ( $replies as $reply ) {
			$output .= $reply->show();
		}

		$output .= $this->showFooter();

		if ( $maxPerPage > 0 ) {
			$dbr = wfGetDB( DB_REPLICA );
			$countReplies = $dbr->selectRow(
				'wikiforum_replies',
				'COUNT(*) AS count',
				[ 'wfr_thread' => $this->getId() ],
				__METHOD__
			);
			$output .= WikiForumGui::showFooterRow(
				$limit_page,
				$countReplies->count,
				$maxPerPage,
				[ 'thread' => $this->getId() ]
			);
		}

		if ( !$this->isClosed() ) {
			$quoteReply = $request->getInt( 'quotereply', 0 );
			$quoteThread = $request->getBool( 'quotethread', false );
			$output .= $this->showNewReplyForm( $quoteReply, $quoteThread );
		}

		if ( !wfReadOnly() ) {
			$dbw = wfGetDB( DB_MASTER );
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
	 * @param string $class
	 * @param string $extraInfo any extra information to show after the posted info
	 * @return string
	 */
	private function showListItemMain( $class, $extraInfo = '' ) {
		$output = '<tr class="mw-wikiforum-';

		if ( $this->isSticky() ) {
			$output .= 'sticky';
		} else {
			$output .= 'normal';
		}

		$desc = '<p class="mw-wikiforum-thread">' . $this->getIcon() . $this->showLink() .
			'<p class="mw-wikiforum-descr">' . $this->showPostedInfo() . $extraInfo . '</p></p>';

		$output .= '">
				<td class="mw-wikiforum-title">' . $desc . '</td>
				<td class="mw-wikiforum-value">' . $this->getReplyCount() . '</td>
				<td class="mw-wikiforum-value">' . $this->getViewCount() . '</td>
				<td class="mw-wikiforum-value">' . $this->showLastPostInfo() . '</td>
			</tr>';

		return $output;
	}

	/**
	 * Show a row for this thread, for a forum page
	 *
	 * @return string
	 */
	function showListItem() {
		if ( $this->isSticky() ) {
			$class = 'sticky';
		} else {
			$class = 'normal';
		}
		return $this->showListItemMain( $class );
	}

	/**
	 * Show a row for this thread, for the <WikiForumList> tag
	 *
	 * @return string
	 */
	function showTagListItem() {
		$categoryLink = $this->getForum()->getCategory()->showLink();
		$forumLink = $this->getForum()->showPlainLink();

		$extraInfo = '<br />' . $this->msg( 'wikiforum-forum', $categoryLink, $forumLink )->text();

		return $this->showListItemMain( 'normal', $extraInfo );
	}

	/**
	 * Shows the edit/delete buttons for the topic author, moderators and also
	 * close (lock) and reopen (unlock) buttons for moderators.
	 *
	 * @return string HTML
	 */
	function showButtons() {
		$extensionAssetsPath = $this->getConfig()->get( 'ExtensionAssetsPath' );
		$user = $this->getUser();

		$editButtons = '';

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$editButtons .= '<a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'thread' => $this->getId(), 'quotethread' => $this->getId() ] ) ) . '#writereply">';
		$editButtons .= '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/comments_add.png" title="' . $this->msg( 'wikiforum-quote' )->text() . '" />';
		$editButtons .= '</a>';

		$forum = $this->getForum();

		if (
			$user->getActorId() == $this->getPostedById() ||
			$user->isAllowed( 'wikiforum-moderator' )
		) {
			$editButtons .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'editthread', 'thread' => $this->getId() ] ) ) . '">';
			$editButtons .= '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/note_edit.png" title="' . $this->msg( 'wikiforum-edit-thread' )->text() . '" />';
			$editButtons .= '</a> <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'deletethread', 'thread' => $this->getId() ] ) ) . '">';
			$editButtons .= '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/note_delete.png" title="' . $this->msg( 'wikiforum-delete-thread' )->text() . '" />';
			$editButtons .= '</a> ';

			// Only moderators can lock and reopen threads
			if ( $user->isAllowed( 'wikiforum-moderator' ) ) {
				if ( !$this->isClosed() ) {
					$editButtons .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'closethread', 'thread' => $this->getId() ] ) ) . '">';
					$editButtons .= '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/lock_add.png" title="' . $this->msg( 'wikiforum-close-thread' )->text() . '" />';
					$editButtons .= '</a>';
				} else {
					$editButtons .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'reopenthread', 'thread' => $this->getId() ] ) ) . '">';
					$editButtons .= '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/lock_open.png" title="' . $this->msg( 'wikiforum-reopen-thread' )->text() . '" />';
					$editButtons .= '</a>';
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
		global $wgRequest, $wgWikiForumAllowAnonymous, $wgWikiForumLogInRC, $wgLang;
		$user = $forum->getUser();

		if ( !$wgWikiForumAllowAnonymous && $user->isAnon() ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
		}

		if ( strlen( $text ) == 0 || strlen( $title ) == 0 ) { // show form again, return it
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . $forum->showNewThreadForm( $title, $text );
		}

		if ( self::titleExists( $title ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-title-already-exists' );
		}

		$title = trim( $title );

		if ( preg_replace( '/[' . Title::legalChars() . ']/', '', $title ) ) { // removes all legal chars, then sees if string has length
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-bad-title' );
		}

		if ( $forum->isAnnouncement() && !$user->isAllowed( 'wikiforum-moderator' ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
		}

		if ( WikiForum::useCaptcha( $user ) ) {
			$captcha = ConfirmEditHooks::getInstance();
			$captcha->setTrigger( 'wikiforum' );
			if ( !$captcha->passCaptchaFromRequest( $wgRequest, $user ) ) {
				$output = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-captcha' );
				$output .= self::showGeneralEditor(
					$title,
					'',
					$text,
					[
						'wfaction' => 'savenewthread',
						'forum' => $forum->getId()
					]
				);
				return $output;
			}
		}

		$dbw = wfGetDB( DB_MASTER );
		$timestamp = wfTimestampNow();

		$result = $dbw->insert(
			'wikiforum_threads',
			[
				'wft_thread_name' => $title,
				'wft_text' => $text,
				'wft_posted_timestamp' => $dbw->timestamp( $timestamp ),
				'wft_actor' => $user->getActorId(),
				'wft_user_ip' => $wgRequest->getIP(),
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
				'wff_last_post_user_ip' => $wgRequest->getIP(),
				'wff_last_post_timestamp' => $dbw->timestamp( $timestamp )
			],
			[ 'wff_forum' => $forum->getId() ],
			__METHOD__
		);

		$logEntry = new ManualLogEntry( 'forum', 'add-thread' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( SpeciaLPage::getTitleFor( 'wikiforum' ) );
		$shortText = $wgLang->truncateForDatabase( $text, 50 );
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

		return WikiForumGui::showFrameHeader() . '
				<table style="width:100%">
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
		$posted .= '<br />' . $this->msg( 'wikiforum-search-thread', $this->showLink() )->text();

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
		return '</table>' . WikiForumGui::showFrameFooter();
	}

	/**
	 * Show the editor for this thread
	 *
	 * @return string
	 */
	function showEditForm() {
		return self::showGeneralEditor(
			$this->getName(),
			'',
			$this->getText(),
			[
				'wfaction' => 'savethread',
				'thread' => $this->getId()
			],
			$this->getUser()
		);
	}

	/**
	 * Show the editor for adding a thread/editing one
	 *
	 * @param string $titleValue value to preload the title input with
	 * @param string $titlePlaceholder the placeholder element of the title input
	 * @param string $textValue value to preload the text field with
	 * @param array $params array of URL params to pass to the form
	 * @param User $user
	 * @return string
	 */
	static function showGeneralEditor( $titleValue, $titlePlaceholder, $textValue, $params, User $user ) {
		$titleValue = str_replace( '"', '&quot;', $titleValue );
		$titlePlaceholder = str_replace( '"', '&quot;', $titlePlaceholder );
		$input = '<tr><td><input type="text" name="name" style="width:100%" placeholder="' . $titlePlaceholder . '" value="' . $titleValue . '" /></td></tr>';

		return WikiForumGui::showWriteForm( true, $params, $input, '25em', $textValue, wfMessage( 'wikiforum-save-thread' )->text(), $user );
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
				$textValue = '[quote=' . $posted . ']' . $reply->getText() . '[/quote]';
			}
		} elseif ( $quoteThread ) {
			$posted = $this->showPlainPostedInfo();
			$textValue = '[quote=' . $posted . ']' . $this->getText() . '[/quote]';
		} elseif ( $this->preloadText ) {
			$textValue = $this->preloadText;
		}

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
