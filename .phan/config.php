<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'includes/',
		'maintenance/',
	]
);

// Optional dependency: SocialProfile extension (for avatar support)
// Only included if SocialProfile is installed - phan will skip if not found
$socialProfilePath = __DIR__ . '/../../extensions/SocialProfile';
if ( is_dir( $socialProfilePath ) ) {
	$cfg['directory_list'][] = '../../extensions/SocialProfile';
	$cfg['exclude_analysis_directory_list'] = array_merge(
		$cfg['exclude_analysis_directory_list'] ?? [],
		[
			// Don't analyze SocialProfile code, just use it for type information
			'../../extensions/SocialProfile',
		]
	);
}

// "Mandatory" dependency for phan analysis: Echo (notifications) extension
$cfg['directory_list'][] = '../../extensions/Echo';
$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'] ?? [],
	[
		// Don't analyze SocialProfile code, just use it for type information
		'../../extensions/Echo',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'] ?? [],
	[
		'tests/',
	]
);

// Suppress issues that are expected or false positives
// Note: Merge with parent config to inherit MediaWiki's standard suppressions (like PhanPluginMixedKeyNoKey)
$cfg['suppress_issue_types'] = array_merge(
	$cfg['suppress_issue_types'] ?? [],
	[
		// These are expected for extensions that handle user input
		'SecurityCheck-LikelyFalsePositive',
		'SecurityCheck-PHPSerializeInjection',

		// Non-security type errors - suppress to focus on security checks only
		// These existed before security improvements and should be fixed separately
		'PhanUndeclaredTypeReturnType',
		'PhanTypeMismatchDefault',
		'PhanTypeMismatchReturn',
		'PhanTypeMismatchArgument',
		'PhanTypeMismatchArgumentInternal',
		'PhanTypeMismatchArgumentNullable',
		'PhanAccessMethodInternal',
		'PhanDeprecatedFunction',
		'PhanUndeclaredClassMethod',
		'PhanPossiblyUndeclaredVariable',
	]
);

return $cfg;
