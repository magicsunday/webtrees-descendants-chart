<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart;

use Aura\Router\Exception\ImmutableProperty;
use Aura\Router\Exception\RouteAlreadyExists;
use Closure;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\DescendancyChartModule;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleThemeInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Collection;
use JsonException;
use MagicSunday\Webtrees\DescendantsChart\Traits\ModuleChartTrait;
use MagicSunday\Webtrees\DescendantsChart\Traits\ModuleCustomTrait;
use MagicSunday\Webtrees\ModuleBase\Processor\DateProcessor;
use MagicSunday\Webtrees\ModuleBase\Processor\ImageProcessor;
use MagicSunday\Webtrees\ModuleBase\Processor\NameProcessor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

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
    public const CUSTOM_VERSION = '1.6.1-dev';

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
     *
     * @return void
     *
     * @throws ImmutableProperty
     * @throws RouteAlreadyExists
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
     * Returns the path where this module stores its resources.
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

        if ($ajax) {
            $this->layout = $this->name() . '::layouts/ajax';

            return $this->viewResponse(
                $this->name() . '::modules/descendants-chart/chart',
                [
                    'id'                => uniqid(),
                    'data'              => $this->createJsonTreeStructure($individual),
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
     * @param Individual $individual The individual used in the current chart
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
     * Creates the JSON tree structure.
     *
     * @param Individual $individual
     *
     * @return mixed[]
     */
    private function createJsonTreeStructure(Individual $individual): array
    {
        // The root node with all children
        $root = [
            'children' => $this->buildJsonTree($individual),
        ];

        // We need to post-process the data to correct the structure for some special cases
        return $this->postProcessTreeStructure($root);
    }

    /**
     * Post process the tree structure.
     *
     * @param mixed[] $root
     *
     * @return mixed[]
     */
    private function postProcessTreeStructure(array $root): array
    {
        $recursiveIterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($root),
            RecursiveIteratorIterator::SELF_FIRST
        );

        if ($this->configuration->getHideSpouses()) {
            $this->sortChildrenByBirthdate($recursiveIterator);
        }

        $this->removeObsoleteData($recursiveIterator);

        return $recursiveIterator->getInnerIterator()->getArrayCopy();
    }

    /**
     * Sort the list of children by their date of birth.
     *
     * If the spouses are not displayed, the children in a polygamous family may not be sorted correctly,
     * for example, if there are several children with different partners who have mixed dates of birth.
     *
     * @param RecursiveIteratorIterator $recursiveIterator
     *
     * @return void
     */
    private function sortChildrenByBirthdate(RecursiveIteratorIterator $recursiveIterator): void
    {
        // Sort children by birthdate
        foreach ($recursiveIterator as $key => $value) {
            if (is_array($value) && array_key_exists('children', $value)) {
                $childrenCollection = new Collection($value['children']);

                // Check if each individual in the child list has a valid birthdate
                if (
                    $childrenCollection->every(
                        static function (array $value, int $key): bool {
                            return $value['data']['individual']->getBirthDate()->isOK();
                        }
                    )
                ) {
                    $value['children'] = $childrenCollection
                        ->sort(self::birthDateComparator())
                        ->values()
                        ->toArray();

                    $this->updateValueInIterator($recursiveIterator, $value);
                }
            }
        }
    }

    /**
     * Removes any obsolete/intermediate data from the final structure.
     *
     * @param RecursiveIteratorIterator $recursiveIterator
     *
     * @return void
     */
    private function removeObsoleteData(RecursiveIteratorIterator $recursiveIterator): void
    {
        // Remove individual instance as this was only required to sort by birthdate in the previous step
        foreach ($recursiveIterator as $key => $value) {
            if (is_array($value) && array_key_exists('individual', $value)) {
                unset($value['individual']);

                $this->updateValueInIterator($recursiveIterator, $value);
            }
        }
    }

    /**
     * Updates the value in the iterator.
     *
     * @param RecursiveIteratorIterator $recursiveIterator
     * @param mixed                     $value
     *
     * @return void
     */
    private function updateValueInIterator(RecursiveIteratorIterator $recursiveIterator, $value): void
    {
        // Get the current depth and traverse back up the tree, saving the modifications
        $currentDepth = $recursiveIterator->getDepth();

        for ($subDepth = $currentDepth; $subDepth >= 0; --$subDepth) {
            // Get the current level iterator
            $subIterator = $recursiveIterator->getSubIterator($subDepth);

            // If we are on the level we want to change, use the replacements ($value)
            // otherwise set the key to the parent iterators value
            if ($subIterator !== null) {
                $subIteratorNext = $recursiveIterator->getSubIterator($subDepth + 1);

                $subIterator->offsetSet(
                    $subIterator->key(),
                    $subDepth === $currentDepth
                        ? $value
                        : ($subIteratorNext !== null ? $subIteratorNext->getArrayCopy() : [])
                );
            }
        }
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

        // Get spouse families
        $families = $individual->spouseFamilies();
        $parents  = [];

        $parents[$individual->xref()] = [
            'data' => $this->getIndividualData($individual, $generation),
        ];

        if ($families->count() > 0) {
            /** @var Family $family */
            foreach ($families as $familyIndex => $family) {
                $children = [];
                $spouse   = null;

                if (!$this->configuration->getHideSpouses()) {
                    $spouse = $family->spouse($individual);
                }

                foreach ($family->children() as $child) {
                    $childTree = $this->buildJsonTree($child, $generation + 1);

                    if (count($childTree) > 0) {
                        foreach ($childTree as $childData) {
                            if ($childData['data'] !== null) {
                                $children[$childData['data']['id']] = $childData;
                            } else {
                                $children[$childData['spouse']]['children'] = $childData['children'];
                                $children[$childData['spouse']]['family'] = $childData['family'];
                            }
                        }
                    }
                }

                if ($spouse !== null) {
                    $parentData = [
                        'data'     => $this->getIndividualData($spouse, $generation, $individual),
                        'spouse'   => $parents[$individual->xref()]['data']['id'],
                        'family'   => $familyIndex,
                        'children' => array_values($children),
                    ];

                    $parents[] = $parentData;

                    // Add spouse to list
                    $parents[$individual->xref()]['spouses'][] = $parentData['data']['id'];
                } else {
                    $parents[$individual->xref()]['family'] = $familyIndex;

                    if (!isset($parents[$individual->xref()]['children'])) {
                        $parents[$individual->xref()]['children'] = [];
                    }

                    // If there is no spouse, merge all children from all families
                    // of the individual into one list
                    $parents[$individual->xref()]['children'] = array_merge(
                        $parents[$individual->xref()]['children'],
                        array_values($children)
                    );
                }
            }
        }

        return array_values($parents);
    }

    /**
     * A closure which will compare individuals by birthdate.
     *
     * @return Closure(mixed[],mixed[]):int
     */
    public static function birthDateComparator(): Closure
    {
        return static function (array $x, array $y): int {
            return Date::compare(
                $x['data']['individual']->getEstimatedBirthDate(),
                $y['data']['individual']->getEstimatedBirthDate()
            );
        };
    }

    /**
     * Get the individual data required for display the chart.
     *
     * @param Individual      $individual The current individual
     * @param int             $generation The generation the person belongs to
     * @param null|Individual $spouse
     *
     * @return array<string, array<string>|bool|int|string>
     */
    private function getIndividualData(Individual $individual, int $generation, Individual $spouse = null): array
    {
        $nameProcessor = new NameProcessor(
            $individual,
            $spouse,
            $this->configuration->getShowMarriedNames()
        );

        $dateProcessor  = new DateProcessor($individual);
        $imageProcessor = new ImageProcessor($this, $individual);

        $alternativeNames = $nameProcessor->getAlternateNames();

        // Create a unique ID for each individual
        static $id = 0;

        return [
            'id'               => ++$id,
            'xref'             => $individual->xref(),
            'url'              => $individual->url(),
            'updateUrl'        => $this->getUpdateRoute($individual),
            'generation'       => $generation,
            'name'             => $nameProcessor->getFullName(),
            'firstNames'       => $nameProcessor->getFirstNames(),
            'lastNames'        => $nameProcessor->getLastNames(),
            'preferredName'    => $nameProcessor->getPreferredName(),
            'alternativeNames' => $alternativeNames,
            'isAltRtl'         => $this->isRtl($alternativeNames),
            'thumbnail'        => $imageProcessor->getHighlightImageUrl(),
            'sex'              => $individual->sex(),
            'birth'            => $dateProcessor->getBirthDate(),
            'death'            => $dateProcessor->getDeathDate(),
            'timespan'         => $dateProcessor->getLifetimeDescription(),
            'individual'       => $individual,
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
        $stylesheets   = Registry::container()->get(ModuleThemeInterface::class)->stylesheets();
        $stylesheets[] = $this->assetUrl('css/svg.css');

        return $stylesheets;
    }
}
