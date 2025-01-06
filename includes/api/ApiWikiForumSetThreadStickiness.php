<?php
/**
 * Make a thread sticky or remove stickiness from a thread.
 *
 * @file
 * @date 22 May 2024
 * @see https://phabricator.wikimedia.org/T312733
 */

use Wikimedia\ParamValidator\ParamValidator;

/**
 * @ingroup API
 */
class ApiWikiForumSetThreadStickiness extends ApiBase {

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 */
	public function __construct( ApiMain $mainModule, $moduleName ) {
		parent::__construct( $mainModule, $moduleName );
	}

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();
		$params = $this->extractRequestParams();

		// Check whether the user has the appropriate permissions anyway
		$permission = $user->isAllowed( 'wikiforum-admin' );

		if ( $permission !== true ) {
			if ( !$user->isRegistered() ) {
				$this->dieWithError( [ 'apierror-mustbeloggedin', $this->msg( 'action-wikiforum-admin' ) ] );
			}

			$this->dieStatus( User::newFatalPermissionDeniedStatus( $permission ) );
		}

		// Check blocks
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Block is checked and not null
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Block is checked and not null
			$this->dieBlocked( $user->getBlock() );
		}

		// Global blocks
		if ( $user->isBlockedGlobally() ) {
			$this->dieBlocked( $user->getGlobalBlock() );
		}

		$id = $params['id'];
		$stickiness = $params['stickiness'];

		$thread = WFThread::newFromID( $id );
		if ( !$thread ) {
			$this->dieWithError( 'wikiforum-invalid-id', 'invalid-id' );
		}

		// @todo Should we care if $thread->isSticky() already?
		if ( $stickiness === 'set' ) {
			$thread->makeSticky();
		} else {
			$thread->removeSticky();
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'status' => 'OK' ] );
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			// @todo FIXME: eventually support both the internal ID and the human-readable
			// name string and require only one of 'em to be present
			'id' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			],
			'stickiness' => [
				ParamValidator::PARAM_TYPE => [ 'set', 'remove' ],
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=wikiforum-set-thread-stickiness&id=666&stickiness=set'
				=> 'apihelp-wikiforum-set-thread-stickiness-example-1'
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:WikiForum/API';
	}
}
