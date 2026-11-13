## Overview
This repository hosts the webtrees descendants chart module — an interactive SVG descendants (rectangular tree) chart of an individual's descendants using D3.js, installed as a Composer package inside webtrees.

## Setup/env
- PHP 8.3 up to but not including 9 with extensions dom and json is required. The constraint is `>=8.3 <9` and the CI matrix states what is tested. Composer installs dependencies into .build/vendor and binaries into .build/bin.
- Node.js tooling is used for asset builds (rollup). Install dev dependencies via `npm install` when touching frontend resources.
- All PHP/Node tooling runs inside the webtrees Docker buildbox — never directly on the NAS or in phpfpm:
  ```
  cd /volume2/docker/webtrees && make bash
  cd app/vendor/magicsunday/webtrees-descendants-chart
  ```
- JS bundles are built via the node Docker container from the module directory: `make install`, `make build`.
- `compose.yaml` bind-mounts the sibling clone `../webtrees-chart-lib` into the `node` service for every `docker compose run`, because the chart-lib dev symlink of `make link-chart-lib` has to resolve inside the container. Compose has no way to skip a bind mount whose source is missing (`create_host_path: false` makes the run fail instead), so Docker creates the missing `../webtrees-chart-lib` as a root-owned empty directory next to the checkout. It is harmless and unused as long as the dev symlink is not active. Remove it with `rmdir`, which the owner of the checkout may do because the parent directory is theirs, before cloning the real chart-lib there, because `make link-chart-lib` refuses a directory without `package.json`.
- After PHP or JS changes visible in the browser: `docker restart webtrees-phpfpm-1`.
- After JS changes, always verify in the browser via Playwright before claiming success.

### Convention for new advisory ignores

Before adding an entry, check the following. Is the package a direct dependency (`composer why`)? Does a public fix exist? Does an exact pin of webtrees alone block that fix (`composer why-not <package> <fixed version>`)? Update instead of ignoring whenever the fix is reachable.

- An advisory that webtrees' exact pin keeps unresolved is ignored by its **advisory ID** under `config.policy.advisories.ignore-id`, so later advisories on the same package keep blocking. Never ignore by package name under `ignore`, because that also hides every later advisory of the package.
- The reason starts with a prefix that says why the entry exists, followed by the CVE and the condition for removing it. `UPSTREAM-PINNED:` marks a fix that exists publicly but is held back by the exact pin of webtrees. `ACCEPTED:` is reserved for a conscious risk acceptance with a low severity and no attack path in this package.
- Remove the entry once the supported webtrees range no longer pins an affected version. `composer ci:test:php:advisories` runs `composer audit --format=json` on the installed packages and fails with the ids of every `ignore-id` entry that is missing from the ignored advisories of the report. An entry has to be the advisory id (PKSA), because the script compares the ignored ids (the keys of `ignore-id`, or the values of a plain list) with the `advisoryId` values of the report, so an entry keyed by a CVE or a remote id is reported as stale. The exit status of the audit itself is not the result of this check, since advisories that are not ignored are a separate matter, and a crashed audit leaves no valid report, which the script rejects. It is part of `composer ci:test` and runs in CI as a step of the `js` job of `.github/workflows/ci.yml`, because the shared `php-quality.yml` workflow does not know it. The check reads the installed packages, so run `composer update` first when the local `.build/vendor` is older than `composer.json`. The script is `scripts/check-stale-advisory-ignores.php` and its test is `tests/Release/StaleAdvisoryIgnoresTest.php`.

## Build & tests
- **`composer ci:test` MUST run before every commit** — catches Biome, PHPStan, PHP-CS-Fixer, Rector, PHPUnit, Jest, and CPD issues before they reach GitHub CI.
- Individual checks: `composer ci:test:php:phpstan`, `composer ci:test:php:unit`, `composer ci:test:php:cgl`, `composer ci:test:js:lint`, `composer ci:test:js:unit`, `composer ci:test:php:advisories` (stale advisory ignores).
- `composer ci:test:php:cpd` runs the installed `node_modules/.bin/jscpd`, so the Node dependencies must be installed (`make install` or `npm ci`). jscpd is pinned to an exact version in `package.json`. CI runs it as its own job through the shared `cpd.yml` workflow of the `.github` repository, reported as `cpd / Copy-paste detection`.
- `composer ci:test:php:infection` runs mutation testing against `src/` over the whole tree and only reports. It is not part of `composer ci:test`, because a mutation run is too slow for every local check. CI runs it as the mutation step of the shared `php-quality.yml` workflow, on one PHP leg of its matrix. A fixed score floor over the whole tree rewards tests that only kill a mutant in a getter, a literal, a default or a call order, so `infection.json5` has none. `timeoutsAsEscaped` stays on, since Infection counts a timed-out mutant as killed by default and a mutation that made the suite hang is not one the tests caught. There is deliberately no `maxTimeouts` limit, because the timeout count varies with machine load.
- A pull request is gated on the covered code of the lines it changes, with `composer ci:test:php:infection:changed`. The shared `php-quality.yml` workflow runs that script on a pull request, with the base branch as `--git-diff-base`, so the checkout of that leg fetches the full history, and the script has no default for it. A pull request whose changed lines carry no mutants passes. The floor sits in the command of the script, and a regression is fixed in the tests and never admitted by lowering it. The script switches off the rename detection of Git for its run, so that a file which a pull request renames counts as an added file and is gated in full, a pure move included, where a detected rename would be skipped unchecked. Infection mutates only code that a test executes, so new code that no test reaches produces no mutants and this gate does not catch it. That stays a matter of the coverage of the tests.
- `composer ci:test` runs the unit tests with `composer ci:test:php:unit:coverage` and not with the plain `composer ci:test:php:unit`. The CI leg that also runs the mutation tests loads a coverage driver for its unit step, and under a coverage driver PHPUnit reports a test as risky when it executes code that it does not declare as covered or used, which `failOnRisky` turns into a failure. A run without a driver does not show that, so the coverage variant is what a local run needs to predict that leg. It takes longer than the plain run and writes an HTML report to `.build/coverage/`.
- Single PHPUnit test: `composer ci:test:php:unit -- --filter TestClassName`.
- Auto-fix: `composer ci:cgl` (PHP style), `composer ci:rector` (Rector), `npm run lint:fix` (Biome).
- JS bundles: `make build` (rollup), `make watch` (dev rebuild loop).
- Translations: `make lang` runs the i18n pipeline and compiles every `.po` to its `.mo`, and the merge and compile steps stop at the first locale that fails. A text of this module is translated in the locales that ship a translation for it. A text that webtrees already translates stays empty, see the catalogue rules below.
- **Catalogue rules.** webtrees merges the compiled catalogue of a module over its own, so an entry here replaces the webtrees translation of the same text in the whole installation, not only on this module's pages.
    - Leave the `msgstr` empty for every text that webtrees core translates in that locale, except for a text of the next point. A compiled entry replaces the core text in the whole installation and goes stale when core rewords it. This holds for the chart title (`Descendants chart`) and the button and form labels as well. Where core translates them, the core wording is used. The `core-overlap` job of the shared `i18n.yml` workflow compares every catalogue with the `.po` files of the webtrees releases the module supports and fails a pull request that fills such a text, the annotation names the file and line. A text that core does not translate in a locale may be filled. Fuzzy entries and plural entries with an empty first form are not compiled and do not count.
    - A text whose wording the module deliberately words differently from the core carries the name of the module as its context, and the call site passes the translation of that context through `ContextText::resolve()`, for example `ContextText::resolve(I18N::translateContext('webtrees-descendants-chart', 'view'), 'view')`. An entry with a context is a key of its own, so it does not replace the core text in the rest of the installation, and the `core-overlap` job leaves it alone. The source text stays a literal at the call site so that `make lang` extracts it. Only a language whose wording differs on purpose carries an entry: German with the wording of the module, and a language that translated the text before the context existed keeps that translation in the entry. Every other entry stays empty. A language without an entry keeps the wording of the core, because `resolve()` falls back to the plain translation.
    - Source texts are American English like in webtrees core. There is no `en-US` catalogue, the source text is the `en-US` text. `en-GB` holds the British variants of changed texts and no copy of the source text. When a source text changes its spelling, change the `msgid` in every catalogue in the same commit so that no entry turns fuzzy.
    - A plural entry is either left empty entirely or has every form filled in, and the number of forms (the `msgstr[N]` slots of an empty entry included) is the number the webtrees plural rule of the locale selects from (`Locale::create($tag)->pluralRule()->plurals()` in `fisharebest/localization`). webtrees ignores the `Plural-Forms` header of the PO, so the header only has to agree with that rule. `tests/CatalogueStructureTest.php` extends the shared catalogue structure case of `webtrees-module-base` and names the catalogue directory. The shared case enforces the form count, the empty forms and the placeholders of the compiled catalogue, the slot count of the source catalogue and the form count in its header, and renders every plural entry for a range of numbers.
- **Every class under `src/` is final**, and a phpat rule in `tests/Architecture/ArchitectureTest.php` enforces it (the rule does not cover traits). A class that a test needs to stub is not made final. It is excluded from the rule with a reason.
- **PHPStan runs the shared strict tier** (`phpstan.neon` includes `phpstan/strict.neon` of `magicsunday/coding-standard`, which needs `shipmonk/phpstan-rules`, `symplify/phpstan-rules` and `spaze/phpstan-disallowed-calls` as devDependencies). Fix a finding in code. An irreducible idiom conflict gets an `ignoreErrors` entry scoped by identifier and path with a comment, never a baseline.
- Keep PHPStan, PHPCS, and CPD clean on affected code. Add the PHPUnit tests that the testing policy below calls for, each with its attribute-based coverage target.
- `install`, `build`, `watch`, `lint-fix`, `unlink-chart-lib` and the lang targets run as the numeric user of the caller in the webtrees-docker tooling image, so they leave no root-owned files. Files that an earlier run as root left in `node_modules` are removed with `make clean`, or via: `docker compose run --rm buildbox-root bash -c "rm -rf app/vendor/magicsunday/webtrees-descendants-chart/node_modules"`. Root-owned files elsewhere in the checkout are given back once, from the checkout on the Docker host, with `docker run --rm -v "$PWD":/app -w /app alpine find . -xdev -user 0 -exec chown -h "$(id -u):$(id -g)" {} +`.
- **Shared JS/TS configs**: `biome.json` extends `@magicsunday/coding-standard/biome/base.json` and `tsconfig.json` extends `@magicsunday/coding-standard/tsconfig/base` (npm devDependency `github:magicsunday/coding-standard#<tag>`). Keep only repository-specific settings locally (`files.includes`, module/target/lib/paths); never switch a shared rule off or a pinned strict flag (`strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `noImplicitOverride`, `forceConsistentCasingInFileNames`, `isolatedModules`) back to false — `composer ci:test:php:templates` rejects it. `@biomejs/biome` and `typescript` stay own devDependencies within the shared package's peer ranges.
- **Typecheck scope is intentionally limited to production sources** (`tsconfig.json` includes only `resources/js/modules/**/*.js`). Tests under `resources/js/tests/**` are validated by jest at runtime but skipped by `tsc --noEmit` because the partial mock objects required to isolate units would otherwise produce dozens of structural-incompatibility errors against the real `Configuration` / `Selection` shapes. The strict-typecheck gate applies to shipped code; test scaffolding stays loose.

<!-- testing-policy:begin v1 -->
## Testing policy

Tests protect behavior whose regression would be materially incorrect, unsafe,
destructive or hard to notice before release. They are not a coverage target and
they are not a mutation-score target.

Protect these with durable tests:
- security and privacy boundaries, including untrusted data reaching a path,
  archive member, URL, header, query, command or DOM sink
- confidentiality outside access checks (secrets or personal data in logs,
  exceptions, temporary files, caches, exports, URLs)
- data integrity across import, export, update and migration
- lifecycle and failure semantics: atomic writes, rollback, cleanup, idempotency,
  retries, partial failure, and ordering where the order is the protocol
- calculation and decision rules, including their boundaries
- compatibility boundaries: public API, serialized formats, extension interfaces,
  persisted configuration keys, producer and consumer contracts
- every fixed behavioral bug (some test must fail on the old behavior). A bug
  that is only a wrong label, CSS class or default is not behavioral unless a
  named consumer breaks on it.

For side effects assert the postcondition and the identity of the affected
resource, not only that a collaborator was called.

Do not add tests whose only content is:
- a getter, setter, constructor that only stores values, DTO, enum or constant,
  unless it carries a persisted key or a serialized format from the list above
- a CSS class list, a label or message text, or a default value, unless the exact
  value is a documented interface that a named consumer breaks on. Prefer testing
  the consuming behavior over the producer literal and pin only the minimum literal.
- transparent delegation that adds no argument selection, ordering, error
  translation or security context
- the text of source files instead of the behavior they produce

Order and default assertions are fine when they encode a safety property, for
example authorize before the side effect, write before rename, private unless
made public.

A test that a reviewer, human or agent, questions must show the observable
behavior or invariant it protects, a plausible faulty change it rejects, and for
a pinned literal the consumer that would break. Mutating the code or reverting
the fix and watching the test fail is the way to show it.
<!-- testing-policy:end -->

Where the testing policy and the changed-lines mutation gate point to different answers, the policy wins. A surviving mutant that only a test of a kind the policy excludes could kill, such as a getter, a literal or a default, is answered with an `ignore` entry of its mutator in `infection.json5` with a comment that names the reason. It is never answered with a test that pins the value.

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
- **Media preload**: before `DataFacade` walks the descendants, `preloadMedia()` finds the people of each generation the way the walk finds them and hands them to `Support/MediaPreloader` of the module base one generation at a time, so that the highlight image of each person finds its media object in the record cache. A generation holds its people, the partners of their families unless `hideSpouses` is on, and the children that the descendant line keeps (`DescendantLine::keepsLink()`). The preloader reads the media objects in chunks of `MediaPreloader::CHUNK_SIZE` and stores them under the key that `MediaFactory` reads. It does nothing while the tree setting `SHOW_HIGHLIGHT_IMAGES` is empty. It skips a person without a `1 OBJE @xref@` line before it asks whether the viewer may see the person, because that check can read the families of the parents. Then it skips a person the viewer may not see, a media object that it has already requested in the request, and a media object with a pending change, which is left to the factory. For a visitor or a member who is no editor, in a tree that hides living people, the core hides a media object when any record it is linked to is hidden, and it reads the links of every media object for that. The preload reads the links of a chunk of media objects at once, loads the people and families they point to into the record cache, applies the same rule and stores the result under the visibility key of the core, so the walk sends no statement for it. The core keeps the decision for a media object with a restriction notice of its own, one with a rule of its own in the default restrictions of the tree, one whose xref is that of the account of the viewer, every manager and every editor, and a tree that does not hide living people. The links of a media object are grouped under the lower-case xref, because MySQL and MariaDB compare the link and media columns case-insensitively under their usual collations, and on a case-sensitive database the grouping can only hide more and never show more. A webtrees release that changes the cache key of the media factory or of the visibility check leaves the preload without effect. The walk still reads the people and families one by one, and the records of other types that a media object is linked to, such as notes or sources, are read by the core record by record. `tests/Facade/DataFacadeQueryCountTest.php` counts the media statements of a request over a real imported descendant tree with a media object per person, for the administrator and a visitor, pins the exact set of media objects that a request binds for the shown generations, with and without the partners and for each descendant line, and compares the images of the chart with those that the core record factories give without the preload, also for a member who is no editor, over media objects that are linked to a confidential person.
- **Enum/DescendantLine** — the `descendantLine` page option (`all`, `paternal`, `maternal`). `keepsLink($parentSex, $childSex)` is the single decision whether the line continues along a parent to child link, by the recorded sex of both. `paternal` keeps a link only when parent and child are male, `maternal` keeps it when the parent is female whatever the child, and a parent of unknown or other sex keeps no link. Under `paternal` a child of unknown or other sex is dropped too, under `maternal` such a child is kept as the end of the line. `DataFacade::buildCoupleStructure()` applies it where the children of a family are collected, so the merged children of the `hideSpouses` mode are filtered as well. The subject and its partner families always stay, the filter touches only the descendant links. `Configuration::getDescendantLine()` falls back to the `default_descendantLine` module preference and then to `all`, and the option travels in `getRouteToggleParams()`. Pedigree and fan charts show ancestors and have no counterpart.
- **Model/CoupleNode** — Tree node with JSON serialization for D3. Its members are the `NodeData` objects of module-base.
- **Shared classes from [`magicsunday/webtrees-module-base`](https://github.com/magicsunday/webtrees-module-base)** (composer dependency, the constraint is in `composer.json`):
  - `Processor/DateProcessor` — date extraction. Descendants uses the locale-aware methods (`getBirthDate`, `getDeathDate`, `getLifetimeDescription`). `getLifetimeDescription` takes a `LifetimeLabels` object that `DataFacade` builds from `I18N::translate()` literals, so the catalogue of this module holds the texts of the lifetime label and `DateProcessor` translates nothing. The deceased text goes through `I18N::translateContext()` with the context `webtrees-descendants-chart`, the name of the module, because the plain source text is also a statistic label of another module and webtrees merges catalogues by source text.
  - `Processor/FactResolver`: the fact slots of a person (birth, death, the marriage and the divorce of the families of the person with the name of the spouse in `partner`, which the resolver adds by itself because the administration offers only facts of a person, and the optional tags of the tree preference `CHART_BOX_TAGS`). `DataFacade` builds it privately with the full place name and puts the slots into the person payload of every member, the partners included, only while `Configuration::getShowDetailPanel()` is on, so a chart without the detail panel pays neither their payload nor their database work.
  - `Support/MediaPreloader`, `Support/PendingRecords` and `Testing/RealTreeFixture`: the media preload and the real-tree test fixture. The module base owns them, so a fix to the preload lands there and this module takes it with the next release of the module base.
  - `Processor/NameProcessor`, `Processor/ImageProcessor` — name/image extraction. NameProcessor's 3-arg constructor `($individual, $spouse, $useMarriedName)` and its `getNameSegments()` are used here.
  - `Model/NodeData` — the serialisable person record that the chart script reads. It carries the name as `nameSegments`, a list of `NameSegment` objects in display order.
  - `Model/NameSegment`, `Model/NameSegmentKind` — one drawable part of a name and its role (`given`, `surname`, `nickname`, `married`).
  - `Module/VersionInformation` — GitHub release-checking with file cache.
  - For local edits to module-base while developing descendants-chart, run `make link-base` (symlinks `.build/vendor/.../webtrees-module-base` → the sibling clone). Reverse with `make unlink-base` or any `composer install/update`.
  - For local edits to chart-lib, run `make link-chart-lib` (symlinks `node_modules/@magicsunday/webtrees-chart-lib` → the sibling clone, with a relative target so it also resolves inside the node container). Reverse with `make unlink-chart-lib` or any `npm install`.

### JS (`resources/js/modules/`)
Flat layout — every file is descendants-specific glue. Reusable base classes (Storage, ChartExport, ChartOverlay, ChartZoom, Orientations, measureText, truncateNames) and the render host of the linked tree charts (`LinkedTreeSvg`, `OrientationCollection`, `ImageBox`, `TextBox`, `TextRenderer`, `DateRenderer`, the `LAYOUT_*` constants, imported from the `linked-tree` subpath) live in the external [`@magicsunday/webtrees-chart-lib`](https://github.com/magicsunday/webtrees-chart-lib) package, shared with the fan- and pedigree-chart modules. Consumed via Git URL pinned in `package.json` (`github:magicsunday/webtrees-chart-lib#X.Y.Z`); chart-lib's `prepare` script builds its `dist/` during install, so `npm ci --ignore-scripts` will break the build.
- **`index.js`** — Exports `DescendantsChart` class (ES module entry point for Rollup).
- **`page-entry.js` / `page-init.js`** — UMD bundle (`descendants-chart-page.min.js`) loaded by `page.phtml`. Owns localStorage form-state wiring; `initPage()` resolves user options and publishes them under `WebtreesDescendantsChart.chartOptions` for `chart.phtml` getters.
- **Toolbar style**: the rules of the `.btn-chart` toolbar buttons come from the view `toolbar-style` of `webtrees-module-base`, which `page.phtml` renders once with the container class `webtrees-descendants-fullscreen-container`. The stylesheet of the module keeps no copy of them, only the rules of this chart.
- **Help popover**: `initHelpPopover()` and `syncHelpMode()` come from `@magicsunday/webtrees-chart-lib/infra`, which holds their behaviour and their tests. `initHelpPopover()` toggles the popover of the help button, mirrors the state in `aria-expanded` on the button and closes an open popover on Escape. The markup comes from the view `help-popover` of `webtrees-module-base`, which `resources/views/modules/components/buttonbar/help-button.phtml` renders with the translated rows of this module. The rows marked with a `mode` describe the default mode or the detail panel mode of the chart, and the rows without a mode apply to both. `syncHelpMode("default")` shows the rows of the mode that the checkbox `showDetailPanel` has at that moment, and `initPage()` calls it where it sets the AJAX URL of the chart, because the chart takes its mode from that load and not from a tick that is not submitted yet.
- **Detail panel**: `DetailPanel` and `PanelSelection` come from `@magicsunday/webtrees-chart-lib/chart-core`, which holds their behaviour and their tests. The markup comes from the view `detail-panel` of `webtrees-module-base`, which `page.phtml` renders with the translated texts of this module (the `aside` is hidden unless the switch `showDetailPanel` is on, and its parts are marked by `data-panel`), and the view carries its own styles. `Module::boot()` registers the views of the module base with `SharedViews::register()`. This module provides the wiring. `index.js` creates the panel when the switch is on and hands it the callbacks `onIndividual`, `onCenter`, `onClose` and `onHidden`. `chart.js` makes a click or Enter select the person instead of navigating and passes `clearSelection` and `restoreFocus` on to the selection. The person boxes are focusable and operable with Enter or Space in both modes, and a key press does what a click does. The facts and the image come with the person payload, the panel orders the facts by `sortDay` and shows the `thumbnail` of the person as the box does. When the panel closes by its button or Escape, the focus goes back to the person first. In every case the selected mark is cleared.
- **`chart.js`** (D3 hierarchy.tree layout, click handling and selection), **`hierarchy.js`** (D3 hierarchy), **`tree.js`** (d3.tree layout, separation hook and the single draw of links and boxes), **`configuration.js`**.
- **`family-tree.js`** (CoupleNode → FamilyNode tree), **`separation.js`** (`pickGap` half-sibling detection — see issue #84).
- **`tree/`** — `name.js` (draws the name from the ordered `nameSegments`, extends the library `TextRenderer`), `node-drawer.js` (also uses the library `DateRenderer`), `link-drawer.js`, `connection-builder.js`.
- **`constants.js`** (sex tokens and the box gaps of the layout), **`d3.js`** (re-export facade).

### Views (`resources/views/`)
- **`descendants-chart/page.phtml`** — Form + AJAX container. Loads `descendants-chart-page.min.js` and calls `WebtreesDescendantsChart.initPage({ ajaxUrl })`.
- **`descendants-chart/chart.phtml`** — AJAX response: `<script type="module">` with `import()` to load ES module bundle. Reads user overrides from `WebtreesDescendantsChart.chartOptions ?? PHP defaults`.
- **`descendants-chart/form/*.phtml`** — Form partials (generations, orientation, display, details, behavior).

## Key patterns
- **ES module loading**: `import().then(({ DescendantsChart }) => ...)` in `<script type="module">`, avoiding the `webtrees.load()` race condition.
- **Storage flow**: `page-init.js` reads localStorage → publishes resolved options under `WebtreesDescendantsChart.chartOptions` → `chart.phtml` getter reads `opts.x ?? PHP default`.
- **Orientation strategy**: chart layout is configurable in 4 directions (top-bottom, bottom-top, left-right, right-left) via the `OrientationCollection` of the chart-lib `linked-tree` subpath and its matching `Orientation*` classes (node coordinate transforms + elbow connector geometry).
- **Married-name modes**: an admin-only default, set as `default_marriedNamesMode` on the module configuration page. The chart page has no form field for it, so it is neither registered in the page script nor forwarded in the chart URLs. The modes are `off`, `married_only` and `birth_and_married`. `married_only` swaps the primary to a `_MARNM` record matching the spouse's surname (NameProcessor's `useMarriedName` flag). `birth_and_married` keeps the birth-name primary and appends the married surname in brackets, e.g. `Schmidt (Müller)`. The bracketed text is the last name segment, of kind `married`, and it also ends the flat `name`, which the box tooltip reads. Migrates from the legacy boolean `default_showMarriedNames` preference (true → `married_only`, false → `off`).

## Release process
Runs inside the buildbox (requires git, node, npm, composer, jq, zip, gh, sed):
```
make release 2.1.0
make release 2.1.0 NOTES_FILE=/tmp/notes.md
make release VERSION=2.1.0 NOTES="Bug fix release"
```
The Make setup and the release tests are shared with the other modules that ship a ZIP. `Makefile`, `Make/release.mk`, `Make/lang.mk`, `Make/node.mk`, `Make/lint.mk`, `Make/dev.mk`, `Make/helper/` and the files of `tests/Release/` are copied from `templates/webtrees-module/` of the `magicsunday/coding-standard` package by its `bin/sync-webtrees-module.php`. Do not edit them here. Change the template and run the script again. The values that belong to this module are in `Make/module.mk` (`MODULE_NAME`, `MODULE_NS`, `SCOPE_NS`, `WEBTREES_CONSTRAINT`, `CHART_LIB_MARKER` and `SHARED_VIEWS`), and the script never touches that file. The three identity lines of each PHP file under `tests/Release/` (the package name, the link tag and the namespace) are rendered from it by the script. A test of this module that the template does not carry stays local, and the script deletes nothing.

Pipeline (`make release X.Y.Z`):
- `release-check` — tools, semver, clean tree, checked out on `main`, no active `make link-base` or `make link-chart-lib` symlink, gh auth (or `GH_TOKEN`).
- `release-prepare` — `sed_edit` macro updates `CUSTOM_VERSION` in `src/Module.php` with a post-write assertion; `jq_edit` macro (with `--indent 4` and post-write assertion) updates `package.json` version + `composer.json` webtrees pin → clean+rebuild JS bundles via `build-js-fresh` → commit → `dist` → signed annotated tag with the version as its message (only after dist succeeds, so a dist failure does not leave a dangling tag).
- `dist`: symlink guard fires before composer touches anything, `composer update --no-dev --no-interaction` with the advisory blocking of composer on, `git archive HEAD` (respects `.gitattributes` export-ignore), bundles `magicsunday/webtrees-module-base` (`src/`, its `LICENSE` and the views named in `SHARED_VIEWS`) into the zip's `vendor/` so manual ZIP installs work without composer, writes the version of that module base that composer resolved to `vendor/magicsunday/webtrees-module-base/VERSION` and stops when it is not a well-formed version, strips all `composer.json` files (`find vendor -name composer.json -delete`), scopes the PHP namespace of the bundled module-base with `SCOPE_NS` and the name of the view namespace that its `SharedViews` registers with `SCOPE_VIEW_NS` (`SCOPE_NS` is set in `Make/module.mk`, `SCOPE_VIEW_NS` derives from the module name, both unique per module, the build fails when a rewrite does not apply), atomic write via `.tmp` + rename + `zip -T` integrity check.
- `dist-smoke`: separate target, asserts required entries (module.php, LICENSE, the versioned JS bundle, module-base `src/` and its views from `SHARED_VIEWS`, which are `detail-panel.phtml`, `help-popover.phtml` and `toolbar-style.phtml`) are present, that the extracted archive holds each of these views non-empty, that `VERSION` of the module base is a well-formed version, and that forbidden entries (composer.json, assets/, dev/, .githooks/, deptrac.yaml, tsconfig.json) are absent, and that the bundled `SharedViews.php` registers the scoped view namespace and not the unscoped one. `tests/Release/DistSmokeTest.php` runs the target against small archives. CI runs this on every pull request and on every push to the branches that `ci.yml` lists.
- `release-publish` — refuses a missing zip before it pushes, then pushes `main` and only the tag of the release version (other local tags stay local), `gh release create` with the zip + notes, emits `RELEASE_PUBLISHED version=X` marker for agent observers only after `gh` succeeded. A failing `gh` call fails the target and keeps the zip, and the temporary notes file is removed on every exit. The rerun after such a failure removes the draft release that an interrupted upload left behind before it creates the release, and it stops on a release that is already published. `tests/Release/ReleasePublishTest.php` runs the target against stubbed `git` and `gh`. An inline `NOTES` reaches the shell as raw text through `NOTES_RAW`, so once make has received it backticks, quotes and dollar signs arrive unchanged and neither make nor the shell runs anything from it. Quote the note with single quotes in the calling shell, because that shell evaluates a command substitution inside double quotes before make sees it. Make drops leading whitespace of a note given on the command line, so a note that needs it goes through `NOTES_FILE` or the environment.
- `release-bump` — bump to `VERSION+1-dev`, keep the stable webtrees constraint (`WEBTREES_CONSTRAINT` in `Make/module.mk`, shared with `release-prepare`), push. The dev tree tracks stable webtrees, not `dev-main`, because the `dev-main` i18n refactor removed APIs the module still uses and stable is what end users install; re-add `|| dev-main` here (and adapt the code) when webtrees' next stable nears.

Rollback of a bad release: a published tag is never moved or deleted, because the immutable-tags ruleset makes a published tag impossible to remove. A bad release is superseded by the next patch version. Land the fix on `main` first, then run `make release X.Y.Z` with that next version. If the tag was pushed but the GitHub release failed, the tag stays and `make release` is not run again. Check out the tagged commit, run `make dist` and `make dist-smoke` there, then run `make release-publish VERSION=X.Y.Z`, followed by `make release-bump VERSION=X.Y.Z` on `main` and `make release-clean`, as the comments in `Make/release.mk` describe.

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
- Cover the behavior that the testing policy lists with tests. Coverage is a diagnostic here, not a target.
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
