<?php
/**
 * @file
 * @ingroup Maintenance
 */
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
		$dbw = $this->getDB( DB_MASTER );

		// wikiforum_category
		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_category' )} SET wfc_added_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wfc_added_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_category' )} SET wfc_edited_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wfc_edited_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_category' )} SET wfc_deleted_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wfc_deleted_user)",
			__METHOD__
		);

		// wikiforum_forums
		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_forums' )} SET wff_last_post_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wff_last_post_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_forums' )} SET wff_added_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wff_added_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_forums' )} SET wff_edited_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wff_edited_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_forums' )} SET wff_deleted_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wff_deleted_user)",
			__METHOD__
		);

		// wikiforum_threads
		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_threads' )} SET wft_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wft_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_threads' )} SET wft_deleted_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wft_deleted_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_threads' )} SET wft_edit_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wft_edit_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_threads' )} SET wft_closed_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wft_closed_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_threads' )} SET wft_last_post_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wft_last_post_user)",
			__METHOD__
		);

		// wikiforum_replies
		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_replies' )} SET wfr_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wfr_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_replies' )} SET wfr_deleted_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wfr_deleted_user)",
			__METHOD__
		);

		$dbw->query(
			"UPDATE {$dbw->tableName( 'wikiforum_replies' )} SET wfr_edit_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=wfr_edit_user)",
			__METHOD__
		);

		return true;
	}
}

$maintClass = MigrateOldWikiForumUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
