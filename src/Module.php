<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\DescendancyChartModule;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Traits\ModuleChartTrait;
use MagicSunday\Webtrees\DescendantsChart\Traits\ModuleCustomTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Descendants chart module class.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
class Module extends DescendancyChartModule implements ModuleCustomInterface
{
    use ModuleCustomTrait;
    use ModuleChartTrait;

    public const ROUTE_DEFAULT     = 'webtrees-descendants-chart';
    public const ROUTE_DEFAULT_URL = '/tree/{tree}/webtrees-descendants-chart/{xref}';

    /**
     * @var string
     */
    private const GITHUB_REPO = 'magicsunday/webtrees-descendants-chart';

    /**
     * @var string
     */
    public const CUSTOM_AUTHOR = 'Rico Sonntag';

    /**
     * @var string
     */
    public const CUSTOM_VERSION = '1.7.1';

    /**
     * @var string
     */
    public const CUSTOM_SUPPORT_URL = 'https://github.com/' . self::GITHUB_REPO . '/issues';

    /**
     * @var string
     */
    public const CUSTOM_LATEST_VERSION = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

    /**
     * The configuration instance.
     *
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var DataFacade
     */
    private DataFacade $dataFacade;

    /**
     * Constructor.
     *
     * @param ChartService $chartService
     * @param DataFacade   $dataFacade
     */
    public function __construct(
        ChartService $chartService,
        DataFacade $dataFacade
    ) {
        parent::__construct($chartService);

        $this->dataFacade = $dataFacade;
    }

    /**
     * Initialization.
     */
    public function boot(): void
    {
        Registry::routeFactory()
            ->routeMap()
            ->get(self::ROUTE_DEFAULT, self::ROUTE_DEFAULT_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);

        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
        View::registerCustomView('::modules/charts/chart', $this->name() . '::modules/charts/chart');
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return I18N::translate('Descendants chart');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return I18N::translate('An overview of an individual’s descendants.');
    }

    /**
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     * Handles a request and produces a response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $xref = Validator::attributes($request)->isXref()->string('xref');
        $user = Validator::attributes($request)->user();
        $ajax = Validator::queryParams($request)->boolean('ajax', false);

        // Convert POST requests into GET requests for pretty URLs.
        // This also updates the name above the form, which won't get updated if only a POST request is used
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validator = Validator::parsedBody($request);

            return redirect(
                route(
                    self::ROUTE_DEFAULT,
                    [
                        'tree'             => $tree->name(),
                        'xref'             => $validator->string('xref', ''),
                        'generations'      => $validator->integer('generations', 4),
                        'hideSpouses'      => $validator->boolean('hideSpouses', false),
                        'showMarriedNames' => $validator->boolean('showMarriedNames', false),
                        'layout'           => $validator->string('layout', Configuration::LAYOUT_LEFTRIGHT),
                    ]
                )
            );
        }

        Auth::checkComponentAccess($this, ModuleChartInterface::class, $tree, $user);

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual, false, true);

        $this->configuration = new Configuration($request);

        $this->dataFacade
            ->setModule($this)
            ->setConfiguration($this->configuration)
            ->setRoute(self::ROUTE_DEFAULT);

        if ($ajax) {
            $this->layout = $this->name() . '::layouts/ajax';

            return $this->viewResponse(
                $this->name() . '::modules/descendants-chart/chart',
                [
                    'id'                => uniqid(),
                    'data'              => $this->dataFacade->createTreeStructure($individual),
                    'configuration'     => $this->configuration,
                    'chartParams'       => $this->getChartParameters(),
                    'exportStylesheets' => $this->getExportStylesheets(),
                    'javascript'        => $this->assetUrl('js/descendants-chart.min.js'),
                ]
            );
        }

        return $this->viewResponse(
            $this->name() . '::modules/descendants-chart/page',
            [
                'ajaxUrl'       => $this->getAjaxRoute($individual, $xref),
                'title'         => $this->getPageTitle($individual),
                'moduleName'    => $this->name(),
                'individual'    => $individual,
                'tree'          => $tree,
                'configuration' => $this->configuration,
                'stylesheets'   => $this->getStylesheets(),
                'javascript'    => $this->assetUrl('js/descendants-chart-storage.min.js'),
            ]
        );
    }

    /**
     * Returns the page title.
     *
     * @param Individual $individual The individual used in the curret chart
     *
     * @return string
     */
    private function getPageTitle(Individual $individual): string
    {
        $title = I18N::translate('Descendants chart');

        if ($individual->canShowName()) {
            $title = I18N::translate('Descendants chart of %s', $individual->fullName());
        }

        return $title;
    }

    /**
     * Collects and returns the required chart data.
     *
     * @return array<string, bool|array<string, string>>
     */
    private function getChartParameters(): array
    {
        return [
            'rtl'    => I18N::direction() === 'rtl',
            'labels' => [
                'zoom' => I18N::translate('Use Ctrl + scroll to zoom in the view'),
                'move' => I18N::translate('Move the view with two fingers'),
            ],
        ];
    }

    /**
     *
     * @param Individual $individual
     * @param string     $xref
     *
     * @return string
     */
    private function getAjaxRoute(Individual $individual, string $xref): string
    {
        return $this->chartUrl(
            $individual,
            [
                'ajax'             => true,
                'generations'      => $this->configuration->getGenerations(),
                'hideSpouses'      => $this->configuration->getHideSpouses(),
                'showMarriedNames' => $this->configuration->getShowMarriedNames(),
                'layout'           => $this->configuration->getLayout(),
                'xref'             => $xref,
            ]
        );
    }

    /**
     * Returns a list of used stylesheets with this module.
     *
     * @return array<string>
     */
    private function getStylesheets(): array
    {
        $stylesheets = [];

        $stylesheets[] = $this->assetUrl('css/descendants-chart.css');
        $stylesheets[] = $this->assetUrl('css/svg.css');

        return $stylesheets;
    }

    /**
     * Returns a list required stylesheets for the SVG export.
     *
     * @return array<string>
     */
    private function getExportStylesheets(): array
    {
        /** @var ModuleThemeInterface $moduleTheme */
        $moduleTheme   = app(ModuleThemeInterface::class);
        $stylesheets   = $moduleTheme->stylesheets();
        $stylesheets[] = $this->assetUrl('css/svg.css');

        return $stylesheets;
    }
}
