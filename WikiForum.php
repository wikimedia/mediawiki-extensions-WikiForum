<?php
/**
 * WikiForum -- forum extension for MediaWiki
 *
 * @file
 * @ingroup Extensions
 * @author Michael Chlebek
 * @author Jack Phoenix <jack@countervandalism.net>
 * @author UltrasonicNXT/Adam Carter
 * @date 26 July 2013
 * @copyright Copyright © 2010 Unidentify Studios
 * @copyright Copyright © 2010-2013 Jack Phoenix <jack@countervandalism.net>
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

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'WikiForum',
	'author' => array( 'Michael Chlebek', 'Jack Phoenix', 'Adam Carter (UltrasonicNXT)' ),
	'version' => '2.0.1',
	'url' => 'https://www.mediawiki.org/wiki/Extension:WikiForum',
	'descriptionmsg' => 'wikiforum-desc'
);

// Set up i18n, the new special page etc.
$dir = dirname( __FILE__ ) . '/';
$wgMessagesDirs['WikiForum'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['WikiForum'] = $dir . 'WikiForum.i18n.php';
$wgExtensionMessagesFiles['WikiForumAlias'] = $dir . 'WikiForum.alias.php';
$wgAutoloadClasses['WikiForumHooks'] = $dir . 'WikiForumHooks.php';
$wgAutoloadClasses['WikiForumGui'] = $dir . 'WikiForumGui.php';
$wgAutoloadClasses['WikiForumClass'] = $dir . 'WikiForumClass.php';
$wgAutoloadClasses['WikiForum'] = $dir . 'SpecialWikiForum.php';
$wgAutoloadClasses['WFReply'] = $dir . 'Reply.php';
$wgAutoloadClasses['WFThread'] = $dir . 'Thread.php';
$wgAutoloadClasses['WFForum'] = $dir . 'Forum.php';
$wgAutoloadClasses['WFCategory'] = $dir . 'Category.php';
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

// Show the forum log in RecentChanges?
$wgWikiForumLogInRC = true;

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.wikiForum'] = array(
	'styles' => 'styles.css',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'WikiForum',
);

// Hooked functions
$wgHooks['ParserFirstCallInit'][] = 'WikiForumHooks::registerParserHooks';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'WikiForumHooks::addTables';

// Logging
$wgAutoloadClasses['WikiForumLogFormatter'] = $dir . 'WikiForumLogFormatter.php';
$wgLogTypes[] = 'forum';
$wgLogActionsHandlers['forum/*'] = 'WikiForumLogFormatter';
