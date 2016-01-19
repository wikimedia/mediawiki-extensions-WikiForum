<?php
/**
 * Special:WikiForum -- an overview of all available boards on the forum
 *
 * @file
 * @ingroup Extensions
 */

class WikiForum extends SpecialPage {
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
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// If user is blocked, s/he doesn't need to access this page
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		$this->setHeaders();

		// Add CSS
		$out->addModuleStyles( 'ext.wikiForum' );

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
					$output .= WikiForumClass::showErrorMessage( 'wikiforum-forum-not-found', 'wikiforum-forum-not-found-text' );
					$output .= WikiForumClass::showOverview();
				}
			} else {
				$thread = WFThread::newFromName( $par );
				if ( $thread ) {
					$output .= $thread->show();
				} else {
					$output .= WikiForumClass::showErrorMessage( 'wikiforum-thread-not-found', 'wikiforum-thread-not-found-text' );
					$output .= WikiForumClass::showOverview();
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
					$output .= WikiForumClass::showErrorMessage( 'wikiforum-cat-not-found', 'wikiforum-cat-not-found-text' );
					$output .= WikiForumClass::showOverview();

				} else {
					if ( wfReadOnly() ) {
						$output .= WikiForumClass::showErrorMessage( 'wikiforum-error-category', 'wikiforum-error-readonly' );
						$output .= $category->show();

					} else {
						switch ( $action ) {
							case 'editcategory':
								$output .= $category->showEditForm();
								break;
							case 'savecategory':
								$output .= $category->edit( $title );
								break;
							case 'categorydown':
								$output .= $category->sortDown();
								break;
							case 'categoryup':
								$output .= $category->sortUp();
								break;
							case 'deletecategory':
								$output .= $category->delete();
								break;

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
					$output .= WikiForumClass::showErrorMessage( 'wikiforum-forum-not-found', 'wikiforum-forum-not-found-text' );
					$output .= WikiForumClass::showOverview();

				} else {
					if ( wfReadOnly() ) {
						$output .= WikiForumClass::showErrorMessage( 'wikiforum-error-forum', 'wikiforum-error-readonly' );
						$output .= $forum->show();

					} else {
						switch ( $action ) {
							case 'editforum':
								$output .= $forum->showEditForm();
								break;
							case 'saveforum':
								$output .= $forum->edit( $title, $text, $request->getVal( 'announcement' ) );
								break;
							case 'deleteforum':
								$output .= $forum->delete();
								break;
							case 'forumup':
								$output .= $forum->sortUp();
								break;
							case 'forumdown':
								$output .= $forum->sortDown();
								break;

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

			} elseif( $threadID ) { // actions requiring a thread
				$thread = WFThread::newFromID( $threadID );

				if ( !$thread ) { // show error message, thread not found
					$output .= WikiForumClass::showErrorMessage( 'wikiforum-thread-not-found', 'wikiforum-thread-not-found-text' );
					$output .= WikiForumClass::showOverview();

				} else {
					if ( wfReadOnly() ) {
						$output .= WikiForumClass::showErrorMessage( 'wikiforum-error-thread', 'wikiforum-error-readonly' );
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
							case 'removesticky':
								$output .= $thread->removeSticky();
								break;
							case 'makesticky':
								$output .= $thread->makeSticky();
								break;
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
					$output .= WikiForumClass::showErrorMessage( 'wikiforum-reply-not-found', 'wikiforum-reply-not-found-text' );
					$output .= WikiForumClass::showOverview();

				} else {
					if ( wfReadOnly() ) {
						$output .= WikiForumClass::showErrorMessage( 'wikiforum-error-thread', 'wikiforum-error-readonly' );
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
						$output .= WFCategory::showAddForm();
						break;
					case 'savenewcategory': //title
						$output .= WFCategory::add( $title );
						break;
					case 'search':
						$output .= WikiForumClass::showSearchResults( $request->getText( 'query' ) );
						break;
					default:
						$output .= WikiForumClass::showOverview();
						break;
				}
			}
		}

		$out->addHTML( $output );
	}
}
