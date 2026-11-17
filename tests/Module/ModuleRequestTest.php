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
use Fisharebest\Webtrees\Contracts\IndividualFactoryInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factories\CacheFactory;
use Fisharebest\Webtrees\Factories\ResponseFactory;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\DescendantsChart\Support\ContextText;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\ModuleSettingsFixtureTrait;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\ViewFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\EnglishLocaleFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\RoutingFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function preg_match;
use function rawurlencode;

/**
 * Drives the chart partial of Module::handle() through the access checks and the facade, so the
 * container, the script and the stylesheets that the partial renders are the ones the request
 * produces.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
#[CoversClass(Module::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(DataFacade::class)]
#[UsesClass(DescendantLine::class)]
#[UsesClass(CoupleNode::class)]
#[UsesClass(ContextText::class)]
final class ModuleRequestTest extends TestCase
{
    use ModuleSettingsFixtureTrait;

    private const int TREE_ID = 7;

    /**
     * The tree of the request, which the person of the test belongs to.
     */
    private Tree $tree;

    /**
     * Prepares the container, the registry, the views and the database that a request needs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        EnglishLocaleFixture::install();
        Validator::serverParams(new ServerRequest(RequestMethodInterface::METHOD_GET, '/'));
        View::registerNamespace(ViewFixture::MODULE_NAME, __DIR__ . '/../../resources/views/');

        Registry::cache(new CacheFactory());
        Registry::responseFactory(new ResponseFactory(new HttpFactory(), new HttpFactory()));

        RoutingFixture::register([
            ServerRequestInterface::class => new ServerRequest(RequestMethodInterface::METHOD_GET, '/'),
        ]);

        $this->tree = $this->createTree();

        // The module settings fixture boots the in-memory database that holds the access levels.
        $this->createModuleWithPreferences([]);

        $schema = DB::connection()->getSchemaBuilder();

        if (!$schema->hasTable('module_privacy')) {
            $schema->create('module_privacy', static function (Blueprint $table): void {
                $table->integer('gedcom_id');
                $table->string('module_name');
                $table->string('interface');
                $table->integer('access_level');
            });
        }

        DB::table('module_privacy')->delete();
    }

    /**
     * The chart partial carries the chart of the requested person and no page shell around it.
     */
    #[Test]
    public function theChartPartialCarriesTheDataOfTheRequestedPerson(): void
    {
        $response = $this->createModuleWithPreferences([])->handle($this->chartRequest());
        $body     = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"xref":"I1"', $body);
        self::assertStringNotContainsString('descendants-chart-url', $body);
    }

    /**
     * The chart partial names its container with a unique id and the script selects exactly that
     * container, loads the script of the module in its current version and links the stylesheets
     * of the chart.
     */
    #[Test]
    public function theChartPartialCarriesTheContainerIdTheScriptAndTheStylesheets(): void
    {
        $response = $this->createModuleWithPreferences([])->handle($this->chartRequest());
        $body     = (string) $response->getBody();

        self::assertSame(
            1,
            preg_match('/id="(webtrees-descendants-chart-container-[^"]+)"/', $body, $container),
        );
        self::assertStringContainsString(
            '"#' . $container[1] . '"',
            $body,
            'The script selects the container by that id.',
        );
        self::assertStringContainsString(
            'asset=' . rawurlencode('js/descendants-chart-' . Module::CUSTOM_VERSION . '.min.js'),
            $body
        );
        self::assertStringContainsString('asset=' . rawurlencode('css/descendants-chart.css'), $body);
        self::assertStringContainsString('asset=' . rawurlencode('css/svg.css'), $body);
    }

    /**
     * Builds the chart partial request for the person that the factory finds.
     */
    private function chartRequest(): ServerRequestInterface
    {
        $individual = $this->createIndividual();
        $factory    = self::createStub(IndividualFactoryInterface::class);

        // Only the requested xref of the test tree resolves, so a lookup of any other person fails the request.
        $factory->method('make')->willReturnCallback(
            fn (string $xref, Tree $tree): ?Individual => ($xref === 'I1') && ($tree === $this->tree) ? $individual : null
        );

        Registry::individualFactory($factory);

        return (new ServerRequest(RequestMethodInterface::METHOD_GET, '/'))
            ->withAttribute('tree', $this->tree)
            ->withAttribute('user', self::createStub(UserInterface::class))
            ->withAttribute('xref', 'I1')
            ->withQueryParams(['ajax' => '1']);
    }

    /**
     * Creates the tree of the request, with every preference on except the display of private
     * relationships and the surname tradition.
     */
    private function createTree(): Tree
    {
        $tree = self::createStub(Tree::class);
        $tree->method('id')->willReturn(self::TREE_ID);
        $tree->method('name')->willReturn('main');
        $tree->method('getPreference')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                'SHOW_PRIVATE_RELATIONSHIPS', 'SURNAME_TRADITION' => '',
                default                                           => '1',
            }
        );

        return $tree;
    }

    /**
     * Creates a visible person named Jane Doe without families, with everything that the facade reads.
     */
    private function createIndividual(): Individual
    {
        $unknownDate = self::createStub(Date::class);
        $unknownDate->method('display')->willReturn('');
        $unknownDate->method('isOK')->willReturn(false);

        $nameFact = self::createStub(Fact::class);
        $nameFact->method('attribute')->willReturn('');

        $individual = self::createStub(Individual::class);
        $individual->method('tree')->willReturn($this->tree);
        $individual->method('xref')->willReturn('I1');
        $individual->method('url')->willReturn('/individual/I1');
        $individual->method('sex')->willReturn('F');
        $individual->method('fullName')->willReturn('Jane Doe');
        $individual->method('canShow')->willReturn(true);
        $individual->method('canShowName')->willReturn(true);
        $individual->method('canEdit')->willReturn(false);
        $individual->method('isDead')->willReturn(false);
        $individual->method('getBirthDate')->willReturn($unknownDate);
        $individual->method('getEstimatedBirthDate')->willReturn($unknownDate);
        $individual->method('getDeathDate')->willReturn($unknownDate);
        $individual->method('spouseFamilies')->willReturn(new Collection());
        $individual->method('facts')->willReturn(new Collection([$nameFact]));
        $individual->method('findHighlightedMediaFile')->willReturn(null);
        $individual->method('getPrimaryName')->willReturn(0);
        $individual->method('getSecondaryName')->willReturn(0);
        $individual->method('getAllNames')->willReturn([
            [
                'full'   => '<span class="NAME" dir="auto" translate="no">Jane <span class="SURN">Doe</span></span>',
                'fullNN' => 'Jane Doe',
                'type'   => 'BIRT',
                'surn'   => 'Doe',
                'givn'   => 'Jane',
                'sort'   => '',
            ],
        ]);

        return $individual;
    }
}
