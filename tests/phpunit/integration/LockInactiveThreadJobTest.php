<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \LockInactiveThreadJob
 * @group WikiForum
 * @group Database
 */
class LockInactiveThreadJobTest extends MediaWikiIntegrationTestCase {

	/** @var string[] */
	protected $tablesUsed = [];

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'wikiforum_category';
		$this->tablesUsed[] = 'wikiforum_forums';
		$this->tablesUsed[] = 'wikiforum_threads';
		$this->tablesUsed[] = 'job';
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
	 * Test job with invalid thread ID
	 */
	public function testJobInvalidThreadId() {
		$job = new LockInactiveThreadJob( [ 'threadId' => null ] );
		$result = $job->run();
		$this->assertFalse( $result );
		$this->assertNotEmpty( $job->getLastError() );
	}

	/**
	 * Test job with non-existent thread
	 */
	public function testJobNonExistentThread() {
		$job = new LockInactiveThreadJob( [ 'threadId' => 999999 ] );
		$result = $job->run();
		// Should return true (nothing to do, thread doesn't exist)
		$this->assertTrue( $result );
	}

	/**
	 * Test job with active thread (should not lock)
	 */
	public function testJobActiveThread() {
		$this->overrideConfigValue( 'WikiForumAutoLockInactiveHours', 24 );

		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$thread = $this->createTestThread( $adminUser );
		$threadId = $thread->getId();

		$job = new LockInactiveThreadJob( [ 'threadId' => $threadId ] );
		$result = $job->run();

		// Thread is recent, should not be locked
		$this->assertTrue( $result );

		// Verify thread is not locked
		$updatedThread = WFThread::newFromID( $threadId );
		$this->assertFalse( $updatedThread->isClosed() );
	}

	/**
	 * Test job with inactive thread (should lock)
	 */
	public function testJobInactiveThread() {
		$this->overrideConfigValue( 'WikiForumAutoLockInactiveHours', 1 );

		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$thread = $this->createTestThread( $adminUser );
		$threadId = $thread->getId();

		// Make thread old by updating timestamp
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$oldTimestamp = wfTimestamp( TS_MW, time() - 7200 ); // 2 hours ago
		$dbw->update(
			'wikiforum_threads',
			[
				'wft_posted_timestamp' => $dbw->timestamp( $oldTimestamp ),
				'wft_last_post_timestamp' => $dbw->timestamp( $oldTimestamp )
			],
			[ 'wft_thread' => $threadId ],
			__METHOD__
		);

		$job = new LockInactiveThreadJob( [ 'threadId' => $threadId ] );
		$result = $job->run();

		// Should successfully lock the thread
		$this->assertTrue( $result );

		// Verify thread is locked
		$updatedThread = WFThread::newFromID( $threadId );
		$this->assertTrue( $updatedThread->isClosed() );
	}

	/**
	 * Test job with already locked thread
	 */
	public function testJobAlreadyLockedThread() {
		$this->overrideConfigValue( 'WikiForumAutoLockInactiveHours', 1 );

		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$thread = $this->createTestThread( $adminUser );
		$threadId = $thread->getId();

		// Manually lock the thread
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$thread->setContext( $context );
		$thread->close();

		$job = new LockInactiveThreadJob( [ 'threadId' => $threadId ] );
		$result = $job->run();

		// Should return true (nothing to do, already locked)
		$this->assertTrue( $result );
	}

	/**
	 * Test job with auto-lock disabled
	 */
	public function testJobAutoLockDisabled() {
		$this->overrideConfigValue( 'WikiForumAutoLockInactiveHours', null );

		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$thread = $this->createTestThread( $adminUser );
		$threadId = $thread->getId();

		$job = new LockInactiveThreadJob( [ 'threadId' => $threadId ] );
		$result = $job->run();

		// Should return true (auto-lock disabled)
		$this->assertTrue( $result );

		// Verify thread is not locked
		$updatedThread = WFThread::newFromID( $threadId );
		$this->assertFalse( $updatedThread->isClosed() );
	}
}
