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
			$out->blockedPage();
			return;
		}

		// Checking for wfReadOnly() is done in the individual functions
		// in WikiForumClass.php; besides, we should be able to browse the
		// forum even when the DB is in read-only mode

		$this->setHeaders();

		$forum = new WikiForumClass;
		$values = array();

		// Add CSS
		$out->addModuleStyles( 'ext.wikiForum' );

		// If a parameter to the special page is specified, check its type
		// and either display a forum (if parameter is a number) or a thread
		// (if it's the title of a topic)
		if ( $par ) {
			// Let search spiders index our content
			$out->setRobotPolicy( 'index,follow' );

			if ( is_numeric( $par ) ) {
				$out->addHTML( $forum->showForum( $par ) );
			} else {
				$threadId = WikiForumClass::findThreadIDByTitle( $par );
				$out->addHTML( $forum->showThread( $threadId ) );
			}
		} else {
			// That's...a lot of variables. No kidding.
			$mod_category		= $request->getInt( 'category' );
			$mod_forum			= $request->getInt( 'forum' );
			$mod_thread		= $request->getInt( 'thread' );
			$mod_writethread	= $request->getInt( 'writethread' );
			$mod_addcomment	= $request->getInt( 'addcomment' );
			$mod_addthread		= $request->getInt( 'addthread' );
			$mod_editcomment	= $request->getInt( 'editcomment' );
			$mod_editthread	= $request->getInt( 'editthread' );
			$mod_deletecomment	= $request->getInt( 'deletecomment' );
			$mod_deletethread	= $request->getInt( 'deletethread' );
			$mod_closethread	= $request->getInt( 'closethread' );
			$mod_reopenthread	= $request->getInt( 'reopenthread' );
			$mod_addcategory	= $request->getBool( 'addcategory' );
			$mod_addforum		= $request->getInt( 'addforum' );
			$mod_editcategory	= $request->getInt( 'editcategory' );
			$mod_editforum		= $request->getInt( 'editforum' );
			$mod_deletecategory	= $request->getInt( 'deletecategory' );
			$mod_deleteforum	= $request->getInt( 'deleteforum' );
			$mod_makesticky		= $request->getInt( 'makesticky' );
			$mod_removesticky	= $request->getInt( 'removesticky' );
			$mod_categoryup		= $request->getInt( 'categoryup' );
			$mod_categorydown	= $request->getInt( 'categorydown' );
			$mod_forumup		= $request->getInt( 'forumup' );
			$mod_forumdown		= $request->getInt( 'forumdown' );
			$mod_search			= $request->getVal( 'txtSearch' );
			$mod_submit			= $request->getBool( 'butSubmit' );
			$mod_pastethread	= $request->getInt( 'pastethread' );

			// Define this variable to prevent E_NOTICEs about undefined variable
			$mod_none = false;

			// Figure out what we're going to do here...post a reply, a new thread,
			// edit a reply, edit a thread...and so on.
			if ( isset( $mod_addcomment ) && $mod_addcomment > 0 ) {
				$data_text = $request->getVal( 'frmText' );
				$data_preview = $request->getBool( 'butPreview' );
				$data_save = $request->getBool( 'butSave' );
				if ( $data_save == true ) {
					$result = $forum->addReply( $mod_addcomment, $data_text );
					$mod_thread = $mod_addcomment;
				} elseif ( $data_preview == true ) {
					$result = $out->addHTML(
						$forum->previewIssue(
							'addcomment',
							$mod_addcomment,
							false,
							$data_text
						)
					);
					$mod_none = true;
				}
			} elseif ( isset( $mod_addthread ) && $mod_addthread > 0 ) {
				$data_title = $request->getVal( 'frmTitle' );
				$data_text = $request->getVal( 'frmText' );
				$data_preview = $request->getBool( 'butPreview' );
				$data_save = $request->getBool( 'butSave' );

				if ( $data_save == true ) {
					$result = $forum->addThread(
						$mod_addthread,
						$data_title,
						$data_text
					);
					$mod_forum = $mod_addthread;
				} elseif ( $data_preview == true ) {
					$result = $out->addHTML(
						$forum->previewIssue(
							'addthread',
							$mod_addthread,
							$data_title,
							$data_text
						)
					);
					$mod_none = true;
				} else {
					$mod_writethread = $mod_addthread;
				}
			} elseif ( isset( $mod_editcomment ) && $mod_editcomment > 0 ) {
				$data_text = $request->getVal( 'frmText' );
				$data_preview = $request->getBool( 'butPreview' );
				$data_save = $request->getBool( 'butSave' );

				if ( $data_save == true ) {
					$result = $forum->editReply(
						$mod_editcomment,
						$data_text
					);
					$mod_thread = $mod_thread;
				} elseif ( $data_preview == true ) {
					$result = $out->addHTML(
						$forum->previewIssue(
							'editcomment',
							$mod_editcomment,
							false,
							$data_text
						)
					);
					$mod_none = true;
				}
			} elseif ( isset( $mod_editthread ) && $mod_editthread > 0 ) {
				$data_title = $request->getVal( 'frmTitle' );
				$data_text = $request->getVal( 'frmText' );
				$data_preview = $request->getBool( 'butPreview' );
				$data_save = $request->getBool( 'butSave' );

				if ( $data_save == true ) {
					$result = $forum->editThread(
						$mod_editthread,
						$data_title,
						$data_text
					);
					$mod_thread = $mod_editthread;
				} elseif ( $data_preview == true ) {
					$result = $out->addHTML(
						$forum->previewIssue(
							'editthread',
							$mod_editthread,
							$data_title,
							$data_text
						)
					);
					$mod_none = true;
				} else {
					$mod_writethread = $mod_editthread;
				}
			} elseif ( isset( $mod_deletecomment ) && $mod_deletecomment > 0 ) {
				$result = $forum->deleteReply( $mod_deletecomment );
			} elseif ( isset( $mod_deletethread ) && $mod_deletethread > 0 ) {
				$result = $forum->deleteThread( $mod_deletethread );
			} elseif ( isset( $mod_deletecategory ) && $mod_deletecategory > 0 ) {
				$result = $forum->deleteCategory( $mod_deletecategory );
			} elseif ( isset( $mod_deleteforum ) && $mod_deleteforum > 0 ) {
				$result = $forum->deleteForum( $mod_deleteforum );
			} elseif ( isset( $mod_categoryup ) && $mod_categoryup > 0 ) {
				$result = $forum->sortKeys( $mod_categoryup, 'category', true );
			} elseif ( isset( $mod_categorydown ) && $mod_categorydown > 0 ) {
				$result = $forum->sortKeys( $mod_categorydown, 'category', false );
			} elseif ( isset( $mod_forumup ) && $mod_forumup > 0 ) {
				$result = $forum->sortKeys( $mod_forumup, 'forum', true );
			} elseif ( isset( $mod_forumdown ) && $mod_forumdown > 0 ) {
				$result = $forum->sortKeys( $mod_forumdown, 'forum', false );
			} elseif ( isset( $mod_closethread ) && $mod_closethread > 0 ) {
				$result = $forum->closeThread( $mod_closethread );
				$mod_thread = $mod_closethread;
			} elseif ( isset( $mod_reopenthread ) && $mod_reopenthread > 0 ) {
				$result = $forum->reopenThread( $mod_reopenthread );
				$mod_thread = $mod_reopenthread;
			} elseif ( isset( $mod_makesticky ) && $mod_makesticky > 0 ) {
				$result = $forum->makeSticky( $mod_makesticky, true );
				$mod_thread = $mod_makesticky;
			} elseif ( isset( $mod_removesticky ) && $mod_removesticky > 0 ) {
				$result = $forum->makeSticky( $mod_removesticky, false );
				$mod_thread = $mod_removesticky;
			} elseif ( isset( $mod_pastethread ) && $mod_pastethread > 0 ) {
				$result = $forum->pasteThread( $mod_pastethread, $mod_forum );
			} elseif (
				isset( $mod_addcategory ) && $mod_addcategory == true &&
				$user->isAllowed( 'wikiforum-admin' )
			) {
				if ( $mod_submit == true ) {
					$values['title'] = $request->getVal( 'frmTitle' );
					$mod_submit = $forum->addCategory( $values['title'] );
				}

				if ( $mod_submit == false ) {
					$mod_showform = true;
					$type = 'addcategory';
					$id = $mod_addcategory;
				}
			} elseif (
				isset( $mod_addforum ) && $mod_addforum > 0 &&
				$user->isAllowed( 'wikiforum-admin' )
			) {
				if ( $mod_submit == true ) {
					$values['title'] = $request->getVal( 'frmTitle' );
					$values['text'] = $request->getVal( 'frmText' );

					if ( $request->getBool( 'chkAnnouncement' ) == true ) {
						$values['announce'] = '1';
					} else {
						$values['announce'] = '0';
					}
					$mod_submit = $forum->addForum(
						$mod_addforum,
						$values['title'],
						$values['text'],
						$values['announce']
					);
				}

				if ( $mod_submit == false ) {
					$mod_showform = true;
					$type = 'addforum';
					$id = $mod_addforum;
				}
			} elseif (
				isset( $mod_editcategory ) && $mod_editcategory > 0 &&
				$user->isAllowed( 'wikiforum-admin' )
			) {
				if ( $mod_submit == true ) {
					$values['title'] = $request->getVal( 'frmTitle' );
					$mod_submit = $forum->editCategory(
						$mod_editcategory,
						$values['title']
					);
				}

				if ( $mod_submit == false ) {
					$mod_showform = true;
					$type = 'editcategory';
					$id = $mod_editcategory;
				}
			} elseif (
				isset( $mod_editforum ) && $mod_editforum > 0 &&
				$user->isAllowed( 'wikiforum-admin' )
			) {
				if ( $mod_submit == true ) {
					$values['title'] = $request->getVal( 'frmTitle' );
					$values['text'] = $request->getVal( 'frmText' );

					if ( $request->getBool( 'chkAnnouncement' ) == true ) {
						$values['announce'] = '1';
					} else {
						$values['announce'] = '0';
					}
					$mod_submit = $forum->editForum(
						$mod_editforum,
						$values['title'],
						$values['text'],
						$values['announce']
					);
				}

				if ( $mod_submit == false ) {
					$mod_showform = true;
					$type = 'editforum';
					$id = $mod_editforum;
				}
			}

			// Only in certain cases we want search spiders to index our content
			// and follow links. These are overview (Special:WikiForum), individual
			// threads, forums and categories.
			if ( isset( $mod_search ) && $mod_search == true ) {
				$out->addHTML( $forum->showSearchResults( $mod_search ) );
			} elseif ( $mod_none == true ) {
				// no data
			} elseif ( isset( $mod_category ) && $mod_category > 0 ) {
				// Let search spiders index our content
				$out->setRobotPolicy( 'index,follow' );
				$out->addHTML( $forum->showCategory( $mod_category ) );
			} elseif ( isset( $mod_forum ) && $mod_forum > 0 ) {
				// Let search spiders index our content
				$out->setRobotPolicy( 'index,follow' );
				$out->addHTML( $forum->showForum( $mod_forum ) );
			} elseif ( isset( $mod_thread ) && $mod_thread > 0 ) {
				// Let search spiders index our content
				$out->setRobotPolicy( 'index,follow' );
				$out->addHTML( $forum->showThread( $mod_thread ) );
			} elseif ( isset( $mod_writethread ) && $mod_writethread > 0 ) {
				$out->addHTML( $forum->writeThread( $mod_writethread ) );
			} elseif ( isset( $mod_showform ) && $mod_showform ) {
				$out->addHTML(
					$forum->showEditorCatForum( $id, $type, $values )
				);
			} else {
				// Let search spiders index our content
				$out->setRobotPolicy( 'index,follow' );
				$out->addHTML( $forum->showOverview() );
			}
		} // else from line 55 (the if $par is not specified one)
	} // execute()
}
