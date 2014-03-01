<?php
/**
 * Static class containing all the hooked functions used by WikiForum.
 *
 * @file
 */
class WikiForumHooks {

	/**
	 * Set up the two new parser hooks: <WikiForumList> and <WikiForumThread>
	 *
	 * @param $parser Object: instance of Parser
	 * @return Boolean: true
	 */
	public static function registerParserHooks( &$parser ) {
		$parser->setHook( 'WikiForumList', 'WikiForumHooks::renderWikiForumList' );
		$parser->setHook( 'WikiForumThread', 'WikiForumHooks::renderWikiForumThread' );
		return true;
	}

	/**
	 * Callback for <WikiForumList> tag.
	 * Takes only the following argument: num (used as the LIMIT for the SQL query)
	 */
	public static function renderWikiForumList( $input, $args, Parser $parser, $frame ) {
		$parser->getOutput()->addModuleStyles( 'ext.wikiForum' );

		if ( !isset( $args['num'] ) ) {
			$args['num'] = 5;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$sqlThreads = $dbr->select(
			array( 'wikiforum_threads' ),
			array( '*' ),
			array(),
			__METHOD__,
			array(
				'ORDER BY' => 'wft_last_post_timestamp DESC',
				'LIMIT' => intval( $args['num'] )
			)
		);

		$output = WikiForumGui::showListTagHeader(
			wfMessage( 'wikiforum-updates' )->text(),
			wfMessage( 'wikiforum-replies' )->text(),
			wfMessage( 'wikiforum-views' )->text(),
			wfMessage( 'wikiforum-latest-reply' )->text()
		);

		foreach ( $sqlThreads as $threadData ) {
			$thread = WFThread::newFromSQL( $threadData );

			$output .= $thread->showTagListItem();
		}

		$output .= WikiForumGui::showListTagFooter();

		return $output;
	}

	/**
	 * Callback for the <WikiForumThread> hook.
	 * Takes the following arguments: id (ID number of the thread, used in SQL
	 * query), replies (whether to display replies)
	 */
	public static function renderWikiForumThread( $input, $args, Parser $parser, $frame ) {
		$parser->getOutput()->addModuleStyles( 'ext.wikiForum' );

		if ( !isset( $args['id'] ) || $args['id'] == 0 ) {
			return wfMessage( 'wikiforum-must-supply-thread' )->text();
		}

		$thread = WFThread::newFromID( $args['id'] );

		if ( !$thread ) {
			return wfMessage( 'wikiforum-thread-not-found-text' )->text();
		}

		$output = WikiForumGui::showHeaderRow( $thread->showHeaderLinks() );

		$posted = $thread->showPostedInfo();
		if ( $thread->getEditedTimestamp() > 0 ) {
			$posted .= '<br /><i>' . $thread->showEditedInfo() . '</i>';
		}
		$output .= $thread->showHeader( $posted );

		if ( isset( $args['replies'] ) && $args['replies'] ) {
			$replies = $thread->getReplies();

			foreach ( $replies as $reply ) {
				$output .= $reply->show();
			}
		}

		$output .= $thread->showFooter();

		return $output;
	}

	/**
	 * Adds the four new tables to the database when the user runs
	 * maintenance/update.php.
	 *
	 * @param $updater DatabaseUpdater
	 * @return Boolean: true
	 */
	public static function addTables( $updater ) {
		$dir = dirname( __FILE__ ) . '/sql';
		$file = "$dir/wikiforum.sql";

		$updater->addExtensionUpdate( array( 'addTable', 'wikiforum_category', $file, true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'wikiforum_forums', $file, true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'wikiforum_threads', $file, true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'wikiforum_replies', $file, true ) );

		// upgrade from pre 1.3.0-SW
		if ( !$updater->getDB()->fieldExists( 'wikiforum_category', 'wfc_added_user_ip' ) ) {
			$file = $dir . '/1.3.0-SW-new-fields.sql';
			// wikiforum_category
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_category', 'wfc_added_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_category', 'wfc_edited_user_ip', $file, true ) );
			// wikiforum_forums
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_forums', 'wff_last_post_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_forums', 'wff_added_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_forums', 'wff_edited_user_ip', $file, true ) );
			// wikiforum_threads
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_threads', 'wft_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_threads', 'wft_edit_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_threads', 'wft_closed_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_threads', 'wft_last_post_user_ip', $file, true ) );
			// wikiforum_replies
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_replies', 'wfr_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'addField', 'wikiforum_replies', 'wfr_edit_user_ip', $file, true ) );
		}

		// Upgrade from post 1.3.0-SW and pre 2.0.0
		else if ( $updater->getDB()->fieldExists( 'wikiforum_category', 'wfc_added_user_user_text' ) ) {
			$file = $dir . '/2.0.0-drop-fields.sql';
			// wikiforum_category
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_category', 'wfc_added_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_category', 'wfc_edited_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_category', 'wfc_deleted_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_category', 'wfc_deleted_user_ip', $file, true ) );
			// wikiforum_forums
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_forums', 'wff_last_post_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_forums', 'wff_added_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_forums', 'wff_edited_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_forums', 'wff_deleted_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_forums', 'wff_deleted_user_ip', $file, true ) );
			// wikiforum_threads
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_threads', 'wft_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_threads', 'wft_deleted_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_threads', 'wft_deleted_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_threads', 'wft_edit_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_threads', 'wft_closed_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_threads', 'wft_last_post_user_text', $file, true ) );
			// wikiforum_replies
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_replies', 'wfr_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_replies', 'wfr_deleted_user_text', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_replies', 'wfr_deleted_user_ip', $file, true ) );
			$updater->addExtensionUpdate( array( 'dropField', 'wikiforum_replies', 'wfr_edit_user_text', $file, true ) );
		}

		return true;
	}
}
