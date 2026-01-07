<?php

use MediaWiki\Parser\Parser;

/**
 * @covers \WikiForumHooks
 * @group WikiForum
 * @group Database
 */
class WikiForumHooksTest extends MediaWikiIntegrationTestCase {

	/** @var string[] */
	protected $tablesUsed = [];

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'wikiforum_threads';
	}

	/**
	 * Test registerParserHooks
	 */
	public function testRegisterParserHooks() {
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->exactly( 2 ) )
			->method( 'setHook' )
			->withConsecutive(
				[ 'WikiForumList', $this->isType( 'array' ) ],
				[ 'WikiForumThread', $this->isType( 'array' ) ]
			);

		$result = WikiForumHooks::registerParserHooks( $parser );
		$this->assertTrue( $result );
	}

	/**
	 * Test renderWikiForumList
	 */
	public function testRenderWikiForumList() {
		$parserFactory = $this->getServiceContainer()->getParserFactory();
		$parser = $parserFactory->create();
		$title = \MediaWiki\Title\Title::makeTitle( NS_MAIN, 'Test' );
		$options = \MediaWiki\Parser\ParserOptions::newFromAnon();
		// Initialize parser by parsing empty text
		$parser->parse( '', $title, $options );

		$preprocessor = $parser->getPreprocessor();
		if ( !$preprocessor ) {
			$this->markTestSkipped( 'Preprocessor not available' );
			return;
		}
		$frame = $this->createMock( \MediaWiki\Parser\PPFrame::class );

		$input = '';
		$args = [ 'num' => '5' ];

		$result = WikiForumHooks::renderWikiForumList( $input, $args, $parser, $frame );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'table', $result );
	}

	/**
	 * Test renderWikiForumList with default num
	 */
	public function testRenderWikiForumListDefaultNum() {
		$parserFactory = $this->getServiceContainer()->getParserFactory();
		$parser = $parserFactory->create();
		$title = \MediaWiki\Title\Title::makeTitle( NS_MAIN, 'Test' );
		$options = \MediaWiki\Parser\ParserOptions::newFromAnon();
		// Initialize parser by parsing empty text
		$parser->parse( '', $title, $options );

		$frame = $this->createMock( \MediaWiki\Parser\PPFrame::class );

		$input = '';
		$args = []; // No num specified

		$result = WikiForumHooks::renderWikiForumList( $input, $args, $parser, $frame );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test renderWikiForumThread without ID
	 */
	public function testRenderWikiForumThreadNoId() {
		$parserFactory = $this->getServiceContainer()->getParserFactory();
		$parser = $parserFactory->create();
		$title = \MediaWiki\Title\Title::makeTitle( NS_MAIN, 'Test' );
		$options = \MediaWiki\Parser\ParserOptions::newFromAnon();
		// Initialize parser by parsing empty text
		$parser->parse( '', $title, $options );

		$frame = $this->createMock( \MediaWiki\Parser\PPFrame::class );

		$input = '';
		$args = []; // No id specified

		$result = WikiForumHooks::renderWikiForumThread( $input, $args, $parser, $frame );
		$this->assertIsString( $result );
		// Should return error message
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test renderWikiForumThread with invalid ID
	 */
	public function testRenderWikiForumThreadInvalidId() {
		$parserFactory = $this->getServiceContainer()->getParserFactory();
		$parser = $parserFactory->create();
		$title = \MediaWiki\Title\Title::makeTitle( NS_MAIN, 'Test' );
		$options = \MediaWiki\Parser\ParserOptions::newFromAnon();
		// Initialize parser by parsing empty text
		$parser->parse( '', $title, $options );

		$frame = $this->createMock( \MediaWiki\Parser\PPFrame::class );

		$input = '';
		$args = [ 'id' => '999999' ]; // Non-existent ID

		$result = WikiForumHooks::renderWikiForumThread( $input, $args, $parser, $frame );
		$this->assertIsString( $result );
		// Should return error message
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test renderWikiForumThread with valid ID
	 */
	public function testRenderWikiForumThreadValidId() {
		// Create a test thread first
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new \MediaWiki\Request\FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Category ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );
		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category, 'Category should exist' );

		$categoryContext = new \MediaWiki\Context\RequestContext();
		$categoryContext->setUser( $adminUser );
		$categoryContext->setRequest( new \MediaWiki\Request\FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $categoryContext );
		$category->addForum( 'Test Forum', 'Description', false );
		$forum = WFForum::newFromName( 'Test Forum' );
		$this->assertNotFalse( $forum, 'Forum should exist' );

		$threadUser = $this->getTestUser()->getUser();
		$title = \MediaWiki\Title\Title::makeTitle( NS_SPECIAL, 'WikiForum' );

		// Create POST request first (without token) - this creates a session
		$request = new \MediaWiki\Request\FauxRequest( [], true );
		$context = new \MediaWiki\Context\RequestContext();
		$context->setUser( $threadUser );
		$context->setTitle( $title );
		$context->setRequest( $request );

		// Get token using the same request - this ensures session matches
		$token = $threadUser->getEditToken( '', $request );

		// Set token in the request
		$request->setVal( 'wpToken', $token );
		$forum->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = \MediaWiki\Context\RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $threadUser );
		$globalContext->setRequest( $request );
		// Update global $wgOut
		global $wgOut;
		$wgOut = $globalContext->getOutput();

		$result = $forum->addThread( 'Test Thread', 'Thread text' );
		// addThread returns HTML string, not the thread object
		$this->assertIsString( $result, 'addThread should return HTML string' );
		// Check that result doesn't contain error messages (basic sanity check)
		$this->assertStringNotContainsString( 'wikiforum-error', $result,
			'addThread should not return error message' );

		// Use primary DB to read immediately after write (same as WFThread::add does internally)
		$dbw = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$threadData = $dbw->selectRow(
			'wikiforum_threads',
			'*',
			[ 'wft_thread_name' => 'Test Thread' ],
			__METHOD__
		);

		// If thread not found, provide more diagnostic information
		if ( !$threadData ) {
			// Check if any threads exist in this forum
			$allThreads = $dbw->select(
				'wikiforum_threads',
				[ 'wft_thread_id', 'wft_thread_name', 'wft_forum' ],
				[ 'wft_forum' => $forum->getId() ],
				__METHOD__
			);
			$threadNames = [];
			foreach ( $allThreads as $row ) {
				$threadNames[] = $row->wft_thread_name;
			}
			$this->fail( 'Thread not found after addThread. Forum ID: ' . $forum->getId() .
				', Thread name searched: "Test Thread", Existing threads: ' .
				( empty( $threadNames ) ? '(none)' : implode( ', ', $threadNames ) ) .
				', addThread result length: ' . strlen( $result ) );
		}

		$thread = WFThread::newFromSQL( $threadData );
		$this->assertNotFalse( $thread, 'Thread should exist' );

		$parserFactory = $this->getServiceContainer()->getParserFactory();
		$parser = $parserFactory->create();
		$title = \MediaWiki\Title\Title::makeTitle( NS_MAIN, 'Test' );
		$options = \MediaWiki\Parser\ParserOptions::newFromAnon();
		// Initialize parser by parsing empty text
		$parser->parse( '', $title, $options );

		$frame = $this->createMock( \MediaWiki\Parser\PPFrame::class );

		$input = '';
		$args = [ 'id' => (string)$thread->getId() ];

		$result = WikiForumHooks::renderWikiForumThread( $input, $args, $parser, $frame );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test onLoadExtensionSchemaUpdates exists
	 */
	public function testOnLoadExtensionSchemaUpdatesExists() {
		$this->assertTrue( method_exists( 'WikiForumHooks', 'onLoadExtensionSchemaUpdates' ) );
	}
}
