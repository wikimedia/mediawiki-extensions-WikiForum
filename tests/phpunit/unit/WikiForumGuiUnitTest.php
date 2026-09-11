<?php

/**
 * @covers \WikiForumGui
 * @group WikiForum
 */
class WikiForumGuiUnitTest extends MediaWikiUnitTestCase {

	/**
	 * Test showMainHeaderRow with 4 parameters
	 */
	public function testShowMainHeaderRowFourParams() {
		$result = WikiForumGui::showMainHeaderRow( 'Title1', 'Title2', 'Title3', 'Title4' );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Title1', $result );
		$this->assertStringContainsString( 'Title2', $result );
		$this->assertStringContainsString( 'Title3', $result );
		$this->assertStringContainsString( 'Title4', $result );
		$this->assertStringContainsString( '<tr', $result );
		$this->assertStringContainsString( '<th', $result );
		$this->assertStringNotContainsString( 'mw-wikiforum-admin', $result );
	}

	/**
	 * Test showMainHeaderRow with 5 parameters (with admin column)
	 */
	public function testShowMainHeaderRowFiveParams() {
		$result = WikiForumGui::showMainHeaderRow( 'Title1', 'Title2', 'Title3', 'Title4', 'Admin' );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Title1', $result );
		$this->assertStringContainsString( 'Title2', $result );
		$this->assertStringContainsString( 'Title3', $result );
		$this->assertStringContainsString( 'Title4', $result );
		$this->assertStringContainsString( 'Admin', $result );
		$this->assertStringContainsString( 'mw-wikiforum-admin', $result );
	}

	/**
	 * Test showMainHeaderRow with empty title5
	 */
	public function testShowMainHeaderRowEmptyTitle5() {
		$result = WikiForumGui::showMainHeaderRow( 'Title1', 'Title2', 'Title3', 'Title4', '' );
		$this->assertIsString( $result );
		$this->assertStringNotContainsString( 'mw-wikiforum-admin', $result );
	}

}
