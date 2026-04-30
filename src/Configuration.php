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
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $validator
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
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $validator
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
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $validator
            ->boolean(
                'hideSpouses',
                (bool) $this->module->getPreference(
                    'default_hideSpouses',
                    '0'
                )
            );
    }

    /**
     * Returns the married-names display mode (one of the MARRIED_NAMES_* constants).
     *
     * Reads the `marriedNamesMode` request parameter when present; otherwise falls
     * back to the `default_marriedNamesMode` module preference; otherwise migrates
     * from the legacy `default_showMarriedNames` boolean preference (true →
     * `married_only`, false → `off`). Returns `off` when the value is unrecognised.
     *
     * @return string
     */
    public function getMarriedNamesMode(): string
    {
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

        return in_array($mode, self::MARRIED_NAMES_MODES, true)
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
     * Returns whether to open a new browser window/tab on left-click on an individual or not.
     *
     * @return bool
     */
    public function getOpenNewTabOnClick(): bool
    {
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $validator
            ->boolean(
                'openNewTabOnClick',
                (bool) $this->module->getPreference(
                    'default_openNewTabOnClick',
                    '1'
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
        if ($this->request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($this->request);
        } else {
            $validator = Validator::queryParams($this->request);
        }

        return $validator
            ->boolean(
                'showAlternativeName',
                (bool) $this->module->getPreference(
                    'default_showAlternativeName',
                    '1'
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
     * Returns the dropdown options for the name-abbreviation strategy in
     * the admin config form. Keyed by the persisted enum value.
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
     * Returns the name-abbreviation strategy as stored. One of
     * {@see NameAbbreviation::AUTO}, GIVEN or SURNAME. The chart-render path
     * resolves AUTO to GIVEN/SURNAME via the tree's SURNAME_TRADITION before
     * serialising to the JS config — see {@see Module::getChartParameters()}.
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
}
