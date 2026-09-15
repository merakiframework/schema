<?php
declare(strict_types=1);

/**
 * Writes CHANGELOG.md from the commit history.
 *
 * Generated rather than hand-kept, because a hand-kept changelog is a second place to write
 * down what a commit already said, and the two drift the first time somebody is in a hurry.
 * The commit messages here carry a subject and a body explaining *why*, which is most of a
 * changelog entry already.
 *
 * What it does not do is guess at significance. There is no attempt to sort commits into
 * "Added / Changed / Fixed": that classification lives in the author's head at commit time
 * and a script inferring it from a verb produces confident nonsense. Entries appear in the
 * order they were made, under the release that contains them.
 *
 * Usage:
 *
 *     php tools/changelog.php            # write CHANGELOG.md
 *     php tools/changelog.php --check    # fail if it is out of date (for CI)
 *     php tools/changelog.php -          # write to stdout
 */

const REPO = __DIR__ . '/..';

/**
 * @return list<array{hash: string, subject: string, body: string, date: string, tag: string|null}>
 */
function commits(): array
{
    // Separators that cannot occur in a commit message. git writes them itself, via its own
    // %x escapes, rather than them being passed through as bytes.
    $sep = "\x1e";
    $field = "\x1f";

    // proc_open with an *array* runs git directly instead of through a shell. That matters on
    // Windows, where cmd.exe treats `%` as the start of a variable and quietly eats every
    // placeholder in the format string.
    $process = proc_open(
        ['git', '-C', REPO, 'log', '--reverse', '--no-merges', '--format=%H%x1f%s%x1f%cs%x1f%D%x1f%b%x1e'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (!is_resource($process)) {
        fwrite(STDERR, "Could not run git.\n");

        exit(1);
    }

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        fwrite(STDERR, "Not a git repository, or git is unavailable.\n" . $err);

        exit(1);
    }

    $commits = [];

    foreach (explode($sep, (string) $out) as $record) {
        $record = trim($record, "\n");

        if ($record === '') {
            continue;
        }

        [$hash, $subject, $date, $refs, $body] = array_pad(explode($field, $record, 5), 5, '');

        $commits[] = [
            'hash' => substr($hash, 0, 8),
            'subject' => trim($subject),
            'body' => trim($body),
            'date' => $date,
            'tag' => tagIn($refs),
        ];
    }

    return $commits;
}

/** The tag a commit carries, if any — `tag: v2.0.0` among the ref names. */
function tagIn(string $refs): ?string
{
    foreach (explode(', ', $refs) as $ref) {
        if (str_starts_with($ref, 'tag: ')) {
            return substr($ref, 5);
        }
    }

    return null;
}

/**
 * Groups commits under the release that contains them.
 *
 * Walking forwards and closing a section when a tag appears means a commit belongs to the first
 * release made *after* it, which is what "shipped in" means. Anything after the last tag is
 * unreleased.
 *
 * @param list<array{hash: string, subject: string, body: string, date: string, tag: string|null}> $commits
 * @return list<array{release: string, date: string|null, commits: list<array<string, mixed>>}>
 */
function releases(array $commits): array
{
    $releases = [];
    $pending = [];

    foreach ($commits as $commit) {
        $pending[] = $commit;

        if ($commit['tag'] !== null) {
            $releases[] = ['release' => $commit['tag'], 'date' => $commit['date'], 'commits' => $pending];
            $pending = [];
        }
    }

    if ($pending !== []) {
        $releases[] = ['release' => 'Unreleased', 'date' => null, 'commits' => $pending];
    }

    // Newest first, which is the order a changelog is read in.
    return array_reverse($releases);
}

function render(array $releases): string
{
    $out = <<<'MD'
# Changelog

**Generated from the commit history** by `php tools/changelog.php`. Do not edit by hand — a
hand-kept changelog is a second place to write down what a commit already said, and the two drift
the first time somebody is in a hurry.

Entries are not sorted into "Added / Changed / Fixed". That classification lives in the author's
head at commit time, and a script inferring it from a verb produces confident nonsense. Each entry
is a commit subject, with the body kept because the body is where the reasoning is.

MD;

    foreach ($releases as $release) {
        $heading = $release['date'] === null
            ? '## Unreleased'
            : sprintf('## %s — %s', $release['release'], $release['date']);

        $out .= "\n" . $heading . "\n";

        foreach (array_reverse($release['commits']) as $commit) {
            $out .= "\n### " . $commit['subject'] . "\n\n";
            $out .= sprintf("`%s` · %s\n", $commit['hash'], $commit['date']);

            $body = preg_replace('/\n?Co-Authored-By:.*$/s', '', $commit['body']) ?? '';
            $body = trim($body);

            if ($body !== '') {
                $out .= "\n" . $body . "\n";
            }
        }
    }

    return $out;
}

$rendered = render(releases(commits()));
$target = REPO . '/CHANGELOG.md';

if (in_array('-', $argv, true)) {
    echo $rendered;

    exit(0);
}

if (in_array('--check', $argv, true)) {
    $current = is_file($target) ? file_get_contents($target) : '';

    if ($current === $rendered) {
        echo "  CHANGELOG.md is up to date\n";

        exit(0);
    }

    fwrite(STDERR, "CHANGELOG.md is out of date. Run: php tools/changelog.php\n");

    exit(1);
}

file_put_contents($target, $rendered);

printf("  wrote CHANGELOG.md (%d releases, %d commits)\n", count(releases(commits())), count(commits()));
