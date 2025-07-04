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
		return SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'category' => $this->getId() ] );
	}

	/**
	 * Get the HTML for a link to this category
	 *
	 * @return string HTML the link
	 */
	function showLink() {
		return Html::element(
			'a',
			[ 'href' => $this->getURL() ],
			$this->getName()
		);
	}

	/**
	 * Show a link to add a forum to this category. (You should probably check user priviledges before showing though!)
	 *
	 * @return string HTML, the link
	 */
	function showAddForumLink() {
		return WikiForum::getIconHTML( 'wikiforum-add-forum' ) . ' ' .
			 Html::element(
				'a',
				[ 'href' => SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addforum', 'category' => $this->getId() ] ) ],
				$this->msg( 'wikiforum-add-forum' )->text()
			);
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
				[ 'href' => $specialPage->getFullURL( [ 'wfaction' => 'editcategory', 'category' => $this->getId() ] ) ],
				WikiForum::getIconHTML( 'wikiforum-edit-category' )
			) . ' ';

			$link .= Html::rawElement(
				'a',
				[
					'href' => $specialPage->getFullURL( [ 'wfaction' => 'deletecategory', 'category' => $this->getId() ] ),
					'class' => 'wikiforum-delete-category-link',
					'data-wikiforum-category-id' => $this->getId(),
				],
				WikiForum::getIconHTML( 'wikiforum-delete-category' )
			);

			if ( $sort ) {
				$link .= ' ' . Html::rawElement(
					'a',
					[
						'href' => $specialPage->getFullURL( [ 'wfaction' => 'categoryup', 'category' => $this->getId() ] ),
						'class' => 'wikiforum-up-link wikiforum-category-sort-link',
						'data-wikiforum-category-id' => $this->getId()
					],
					WikiForum::getIconHTML( 'wikiforum-sort-up' )
				);

				$link .= ' ' . Html::rawElement(
					'a',
					[
						'href' => $specialPage->getFullURL( [ 'wfaction' => 'categorydown', 'category' => $this->getId() ] ),
						'class' => 'wikiforum-down-link wikiforum-category-sort-link',
						'data-wikiforum-category-id' => $this->getId()
					],
					WikiForum::getIconHTML( 'wikiforum-sort-down' )
				);
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

		if ( !$user->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
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

		$breadCrumbs = WikiForumGui::showHeaderRow( $headerLinks, $user, $addLink );

		$outputTable = WikiForumGui::showMainHeaderRow(
			htmlspecialchars( $this->getName(), ENT_QUOTES ),
			$this->msg( 'wikiforum-threads' )->escaped(),
			$this->msg( 'wikiforum-replies' )->escaped(),
			$this->msg( 'wikiforum-latest-thread' )->escaped(),
			$categoryLink
		);

		foreach ( $this->getForums() as $forum ) {
			$outputTable .= $forum->showListItem();
		}

		$output = $breadCrumbs . Html::rawElement( 'div', [ 'class' => 'mw-wikiforum-frame' ],
			Html::rawElement( 'table', [ 'class' => 'mw-wikiforum-category-list' ],
				$outputTable
			)
		);

		return $output;
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
			return $error . self::showAddForm();
		}

		if ( !$user->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
			$error = WikiForum::showErrorMessage( 'wikiforum-error-add', 'sessionfailure' );
			return $error . self::showAddForm();
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
	 * @param bool $new Add a new forum, default false
	 * @return string HTML
	 */
	function showEditForm( bool $new = false ) {
		if ( !$this->getUser()->isAllowed( 'wikiforum-admin' ) ) {
			return WikiForum::showErrorMessage( 'wikiforum-error-category', 'wikiforum-error-no-rights' );
		}

		$formDescriptor = [
			'wfaction' => [
				'type' => 'hidden',
				'name' => 'wfaction',
				'default' => ( $new ? 'savenewcategory' : 'savecategory' ),
				'required' => true
			],
			'category' => [
				'type' => 'hidden',
				'name' => 'category',
				'default' => ( $new ? '' : $this->getId() ),
				'required' => true
			],
			'name' => [
				'label-message' => 'wikiforum-name',
				'type' => 'text',
				'name' => 'name',
				'default' => ( $new ? '' : $this->getName() ),
				'required' => true,
				'placeholder-message' => 'wikiforum-category-preload'
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setFormIdentifier( 'edit-category-form' )
			->setWrapperLegendMsg( $new ? 'wikiforum-add-category' : 'wikiforum-edit-category' )
			->setSubmitTextMsg( 'wikiforum-save' )
			->prepareForm()
			->displayForm( false );

		return '';
	}

	/**
	 * Show the form for adding a new category
	 *
	 * @return string HTML
	 */
	static function showAddForm() {
		// Make a new object with dummy data to get access to class methods
		$wfCategory = new self( (object)[ 'id' => -1 ] );
		return $wfCategory->showEditForm( true );
	}

	/**
	 * Show the form for adding a new forum to this category
	 *
	 * @return string HTML, the form
	 */
	function showAddForumForm() {
		return WFForum::showAddForumForm( $this->getId() );
	}
}
