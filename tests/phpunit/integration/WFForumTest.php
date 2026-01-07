<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \WFForum
 * @group WikiForum
 * @group Database
 */
class WFForumTest extends MediaWikiIntegrationTestCase {

	/** @var string[] */
	protected $tablesUsed = [];

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'wikiforum_category';
		$this->tablesUsed[] = 'wikiforum_forums';
		$this->tablesUsed[] = 'wikiforum_threads';
	}

	/**
	 * Helper to create a test category
	 * @param \MediaWiki\User\User $user
	 * @return WFCategory
	 */
	private function createTestCategory( $user ) {
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $user->getEditToken()
		] ) );
		$categoryName = 'Test Category ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $user );
		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category, 'Category should be created' );
		return $category;
	}

	/**
	 * Test creating a forum from ID
	 */
	public function testNewFromID() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Forum ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Forum description', false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );
		$forumId = $forum->getId();

		$forumFromId = WFForum::newFromID( $forumId );
		$this->assertNotFalse( $forumFromId );
		$this->assertEquals( $forumName, $forumFromId->getName() );
		$this->assertEquals( $forumId, $forumFromId->getId() );

		// Test with non-existent ID
		$nonExistent = WFForum::newFromID( 999999 );
		$this->assertFalse( $nonExistent );
	}

	/**
	 * Test creating a forum from name
	 */
	public function testNewFromName() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Forum Name ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Forum description', false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );
		$this->assertEquals( $forumName, $forum->getName() );

		// Test with non-existent name
		$nonExistent = WFForum::newFromName( 'NonExistentForum' . wfRandomString( 20 ) );
		$this->assertFalse( $nonExistent );
	}

	/**
	 * Test creating a forum from SQL row
	 */
	public function testNewFromSQL() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Forum SQL ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Forum description', false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );

		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sqlData = $dbr->selectRow(
			'wikiforum_forums',
			'*',
			[ 'wff_forum' => $forum->getId() ],
			__METHOD__
		);

		$forumFromSQL = WFForum::newFromSQL( $sqlData );
		$this->assertInstanceOf( WFForum::class, $forumFromSQL );
		$this->assertEquals( $forumName, $forumFromSQL->getName() );
	}

	/**
	 * Test getters
	 */
	public function testGetters() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Forum Getters ' . wfRandomString( 10 );
		$forumDescription = 'Test description';
		$category->addForum( $forumName, $forumDescription, false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );

		$this->assertEquals( $forumName, $forum->getName() );
		$this->assertEquals( $forumDescription, $forum->getText() );
		// getId() may return string from DB, so check it's numeric
		$this->assertIsNumeric( $forum->getId() );
		$this->assertGreaterThan( 0, (int)$forum->getId() );
		// getThreadCount() and getReplyCount() may return strings from DB
		$this->assertSame( 0, (int)$forum->getThreadCount() );
		$this->assertSame( 0, (int)$forum->getReplyCount() );
		$this->assertFalse( $forum->isAnnouncement() );
	}

	/**
	 * Test isAnnouncement
	 */
	public function testIsAnnouncement() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$regularForumName = 'Regular Forum ' . wfRandomString( 10 );
		$category->addForum( $regularForumName, 'Description', false );
		$regularForum = WFForum::newFromName( $regularForumName );
		$this->assertNotFalse( $regularForum );
		$this->assertFalse( $regularForum->isAnnouncement() );

		$announcementForumName = 'Announcement Forum ' . wfRandomString( 10 );
		$category->addForum( $announcementForumName, 'Description', true );
		$announcementForum = WFForum::newFromName( $announcementForumName );
		$this->assertNotFalse( $announcementForum );
		$this->assertTrue( $announcementForum->isAnnouncement() );
	}

	/**
	 * Test getThreads
	 */
	public function testGetThreads() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Forum Threads ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Description', false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );

		// Initially should have no threads
		// Pass empty string to avoid SQL syntax error
		$threads = $forum->getThreads( 'wft_posted_timestamp DESC' );
		$this->assertIsArray( $threads );
		$this->assertCount( 0, $threads );
	}

	/**
	 * Test adding a forum
	 */
	public function testAdd() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Add Forum ' . wfRandomString( 10 );
		$forumDescription = 'Test forum description';
		$result = $category->addForum( $forumName, $forumDescription, false );

		$this->assertIsString( $result );

		// Verify forum was created
		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );
		$this->assertEquals( $forumName, $forum->getName() );
		$this->assertEquals( $forumDescription, $forum->getText() );
	}

	/**
	 * Test editing a forum
	 */
	public function testEdit() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Edit Forum ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Original description', false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$forum->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );

		$newName = 'Edited Forum Name ' . wfRandomString( 10 );
		$newDescription = 'Edited description';
		$result = $forum->edit( $newName, $newDescription, false );

		$this->assertIsString( $result );

		// Verify changes
		$updatedForum = WFForum::newFromID( $forum->getId() );
		$this->assertNotFalse( $updatedForum );
		$this->assertEquals( $newName, $updatedForum->getName() );
		$this->assertEquals( $newDescription, $updatedForum->getText() );
	}

	/**
	 * Test deleting a forum
	 */
	public function testDelete() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Delete Forum ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Description', false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );
		$forumId = $forum->getId();

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$forum->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );

		$result = $forum->delete();
		$this->assertIsString( $result );

		// Verify forum was deleted
		$deletedForum = WFForum::newFromID( $forumId );
		$this->assertFalse( $deletedForum );
	}

	/**
	 * Test show methods return HTML
	 */
	public function testShowMethods() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		$forumName = 'Test Show Forum ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Description', false );

		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum );

		$context = new RequestContext();
		$context->setUser( $adminUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$forum->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );

		$showResult = $forum->show();
		$this->assertIsString( $showResult );
		$this->assertNotEmpty( $showResult );

		$showLinkResult = $forum->showLink();
		$this->assertIsString( $showLinkResult );
		$this->assertStringContainsString( $forumName, $showLinkResult );
	}
}
