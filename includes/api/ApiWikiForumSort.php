<?php
/**
 * Sort a WikiForum forum or category.
 * This is a backend to be called by AJAX with the appropriate anti-CSRF token set.
 *
 * @file
 * @date 22 May 2024
 * @see https://phabricator.wikimedia.org/T312733
 */

use Wikimedia\ParamValidator\ParamValidator;

/**
 * @ingroup API
 */
class ApiWikiForumSort extends ApiBase {

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
		$direction = $params['direction'];

		if ( $isCategory ) {
			$obj = WFCategory::newFromID( $id );
		} else {
			$obj = WFForum::newFromID( $id );
		}

		if ( !$obj ) {
			$this->dieWithError( 'wikiforum-invalid-id', 'invalid-id' );
		}

		// @todo FIXME: would be real dandy if the sort*() methods returned something else than a HTML string...
		if ( $direction === 'up' ) {
			$obj->sortUp();
		} else {
			$obj->sortDown();
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
			'iscategory' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'direction' => [
				ParamValidator::PARAM_TYPE => [ 'up', 'down' ],
				ParamValidator::PARAM_DEFAULT => 'down',
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
			'action=wikiforum-sort&sort=up&iscategory=true&id=32'
				=> 'apihelp-wikiforum-sort-example-1'
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:WikiForum/API';
	}
}
