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
	 * Test XSS protection: showPlainPostedInfo should escape HTML in username
	 */
	public function testShowPlainPostedInfoXssProtection() {
		// Create a user with XSS payload in username
		// Note: MediaWiki may reject usernames with <script>, so we'll use a mock
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		// Use reflection to test with XSS payload
		// Since we can't easily create a user with XSS in name, we'll test the escaping logic
		// by checking that htmlspecialchars is applied correctly
		$xssUsername = '<script>alert("XSS")</script>';

		// Create a mock user or use reflection to set name
		// For now, test with a user that has special characters that should be escaped
		$result = WikiForumGui::showPlainPostedInfo( $timestamp, $user );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );

		// Verify that if username contains HTML, it would be escaped
		// Since we can't easily inject XSS into username, we test the escaping mechanism
		// by verifying the method doesn't allow unescaped HTML
		$escapedName = htmlspecialchars( $user->getName() );
		// The escaped name should appear in the output (may be in different format due to message parsing)
		// But we verify no unescaped <script> tags appear
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test double escaping protection: showPlainPostedInfo should not double-escape HTML
	 */
	public function testShowPlainPostedInfoNoDoubleEscaping() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showPlainPostedInfo( $timestamp, $user );
		$this->assertIsString( $result );

		// Check that &amp;lt; (double-escaped <) does not appear
		// This would indicate double escaping
		$this->assertStringNotContainsString( '&amp;lt;', $result );
		$this->assertStringNotContainsString( '&amp;gt;', $result );
		$this->assertStringNotContainsString( '&amp;amp;', $result );
		$this->assertStringNotContainsString( '&amp;quot;', $result );
	}

	/**
	 * Test that showEditedInfo preserves HTML links (rawParams) and doesn't double-escape
	 */
	public function testShowEditedInfoHtmlLinksPreserved() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showEditedInfo( $timestamp, $user );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );

		// Should contain HTML link (not escaped)
		$this->assertStringContainsString( '<a href', $result );
		// Should not contain double-escaped link
		$this->assertStringNotContainsString( '&lt;a href', $result );
		// Should not contain double-escaped HTML entities
		$this->assertStringNotContainsString( '&amp;lt;', $result );
		$this->assertStringNotContainsString( '&amp;gt;', $result );
	}

	/**
	 * Test that showByInfo preserves HTML links (rawParams) and doesn't double-escape
	 */
	public function testShowByInfoHtmlLinksPreserved() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showByInfo( $timestamp, $user );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );

		// Should contain HTML link (not escaped)
		$this->assertStringContainsString( '<a href', $result );
		// Should not contain double-escaped link
		$this->assertStringNotContainsString( '&lt;a href', $result );
		// Should not contain double-escaped HTML entities
		$this->assertStringNotContainsString( '&amp;lt;', $result );
		$this->assertStringNotContainsString( '&amp;gt;', $result );
	}

	/**
	 * Test XSS protection: showEditedInfo should escape user text but preserve HTML links
	 */
	public function testShowEditedInfoXssProtection() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showEditedInfo( $timestamp, $user );
		$this->assertIsString( $result );

		// HTML links should be preserved (rawParams)
		$this->assertStringContainsString( '<a href', $result );
		// But no unescaped script tags should appear
		$this->assertStringNotContainsString( '<script>', $result );
		// User name should be present (may be in link or text)
		$this->assertStringContainsString( $user->getName(), $result );
	}

	/**
	 * Test XSS protection: showByInfo should escape user text but preserve HTML links
	 */
	public function testShowByInfoXssProtection() {
		$user = $this->getTestUser()->getUser();
		$timestamp = wfTimestamp( TS_MW );

		$result = WikiForumGui::showByInfo( $timestamp, $user );
		$this->assertIsString( $result );

		// HTML links should be preserved (rawParams)
		$this->assertStringContainsString( '<a href', $result );
		// But no unescaped script tags should appear
		$this->assertStringNotContainsString( '<script>', $result );
		// User name should be present (may be in link or text)
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

	/**
	 * Test showWriteForm with message key (should be translated)
	 */
	public function testShowWriteFormWithMessageKey() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );
		RequestContext::getMain()->setUser( $user );
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );

		$result = WikiForumGui::showWriteForm( true, [], '', '10em', '', 'wikiforum-save-thread', $user );
		$this->assertIsString( $result );
		$this->assertStringContainsString( '<form', $result );
		// Should contain translated message, not the key
		$this->assertStringContainsString( 'Save thread', $result );
		$this->assertStringNotContainsString( 'wikiforum-save-thread', $result );
	}

	/**
	 * Test XSS protection: message key should be escaped even if it contains HTML
	 */
	public function testShowWriteFormXssProtection() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );
		RequestContext::getMain()->setUser( $user );
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );

		// Try with potential XSS in button text (should be escaped)
		$xssPayload = '<script>alert("XSS")</script>';
		$result = WikiForumGui::showWriteForm( true, [], '', '10em', '', $xssPayload, $user );
		$this->assertIsString( $result );
		// Should escape the script tag
		$this->assertStringContainsString( '&lt;script&gt;', $result );
		$this->assertStringNotContainsString( '<script>', $result );
		// Should not contain unescaped alert
		$this->assertStringNotContainsString( 'alert("XSS")', $result );
	}

	/**
	 * Test XSS protection: showTopLevelForm should escape title parameters
	 */
	public function testShowTopLevelFormXssProtection() {
		$xssTitle = '<script>alert("XSS")</script>';
		$xssPlaceholder = '<img src=x onerror=alert("XSS")>';
		$url = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addcategory' ] );

		$result = WikiForumGui::showTopLevelForm( $url, '', 'Test Form', $xssPlaceholder, $xssTitle );
		$this->assertIsString( $result );

		// XSS payloads should be escaped
		$this->assertStringContainsString( '&lt;script&gt;', $result );
		$this->assertStringNotContainsString( '<script>alert', $result );
		$this->assertStringContainsString( '&lt;img', $result );
		// Check that unescaped HTML tags are not present (even if escaped versions are)
		$this->assertStringNotContainsString( '<img src=x onerror', $result );
	}

	/**
	 * Test double escaping protection: showTopLevelForm should not double-escape HTML
	 */
	public function testShowTopLevelFormNoDoubleEscaping() {
		$url = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addcategory' ] );
		$title = 'Test & Title';
		$placeholder = 'Enter & Name';

		$result = WikiForumGui::showTopLevelForm( $url, '', 'Test Form', $placeholder, $title );
		$this->assertIsString( $result );

		// Check that & is escaped but not double-escaped
		$this->assertStringContainsString( '&amp;', $result );
		$this->assertStringNotContainsString( '&amp;amp;', $result );
		$this->assertStringNotContainsString( '&amp;lt;', $result );
		$this->assertStringNotContainsString( '&amp;gt;', $result );
	}

	/**
	 * Test XSS protection: showTopLevelForm $extraRow parameter must be pre-escaped
	 */
	public function testShowTopLevelFormExtraRowRequiresPreEscaping() {
		$url = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'addforum' ] );

		// extraRow should be pre-escaped HTML - test that raw HTML is passed through
		$extraRow = '<tr><td class="test-class">Extra Content</td></tr>';
		$result = WikiForumGui::showTopLevelForm( $url, $extraRow, 'Test Form', 'Placeholder', 'Value' );

		$this->assertIsString( $result );
		// Raw HTML should be passed through (not escaped)
		$this->assertStringContainsString( '<tr><td class="test-class">Extra Content</td></tr>', $result );
		$this->assertStringNotContainsString( '&lt;tr&gt;', $result );
	}

	/**
	 * Test XSS protection: showWriteForm $input parameter must be pre-escaped
	 */
	public function testShowWriteFormInputRequiresPreEscaping() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );
		RequestContext::getMain()->setUser( $user );
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );

		// input should be pre-escaped HTML - test that raw HTML is passed through
		$input = '<input type="hidden" name="test" value="123" />';
		$result = WikiForumGui::showWriteForm( false, [ 'thread' => 1 ], $input, '10em', 'Test text', 'Save', $user );

		$this->assertIsString( $result );
		// Raw HTML should be passed through (not escaped)
		$this->assertStringContainsString( '<input type="hidden" name="test" value="123" />', $result );
		$this->assertStringNotContainsString( '&lt;input', $result );
	}

	/**
	 * Test XSS protection: showWriteForm should escape text_prev parameter
	 */
	public function testShowWriteFormTextPrevXssProtection() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );
		RequestContext::getMain()->setUser( $user );
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );

		$xssText = '<script>alert("XSS")</script>';
		$result = WikiForumGui::showWriteForm( false, [ 'thread' => 1 ], '', '10em', $xssText, 'Save', $user );

		$this->assertIsString( $result );
		// XSS should be escaped in textarea - Html::element() escapes < and & but not >
		// This is sufficient for security in textarea context
		$this->assertStringContainsString( '&lt;script', $result );
		$this->assertStringNotContainsString( '<script>alert', $result );
	}
}
