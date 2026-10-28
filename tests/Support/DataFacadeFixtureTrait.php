<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Support;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factories\CalendarDateFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use PHPUnit\Framework\MockObject\Stub;

use function is_string;

/**
 * Builds the people, families and charts that the facade tests describe their cases with.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
trait DataFacadeFixtureTrait
{
    use ModuleSettingsFixtureTrait;

    /**
     * The formatted birth name of the person the facade tests start from.
     */
    private const string ANNA_SCHMIDT = '<span class="NAME" dir="auto" translate="no">Anna <span class="SURN">Schmidt</span></span>';

    /**
     * Prepares the router and translation context the facade needs.
     */
    private function installFacadeContext(): void
    {
        Validator::serverParams(new ServerRequest('GET', '/'));
        Registry::calendarDateFactory(new CalendarDateFactory());

        EnglishLocaleFixture::install();

        RoutingFixture::install();
    }

    /**
     * Creates an individual stub with the given name records and no dates.
     *
     * @param list<array<string, string>> $names         The name records of the person
     * @param string                      $nickname      The GEDCOM nickname of the primary name
     * @param int                         $secondaryName The index of the alternative name record
     * @param string                      $birth         The GEDCOM birth date of the person
     * @param array<string, Fact>|null    $factsByTag    The fact of each tag, or null for a person without facts
     * @param string                      $chartBoxTags  The tags that the tree names for the chart box
     *
     * @return Individual&Stub
     */
    private function createPerson(
        string $xref,
        string $sex,
        array $names,
        string $nickname = '',
        int $secondaryName = 0,
        string $birth = '',
        ?array $factsByTag = null,
        string $chartBoxTags = '',
    ): Individual {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn('main');
        $tree->method('getPreference')->willReturnCallback(
            static fn (string $name): string => ($name === 'CHART_BOX_TAGS') ? $chartBoxTags : ''
        );

        $nameFact = self::createStub(Fact::class);
        $nameFact->method('attribute')->willReturnMap([
            ['TYPE', ''],
            ['NICK', $nickname],
        ]);

        $person = self::createStub(Individual::class);
        $person->method('xref')->willReturn($xref);
        $person->method('url')->willReturn('/individual/' . $xref);
        $person->method('sex')->willReturn($sex);
        $person->method('canShow')->willReturn(true);
        $person->method('canShowName')->willReturn(true);
        $person->method('getAllNames')->willReturn($names);
        $person->method('getPrimaryName')->willReturn(0);
        $person->method('getSecondaryName')->willReturn($secondaryName);
        $person->method('getBirthDate')->willReturn(new Date($birth));
        $person->method('getEstimatedBirthDate')->willReturn(new Date($birth));
        $person->method('getDeathDate')->willReturn(new Date(''));
        $person->method('tree')->willReturn($tree);
        $person->method('facts')->willReturnCallback(
            static function (array $filter = []) use ($nameFact, $factsByTag): Collection {
                $first = $filter[0] ?? '';
                $tag   = is_string($first) ? $first : '';

                if (($factsByTag === null) || ($tag === 'NAME')) {
                    return new Collection([$nameFact]);
                }

                return new Collection(isset($factsByTag[$tag]) ? [$factsByTag[$tag]] : []);
            }
        );
        $person->method('findHighlightedMediaFile')->willReturn(null);

        return $person;
    }

    /**
     * Creates a name record the way webtrees supplies it to the name processor.
     *
     * @return array<string, string>
     */
    private function createName(string $formatted, string $plain, string $surname = '', string $type = 'BIRT'): array
    {
        return [
            'full'   => $formatted,
            'fullNN' => $plain,
            'type'   => $type,
            'surn'   => $surname,
            'givn'   => '',
            'sort'   => '',
        ];
    }

    /**
     * Creates a family stub with an optional partner and the given children.
     *
     * @param Individual[] $children
     */
    private function createFamily(?Individual $partner, array $children): Family
    {
        $family = self::createStub(Family::class);
        $family->method('children')->willReturn(new Collection($children));
        $family->method('spouse')->willReturn($partner);

        return $family;
    }

    /**
     * Builds the chart of the person from the request parameters and returns its
     * first couple, the way the facade serialises it for the browser.
     *
     * @param array<string, string> $query The request parameters of the chart
     *
     * @return array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>}
     */
    private function buildStructure(Individual&Stub $person, array $query): array
    {
        $module = self::createStubForIntersectionOfInterfaces([ModuleCustomInterface::class, ModuleAssetUrlInterface::class]);
        $module->method('name')->willReturn('webtrees-descendants-chart');

        $request = (new ServerRequest('GET', '/'))->withQueryParams($query);

        $facade = (new DataFacade())
            ->setConfiguration(new Configuration($request, $this->createModuleWithPreferences([])))
            ->setModule($module)
            ->setRoute('module');

        $structure = $facade->createTreeStructure($person);

        self::assertIsArray($structure);

        return $structure;
    }

    /**
     * Returns the member of a couple at the given position.
     *
     * @param NodeData[] $members
     */
    private function memberAt(array $members, int $index): NodeData
    {
        self::assertArrayHasKey($index, $members);

        return $members[$index];
    }
}
