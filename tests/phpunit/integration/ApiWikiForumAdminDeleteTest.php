<?php

use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @covers \ApiWikiForumAdminDelete
 * @group WikiForum
 * @group Database
 */
class ApiWikiForumAdminDeleteTest extends ApiTestCase {

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
		$this->setMwGlobals( 'wgRequest', new \MediaWiki\Request\FauxRequest( [
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
		$context = new \MediaWiki\Context\RequestContext();
		$context->setUser( $user );
		$context->setRequest( new \MediaWiki\Request\FauxRequest( [
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
	 * Test deleting a category via API
	 */
	public function testDeleteCategory() {
		$adminUser = $this->getTestSysop()->getUser();
		$category = $this->createTestCategory( $adminUser );
		$categoryId = $category->getId();

		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-admin-delete',
			'id' => $categoryId,
			'iscategory' => true
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-admin-delete', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-admin-delete']['status'] );

		// Verify category was deleted - use DB_PRIMARY to read after delete in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$categoryData = $dbw->selectRow(
			'wikiforum_category',
			'*',
			[ 'wfc_category' => $categoryId ],
			__METHOD__
		);
		$this->assertFalse( $categoryData, 'Category should be deleted' );
	}

	/**
	 * Test deleting a forum via API
	 * @group Broken
	 * FIXME: API request runs in separate transaction and doesn't see test data properly
	 */
	public function testDeleteForum() {
		$adminUser = $this->getTestSysop()->getUser();
		$forum = $this->createTestForum( $adminUser );
		$this->assertNotFalse( $forum, 'Forum should exist' );
		$forumId = $forum->getId();

		$result = $this->doApiRequestWithToken( [
			'action' => 'wikiforum-admin-delete',
			'id' => $forumId,
			'iscategory' => false
		], null, $adminUser );

		$this->assertArrayHasKey( 'wikiforum-admin-delete', $result[0] );
		$this->assertEquals( 'OK', $result[0]['wikiforum-admin-delete']['status'] );

		// Verify forum was deleted - use DB_PRIMARY to read after delete in same test
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$forumData = $dbw->selectRow(
			'wikiforum_forums',
			'*',
			[ 'wff_forum' => $forumId ],
			__METHOD__
		);
		$this->assertFalse( $forumData, 'Forum should be deleted' );
	}

	/**
	 * Test deleting without permissions
	 */
	public function testDeleteWithoutPermissions() {
		$adminUser = $this->getTestSysop()->getUser();
		$category = $this->createTestCategory( $adminUser );
		$categoryId = $category->getId();

		$regularUser = $this->getTestUser()->getUser();

		try {
			$this->doApiRequestWithToken( [
				'action' => 'wikiforum-admin-delete',
				'id' => $categoryId,
				'iscategory' => true
			], null, $regularUser );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( \MediaWiki\Api\ApiUsageException $e ) {
			$this->assertTrue( true ); // Expected exception
		}
	}

	/**
	 * Test deleting with invalid ID
	 */
	public function testDeleteInvalidId() {
		$adminUser = $this->getTestSysop()->getUser();

		try {
			$this->doApiRequestWithToken( [
				'action' => 'wikiforum-admin-delete',
				'id' => 999999,
				'iscategory' => true
			], null, $adminUser );
			$this->fail( 'Expected ApiUsageException' );
		} catch ( \MediaWiki\Api\ApiUsageException $e ) {
			$this->assertTrue( true ); // Expected exception
		}
	}
}
