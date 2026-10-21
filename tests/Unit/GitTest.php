<?php

use ClaudioDekker\LaravelGuardrails\Git;

it('reads the lines each hunk adds or changes', function () {
    $patch = "diff --git a/x b/x\n--- a/x\n+++ b/x\n@@ -2 +2 @@ one\n-y\n+Y\n@@ -5,2 +5,4 @@ four\n-a\n-b\n+c\n+d\n+e\n+f\n";

    expect(Git::changedLines($patch, 9))->toBe([2, 5, 6, 7, 8]);
});

it('counts a deletion at the line it leaves and the one after it', function () {
    $patch = "diff --git a/x b/x\n--- a/x\n+++ b/x\n@@ -5,3 +4,0 @@ four\n-/**\n- * Do it.\n- */\n";

    expect(Git::changedLines($patch, 9))->toBe([4, 5]);
});

it('counts a deletion at the top of the file at its first line', function () {
    $patch = "diff --git a/x b/x\n--- a/x\n+++ b/x\n@@ -1,2 +0,0 @@\n-one\n-two\n";

    expect(Git::changedLines($patch, 9))->toBe([1]);
});

it('counts every line of a file git does not diff', function () {
    $patch = "diff --git a/x b/x\nindex a6e0e24..f0f2307 100644\nBinary files a/x and b/x differ\n";

    expect(Git::changedLines($patch, 3))->toBe([1, 2, 3])
        ->and(Git::changedLines($patch, 0))->toBe([]);
});

it('reads a raw diff and the patch of its edits into the diff of each file, by the path the change leaves or deletes', function () {
    $raw = implode("\0", [
        ':100644 100644 '.str_repeat('1', 40).' '.str_repeat('2', 40).' M', 'app/Edited.php',
        ':000000 100644 '.str_repeat('0', 40).' '.str_repeat('3', 40).' A', 'app/New.php',
        ':100644 000000 '.str_repeat('4', 40).' '.str_repeat('0', 40).' D', 'app/Old.php',
        ':100644 100644 '.str_repeat('5', 40).' '.str_repeat('6', 40).' R087', 'app/From.php', 'app/To.php',
    ])."\0";

    $patch = "diff --git a/app/Edited.php b/app/Edited.php\n@@ -3 +3 @@\n-a\n+b\ndiff --git a/app/From.php b/app/To.php\nsimilarity index 87%\n@@ -5 +5 @@\n-c\n+d\n";

    expect(Git::diffs($raw, $patch))->toBe([
        'app/Edited.php' => ['path' => 'app/Edited.php', 'previousPath' => 'app/Edited.php', 'blob' => str_repeat('2', 40), 'previousBlob' => str_repeat('1', 40), 'patch' => "a/app/Edited.php b/app/Edited.php\n@@ -3 +3 @@\n-a\n+b\n"],
        'app/New.php' => ['path' => 'app/New.php', 'previousPath' => 'app/New.php', 'blob' => str_repeat('3', 40), 'previousBlob' => null, 'patch' => ''],
        'app/Old.php' => ['path' => 'app/Old.php', 'previousPath' => 'app/Old.php', 'blob' => null, 'previousBlob' => str_repeat('4', 40), 'patch' => ''],
        'app/To.php' => ['path' => 'app/To.php', 'previousPath' => 'app/From.php', 'blob' => str_repeat('6', 40), 'previousBlob' => str_repeat('5', 40), 'patch' => "a/app/From.php b/app/To.php\nsimilarity index 87%\n@@ -5 +5 @@\n-c\n+d\n"],
    ]);
});

it('leaves out the side of a file that is a link or a submodule, so a type change becomes an addition or a deletion', function () {
    $raw = implode("\0", [
        ':100644 000000 '.str_repeat('7', 40).' '.str_repeat('0', 40).' U', 'conflict.txt',
        ':160000 160000 '.str_repeat('a', 40).' '.str_repeat('b', 40).' M', 'lib',
        ':120000 120000 '.str_repeat('8', 40).' '.str_repeat('9', 40).' M', 'link',
        ':120000 100644 '.str_repeat('c', 40).' '.str_repeat('d', 40).' T', 'now-file',
        ':100644 120000 '.str_repeat('e', 40).' '.str_repeat('f', 40).' T', 'now-link',
        ':100644 100644 '.str_repeat('1', 40).' '.str_repeat('2', 40).' M', 'z.txt',
    ])."\0";

    $patch = "diff --git a/lib b/lib\n@@ -1 +1 @@\n-Subproject commit aaa\n+Subproject commit bbb\ndiff --git a/link b/link\n@@ -1 +1 @@\n-t1\n+t2\ndiff --git a/z.txt b/z.txt\n@@ -1 +1 @@\n-y\n+z\n";

    expect(Git::diffs($raw, $patch))->toBe([
        'now-file' => ['path' => 'now-file', 'previousPath' => 'now-file', 'blob' => str_repeat('d', 40), 'previousBlob' => null, 'patch' => ''],
        'now-link' => ['path' => 'now-link', 'previousPath' => 'now-link', 'blob' => null, 'previousBlob' => str_repeat('e', 40), 'patch' => ''],
        'z.txt' => ['path' => 'z.txt', 'previousPath' => 'z.txt', 'blob' => str_repeat('2', 40), 'previousBlob' => str_repeat('1', 40), 'patch' => "a/z.txt b/z.txt\n@@ -1 +1 @@\n-y\n+z\n"],
    ]);
});

it('refuses a patch whose blocks do not match the entries of the raw diff', function () {
    $patch = "diff --git a/a b/a\n@@ -1 +1 @@\n-one\n+two\n";

    expect(fn () => Git::blocks($patch, 2))->toThrow(RuntimeException::class, 'git printed 1 of the 2 patch blocks its raw diff calls for.');
});
