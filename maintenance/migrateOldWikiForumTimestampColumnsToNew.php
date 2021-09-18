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

class MigrateOldWikiForumTimestampColumnsToNew extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrates data from old timestamp columns to new columns.' );
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
		return 'WikiForum\'s database tables have already been migrated to use the new timestamp columns.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );

		if ( $dbw->fieldExists( 'wikiforum_category', 'wfc_edited', __METHOD__ ) ) {
			// wikiforum_category
			$res = $dbw->select(
				'wikiforum_category',
				[
					'wfc_category',
					'wfc_edited'
				],
				'',
				__METHOD__,
				[ 'DISTINCT' ]
			);

			foreach ( $res as $row ) {
				$dbw->update(
					'wikiforum_category',
					[
						'wfc_edited_timestamp' => $row->wfc_edited
					],
					[
						'wfc_category' => (int)$row->wfc_category
					],
					__METHOD__
				);
			}
		}

		if ( $dbw->fieldExists( 'wikiforum_threads', 'wft_closed', __METHOD__ ) ) {
			// wikiforum_threads
			$res = $dbw->select(
				'wikiforum_threads',
				[
					'wft_thread',
					'wft_closed'
				],
				'',
				__METHOD__,
				[ 'DISTINCT' ]
			);

			foreach ( $res as $row ) {
				$dbw->update(
					'wikiforum_threads',
					[
						'wft_closed_timestamp' => $row->wft_closed
					],
					[
						'wft_thread' => (int)$row->wft_thread
					],
					__METHOD__
				);
			}
		}

		return true;
	}
}

$maintClass = MigrateOldWikiForumTimestampColumnsToNew::class;
require_once RUN_MAINTENANCE_IF_MAIN;
