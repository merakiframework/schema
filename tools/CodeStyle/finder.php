<?php
declare(strict_types=1);

/**
 * The set of files both fixer configs look at.
 *
 * Shared rather than duplicated because a path added to one and forgotten in the other is a
 * file that gets formatted but never checked, which is the failure nobody notices.
 */

use PhpCsFixer\Finder;

return static function (string $root): Finder {
	return Finder::create()
		->in([
			$root . '/src',
			$root . '/tests',
			$root . '/tools',
			$root . '/examples',
		])
		->append([
			// No .php extension, and a shebang before the open tag.
			$root . '/bin/schema-lang',
			// The configs format themselves.
			$root . '/.php-cs-fixer.dist.php',
			$root . '/.php-cs-fixer-risky.dist.php',
		])
		// Inputs to the .mfr parser, not code.
		->notPath('fixtures');
};
