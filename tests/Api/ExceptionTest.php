<?php
declare(strict_types=1);

namespace Meraki\Schema\Api;

use Meraki\Schema\Exception;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * Everything this library throws is something it named, and it says so in its type.
 *
 * ### The claim this is here to make true
 *
 * `catch (Meraki\Schema\Exception $e)` is documented as covering the whole library — see
 * *What raises, and what does not* in [API.md](../../docs/API.md). That is a promise about every
 * throw in `src/`, and nothing about writing `throw new InvalidArgumentException(...)` makes it
 * fail loudly: the code runs, the message is fine, and the only symptom is a caller's `catch`
 * quietly not firing for one case out of ninety.
 *
 * So the check is on the source rather than on behaviour. Behaviour cannot see the throw that
 * nobody wrote a test for, and the throws worth catching this way are exactly the ones nobody
 * writes a test for.
 *
 * ### Why the second half is a source scan and not reflection
 *
 * `@throws` is a docblock and PHP does not enforce it, so there is nothing at runtime to ask.
 * Reading the text is the only way to see a `throw` that no test reaches — which is the point.
 */
#[Group('api')]
#[CoversNothing]
final class ExceptionTest extends TestCase
{
	private const SRC = __DIR__ . '/../../src';

	/**
	 * Both halves of the promise in one sentence: if it is a Throwable defined here, it is
	 * catchable as a Meraki\Schema\Exception.
	 */
	#[Test]
	public function every_exception_this_library_defines_carries_the_marker(): void
	{
		$missing = [];

		foreach (self::classesIn(self::SRC) as $class) {
			if (!is_a($class, Throwable::class, true)) {
				continue;
			}

			if (!is_a($class, Exception::class, true)) {
				$missing[] = $class;
			}
		}

		$this->assertNotEmpty(
			array_filter(self::classesIn(self::SRC), static fn(string $c): bool => is_a($c, Throwable::class, true)),
			'found no exception classes at all, so this test is passing for the wrong reason',
		);

		$this->assertSame([], $missing, 'these do not implement Meraki\Schema\Exception');
	}

	/**
	 * A generic throw is not wrong so much as unnameable: `InvalidArgumentException` says a value
	 * was bad and nothing else, so the only place the reason can live is a string nobody can catch
	 * on, match on or translate.
	 */
	#[Test]
	public function nothing_in_src_throws_a_generic_exception(): void
	{
		$generic = [
			'Exception',
			'ErrorException',
			'LogicException',
			'BadFunctionCallException',
			'BadMethodCallException',
			'DomainException',
			'InvalidArgumentException',
			'LengthException',
			'OutOfRangeException',
			'RuntimeException',
			'OutOfBoundsException',
			'OverflowException',
			'RangeException',
			'UnderflowException',
			'UnexpectedValueException',
		];

		$found = [];

		foreach (self::phpFilesIn(self::SRC) as $file) {
			$source = file_get_contents($file->getPathname());

			if ($source === false) {
				continue;
			}

			foreach ($generic as $class) {
				if (preg_match('/throw new \\\\?' . $class . '\s*\(/', $source) === 1) {
					$found[] = self::relative($file) . ' throws ' . $class;
				}
			}
		}

		$this->assertSame([], $found, 'give these a class in Meraki\Schema\Exception instead');
	}

	/**
	 * The docblock half. `@throws InvalidArgumentException` tells a reader to catch the one thing
	 * that will also catch three other libraries' mistakes, which is worse than saying nothing.
	 */
	#[Test]
	public function no_docblock_in_src_promises_a_generic_exception(): void
	{
		$found = [];

		foreach (self::phpFilesIn(self::SRC) as $file) {
			$source = file_get_contents($file->getPathname());

			if ($source === false) {
				continue;
			}

			// The exception classes themselves say what they extend, in prose and in `extends`.
			if (str_contains($source, 'implements Exception')) {
				continue;
			}

			if (preg_match('/@throws \\\\?((?:Logic|Runtime|InvalidArgument|Unexpected\w+|Domain)?Exception)\b/', $source, $m) === 1) {
				$found[] = self::relative($file) . ' promises ' . $m[1];
			}
		}

		$this->assertSame([], $found, 'name the class that is really thrown');
	}

	/**
	 * @return list<class-string>
	 */
	private static function classesIn(string $dir): array
	{
		$classes = [];

		foreach (self::phpFilesIn($dir) as $file) {
			$source = file_get_contents($file->getPathname());

			if ($source === false || preg_match('/^namespace ([^;]+);/m', $source, $ns) !== 1) {
				continue;
			}

			$class = $ns[1] . '\\' . $file->getBasename('.php');

			if (class_exists($class) && (new ReflectionClass($class))->getFileName() !== false) {
				$classes[] = $class;
			}
		}

		return $classes;
	}

	/**
	 * @return list<SplFileInfo>
	 */
	private static function phpFilesIn(string $dir): array
	{
		$files = [];
		$walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

		foreach ($walk as $file) {
			if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
				$files[] = $file;
			}
		}

		return $files;
	}

	private static function relative(SplFileInfo $file): string
	{
		$path = str_replace('\\', '/', $file->getPathname());
		$at = strrpos($path, '/src/');

		return $at === false ? $path : substr($path, $at + 1);
	}
}
