<?php
/**
 * @file
 * @ingroup Maintenance
 */

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Run automatically with update.php
 *
 * - Adds new actor columns to the required tables
 * - Populates these columns appropriately
 * - And finally drops the said columns
 *
 * @since January 2020
 */
class MigrateOldWikiForumUserColumnsToActor extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrates data from old user ID columns in WikiForum database tables to the new actor columns.' );
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	protected function updateSkippedMessage() {
		return 'WikiForum\'s database tables have already been migrated to use the actor columns.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );

		// wikiforum_category
		$res = $dbw->select(
			'wikiforum_category',
			[
				'wfc_added_user',
				'wfc_edited_user'
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);
		foreach ( $res as $row ) {
			$user = $this->getUser( $row->wfc_added_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_category',
					[
						'wfc_added_actor' => $actorId
					],
					[
						'wfc_added_user' => (int)$row->wfc_added_user
					],
					__METHOD__
				);
			}

			$user = $this->getUser( $row->wfc_edited_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_category',
					[
						'wfc_edited_actor' => $actorId
					],
					[
						'wfc_edited_user' => (int)$row->wfc_edited_user
					],
					__METHOD__
				);
			}
		}

		// wikiforum_forums
		$res = $dbw->select(
			'wikiforum_forums',
			[
				'wff_last_post_user',
				'wff_added_user',
				'wff_edited_user'
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);
		foreach ( $res as $row ) {
			$user = $this->getUser( $row->wff_last_post_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_forums',
					[
						'wff_last_post_actor' => $actorId
					],
					[
						'wff_last_post_user' => (int)$row->wff_last_post_user
					],
					__METHOD__
				);
			}

			$user = $this->getUser( $row->wff_added_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_forums',
					[
						'wff_added_actor' => $actorId
					],
					[
						'wff_added_user' => (int)$row->wff_added_user
					],
					__METHOD__
				);
			}

			$user = $this->getUser( $row->wff_edited_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_forums',
					[
						'wff_edited_actor' => $actorId
					],
					[
						'wff_edited_user' => (int)$row->wff_edited_user
					]
				);
			}
		}

		// wikiforum_threads
		$res = $dbw->select(
			'wikiforum_threads',
			[
				'wft_user',
				'wft_edit_user',
				'wft_closed_user',
				'wft_last_post_user'
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);
		foreach ( $res as $row ) {
			$user = $this->getUser( $row->wft_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_threads',
					[
						'wft_actor' => $actorId
					],
					[
						'wft_user' => (int)$row->wft_user
					],
					__METHOD__
				);
			}

			$user = $this->getUser( $row->wft_edit_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_threads',
					[
						'wft_edit_actor' => $actorId
					],
					[
						'wft_edit_user' => (int)$row->wft_edit_user
					],
					__METHOD__
				);
			}

			$user = $this->getUser( $row->wft_closed_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_threads',
					[
						'wft_closed_actor' => $actorId
					],
					[
						'wft_closed_user' => (int)$row->wft_closed_user
					],
					__METHOD__
				);
			}

			$user = $this->getUser( $row->wft_last_post_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_threads',
					[
						'wft_last_post_actor' => $actorId
					],
					[
						'wft_last_post_user' => (int)$row->wft_last_post_user
					],
					__METHOD__
				);
			}
		}

		// wikiforum_replies
		$res = $dbw->select(
			'wikiforum_replies',
			[
				'wfr_user',
				'wfr_edit_user'
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);
		foreach ( $res as $row ) {
			$user = $this->getUser( $row->wfr_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_replies',
					[
						'wfr_actor' => $actorId
					],
					[
						'wfr_user' => (int)$row->wfr_user
					],
					__METHOD__
				);
			}

			$user = $this->getUser( $row->wfr_edit_user );
			if ( $user ) {
				if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
					// MW 1.36+
					$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
				} else {
					$actorId = $user->getActorId( $dbw );
				}
				$dbw->update(
					'wikiforum_replies',
					[
						'wfr_edit_actor' => $actorId
					],
					[
						'wfr_edit_user' => (int)$row->wfr_edit_user
					],
					__METHOD__
				);
			}
		}

		return true;
	}

	/**
	 * Fetches the user from newFromId.
	 *
	 * @param string $userId
	 *
	 * @return User|false
	 */
	protected function getUser( $userId ) {
		if ( (int)$userId === 0 ) {
			return false;
		}

		// We create a user object
		// to get to actor id.
		return User::newFromId( $userId );
	}
}

$maintClass = MigrateOldWikiForumUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
