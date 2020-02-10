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
	 * @return Boolean true
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

		$dbr = wfGetDB( DB_REPLICA );
		$sqlThreads = $dbr->select(
			[ 'wikiforum_threads' ],
			[ '*' ],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'wft_last_post_timestamp DESC',
				'LIMIT' => intval( $args['num'] )
			]
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

		$output = WikiForumGui::showHeaderRow( $thread->showHeaderLinks(), $parser->getUser() );

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
	 * Also runs other database upgrades for users upgrading from an older version
	 * of WikiForum.
	 *
	 * @param $updater DatabaseUpdater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';
		$file = "$dir/wikiforum.sql";

		$updater->addExtensionTable( 'wikiforum_category', $file );
		$updater->addExtensionTable( 'wikiforum_forums', $file );
		$updater->addExtensionTable( 'wikiforum_threads', $file );
		$updater->addExtensionTable( 'wikiforum_replies', $file );

		$db = $updater->getDB();
		// upgrade from pre 1.3.0-SW
		if ( !$db->fieldExists( 'wikiforum_category', 'wfc_added_user_ip' ) ) {
			$file = $dir . '/1.3.0-SW-new-fields.sql';
			// wikiforum_category
			$updater->addExtensionField( 'wikiforum_category', 'wfc_added_user_ip', $file );
			$updater->addExtensionField( 'wikiforum_category', 'wfc_edited_user_ip', $file );
			// wikiforum_forums
			$updater->addExtensionField( 'wikiforum_forums', 'wff_last_post_user_ip', $file );
			$updater->addExtensionField( 'wikiforum_forums', 'wff_added_user_ip', $file );
			$updater->addExtensionField( 'wikiforum_forums', 'wff_edited_user_ip', $file );
			// wikiforum_threads
			$updater->addExtensionField( 'wikiforum_threads', 'wft_user_ip', $file );
			$updater->addExtensionField( 'wikiforum_threads', 'wft_edit_user_ip', $file );
			$updater->addExtensionField( 'wikiforum_threads', 'wft_closed_user_ip', $file );
			$updater->addExtensionField( 'wikiforum_threads', 'wft_last_post_user_ip', $file );
			// wikiforum_replies
			$updater->addExtensionField( 'wikiforum_replies', 'wfr_user_ip', $file );
			$updater->addExtensionField( 'wikiforum_replies', 'wfr_edit_user_ip', $file );
		} elseif ( $db->fieldExists( 'wikiforum_category', 'wfc_added_user_text' ) ) {
			// Upgrade from post 1.3.0-SW and pre 2.0.0
			$file = $dir . '/2.0.0-drop-fields.sql';
			// wikiforum_category
			$updater->dropExtensionField( 'wikiforum_category', 'wfc_added_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_category', 'wfc_edited_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_category', 'wfc_deleted_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_category', 'wfc_deleted_user_ip', $file );
			// wikiforum_forums
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_last_post_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_added_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_edited_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_deleted_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_deleted_user_ip', $file );
			// wikiforum_threads
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_deleted_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_deleted_user_ip', $file );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_edit_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_closed_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_last_post_user_text', $file );
			// wikiforum_replies
			$updater->dropExtensionField( 'wikiforum_replies', 'wfr_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_replies', 'wfr_deleted_user_text', $file );
			$updater->dropExtensionField( 'wikiforum_replies', 'wfr_deleted_user_ip', $file );
			$updater->dropExtensionField( 'wikiforum_replies', 'wfr_edit_user_text', $file );
		}

		// Slightly an overkill, given that I didn't bother splitting out the huge files
		// into one-query-per-file, but whatever...
		// The existence of *any* of these fields means that we are upgrading from a pre-actor
		// version of WikiForum and we need to add in the actor columns
		if (
			$db->fieldExists( 'wikiforum_category', 'wfc_added_user' ) ||
			$db->fieldExists( 'wikiforum_category', 'wfc_edited_user' ) ||
			$db->fieldExists( 'wikiforum_category', 'wfc_deleted_user' ) ||
			$db->fieldExists( 'wikiforum_forums', 'wff_last_post_user' ) ||
			$db->fieldExists( 'wikiforum_forums', 'wff_added_user' ) ||
			$db->fieldExists( 'wikiforum_forums', 'wff_edited_user' ) ||
			$db->fieldExists( 'wikiforum_forums', 'wff_deleted_user' ) ||
			$db->fieldExists( 'wikiforum_threads', 'wft_user' ) ||
			$db->fieldExists( 'wikiforum_threads', 'wft_deleted_user' ) ||
			$db->fieldExists( 'wikiforum_threads', 'wft_edit_user' ) ||
			$db->fieldExists( 'wikiforum_threads', 'wft_closed_user' ) ||
			$db->fieldExists( 'wikiforum_threads', 'wft_last_post_user' ) ||
			$db->fieldExists( 'wikiforum_replies', 'wfr_user' ) ||
			$db->fieldExists( 'wikiforum_replies', 'wfr_deleted_user' ) ||
			$db->fieldExists( 'wikiforum_replies', 'wfr_edit_user' )
		) {
			// 1. add the new actor columns for each table

			// wikiforum_category
			$updater->addExtensionField( 'wikiforum_category', 'wfc_added_actor', "$dir/patches/actor/add-wfc_added_actor-to-wikiforum_category.sql" );
			$updater->addExtensionField( 'wikiforum_category', 'wfc_edited_actor', "$dir/patches/actor/add-wfc_edited_actor-to-wikiforum_category.sql" );
			$updater->addExtensionField( 'wikiforum_category', 'wfc_deleted_actor', "$dir/patches/actor/add-wfc_deleted_actor-to-wikiforum_category.sql" );
			// wikiforum_forums
			$updater->addExtensionField( 'wikiforum_forums', 'wff_last_post_actor', "$dir/patches/actor/add-wff_last_post_actor-to-wikiforum_forums.sql" );
			$updater->addExtensionField( 'wikiforum_forums', 'wff_added_actor', "$dir/patches/actor/add-wff_added_actor-to-wikiforum_forums.sql" );
			$updater->addExtensionField( 'wikiforum_forums', 'wff_edited_actor', "$dir/patches/actor/add-wff_edited_actor-to-wikiforum_forums.sql" );
			$updater->addExtensionField( 'wikiforum_forums', 'wff_deleted_actor', "$dir/patches/actor/add-wff_deleted_actor-to-wikiforum_forums.sql" );
			// wikiforum_threads
			$updater->addExtensionField( 'wikiforum_threads', 'wft_actor', "$dir/patches/actor/add-wft_actor-to-wikiforum_threads.sql" );
			$updater->addExtensionField( 'wikiforum_threads', 'wft_deleted_actor', "$dir/patches/actor/add-wft_deleted_actor-to-wikiforum_threads.sql" );
			$updater->addExtensionField( 'wikiforum_threads', 'wft_edit_actor', "$dir/patches/actor/add-wft_edit_actor-to-wikiforum_threads.sql" );
			$updater->addExtensionField( 'wikiforum_threads', 'wft_closed_actor', "$dir/patches/actor/add-wft_closed_actor-to-wikiforum_threads.sql" );
			$updater->addExtensionField( 'wikiforum_threads', 'wft_last_post_actor', "$dir/patches/actor/add-wft_last_post_actor-to-wikiforum_threads.sql" );
			// wikiforum_replies
			$updater->addExtensionField( 'wikiforum_replies', 'wfr_actor', "$dir/patches/actor/add-wfr_actor-to-wikiforum_replies.sql" );
			$updater->addExtensionField( 'wikiforum_replies', 'wfr_deleted_actor', "$dir/patches/actor/add-wfr_deleted_actor-to-wikiforum_replies.sql" );
			$updater->addExtensionField( 'wikiforum_replies', 'wfr_edit_actor', "$dir/patches/actor/add-wfr_edit_actor-to-wikiforum_replies.sql" );

			// 2. migrate old data to the new actor fields
			// PITFALL WARNING! Do NOT change this to $updater->runMaintenance,
			// THEY ARE NOT THE SAME THING and this MUST be using addExtensionUpdate
			// instead for the code to work as desired!
			// HT Skizzerz
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldWikiForumUserColumnsToActor',
				'../maintenance/migrateOldWikiForumUserColumnsToActor.php'
			] );

			// 3. drop old, now unused fields

			// wikiforum_category
			$updater->dropExtensionField( 'wikiforum_category', 'wfc_added_user', "$dir/patches/actor/drop-wfc_added_user-from-wikiforum_category.sql" );
			$updater->dropExtensionField( 'wikiforum_category', 'wfc_edited_user', "$dir/patches/actor/drop-wfc_edited_user-from-wikiforum_category.sql" );
			$updater->dropExtensionField( 'wikiforum_category', 'wfc_deleted_user', "$dir/patches/actor/drop-wfc_deleted_user-from-wikiforum_category.sql" );
			// wikiforum_forums
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_last_post_user', "$dir/patches/actor/drop-wff_last_post_user-from-wikiforum_forums.sql" );
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_added_user', "$dir/patches/actor/drop-wff_added_user-from-wikiforum_forums.sql" );
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_edited_user', "$dir/patches/actor/drop-wff_edited_user-from-wikiforum_forums.sql" );
			$updater->dropExtensionField( 'wikiforum_forums', 'wff_deleted_user', "$dir/patches/actor/drop-wff_deleted_user-from-wikiforum_forums.sql" );
			// wikiforum_threads
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_user', "$dir/patches/actor/drop-wft_user-from-wikiforum_threads.sql" );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_deleted_user', "$dir/patches/actor/drop-wft_deleted_user-from-wikiforum_threads.sql" );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_edit_user', "$dir/patches/actor/drop-wft_edit_user-from-wikiforum_threads.sql" );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_closed_user', "$dir/patches/actor/drop-wft_closed_user-from-wikiforum_threads.sql" );
			$updater->dropExtensionField( 'wikiforum_threads', 'wft_last_post_user', "$dir/patches/actor/drop-wft_last_post_user-from-wikiforum_threads.sql" );
			// wikiforum_replies
			$updater->dropExtensionField( 'wikiforum_replies', 'wfr_user', "$dir/patches/actor/drop-wfr_user-from-wikiforum_replies.sql" );
			$updater->dropExtensionField( 'wikiforum_replies', 'wfr_deleted_user', "$dir/patches/actor/drop-wfr_deleted_user-from-wikiforum_replies.sql" );
			$updater->dropExtensionField( 'wikiforum_replies', 'wfr_edit_user', "$dir/patches/actor/drop-wfr_edit_user-from-wikiforum_replies.sql" );
		}
	}
}
