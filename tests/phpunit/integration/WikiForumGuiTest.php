<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @covers \WikiForumGui
 * @group WikiForum
 * @group Database
 */
class WikiForumGuiTest extends MediaWikiIntegrationTestCase {

	/** @var string[] */
	protected $tablesUsed = [];

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'wikiforum_category';
		$this->tablesUsed[] = 'wikiforum_forums';
		$this->tablesUsed[] = 'wikiforum_threads';
		$this->tablesUsed[] = 'wikiforum_replies';
	}

	/**
	 * Test showSearchbox
	 */
	public function testShowSearchbox() {
		$result = WikiForumGui::showSearchbox();
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-wikiforum-searchbox', $result );
		$this->assertStringContainsString( '<form', $result );
		$this->assertStringContainsString( 'wfaction=search', $result );
	}

	/**
	 * Test showHeaderRow with registered user
	 */
	public function testShowHeaderRowWithRegisteredUser() {
		$user = $this->getTestUser()->getUser();
		$links = '<a href="#">Link</a>';
		$additionalLinks = '<a href="#">Add</a>';

		$result = WikiForumGui::showHeaderRow( $links, $user, $additionalLinks );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-wikiforum-headerrow', $result );
		$this->assertStringContainsString( 'Link', $result );
		$this->assertStringContainsString( 'Add', $result );
		$this->assertStringContainsString( 'mw-wikiforum-rightside', $result );
	}

	/**
	 * Test showHeaderRow without additional links
	 */
	public function testShowHeaderRowWithoutAdditionalLinks() {
		$user = $this->getTestUser()->getUser();
		$links = '<a href="#">Link</a>';

		$result = WikiForumGui::showHeaderRow( $links, $user );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Link', $result );
	}

	/**
	 * Test showHeaderRow with anonymous user when anonymous is allowed
	 */
	public function testShowHeaderRowWithAnonymousUserAllowed() {
		$this->setMwGlobals( 'wgWikiForumAllowAnonymous', true );
		$user = User::newFromName( '127.0.0.1', false );
		$links = '<a href="#">Link</a>';
		$additionalLinks = '<a href="#">Add</a>';

		$result = WikiForumGui::showHeaderRow( $links, $user, $additionalLinks );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Add', $result );
	}

	/**
	 * Test showHeaderRow with anonymous user when anonymous is not allowed
	 */
	public function testShowHeaderRowWithAnonymousUserNotAllowed() {
		$this->setMwGlobals( 'wgWikiForumAllowAnonymous', false );
		$user = User::newFromName( '127.0.0.1', false );
		$links = '<a href="#">Link</a>';
		$additionalLinks = '<a href="#">Add</a>';

		$result = WikiForumGui::showHeaderRow( $links, $user, $additionalLinks );
		$this->assertIsString( $result );
		$this->assertStringNotContainsString( 'mw-wikiforum-rightside', $result );
	}

	/**
	 * Test showFooterRow with pagination
	 */
	public function testShowFooterRowWithPagination() {
		$page = 1; // First page (1-based)
		$maxIssues = 25;
		$limit = 10;
		$params = [ 'forum' => 1 ];

		$result = WikiForumGui::showFooterRow( $page, $maxIssues, $limit, $params );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-wikiforum-footerrow', $result );
		// Should have page links
		$this->assertStringContainsString( '<a', $result );
		// Should contain page numbers
		$this->assertStringContainsString( '01', $result );
	}

	/**
	 * Test showFooterRow with single page (no pagination)
	 */
	public function testShowFooterRowSinglePage() {
		$page = 1;
		$maxIssues = 5;
		$limit = 10;
		$params = [ 'forum' => 1 ];

		$result = WikiForumGui::showFooterRow( $page, $maxIssues, $limit, $params );
		$this->assertSame( '', $result );
	}

	/**
	 * Test showFooterRow with zero limit
	 */
	public function testShowFooterRowZeroLimit() {
		$page = 1;
		$maxIssues = 25;
		$limit = 0;
		$params = [ 'forum' => 1 ];

		$result = WikiForumGui::showFooterRow( $page, $maxIssues, $limit, $params );
		$this->assertSame( '', $result );
	}

	/**
	 * Test showFooterRow current page highlighting
	 */
	public function testShowFooterRowCurrentPage() {
		$page = 2; // Second page (1-based)
		$maxIssues = 25;
		$limit = 10;
		$params = [ 'forum' => 1 ];

		$result = WikiForumGui::showFooterRow( $page, $maxIssues, $limit, $params );
		$this->assertIsString( $result );
		// Current page should be in brackets ([02])
		$this->assertStringContainsString( '[02]', $result );
	}

	/**
	 * Test showBottomLine with registered user
	 */
	public function testShowBottomLineWithRegisteredUser() {
		$user = $this->getTestUser()->getUser();
		$posted = 'Posted info';
		$buttons = '<button>Edit</button>';

		$result = WikiForumGui::showBottomLine( $posted, $user, $buttons );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-wikiforum-posted', $result );
		$this->assertStringContainsString( 'Posted info', $result );
		$this->assertStringContainsString( 'Edit', $result );
		$this->assertStringContainsString( 'mw-wikiforum-rightside', $result );
	}

	/**
	 * Test showBottomLine with anonymous user
	 */
	public function testShowBottomLineWithAnonymousUser() {
		$user = User::newFromName( '127.0.0.1', false );
		$posted = 'Posted info';
		$buttons = '<button>Edit</button>';

		$result = WikiForumGui::showBottomLine( $posted, $user, $buttons );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Posted info', $result );
		// Anonymous users shouldn't see buttons
		$this->assertStringNotContainsString( 'mw-wikiforum-rightside', $result );
	}

	/**
	 * Test showBottomLine without buttons
	 */
	public function testShowBottomLineWithoutButtons() {
		$user = $this->getTestUser()->getUser();
		$posted = 'Posted info';

		$result = WikiForumGui::showBottomLine( $posted, $user );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Posted info', $result );
	}

	/**
	 * Test showTopLevelForm
	 */
	public function testShowTopLevelForm() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );
		RequestContext::getMain()->setUser( $user );

		$url = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addcategory' ] );
		$extraRow = '<tr><td>Extra</td></tr>';
		$formTitle = 'Test Form';
		$titlePlaceholder = 'Enter name';
		$titleValue = '';

		$result = WikiForumGui::showTopLevelForm( $url, $extraRow, $formTitle, $titlePlaceholder, $titleValue );
		$this->assertIsString( $result );
		$this->assertStringContainsString( '<form', $result );
		$this->assertStringContainsString( 'Test Form', $result );
		$this->assertStringContainsString( 'Enter name', $result );
		$this->assertStringContainsString( 'Extra', $result );
		// Form should contain name input field
		$this->assertStringContainsString( 'name="name"', $result );
	}

	/**
	 * Test showPostedInfo
	 */
	public function testShowPostedInfo() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showPostedInfo( $timestamp, $user );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		// Should contain user name or link
		$this->assertStringContainsString( $user->getName(), $result );
	}

	/**
	 * Test showPlainPostedInfo
	 */
	public function testShowPlainPostedInfo() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showPlainPostedInfo( $timestamp, $user );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( $user->getName(), $result );
	}

	/**
	 * Test showEditedInfo
	 */
	public function testShowEditedInfo() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showEditedInfo( $timestamp, $user );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		// Should contain user name or link
		$this->assertStringContainsString( $user->getName(), $result );
	}

	/**
	 * Test showByInfo
	 */
	public function testShowByInfo() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showByInfo( $timestamp, $user );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		// Should contain user name or link
		$this->assertStringContainsString( $user->getName(), $result );
	}

	/**
	 * Test showWriteForm with registered user
	 */
	public function testShowWriteFormWithRegisteredUser() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );
		RequestContext::getMain()->setUser( $user );
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );

		$showCancel = true;
		$params = [ 'thread' => 1 ];
		$input = '<input type="hidden" name="test" value="1" />';
		$height = '10em';
		$text_prev = 'Previous text';
		$saveButton = 'Save';

		$result = WikiForumGui::showWriteForm( $showCancel, $params, $input, $height, $text_prev, $saveButton, $user );
		$this->assertIsString( $result );
		$this->assertStringContainsString( '<form', $result );
		$this->assertStringContainsString( 'writereply', $result );
		$this->assertStringContainsString( 'Previous text', $result );
		$this->assertStringContainsString( 'Save', $result );
		// Cancel button should be present (translated)
		$this->assertStringContainsString( 'Cancel', $result );
	}

	/**
	 * Test showWriteForm without cancel button
	 */
	public function testShowWriteFormWithoutCancel() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );
		RequestContext::getMain()->setUser( $user );
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );

		$showCancel = false;
		$params = [ 'thread' => 1 ];
		$input = '';
		$height = '10em';
		$text_prev = '';
		$saveButton = 'Save';

		$result = WikiForumGui::showWriteForm( $showCancel, $params, $input, $height, $text_prev, $saveButton, $user );
		$this->assertIsString( $result );
		// Cancel button should not be present when showCancel is false
		$this->assertStringNotContainsString( 'Cancel', $result );
	}

	/**
	 * Test showWriteForm with anonymous user when not allowed
	 */
	public function testShowWriteFormAnonymousNotAllowed() {
		$this->setMwGlobals( 'wgWikiForumAllowAnonymous', false );
		$user = User::newFromName( '127.0.0.1', false );
		$context = new RequestContext();
		$context->setUser( $user );
		RequestContext::getMain()->setUser( $user );

		$result = WikiForumGui::showWriteForm( true, [], '', '10em', '', 'Save', $user );
		$this->assertSame( '', $result );
	}
}
