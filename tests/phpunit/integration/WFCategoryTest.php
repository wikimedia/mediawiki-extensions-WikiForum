<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \WFCategory
 * @group WikiForum
 * @group Database
 */
class WFCategoryTest extends MediaWikiIntegrationTestCase {

	/** @var string[] */
	protected $tablesUsed = [];

	protected function setUp(): void {
		parent::setUp();
		// Ensure tables exist
		$this->tablesUsed[] = 'wikiforum_category';
		$this->tablesUsed[] = 'wikiforum_forums';
	}

	/**
	 * Test creating a category from ID
	 */
	public function testNewFromID() {
		// Create a test category
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Category ' . wfRandomString( 10 );
		$result = WFCategory::add( $categoryName, $adminUser );
		$this->assertIsString( $result );

		// Find the category by name to get its ID
		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category, 'Category should be created' );
		$categoryId = $category->getId();

		// Test newFromID
		$categoryFromId = WFCategory::newFromID( $categoryId );
		$this->assertNotFalse( $categoryFromId, 'Should find category by ID' );
		$this->assertEquals( $categoryName, $categoryFromId->getName() );
		$this->assertEquals( $categoryId, $categoryFromId->getId() );

		// Test with non-existent ID
		$nonExistent = WFCategory::newFromID( 999999 );
		$this->assertFalse( $nonExistent, 'Should return false for non-existent ID' );
	}

	/**
	 * Test creating a category from name
	 */
	public function testNewFromName() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Category Name ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category, 'Should find category by name' );
		$this->assertEquals( $categoryName, $category->getName() );

		// Test with non-existent name
		$nonExistent = WFCategory::newFromName( 'NonExistentCategory' . wfRandomString( 20 ) );
		$this->assertFalse( $nonExistent, 'Should return false for non-existent name' );
	}

	/**
	 * Test creating a category from SQL row
	 */
	public function testNewFromSQL() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Category SQL ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );

		// Get raw SQL data
		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sqlData = $dbr->selectRow(
			'wikiforum_category',
			'*',
			[ 'wfc_category' => $category->getId() ],
			__METHOD__
		);

		// Test newFromSQL
		$categoryFromSQL = WFCategory::newFromSQL( $sqlData );
		$this->assertInstanceOf( WFCategory::class, $categoryFromSQL );
		$this->assertEquals( $categoryName, $categoryFromSQL->getName() );
		$this->assertEquals( $category->getId(), $categoryFromSQL->getId() );
	}

	/**
	 * Test getters
	 */
	public function testGetters() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Category Getters ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );

		$this->assertEquals( $categoryName, $category->getName() );
		// getId() may return string from DB, so check it's numeric
		$this->assertIsNumeric( $category->getId() );
		$this->assertGreaterThan( 0, (int)$category->getId() );
	}

	/**
	 * Test getForums
	 */
	public function testGetForums() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();

		// Create request with proper token setup
		$request = new FauxRequest( [], true );
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$context->setRequest( $request );

		// Get token using the same request
		$token = $adminUser->getEditToken( '', $request );
		$request->setVal( 'wpEditToken', $token );

		// Set in global context AND global $wgRequest (WFCategory::add uses it)
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );
		$globalContext->setRequest( $request );
		global $wgOut, $wgRequest;
		$wgOut = $globalContext->getOutput();
		$wgRequest = $request;

		$categoryName = 'Test Category Forums ' . wfRandomString( 10 );
		$addResult = WFCategory::add( $categoryName, $adminUser );
		// add() returns HTML string
		$this->assertIsString( $addResult, 'add() should return HTML' );
		$this->assertStringNotContainsString( 'wikiforum-error', $addResult, 'add() should not return error. Got: ' . $addResult );

		// Use DB_PRIMARY to read immediately after write in the same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		// Debug: check what's in the database
		$allCategories = $dbw->select(
			'wikiforum_category',
			[ 'wfc_category', 'wfc_category_name' ],
			[],
			__METHOD__
		);
		$catList = [];
		foreach ( $allCategories as $row ) {
			$catList[] = $row->wfc_category . ': ' . $row->wfc_category_name;
		}

		$categoryData = $dbw->selectRow(
			'wikiforum_category',
			'*',
			[ 'wfc_category_name' => $categoryName ],
			__METHOD__
		);
		$this->assertNotFalse(
			$categoryData,
			'Category "' . $categoryName . '" should exist. Found categories: ' . implode( ', ', $catList )
		);
		$category = WFCategory::newFromSQL( $categoryData );
		$category->setContext( $context );

		// Initially should have no forums
		$forums = $category->getForums();
		$this->assertIsArray( $forums );
		$this->assertCount( 0, $forums );

		// Add a forum to the category
		$forumName = 'Test Forum ' . wfRandomString( 10 );
		$result = $category->addForum( $forumName, 'Forum description', false );
		$this->assertIsString( $result );
		$this->assertStringNotContainsString( 'wikiforum-error', $result, 'Forum should be added' );

		// Reload category from DB to clear cache - use DB_PRIMARY to read after write in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$categoryData = $dbw->selectRow(
			'wikiforum_category',
			'*',
			[ 'wfc_category_name' => $categoryName ],
			__METHOD__
		);
		$this->assertNotFalse( $categoryData, 'Category should exist' );
		$category = WFCategory::newFromSQL( $categoryData );
		$category->setContext( $context );
		$forums = $category->getForums();
		$this->assertCount( 1, $forums );
		$this->assertInstanceOf( WFForum::class, $forums[0] );
		$this->assertEquals( $forumName, $forums[0]->getName() );
	}

	/**
	 * Test adding a category
	 */
	public function testAdd() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Add Category ' . wfRandomString( 10 );
		$result = WFCategory::add( $categoryName, $adminUser );

		$this->assertIsString( $result );
		$this->assertStringContainsString( $categoryName, $result );

		// Verify category was created
		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );
		$this->assertEquals( $categoryName, $category->getName() );
	}

	/**
	 * Test adding category without permissions
	 */
	public function testAddWithoutPermissions() {
		$regularUser = $this->getTestUser()->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $regularUser->getEditToken()
		] ) );

		$categoryName = 'Test Add Category No Perms ' . wfRandomString( 10 );
		$result = WFCategory::add( $categoryName, $regularUser );

		// Should return error message
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'error', strtolower( $result ) );

		// Category should not be created
		$category = WFCategory::newFromName( $categoryName );
		$this->assertFalse( $category );
	}

	/**
	 * Test editing a category
	 */
	public function testEdit() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Edit Category ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );

		// Set up context for edit
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );
		$category->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );

		$newName = 'Edited Category Name ' . wfRandomString( 10 );
		$result = $category->edit( $newName );

		$this->assertIsString( $result );

		// Verify name was changed
		$updatedCategory = WFCategory::newFromID( $category->getId() );
		$this->assertNotFalse( $updatedCategory );
		$this->assertEquals( $newName, $updatedCategory->getName() );
	}

	/**
	 * Test editing category without permissions
	 */
	public function testEditWithoutPermissions() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$regularUser = $this->getTestUser()->getUser();

		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Edit Category No Perms ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );

		// Set up context with regular user
		$context = new RequestContext();
		$context->setUser( $regularUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $regularUser->getEditToken()
		] ) );
		$category->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $regularUser );

		$newName = 'Unauthorized Edit ' . wfRandomString( 10 );
		$result = $category->edit( $newName );

		// Should return error message
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'error', strtolower( $result ) );

		// Name should not be changed
		$updatedCategory = WFCategory::newFromID( $category->getId() );
		$this->assertNotFalse( $updatedCategory );
		$this->assertEquals( $categoryName, $updatedCategory->getName() );
	}

	/**
	 * Test deleting a category
	 */
	public function testDelete() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Delete Category ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );
		$categoryId = $category->getId();

		// Set up context
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$category->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );

		$result = $category->delete();
		$this->assertIsString( $result );

		// Verify category was deleted
		$deletedCategory = WFCategory::newFromID( $categoryId );
		$this->assertFalse( $deletedCategory, 'Category should be deleted' );
	}

	/**
	 * Test deleting category without permissions
	 */
	public function testDeleteWithoutPermissions() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$regularUser = $this->getTestUser()->getUser();

		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Delete Category No Perms ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );
		$categoryId = $category->getId();

		// Set up context with regular user
		$context = new RequestContext();
		$context->setUser( $regularUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$category->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $regularUser );

		$result = $category->delete();

		// Should return error message
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'error', strtolower( $result ) );

		// Category should not be deleted
		$stillExists = WFCategory::newFromID( $categoryId );
		$this->assertNotFalse( $stillExists, 'Category should still exist' );
	}

	/**
	 * Test sorting categories
	 */
	public function testSortUp() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		// Create two categories
		$category1Name = 'Test Sort Category 1 ' . wfRandomString( 10 );
		$category2Name = 'Test Sort Category 2 ' . wfRandomString( 10 );
		WFCategory::add( $category1Name, $adminUser );
		WFCategory::add( $category2Name, $adminUser );

		$category2 = WFCategory::newFromName( $category2Name );
		$this->assertNotFalse( $category2 );

		// Set up context
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$category2->setContext( $context );

		$result = $category2->sortUp();
		$this->assertIsString( $result );
	}

	/**
	 * Test sorting categories down
	 */
	public function testSortDown() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		// Create two categories
		$category1Name = 'Test Sort Down Category 1 ' . wfRandomString( 10 );
		$category2Name = 'Test Sort Down Category 2 ' . wfRandomString( 10 );
		WFCategory::add( $category1Name, $adminUser );
		WFCategory::add( $category2Name, $adminUser );

		$category1 = WFCategory::newFromName( $category1Name );
		$this->assertNotFalse( $category1 );

		// Set up context
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$category1->setContext( $context );

		$result = $category1->sortDown();
		$this->assertIsString( $result );
	}

	/**
	 * Test show methods return HTML
	 */
	public function testShowMethods() {
		$adminUser = $this->getTestUser( [ 'sysop' ] )->getUser();
		$this->setMwGlobals( 'wgRequest', new FauxRequest( [
			'wpEditToken' => $adminUser->getEditToken()
		] ) );

		$categoryName = 'Test Show Category ' . wfRandomString( 10 );
		WFCategory::add( $categoryName, $adminUser );

		$category = WFCategory::newFromName( $categoryName );
		$this->assertNotFalse( $category );

		// Set up context
		$context = new RequestContext();
		$context->setUser( $adminUser );
		$title = Title::makeTitle( NS_SPECIAL, 'WikiForum' );
		$context->setTitle( $title );
		$category->setContext( $context );

		// Set title in global context for methods that use OutputPage::parseAsContent
		$globalContext = RequestContext::getMain();
		$globalContext->setTitle( $title );
		$globalContext->setUser( $adminUser );

		$showResult = $category->show();
		$this->assertIsString( $showResult );
		$this->assertNotEmpty( $showResult );

		$showMainResult = $category->showMain();
		$this->assertIsString( $showMainResult );
		$this->assertNotEmpty( $showMainResult );

		$showLinkResult = $category->showLink();
		$this->assertIsString( $showLinkResult );
		$this->assertStringContainsString( $categoryName, $showLinkResult );
	}
}
