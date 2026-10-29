## Overview
This repository hosts the webtrees descendants chart module — an interactive SVG descendants (rectangular tree) chart of an individual's descendants using D3.js, installed as a Composer package inside webtrees.

## Setup/env
- PHP 8.3+ with extensions dom and json is required; composer installs dependencies into .build/vendor and binaries into .build/bin.
- Node.js tooling is used for asset builds (rollup). Install dev dependencies via `npm install` when touching frontend resources.
- All PHP/Node tooling runs inside the webtrees Docker buildbox — never directly on the NAS or in phpfpm:
  ```
  cd /volume2/docker/webtrees && make bash
  cd app/vendor/magicsunday/webtrees-descendants-chart
  ```
- JS bundles are built via the node Docker container from the module directory: `make install`, `make build`.
- After PHP or JS changes visible in the browser: `docker restart webtrees-phpfpm-1`.
- After JS changes, always verify in the browser via Playwright before claiming success.

### Convention for new advisory ignores

Before adding an entry, check the following. Is the package a direct dependency (`composer why`)? Does a public fix exist? Does an exact pin of webtrees alone block that fix (`composer why-not <package> <fixed version>`)? Update instead of ignoring whenever the fix is reachable.

- An advisory that webtrees' exact pin keeps unresolved is ignored by its **advisory ID** under `config.policy.advisories.ignore-id`, so later advisories on the same package keep blocking. Never ignore by package name under `ignore`, because that also hides every later advisory of the package.
- The reason starts with a prefix that says why the entry exists, followed by the CVE and the condition for removing it. `UPSTREAM-PINNED:` marks a fix that exists publicly but is held back by the exact pin of webtrees. `ACCEPTED:` is reserved for a conscious risk acceptance with a low severity and no attack path in this package.
- Remove the entry once the supported webtrees range no longer pins an affected version.

## Build & tests
- **`composer ci:test` MUST run before every commit** — catches Biome, PHPStan, PHP-CS-Fixer, Rector, PHPUnit, Jest, and CPD issues before they reach GitHub CI.
- Individual checks: `composer ci:test:php:phpstan`, `composer ci:test:php:unit`, `composer ci:test:php:cgl`, `composer ci:test:js:lint`, `composer ci:test:js:unit`.
- `composer ci:test:php:cpd` runs the installed `node_modules/.bin/jscpd`, so the Node dependencies must be installed (`make install` or `npm ci`). jscpd is pinned to an exact version in `package.json`. CI runs it as its own job through the shared `cpd.yml` workflow of the `.github` repository, reported as `cpd / Copy-paste detection`.
- `composer ci:test:php:infection` runs mutation testing against `src/` with a gating score floor in `infection.json5`. It is not part of `composer ci:test`, because a mutation run is too slow for every local check. CI runs it as the mutation step of the shared `php-quality.yml` workflow, on one PHP leg of its matrix. The floor sits a little below the measured score, so raise it as the score improves and never lower it to admit a regression.
- Single PHPUnit test: `composer ci:test:php:unit -- --filter TestClassName`.
- Auto-fix: `composer ci:cgl` (PHP style), `composer ci:rector` (Rector), `npm run lint:fix` (Biome).
- JS bundles: `make build` (rollup), `make watch` (dev rebuild loop).
- Translations: `make lang` runs the i18n pipeline and compiles every `.po` to its `.mo`. A text of this module is translated in the locales that ship a translation for it. A text that webtrees already translates stays empty, see the catalogue rules below.
- **Catalogue rules.** webtrees merges the compiled catalogue of a module over its own, so an entry here replaces the webtrees translation of the same text in the whole installation, not only on this module's pages.
    - Leave the `msgstr` empty for every text that webtrees core translates in that locale, without exception. A compiled entry replaces the core text in the whole installation and goes stale when core rewords it. This holds for the chart title (`Descendants chart`) and the button and form labels as well. Where core translates them, the core wording is used. The `core-overlap` job of the shared `i18n.yml` workflow compares every catalogue with the `.po` files of the webtrees releases the module supports and fails a pull request that fills such a text, the annotation names the file and line. A text that core does not translate in a locale may be filled. Fuzzy entries and plural entries with an empty first form are not compiled and do not count.
    - Source texts are American English like in webtrees core. There is no `en-US` catalogue, the source text is the `en-US` text. `en-GB` holds the British variants of changed texts and no copy of the source text. When a source text changes its spelling, change the `msgid` in every catalogue in the same commit so that no entry turns fuzzy.
    - A plural entry is either left empty entirely or has every form filled in, and the number of forms (the `msgstr[N]` slots of an empty entry included) is the number the webtrees plural rule of the locale selects from (`Locale::create($tag)->pluralRule()->plurals()` in `fisharebest/localization`). webtrees ignores the `Plural-Forms` header of the PO, so the header only has to agree with that rule. `tests/CatalogueStructureTest.php` extends the shared catalogue structure case of `webtrees-module-base` and names the catalogue directory. The shared case enforces the form count, the empty forms and the placeholders of the compiled catalogue, the slot count of the source catalogue and the form count in its header, and renders every plural entry for a range of numbers.
- **Every class under `src/` is final**, and a phpat rule in `tests/Architecture/ArchitectureTest.php` enforces it (the rule does not cover traits). A class that a test needs to stub is not made final. It is excluded from the rule with a reason.
- **PHPStan runs the shared strict tier** (`phpstan.neon` includes `phpstan/strict.neon` of `magicsunday/coding-standard`, which needs `shipmonk/phpstan-rules`, `symplify/phpstan-rules` and `spaze/phpstan-disallowed-calls` as devDependencies). Fix a finding in code. An irreducible idiom conflict gets an `ignoreErrors` entry scoped by identifier and path with a comment, never a baseline.
- Keep PHPStan, PHPCS, and CPD clean on affected code; add PHPUnit attribute-based coverage (positive and negative cases) for every class/method introduced or modified.
- If `node_modules` has permission issues (from node container), clean via: `docker compose run --rm buildbox-root bash -c "rm -rf app/vendor/magicsunday/webtrees-descendants-chart/node_modules"`.
- **Shared JS/TS configs**: `biome.json` extends `@magicsunday/coding-standard/biome/base.json` and `tsconfig.json` extends `@magicsunday/coding-standard/tsconfig/base` (npm devDependency `github:magicsunday/coding-standard#<tag>`). Keep only repository-specific settings locally (`files.includes`, module/target/lib/paths); never switch a shared rule off or a pinned strict flag (`strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `noImplicitOverride`, `forceConsistentCasingInFileNames`, `isolatedModules`) back to false — `composer ci:test:php:templates` rejects it. `@biomejs/biome` and `typescript` stay own devDependencies within the shared package's peer ranges.
- **Typecheck scope is intentionally limited to production sources** (`tsconfig.json` includes only `resources/js/modules/**/*.js`). Tests under `resources/js/tests/**` are validated by jest at runtime but skipped by `tsc --noEmit` because the partial mock objects required to isolate units would otherwise produce dozens of structural-incompatibility errors against the real `Configuration` / `Selection` shapes. The strict-typecheck gate applies to shipped code; test scaffolding stays loose.

## Architecture

### Data flow: PHP → JSON → D3

```
Module.php (entry point, registers routes)
  → page.phtml (form + AJAX container, localStorage via Storage)
    → chart.phtml (import() loads ES module, passes config as JS object)
      → DataFacade.php (builds Node tree from Individual records)
        → DateProcessor, NameProcessor, ImageProcessor (from module-base)
          → NodeData → JSON → D3 hierarchy → SVG rendering
```

### PHP (`src/`)
- **Module.php** — Entry point, extends webtrees DescendantsChartModule, registers chart route.
- **Configuration.php** — Reads form parameters from request (POST/GET) with user preference fallback.
- **Facade/DataFacade.php** — Builds hierarchical Node tree, recursing through spouse families to the configured generation depth. Passes `$spouse` and the married-names mode (`off` / `married_only` / `birth_and_married`) into NameProcessor and stores the result of `getNameSegments()` in the node data. In `birth_and_married` mode the segment list ends with the bracketed married surname, and the flat `name`, which the box tooltip reads, ends with the same text.
- **Enum/DescendantLine** — the `descendantLine` page option (`all`, `paternal`, `maternal`). `keepsLink($parentSex, $childSex)` is the single decision whether the line continues along a parent to child link, by the recorded sex of both. `paternal` keeps a link only when parent and child are male, `maternal` keeps it when the parent is female whatever the child, and a parent of unknown or other sex keeps no link. Under `paternal` a child of unknown or other sex is dropped too, under `maternal` such a child is kept as the end of the line. `DataFacade::buildCoupleStructure()` applies it where the children of a family are collected, so the merged children of the `hideSpouses` mode are filtered as well. The subject and its partner families always stay, the filter touches only the descendant links. `Configuration::getDescendantLine()` falls back to the `default_descendantLine` module preference and then to `all`, and the option travels in `getRouteToggleParams()`. Pedigree and fan charts show ancestors and have no counterpart.
- **Model/CoupleNode** — Tree node with JSON serialization for D3. Its members are the `NodeData` objects of module-base.
- **Shared classes from [`magicsunday/webtrees-module-base`](https://github.com/magicsunday/webtrees-module-base)** (composer dependency, the constraint is in `composer.json`):
  - `Processor/DateProcessor` — date extraction; descendants currently uses the legacy locale-aware methods (`getBirthDate`, `getDeathDate`, `getLifetimeDescription`).
  - `Processor/FactResolver`: the fact slots of a person (birth, death, the marriage and the divorce of the families of the person with the name of the spouse in `partner`, which the resolver adds by itself because the administration offers only facts of a person, and the optional tags of the tree preference `CHART_BOX_TAGS`). `DataFacade` builds it privately with the full place name and puts the slots into the person payload of every member, the partners included, only while `Configuration::getShowDetailPanel()` is on, so a chart without the detail panel pays neither their payload nor their database work.
  - `Processor/NameProcessor`, `Processor/ImageProcessor` — name/image extraction. NameProcessor's 3-arg constructor `($individual, $spouse, $useMarriedName)` and its `getNameSegments()` are used here.
  - `Model/NodeData` — the serialisable person record that the chart script reads. It carries the name as `nameSegments`, a list of `NameSegment` objects in display order.
  - `Model/NameSegment`, `Model/NameSegmentKind` — one drawable part of a name and its role (`given`, `surname`, `nickname`, `married`).
  - `Module/VersionInformation` — GitHub release-checking with file cache.
  - For local edits to module-base while developing descendants-chart, run `make link-base` (symlinks `.build/vendor/.../webtrees-module-base` → the sibling clone). Reverse with `make unlink-base` or any `composer install/update`.

### JS (`resources/js/modules/`)
Flat layout — every file is descendants-specific glue. Reusable base classes (Storage, ChartExport, ChartOverlay, ChartZoom, Orientations, measureText, truncateNames) and the render host of the linked tree charts (`LinkedTreeSvg`, `OrientationCollection`, `ImageBox`, `TextBox`, `TextRenderer`, `DateRenderer`, the `LAYOUT_*` constants, imported from the `linked-tree` subpath) live in the external [`@magicsunday/webtrees-chart-lib`](https://github.com/magicsunday/webtrees-chart-lib) package, shared with the fan- and pedigree-chart modules. Consumed via Git URL pinned in `package.json` (`github:magicsunday/webtrees-chart-lib#X.Y.Z`); chart-lib's `prepare` script builds its `dist/` during install, so `npm ci --ignore-scripts` will break the build.
- **`index.js`** — Exports `DescendantsChart` class (ES module entry point for Rollup).
- **`page-entry.js` / `page-init.js`** — UMD bundle (`descendants-chart-page.min.js`) loaded by `page.phtml`. Owns localStorage form-state wiring; `initPage()` resolves user options and publishes them under `WebtreesDescendantsChart.chartOptions` for `chart.phtml` getters.
- **Help popover**: `initHelpPopover()` and `syncHelpMode()` come from `@magicsunday/webtrees-chart-lib/infra`, which holds their behaviour and their tests. `initHelpPopover()` toggles the popover of the help button, mirrors the state in `aria-expanded` on the button and closes an open popover on Escape. The markup comes from the view `help-popover` of `webtrees-module-base`, which `resources/views/modules/components/buttonbar/help-button.phtml` renders with the translated rows of this module. The rows marked with a `mode` describe the default mode or the detail panel mode of the chart, and the rows without a mode apply to both. `syncHelpMode("default")` shows the rows of the mode that the checkbox `showDetailPanel` has at that moment, and `initPage()` calls it where it sets the AJAX URL of the chart, because the chart takes its mode from that load and not from a tick that is not submitted yet.
- **Detail panel**: `DetailPanel` and `PanelSelection` come from `@magicsunday/webtrees-chart-lib/chart-core`, which holds their behaviour and their tests. The markup comes from the view `detail-panel` of `webtrees-module-base`, which `page.phtml` renders with the translated texts of this module (the `aside` is hidden unless the switch `showDetailPanel` is on, and its parts are marked by `data-panel`), and the shared styles come with the `DetailPanel` of the chart library. `Module::boot()` registers the views of the module base with `SharedViews::register()`. This module provides the wiring. `index.js` creates the panel when the switch is on and hands it the callbacks `onIndividual`, `onCenter`, `onClose` and `onHidden`. `chart.js` makes a click or Enter select the person instead of navigating and passes `clearSelection` and `restoreFocus` on to the selection. The person boxes are focusable and operable with Enter or Space in both modes, and a key press does what a click does. The facts and the image come with the person payload, the panel orders the facts by `sortDay` and shows the `thumbnail` of the person as the box does. When the panel closes by its button or Escape, the focus goes back to the person first. In every case the selected mark is cleared.
- **`chart.js`** (D3 hierarchy.tree layout, click handling and selection), **`hierarchy.js`** (D3 hierarchy), **`tree.js`** (d3.tree layout, separation hook and the single draw of links and boxes), **`configuration.js`**.
- **`family-tree.js`** (CoupleNode → FamilyNode tree), **`separation.js`** (`pickGap` half-sibling detection — see issue #84).
- **`tree/`** — `name.js` (draws the name from the ordered `nameSegments`, extends the library `TextRenderer`), `node-drawer.js` (also uses the library `DateRenderer`), `link-drawer.js`, `connection-builder.js`.
- **`constants.js`** (sex tokens and the box gaps of the layout), **`d3.js`** (re-export facade).

### Views (`resources/views/`)
- **`descendants-chart/page.phtml`** — Form + AJAX container. Loads `descendants-chart-page.min.js` and calls `WebtreesDescendantsChart.initPage({ ajaxUrl })`.
- **`descendants-chart/chart.phtml`** — AJAX response: `<script type="module">` with `import()` to load ES module bundle. Reads user overrides from `WebtreesDescendantsChart.chartOptions ?? PHP defaults`.
- **`descendants-chart/form/*.phtml`** — Form partials (generations, orientation, display, details, behavior).
- **`charts/chart.phtml`** — Block template override (home page widget), uses `data-wt-ajax-url` pattern.

## Key patterns
- **ES module loading**: `import().then(({ DescendantsChart }) => ...)` in `<script type="module">`, avoiding the `webtrees.load()` race condition.
- **Storage flow**: `page-init.js` reads localStorage → publishes resolved options under `WebtreesDescendantsChart.chartOptions` → `chart.phtml` getter reads `opts.x ?? PHP default`.
- **Orientation strategy**: chart layout is configurable in 4 directions (top-bottom, bottom-top, left-right, right-left) via the `OrientationCollection` of the chart-lib `linked-tree` subpath and its matching `Orientation*` classes (node coordinate transforms + elbow connector geometry).
- **Married-name modes**: an admin-only default, set as `default_marriedNamesMode` on the module configuration page. The chart page has no form field for it, so it is neither registered in the page script nor forwarded in the chart URLs. The modes are `off`, `married_only` and `birth_and_married`. `married_only` swaps the primary to a `_MARNM` record matching the spouse's surname (NameProcessor's `useMarriedName` flag). `birth_and_married` keeps the birth-name primary and appends the married surname in brackets, e.g. `Schmidt (Müller)`. The bracketed text is the last name segment, of kind `married`, and it also ends the flat `name`, which the box tooltip reads. Migrates from the legacy boolean `default_showMarriedNames` preference (true → `married_only`, false → `off`).
- **Block template**: Overrides core `modules/charts/chart.phtml` — must stay in sync with webtrees core changes (e.g. VanillaJS conversion).

## Release process
Runs inside the buildbox (requires git, node, npm, composer, jq, zip, gh, sed):
```
make release 2.1.0
make release 2.1.0 NOTES_FILE=/tmp/notes.md
make release VERSION=2.1.0 NOTES="Bug fix release"
```
Pipeline (`make release X.Y.Z`):
- `release-check` — tools, semver, clean tree, no detached HEAD, no active `make link-base` symlink, gh auth (or `GH_TOKEN`).
- `release-prepare` — `sed_edit` macro updates `CUSTOM_VERSION` in `src/Module.php` with a post-write assertion; `jq_edit` macro (with `--indent 4` and post-write assertion) updates `package.json` version + `composer.json` webtrees pin → clean+rebuild JS bundles via `build-js-fresh` → commit → `dist` → tag (only after dist succeeds, so a dist failure does not leave a dangling tag).
- `dist`: symlink guard fires before composer touches anything, `composer update --no-dev --no-interaction`, `git archive HEAD` (respects `.gitattributes` export-ignore), bundles `magicsunday/webtrees-module-base` (`src/` and the shared `resources/views/`) into the zip's `vendor/` so manual ZIP installs work without composer, strips all `composer.json` files (`find vendor -name composer.json -delete`), atomic write via `.tmp` + rename + `zip -T` integrity check.
- `dist-smoke`: separate target, asserts required entries (module.php, LICENSE, the versioned JS bundle, module-base `src/` and its views `detail-panel.phtml` and `help-popover.phtml`) are present and forbidden ones (composer.json, assets/) are absent. CI runs this on every pull request and on every push to the branches that `ci.yml` lists.
- `release-publish` — `git push --tags`, `gh release create` with the zip + notes, emits `RELEASE_PUBLISHED version=X` marker for agent observers.
- `release-bump` — bump to `VERSION+1-dev`, keep the `~2.2.0` (stable) webtrees constraint, push. The dev tree tracks stable webtrees, not `dev-main`, because the `dev-main` i18n refactor removed APIs the module still uses and stable is what end users install; re-add `|| dev-main` here (and adapt the code) when webtrees' next stable nears.

## Code style

### PHP
- Follow PSR-12 with `declare(strict_types=1)` in every file.
- **No `mixed` types** — use specific types or union types.
- **No `empty()`** — use explicit comparisons (`=== ''`, `=== []`, `=== null`).
- Use enums or typed constants instead of magic numbers/strings.
- Prefer value objects over complex plain arrays.
- Provide explicit parentheses for complex conditional expressions.
- Use expressive variable names; English inline comments only at complex logic.
- Docblocks: Always multi-line format. Describe purpose, not just repeat the method name. Keep `@param` only when it adds information beyond type+name. Keep `@return` tags.

### JavaScript
- ES modules only; vanilla JS except D3.
- **`@private`** on all non-public methods — this is the JS equivalent of PHP `private`.
- **`@return`** on all getters and methods that return a value.
- **No single-line docblocks** — always use multi-line `/**\n * text\n */` format.
- Use `@return` (not `@returns`) for consistency.
- Docblock order: description → `@param` → `@return` → `@private`.
- Parenthesize arithmetic in comparisons: `if (si < (group.length - 1))`.
- Parenthesize sub-conditions in compound booleans: `if ((a > 0) && (b < 1))`.
- No single-letter variable names except loop iterators.
- `for...of` on arrays (not `for...in`).
- `Object.prototype.hasOwnProperty.call()` instead of direct `.hasOwnProperty()` for prototype-pollution safety.
- Always include `.js` extension on relative ES module imports (Biome `useImportExtensions`).

## Security
- Do not commit secrets or PII; rely on secret managers and keep .build outputs out of version control.

## PR/commit checklist
- `composer ci:test` must pass before every commit.
- Commit subjects — and the pull-request title — are governed by the shared `commit-convention` gate; the normative rule and its full rationale live in `magicsunday/.github/.github/workflows/commit-convention.yml@main`, which self-tests a decision table before applying it. In short: a `GH-`-prefixed subject must match `^GH-\d+: [A-Z]`, every other subject `^[A-Z]` — a capitalised English imperative — and conventional-commit prefixes (`feat:`, `Fix:`, …) as well as path-like starts (`src/…: …`) are rejected whatever their case. It runs on every pull request via `.github/workflows/commit-lint.yml`, advisory until `commit-convention / Commit convention` is a required context in branch protection.
- Branches for an issue are named exactly `GH-<N>`; the `GH-<N>: ` prefix marks work that belongs to that issue, so a drive-by fix on the branch keeps its own unprefixed subject.
- The pull-request body closes the issue with `Closes #<N>` — the `GH-<N>: ` subject prefix is not a GitHub link and closes nothing.
- Never add a `Co-Authored-By:` trailer or any other AI attribution.
- Keep PRs small and focused (~≤300 net LOC) with atomic commits.
- Ensure coverage ≥90% on touched PHP paths.
- After PR receives review comments: assess, fix, commit, reply with commit hash, resolve threads via GraphQL.
- Never comment on GitHub issues/PRs without explicit user approval.

## When stuck
- Check composer scripts (`composer run-script --list`) and the README for expected workflows.
- The fan-chart module follows the same architectural patterns and is the canonical reference for tooling/CI/test setup.

## House Rules
- Maintain strict typing and PHPStan level max alignment.
- Prefer interfaces where sensible; mark data-only classes as `readonly`.
- Avoid external JavaScript libraries beyond D3.
- Always use Playwright to verify JS changes in the browser — don't just trust the tests.
- Use `jq` (not `sed`) for **JSON file** manipulation in build scripts — Alpine `sed` does not support the GNU regex extensions needed for nested JSON edits. `sed` is fine for non-JSON files (PHP, YAML); see the `sed_edit` and `jq_edit` macros in `Make/release.mk`.
