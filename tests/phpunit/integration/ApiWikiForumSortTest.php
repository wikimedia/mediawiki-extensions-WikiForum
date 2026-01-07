<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @covers \ApiWikiForumSort
 * @group WikiForum
 * @group Database
 */
class ApiWikiForumSortTest extends ApiTestCase {

	/** @var string[] */
	protected $tablesUsed = [];

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'wikiforum_category';
		$this->tablesUsed[] = 'wikiforum_forums';
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
	 * @param WFCategory|null $category
	 * @return WFForum
	 */
	private function createTestForum( $user, $category = null ) {
		if ( !$category ) {
			$category = $this->createTestCategory( $user );
		}
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setRequest( new FauxRequest( [
			'wpEditToken' => $user->getEditToken()
		] ) );
		$category->setContext( $context );
		$forumName = 'Test Forum ' . wfRandomString( 10 );
		$category->addForum( $forumName, 'Forum description', false );

		// Use DB_PRIMARY to read immediately after write in the same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$forumData = $dbw->selectRow(
			'wikiforum_forums',
			'*',
			[ 'wff_forum_name' => $forumName ],
			__METHOD__
		);
		$this->assertNotFalse( $forumData, 'Forum should be created' );
		$forum = WFForum::newFromSQL( $forumData );
		return $forum;
	}

	/**
	 * Test sorting category up
	 */
	public function testSortCategoryUp() {
		$adminUser = $this->getTestSysop()->getUser();
		$category1 = $this->createTestCategory( $adminUser );
		$category2 = $this->createTestCategory( $adminUser );
		$category2Id = $category2->getId();

		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-sort',
			'id' => $category2Id,
			'iscategory' => true,
			'direction' => 'up'
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-sort', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-sort']['status'] );
	}

	/**
	 * Test sorting category down
	 */
	public function testSortCategoryDown() {
		$adminUser = $this->getTestSysop()->getUser();
		$category1 = $this->createTestCategory( $adminUser );
		$category1Id = $category1->getId();

		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-sort',
			'id' => $category1Id,
			'iscategory' => true,
			'direction' => 'down'
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-sort', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-sort']['status'] );
	}

	/**
	 * Test sorting forum up
	 * @group Broken
	 * FIXME: API request runs in separate transaction and doesn't see test data
	 */
	public function testSortForumUp() {
		$adminUser = $this->getTestSysop()->getUser();
		$category = $this->createTestCategory( $adminUser );
		$forum1 = $this->createTestForum( $adminUser, $category );
		$forum2 = $this->createTestForum( $adminUser, $category );
		$forum2Id = $forum2->getId();

		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-sort',
			'id' => $forum2Id,
			'iscategory' => false,
			'direction' => 'up'
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-sort', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-sort']['status'] );
	}

	/**
	 * Test sorting forum down
	 */
	public function testSortForumDown() {
		$adminUser = $this->getTestSysop()->getUser();
		$category = $this->createTestCategory( $adminUser );
		$forum1 = $this->createTestForum( $adminUser, $category );
		$forum2 = $this->createTestForum( $adminUser, $category );
		$forum1Id = $forum1->getId();

		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-sort',
			'id' => $forum1Id,
			'iscategory' => false,
			'direction' => 'down'
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-sort', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-sort']['status'] );
	}

	/**
	 * Test without permissions
	 */
	public function testWithoutPermissions() {
		$adminUser = $this->getTestSysop()->getUser();
		$category = $this->createTestCategory( $adminUser );
		$categoryId = $category->getId();

		$regularUser = $this->getTestUser()->getUser();

		try {
			$this->doApiRequestWithToken( [
				'action' => 'wikiforum-sort',
				'id' => $categoryId,
				'iscategory' => true,
				'direction' => 'up'
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
				'action' => 'wikiforum-sort',
				'id' => 999999,
				'iscategory' => true,
				'direction' => 'up'
			], null, $adminUser );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( \MediaWiki\Api\ApiUsageException $e ) {
			$this->assertTrue( true ); // Expected exception
		}
	}
}
