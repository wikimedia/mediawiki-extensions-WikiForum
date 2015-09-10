<?php

class WFForum extends ContextSource {

	public $category;
	private $data;
	private $threads;

	private function __construct( $sql ) {
		$this->data = $sql;
	}

	/**
	 * Get a new forum object for the given ID
	 *
	 * @param int $id
	 * @return WFForum
	 */
	public static function newFromID( $id ) {
		$dbr = wfGetDB( DB_SLAVE );

		$data = $dbr->selectRow(
			'wikiforum_forums',
			'*',
			array( 'wff_forum' => $id ),
			__METHOD__
		);

		if ( $data ) {
			return new WFForum( $data );
		} else {
			return false;
		}
	}

	/**
	 * Get a new forum object from the given database row
	 *
	 * @param stdClass $sql: DB row. Must be a row, not a ResultWrapper!
	 * @return WFForum
	 */
	public static function newFromSQL( $sql ) {
		return new WFForum( $sql );
	}

	/**
	 * Get a WFForum object for the given title
	 *
	 * @param string $title: title to get forum for
	 * @return WFForum|boolean: the forum, or false on failure
	 */
	public static function newFromName( $title ) {
		$dbr = wfGetDB( DB_SLAVE );

		$data = $dbr->selectRow(
			'wikiforum_forums',
			'*',
			array( 'wff_forum_name' => $title ),
			__METHOD__
		);

		if ( $data ) {
			return new WFForum( $data );
		} else {
			return false;
		}
	}

	/**
	 * Is this an annoucement forum?
	 *
	 * @return boolean
	 */
	function isAnnouncement() {
		return $this->data->wff_announcement == true;
	}

	/**
	 * Get this forum's name/title
	 *
	 * @return string
	 */
	function getName() {
		return $this->data->wff_forum_name;
	}

	/**
	 * Show a forum's name for displaying (often in tooltips)
	 *
	 * @return string
	 */
	function showName() {
		return wfMessage( 'wikiforum-forum-name', $this->getName() )->text();
	}

	/**
	 * Get the text/description of this forum
	 *
	 * @return string
	 */
	function getText() {
		return $this->data->wff_description;
	}

	/**
	 * Get this forum's ID number
	 *
	 * @return int: the ID
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
		return WikiForumClass::getUserFromDB( $this->data->wff_last_post_user, $this->data->wff_last_post_user_ip );
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
	 * @param array $orderBy: SQL fragment for ordering by
	 * @return multitype:WFThread: array of threads
	 */
	function getThreads( $orderBy = '' ) {
		if ( !$this->threads ) {
			$dbr = wfGetDB( DB_SLAVE );

			$sqlThreads = $dbr->select(
				'wikiforum_threads',
				'*',
				array( 'wft_forum' => $this->getId() ),
				__METHOD__,
				array( 'ORDER BY' => array( 'wft_sticky DESC', $orderBy ) )
			);

			$threads = array();

			foreach( $sqlThreads as $sql ) {
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
	 * @return string: the URL
	 */
	function getURL() {
		$page = SpecialPage::getTitleFor( 'WikiForum' );

		return htmlspecialchars( $page->getFullURL( array( 'forum' => $this->getId() ) ) );
	}

	/**
	 * Show a link to this forum, with icon
	 *
	 * @return string: HTML, the link
	 */
	function showLink() {
		global $wgExtensionAssetsPath;

		$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder.png" title="' . wfMessage( 'wikiforum-forum-name', $this->getName() )->text() . '" /> ';
		return $icon . $this->showPlainLink();
	}

	/**
	 * Show a link to this forum, without icon
	 *
	 * @return string
	 */
	function showPlainLink() {
		return '<a href="' . $this->getURL() . '">' . $this->getName() . '</a>';
	}

	/**
	 * Show an item (row) for a list (table) for this forum. The sort you find on category pages, and the overview
	 *
	 * @return string: HTML, the row.
	 */
	function showListItem() {
		$output = '<tr class="mw-wikiforum-marked"> <td class="mw-wikiforum-title">
				<p class="mw-wikiforum-issue">' . $this->showLink() . '</p><p class="mw-wikiforum-descr">' . $this->getText() . '</p></td>';

		$icons = $this->showAdminIcons( true );
		if ( $icons ) {
			$output .= '<td class="mw-wikiforum-admin">' . $icons . '</td>';
		}

		$output .= '<td class="mw-wikiforum-value">' . $this->getThreadCount() . '</td>
					<td class="mw-wikiforum-value">' . $this->getReplyCount() . '</td>
					<td class="mw-wikiforum-value">' . $this->showLastPostInfo() . '</td></tr>';
		return $output;
	}

	/**
	 * Show icons for administrative functions (edit, delete, sort up/down).
	 *
	 * @param $sort Boolean
	 * @return HTML
	 */
	function showAdminIcons( $sort ) {
		global $wgExtensionAssetsPath;

		$link = '';

		if ( $this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder_edit.png" title="' . wfMessage( 'wikiforum-edit-forum' )->text() . '" />';
			$link = ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'editforum', 'forum' => $this->getId() ) ) ) . '">' . $icon . '</a>';

			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder_delete.png" title="' . wfMessage( 'wikiforum-delete-forum' )->text() . '" />';
			$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'deleteforum', 'forum' => $this->getId() ) ) ) . '">' . $icon . '</a>';

			if ( $sort ) {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/arrow_up.png" title="' . wfMessage( 'wikiforum-sort-up' )->text() . '" />';
				$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'forumup', 'forum' => $this->getId() ) ) ) . '">' . $icon . '</a>';

				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/arrow_down.png" title="' . wfMessage( 'wikiforum-sort-down' )->text() . '" />';
				$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'forumdown', 'forum' => $this->getId() ) ) ) . '">' . $icon . '</a>';
			}
		}

		return $link;
	}

	/**
	 * Add a new thread to this forum
	 *
	 * @param string $title: user-supplied title
	 * @param string $text: user-supplied text
	 * @return WFThread|boolean: the thread on success, or false on failure
	 */
	function addThread( $title, $text ) {
		return WFThread::add( $this, $title, $text );
	}

	/**
	 * Edit the forum
	 *
	 * @param $forumName String: forum name as supplied by the user
	 * @param $description String: forum description as supplied by the user
	 * @param $announcement boolean
	 * @return string: HTML
	 */
	function edit( $forumName, $description, $announcement ) {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}
		if ( strlen( $forumName ) == 0 ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . $this->showEditForm();
		}

		$dbr = wfGetDB( DB_SLAVE );

		if (
			$this->getName() != $forumName ||
			$this->getText() != $description ||
			$this->isAnnouncement() != $announcement
		) { // only update DB if anything has been changed
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'wikiforum_forums',
				array(
					'wff_forum_name' => $forumName,
					'wff_description' => $description,
					'wff_edited_timestamp' => wfTimestampNow(),
					'wff_edited_user' => $user->getId(),
					'wff_edited_user_ip' => $this->getRequest()->getIP(),
					'wff_announcement' => $announcement
				),
				array( 'wff_forum' => $this->getId() ),
				__METHOD__
			);
		}

		$this->data->wff_forum_name = $forumName;
		$this->data->wff_description = $description;
		$this->data->wff_edited_timestamp = wfTimestampNow();
		$this->data->wff_edited_user = $user->getId();
		$this->data->wff_edited_user_ip = $this->getRequest()->getIP();
		$this->data->wff_announcement = $announcement;

		return $this->show();
	}

	/**
	 * Deletes the forum
	 *
	 * @return string: HTML
	 */
	function delete() {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->delete(
			'wikiforum_forums',
			array( 'wff_forum' => $this->getId() ),
			__METHOD__
		);

		return $this->show();
	}

	/**
	 * Show this forum, including frames, headers, et al.
	 *
	 * @return string: HTML the forum
	 */
	function show() {
		global $wgExtensionAssetsPath;
		$request = $this->getRequest();

		$output = '';
		$dbr = wfGetDB( DB_SLAVE );

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$up = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/bullet_arrow_up.png" alt="" />';
		$down = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/bullet_arrow_down.png" alt="" />';

		// Non-moderators cannot post in an announcement-only forum
		if ( $this->isAnnouncement() && !$this->getUser()->isAllowed( 'wikiforum-moderator' ) ) {
			$write_thread = '';
		} else {
			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/note_add.png" title="' . wfMessage( 'wikiforum-write-thread' )->text() . '" /> ';
			$write_thread = $icon . '<a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'addthread', 'forum' => $this->getId() ) ) ) . '">' .
					wfMessage( 'wikiforum-write-thread' ) . '</a>';
		}

		$output .= WikiForumGui::showSearchbox();
		$output .= WikiForumGui::showHeaderRow( $this->showHeaderLinks(), $write_thread );

		// @todo FIXME: the logic here seems wonky from the end-users point
		// of view and this code is horrible...
		// The <br />s are here to fix ShoutWiki bug #176
		// @see http://bugzilla.shoutwiki.com/show_bug.cgi?id=174
		$output .= WikiForumGui::showMainHeader(
			$this->getName() . ' <a href="' .
			htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'thread', 'sd' => 'up', 'forum' => $this->getId() ) ) ) . '">' . $up .
			'</a><a href="' .
			htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'thread', 'sd' => 'down', 'forum' => $this->getId() ) ) ) . '">' . $down . '</a>',

			wfMessage( 'wikiforum-replies' )->text() . ' <br /><a href="' .
			htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'answers', 'sd' => 'up', 'forum' => $this->getId() ) ) ) . '">' . $up .
			'</a><a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'answers', 'sd' => 'down', 'forum' => $this->getId() ) ) ) . '">' . $down . '</a>',

			wfMessage( 'wikiforum-views' )->text() . ' <br /><a href="' .
			htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'calls', 'sd' => 'up', 'forum' => $this->getId() ) ) ) . '">' . $up . '</a><a href="' .
			htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'calls', 'sd' => 'down', 'forum' => $this->getId() ) ) ) . '">' . $down . '</a>',

			wfMessage( 'wikiforum-latest-reply' )->text() . ' <br /><a href="' .
			htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'last', 'sd' => 'up', 'forum' => $this->getId() ) ) ) . '">' . $up .
			'</a><a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'st' => 'last', 'sd' => 'up', 'forum' => $this->getId() ) ) ) . '">' . $down . '</a>'
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
		$maxPerPage = intval( wfMessage( 'wikiforum-max-threads-per-page' )->inContentLanguage()->plain() );

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
			$output .= '<tr class="sub"><td class="mw-wikiforum-title" colspan="4">' . wfMessage( 'wikiforum-no-threads' )->text() . '</td></tr>';
		}
		$output .= WikiForumGui::showMainFooter();

		if ( $maxPerPage > 0 ) {
			$output .= $this->showFooterRow( $limit_page, $maxPerPage );
		}

		return $output;
	}

	/**
	 * Sort this forum upwards
	 *
	 * @return string: HTML
	 */
	function sortUp() {
		return $this->sort( true );
	}

	/**
	 * Sort this forum downwards
	 *
	 * @return string: HTML
	 */
	function sortDown() {
		return $this->sort( false );
	}

	/**
	 * Do the actual sorting. Do not use - use sortUp and sortDown above instead!
	 *
	 * @param boolean $direction_up: true - up, false - down
	 * @return string: HTML
	 */
	private function sort( $direction_up ) {
		if ( $this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$dbr = wfGetDB( DB_SLAVE );

			$sqlData = $dbr->select( // select all forums in the same category as this
				'wikiforum_forums',
				array( 'wff_forum', 'wff_sortkey' ),
				array( 'wff_category' => $this->getCategory()->getId() ),
				__METHOD__,
				array( 'ORDER BY' => 'wff_sortkey ASC' )
			);

			$i = 0;
			$new_array = array();
			foreach ( $sqlData as $entry ) {
				$entry->wff_sortkey = $i;
				array_push( $new_array, $entry );
				$i++;
			}
			for ( $i = 0; $i < sizeof( $new_array ); $i++ ) {
				if ( $new_array[$i]->wff_forum == $this->getId() ) {
					if ( $direction_up && $i > 0 ) {
						$new_array[$i]->wff_sortkey--;
						$new_array[$i - 1]->wff_sortkey++;
					} elseif ( !$direction_up && $i + 1 < sizeof( $new_array ) ) {
						$new_array[$i]->wff_sortkey++;
						$new_array[$i + 1]->wff_sortkey--;
					}
					$i = sizeof( $new_array );
				}
			}
			$dbw = wfGetDB( DB_MASTER );
			foreach ( $new_array as $entry ) {
				$result = $dbw->update(
					'wikiforum_forums',
					array( 'wff_sortkey' => $entry->wff_sortkey ),
					array( 'wff_forum' => $entry->wff_forum ),
					__METHOD__
				);
			}
		}

		return $this->getCategory()->show();
	}

	/**
	 * Add a new forum
	 *
	 * @param WFCategory $category: category to add to
	 * @param string $forumName: user-supplied name
	 * @param string $description: user-supplied description
	 * @param boolean $announcement: user-supplied announcement checkbox
	 * @return string: HTML
	 */
	static function add( WFCategory $category, $forumName, $description, $announcement ) {
		global $wgWikiForumLogInRC, $wgUser, $wgRequest, $wgLang;

		if ( strlen( $forumName ) == 0 ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . $category->showAddForumForm();
		}
		if ( !$wgUser->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
			return $error . $category->show();
		}

		$dbr = wfGetDB( DB_SLAVE );

		$sortKey = $dbr->selectRow(
			'wikiforum_forums',
			'MAX(wff_sortkey) AS the_key',
			array( 'wff_category' => $category->getId() ),
			__METHOD__
		);

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'wikiforum_forums',
			array(
				'wff_forum_name' => $forumName,
				'wff_description' => $description,
				'wff_category' => $category->getId(),
				'wff_sortkey' => ( $sortKey->the_key + 1 ),
				'wff_added_timestamp' => wfTimestampNow(),
				'wff_added_user' => $wgUser->getId(),
				'wff_added_user_ip' => $wgRequest->getIP(),
				'wff_announcement' => $announcement
			),
			__METHOD__
		);

		$forum = WFForum::newFromName( $forumName );
		$forum->category = $category;

		$logEntry = new ManualLogEntry( 'forum', 'add-forum' );
		$logEntry->setPerformer( $wgUser );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiForum' ) );
		$shortText = $wgLang->truncate( $description, 50 );
		$logEntry->setComment( $shortText );
		$logEntry->setParameters( array(
			'4::forum-url' => $forum->getURL(),
			'5::forum-name' => $forumName
		) );
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
	 * @param array $params: URL parameters
	 * @param string $titlePlaceholder: placeholder attribute for the title input
	 * @param string $titleValue: value attribute for the title input
	 * @param boolean $announcement: value for the announcement checkbox
	 * @param string $formTitle: title to label the form with
	 * @return string HTML, the form
	 */
	static function showForm( $params, $titlePlaceholder = '', $titleValue = '', $textValue = '', $announcement = false, $formTitle ) {
		global $wgUser;

		if ( !$wgUser->isAllowed( 'wikiforum-admin' ) ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-write', 'wikiforum-error-no-rights' );
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
				<p>' . wfMessage( 'wikiforum-description' )->text() . '</p>
				<textarea name="text" style="height: 40px;">' . $textValue . '</textarea>
			</td>
		</tr>
		<tr>
			<td>
				<p><input type="checkbox" name="announcement"' . $check . '/> ' .
					wfMessage( 'wikiforum-announcement-only-description' )->text() .
					'</p>
			</td>
		</tr>';

		return WikiForumGui::showTopLevelForm( $url, $extraRow, $formTitle, $titlePlaceholder, $titleValue );
	}

	/**
	 * Show the form for editing this forum
	 *
	 * @return string: HTML, the form
	 */
	function showEditForm() {
		$params = array( 'wfaction' => 'saveforum', 'forum' => $this->getId() );
		return self::showForm( $params, '', $this->getName(), $this->getText(), $this->isAnnouncement(), wfMessage( 'wikiforum-edit-forum' )->text() );
	}

	/**
	 * Show the editor for adding a new thread to this forum
	 *
	 * @return string: HTML the editor
	 */
	function showNewThreadForm( $preloadTitle, $preloadText ) {
		return WFThread::showGeneralEditor(
			$preloadTitle,
			wfMessage( 'wikiforum-thread-title' )->text(),
			$preloadText,
			array(
				'wfaction' => 'savenewthread',
				'forum' => $this->getId()
			)
		);
	}

	function showFooterRow( $page, $limit ) {
		return WikiForumGui::showFooterRow( $page, $this->getThreadCount(), $limit, array( 'forum' => $this->getId() ) );
	}
}
