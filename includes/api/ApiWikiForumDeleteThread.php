<?php
/**
 * Delete a WikiForum thread or reply.
 * This is a backend to be called by AJAX with the appropriate anti-CSRF token set.
 *
 * @file
 * @date 23 May 2024
 * @see https://phabricator.wikimedia.org/T312733
 */

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @ingroup API
 */
class ApiWikiForumDeleteThread extends ApiBase {

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

		$id = $params['id'];
		$isReply = (bool)$params['isreply'];

		// Use DB_PRIMARY to ensure we see data written in the same transaction (important for tests)
		// In production, this also ensures we see the latest data
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		if ( $isReply ) {
			$data = $db->selectRow(
				'wikiforum_replies',
				'*',
				[ 'wfr_reply_id' => $id ],
				__METHOD__
			);
			if ( $data ) {
				$obj = WFReply::newFromSQL( $data );
				// Ensure reply has thread property set (needed for delete method)
				if ( $obj && isset( $data->wfr_thread ) ) {
					$threadData = $db->selectRow(
						'wikiforum_threads',
						'*',
						[ 'wft_thread' => $data->wfr_thread ],
						__METHOD__
					);
					if ( $threadData ) {
						$obj->thread = WFThread::newFromSQL( $threadData );
					}
				}
			} else {
				$obj = false;
			}
		} else {
			$data = $db->selectRow(
				'wikiforum_threads',
				'*',
				[ 'wft_thread' => $id ],
				__METHOD__
			);
			if ( $data ) {
				$obj = WFThread::newFromSQL( $data );
				// Ensure thread has forum property set (needed for delete method)
				if ( $obj && isset( $data->wft_forum ) ) {
					$forumData = $db->selectRow(
						'wikiforum_forums',
						'*',
						[ 'wff_forum' => $data->wft_forum ],
						__METHOD__
					);
					if ( $forumData ) {
						$obj->forum = WFForum::newFromSQL( $forumData );
					}
				}
			} else {
				$obj = false;
			}
		}

		if ( !$obj ) {
			$this->dieWithError( 'wikiforum-invalid-id', 'invalid-id' );
		}

		// Check permissions
		if ( $isReply ) {
			$reply = $obj;
			$hasPermission = (
				!$user->isAnon() &&
				(
					$user->getActorId() == $reply->getPostedById() ||
					$user->isAllowed( 'wikiforum-moderator' )
				)
			);
		} else {
			$thread = $obj;
			$hasPermission = (
				!$user->isAnon() &&
				(
					$user->getActorId() == $thread->getPostedBy()->getActorId() ||
					$user->isAllowed( 'wikiforum-moderator' )
				)
			);
		}

		if ( !$hasPermission ) {
			if ( !$user->isRegistered() ) {
				$this->dieWithError( [ 'apierror-mustbeloggedin', $this->msg( 'action-wikiforum-moderator' ) ] );
			}
			$this->dieStatus( User::newFatalPermissionDeniedStatus( 'wikiforum-moderator' ) );
		}

		// Check blocks
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Block is checked and not null
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Block is checked and not null
			$this->dieBlocked( $user->getBlock() );
		}

		// Set context and token for delete() method which checks it
		// API already validated the token, but delete() methods expect it in the request
		$apiRequest = $this->getMain()->getRequest();
		$token = $apiRequest->getVal( 'token' );

		// Create a context with the API user and request, and set token
		$context = new RequestContext();
		$context->setUser( $user );
		$context->setRequest( $apiRequest );
		if ( $token ) {
			$apiRequest->setVal( 'wpToken', $token );
		}
		$obj->setContext( $context );

		// Delete the object
		// Note: delete() methods return HTML strings, but we don't need them for API
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
			'id' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			],
			'isreply' => [
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
			'action=wikiforum-delete-thread&id=123'
				=> 'apihelp-wikiforum-delete-thread-example-1',
			'action=wikiforum-delete-thread&isreply=true&id=456'
				=> 'apihelp-wikiforum-delete-thread-example-2'
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:WikiForum/API';
	}
}
