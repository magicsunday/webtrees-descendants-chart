<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Support;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Tree;
use GuzzleHttp\Psr7\ServerRequest;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Assert;

/**
 * Boots webtrees on a real schema, registers the module of the chart with its route and builds the
 * structure of the chart for the current viewer.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
final readonly class RealTreeChart
{
    /**
     * @param Module $module The module of the chart
     */
    private function __construct(
        private Module $module,
    ) {
    }

    /**
     * Boots webtrees and registers the module of the chart with its route.
     *
     * @param ChartService $chartService The chart service that the module is built with
     */
    public static function boot(ChartService $chartService): self
    {
        RealTreeFixture::boot();

        DB::table('module')->insert(['module_name' => 'webtrees-descendants-chart']);

        $module = new Module($chartService, new DataFacade());
        $module->setName('webtrees-descendants-chart');
        $module->boot();

        return new self($module);
    }

    /**
     * Returns the module of the chart.
     */
    public function module(): Module
    {
        return $this->module;
    }

    /**
     * Builds the tree structure of the chart for the current viewer.
     *
     * @param Tree   $tree        The imported tree
     * @param string $xref        The xref of the chart subject
     * @param int    $generations The number of generations of the chart
     * @param bool   $hideSpouses Whether the chart hides the partners
     * @param string $line        The descendant line of the chart
     * @param bool   $detailPanel Whether the detail panel is on
     *
     * @return array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>}
     */
    public function structure(
        Tree $tree,
        string $xref,
        int $generations,
        bool $hideSpouses,
        string $line = 'all',
        bool $detailPanel = false,
    ): array {
        $individual = Registry::individualFactory()->make($xref, $tree);
        Assert::assertInstanceOf(Individual::class, $individual);

        $configuration = new Configuration(
            (new ServerRequest(RequestMethodInterface::METHOD_GET, '/'))->withQueryParams([
                'generations'     => (string) $generations,
                'showDetailPanel' => $detailPanel ? '1' : '0',
                'hideSpouses'     => $hideSpouses ? '1' : '0',
                'descendantLine'  => $line,
            ]),
            $this->module,
        );

        $structure = (new DataFacade())
            ->setModule($this->module)
            ->setConfiguration($configuration)
            ->setRoute(Module::ROUTE_DEFAULT)
            ->createTreeStructure($individual);

        Assert::assertIsArray($structure);

        return $structure;
    }
}
