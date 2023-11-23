<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Traits;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Menu;

/**
 * Trait ModuleChartTrait.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
trait ModuleChartTrait
{
    use \Fisharebest\Webtrees\Module\ModuleChartTrait;

    public function chartMenuClass(): string
    {
        return 'menu-chart-descendants';
    }

    public function chartBoxMenu(Individual $individual): ?Menu
    {
        return $this->chartMenu($individual);
    }

    public function chartTitle(Individual $individual): string
    {
        return I18N::translate('Descendants chart of %s', $individual->fullName());
    }

    public function chartUrl(Individual $individual, array $parameters = []): string
    {
        return route(
            self::ROUTE_DEFAULT,
            [
                'xref' => $individual->xref(),
                'tree' => $individual->tree()->name(),
            ] + $parameters
        );
    }
}
