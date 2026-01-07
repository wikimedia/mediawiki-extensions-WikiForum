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

		$context = new \MediaWiki\Context\RequestContext();
		$threadUser = $this->getTestUser()->getUser();
		$context->setUser( $threadUser );
		$context->setRequest( new \MediaWiki\Request\FauxRequest( [
			'wpToken' => $threadUser->getEditToken()
		] ) );
		$forum->setContext( $context );
		$forum->addThread( 'Test Thread', 'Thread text' );
		$thread = WFThread::newFromName( 'Test Thread' );
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
