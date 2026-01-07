<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \WFReply
 * @group WikiForum
 * @group Database
 */
class WFReplyTest extends MediaWikiIntegrationTestCase {

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
	 * Helper to create a test thread
	 * @param \MediaWiki\User\User $user
	 * @return WFThread
	 */
	private function createTestThread( $user ) {
		$forum = $this->createTestForum( $this->getTestUser( [ 'sysop' ] )->getUser() );
		$this->assertNotFalse( $forum, 'Forum should exist' );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );

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

		$forum->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$threadTitle = 'Test Thread ' . wfRandomString( 10 );
		$result = $forum->addThread( $threadTitle, 'Thread text' );
		// addThread returns HTML, check that it's not an error message
		$this->assertIsString( $result );
		$this->assertStringNotContainsString( 'wikiforum-error', $result, 'Thread creation should not return error' );

		// Check if thread was created - use DB_PRIMARY to read after write in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$threadData = $dbw->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread_name' => $threadTitle ],
			__METHOD__
		);
		$this->assertNotFalse( $threadData, 'Thread should exist' );
		$thread = WFThread::newFromSQL( $threadData );
		$thread->forum = $forum;
		// Set context on thread for future operations - use the same context with request
		$thread->setContext( $context );
		return $thread;
	}

	/**
	 * Test creating a reply from ID
	 */
	public function testNewFromID() {
		$user = $this->getTestUser()->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Reply ' . wfRandomString( 10 );
		$thread->addReply( $replyText );

		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies );
		$reply = $replies[0];
		$replyId = $reply->getId();

		$replyFromId = WFReply::newFromID( $replyId );
		$this->assertNotFalse( $replyFromId );
		$this->assertEquals( $replyText, $replyFromId->getText() );
		$this->assertEquals( $replyId, $replyFromId->getId() );

		// Test with non-existent ID
		$nonExistent = WFReply::newFromID( 999999 );
		$this->assertFalse( $nonExistent );
	}

	/**
	 * Test creating a reply from text
	 */
	public function testNewFromText() {
		$user = $this->getTestUser()->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Reply Text ' . wfRandomString( 10 );
		$thread->addReply( $replyText );

		$reply = WFReply::newFromText( $replyText );
		$this->assertNotFalse( $reply );
		$this->assertEquals( $replyText, $reply->getText() );
	}

	/**
	 * Test creating a reply from SQL row
	 */
	public function testNewFromSQL() {
		$user = $this->getTestUser()->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Reply SQL ' . wfRandomString( 10 );
		$thread->addReply( $replyText );

		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies );
		$reply = $replies[0];

		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sqlData = $dbr->selectRow(
			'wikiforum_replies',
			'*',
			[ 'wfr_reply_id' => $reply->getId() ],
			__METHOD__
		);

		$replyFromSQL = WFReply::newFromSQL( $sqlData );
		$this->assertInstanceOf( WFReply::class, $replyFromSQL );
		$this->assertEquals( $replyText, $replyFromSQL->getText() );
	}

	/**
	 * Test getters
	 */
	public function testGetters() {
		$user = $this->getTestUser()->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Reply Getters ' . wfRandomString( 10 );
		$thread->addReply( $replyText );

		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies );
		$reply = $replies[0];

		$this->assertEquals( $replyText, $reply->getText() );
		// getId() may return string from DB, so check it's numeric
		$this->assertIsNumeric( $reply->getId() );
		$this->assertGreaterThan( 0, (int)$reply->getId() );
		$this->assertIsString( $reply->getEditedTimestamp() );
	}

	/**
	 * Test adding a reply
	 */
	public function testAdd() {
		$user = $this->getTestUser()->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Add Reply ' . wfRandomString( 10 );
		$result = $thread->addReply( $replyText );

		$this->assertIsString( $result );

		// Verify reply was created
		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies );
		$this->assertEquals( $replyText, $replies[0]->getText() );
	}

	/**
	 * Test editing a reply
	 */
	public function testEdit() {
		// Use a user with moderator rights to allow editing
		$user = $this->getTestUser( [ 'sysop' ] )->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Edit Reply ' . wfRandomString( 10 );
		$thread->addReply( $replyText );

		// Reload thread to get fresh replies list - use DB_PRIMARY to read after write in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$threadData = $dbw->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread' => $thread->getId() ],
			__METHOD__
		);
		$this->assertNotFalse( $threadData, 'Thread should exist' );
		$thread = WFThread::newFromSQL( $threadData );
		$thread->setContext( $context );
		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies, 'Thread should have one reply' );
		$reply = $replies[0];
		$this->assertNotFalse( $reply, 'Reply should exist' );
		$replyId = $reply->getId();
		$reply->setContext( $context );

		// Verify user is the author of the reply
		$this->assertEquals( $user->getActorId(), $reply->getPostedById(), 'User should be the author of the reply' );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $context->getRequest() );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		// Ensure token is set in request for edit
		$token = $user->getEditToken( '', $context->getRequest() );
		$context->getRequest()->setVal( 'wpToken', $token );

		$newText = 'Edited reply text ' . wfRandomString( 10 );
		$result = $reply->edit( $newText );

		// edit() may return true if text didn't change, or string HTML
		$this->assertTrue( is_string( $result ) || $result === true, 'edit() should return string or true' );
		// Check that edit() didn't return an error
		if ( is_string( $result ) ) {
			$this->assertStringNotContainsString( 'wikiforum-error', $result, 'edit() should not return error' );
		}

		// Verify changes - reload reply from DB using DB_PRIMARY to read after write in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$replyData = $dbw->selectRow(
			'wikiforum_replies',
			'*',
			[ 'wfr_reply_id' => $replyId ],
			__METHOD__
		);
		$this->assertNotFalse( $replyData, 'Reply should exist' );
		$this->assertEquals( $newText, $replyData->wfr_reply_text, 'Reply text should be updated' );
	}

	/**
	 * Test deleting a reply
	 */
	public function testDelete() {
		$user = $this->getTestUser()->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Delete Reply ' . wfRandomString( 10 );
		$thread->addReply( $replyText );

		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies );
		$reply = $replies[0];
		$this->assertNotFalse( $reply, 'Reply should exist' );
		$replyId = $reply->getId();
		$reply->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );

		$result = $reply->delete();
		$this->assertIsString( $result );

		// Verify reply was deleted
		$deletedReply = WFReply::newFromID( $replyId );
		$this->assertFalse( $deletedReply );
	}

	/**
	 * Test show methods return HTML
	 */
	public function testShowMethods() {
		$user = $this->getTestUser()->getUser();
		$thread = $this->createTestThread( $user );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		// Thread already has context set in createTestThread, use the same request
		$context = $thread->getContext();
		$request = $context->getRequest();
		// Get token with the request to ensure session matches
		$token = $user->getEditToken( '', $request );
		// Update token in the existing request
		$request->setVal( 'wpToken', $token );
		$thread->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );
		$globalContext->setRequest( $request );

		$replyText = 'Test Show Reply ' . wfRandomString( 10 );
		$thread->addReply( $replyText );

		$replies = $thread->getReplies();
		$this->assertCount( 1, $replies );
		$reply = $replies[0];
		$this->assertNotFalse( $reply, 'Reply should exist' );
		$reply->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $user );

		$showResult = $reply->show();
		$this->assertIsString( $showResult );
		$this->assertNotEmpty( $showResult );
		$this->assertStringContainsString( $replyText, $showResult );
	}
}
