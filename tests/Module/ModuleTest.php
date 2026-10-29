<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Module;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Individual;
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
use MagicSunday\Webtrees\DescendantsChart\Test\Support\RoutingFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function view;

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
        RoutingFixture::install(new HttpFactory());

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
        RoutingFixture::install(new HttpFactory());

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
        RoutingFixture::install(new HttpFactory());
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
        RoutingFixture::install(new HttpFactory());
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
        RoutingFixture::install(new HttpFactory());
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
        RoutingFixture::install(new HttpFactory());
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
     * Booting the module registers the shared views of the module base, so the page can render them.
     * The registration is static state that other tests set up as well, so the test runs in its own
     * process and starts without it.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function bootRegistersTheSharedViewsOfTheModuleBase(): void
    {
        $routeMap = RoutingFixture::install(new HttpFactory());
        EnglishLocaleFixture::install();

        $module = $this->createModuleWithPreferences([]);
        $module->boot();

        $route = $routeMap->getRoute(Module::ROUTE_DEFAULT);

        self::assertSame(Module::ROUTE_DEFAULT_URL, $route->path);
        self::assertContains('POST', $route->allows);
        self::assertFileExists((new View($module->name() . '::layouts/ajax'))->getFilenameForView($module->name() . '::layouts/ajax'));

        $markup = view('webtrees-module-base::help-popover', [
            'id'         => 'booted-help-popover',
            'label'      => 'Show help',
            'rows'       => [['key' => 'Tab key', 'text' => 'Move between the persons']],
            'activeMode' => null,
        ]);

        self::assertStringContainsString('id="booted-help-popover"', $markup);
    }
}
