<?php

/**
 * @covers \WikiForumGui
 * @group WikiForum
 */
class WikiForumGuiUnitTest extends MediaWikiUnitTestCase {

	/**
	 * Test showFrameHeader
	 */
	public function testShowFrameHeader() {
		$result = WikiForumGui::showFrameHeader();
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-wikiforum-frame', $result );
		$this->assertStringContainsString( '<table', $result );
	}

	/**
	 * Test showFrameFooter
	 */
	public function testShowFrameFooter() {
		$result = WikiForumGui::showFrameFooter();
		$this->assertIsString( $result );
		$this->assertStringContainsString( '</table>', $result );
	}

	/**
	 * Test showListTagFooter
	 */
	public function testShowListTagFooter() {
		$result = WikiForumGui::showListTagFooter();
		$this->assertIsString( $result );
		$this->assertEquals( '</table>', $result );
	}

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

	/**
	 * Test showMainHeader
	 */
	public function testShowMainHeader() {
		$result = WikiForumGui::showMainHeader( 'Title1', 'Title2', 'Title3', 'Title4' );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-wikiforum-frame', $result );
		$this->assertStringContainsString( 'mw-wikiforum-title', $result );
		$this->assertStringContainsString( 'Title1', $result );
	}

	/**
	 * Test showMainHeader with admin column
	 */
	public function testShowMainHeaderWithAdmin() {
		$result = WikiForumGui::showMainHeader( 'Title1', 'Title2', 'Title3', 'Title4', 'Admin' );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Admin', $result );
		$this->assertStringContainsString( 'mw-wikiforum-admin', $result );
	}

	/**
	 * Test showListTagHeader
	 */
	public function testShowListTagHeader() {
		$result = WikiForumGui::showListTagHeader( 'Title1', 'Title2', 'Title3', 'Title4' );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'mw-wikiforum-mainpage', $result );
		$this->assertStringContainsString( 'Title1', $result );
		$this->assertStringContainsString( '<table', $result );
	}

	/**
	 * Test showMainFooter
	 */
	public function testShowMainFooter() {
		$result = WikiForumGui::showMainFooter();
		$this->assertIsString( $result );
		$this->assertStringContainsString( '</table>', $result );
		// Should contain frame footer
		$this->assertStringContainsString( '</td>', $result );
	}

	/**
	 * Test showSearchHeader
	 */
	public function testShowSearchHeader() {
		$result = WikiForumGui::showSearchHeader( 'Search Title' );
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Search Title', $result );
		$this->assertStringContainsString( 'mw-wikiforum-frame', $result );
		$this->assertStringContainsString( 'mw-wikiforum-thread-top', $result );
		$this->assertStringContainsString( '<table', $result );
	}

	/**
	 * Test showSearchHeader with empty title
	 */
	public function testShowSearchHeaderEmptyTitle() {
		$result = WikiForumGui::showSearchHeader( '' );
		$this->assertIsString( $result );
		$this->assertStringContainsString( '<table', $result );
	}
}
