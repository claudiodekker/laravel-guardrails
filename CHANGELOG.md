# Changelog

## 1.4.1

- `OneClausePerLine` lets the first clause of a condition stay on the line of the opening parenthesis, as in `if ($a` followed by `&& $b` and `&& $c` on lines of their own. The clauses after the first still start their own line with their operator. Code that passed before still passes. ([#14](https://github.com/claudiodekker/laravel-guardrails/pull/14))

## 1.4.0

- Add the opt-in `floor` preset, with six new checks that refuse a change which lowers the quality bar instead of fixing the code. No other preset includes them, and the installer never pre-ticks `floor`. Apps that already have a `guardrails.php` turn them on by adding them there. The `opinionated` description now says it leaves out the strict and floor checks. ([#12](https://github.com/claudiodekker/laravel-guardrails/pull/12))
- Add the `NoSkippedTests` check. It flags `markTestSkipped()`, `markTestIncomplete()`, Pest's `todo()` function, and a `skip()` or `todo()` chained onto a Pest `it()`, `test()` or `describe()`. A collection's `skip()` in a test passes. ([#12](https://github.com/claudiodekker/laravel-guardrails/pull/12))
- Add the `NoAnalyserIgnores` check. It flags every `@phpstan-ignore` and `@psalm-suppress` comment. It complements `PhpstanIgnoresNameIdentifier`, which asks an ignore to name its error, so both may flag one line. ([#12](https://github.com/claudiodekker/laravel-guardrails/pull/12))
- Add the `PhpstanBaselineNeverGrows` check. It fails a change that makes a PHPStan baseline, in NEON or PHP, ignore more errors than before, and a new baseline that ignores any. A baseline that shrinks passes. ([#12](https://github.com/claudiodekker/laravel-guardrails/pull/12))
- Add the `PhpstanLevelNeverLowers` check. It flags the level line when a change lowers the level in `phpstan.neon`, and the file when a change removes the level or deletes the file. `max` counts as higher than every number. ([#12](https://github.com/claudiodekker/laravel-guardrails/pull/12))
- Add the `NoPhpUnitExclusions` check. It flags each group, directory or file an `<exclude>` in `phpunit.xml` lists, and an `<exclude>` inside a test suite. ([#12](https://github.com/claudiodekker/laravel-guardrails/pull/12))
- Add the `NoRemovedAssertions` check. It flags a PHPUnit or Pest test that has fewer assertions than before. A new, renamed or deleted test passes. ([#12](https://github.com/claudiodekker/laravel-guardrails/pull/12))

## 1.3.0

- Add two checks for parentheses around a value that a method is called on. `NoParenthesesAroundCallReceivers` flags parentheses around a call that change nothing, such as `($user->team())->role()`. `NoParenthesisedReceiverInConditions` flags a `match`, `switch`, `if`, `elseif` or `while` that opens with a doubled parenthesis, such as `match ((new Foo)->bar())`, and asks you to name the value in a variable first. `(new Carbon)->addDay()` and grouping such as `if (($a && $b) || $c)` pass. The `opinionated` preset offers both, and apps that already have a `guardrails.php` turn them on by adding them there. ([#11](https://github.com/claudiodekker/laravel-guardrails/pull/11))
- Deprecate `NoMatchOnCall` in favour of the two parentheses checks. It keeps flagging what it did, so a `guardrails.php` that lists it runs as before, but no preset offers it any more. It is removed in 2.0. ([#11](https://github.com/claudiodekker/laravel-guardrails/pull/11))
- `NoMixedTypes` allows `mixed` where the docblock gives a template type, such as `@return TResult` with `@template TResult` on the function or its class, because PHP cannot declare one. A docblock type PHP can declare, such as `string|null`, still fails. ([#10](https://github.com/claudiodekker/laravel-guardrails/pull/10))

## 1.2.0

- Add the `NoEmptyBraces` check. It flags an empty `{}` body on one line and asks for three lines instead: the `{`, an indented line holding only `//`, and the `}`. Closures and anonymous classes are left alone. The `laravel` and `opinionated` presets offer it, and apps that already have a `guardrails.php` turn it on by adding it there. ([#7](https://github.com/claudiodekker/laravel-guardrails/pull/7))
- Add the opt-in `strict` preset for package code, with two new checks. `ClassesTaggedApiOrInternal` flags a class, interface, trait or enum whose docblock has neither `@api` nor `@internal`. `NoMixedTypes` flags `mixed` as a parameter, return or property type. Both cover `src` by default and take the folders they cover, such as `new NoMixedTypes(['app/Billing'])`. No other preset includes them, and the installer never pre-ticks `strict`. ([#8](https://github.com/claudiodekker/laravel-guardrails/pull/8))

## 1.1.0

- Add the `ClassDocblocksHoldOnlyTags` check. It flags prose in the docblock of a class, interface, trait or enum, which may hold only tags such as `@extends`, `@mixin` or `@phpstan-type`. List a class whose note fits nowhere else with `allowing()`. The `laravel` and `opinionated` presets offer it, and apps that already have a `guardrails.php` turn it on by adding it there. ([#3](https://github.com/claudiodekker/laravel-guardrails/pull/3))

## 1.0.1

- `NoTestCodeInApp` no longer fails `guardrails.php` for importing checks from a namespace `autoload-dev` maps. Only the guardrails command loads that file, and it runs with the dev dependencies installed. ([#1](https://github.com/claudiodekker/laravel-guardrails/issues/1))
- The custom check examples in the README and docs now live under `Tests\Guardrails`, because `Check` comes from a dev dependency.

## 1.0.0

This is the first release of Laravel Guardrails. See the [README](README.md) to get started.
