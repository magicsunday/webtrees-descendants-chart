<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\PedigreeChartModule;
use Fisharebest\Webtrees\Validator;
use MagicSunday\Webtrees\ModuleBase\Model\NameAbbreviation;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Configuration class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
class Configuration
{
    /**
     * Tree layout variants.
     *
     * @see PedigreeChartModule
     */
    public const LAYOUT_TOPBOTTOM = PedigreeChartModule::STYLE_DOWN;

    public const LAYOUT_BOTTOMTOP = PedigreeChartModule::STYLE_UP;

    public const LAYOUT_LEFTRIGHT = PedigreeChartModule::STYLE_RIGHT;

    public const LAYOUT_RIGHTLEFT = PedigreeChartModule::STYLE_LEFT;

    /**
     * The default number of generations to display.
     *
     * @var int
     */
    public const DEFAULT_GENERATIONS = 4;

    /**
     * Minimum number of displayable generations.
     *
     * @var int
     */
    public const MIN_GENERATIONS = 2;

    /**
     * Maximum number of displayable generations.
     *
     * @var int
     */
    public const MAX_GENERATIONS = 25;

    /**
     * Tree layout.
     *
     * @var string
     */
    public const DEFAULT_TREE_LAYOUT = self::LAYOUT_LEFTRIGHT;

    /**
     * Married-names display mode: only the birth name is shown.
     */
    public const string MARRIED_NAMES_OFF = 'off';

    /**
     * Married-names display mode: the married name replaces the birth name.
     */
    public const string MARRIED_NAMES_ONLY = 'married_only';

    /**
     * Married-names display mode: birth name with married surname appended in
     * brackets, e.g. "Schmidt (Müller)".
     */
    public const string MARRIED_NAMES_BIRTH_AND_MARRIED = 'birth_and_married';

    /**
     * @var list<string>
     */
    private const array MARRIED_NAMES_MODES = [
        self::MARRIED_NAMES_OFF,
        self::MARRIED_NAMES_ONLY,
        self::MARRIED_NAMES_BIRTH_AND_MARRIED,
    ];

    // The seven getters below are each called once per node while the tree is
    // built. AbstractModule::getPreference() runs a `module_setting` query on
    // every call and holds no cache, and Validator::__construct() re-scans all
    // request parameters for valid UTF-8, so both costs would scale with the
    // size of the chart. Each getter therefore returns its memoised value
    // before doing either — an assignment at the return site would be too
    // late, because the validator is built above it.

    /**
     * The resolved number of generations to display, or null until first resolved.
     */
    private ?int $generations = null;

    /**
     * The resolved tree layout, or null until first resolved.
     */
    private ?string $layout = null;

    /**
     * Whether spouses are hidden, or null until first resolved.
     */
    private ?bool $hideSpouses = null;

    /**
     * The resolved married-names display mode, or null until first resolved.
     */
    private ?string $marriedNamesMode = null;

    /**
     * Whether nicknames are shown, or null until first resolved.
     */
    private ?bool $showNicknames = null;

    /**
     * Whether a click opens a new tab, or null until first resolved.
     */
    private ?bool $openNewTabOnClick = null;

    /**
     * Whether the alternative name is shown, or null until first resolved.
     */
    private ?bool $showAlternativeName = null;

    /**
     * Configuration constructor.
     *
     * @param ServerRequestInterface $request
     * @param AbstractModule         $module
     */
    public function __construct(
        /**
         * The current request instance.
         */
        private readonly ServerRequestInterface $request,
        private readonly AbstractModule $module,
    ) {
    }

    /**
     * Returns the number of generations to display.
     *
     * @return int
     */
    public function getGenerations(): int
    {
        if ($this->generations !== null) {
            return $this->generations;
        }

        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $this->generations = $validator
            ->isBetween(self::MIN_GENERATIONS, self::MAX_GENERATIONS)
            ->integer(
                'generations',
                (int) $this->module->getPreference(
                    'default_generations',
                    (string) self::DEFAULT_GENERATIONS
                )
            );
    }

    /**
     * Returns a list of possible selectable generations.
     *
     * @return string[]
     */
    public function getGenerationsList(): array
    {
        $result = [];

        foreach (range(self::MIN_GENERATIONS, self::MAX_GENERATIONS) as $value) {
            $result[$value] = I18N::number($value);
        }

        return $result;
    }

    /**
     * Returns the tree layout.
     *
     * @return string
     */
    public function getLayout(): string
    {
        if ($this->layout !== null) {
            return $this->layout;
        }

        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $this->layout = $validator
            ->isInArray([
                self::LAYOUT_BOTTOMTOP,
                self::LAYOUT_LEFTRIGHT,
                self::LAYOUT_RIGHTLEFT,
                self::LAYOUT_TOPBOTTOM,
            ])
            ->string(
                'layout',
                $this->module->getPreference(
                    'default_layout',
                    self::DEFAULT_TREE_LAYOUT
                )
            );
    }

    /**
     * Returns the available tree layouts.
     *
     * @return string[]
     */
    public function getLayouts(): array
    {
        if (I18N::direction() === 'rtl') {
            return [
                self::LAYOUT_LEFTRIGHT => view('icons/pedigree-left') . I18N::translate('left'),
                self::LAYOUT_RIGHTLEFT => view('icons/pedigree-right') . I18N::translate('right'),
                self::LAYOUT_BOTTOMTOP => view('icons/pedigree-up') . I18N::translate('up'),
                self::LAYOUT_TOPBOTTOM => view('icons/pedigree-down') . I18N::translate('down'),
            ];
        }

        return [
            self::LAYOUT_RIGHTLEFT => view('icons/pedigree-left') . I18N::translate('left'),
            self::LAYOUT_LEFTRIGHT => view('icons/pedigree-right') . I18N::translate('right'),
            self::LAYOUT_BOTTOMTOP => view('icons/pedigree-up') . I18N::translate('up'),
            self::LAYOUT_TOPBOTTOM => view('icons/pedigree-down') . I18N::translate('down'),
        ];
    }

    /**
     * Returns whether to hide spouses or not.
     *
     * @return bool
     */
    public function getHideSpouses(): bool
    {
        if ($this->hideSpouses !== null) {
            return $this->hideSpouses;
        }

        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $this->hideSpouses = $validator
            ->boolean(
                'hideSpouses',
                (bool) $this->module->getPreference(
                    'default_hideSpouses',
                    '0'
                )
            );
    }

    /**
     * Returns the married-names display mode (one of the MARRIED_NAMES_*
     * constants).
     *
     * Reads the `marriedNamesMode` request parameter when present; otherwise
     * falls back to the `default_marriedNamesMode` module preference; otherwise
     * migrates from the legacy `default_showMarriedNames` boolean preference
     * (true → `married_only`, false → `off`). Returns `off` when the value is
     * unrecognised.
     *
     * @return string
     */
    public function getMarriedNamesMode(): string
    {
        // This getter resolves TWO preferences, the legacy one included.
        if ($this->marriedNamesMode !== null) {
            return $this->marriedNamesMode;
        }

        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        $legacyDefault = ((bool) $this->module->getPreference('default_showMarriedNames', '0'))
            ? self::MARRIED_NAMES_ONLY
            : self::MARRIED_NAMES_OFF;

        $default = $this->module->getPreference('default_marriedNamesMode', $legacyDefault);

        $mode = $validator->string('marriedNamesMode', $default);

        return $this->marriedNamesMode = in_array($mode, self::MARRIED_NAMES_MODES, true)
            ? $mode
            : self::MARRIED_NAMES_OFF;
    }

    /**
     * Returns the list of married-names display modes keyed by storage value,
     * with translated labels — for use in the configuration form.
     *
     * @return array<string, string>
     */
    public function getMarriedNamesModes(): array
    {
        return [
            self::MARRIED_NAMES_OFF               => I18N::translate('Birth name only'),
            self::MARRIED_NAMES_ONLY              => I18N::translate('Married name only'),
            self::MARRIED_NAMES_BIRTH_AND_MARRIED => I18N::translate('Birth name with married name in brackets'),
        ];
    }

    /**
     * Returns whether to open a new browser window/tab on left-click on an
     * individual or not.
     *
     * @return bool
     */
    public function getOpenNewTabOnClick(): bool
    {
        if ($this->openNewTabOnClick !== null) {
            return $this->openNewTabOnClick;
        }

        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $this->openNewTabOnClick = $validator
            ->boolean(
                'openNewTabOnClick',
                (bool) $this->module->getPreference(
                    'default_openNewTabOnClick',
                    '1'
                )
            );
    }

    /**
     * Returns true when the legacy GEDCOM `2 NICK` value should be displayed in
     * quotes between the given names and the surname (e.g. `Martin "Chalky"
     * White`). Default off so existing trees keep the post-2.0 webtrees
     * rendering.
     *
     * @return bool
     */
    public function getShowNicknames(): bool
    {
        if ($this->showNicknames !== null) {
            return $this->showNicknames;
        }

        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $this->showNicknames = $validator
            ->boolean(
                'showNicknames',
                (bool) $this->module->getPreference(
                    'default_showNicknames',
                    '0'
                )
            );
    }

    /**
     * Returns whether to show the alternative name of an individual or not.
     *
     * @return bool
     */
    public function getShowAlternativeName(): bool
    {
        if ($this->showAlternativeName !== null) {
            return $this->showAlternativeName;
        }

        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $this->showAlternativeName = $validator
            ->boolean(
                'showAlternativeName',
                (bool) $this->module->getPreference(
                    'default_showAlternativeName',
                    '0'
                )
            );
    }

    /**
     * Returns whether to hide the SVG export button or not.
     *
     * @return bool
     */
    public function getHideSvgExport(): bool
    {
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $validator
            ->boolean(
                'hideSvgExport',
                (bool) $this->module->getPreference(
                    'default_hideSvgExport',
                    '0'
                )
            );
    }

    /**
     * Returns whether to hide the PNG export button or not.
     *
     * @return bool
     */
    public function getHidePngExport(): bool
    {
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $validator
            ->boolean(
                'hidePngExport',
                (bool) $this->module->getPreference(
                    'default_hidePngExport',
                    '0'
                )
            );
    }

    /**
     * Returns the dropdown options for the name-abbreviation strategy in the
     * admin config form. Keyed by the persisted enum value.
     *
     * @return array<string, string>
     */
    public function getNameAbbreviationList(): array
    {
        return [
            NameAbbreviation::AUTO    => I18N::translate("Automatic (based on tree's surname tradition)"),
            NameAbbreviation::GIVEN   => I18N::translate('Abbreviate given names first'),
            NameAbbreviation::SURNAME => I18N::translate('Abbreviate surnames first'),
        ];
    }

    /**
     * Returns the name-abbreviation strategy as stored. One of {@see
     * NameAbbreviation::AUTO}, GIVEN or SURNAME. The chart-render path resolves
     * AUTO to GIVEN/SURNAME via the tree's SURNAME_TRADITION before serialising
     * to the JS config — see {@see Module::getChartParameters()}.
     *
     * @return string
     */
    public function getNameAbbreviation(): string
    {
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        $value = $validator
            ->string(
                'nameAbbreviation',
                $this->module->getPreference(
                    'default_nameAbbreviation',
                    NameAbbreviation::AUTO
                )
            );

        return in_array($value, NameAbbreviation::CHOICES, true)
            ? $value
            : NameAbbreviation::AUTO;
    }

    /**
     * Returns the settings that have to travel with the re-centering URL.
     *
     * Clicking a person navigates to the page route afresh, so every setting the
     * re-centered page has to restore must travel in the URL — otherwise that
     * setting silently falls back to the module preference default. The list is
     * therefore the user-settable form fields, not just what the data facade
     * reads: `openNewTabOnClick` and `showAlternativeName` are resolved by the
     * view rather than the facade and still have to be forwarded.
     *
     * `nameAbbreviation` is deliberately absent — it is an admin preference with
     * no form field, so it resolves identically on the re-centered page.
     *
     * Cheap to call per node even though it resolves seven settings: each getter
     * memoises its value, and AbstractModule::getPreference() would otherwise
     * issue a database query on every call, putting the query count in linear
     * proportion to the tree size. The configuration is constructed per request,
     * so the resolved values cannot go stale within its lifetime.
     *
     * @return array{generations: int, layout: string, hideSpouses: string, marriedNamesMode: string, showNicknames: string, openNewTabOnClick: string, showAlternativeName: string}
     */
    public function getRouteToggleParams(): array
    {
        return [
            'generations'         => $this->getGenerations(),
            'layout'              => $this->getLayout(),
            'hideSpouses'         => $this->getHideSpouses() ? '1' : '0',
            'marriedNamesMode'    => $this->getMarriedNamesMode(),
            'showNicknames'       => $this->getShowNicknames() ? '1' : '0',
            'openNewTabOnClick'   => $this->getOpenNewTabOnClick() ? '1' : '0',
            'showAlternativeName' => $this->getShowAlternativeName() ? '1' : '0',
        ];
    }
}
