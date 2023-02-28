<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart;

use Aura\Router\RouterContainer;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\DescendancyChartModule;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use JsonException;
use MagicSunday\Webtrees\DescendantsChart\Traits\IndividualTrait;
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
    use IndividualTrait;

    private const ROUTE_DEFAULT     = 'webtrees-descendants-chart';
    private const ROUTE_DEFAULT_URL = '/tree/{tree}/webtrees-descendants-chart/{xref}';

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
    public const CUSTOM_VERSION = '1.3.1-dev';

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
     * Initialization.
     */
    public function boot(): void
    {
        /** @var RouterContainer $routerContainer */
        $routerContainer = app(RouterContainer::class);

        $routerContainer->getMap()
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
     * Where does this module store its resources
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
     *
     * @throws JsonException
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
                        'tree'        => $tree->name(),
                        'xref'        => $validator->string('xref', ''),
                        'generations' => $validator->integer('generations', 4),
                        'hideSpouses' => $validator->boolean('hideSpouses', false),
                        'layout'      => $validator->string('layout', Configuration::LAYOUT_LEFTRIGHT),
                    ]
                )
            );
        }

        Auth::checkComponentAccess($this, ModuleChartInterface::class, $tree, $user);

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual, false, true);

        $this->configuration = new Configuration($request);

        if ($ajax) {
            $this->layout = $this->name() . '::layouts/ajax';

            return $this->viewResponse(
                $this->name() . '::modules/descendants-chart/chart',
                [
                    'data'          => $this->buildJsonTree($individual),
                    'configuration' => $this->configuration,
                    'chartParams'   => json_encode($this->getChartParameters(), JSON_THROW_ON_ERROR),
                    'stylesheet'    => $this->assetUrl('css/descendants-chart.css'),
                    'svgStylesheet' => $this->assetUrl('css/svg.css'),
                    'javascript'    => $this->assetUrl('js/descendants-chart.min.js'),
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
                'stylesheet'    => $this->assetUrl('css/descendants-chart.css'),
                'svgStylesheet' => $this->assetUrl('css/svg.css'),
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
     * Recursively build the data array of the individual ancestors.
     *
     * @param null|Individual $individual The start person
     * @param int             $generation The current generation
     *
     * @return mixed[]
     */
    private function buildJsonTree(?Individual $individual, int $generation = 1): array
    {
        // Maximum generation reached
        if (($individual === null) || ($generation > $this->configuration->getGenerations())) {
            return [];
        }

        /** @var array<string, array<string>> $data */
        $data     = $this->getIndividualData($individual, $generation);
        $families = $individual->spouseFamilies();

        if (!$families->count()) {
            return $data;
        }

        $familiesData = [];

        /** @var Family $family */
        foreach ($families as $family) {
            $spouse   = $family->spouse($individual);
            $children = [];

            foreach ($family->children() as $child) {
                $childTree = $this->buildJsonTree($child, $generation + 1);

                if (count($childTree) > 0) {
                    $children[] = $childTree;
                }
            }

            // Add spouse to last displayed generation too?
            if (($generation + 1) <= $this->configuration->getGenerations()) {
                // Intialize spouse with null otherwise it will be "undefined" in Javascript
                $familyData = [
                    'spouse' => null,
                ];

                if ($spouse !== null) {
                    $familyData['spouse'] = $this->getIndividualData($spouse, $generation);
                }

                if (count($children) > 0) {
                    $familyData['children'] = $children;
                }

                $familiesData[] = $familyData;
            }
        }

        if (count($familiesData) > 0) {
            $data['families'] = $familiesData;
        }

        return $data;
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
                'ajax'        => true,
                'generations' => $this->configuration->getGenerations(),
                'layout'      => $this->configuration->getLayout(),
                'xref'        => $xref,
            ]
        );
    }

    /**
     * Get the raw update URL. The "xref" parameter must be the last one as the URL gets appended
     * with the clicked individual id in order to load the required chart data.
     *
     * @param Individual $individual
     *
     * @return string
     */
    private function getUpdateRoute(Individual $individual): string
    {
        return $this->chartUrl(
            $individual,
            [
                'generations' => $this->configuration->getGenerations(),
                'layout'      => $this->configuration->getLayout(),
            ]
        );
    }

    /**
     * Returns whether the given text is in RTL style or not.
     *
     * @param string[] $text The text to check
     *
     * @return bool
     */
    private function isRtl(array $text): bool
    {
        foreach ($text as $entry) {
            if (I18N::scriptDirection(I18N::textScript($entry)) === 'rtl') {
                return true;
            }
        }

        return false;
    }
}
