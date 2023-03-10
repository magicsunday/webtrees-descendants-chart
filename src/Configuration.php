<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\PedigreeChartModule;
use Fisharebest\Webtrees\Validator;
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
     * @see \Fisharebest\Webtrees\Module\PedigreeChartModule
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
    private const DEFAULT_GENERATIONS = 4;

    /**
     * Minimum number of displayable generations.
     *
     * @var int
     */
    private const MIN_GENERATIONS = 2;

    /**
     * Maximum number of displayable generations.
     *
     * @var int
     */
    private const MAX_GENERATIONS = 25;

    /**
     * Tree layout.
     *
     * @var string
     */
    private const DEFAULT_TREE_LAYOUT = self::LAYOUT_LEFTRIGHT;

    /**
     * The current request instance.
     *
     * @var ServerRequestInterface
     */
    private ServerRequestInterface $request;

    /**
     * Configuration constructor.
     *
     * @param ServerRequestInterface $request
     */
    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Returns the number of generations to display.
     *
     * @return int
     */
    public function getGenerations(): int
    {
        return Validator::queryParams($this->request)
            ->isBetween(self::MIN_GENERATIONS, self::MAX_GENERATIONS)
            ->integer('generations', self::DEFAULT_GENERATIONS);
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
        return Validator::queryParams($this->request)
            ->string('layout', self::DEFAULT_TREE_LAYOUT);
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
        return Validator::queryParams($this->request)
            ->boolean('hideSpouses', false);
    }

    /**
     * Returns whether to show the married names or not.
     *
     * @return bool
     */
    public function getShowMarriedNames(): bool
    {
        return Validator::queryParams($this->request)
            ->boolean('showMarriedNames', false);
    }
}
