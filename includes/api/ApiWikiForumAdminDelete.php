<?php
/**
 * Administrative deletion API: delete a WikiForum category or forum (only; an API
 * module for deleting threads and/or individual replies is to be written, or perhaps
 * this can be piggybacked to do that. Time will tell.)
 * This is a backend to be called by AJAX with the appropriate anti-CSRF token set.
 *
 * @file
 * @date 23 May 2024
 * @see https://phabricator.wikimedia.org/T312733
 */

use Wikimedia\ParamValidator\ParamValidator;

/**
 * @ingroup API
 */
class ApiWikiForumAdminDelete extends ApiBase {

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

		$id = $params['id'];
		$isCategory = (bool)$params['iscategory'];

		if ( $isCategory ) {
			$obj = WFCategory::newFromID( $id );
		} else {
			$obj = WFForum::newFromID( $id );
		}

		if ( !$obj ) {
			$this->dieWithError( 'wikiforum-invalid-id', 'invalid-id' );
		}

		// @todo FIXME: would be real dandy if the delete() methods returned something else than a HTML string...
		$obj->delete();

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
			'iscategory' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
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
			'action=wikiforum-admin-delete&iscategory=true&id=32'
				=> 'apihelp-wikiforum-admin-delete-example-1'
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:WikiForum/API';
	}
}
