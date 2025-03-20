<?php

use MediaWiki\MediaWikiServices;

class WFForum extends ContextSource {

	public $category;
	private $data;
	private $threads;

	/**
	 * @param stdClass $sql
	 */
	private function __construct( $sql ) {
		$this->data = $sql;
	}

	/**
	 * Get a new forum object for the given ID
	 *
	 * @param int $id
	 * @return self|false
	 */
	public static function newFromID( $id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$data = $dbr->selectRow(
			'wikiforum_forums',
			'*',
			[ 'wff_forum' => $id ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Get a new forum object from the given database row
	 *
	 * @param stdClass $sql DB row. Must be a row, not a ResultWrapper!
	 * @return self
	 */
	public static function newFromSQL( $sql ) {
		return new self( $sql );
	}

	/**
	 * Get a WFForum object for the given title
	 *
	 * @param string $title title to get forum for
	 * @return self|false the forum, or false on failure
	 */
	public static function newFromName( $title ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$data = $dbr->selectRow(
			'wikiforum_forums',
			'*',
			[ 'wff_forum_name' => $title ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Is this an annoucement forum?
	 *
	 * @return bool
	 */
	function isAnnouncement() {
		return $this->data->wff_announcement == true;
	}

	/**
	 * Get this forum's name/title
	 *
	 * @return string Unescaped name pulled from the DB; remember to escape it yourself!
	 */
	function getName() {
		return $this->data->wff_forum_name;
	}

	/**
	 * Show a forum's name for displaying (often in tooltips)
	 *
	 * @return string HTML-safe i18n message
	 */
	function showName() {
		return $this->msg( 'wikiforum-forum-name', $this->getName() )->escaped();
	}

	/**
	 * Get the text/description of this forum
	 *
	 * @return string Unescaped string pulled from the DB; remember to escape it yourself!
	 */
	function getText() {
		return $this->data->wff_description;
	}

	/**
	 * Get this forum's ID number
	 *
	 * @return int the ID
	 */
	function getId() {
		return $this->data->wff_forum;
	}

	/**
	 * Get the timestamp of the last post in this forum
	 *
	 * @return string
	 */
	function getLastPostTimestamp() {
		return $this->data->wff_last_post_timestamp;
	}

	/**
	 * Get the last user to have posted in this forum
	 *
	 * @return User
	 */
	function getLastPostUser() {
		return WikiForum::getUserFromDB( $this->data->wff_last_post_actor, $this->data->wff_last_post_user_ip );
	}

	/**
	 * Get the number of threads in this forum
	 *
	 * @return int
	 */
	function getThreadCount() {
		return $this->data->wff_thread_count;
	}

	/**
	 * Get the number of replies in the threads in this forum
	 *
	 * @return int
	 */
	function getReplyCount() {
		return $this->data->wff_reply_count;
	}

	/**
	 * Get this forum's parent category
	 *
	 * @return WFCategory
	 */
	function getCategory() {
		if ( !$this->category ) {
			$this->category = WFCategory::newFromID( $this->data->wff_category );
		}
		return $this->category;
	}

	/**
	 * Get an array of this forum's children threads
	 *
	 * @param array $orderBy SQL fragment for ordering by
	 * @return multitype:WFThread array of threads
	 */
	function getThreads( $orderBy = '' ) {
		if ( !$this->threads ) {
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

			$sqlThreads = $dbr->select(
				'wikiforum_threads',
				'*',
				[ 'wft_forum' => $this->getId() ],
				__METHOD__,
				[ 'ORDER BY' => [ 'wft_sticky DESC', $orderBy ] ]
			);

			$threads = [];

			foreach ( $sqlThreads as $sql ) {
				$thread = WFThread::newFromSQL( $sql );
				$thread->forum = $this; // saves thread making DB query to find this
				$threads[] = $thread;
			}

			$this->threads = $threads;
		}

		return $this->threads;
	}

	/**
	 * Show the user and timestamp of the last post in this forum
	 *
	 * @return string
	 */
	function showLastPostInfo() {
		if ( $this->getLastPostTimestamp() > 0 ) {
			return WikiForumGui::showByInfo(
				$this->getLastPostTimestamp(),
				$this->getLastPostUser()
			);
		} else {
			return '';
		}
	}

	/**
	 * Get the URL to this forum
	 *
	 * @return string the URL (properly escaped and thus HTML-safe)
	 */
	function getURL() {
		$page = SpecialPage::getTitleFor( 'WikiForum' );

		return $page->getFullURL( [ 'forum' => $this->getId() ] );
	}

	/**
	 * Show a link to this forum, with icon
	 *
	 * @return string HTML, the link
	 */
	function showLink() {
		$icon = WikiForum::getIconHTML(
			'wikiforum-forum-name',
			$this->msg( 'wikiforum-forum-name', $this->getName() )
		);
		return $icon . ' ' . $this->showPlainLink();
	}

	/**
	 * Show a link to this forum, without icon
	 *
	 * @return string Properly escaped HTML string
	 */
	function showPlainLink() {
		return Html::element(
			'a',
			[ 'href' => $this->getURL() ],
			$this->getName()
		);
	}

	/**
	 * Show an item (row) for a list (table) for this forum. The sort you find on category pages, and the overview
	 *
	 * @return string HTML, the row.
	 */
	function showListItem() {
		$output = '<tr class="mw-wikiforum-marked"> <td class="mw-wikiforum-title">
				<p class="mw-wikiforum-issue">' . $this->showLink() . '</p><p class="mw-wikiforum-descr">' .
				htmlspecialchars( $this->getText(), ENT_QUOTES ) . '</p></td>';

		$icons = $this->showAdminIcons( true );
		if ( $icons ) {
			$output .= Html::rawElement( 'td', [ 'class' => 'mw-wikiforum-admin' ], $icons );
		}

		$output .=
			Html::element( 'td', [ 'class' => 'mw-wikiforum-value' ], $this->getThreadCount() ) .
			Html::element( 'td', [ 'class' => 'mw-wikiforum-value' ], $this->getReplyCount() ) .
			Html::rawElement( 'td', [ 'class' => 'mw-wikiforum-value' ], $this->showLastPostInfo() );

		return $output;
	}

	/**
	 * Show icons for administrative functions (edit, delete, sort up/down).
	 *
	 * @param bool $sort Display "sort up" and "sort down" links?
	 * @return string HTML Links for privileged users, nothing for those w/o the wikiforum-admin user right
	 */
	function showAdminIcons( $sort ) {
		$link = '';

		if ( $this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

			// @see https://phabricator.wikimedia.org/T312733
			$this->getOutput()->addModules( 'ext.wikiForum.admin-links' );

			$link = Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'wfaction' => 'editforum', 'forum' => $this->getId() ] ) ],
				WikiForum::getIconHTML( 'wikiforum-edit-forum' )
			);

			$link .= ' ' . Html::rawElement(
				'a',
				[
					'href' => $specialPage->getFullURL( [ 'wfaction' => 'deleteforum', 'forum' => $this->getId() ] ),
					'class' => 'wikiforum-delete-forum-link',
					'data-wikiforum-forum-id' => $this->getId()
				],
				WikiForum::getIconHTML( 'wikiforum-delete-forum' )
			);

			if ( $sort ) {
				$link .= ' ' . Html::rawElement(
					'a',
					[
						'href' => $specialPage->getFullURL( [ 'wfaction' => 'forumup', 'forum' => $this->getId() ] ),
						'class' => 'wikiforum-up-link wikiforum-forum-sort-link',
						'data-wikiforum-forum-id' => $this->getId()
					],
					WikiForum::getIconHTML( 'wikiforum-sort-up' )
				);

				$link .= ' ' . Html::rawElement(
					'a',
					[
						'href' => $specialPage->getFullURL( [ 'wfaction' => 'forumdown', 'forum' => $this->getId() ] ),
						'class' => 'wikiforum-down-link wikiforum-forum-sort-link',
						'data-wikiforum-forum-id' => $this->getId()
					],
					WikiForum::getIconHTML( 'wikiforum-sort-down' )
				);
			}
		}

		return $link;
	}

	/**
	 * Add a new thread to this forum
	 *
	 * @param string $title user-supplied title
	 * @param string $text user-supplied text
	 * @return WFThread|bool the thread on success, or false on failure
	 */
	function addThread( $title, $text ) {
		return WFThread::add( $this, $title, $text );
	}

	/**
	 * Edit the forum
	 *
	 * @param string $forumName forum name as supplied by the user
	 * @param string $description forum description as supplied by the user
	 * @param bool $announcement
	 * @return string HTML
	 */
	function edit( $forumName, $description, $announcement ) {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}

		if ( strlen( $forumName ) == 0 ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . $this->showEditForm();
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'sessionfailure' );
			return $error . $this->showEditForm();
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		if (
			$this->getName() != $forumName ||
			$this->getText() != $description ||
			$this->isAnnouncement() != $announcement
		) { // only update DB if anything has been changed
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			$dbw->update(
				'wikiforum_forums',
				[
					'wff_forum_name' => $forumName,
					'wff_description' => $description,
					'wff_edited_timestamp' => $dbw->timestamp( wfTimestampNow() ),
					'wff_edited_actor' => $user->getActorId(),
					'wff_edited_user_ip' => $request->getIP(),
					'wff_announcement' => (bool)$announcement
				],
				[ 'wff_forum' => $this->getId() ],
				__METHOD__
			);
		}

		$this->data->wff_forum_name = $forumName;
		$this->data->wff_description = $description;
		$this->data->wff_edited_timestamp = wfTimestampNow();
		$this->data->wff_edited_actor = $user->getActorId();
		$this->data->wff_edited_user_ip = $request->getIP();
		$this->data->wff_announcement = $announcement;

		return $this->show();
	}

	/**
	 * Deletes the forum
	 *
	 * @return string HTML
	 */
	function delete() {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		// @todo FIXME: anti-CSRF feature would go here but since the request is currently a GET
		// request...

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$result = $dbw->delete(
			'wikiforum_forums',
			[ 'wff_forum' => $this->getId() ],
			__METHOD__
		);

		return $this->show();
	}

	/**
	 * Show this forum, including frames, headers, et al.
	 *
	 * @return string HTML the forum
	 */
	function show() {
		$request = $this->getRequest();

		$output = '';
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$upIcon = WikiForum::getIconHTML( 'wikiforum-bullet-arrow-up', $this->msg( 'sort-ascending' ) );
		$downIcon = WikiForum::getIconHTML( 'wikiforum-bullet-arrow-down', $this->msg( 'sort-descending' ) );

		// Non-moderators cannot post in an announcement-only forum
		if ( $this->isAnnouncement() && !$this->getUser()->isAllowed( 'wikiforum-moderator' ) ) {
			$write_thread = '';
		} else {
			$write_thread = WikiForum::getIconHTML( 'wikiforum-note-add' ) .
				' ' .
				Html::element(
					'a',
					[ 'href' => $specialPage->getFullURL( [ 'wfaction' => 'addthread', 'forum' => $this->getId() ] ) ],
					$this->msg( 'wikiforum-write-thread' )->text()
				);
		}

		$output .= WikiForumGui::showSearchbox();
		$output .= WikiForumGui::showHeaderRow( $this->showHeaderLinks(), $this->getUser(), $write_thread );

		// @todo FIXME: the logic here seems wonky from the end-users point
		// of view and this code is horrible...
		// The <br />s are here to fix ShoutWiki bug #176
		// @see http://bugzilla.shoutwiki.com/show_bug.cgi?id=174
		$output .= '<div class="mw-wikiforum-frame">' . '<table class="mw-wikiforum-forum-list">';
		$output .= WikiForumGui::showMainHeaderRow(
			// threads
			htmlspecialchars( $this->getName(), ENT_QUOTES ) .
			' ' .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'thread', 'sd' => 'up', 'forum' => $this->getId() ] ) ],
				$upIcon
			) .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'thread', 'sd' => 'down', 'forum' => $this->getId() ] ) ],
				$downIcon
			),
			// replies
			$this->msg( 'wikiforum-replies' )->escaped() .
			' <br />' .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'answers', 'sd' => 'up', 'forum' => $this->getId() ] ) ],
				$upIcon
			) .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'answers', 'sd' => 'down', 'forum' => $this->getId() ] ) ],
				$downIcon
			),
			// Views
			$this->msg( 'wikiforum-views' )->escaped() .
			' <br />' .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'calls', 'sd' => 'up', 'forum' => $this->getId() ] ) ],
				$upIcon
			) .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'calls', 'sd' => 'down', 'forum' => $this->getId() ] ) ],
				$downIcon
			),
			// Latest
			$this->msg( 'wikiforum-latest-reply' )->escaped() .
			' <br />' .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'last', 'sd' => 'up', 'forum' => $this->getId() ] ) ],
				$upIcon
			) .
			Html::rawElement(
				'a',
				[ 'href' => $specialPage->getFullURL( [ 'st' => 'last', 'sd' => 'down', 'forum' => $this->getId() ] ) ],
				$downIcon
			)
		);

		// sorting
		if ( $request->getVal( 'sd' ) == 'up' ) {
			$sort_direction = 'ASC';
		} else {
			$sort_direction = 'DESC';
		}

		if ( $request->getVal( 'st' ) == 'answers' ) {
			$sort_type = 'wft_reply_count';
		} elseif ( $request->getVal( 'st' ) == 'calls' ) {
			$sort_type = 'wft_view_count';
		} elseif ( $request->getVal( 'st' ) == 'thread' ) {
			$sort_type = 'wft_thread_name';
		} else {
			$sort_type = 'wft_last_post_timestamp';
		}

		$sort = $sort_type . ' ' . $sort_direction;

		// limiting
		$maxPerPage = intval( $this->msg( 'wikiforum-max-threads-per-page' )->inContentLanguage()->plain() );

		if ( is_numeric( $request->getVal( 'page' ) ) ) {
			$limit_page = $request->getVal( 'page' ) - 1;
		} else {
			$limit_page = 0;
		}

		$threads = $this->getThreads( $sort );

		if ( $maxPerPage > 0 ) { // limiting
			$threads = array_slice( $threads, $limit_page * $maxPerPage, $maxPerPage );
		}

		foreach ( $threads as $thread ) {
			$output .= $thread->showListItem();
		}

		if ( !$threads ) {
			$output .= '<tr class="sub"><td class="mw-wikiforum-title" colspan="4">' .
				$this->msg( 'wikiforum-no-threads' )->escaped() . '</td></tr>';
		}
		$output .= '</table></div>';

		if ( $maxPerPage > 0 ) {
			$output .= $this->showFooterRow( $limit_page, $maxPerPage );
		}

		return $output;
	}

	/**
	 * Sort this forum upwards
	 *
	 * @return string HTML
	 */
	function sortUp() {
		return $this->sort( true );
	}

	/**
	 * Sort this forum downwards
	 *
	 * @return string HTML
	 */
	function sortDown() {
		return $this->sort( false );
	}

	/**
	 * Do the actual sorting. Do not use - use sortUp and sortDown above instead!
	 *
	 * @param bool $direction_up true - up, false - down
	 * @return string HTML
	 */
	private function sort( $direction_up ) {
		// @todo FIXME: needs anti-CSRF support...somehow
		// ashley 22 May 2024: now sorta done via the API module + JS which uses that +
		// commenting out the code in SpecialWikiForum.php which calls this directly
		if ( $this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

			$sqlData = $dbr->select( // select all forums in the same category as this
				'wikiforum_forums',
				[ 'wff_forum', 'wff_sortkey' ],
				[ 'wff_category' => $this->getCategory()->getId() ],
				__METHOD__,
				[ 'ORDER BY' => 'wff_sortkey ASC' ]
			);

			$i = 0;
			$new_array = [];
			foreach ( $sqlData as $entry ) {
				$entry->wff_sortkey = $i;
				array_push( $new_array, $entry );
				$i++;
			}
			for ( $i = 0; $i < count( $new_array ); $i++ ) {
				if ( $new_array[$i]->wff_forum == $this->getId() ) {
					if ( $direction_up && $i > 0 ) {
						$new_array[$i]->wff_sortkey--;
						$new_array[$i - 1]->wff_sortkey++;
					} elseif ( !$direction_up && $i + 1 < count( $new_array ) ) {
						$new_array[$i]->wff_sortkey++;
						$new_array[$i + 1]->wff_sortkey--;
					}
					$i = count( $new_array );
				}
			}
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			foreach ( $new_array as $entry ) {
				$result = $dbw->update(
					'wikiforum_forums',
					[ 'wff_sortkey' => $entry->wff_sortkey ],
					[ 'wff_forum' => $entry->wff_forum ],
					__METHOD__
				);
			}
		}

		return $this->getCategory()->show();
	}

	/**
	 * Add a new forum
	 *
	 * @param WFCategory $category category to add to
	 * @param string $forumName user-supplied name
	 * @param string $description user-supplied description
	 * @param bool $announcement user-supplied announcement checkbox
	 * @return string HTML
	 */
	static function add( WFCategory $category, $forumName, $description, $announcement ) {
		global $wgWikiForumLogInRC;

		$request = $category->getRequest();
		$user = $category->getUser();

		if ( strlen( $forumName ) == 0 ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . $category->showAddForumForm();
		}

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
			return $error . $category->show();
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'sessionfailure' );
			return $error . $category->show();
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$sortKey = $dbr->selectRow(
			'wikiforum_forums',
			'MAX(wff_sortkey) AS the_key',
			[ 'wff_category' => $category->getId() ],
			__METHOD__
		);

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->insert(
			'wikiforum_forums',
			[
				'wff_forum_name' => $forumName,
				'wff_description' => $description,
				'wff_category' => $category->getId(),
				'wff_sortkey' => ( $sortKey->the_key + 1 ),
				'wff_added_timestamp' => $dbw->timestamp( wfTimestampNow() ),
				'wff_added_actor' => $user->getActorId(),
				'wff_added_user_ip' => $request->getIP(),
				'wff_announcement' => $announcement
			],
			__METHOD__
		);

		$forum = self::newFromName( $forumName );
		$forum->category = $category;

		$logEntry = new ManualLogEntry( 'forum', 'add-forum' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiForum' ) );
		$shortText = $category->getLanguage()->truncateForDatabase( $description, 50 );
		$logEntry->setComment( $shortText );
		$logEntry->setParameters( [
			'4::forum-url' => $forum->getURL(),
			'5::forum-name' => $forumName
		] );
		$logid = $logEntry->insert();
		if ( $wgWikiForumLogInRC ) {
			$logEntry->publish( $logid );
		}

		return $forum->show();
	}

	/**
	 * Show the header row links - the breadcrumb navigation
	 * (Overview > Category name > Forum)
	 *
	 * @return string
	 */
	function showHeaderLinks() {
		$output = $this->getCategory()->showHeaderLinks();

		return $output . ' &gt; ' . $this->showPlainLink();
	}

	/**
	 * Get the form for adding/editing forums.
	 *
	 * @param array $params URL parameters
	 * @param string $titlePlaceholder placeholder attribute for the title input
	 * @param string $titleValue value attribute for the title input
	 * @param string $textValue
	 * @param bool $announcement value for the announcement checkbox
	 * @param string $formTitle title to label the form with
	 * @param User $user
	 * @return string HTML, the form
	 */
	static function showForm( $params, $titlePlaceholder, $titleValue, $textValue, $announcement, $formTitle, User $user ) {
		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-write', 'wikiforum-error-no-rights' );
		}

		$titlePlaceholder = str_replace( '"', '&quot;', $titlePlaceholder );
		$titleValue = str_replace( '"', '&quot;', $titleValue );
		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );
		$url = htmlspecialchars( $specialPage->getFullURL( $params ) );

		$check = '';
		if ( $announcement ) {
			$check = 'checked="checked"';
		}
		$extraRow = '<tr>
			<td>
				<p>' . wfMessage( 'wikiforum-description' )->escaped() . '</p>
				<textarea name="text" style="height: 40px;">' . $textValue . '</textarea>
			</td>
		</tr>
		<tr>
			<td>
				<p><input type="checkbox" name="announcement"' . $check . '/> ' .
					wfMessage( 'wikiforum-announcement-only-description' )->escaped() .
					'</p>
			</td>
		</tr>';

		return WikiForumGui::showTopLevelForm( $url, $extraRow, $formTitle, $titlePlaceholder, $titleValue );
	}

	/**
	 * Show the form for editing this forum
	 *
	 * @return string HTML, the form
	 */
	function showEditForm() {
		$params = [ 'wfaction' => 'saveforum', 'forum' => $this->getId() ];
		return self::showForm( $params, '', $this->getName(), $this->getText(), $this->isAnnouncement(), $this->msg( 'wikiforum-edit-forum' )->escaped(), $this->getUser() );
	}

	/**
	 * Show the editor for adding a new thread to this forum
	 *
	 * @param string $preloadTitle
	 * @param string $preloadText
	 * @return string HTML the editor
	 */
	function showNewThreadForm( $preloadTitle, $preloadText ) {
		return WFThread::showGeneralEditor(
			$preloadTitle,
			$this->msg( 'wikiforum-thread-title' )->escaped(),
			$preloadText,
			[
				'wfaction' => 'savenewthread',
				'forum' => $this->getId()
			],
			$this->getUser()
		);
	}

	/**
	 * @param int $page
	 * @param int $limit
	 * @return string HTML
	 */
	function showFooterRow( $page, $limit ) {
		return WikiForumGui::showFooterRow( $page, $this->getThreadCount(), $limit, [ 'forum' => $this->getId() ] );
	}
}
