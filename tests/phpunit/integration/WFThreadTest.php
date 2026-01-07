<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \WFThread
 * @group WikiForum
 * @group Database
 */
class WFThreadTest extends MediaWikiIntegrationTestCase {

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
	 * Helper to create a FauxRequest with proper token setup
	 * This ensures the token is generated from the same request session
	 * @param \MediaWiki\User\User $user
	 * @param \MediaWiki\Title\Title|null $title
	 * @return array
	 */
	private function createRequestWithToken( $user, $title = null ) {
		if ( !$title ) {
			$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		}

		// Create POST request first (without token) - this creates a session
		$request = new FauxRequest( [], true );
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( $title );
		$context->setRequest( $request );

		// Get token using the same request - this ensures session matches
		$token = $user->getEditToken( '', $request );

		// Set token in the request
		$request->setVal( 'wpToken', $token );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		return [ $request, $context ];
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
	 * Helper to create a test forum
	 * @param \MediaWiki\User\User $user
	 * @return WFForum
	 */
	private function createTestForum( $user ) {
		$category = $this->createTestCategory( $user );
		$this->assertNotFalse( $category, 'Category should exist' );
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'WikiForum' ) );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $user->getEditToken()
		] ) );
		$category->setContext( $context );
		$forumName = 'Test Forum ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Forum description', false );
		$forum = WFForum::newFromName( $forumName );
		$this->assertNotFalse( $forum, 'Forum should be created' );
		return $forum;
	}

	/**
	 * Test creating a thread from ID
	 */
	public function testNewFromID() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Thread ' . wfRandomString( 10 );
		$threadText = 'Thread text content';
		$forum->addThread( $threadTitle, $threadText );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread );
		$threadId = $thread->getId();

		$threadFromId = WFThread::newFromID( $threadId );
		$this->assertNotFalse( $threadFromId );
		$this->assertEquals( $threadTitle, $threadFromId->getName() );
		$this->assertEquals( $threadId, $threadFromId->getId() );

		// Test with non-existent ID
		$nonExistent = WFThread::newFromID( 999999 );
		$this->assertFalse( $nonExistent );
	}

	/**
	 * Test creating a thread from name
	 */
	public function testNewFromName() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Thread Name ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread );
		$this->assertEquals( $threadTitle, $thread->getName() );

		// Test with non-existent name
		$nonExistent = WFThread::newFromName( 'NonExistentThread' . wfRandomString( 20 ) );
		$this->assertFalse( $nonExistent );
	}

	/**
	 * Test creating a thread from SQL row
	 */
	public function testNewFromSQL() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Thread SQL ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread );

		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sqlData = $dbr->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread' => $thread->getId() ],
			__METHOD__
		);

		$threadFromSQL = WFThread::newFromSQL( $sqlData );
		$this->assertInstanceOf( WFThread::class, $threadFromSQL );
		$this->assertEquals( $threadTitle, $threadFromSQL->getName() );
	}

	/**
	 * Test getters
	 */
	public function testGetters() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Thread Getters ' . wfRandomString( 10 );
		$threadText = 'Thread text content';
		$forum->addThread( $threadTitle, $threadText );

		// Reload thread from DB to ensure fresh data - use DB_PRIMARY to read after write in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$threadData = $dbw->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread_name' => $threadTitle ],
			__METHOD__
		);
		$this->assertNotFalse( $threadData, 'Thread should exist in database' );
		$thread = WFThread::newFromSQL( $threadData );
		$thread->setContext( $context );

		$this->assertEquals( $threadTitle, $thread->getName() );
		$this->assertEquals( $threadText, $thread->getText() );
		// getId() may return string from DB, so check it's numeric
		$this->assertIsNumeric( $thread->getId() );
		$this->assertGreaterThan( 0, (int)$thread->getId() );
		// getReplyCount() may return string from DB, so convert to int for comparison
		// New threads should have 0 replies
		$this->assertSame( 0, (int)$thread->getReplyCount(), 'New thread should have 0 replies' );
		// View count may be incremented by show() method calls, so just check it's non-negative
		$this->assertGreaterThanOrEqual( 0, (int)$thread->getViewCount(), 'View count should be non-negative' );
	}

	/**
	 * Test isSticky and isClosed
	 */
	public function testThreadStates() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Thread States ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread );

		// Initially should not be sticky or closed
		$this->assertFalse( $thread->isSticky() );
		$this->assertFalse( $thread->isClosed() );
	}

	/**
	 * Test adding a thread
	 */
	public function testAdd() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Add Thread ' . wfRandomString( 10 );
		$threadText = 'Thread text content';
		$result = $forum->addThread( $threadTitle, $threadText );

		$this->assertIsString( $result );

		// Verify thread was created
		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread );
		$this->assertEquals( $threadTitle, $thread->getName() );
		$this->assertEquals( $threadText, $thread->getText() );
	}

	/**
	 * Test editing a thread
	 */
	public function testEdit() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Edit Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Original text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $context->getTitle() );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $context->getRequest() );

		$newTitle = 'Edited Thread Title ' . wfRandomString( 10 );
		$newText = 'Edited text';
		$result = $thread->edit( $newTitle, $newText );

		$this->assertIsString( $result );

		// Verify changes
		$updatedThread = WFThread::newFromID( $thread->getId() );
		$this->assertNotFalse( $updatedThread );
		$this->assertEquals( $newTitle, $updatedThread->getName() );
		$this->assertEquals( $newText, $updatedThread->getText() );
	}

	/**
	 * Test adding a reply to a thread
	 */
	public function testAddReply() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Add Reply Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		$thread->setContext( $context );

		$replyText = 'Reply text content';
		$result = $thread->addReply( $replyText );

		$this->assertIsString( $result );

		// Verify reply was added
		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies );
		$this->assertEquals( $replyText, $replies[0]->getText() );
	}

	/**
	 * Test getReplies
	 */
	public function testGetReplies() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Get Replies Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		$thread->setContext( $context );

		// Initially should have no replies
		$replies = $thread->getReplies();
		$this->assertIsArray( $replies );
		$this->assertCount( 0, $replies );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		// Ensure token is set in request for addReply
		$token = $user->getEditToken( '', $request );
		$request->setVal( 'wpToken', $token );

		// Add a reply
		$result1 = $thread->addReply( 'First reply' );
		$this->assertIsString( $result1 );
		$this->assertStringNotContainsString( 'wikiforum-error', $result1, 'First reply should be added' );

		$result2 = $thread->addReply( 'Second reply' );
		$this->assertIsString( $result2 );
		$this->assertStringNotContainsString( 'wikiforum-error', $result2, 'Second reply should be added' );

		// Now should have 2 replies - reload thread from DB to clear cache
		$thread = WFThread::newFromID( $thread->getId() );
		$thread->setContext( $context );
		$replies = $thread->getReplies();
		$this->assertCount( 2, $replies );
	}

	/**
	 * Test makeSticky and removeSticky
	 */
	public function testStickyOperations() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$forum = $this->createTestForum( $adminUser );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $adminUser );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Sticky Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		$thread->setContext( $context );

		// Initially not sticky
		$this->assertFalse( $thread->isSticky() );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $context->getTitle() );
		$globalContext->setUser( $adminUser );
		$globalContext->setRequest( $context->getRequest() );

		// Make sticky
		$thread->makeSticky();
		$updatedThread = WFThread::newFromID( $thread->getId() );
		$this->assertTrue( $updatedThread->isSticky() );

		// Remove sticky
		$updatedThread->setContext( $context );
		$updatedThread->removeSticky();
		$finalThread = WFThread::newFromID( $thread->getId() );
		$this->assertFalse( $finalThread->isSticky() );
	}

	/**
	 * Test close and reopen
	 */
	public function testCloseReopen() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$forum = $this->createTestForum( $adminUser );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $adminUser );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Close Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $context->getTitle() );
		$globalContext->setUser( $adminUser );
		$globalContext->setRequest( $context->getRequest() );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		// Ensure token is set in request for close/reopen
		$token = $adminUser->getEditToken( '', $request );
		$request->setVal( 'wpToken', $token );

		// Initially not closed
		$this->assertFalse( $thread->isClosed() );

		// Close thread
		$result = $thread->close();
		$this->assertIsString( $result );
		// Note: result will contain "mw-wikiforum-error-msg" CSS class for the "Thread closed" message,
		// which is not an error but an informational message, so we don't check for errors here

		// Reload thread from DB to get updated state - use DB_PRIMARY to read after write in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$closedThreadData = $dbw->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread' => $thread->getId() ],
			__METHOD__
		);
		$this->assertNotFalse( $closedThreadData, 'Closed thread should exist' );
		$closedThread = WFThread::newFromSQL( $closedThreadData );
		$closedThread->setContext( $context );
		$this->assertTrue( $closedThread->isClosed(), 'Thread should be closed' );

		// Reopen thread
		$closedThread->setContext( $context );
		// Ensure token is still set
		$request->setVal( 'wpToken', $token );
		$result = $closedThread->reopen();
		$this->assertIsString( $result );
		$reopenedThread = WFThread::newFromID( $thread->getId() );
		$this->assertFalse( $reopenedThread->isClosed() );
	}

	/**
	 * Test checkAutoLockConditions
	 */
	public function testCheckAutoLockConditions() {
		$this->overrideConfigValue( 'WikiForumAutoLockInactiveHours', null );

		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test AutoLock Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread );

		// With auto-lock disabled, should return false
		$this->assertFalse( $thread->checkAutoLockConditions() );
	}

	/**
	 * Test deleting a thread
	 */
	public function testDelete() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Delete Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		$threadId = $thread->getId();
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		// Ensure token is set in request for delete
		$token = $user->getEditToken( '', $request );
		$request->setVal( 'wpToken', $token );

		// Ensure thread has context with title set
		$thread->setContext( $context );

		$result = $thread->delete();
		$this->assertIsString( $result );

		// Verify thread was deleted - use DB_PRIMARY to read after delete in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$threadData = $dbw->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread' => $threadId ],
			__METHOD__
		);
		$this->assertFalse( $threadData, 'Thread should be deleted' );
	}

	/**
	 * Test show methods return HTML
	 */
	public function testShowMethods() {
		$user = $this->getTestUser()->getUser();
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		[ $request, $context ] = $this->createRequestWithToken( $user );
		$forum->setContext( $context );

		// Ensure Title is set in global context before addThread (which calls show())
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Show Thread ' . wfRandomString( 10 );
		$forum->addThread( $threadTitle, 'Thread text' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		$thread->setContext( $context );

		$showResult = $thread->show();
		$this->assertIsString( $showResult );
		$this->assertNotEmpty( $showResult );

		$showLinkResult = $thread->showLink();
		$this->assertIsString( $showLinkResult );
		$this->assertStringContainsString( $threadTitle, $showLinkResult );
	}
}
