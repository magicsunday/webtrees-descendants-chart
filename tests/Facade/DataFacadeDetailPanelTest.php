<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Facade;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Place;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\DataFacadeFixtureTrait;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function count;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Validates the facts that the facade puts into the node data for the detail panel: when they are left
 * out, which slots a person carries and how the slots follow the tags of the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
#[CoversClass(DataFacade::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(DescendantLine::class)]
#[UsesClass(CoupleNode::class)]
#[UsesClass(Module::class)]
final class DataFacadeDetailPanelTest extends TestCase
{
    use DataFacadeFixtureTrait;

    /**
     * Prepares the router and translation context the facade needs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->installFacadeContext();
    }

    /**
     * Without the detail panel the payload carries no facts, so the default chart
     * pays neither their size nor their database work.
     */
    #[Test]
    public function leavesTheFactsOutWhileTheDetailPanelIsOff(): void
    {
        $person = $this->createPerson(
            'I1',
            'M',
            [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')],
            factsByTag: ['OCCU' => $this->createFact('Occupation', '', '', 'Smith')],
            chartBoxTags: 'OCCU',
        );

        $data = $this->memberAt($this->buildStructure($person, ['generations' => '1'])['members'], 0);

        self::assertNull($this->serialisedFacts($data));
    }

    /**
     * With the detail panel on, the payload of the subject and of its partner carries
     * the birth, the death and one slot for every tag the tree names for the chart
     * box. A slot the person has no fact for is null.
     */
    #[Test]
    public function handsOverOneFactSlotPerRowToTheSubjectAndThePartner(): void
    {
        $birth = $this->createFact('Birth', 'Berlin, Germany', 'Berlin', '');
        $work  = $this->createFact('Occupation', '', '', 'Smith');
        $name  = [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')];

        $partner = $this->createPerson('I2', 'F', $name, factsByTag: [], chartBoxTags: 'OCCU,RESI');
        $root    = $this->createPerson('I1', 'M', $name, factsByTag: ['BIRT' => $birth, 'OCCU' => $work], chartBoxTags: 'OCCU,RESI');

        $root->method('spouseFamilies')->willReturn(new Collection([$this->createFamily($partner, [])]));

        $members = $this->buildStructure($root, ['generations' => '1', 'showDetailPanel' => '1'])['members'];

        self::assertSame(
            [
                [
                    'tag'       => 'BIRT',
                    'label'     => 'Birth',
                    'date'      => '',
                    'place'     => 'Berlin, Germany',
                    'placeFull' => 'Berlin, Germany',
                    'value'     => '',
                    'partner'   => '',
                    'sortDay'   => 0,
                ],
                null,
                [
                    'tag'       => 'OCCU',
                    'label'     => 'Occupation',
                    'date'      => '',
                    'place'     => '',
                    'placeFull' => '',
                    'value'     => 'Smith',
                    'partner'   => '',
                    'sortDay'   => 0,
                ],
                null,
            ],
            $this->serialisedFacts($this->memberAt($members, 0))
        );

        self::assertSame([null, null, null, null], $this->serialisedFacts($this->memberAt($members, 1)));
    }

    /**
     * A tree that names no tag for the chart box has the birth and the death
     * slot only.
     */
    #[Test]
    public function hasTheBirthAndDeathSlotOnlyWithoutChartBoxTags(): void
    {
        $person = $this->createPerson(
            'I1',
            'M',
            [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')],
            factsByTag: [],
            chartBoxTags: '',
        );

        $data = $this->memberAt($this->buildStructure($person, ['generations' => '1', 'showDetailPanel' => '1'])['members'], 0);

        self::assertSame([null, null], $this->serialisedFacts($data));
    }

    /**
     * A facade that gets a new configuration reads the facts of a later run with the tags of the
     * tree of that run, so the slots follow the chart box tags of the tree and not those of the
     * run before.
     */
    #[Test]
    public function readsTheFactsOfALaterRunWithTheTagsOfItsOwnTree(): void
    {
        $name   = [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')];
        $module = self::createStubForIntersectionOfInterfaces([ModuleCustomInterface::class, ModuleAssetUrlInterface::class]);
        $module->method('name')->willReturn('webtrees-descendants-chart');

        $facade = (new DataFacade())->setModule($module)->setRoute('module');
        $slots  = [];

        foreach (['OCCU,RESI', ''] as $tags) {
            $person  = $this->createPerson('I1', 'M', $name, factsByTag: [], chartBoxTags: $tags);
            $request = (new ServerRequest('GET', '/'))->withQueryParams(['generations' => '1', 'showDetailPanel' => '1']);

            $structure = $facade
                ->setConfiguration(new Configuration($request, $this->createModuleWithPreferences([])))
                ->createTreeStructure($person);

            self::assertIsArray($structure);

            $slots[] = count($this->serialisedFacts($this->memberAt($structure['members'], 0)) ?? []);
        }

        self::assertSame([4, 2], $slots);
    }

    /**
     * Builds a fact with the given label, place and value, and no date.
     */
    private function createFact(string $label, string $placeFull, string $placeShort, string $value): Fact
    {
        $place = self::createStub(Place::class);
        $place->method('gedcomName')->willReturn($placeFull);
        $place->method('firstParts')->willReturn(new Collection($placeShort === '' ? [] : [$placeShort]));

        $fact = self::createStub(Fact::class);
        $fact->method('label')->willReturn($label);
        $fact->method('date')->willReturn(new Date(''));
        $fact->method('place')->willReturn($place);
        $fact->method('value')->willReturn($value);

        return $fact;
    }

    /**
     * Returns the facts that the browser receives for a node, or null when the
     * payload carries none.
     *
     * @return list<array<string, int|string>|null>|null
     */
    private function serialisedFacts(NodeData $data): ?array
    {
        /** @var array{facts?: list<array<string, int|string>|null>} $serialised */
        $serialised = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $serialised['facts'] ?? null;
    }
}
