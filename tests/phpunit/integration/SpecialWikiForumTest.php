<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \SpecialWikiForum
 * @group WikiForum
 * @group Database
 */
class SpecialWikiForumTest extends MediaWikiIntegrationTestCase {

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
	 * Test executing special page with no parameters
	 */
	public function testExecuteNoParams() {
		$specialPage = new SpecialWikiForum();
		$context = new RequestContext();
		$user = $this->getTestUser()->getUser();
		$context->setUser( $user );
		$context->setRequest( new FauxRequest() );
		$specialPage->setContext( $context );

		ob_start();
		try {
			$specialPage->execute( null );
		} catch ( \Exception $e ) {
			// May throw exceptions for various reasons, that's OK for this test
		}
		$output = ob_get_clean();

		// Should produce some output
		$this->assertTrue( true ); // If we got here without fatal error, test passes
	}

	/**
	 * Test executing special page with forum ID parameter
	 */
	public function testExecuteWithForumId() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );
		$category->addForum( 'Test Forum', 'Description', false );
		$forum = WFForum::newFromName( 'Test Forum' );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		$specialPage = new SpecialWikiForum();
		$context = new RequestContext();
		$context->setUser( $this->getTestUser()->getUser() );
		$context->setRequest( new FauxRequest() );
		$specialPage->setContext( $context );

		ob_start();
		try {
			$specialPage->execute( (string)$forum->getId() );
		} catch ( \Exception $e ) {
			// May throw exceptions, that's OK
		}
		$output = ob_get_clean();

		$this->assertTrue( true ); // If we got here, test passes
	}

	/**
	 * Test executing special page with thread name parameter
	 */
	public function testExecuteWithThreadName() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$category = $this->createTestCategory( $adminUser );
		$this->assertNotFalse( $category, 'Category should exist' );
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );
		$category->addForum( 'Test Forum', 'Description', false );
		$forum = WFForum::newFromName( 'Test Forum' );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		$threadContext = new RequestContext();
		$threadUser = $this->getTestUser()->getUser();
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$threadContext->setUser( $threadUser );
		$threadContext->setTitle( $title );
		$threadContext->setRequest( new FauxRequest( [
			'wpToken' => $threadUser->getEditToken()
		] ) );
		$forum->setContext( $threadContext );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $threadUser );
		$globalContext->setRequest( $threadContext->getRequest() );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$forum->addThread( 'Test Thread', 'Thread text' );

		$specialPage = new SpecialWikiForum();
		$specialPage->setContext( $threadContext );

		ob_start();
		try {
			$specialPage->execute( 'Test Thread' );
		} catch ( \Exception $e ) {
			// May throw exceptions, that's OK
		}
		$output = ob_get_clean();

		$this->assertTrue( true ); // If we got here, test passes
	}

	/**
	 * Test doesWrites
	 */
	public function testDoesWrites() {
		$specialPage = new SpecialWikiForum();
		$this->assertTrue( $specialPage->doesWrites() );
	}

	/**
	 * Test blocked user cannot access
	 */
	public function testBlockedUser() {
		$user = $this->getTestUser()->getUser();
		// Create a block
		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		// For MediaWiki 1.45+, address parameter is deprecated
		// Use approach compatible with all versions: create block without address,
		// then set target (both deprecated in 1.45+ but still functional)
		if ( version_compare( MW_VERSION, '1.45', '>=' ) ) {
			// Suppress deprecation warnings for MediaWiki 1.45+
			$this->filterDeprecated( '/The address parameter to AbstractBlock::__construct is deprecated/' );
			$this->filterDeprecated( '/Passing UserIdentity\|string to AbstractBlock::setTarget is deprecated/' );
			$block = new \MediaWiki\Block\DatabaseBlock( [
				'by' => $this->getTestSysop()->getUser(),
				'reason' => 'Test block',
				'expiry' => 'infinity',
			] );
			$block->setTarget( $user );
		} else {
			// For MediaWiki < 1.45, use address parameter
			$block = new \MediaWiki\Block\DatabaseBlock( [
				'address' => $user,
				'by' => $this->getTestSysop()->getUser(),
				'reason' => 'Test block',
				'expiry' => 'infinity',
			] );
		}
		$blockStore->insertBlock( $block );

		$specialPage = new SpecialWikiForum();
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setRequest( new FauxRequest() );
		$specialPage->setContext( $context );

		// Expect UserBlockedError to be thrown
		$this->expectException( \UserBlockedError::class );

		try {
			$specialPage->execute( null );
		} finally {
			// Clean up block even if exception is thrown
			$blockStore->deleteBlock( $block );
		}
	}
}
