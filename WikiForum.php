<?php
/**
 * WikiForum -- forum extension for MediaWiki
 *
 * @file
 * @ingroup Extensions
 * @author Michael Chlebek
 * @author Jack Phoenix <jack@countervandalism.net>
 * @date 23 December 2010 (date of this build: 22 January 2011)
 * @version 1.2-SW
 * @copyright Copyright © 2010 Unidentify Studios
 * @copyright Copyright © 2010-2011 Jack Phoenix <jack@countervandalism.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 3.0 or later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if( !defined( 'MEDIAWIKI' ) ) {
	die( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'WikiForum',
	'author' => array( 'Michael Chlebek', 'Jack Phoenix' ),
	'version' => '1.2-SW',
	'url' => 'http://www.mediawiki.org/wiki/Extension:WikiForum',
	'description' => '[[Special:WikiForum|Forum]] extension for MediaWiki',
	'descriptionmsg' => 'wikiforum-desc'
);

// Set up i18n, the new special page etc.
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['WikiForum'] = $dir . 'WikiForum.i18n.php';
$wgExtensionAliasesFiles['WikiForum'] = $dir . 'WikiForum.alias.php';
$wgAutoloadClasses['WikiForumGui'] = $dir . 'WikiForumGui.php';
$wgAutoloadClasses['WikiForumClass'] = $dir . 'WikiForumClass.php';
$wgAutoloadClasses['WikiForum'] = $dir . 'SpecialWikiForum.php';
$wgSpecialPages['WikiForum'] = 'WikiForum';

// New user rights for administrating and moderating the forum
$wgAvailableRights[] = 'wikiforum-admin';
$wgAvailableRights[] = 'wikiforum-moderator';

// New forumadmin group
$wgGroupPermissions['forumadmin']['wikiforum-admin'] = true;
$wgGroupPermissions['forumadmin']['wikiforum-moderator'] = true;

// Allow bureaucrats to add and remove forum administrator status
$wgAddGroups['bureaucrat'][] = 'forumadmin';
$wgRemoveGroups['bureaucrat'][] = 'forumadmin';

// Allow sysops to act as forum administrators, too
$wgGroupPermissions['sysop']['wikiforum-admin'] = true;
$wgGroupPermissions['sysop']['wikiforum-moderator'] = true;

# Configuration parameters
// Allow anonymous users to write threads and replies?
$wgWikiForumAllowAnonymous = true;

// Array of emoticon text forms => image file names
// @todo FIXME: kill this variable WITH FIRE and make an admin-configurable
// way to configure the emoticons (a MediaWiki message maybe?)
$wgWikiForumSmilies = array(
	/*
	':)' => 'icons/emoticon_grin.png',
	// yeah, apparently you have to use &lt; and &gt; instead of < and >
	'&gt;D' => 'icons/emoticon_evilgrin.png',
	*/
);

// Hooked functions
$wgHooks['ParserFirstCallInit'][] = 'wfWikiForumTags';
$wgHooks['SkinTemplateBuildNavUrlsNav_urlsAfterPermalink'][] = 'wfSpecialWikiForumNav';
$wgHooks['SkinTemplateToolboxEnd'][] = 'wfSpecialWikiForumToolbox';

/**
 * Set up the two new parser hooks: <WikiForumList> and <WikiForumThread>
 *
 * @param $parser Object: instance of Parser
 * @return Boolean: true
 */
function wfWikiForumTags( &$parser ) {
	$parser->setHook( 'WikiForumList', 'renderWikiForumList' );
	$parser->setHook( 'WikiForumThread', 'renderWikiForumThread' );
	return true;
}

/**
 * Adds a link to Special:WikiForum to the toolbox, after permalink.
 * Both this and the function below are required to render the link in the
 * toolbox.
 *
 * @param $skinTemplate Object: SkinTemplate instance
 * @param $nav_urls Array: existing navigation URLs
 * @param $oldid Integer
 * @param $revid Integer: revision ID number of the current revision
 * @return Boolean: true
 */
function wfSpecialWikiForumNav( &$skinTemplate, &$nav_urls, &$oldid, &$revid ) {
	$nav_urls['wikiforum'] = array(
		'text' => wfMsg( 'wikiforum' ),
		'href' => $skinTemplate->makeSpecialUrl( 'WikiForum' )
	);
	return true;
}

/**
 * Adds a link to Special:WikiForum to the toolbox, after permalink.
 * Both this and the function above are required to render the link in the
 * toolbox.
 *
 * @param $skinTemplate Object: instance of SkinTemplate class
 * @return Boolean: true
 */
function wfSpecialWikiForumToolbox( &$skinTemplate ) {
	if( isset( $skinTemplate->data['nav_urls']['wikiforum'] ) ) {
		if( $skinTemplate->data['nav_urls']['wikiforum']['href'] == '' ) {
			echo '<li id="t-iswikiforum">' . wfMsg( 'wikiforum' ) . '</li>';
		} else {
			$url = $skinTemplate->data['nav_urls']['wikiforum']['href'];
			echo '<li id="t-wikiforum"><a href="' . htmlspecialchars( $url ) . '">';
			echo wfMsg( 'wikiforum' );
			echo '</a></li>';
		}
	}
	return true;
}

/**
 * Callback for <WikiForumList> tag.
 * Takes only the following argument: num (used as the LIMIT for the SQL query)
 */
function renderWikiForumList( $input, $args, $parser, $frame ) {
	global $wgUser, $wgLang, $wgScriptPath;

	if( !isset( $args['num'] ) ) {
		$args['num'] = 5;
	}

	$dbr = wfGetDB( DB_SLAVE );
	$sqlThreads = $dbr->select(
		array(
			'wikiforum_forums', 'wikiforum_category', 'wikiforum_threads',
			'user'
		),
		array(
			'*', 'wff_forum', 'wff_forum_name', 'wfc_category',
			'wfc_category_name', 'user_name'
		),
		array(
			'wff_deleted' => 0,
			'wfc_deleted' => 0,
			'wft_deleted' => 0,
			'wff_category = wfc_category',
			'wff_forum = wft_forum'
		),
		__METHOD__,
		// it's either wff or wft, I'm not 100% sure which one so I just picked
		// one of them...
		array(
			'ORDER BY' => 'wff_last_post_timestamp DESC',
			'LIMIT' => intval( $args['num'] )
		),
		array( 'user' => array( 'LEFT JOIN', 'user_id = wft_user' ) )
	);

	$output = WikiForumGui::getMainPageHeader(
		wfMsg( 'wikiforum-updates' ),
		wfMsg( 'wikiforum-replies' ),
		wfMsg( 'wikiforum-views' ),
		wfMsg( 'wikiforum-latest-reply' )
	);

	foreach( $sqlThreads as $thread ) {
		$icon = WikiForumClass::getThreadIcon(
			$thread->wft_posted_timestamp,
			$thread->wft_closed,
			$thread->wft_sticky
		);

		$lastpost = '';
		// If there are some replies, then we can obviously figure out who was
		// the last user who posted something on the topic...
		if( $thread->wft_reply_count > 0 ) {
			$lastpost = wfMsg(
				'wikiforum-by',
				$wgLang->timeanddate( $thread->wft_last_post_timestamp ),
				WikiForumClass::getUserLinkById( $thread->wft_last_post_user )
			);
		}

		$specialPageObj = SpecialPage::getTitleFor( 'WikiForum' );
		$sk = $wgUser->getSkin();
		// Build the links to the category and forum pages by using Linker
		$categoryLink = $sk->link(
			$specialPageObj,
			$thread->wfc_category_name,
			array(),
			array( 'category' => $thread->wfc_category )
		);
		$forumLink = $sk->link(
			$specialPageObj,
			$thread->wff_forum_name, 
			array(),
			array( 'forum' => $thread->wff_forum )
		);
		$threadLink = $sk->link(
			$specialPageObj,
			$thread->wft_thread_name,
			array(),
			array( 'thread' => $thread->wft_thread )
		);

		$output .= WikiForumGui::getMainBody(
			'<p class="mw-wikiforum-thread">' . $icon . $threadLink .
			'<p class="mw-wikiforum-descr" style="border-top: 0;">' .
			wfMsg(
				'wikiforum-posted',
				$wgLang->timeanddate( $thread->wft_posted_timestamp ),
				WikiForumClass::getUserLink( $thread->user_name )
			) . '<br />' .
			wfMsgHtml( 'wikiforum-forum', $categoryLink, $forumLink ) .
			'</p></p>',
			$thread->wft_reply_count,
			$thread->wft_view_count,
			$lastpost,
			false,
			false
		);
	}
	$output .= WikiForumGui::getMainPageFooter();

	return $output;
}

/**
 * Callback for the <WikiForumThread> hook.
 * Takes the following arguments: id (ID number of the thread, used in SQL
 * query), replies (whether to display replies)
 */
function renderWikiForumThread( $input, $args, $parser, $frame ) {
	global $wgOut, $wgLang, $wgScriptPath;

	if( isset( $args['id'] ) && $args['id'] > 0 ) {
		$dbr = wfGetDB( DB_SLAVE );
		$sqlThreads = $dbr->select(
			array( 'wikiforum_forums', 'wikiforum_category', 'wikiforum_threads', 'user' ),
			array(
				'wft_thread', 'wft_thread_name', 'wft_text', 'wff_forum',
				'wff_forum_name', 'wfc_category', 'wfc_category_name',
				'user_name', 'user_id', 'wft_edit_timestamp', 'wft_edit_user',
				'wft_posted_timestamp', 'wft_user', 'wft_closed',
				'wft_closed_user'
			),
			array(
				'wff_deleted' => 0,
				'wfc_deleted' => 0,
				'wft_deleted' => 0,
				'wff_category = wfc_category',
				'wff_forum = wft_forum',
				'wft_thread' => intval( $args['id'] )
			),
			__METHOD__,
			array(),
			array( 'user' => array( 'LEFT JOIN', 'user_id = wft_user' ) )
		);
		$overview = $dbr->fetchObject( $sqlThreads );

		if( $overview ) {
			$posted = wfMsg(
				'wikiforum-posted',
				$wgLang->timeanddate( $overview->wft_posted_timestamp ),
				WikiForumClass::getUserLink( $overview->user_name )
			);
			if( $overview->wft_edit_timestamp > 0 ) {
				$posted .= '<br /><i>' .
					wfMsg(
						'wikiforum-edited',
						$wgLang->timeanddate( $overview->wft_edit_timestamp ),
						WikiForumClass::getUserLinkById( $overview->wft_edit_user )
					) . '</i>';
			}

			$output = WikiForumGui::getHeaderRow(
				$overview->wfc_category,
				$overview->wfc_category_name,
				$overview->wff_forum,
				$overview->wff_forum_name,
				false
			);

			$specialPageObj = SpecialPage::getTitleFor( 'WikiForum' );
			$link = $specialPageObj->escapeFullURL( 'thread=' . $overview->wft_thread );

			$output .= WikiForumGui::getThreadHeader(
				'<a href="' . $link . '">' . $overview->wft_thread_name . '</a>',
				$parser->recursiveTagParse( $overview->wft_text, $frame ),
				$posted,
				'',
				$overview->wft_thread,
				$overview->user_id
			);

			if ( isset( $args['replies'] ) && $args['replies'] ) {
				$replies = $dbr->select(
					array( 'wikiforum_replies', 'user' ),
					array( '*', 'user_name' ),
					array( 'wfr_deleted' => 0, 'wfr_thread' => $overview->pkThread ),
					__METHOD__,
					array( 'ORDER BY' => 'wfr_posted_timestamp ASC' ),
					array( 'user' => array( 'LEFT JOIN', 'user_id = wfr_user' ) )
				);

				foreach( $replies as $reply ) {
					$posted = wfMsg(
						'wikiforum-posted',
						$wgLang->timeanddate( $reply->wfr_posted_timestamp ),
						WikiForumClass::getUserLink( $reply->user_name )
					);
					if( $reply->wfr_edit > 0 ) {
						$posted .= '<br /><i>' .
							wfMsg(
								'wikiforum-edited',
								$wgLang->timeanddate( $reply->wfr_edit ),
								WikiForumClass::getUserLinkById( $reply->wfr_edit_user )
							) . '</i>';
					}
					$output .= WikiForumGui::getReply(
						$wgOut->parse( WikiForum::deleteTags( $reply->wfr_reply_text ) ),
						$posted,
						'',
						$reply->wfr_reply_id
					);
				}
			}

			$output .= WikiForumGui::getThreadFooter();
			return $output;
		}
	} else {
		return '';
	}
}

$wgHooks['BeforePageDisplay'][] = 'wfWikiForumAddStyles';
/**
 * Add the CSS file to the output, but only once.
 *
 * @param $out Object: OutputPage instance
 * @param $sk Object: Skin (or descendant class) instance
 */
function wfWikiForumAddStyles( &$out, &$sk ) {
	static $cssDone = false;
	if ( !$cssDone ) {
		global $wgScriptPath;
		$out->addExtensionStyle( $wgScriptPath . '/extensions/WikiForum/styles.css' );
		$cssDone = true;
	}
	return true;
}