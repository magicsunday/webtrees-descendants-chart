<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Module;

use Aura\Router\Map;
use Aura\Router\Route;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Contracts\ContainerInterface;
use Fisharebest\Webtrees\Contracts\RouteFactoryInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\EnglishLocaleFixture;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\ModuleSettingsFixtureTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Validates module metadata exposure and chart helpers.
 */
#[CoversClass(Module::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(DescendantLine::class)]
final class ModuleTest extends TestCase
{
    use ModuleSettingsFixtureTrait;

    /**
     * Ensures trait-provided helpers return expected metadata and URLs.
     */
    #[Test]
    public function traitHelpersExposeExpectedValues(): void
    {
        $this->registerRouteFactory();

        $chartService = self::createStub(ChartService::class);
        $module       = new Module($chartService, new DataFacade());
        $individual   = self::createStub(Individual::class);
        $tree         = self::createStub(Tree::class);

        EnglishLocaleFixture::install();

        $individual->method('fullName')->willReturn('Example Person');
        $individual->method('xref')->willReturn('I1');
        $individual->method('tree')->willReturn($tree);
        $tree->method('name')->willReturn('main');

        self::assertSame('menu-chart-descendants', $module->chartMenuClass());
        self::assertStringContainsString('Example Person', $module->chartTitle($individual));
        self::assertStringContainsString('/route/webtrees-descendants-chart?', $module->chartUrl($individual));
    }

    /**
     * Pins that the redirect which turns a form POST into a GET carries every
     * route toggle parameter, so a setting added to the shared list cannot be
     * missing from the URL the user lands on.
     */
    #[Test]
    public function postRedirectCarriesEveryRouteToggleParameter(): void
    {
        $this->registerRouteFactory();

        $module  = $this->createModuleWithPreferences([]);
        $tree    = self::createStub(Tree::class);
        $request = (new ServerRequest(RequestMethodInterface::METHOD_POST, '/'))
            ->withParsedBody([
                'xref'                => 'I1',
                'generations'         => '3',
                'layout'              => 'down',
                'hideSpouses'         => '1',
                'descendantLine'      => 'maternal',
                'marriedNamesMode'    => 'married_only',
                'showNicknames'       => '1',
                'openNewTabOnClick'   => '0',
                'showAlternativeName' => '0',
                'showDetailPanel'     => '1',
            ])
            ->withAttribute('tree', $tree)
            ->withAttribute('xref', 'I1')
            ->withAttribute('user', self::createStub(UserInterface::class));

        $tree->method('name')->willReturn('main');

        $response = $module->handle($request);

        parse_str((string) parse_url($response->getHeaderLine('location'), PHP_URL_QUERY), $query);

        $expected = (new Configuration($request, $module))->getRouteToggleParams();

        self::assertNotSame([], $expected);

        foreach ($expected as $name => $value) {
            self::assertArrayHasKey($name, $query);
            self::assertSame((string) $value, $query[$name]);
        }

        self::assertArrayHasKey('xref', $query);
        self::assertArrayHasKey('tree', $query);
        self::assertSame('I1', $query['xref']);
        self::assertSame('main', $query['tree']);
    }

    /**
     * The module configuration page stores the descendant line as the default
     * for the chart page, and a value outside the three words keeps the stored default.
     */
    #[Test]
    public function adminFormStoresTheDescendantLineAsDefault(): void
    {
        $this->registerRouteFactory();
        EnglishLocaleFixture::install();

        $module = $this->createModuleWithPreferences([]);

        $module->postAdminAction(
            (new ServerRequest(RequestMethodInterface::METHOD_POST, '/'))
                ->withParsedBody(['descendantLine' => 'maternal'])
        );

        self::assertSame('maternal', $module->getPreference('default_descendantLine'));

        $module->postAdminAction(
            (new ServerRequest(RequestMethodInterface::METHOD_POST, '/'))
                ->withParsedBody(['descendantLine' => 'sideways'])
        );

        self::assertSame('maternal', $module->getPreference('default_descendantLine'));
    }

    /**
     * Pins that saving the administration form stores the detail panel option
     * as the default of the chart.
     */
    #[Test]
    public function adminFormStoresThePanelDefault(): void
    {
        $this->registerRouteFactory();
        EnglishLocaleFixture::install();

        $module = $this->createModuleWithPreferences([]);

        $module->postAdminAction(
            (new ServerRequest(RequestMethodInterface::METHOD_POST, '/'))
                ->withParsedBody(['showDetailPanel' => '1'])
        );

        $stored = new Configuration(new ServerRequest(RequestMethodInterface::METHOD_GET, '/'), $module);

        self::assertSame('1', $module->getPreference('default_showDetailPanel'));
        self::assertTrue($stored->getShowDetailPanel());
    }

    /**
     * Pins that saving the administration form with the switch off takes the panel
     * default back. The form submits a hidden zero for an unchecked box.
     */
    #[Test]
    public function adminFormTakesThePanelDefaultBackWhenTheSwitchIsOff(): void
    {
        $this->registerRouteFactory();
        EnglishLocaleFixture::install();

        $module = $this->createModuleWithPreferences(['default_showDetailPanel' => '1']);

        $module->postAdminAction(
            (new ServerRequest(RequestMethodInterface::METHOD_POST, '/'))
                ->withParsedBody(['showDetailPanel' => '0'])
        );

        $stored = new Configuration(new ServerRequest(RequestMethodInterface::METHOD_GET, '/'), $module);

        self::assertFalse($stored->getShowDetailPanel());
    }

    /**
     * The module configuration page renders and preselects the stored
     * name-abbreviation strategy.
     */
    #[Test]
    public function configurationPageRendersTheStoredNameAbbreviation(): void
    {
        $this->registerRouteFactory();
        EnglishLocaleFixture::install();

        $module = $this->createModuleWithPreferences(['default_nameAbbreviation' => 'SURNAME']);

        View::registerNamespace($module->name(), dirname(__DIR__, 2) . '/resources/views/');

        $html = View::make(
            $module->name() . '::modules/descendants-chart/config',
            [
                'configuration' => new Configuration(new ServerRequest(RequestMethodInterface::METHOD_GET, '/'), $module),
                'moduleName'    => $module->name(),
                'title'         => $module->title(),
                'description'   => $module->description(),
            ]
        );

        self::assertStringContainsString('<option value="SURNAME" selected="selected">', $html);
        self::assertStringNotContainsString('<option value="AUTO" selected="selected">', $html);
    }

    /**
     * Registers a route factory that renders a route as a plain path with its
     * query string, and a container that hands out the PSR-17 response factory
     * the global redirect() helper resolves.
     */
    private function registerRouteFactory(): void
    {
        $routeFactory = new class implements RouteFactoryInterface {
            public function route(string $route_name, array $parameters = []): string
            {
                return '/route/' . $route_name . '?' . http_build_query($parameters);
            }

            public function routeMap(): Map
            {
                return new Map(new Route());
            }
        };

        $container = new readonly class($routeFactory, new HttpFactory()) implements ContainerInterface {
            public function __construct(
                private RouteFactoryInterface $routeFactory,
                private ResponseFactoryInterface $responseFactory,
            ) {
            }

            public function get(string $id): object
            {
                return $id === ResponseFactoryInterface::class ? $this->responseFactory : $this->routeFactory;
            }

            public function has(string $id): bool
            {
                return in_array($id, [RouteFactoryInterface::class, ResponseFactoryInterface::class], true);
            }

            public function set(string $id, object $object): static
            {
                return $this;
            }
        };

        Registry::routeFactory($routeFactory);
        Registry::container($container);
    }
}
