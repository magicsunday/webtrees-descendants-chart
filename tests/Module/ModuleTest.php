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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_replace;
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
     * Names the individual in the page title only when the visitor may see the name, and falls back
     * to the plain chart title otherwise, so a private name never reaches the title.
     */
    #[Test]
    public function pageTitleNamesTheIndividualOnlyWhenTheNameIsVisible(): void
    {
        EnglishLocaleFixture::install();

        $module = new Module(self::createStub(ChartService::class), new DataFacade());

        $visible = self::createStub(Individual::class);
        $visible->method('canShowName')->willReturn(true);
        $visible->method('fullName')->willReturn('Example Person');

        $hidden = self::createStub(Individual::class);
        $hidden->method('canShowName')->willReturn(false);
        $hidden->method('fullName')->willReturn('Secret Person');

        self::assertSame('Descendants chart of Example Person', $module->pageTitle($visible));
        self::assertSame('Descendants chart', $module->pageTitle($hidden));
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
     * Pins the preference key and the stored value of every chart setting that is not a switch. Each
     * one is posted with a value that differs from its default, so a renamed key or a setting that is
     * not stored leaves the stored default visible.
     */
    #[Test]
    public function adminFormStoresTheValueOfEveryChoiceSetting(): void
    {
        RoutingFixture::install(new HttpFactory());
        EnglishLocaleFixture::install();

        $module = $this->createModuleWithPreferences([]);

        $module->postAdminAction(
            (new ServerRequest(RequestMethodInterface::METHOD_POST, '/'))
                ->withParsedBody([
                    'generations'      => '9',
                    'layout'           => Configuration::LAYOUT_TOPBOTTOM,
                    'descendantLine'   => 'paternal',
                    'marriedNamesMode' => Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED,
                    'nameAbbreviation' => 'SURNAME',
                ])
        );

        self::assertSame('9', $module->getPreference('default_generations'));
        self::assertSame(Configuration::LAYOUT_TOPBOTTOM, $module->getPreference('default_layout'));
        self::assertSame('paternal', $module->getPreference('default_descendantLine'));
        self::assertSame(Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED, $module->getPreference('default_marriedNamesMode'));
        self::assertSame('SURNAME', $module->getPreference('default_nameAbbreviation'));
    }

    /**
     * Pins the preference key of every switch. Only the posted switch differs from the stored
     * defaults in each case, so a switch that is written to the key of another one is seen, and a
     * switch that is not written at all keeps its default.
     *
     * @param array<string, string> $posted   The form fields of the request
     * @param array<string, string> $expected The stored value of each switch that differs from the default
     */
    #[Test]
    #[DataProvider('switchProvider')]
    public function adminFormStoresEachSwitchUnderItsOwnKey(array $posted, array $expected): void
    {
        RoutingFixture::install(new HttpFactory());
        EnglishLocaleFixture::install();

        $module = $this->createModuleWithPreferences([]);

        $module->postAdminAction(
            (new ServerRequest(RequestMethodInterface::METHOD_POST, '/'))->withParsedBody($posted)
        );

        $stored = [];

        foreach (array_keys($this->storedSwitchDefaults()) as $key) {
            $stored[$key] = $module->getPreference($key, 'not stored');
        }

        self::assertSame(array_replace($this->storedSwitchDefaults(), $expected), $stored);
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, string>}>
     */
    public static function switchProvider(): iterable
    {
        yield 'hide spouses' => [['hideSpouses' => '1'], ['default_hideSpouses' => '1']];
        yield 'new tab on click off' => [['openNewTabOnClick' => '0'], ['default_openNewTabOnClick' => '']];
        yield 'alternative name' => [['showAlternativeName' => '1'], ['default_showAlternativeName' => '1']];
        yield 'nicknames' => [['showNicknames' => '1'], ['default_showNicknames' => '1']];
        yield 'detail panel' => [['showDetailPanel' => '1'], ['default_showDetailPanel' => '1']];
        yield 'hide SVG export' => [['hideSvgExport' => '1'], ['default_hideSvgExport' => '1']];
        yield 'hide PNG export' => [['hidePngExport' => '1'], ['default_hidePngExport' => '1']];
    }

    /**
     * The value that a form without any posted switch stores under the key of each switch.
     *
     * @return array<string, string>
     */
    private function storedSwitchDefaults(): array
    {
        return [
            'default_hideSpouses'         => '',
            'default_openNewTabOnClick'   => '1',
            'default_showAlternativeName' => '',
            'default_showNicknames'       => '',
            'default_showDetailPanel'     => '',
            'default_hideSvgExport'       => '',
            'default_hidePngExport'       => '',
        ];
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
