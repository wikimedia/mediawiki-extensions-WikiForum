<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;

/**
 * Static class containing all the hooked functions used by WikiForum.
 *
 * @file
 */
class WikiForumHooks {

	/**
	 * Set up the two new parser hooks: <WikiForumList> and <WikiForumThread>
	 *
	 * @param Parser $parser
	 * @return bool true
	 */
	public static function registerParserHooks( $parser ) {
		$parser->setHook( 'WikiForumList', [ __CLASS__, 'renderWikiForumList' ] );
		$parser->setHook( 'WikiForumThread', [ __CLASS__, 'renderWikiForumThread' ] );
		return true;
	}

	/**
	 * Callback for <WikiForumList> tag.
	 * Takes only the following argument: num (used as the LIMIT for the SQL query)
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function renderWikiForumList( $input, $args, Parser $parser, $frame ) {
		$parser->getOutput()->addModuleStyles( [ 'ext.wikiForum' ] );

		if ( !isset( $args['num'] ) ) {
			$args['num'] = 5;
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$sqlThreads = $dbr->select(
			[ 'wikiforum_threads' ],
			[ '*' ],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'wft_last_post_timestamp DESC',
				'LIMIT' => max( 1, intval( $args['num'] ) )
			]
		);

		$output = Html::openElement( 'table', [ 'class' => 'mw-wikiforum-mainpage' ] );
		$output .= WikiForumGui::showMainHeaderRow(
			wfMessage( 'wikiforum-updates' )->escaped(),
			wfMessage( 'wikiforum-replies' )->escaped(),
			wfMessage( 'wikiforum-views' )->escaped(),
			wfMessage( 'wikiforum-latest-reply' )->escaped()
		);

		foreach ( $sqlThreads as $threadData ) {
			$thread = WFThread::newFromSQL( $threadData );

			$output .= $thread->showTagListItem();
		}
		$output .= Html::closeElement( 'table' );

		return $output;
	}

	/**
	 * Callback for the <WikiForumThread> hook.
	 * Takes the following arguments: id (ID number of the thread, used in SQL
	 * query), replies (whether to display replies)
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function renderWikiForumThread( $input, $args, Parser $parser, $frame ) {
		$parser->getOutput()->addModuleStyles( [ 'ext.wikiForum' ] );

		if ( !isset( $args['id'] ) || $args['id'] == 0 ) {
			return wfMessage( 'wikiforum-must-supply-thread' )->escaped();
		}

		$thread = WFThread::newFromID( $args['id'] );

		if ( !$thread ) {
			return wfMessage( 'wikiforum-thread-not-found-text' )->escaped();
		}

		$user = $parser->getUserIdentity();
		$output = WikiForumGui::showHeaderRow( $thread->showHeaderLinks(), $user );

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
	 * For the Echo extension: register our new presentation model with Echo so
	 * Echo knows how it should display our notifications in it.
	 *
	 * @param array &$notifications Echo notifications
	 * @param array &$notificationCategories Echo notification categories
	 * @param array &$icons Icon details
	 */
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories, &$icons ) {
		$notificationCategories['wikiforum-comment'] = [
			'tooltip' => 'echo-pref-tooltip-wikiforum-comment',
		];
		$notifications['wikiforum-comment'] = [
			'category' => 'wikiforum-comment',
			'group' => 'interactive',
			'section' => 'alert',
			'presentation-model' => EchoMentionWikiForumCommentPresentationModel::class,
			EchoAttributeManager::ATTR_LOCATORS => [
				[ 'EchoUserLocator::locateFromEventExtra', [ 'mentioned-users' ] ]
			],
			'icon' => 'mention',
		];
	}

	/**
	 * It's like Article::prepareTextForEdit,
	 *  but not for editing (old wikitext usually)
	 * Stolen from AbuseFilterVariableHolder
	 *
	 * Used inline in WFReply.php; stolen from Comments as of 17 February 2022,
	 * made public and adapted because we can't use an Article here directly :(
	 *
	 * @param string $wikitext
	 * @param WFThread $thread
	 *
	 * @return ParserOutput
	 */
	public static function parseNonEditWikitext( $wikitext, WFThread $thread ) {
		static $cache = [];
		$cacheKey = md5( $wikitext ) . ':' . $thread->getName();
		if ( isset( $cache[$cacheKey] ) ) {
			return $cache[$cacheKey];
		}
		$parser = MediaWikiServices::getInstance()->getParser();
		$options = new ParserOptions( RequestContext::getMain()->getUser() );
		// @todo FIXME: Using Title::newFromText() like this may not be that safe
		// because a wiki page and a forum thread CAN have the same title, and
		// in that case I expect this code to behave in unusual and unexpected ways...
		$output = $parser->parse( $wikitext, Title::newFromText( $thread->getName() ), $options );
		$cache[$cacheKey] = $output;
		return $output;
	}

	/**
	 * For an action taken on a talk page, notify users whose user pages are linked.
	 *
	 * @note Literally stolen from Echo's EchoDiscussionParser (@REL1_33) and
	 * modified to be less Revision-centric.
	 *
	 * Used inline in WFReply.php; stolen from Comments as of 17 February 2022
	 * and adapted a bit: mainly changed the last two parameters to one ($thread).
	 *
	 * @param string $header The subject line for the discussion.
	 * @param int[] $userLinks
	 * @param string $content The content of the post, as a wikitext string.
	 * @param Title $title
	 * @param User $agent The user who made the comment.
	 * @param WFThread $thread The forum thread where a reply was written to
	 * @param int $replyId Internal identifier of the reply
	 */
	public static function generateMentionEvents(
		$header,
		$userLinks,
		$content,
		Title $title,
		User $agent,
		WFThread $thread,
		int $replyId
	) {
		global $wgEchoMaxMentionsCount, $wgEchoMentionStatusNotifications;

		if ( !$title ) {
			return;
		}

		if ( !$userLinks ) {
			return;
		}

		$userMentions = EchoDiscussionParser::getUserMentions( $title, $agent->getId(), $userLinks );
		// @todo If this EchoDiscussionParser method is ever made public, we can just use it here instead
		// of inlining its functionality:
		// $overallMentionsCount = EchoDiscussionParser::getOverallUserMentionsCount( $userMentions );
		$overallMentionsCount = count( $userMentions, COUNT_RECURSIVE ) - count( $userMentions );
		if ( $overallMentionsCount === 0 ) {
			return;
		}

		$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();

		$threadId = $thread->getId(); // added
		$threadName = $thread->getName(); // added

		if ( $overallMentionsCount > $wgEchoMaxMentionsCount ) {
			if ( $wgEchoMentionStatusNotifications ) {
				EchoEvent::create( [
					'type' => 'mention-failure-too-many',
					'title' => $title,
					'extra' => [
						'max-mentions' => $wgEchoMaxMentionsCount,
						'section-title' => $header,
						'thread-name' => $threadName, // added
						'thread-id' => $threadId, // added
						'reply-id' => $replyId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.failure-too-many' );
			}
			return;
		}

		if ( $userMentions['validMentions'] ) {
			EchoEvent::create( [
				'type' => 'wikiforum-comment',
				'title' => $title,
				'extra' => [
					'content' => $content,
					'section-title' => $header,
					'thread-name' => $threadName, // added
					'thread-id' => $threadId, // added
					'reply-id' => $replyId, // added
					'mentioned-users' => $userMentions['validMentions'],
				],
				'agent' => $agent,
			] );
		}

		if ( $wgEchoMentionStatusNotifications ) {
			// TODO batch?
			foreach ( $userMentions['validMentions'] as $mentionedUserId ) {
				EchoEvent::create( [
					'type' => 'mention-success',
					'title' => $title,
					'extra' => [
						'subject-name' => User::newFromId( $mentionedUserId )->getName(),
						'section-title' => $header,
						'thread-name' => $threadName, // added
						'thread-id' => $threadId, // added
						'reply-id' => $replyId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.success' );
			}

			// TODO batch?
			foreach ( $userMentions['anonymousUsers'] as $anonymousUser ) {
				EchoEvent::create( [
					'type' => 'mention-failure',
					'title' => $title,
					'extra' => [
						'failure-type' => 'user-anonymous',
						'subject-name' => $anonymousUser,
						'section-title' => $header,
						'thread-name' => $threadName, // added
						'thread-id' => $threadId, // added
						'reply-id' => $replyId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.failure-user-anonymous' );
			}

			// TODO batch?
			foreach ( $userMentions['unknownUsers'] as $unknownUser ) {
				EchoEvent::create( [
					'type' => 'mention-failure',
					'title' => $title,
					'extra' => [
						'failure-type' => 'user-unknown',
						'subject-name' => $unknownUser,
						'section-title' => $header,
						'thread-name' => $threadName, // added
						'thread-id' => $threadId, // added
						'reply-id' => $replyId, // added
						'notifyAgent' => true
					],
					'agent' => $agent,
				] );
				$stats->increment( 'echo.event.mention.notification.failure-user-unknown' );
			}
		}
	}

	/**
	 * Adds the four new tables to the database when the user runs
	 * maintenance/update.php.
	 *
	 * Also runs other database upgrades for users upgrading from an older version
	 * of WikiForum.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

		$db = $updater->getDB();
		$file = "$dir/wikiforum.sql";
		// @todo Split into one table per file
		if ( $db->getType() === 'postgres' ) {
			$file = "$dir/wikiforum.postgres.sql";
		}

		$updater->addExtensionTable( 'wikiforum_category', $file );
		$updater->addExtensionTable( 'wikiforum_forums', $file );
		$updater->addExtensionTable( 'wikiforum_threads', $file );
		$updater->addExtensionTable( 'wikiforum_replies', $file );

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

		$updater->addExtensionField( 'wikiforum_category', 'wfc_edited_timestamp', "$dir/patches/add-wfc_edited_timestamp-to-wikiforum_category.sql" );
		$updater->addExtensionField( 'wikiforum_threads', 'wft_closed_timestamp', "$dir/patches/add-wft_closed_timestamp-to-wikiforum_threads.sql" );

		$updater->addExtensionUpdate( [
			'runMaintenance',
			'MigrateOldWikiForumTimestampColumnsToNew',
			'../maintenance/migrateOldWikiForumTimestampColumnsToNew.php'
		] );

		$updater->dropExtensionField( 'wikiforum_category', 'wfc_edited', "$dir/patches/drop-wfc_edited-from-wikiforum_category.sql" );
		$updater->dropExtensionField( 'wikiforum_threads', 'wft_closed', "$dir/patches/drop-wft_closed-from-wikiforum_threads.sql" );

		$updater->dropExtensionField( 'wikiforum_category', 'wfc_deleted', "$dir/patches/drop-wfc_deleted-from-wikiforum_category.sql" );
		$updater->dropExtensionField( 'wikiforum_category', 'wfc_deleted_actor', "$dir/patches/drop-wfc_deleted_actor-from-wikiforum_category.sql" );
		$updater->dropExtensionField( 'wikiforum_category', 'wfc_deleted_user_ip', "$dir/patches/drop-wfc_deleted_user_ip-from-wikiforum_category.sql" );
		$updater->dropExtensionField( 'wikiforum_forums', 'wff_deleted', "$dir/patches/drop-wff_deleted-from-wikiforum_forums.sql" );
		$updater->dropExtensionField( 'wikiforum_forums', 'wff_deleted_actor', "$dir/patches/drop-wff_deleted_actor-from-wikiforum_forums.sql" );
		$updater->dropExtensionField( 'wikiforum_forums', 'wff_deleted_user_ip', "$dir/patches/drop-wff_deleted_user_ip-from-wikiforum_forums.sql" );
		$updater->dropExtensionField( 'wikiforum_threads', 'wft_deleted', "$dir/patches/drop-wft_deleted-from-wikiforum_threads.sql" );
		$updater->dropExtensionField( 'wikiforum_threads', 'wft_deleted_actor', "$dir/patches/drop-wft_deleted_actor-from-wikiforum_threads.sql" );
		$updater->dropExtensionField( 'wikiforum_threads', 'wft_deleted_user_ip', "$dir/patches/drop-wft_deleted_user_ip-from-wikiforum_threads.sql" );
		$updater->dropExtensionField( 'wikiforum_replies', 'wfr_deleted', "$dir/patches/drop-wfr_deleted-from-wikiforum_replies.sql" );
		$updater->dropExtensionField( 'wikiforum_replies', 'wft_deleted_actor', "$dir/patches/drop-wfr_deleted_actor-from-wikiforum_replies.sql" );
		$updater->dropExtensionField( 'wikiforum_replies', 'wft_deleted_user_ip', "$dir/patches/drop-wfr_deleted_user_ip-from-wikiforum_replies.sql" );

		$updater->modifyExtensionField( 'wikiforum_category', 'wfc_added_actor', "$dir/patches/actor/add-default-to-wfc_added_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_category', 'wfc_edited_actor', "$dir/patches/actor/add-default-to-wfc_edited_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_forums', 'wff_last_post_actor', "$dir/patches/actor/add-default-to-wff_last_post_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_forums', 'wff_added_actor', "$dir/patches/actor/add-default-to-wff_added_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_forums', 'wff_edited_actor', "$dir/patches/actor/add-default-to-wff_edited_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_threads', 'wft_actor', "$dir/patches/actor/add-default-to-wft_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_threads', 'wft_edit_actor', "$dir/patches/actor/add-default-to-wft_edit_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_threads', 'wft_closed_actor', "$dir/patches/actor/add-default-to-wft_closed_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_threads', 'wft_last_post_actor', "$dir/patches/actor/add-default-to-wft_last_post_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_replies', 'wfr_actor', "$dir/patches/actor/add-default-to-wfr_actor.sql" );
		$updater->modifyExtensionField( 'wikiforum_replies', 'wfr_edit_actor', "$dir/patches/actor/add-default-to-wfr_edit_actor.sql" );
	}
}
