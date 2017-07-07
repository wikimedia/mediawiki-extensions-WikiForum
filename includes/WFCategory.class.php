<?php

class WFCategory extends ContextSource {

	private $data;
	private $forums;

	private function __construct( $sql ) {
		$this->data = $sql;
	}

	/**
	 * Get a new WFCategory object from the given ID
	 *
	 * @param int $id: id to get the category for
	 * @return WFCategory|boolean: the category, or false on failure
	 */
	public static function newFromID( $id ) {
		$dbr = wfGetDB( DB_SLAVE );

		$data = $dbr->selectRow(
			'wikiforum_category',
			'*',
			array( 'wfc_category' => $id ),
			__METHOD__
		);

		if ( $data ) {
			return new WFCategory( $data );
		} else {
			return false;
		}
	}

	/**
	 * Get a new WFCategory category from the given SQL row
	 *
	 * @param stdClass $sql: The row. Must be a row, not a result wrapper!
	 * @return WFCategory
	 */
	public static function newFromSQL( $sql ) {
		return new WFCategory( $sql );
	}

	/**
	 * Get a new WFCategory object from the given category title
	 *
	 * @param string $title: title to search for the category for
	 * @return WFCategory|boolean: WFCategory the category, or false on failure
	 */
	public static function newFromName( $title ) {
		$dbr = wfGetDB( DB_SLAVE );

		$data = $dbr->selectRow(
			'wikiforum_category',
			'*',
			array( 'wfc_category_name' => $title ),
			__METHOD__
		);

		if ( $data ) {
			return new WFCategory( $data );
		} else {
			return false;
		}
	}

	/**
	 * Get this category's name
	 *
	 * @return string
	 */
	function getName() {
		return $this->data->wfc_category_name;
	}

	/**
	 * Get this category's ID number
	 *
	 * @return int: the ID
	 */
	function getId() {
		return $this->data->wfc_category;
	}

	/**
	 * Get an array of this category's child forums
	 *
	 * @return multitype:WFForum: array of WFForum forums
	 */
	function getForums() {
		if ( !$this->forums ) {
			$dbr = wfGetDB( DB_SLAVE );

			$sqlForums = $dbr->select(
				'wikiforum_forums',
				'*',
				array( 'wff_category' => $this->getId() ),
				__METHOD__,
				array( 'ORDER BY' => 'wff_sortkey ASC, wff_forum ASC' )
			);

			$forums = array();

			foreach( $sqlForums as $sql ) {
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
		return htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( array( 'category' => $this->getId() ) ) );
	}

	/**
	 * Get the HTML for a link to this category
	 *
	 * @return string: HTML the link
	 */
	function showLink() {
		return '<a href="' . $this->getURL() . '">' . $this->getName() . '</a>';
	}

	/**
	 * Show a link to add a forum to this category. (You should probably check user priviledges before showing though!)
	 *
	 * @return string: HTML, the link
	 */
	function showAddForumLink() {
		global $wgExtensionAssetsPath;

		$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/folder_add.png" title="' . wfMessage( 'wikiforum-add-forum' )->text() . '" /> ';
		return $icon . '<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( array( 'wfaction' => 'addforum', 'category' => $this->getId() ) ) ) . '">' .
			wfMessage( 'wikiforum-add-forum' )->text() . '</a>';
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

			// For grep: wikiforum-edit-forum, wikiforum-edit-category,
			// wikiforum-delete-forum, wikiforum-delete-category
			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/database_edit.png" title="' . wfMessage( 'wikiforum-edit-category' )->text() . '" />';
			$link = ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'editcategory', 'category' => $this->getId() ) ) ) . '">' . $icon . '</a>';

			$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/database_delete.png" title="' . wfMessage( 'wikiforum-delete-category' )->text() . '" />';
			$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'deletecategory', 'category' => $this->getId() ) ) ) . '">' . $icon . '</a>';

			if ( $sort ) {
				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/arrow_up.png" title="' . wfMessage( 'wikiforum-sort-up' )->text() . '" />';
				$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'categoryup', 'category' => $this->getId() ) ) ) . '">' . $icon . '</a>';

				$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/icons/arrow_down.png" title="' . wfMessage( 'wikiforum-sort-down' )->text() . '" />';
				$link .= ' <a href="' . htmlspecialchars( $specialPage->getFullURL( array( 'wfaction' => 'categorydown', 'category' => $this->getId() ) ) ) . '">' . $icon . '</a>';
			}
		}

		return $link;
	}

	/**
	 * Add a new forum to this category
	 *
	 * @param string $title: forum title to add
	 * @param string $description: forum description to add
	 * @param boolean $announcement: is this an annoucement forum?
	 * @return string: HTML error, or shown HTML forum
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
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-delete', 'wikiforum-error-general' );
			return $error . $this->show();
		}

		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->delete(
			'wikiforum_category',
			array( 'wfc_category' => $this->getId() ),
			__METHOD__
		);

		return WikiForumClass::showOverview();
	}

	/**
	 * Edit the category's name
	 *
	 * @param string $categoryName: user supplied new name
	 * @return string
	 */
	function edit( $categoryName ) {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}
		if ( strlen( $categoryName ) == 0 ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-edit', 'wikiforum-error-no-text-or-title' );
			return $error . $this->showEditForm();
		}

		if ( $this->getName() != $categoryName ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'wikiforum_category',
				array(
					'wfc_category_name' => $categoryName,
					'wfc_edited' => wfTimestampNow(),
					'wfc_edited_user_ip' => $this->getRequest()->getIP()
				),
				array( 'wfc_category' => $this->getId() ),
				__METHOD__
			);
		}

		$this->data->wfc_category_name = $categoryName;
		$this->data->wfc_edited = wfTimestampNow();
		$this->data->wfc_edited_user_ip = $this->getRequest()->getIP();

		return $this->show();
	}

	/**
	 * Show the category page, with search box and header row
	 *
	 * @return string: HTML, the category page
	 */
	function show() {
		$output = WikiForumGui::showSearchbox();
		return $output . $this->showMain( $this->showHeaderLinks(), false );
	}

	/**
	 * Show the main bit of the category, for displaying on category pages, or on the overview
	 *
	 * @param string $headerLinks: Optional: HTML of links to show to the left (Overview > Category type links)
	 * @param boolean $sortLinks: Optional: set to false to disable the sorting links
	 * @return string: HTML, the category
	 */
	function showMain( $headerLinks = '', $sortLinks = true ) {
		$addLink = '';
		$categoryLink = '';

		if ( $this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$categoryLink = $this->showAdminIcons( $sortLinks );
			$addLink = $this->showAddForumLink();
		}

		$output = WikiForumGui::showHeaderRow( $headerLinks, $addLink );

		$output .= WikiForumGui::showMainHeader(
			$this->getName(),
			wfMessage( 'wikiforum-threads' )->text(),
			wfMessage( 'wikiforum-replies' )->text(),
			wfMessage( 'wikiforum-latest-thread' )->text(),
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
	 * @return string: HTML
	 */
	function sortUp() {
		return $this->sort( true );
	}

	/**
	 * Sort the category downwards
	 *
	 * @return string: HTML
	 */
	function sortDown() {
		return $this->sort( false );
	}

	/**
	 * Sort the category. Do not use! Use sortUp() or sortDown() instead!
	 *
	 * @param boolean $direction_up: true sort up, false sort down
	 * @return string: HTML
	 */
	private function sort( $direction_up ) {
		if ( !$this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-category', 'wikiforum-error-no-rights' );
			return $error . $this->show();
		}

		$dbr = wfGetDB( DB_SLAVE );

		$sqlData = $dbr->select(
			'wikiforum_category',
			array( 'wfc_category', 'wfc_sortkey' ),
			array(),
			__METHOD__,
			array( 'ORDER BY' => 'wfc_sortkey ASC' )
		);

		$i = 0;
		$new_array = array();
		foreach ( $sqlData as $entry ) {
			$entry->wfc_sortkey = $i;
			array_push( $new_array, $entry );
			$i++;
		}
		for ( $i = 0; $i < sizeof( $new_array ); $i++ ) {
			if ( $new_array[$i]->wfc_category == $this->getId() ) {
				if ( $direction_up && $i > 0 ) {
					$new_array[$i]->wfc_sortkey--;
					$new_array[$i - 1]->wfc_sortkey++;
				} elseif ( !$direction_up && $i + 1 < sizeof( $new_array ) ) {
					$new_array[$i]->wfc_sortkey++;
					$new_array[$i + 1]->wfc_sortkey--;
				}
				$i = sizeof( $new_array );
			}
		}
		$dbw = wfGetDB( DB_MASTER );
		foreach ( $new_array as $entry ) {
			$result = $dbw->update(
				'wikiforum_category',
				array( 'wfc_sortkey' => $entry->wfc_sortkey ),
				array( 'wfc_category' => $entry->wfc_category ),
				__METHOD__
			);
		}

		return WikiForumClass::showOverview();
	}

	/**
	 * Add a new category, with the given name
	 *
	 * @param string $categoryName: name to add
	 * @return string: HTML
	 */
	static function add( $categoryName ) {
		global $wgWikiForumLogInRC, $wgUser, $wgRequest;

		if ( !$wgUser->isAllowed( 'wikiforum-admin' ) ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-rights' );
			return $error . WikiForumClass::showOverview();
		}
		if ( strlen( $categoryName ) == 0 ) {
			$error = WikiForumClass::showErrorMessage( 'wikiforum-error-add', 'wikiforum-error-no-text-or-title' );
			return $error . WFCategory::showAddForm();
		}

		$dbr = wfGetDB( DB_SLAVE );
		$sortkey = $dbr->selectRow(
			'wikiforum_category',
			'MAX(wfc_sortkey) AS the_key',
			array(),
			__METHOD__
		);

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'wikiforum_category',
			array(
				'wfc_category_name' => $categoryName,
				'wfc_sortkey' => ( $sortkey->the_key + 1 ),
				'wfc_added_timestamp' => wfTimestampNow(),
				'wfc_added_user' => $wgUser->getId(),
				'wfc_added_user_ip' => $wgRequest->getIP(),
			),
			__METHOD__
		);

		$category = WFCategory::newFromName( $categoryName );

		$logEntry = new ManualLogEntry( 'forum', 'add-category' );
		$logEntry->setPerformer( $wgUser );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiForum' ) );
		$logEntry->setParameters( array(
			'4::category-url' => $category->getURL(),
			'5::category-name' => $categoryName
		) );
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
		$output = Linker::link(
			SpecialPage::getTitleFor( 'WikiForum' ),
			wfMessage( 'wikiforum-overview' )->text()
		);

		return $output . ' &gt; ' . $this->showLink();
	}

	/**
	 * Get the form for adding/editing categories and forums.
	 *
	 * @param $params Array: URL parameters (like array( 'foo' => 'bar' ) or so)
	 * @param $titlePlaceholder
	 * @param $titleValue
	 * @param $formTitle
	 */
	static function showForm( $params, $titlePlaceholder, $titleValue, $formTitle ) {
		global $wgUser;

		if ( !$wgUser->isAllowed( 'wikiforum-admin' ) ) {
			return WikiForumClass::showErrorMessage( 'wikiforum-error-category', 'wikiforum-error-no-rights' );
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
	 * @return string: HTML
	 */
	function showEditForm() {
		$params = array( 'wfaction' => 'savecategory', 'category' => $this->getId() );
		return self::showForm( $params, '', $this->getName(), wfMessage( 'wikiforum-edit-category' )->text() );
	}

	/**
	 * Show the form for adding a new category
	 *
	 * @return string: HTML
	 */
	static function showAddForm() {
		$params = array( 'wfaction' => 'savenewcategory' );
		return self::showForm( $params, wfMessage( 'wikiforum-category-preload' )->text(), '', wfMessage( 'wikiforum-add-category' )->text() );
	}

	/**
	 * Show the form for adding a new forum to this category
	 *
	 * @return string: HTML, the form
	 */
	function showAddForumForm() {
		$params = array( 'wfaction' => 'savenewforum', 'category' => $this->getId() );
		return WFForum::showForm( $params, wfMessage( 'wikiforum-forum-preload' )->text(), '', '', false, wfMessage( 'wikiforum-add-forum' )->text() );
	}
}
