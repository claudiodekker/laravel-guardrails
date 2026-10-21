# How a run works

## What counts as new

`guardrails --staged` and `guardrails --base=<ref>` fail only on new code:

- A failure counts when the change added or changed any line the failure spans.
- A change that only deletes lines counts as a change to the line at the deletion point and the line after it.
- A failure of a whole file, such as a test outside its test suites, counts unless the file had the same failure before the change. Moving a test out of its test suites fails, and editing a test that was already outside them does not.
- A file moved into a check's reach counts in full, as a new file does. That covers a file moved into an `only()` path, out of an `allowing()` path, or into a kind the check reads.
- A file git does not diff, such as a binary file or one marked `-diff` in `.gitattributes`, counts every line as changed.
- A file over the token budget counts when the change makes it larger.
- A renamed file counts as unchanged for the rules about lines.
- A deleted file reaches only the checks that judge the change itself, such as a rule that a committed migration is never deleted. See [Judge the change itself](judging-changes.md).
- `git commit --amend` compares the index against the commit being amended, so only what you add to that commit is new.
- During a merge, an octopus merge included, `--staged` compares the index with every parent, and a failure counts only when it is new against each of them. It skips a path that is still unresolved.
- When the `composer.json` or `phpunit.xml` of the base does not parse, the files of the change are judged in full, as if they were new, so the change that repairs it passes on its own merits.
- Your own diff settings change nothing. The command fixes the options it passes to git, rename detection included, and ignores `GIT_DIFF_OPTS` and `GIT_EXTERNAL_DIFF`.

So an app fresh from `laravel new` passes, and so does a commit that touches a large file without growing it.

## Files no check reads

Every check skips binary files, the lockfiles of Composer, npm, Yarn, pnpm and Bun, files in a `vendor` folder at any depth, and files marked `linguist-generated` in `.gitattributes`. Symlinks and submodules are not files to any check, in any mode. A Blade template is text to the checks that read text, such as `NoEmDashes`, and holds no PHP code for the others. A file in a `stubs` folder at any depth is a template, so it holds no PHP code either: `NoSyntaxErrors` does not parse it and the checks that read code find nothing in it, while the checks that read text, such as `NoEmDashes`, still read it.

## Kinds of code

Each check reads only the kinds of code its rule is about: `source`, `tests`, `config`, `migrations`, `routes`, `views`, `frontend`, `lang`, or `other files`, which is every text file outside those, such as the docs and the configuration of other tools. A check may also narrow by a file name, such as `*Test.php`, or by a folder name at any depth, such as `Concerns` or `Unit`.

The command finds the folders of each kind in what the project already declares, so there is nothing to configure:

- Source code is every path under `autoload` in `composer.json`. A stock Laravel app gives `app`, `database/factories` and `database/seeders`.
- Tests are every path under `autoload-dev`, plus each `<testsuite>` in `phpunit.xml`, or in `phpunit.xml.dist` when there is no `phpunit.xml`. A `*` in a test suite folder, such as `packages/*/tests`, matches one folder name.
- Config, migrations, routes, views, frontend code and translations are their usual Laravel folders (`config`, `database/migrations`, `routes`, `resources/views`, `resources/js` and `resources/css`, `lang` and `resources/lang`), plus the same folders inside each source root or beside its parent.

Without `autoload` paths, source code is `app` and `src`. Without `autoload-dev` paths, tests are `tests`. A file has exactly one kind. A file under two kinds belongs to the deeper root, so `src/Testing` under `autoload-dev` is tests while the rest of `src` is source code. At the same depth, tests win over migrations, migrations over config, config over translations, translations over routes, routes over views, views over frontend code, and frontend code over source code.

A new kind can only be carved out of `other files`, never out of another kind. A check that lists `other files` therefore also reads every kind carved out of it later, and never loses a file to a kind added later.

A monorepo needs no setting either. When the root `composer.json` autoloads `packages/billing/src`, the command reads `packages/billing/config` as config, `packages/billing/database/migrations` as migrations, and the other folders of the package the same way. A package the root requires through a path repository inside the project, such as `{"type": "path", "url": "packages/*"}`, is read the same way from its own `composer.json`.

## The pre-commit hook

After every `composer install` and `composer update`, the plugin puts a `pre-commit` hook in the repository's hooks directory. It asks git where that directory is, so a linked worktree and a custom `core.hooksPath` work. When the app lives in a subfolder of the repository, such as `backend/`, the hook runs from there.

- It never overwrites a hook it did not write. It prints the line to add to that hook instead.
- It installs nothing on CI (when `CI`, `GITHUB_ACTIONS`, `GITLAB_CI` or `JENKINS_URL` is set), outside a git repository, or in a project that does not require the package.
- Installing again changes nothing, and removing the package removes the hook.

With `--staged`, the run reads `guardrails.php`, `composer.json` and `.gitattributes` from the index too, so an edit you have not staged cannot change the verdict. Until git tracks `guardrails.php`, such as right after `guardrails install`, the run reads it from disk. Against a base or over every file, it reads them as the working tree holds them. In `--staged` mode the classes of your own checks and the discovery of check packs, which reads the `provides` of `composer.json` and `vendor/composer/installed.json`, still come from disk.

A run against a base reads commits only. When the working tree or index holds changes it did not read, it prints one line on stderr that says so and points at `--staged`. The wording of the text output is not a stable interface yet.

## Other hook managers

One hook serves one Composer project per repository. In a repository with several, install the hook of the one you want to check, or run `guardrails --staged` from each project in your own hook.

With the pre-commit framework, run the command without the file names it passes, since the command reads the staged changes itself:

```yaml
repos:
  - repo: local
    hooks:
      - id: guardrails
        name: guardrails
        entry: vendor/bin/guardrails --staged
        language: system
        pass_filenames: false
```

With lint-staged, use the function form, which ignores the file names too:

```js
export default {
  '*': () => 'vendor/bin/guardrails --staged',
}
```

## Editing guardrails.php by hand

`guardrails install` manages the entries of the checks that packages offer, and leaves the rest of the file alone:

- A configured entry, such as `new FilesWithinTokenBudget(limit: 8000)` or `(new NoEval)->only(...)`, stays as you wrote it, with the comments and blank lines around it. When you turn that check off, its entry goes, with any comment on the same line.
- An entry of a check no package offers, such as `NoNamespaces` or a rule of your own that `composer.json` does not declare, stays whatever you select.
- The comment at the top of the file stays as it is.
