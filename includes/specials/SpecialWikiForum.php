<?php

use MediaWiki\MediaWikiServices;

/**
 * Special:WikiForum -- an overview of all available boards on the forum
 *
 * @file
 * @ingroup Extensions
 */

class SpecialWikiForum extends SpecialPage {
	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'WikiForum' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->getBlock() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		$this->setHeaders();

		// Add CSS
		$out->addModuleStyles( 'ext.wikiForum' );
		$out->addModules( 'mediawiki.page.gallery' ); // needed to show galleries correctly. Should be a better way of doing this so it's only loaded if there is a gallery, but I can't find it.

		$output = '';

		// If a parameter to the special page is specified, check its type
		// and either display a forum (if parameter is a number) or a thread
		// (if it's the title of a topic)
		if ( $par ) {
			// Let search spiders index our content
			$out->setRobotPolicy( 'index,follow' );

			if ( is_numeric( $par ) ) {
				$forum = WFForum::newFromID( $par );
				if ( $forum ) {
					$output .= $forum->show();
				} else {
					$output .= WikiForum::showErrorMessage( 'wikiforum-forum-not-found', 'wikiforum-forum-not-found-text' );
					$output .= WikiForum::showOverview( $user );
				}
			} else {
				$thread = WFThread::newFromName( $par );
				if ( $thread ) {
					$output .= $thread->show();
				} else {
					$output .= WikiForum::showErrorMessage( 'wikiforum-thread-not-found', 'wikiforum-thread-not-found-text' );
					$output .= WikiForum::showOverview( $user );
				}
			}
		} else {
			$action = $request->getVal( 'wfaction' );

			$threadID = $request->getInt( 'thread' );
			$forumID = $request->getInt( 'forum' );
			$categoryID = $request->getInt( 'category' );
			$replyID = $request->getInt( 'reply' );

			$text = $request->getText( 'text' );
			$title = $request->getText( 'name' );

			if ( $categoryID ) { // actions requiring a category
				$category = WFCategory::newFromID( $categoryID );

				if ( !$category ) { // show error message, category not found
					$output .= WikiForum::showErrorMessage( 'wikiforum-cat-not-found', 'wikiforum-cat-not-found-text' );
					$output .= WikiForum::showOverview( $user );

				} else {
					if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
						$output .= WikiForum::showErrorMessage( 'wikiforum-error-category', 'wikiforum-error-readonly' );
						$output .= $category->show();

					} else {
						switch ( $action ) {
							case 'editcategory':
								$output .= $category->showEditForm();
								break;
							case 'savecategory':
								$output .= $category->edit( $title );
								break;
							/* DISABLED due to T312733
							For users with JS enabled, this is handled by the JS in /resources/js/ext.wikiForum.admin-links.js
							For no-JS users, there is no replacement right now. :-(
							In the future, these action can perhaps render a confirmation form which
							would have the appropriate anti-CSRF token set or something like that.
							case 'categorydown':
								$output .= $category->sortDown();
								break;
							case 'categoryup':
								$output .= $category->sortUp();
								break;

							// Also disabled due to T312733 and handled by the same JS file as above
							case 'deletecategory':
								$output .= $category->delete();
								break;
							*/

							case 'addforum':
								$output .= $category->showAddForumForm();
								break;
							case 'savenewforum':
								$output .= $category->addForum( $title, $text, $request->getBool( 'announcement', false ) );
								break;

							default:
								$out->addHTML( $category->show() );
								break;
						}
					}
				}

			} elseif ( $forumID ) { // actions requiring a forum
				$forum = WFForum::newFromID( $forumID );

				if ( !$forum ) { // show error message, forum not found
					$output .= WikiForum::showErrorMessage( 'wikiforum-forum-not-found', 'wikiforum-forum-not-found-text' );
					$output .= WikiForum::showOverview( $user );

				} else {
					if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
						$output .= WikiForum::showErrorMessage( 'wikiforum-error-forum', 'wikiforum-error-readonly' );
						$output .= $forum->show();

					} else {
						switch ( $action ) {
							case 'editforum':
								$output .= $forum->showEditForm();
								break;
							case 'saveforum':
								$output .= $forum->edit( $title, $text, $request->getVal( 'announcement' ) );
								break;
							/* Also disabled due to T312733
							case 'deleteforum':
								$output .= $forum->delete();
								break;
							case 'forumup':
								$output .= $forum->sortUp();
								break;
							case 'forumdown':
								$output .= $forum->sortDown();
								break;
							*/
							case 'addthread':
								$output .= $forum->showNewThreadForm( '', '' );
								break;
							case 'savenewthread':
								$output .= $forum->addThread( $title, $text );
								break;

							default:
								$output .= $forum->show();
								break;
						}
					}
				}

			} elseif ( $threadID ) { // actions requiring a thread
				$thread = WFThread::newFromID( $threadID );

				if ( !$thread ) { // show error message, thread not found
					$output .= WikiForum::showErrorMessage( 'wikiforum-thread-not-found', 'wikiforum-thread-not-found-text' );
					$output .= WikiForum::showOverview( $user );

				} else {
					if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
						$output .= WikiForum::showErrorMessage( 'wikiforum-error-thread', 'wikiforum-error-readonly' );
						$output .= $thread->show();

					} else {
						switch ( $action ) {
							case 'editthread':
								$output .= $thread->showEditForm();
								break;
							case 'savethread':
								$output .= $thread->edit( $title, $text );
								break;
							case 'deletethread':
								$output .= $thread->delete();
								break;
							/* See above why these are commented out but tl,dr: T312733
							case 'removesticky':
								$output .= $thread->removeSticky();
								break;
							case 'makesticky':
								$output .= $thread->makeSticky();
								break;
							*/
							case 'closethread':
								$output .= $thread->close();
								break;
							case 'reopenthread':
								$output .= $thread->reopen();
								break;

							case 'savenewreply':
								$output .= $thread->addReply( $text );
								break;

							default:
								$output .= $thread->show();
								break;
						}
					}
				}

			} elseif ( $replyID ) { // actions requiring a reply
				$reply = WFReply::newFromID( $replyID );

				if ( !$reply ) { // show error message, reply not found
					$output .= WikiForum::showErrorMessage( 'wikiforum-reply-not-found', 'wikiforum-reply-not-found-text' );
					$output .= WikiForum::showOverview( $user );

				} else {
					if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
						$output .= WikiForum::showErrorMessage( 'wikiforum-error-thread', 'wikiforum-error-readonly' );
						$output .= $reply->getThread()->show();
					} else {
						switch ( $action ) {
							case 'deletereply':
								$output .= $reply->delete();
								break;
							case 'editreply':
								$output .= $reply->showEditor();
								break;
							case 'savereply':
								$output .= $reply->edit( $text );
								break;
							default:
								$output .= $reply->getThread()->show();
								break;
						}
					}
				}

			} else { // other actions
				switch ( $action ) {
					case 'addcategory':
						$output .= WFCategory::showAddForm( $user );
						break;
					case 'savenewcategory': // title
						$output .= WFCategory::add( $title, $user );
						break;
					case 'search':
						$output .= WikiForum::showSearchResults( $request->getText( 'query' ), $user );
						break;
					default:
						$output .= WikiForum::showOverview( $user );
						break;
				}
			}
		}

		$out->addHTML( $output );
	}
}
