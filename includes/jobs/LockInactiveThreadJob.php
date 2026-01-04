<?php

/**
 * Job to automatically lock inactive threads in WikiForum
 *
 * This job checks if a thread should be locked based on inactivity time
 * and locks it if conditions are met. The job is idempotent and checks
 * all conditions before locking.
 *
 * @file
 * @ingroup Extensions
 */
class LockInactiveThreadJob extends Job implements GenericParameterJob {

	/**
	 * @param array $params Job parameters (threadId)
	 */
	public function __construct( array $params ) {
		parent::__construct( 'lockInactiveThread', $params );
		$this->removeDuplicates = true;
	}

	/**
	 * Execute the job
	 *
	 * @return bool Success
	 */
	public function run() {
		$threadId = $this->params['threadId'] ?? null;

		if ( !$threadId || !is_numeric( $threadId ) ) {
			$this->setLastError( 'Invalid thread ID' );
			return false;
		}

		// Get thread using WFThread class method
		$thread = WFThread::newFromID( (int)$threadId );

		// Check if thread exists
		if ( !$thread ) {
			// Thread doesn't exist, nothing to do
			return true;
		}

		// Use internal check method from WFThread object
		if ( !$thread->checkAutoLockConditions() ) {
			// Thread should not be locked, nothing to do
			return true;
		}

		// All conditions met, lock the thread using internal method
		if ( !$thread->doLock() ) {
			$this->setLastError( 'Failed to lock thread' );
			return false;
		}

		return true;
	}
}
