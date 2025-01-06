<?php

use MediaWiki\MediaWikiServices;

class WFCategory extends ContextSource {

	private $data;
	private $forums;

	/**
	 * @param stdClass $sql
	 */
	private function __construct( $sql ) {
		$this->data = $sql;
	}

	/**
	 * Get a new WFCategory object from the given ID
	 *
	 * @param int $id id to get the category for
	 * @return self|false the category, or false on failure
	 */
	public static function newFromID( $id ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$data = $dbr->selectRow(
			'wikiforum_category',
			'*',
			[ 'wfc_category' => $id ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Get a new WFCategory category from the given SQL row
	 *
	 * @param stdClass $sql The row. Must be a row, not a result wrapper!
	 * @return self
	 */
	public static function newFromSQL( $sql ) {
		return new self( $sql );
	}

	/**
	 * Get a new WFCategory object from the given category title
	 *
	 * @param string $title title to search for the category for
	 * @return self|false the category, or false on failure
	 */
	public static function newFromName( $title ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$data = $dbr->selectRow(
			'wikiforum_category',
			'*',
			[ 'wfc_category_name' => $title ],
			__METHOD__
		);

		if ( $data ) {
			return new self( $data );
		} else {
			return false;
		}
	}

	/**
	 * Get this category's name
	 *
	 * @return string Unescaped name pulled from the DB; remember to escape it yourself!
	 */
	function getName() {
		return $this->data->wfc_category_name;
	}

	/**
	 * Get this category's ID number
	 *
	 * @return int the ID
	 */
	function getId() {
		return $this->data->wfc_category;
	}

	/**
	 * Get an array of this category's child forums
	 *
	 * @return multitype:WFForum array of WFForum forums
	 */
	function getForums() {
		if ( !$this->forums ) {
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

			$sqlForums = $dbr->select(
				'wikiforum_forums',
				'*',
				[ 'wff_category' => $this->getId() ],
				__METHOD__,
				[ 'ORDER BY' => 'wff_sortkey ASC, wff_forum ASC' ]
			);

			$forums = [];

			foreach ( $sqlForums as $sql ) {
				$forum = WFForum::newFromSQL( $sql );
				$forum->category = $this; // saves forum making DB query to find this
				$forums[] = $forum;
			}

			$this->forums = $forums;
		}

		return $this->forums;
	}

	/**
	 * Get the URL to this category
	 *
	 * @return string
	 */
	function getURL() {
		return htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'category' => $this->getId() ] ) );
	}

	/**
	 * Get the HTML for a link to this category
	 *
	 * @return string HTML the link
	 */
	function showLink() {
		return '<a href="' . $this->getURL() . '">' . htmlspecialchars( $this->getName(), ENT_QUOTES ) . '</a>';
	}

	/**
	 * Show a link to add a forum to this category. (You should probably check user priviledges before showing though!)
	 *
	 * @return string HTML, the link
	 */
	function showAddForumLink() {
		$extensionAssetsPath = $this->getConfig()->get( 'ExtensionAssetsPath' );

		$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/folder_add.png" title="' . $this->msg( 'wikiforum-add-forum' )->escaped() . '" /> ';
		return $icon . '<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addforum', 'category' => $this->getId() ] ) ) . '">' .
			$this->msg( 'wikiforum-add-forum' )->escaped() . '</a>';
	}

	/**
	 * Show icons for administrative functions (edit, delete, sort up/down).
	 *
	 * @param bool $sort Display "sort up" and "sort down" links?
	 * @return string HTML Links for privileged users, nothing for those w/o the wikiforum-admin user right
	 */
	function showAdminIcons( $sort ) {
		$extensionAssetsPath = $this->getConfig()->get( 'ExtensionAssetsPath' );

		$link = '';

		if ( $this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

			// @see https://phabricator.wikimedia.org/T312733
			$this->getOutput()->addModules( 'ext.wikiForum.admin-links' );

			// For grep: wikiforum-edit-forum, wikiforum-edit-category,
			// wikiforum-delete-forum, wikiforum-delete-category
			$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/database_edit.png" title="' . $this->msg( 'wikiforum-edit-category' )->escaped() . '" />';
			$link = ' <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'editcategory', 'category' => $this->getId() ] ) ) . '">' . $icon . '</a>';

			$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/database_delete.png" title="' . $this->msg( 'wikiforum-delete-category' )->escaped() . '" />';
			$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'deletecategory', 'category' => $this->getId() ] ) ) . '" class="wikiforum-delete-category-link" data-wikiforum-category-id="' . $this->getId() . '">' . $icon . '</a>';

			if ( $sort ) {
				$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/arrow_up.png" title="' . $this->msg( 'wikiforum-sort-up' )->escaped() . '" />';
				$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'categoryup', 'category' => $this->getId() ] ) ) . '" class="wikiforum-up-link wikiforum-category-sort-link" data-wikiforum-category-id="' . $this->getId() . '">' . $icon . '</a>';

				$icon = '<img src="' . $extensionAssetsPath . '/WikiForum/resources/images/arrow_down.png" title="' . $this->msg( 'wikiforum-sort-down' )->escaped() . '" />';
				$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( [ 'wfaction' => 'categorydown', 'category' => $this->getId() ] ) ) . '" class="wikiforum-down-link wikiforum-category-sort-link" data-wikiforum-category-id="' . $this->getId() . '">' . $icon . '</a>';
			}
		}

		return $link;
	}

	/**
	 * Add a new forum to this category
	 *
	 * @param string $title forum title to add
	 * @param string $description forum description to add
	 * @param bool $announcement is this an annoucement forum?
	 * @return string HTML error, or shown HTML forum
	 */
	function addForum( $title, $description, $announcement ) {
		return WFForum::add( $this, $title, $description, $announcement );
	}

	/**
	 * Deletes the category
	 *
	 * @return string
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
		$res = $dbw->delete(
			'wikiforum_category',
			[ 'wfc_category' => $this->getId() ],
			__METHOD__
		);

		return WikiForum::showOverview( $user );
	}

	/**
	 * Edit the category's name
	 *
	 * @param string $categoryName user supplied new name
	 * @return string
	 */
	function edit( $categoryName ) {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}

		if ( strlen( $categoryName ) == 0 ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-text-or-title' );
			return $error . $this->showEditForm();
		}

		if ( !$user->matchEditToken( $request->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-edit', 'sessionfailure' );
			return $error . $this->showEditForm();
		}

		if ( $this->getName() != $categoryName ) {
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			$dbw->update(
				'wikiforum_category',
				[
					'wfc_category_name' => $categoryName,
					'wfc_edited_timestamp' => wfTimestampNow(),
					'wfc_edited_user_ip' => $request->getIP()
				],
				[ 'wfc_category' => $this->getId() ],
				__METHOD__
			);
		}

		$this->data->wfc_category_name = $categoryName;
		$this->data->wfc_edited_timestamp = wfTimestampNow();
		$this->data->wfc_edited_user_ip = $request->getIP();

		return $this->show();
	}

	/**
	 * Show the category page, with search box and header row
	 *
	 * @return string HTML, the category page
	 */
	function show() {
		$output = WikiForumGui::showSearchbox();
		return $output . $this->showMain( $this->showHeaderLinks(), false );
	}

	/**
	 * Show the main bit of the category, for displaying on category pages, or on the overview
	 *
	 * @param string $headerLinks Optional: HTML of links to show to the left (Overview > Category type links)
	 * @param bool $sortLinks Optional: set to false to disable the sorting links
	 * @return string HTML, the category
	 */
	function showMain( $headerLinks = '', $sortLinks = true ) {
		$addLink = '';
		$categoryLink = '';
		$user = $this->getUser();

		if ( $user->isAllowed( 'wikiforum-admin' ) ) {
			$categoryLink = $this->showAdminIcons( $sortLinks );
			$addLink = $this->showAddForumLink();
		}

		$output = WikiForumGui::showHeaderRow( $headerLinks, $user, $addLink );

		$output .= WikiForumGui::showMainHeader(
			htmlspecialchars( $this->getName(), ENT_QUOTES ),
			$this->msg( 'wikiforum-threads' )->escaped(),
			$this->msg( 'wikiforum-replies' )->escaped(),
			$this->msg( 'wikiforum-latest-thread' )->escaped(),
			$categoryLink
		);

		foreach ( $this->getForums() as $forum ) {
			$output .= $forum->showListItem();
		}

		return $output . WikiForumGui::showMainFooter();
	}

	/**
	 * Sort the category upwards
	 *
	 * @return string HTML
	 */
	function sortUp() {
		return $this->sort( true );
	}

	/**
	 * Sort the category downwards
	 *
	 * @return string HTML
	 */
	function sortDown() {
		return $this->sort( false );
	}

	/**
	 * Sort the category. Do not use! Use sortUp() or sortDown() instead!
	 *
	 * @param bool $direction_up true sort up, false sort down
	 * @return string HTML
	 */
	private function sort( $direction_up ) {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-category', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$sqlData = $dbr->select(
			'wikiforum_category',
			[ 'wfc_category', 'wfc_sortkey' ],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'wfc_sortkey ASC' ]
		);

		$i = 0;
		$new_array = [];
		foreach ( $sqlData as $entry ) {
			$entry->wfc_sortkey = $i;
			array_push( $new_array, $entry );
			$i++;
		}
		for ( $i = 0; $i < count( $new_array ); $i++ ) {
			if ( $new_array[$i]->wfc_category == $this->getId() ) {
				if ( $direction_up && $i > 0 ) {
					$new_array[$i]->wfc_sortkey--;
					$new_array[$i - 1]->wfc_sortkey++;
				} elseif ( !$direction_up && $i + 1 < count( $new_array ) ) {
					$new_array[$i]->wfc_sortkey++;
					$new_array[$i + 1]->wfc_sortkey--;
				}
				$i = count( $new_array );
			}
		}
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		foreach ( $new_array as $entry ) {
			$result = $dbw->update(
				'wikiforum_category',
				[ 'wfc_sortkey' => $entry->wfc_sortkey ],
				[ 'wfc_category' => $entry->wfc_category ],
				__METHOD__
			);
		}

		return WikiForum::showOverview( $user );
	}

	/**
	 * Add a new category, with the given name
	 *
	 * @param string $categoryName name to add
	 * @param User $user
	 * @return string HTML
	 */
	static function add( $categoryName, User $user ) {
		global $wgWikiForumLogInRC, $wgRequest;

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
			return $error . WikiForum::showOverview( $user );
		}

		if ( strlen( $categoryName ) == 0 ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . self::showAddForm( $user );
		}

		if ( !$user->matchEditToken( $wgRequest->getVal( 'wpToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'sessionfailure' );
			return $error . self::showAddForm( $user );
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sortkey = $dbr->selectRow(
			'wikiforum_category',
			'MAX(wfc_sortkey) AS the_key',
			[],
			__METHOD__
		);

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->insert(
			'wikiforum_category',
			[
				'wfc_category_name' => $categoryName,
				'wfc_sortkey' => ( $sortkey->the_key + 1 ),
				'wfc_added_timestamp' => $dbw->timestamp( wfTimestampNow() ),
				'wfc_added_actor' => $user->getActorId(),
				'wfc_added_user_ip' => $wgRequest->getIP(),
			],
			__METHOD__
		);

		$category = self::newFromName( $categoryName );

		$logEntry = new ManualLogEntry( 'forum', 'add-category' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiForum' ) );
		$logEntry->setParameters( [
			'4::category-url' => $category->getURL(),
			'5::category-name' => $categoryName
		] );
		$logid = $logEntry->insert();
		if ( $wgWikiForumLogInRC ) {
			$logEntry->publish( $logid );
		}

		return $category->show();
	}

	// GUI Methods

	/**
	 * Shows the header row links - the breadcrumb navigation
	 * (Overview > Category name)
	 *
	 * @return string
	 */
	function showHeaderLinks() {
		$output = MediaWikiServices::getInstance()->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'WikiForum' ),
			$this->msg( 'wikiforum-overview' )->text()
		);

		return $output . ' &gt; ' . $this->showLink();
	}

	/**
	 * Get the form for adding/editing categories and forums.
	 *
	 * @param array $params URL parameters (like array( 'foo' => 'bar' ) or so)
	 * @param string $titlePlaceholder
	 * @param string $titleValue
	 * @param string $formTitle
	 * @param User $user
	 * @return string HTML
	 */
	static function showForm( $params, $titlePlaceholder, $titleValue, $formTitle, User $user ) {
		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-category', 'wikiforum-error-no-rights' );
		}

		$titlePlaceholder = str_replace( '"', '&quot;', $titlePlaceholder );
		$titleValue = str_replace( '"', '&quot;', $titleValue );
		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );
		$url = htmlspecialchars( $specialPage->getFullURL( $params ) );

		return WikiForumGui::showTopLevelForm( $url, '', $formTitle, $titlePlaceholder, $titleValue );
	}

	/**
	 * Show the form for editing this category
	 *
	 * @return string HTML
	 */
	function showEditForm() {
		$params = [ 'wfaction' => 'savecategory', 'category' => $this->getId() ];
		return self::showForm( $params, '', $this->getName(), $this->msg( 'wikiforum-edit-category' )->text(), $this->getUser() );
	}

	/**
	 * Show the form for adding a new category
	 *
	 * @param User $user
	 * @return string HTML
	 */
	static function showAddForm( User $user ) {
		$params = [ 'wfaction' => 'savenewcategory' ];
		return self::showForm( $params, wfMessage( 'wikiforum-category-preload' )->text(), '', wfMessage( 'wikiforum-add-category' )->text(), $user );
	}

	/**
	 * Show the form for adding a new forum to this category
	 *
	 * @return string HTML, the form
	 */
	function showAddForumForm() {
		$params = [ 'wfaction' => 'savenewforum', 'category' => $this->getId() ];
		return WFForum::showForm( $params, $this->msg( 'wikiforum-forum-preload' )->text(), '', '', false, $this->msg( 'wikiforum-add-forum' )->text(), $this->getUser() );
	}
}
