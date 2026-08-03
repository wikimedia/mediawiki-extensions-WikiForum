<?php
/**
 * Echo notifications presentation stuff for @username mentions in WikiForum threads.
 *
 * Adapted from the presentation model in the Comments extension.
 *
 * @file
 * @date 17 February 2022
 */

use MediaWiki\Extension\Notifications\DiscussionParser;
use MediaWiki\Extension\Notifications\Formatters\EchoMentionPresentationModel;
use MediaWiki\Extension\Notifications\Formatters\EchoPresentationModelSection;
use MediaWiki\Extension\Notifications\Model\Event as EchoEvent;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;

class EchoMentionWikiForumCommentPresentationModel extends EchoMentionPresentationModel {

	/**
	 * @var EchoPresentationModelSection
	 */
	protected $section;

	/**
	 * @inheritDoc
	 */
	protected function __construct( EchoEvent $event, Language $language, User $user, $distributionType ) {
		parent::__construct( $event, $language, $user, $distributionType );
		$this->section = new EchoPresentationModelSection( $event, $user, $language );
	}

	/**
	 * @return string The symbolic icon name as defined in $wgEchoNotificationIcons
	 */
	public function getIconType() {
		return 'mention';
	}

	/**
	 * If this function returns false, no other methods will be called
	 * on the object.
	 *
	 * @return bool
	 */
	public function canRender() {
		return (bool)$this->event->getTitle();
	}

	/**
	 * @return string Message key that will be used in getHeaderMessage
	 */
	protected function getHeaderMessageKey() {
		if ( $this->event->getExtraParam( 'reply-id' ) ) {
			return 'notification-header-wikiforum-comment';
		}
		return parent::getHeaderMessageKey();
	}

	/**
	 * Get a message object and add the performer's name as
	 * a parameter.
	 *
	 * @return Message
	 */
	public function getHeaderMessage() {
		$t = $this->event->getTitle();

		if ( $this->event->getExtraParam( 'reply-id' ) ) {
			// $1 & $2: user who mentioned you (duplicated for GENDER support)
			$msg = $this->getMessageWithAgent( $this->getHeaderMessageKey() );

			// $3 = your username
			$msg->params( $this->getViewingUserForGender() );

			// $4 = link to Special:WikiForum/<thread name goes here>
			$msg->params( $t->getPrefixedText() );

			// $5 = just the thread name
			$msg->params( $this->event->getExtraParam( 'thread-name' ) );

			// $6 = reply ID, for building the correct fragment
			$msg->params( $this->event->getExtraParam( 'reply-id' ) );

			return $msg;
		}

		return parent::getHeaderMessage();
	}

	/**
	 * Get a message for the notification's body, false if it has no body
	 *
	 * @todo CHECKME: Do we need this? It's literally copypasta leftover from the
	 * parent class, but maybe it's useful...
	 *
	 * @return bool|Message
	 */
	public function getBodyMessage() {
		$content = $this->event->getExtraParam( 'content' );
		if ( $content && $this->userCan( RevisionRecord::DELETED_TEXT ) ) {
			$msg = $this->msg( 'notification-body-mention' );
			$msg->plaintextParams(
				DiscussionParser::getTextSnippet(
					$content,
					$this->language,
					150,
					$this->event->getTitle()
				)
			);
			return $msg;
		} else {
			return false;
		}
	}

	/**
	 * Array of primary link details, with possibly-relative URL & label.
	 *
	 * @return array Array of link data:
	 *                    ['url' => (string) url, 'label' => (string) link text (non-escaped)]
	 */
	public function getPrimaryLink() {
		return [
			// Need FullURL so the section is included
			'url' => $this->section->getTitleWithSection()->getFullURL(),
			'label' => $this->msg( 'notification-link-text-view-mention' )->text()
		];
	}

	/**
	 * Array of secondary link details, including possibly-relative URLs, label,
	 * description & icon name.
	 *
	 * @return array
	 */
	public function getSecondaryLinks() {
		$title = $this->event->getTitle();

		if ( $this->event->getExtraParam( 'reply-id' ) ) {
			$url = $title->getFullURL() . '#reply_' . $this->event->getExtraParam( 'reply-id' );
			$viewChangesLink = [
				'url' => $url,
				'label' => $this->msg( 'notification-link-text-view-mention', $this->getViewingUserForGender() )->text(),
				'description' => '',
				'icon' => 'changes',
				'prioritized' => true,
			];

			return [ $this->getAgentLink(), $viewChangesLink ];
		}

		return parent::getSecondaryLinks();
	}

	/**
	 * @todo Do we need to override this?
	 * @return string
	 */
	protected function getSubjectMessageKey() {
		return 'notification-mention-email-subject';
	}

}
