<?php

/**
 * @file
 */
class EchoHooks implements
	\MediaWiki\Extension\Notifications\Hooks\BeforeCreateEchoEventHook
{
	/**
	 * For the Echo extension: register our new presentation model with Echo so
	 * Echo knows how it should display our notifications in it.
	 *
	 * @param array &$notifications Echo notifications
	 * @param array &$notificationCategories Echo notification categories
	 * @param array &$icons Icon details
	 */
	public function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$icons
	) {
		$notificationCategories['wikiforum-comment'] = [
			'tooltip' => 'echo-pref-tooltip-wikiforum-comment',
		];
		$notifications['wikiforum-comment'] = [
			'category' => 'wikiforum-comment',
			'group' => 'interactive',
			'section' => 'alert',
			'presentation-model' => EchoMentionWikiForumCommentPresentationModel::class,
			EchoAttributeManager::ATTR_LOCATORS => [
				[ 'EchoUserLocator::locateFromEventExtra', [ 'mentioned-users' ] ]
			],
			'icon' => 'mention',
		];
	}
}
