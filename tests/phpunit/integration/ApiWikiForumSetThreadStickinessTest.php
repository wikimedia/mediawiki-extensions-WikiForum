<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * @covers \ApiWikiForumSetThreadStickiness
 * @group WikiForum
 * @group Database
 */
class ApiWikiForumSetThreadStickinessTest extends ApiTestCase {

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
	 * Helper to create a test forum
	 * @param \MediaWiki\User\User $user
	 * @return WFForum
	 */
	private function createTestForum( $user ) {
		$category = $this->createTestCategory( $user );
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
		$forum = $this->createTestForum( $user );
		$this->assertNotFalse( $forum, 'Forum should exist' );
		$threadUser = $this->getTestUser()->getUser();
		$threadTitle = 'Test Thread ' . wfRandomString( 10 );

		$request = new FauxRequest( [], true );
		$context = new RequestContext();
		$context->setUser( $threadUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$context->setRequest( $request );

		// Get token using the same request
		$token = $threadUser->getEditToken( '', $request );
		$request->setVal( 'wpToken', $token );

		$forum->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $threadUser );
		$globalContext->setRequest( $request );
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$result = $forum->addThread( $threadTitle, 'Thread text' );
		$this->assertIsString( $result, 'addThread should return HTML string' );
		$this->assertStringNotContainsString( 'wikiforum-error', $result, 'Thread should be created without errors' );

		$thread = WFThread::newFromName( $threadTitle );
		$this->assertNotFalse( $thread, 'Thread should be created' );
		return $thread;
	}

	/**
	 * Test setting thread stickiness
	 */
	public function testSetSticky() {
		$adminUser = $this->getTestSysop()->getUser();
		$thread = $this->createTestThread( $adminUser );
		$this->assertNotFalse( $thread, 'Thread should exist' );
		$threadId = $thread->getId();

		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-set-thread-stickiness',
			'id' => $threadId,
			'stickiness' => 'set'
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-set-thread-stickiness', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-set-thread-stickiness']['status'] );

		// Verify thread is sticky
		$updatedThread = WFThread::newFromID( $threadId );
		$this->assertTrue( $updatedThread->isSticky() );
	}

	/**
	 * Test removing thread stickiness
	 */
	public function testRemoveSticky() {
		$adminUser = $this->getTestSysop()->getUser();
		$thread = $this->createTestThread( $adminUser );
		$this->assertNotFalse( $thread, 'Thread should exist' );
		$threadId = $thread->getId();

		// First make it sticky
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$thread->setContext( $context );
		$thread->makeSticky();

		// Then remove stickiness via API
		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-set-thread-stickiness',
			'id' => $threadId,
			'stickiness' => 'remove'
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-set-thread-stickiness', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-set-thread-stickiness']['status'] );

		// Verify thread is not sticky
		$updatedThread = WFThread::newFromID( $threadId );
		$this->assertFalse( $updatedThread->isSticky() );
	}

	/**
	 * Test without permissions
	 */
	public function testWithoutPermissions() {
		$adminUser = $this->getTestSysop()->getUser();
		$thread = $this->createTestThread( $adminUser );
		$this->assertNotFalse( $thread, 'Thread should exist' );
		$threadId = $thread->getId();

		$regularUser = $this->getTestUser()->getUser();

		try {
			$this->doApiRequestWithToken( [
				'action' => 'wikiforum-set-thread-stickiness',
				'id' => $threadId,
				'stickiness' => 'set'
			], null, $regularUser );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( \MediaWiki\Api\ApiUsageException $e ) {
			$this->assertTrue( true ); // Expected exception
		}
	}

	/**
	 * Test with invalid ID
	 */
	public function testInvalidId() {
		$adminUser = $this->getTestSysop()->getUser();

		try {
			$this->doApiRequestWithToken( [
				'action' => 'wikiforum-set-thread-stickiness',
				'id' => 999999,
				'stickiness' => 'set'
			], null, $adminUser );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( \MediaWiki\Api\ApiUsageException $e ) {
			$this->assertTrue( true ); // Expected exception
		}
	}
}
