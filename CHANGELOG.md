# Changelog

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
