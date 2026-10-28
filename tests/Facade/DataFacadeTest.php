<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Facade;

use Aura\Router\Map;
use Aura\Router\Route;
use Fisharebest\Webtrees\Contracts\ContainerInterface;
use Fisharebest\Webtrees\Contracts\RouteFactoryInterface;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factories\CalendarDateFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\EnglishLocaleFixture;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\ModuleSettingsFixtureTrait;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Model\NameSegment;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function http_build_query;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Validates how the facade fills the name of a person into the node data: the
 * ordered name segments, the flat full name that tooltips and text alternatives
 * read, and the married surname in every married-names mode.
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
final class DataFacadeTest extends TestCase
{
    use ModuleSettingsFixtureTrait;

    /**
     * The formatted birth name of the person the married-name tests start from.
     */
    private const string ANNA_SCHMIDT = '<span class="NAME" dir="auto" translate="no">Anna <span class="SURN">Schmidt</span></span>';

    /**
     * Prepares the router and translation context the facade needs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Validator::serverParams(new ServerRequest('GET', '/'));
        Registry::calendarDateFactory(new CalendarDateFactory());

        EnglishLocaleFixture::install();

        $this->registerRouting();
    }

    /**
     * A person is described by its name segments in display order, with the
     * preferred given name marked and a particle kept with the given names.
     */
    #[Test]
    public function nodeDataCarriesTheOrderedNameSegments(): void
    {
        $person = $this->createPerson(
            'I1',
            'M',
            [
                $this->createName(
                    '<span class="NAME" dir="auto" translate="no">José <span class="starredname">Antonio</span> von <span class="SURN">Gómez</span> <span class="SURN">Iglesias</span></span>',
                    'José Antonio von Gómez Iglesias',
                ),
            ],
        );

        $data = $this->buildRoot($person, 'off');

        self::assertSame(
            [
                ['José', 'given', false],
                ['Antonio', 'given', true],
                ['von', 'given', false],
                ['Gómez', 'surname', false],
                ['Iglesias', 'surname', false],
            ],
            $this->describeSegments($data),
        );

        self::assertSame('José Antonio von Gómez Iglesias', $this->valueOf($data, 'name'));
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
     * The serialised node data lists its keys in the fixed order of the shared
     * contract, with the name segments where the name token lists used to be.
     */
    #[Test]
    public function serialisedNodeDataKeepsTheSharedKeyOrder(): void
    {
        $data = $this->buildRoot($this->createPerson('I1', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]), 'off');

        self::assertSame(
            [
                'id',
                'xref',
                'url',
                'updateUrl',
                'generation',
                'name',
                'isNameRtl',
                'nameSegments',
                'alternativeName',
                'isAltRtl',
                'thumbnail',
                'silhouette',
                'sex',
                'birth',
                'death',
                'timespan',
            ],
            array_keys($data->jsonSerialize()),
        );
    }

    /**
     * Without a married name record the birth name stands alone in every mode.
     */
    #[Test]
    public function addsNoMarriedSegmentWithoutAMarriedNameRecord(): void
    {
        $person = $this->createPerson('I1', 'F', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);

        $data = $this->buildRoot($person, 'birth_and_married');

        self::assertSame([['Anna', 'given', false], ['Schmidt', 'surname', false]], $this->describeSegments($data));
        self::assertSame('Anna Schmidt', $this->valueOf($data, 'name'));
    }

    /**
     * The birth-name mode leaves the married name record unused.
     */
    #[Test]
    public function birthNameModeIgnoresTheMarriedNameRecord(): void
    {
        $data = $this->buildRoot($this->createMarriedPerson(), 'off');

        self::assertSame([['Anna', 'given', false], ['Schmidt', 'surname', false]], $this->describeSegments($data));
        self::assertSame('Anna Schmidt', $this->valueOf($data, 'name'));
    }

    /**
     * The married-name-only mode draws the married name in place of the birth name.
     */
    #[Test]
    public function marriedNameOnlyModeDrawsTheMarriedNameInstead(): void
    {
        $data = $this->buildRoot($this->createMarriedPerson(), 'married_only');

        self::assertSame([['Anna', 'given', false], ['Müller', 'surname', false]], $this->describeSegments($data));
        self::assertSame('Anna Müller', $this->valueOf($data, 'name'));
    }

    /**
     * The birth-and-married mode closes the segment list with the bracketed
     * married surname and puts the same bracketed text at the end of the flat name.
     */
    #[Test]
    public function birthAndMarriedModeAppendsTheBracketedMarriedSurname(): void
    {
        $data = $this->buildRoot($this->createMarriedPerson(), 'birth_and_married');

        self::assertSame(
            [['Anna', 'given', false], ['Schmidt', 'surname', false], ['(Müller)', 'married', false]],
            $this->describeSegments($data),
        );
        self::assertSame('Anna Schmidt (Müller)', $this->valueOf($data, 'name'));
    }

    /**
     * The married surname of a spouse is the one that matches the other partner.
     */
    #[Test]
    public function marriedSurnameOfASpouseIsTheOneMatchingThePartner(): void
    {
        $husband = $this->createPerson(
            'I1',
            'M',
            [
                $this->createName(
                    '<span class="NAME" dir="auto" translate="no">Hans <span class="SURN">Müller</span></span>',
                    'Hans Müller',
                    'Müller',
                ),
            ],
        );
        $wife = $this->createPerson(
            'I2',
            'F',
            [
                $this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt', 'Schmidt'),
                $this->createName('', '', 'Meier', '_MARNM'),
                $this->createName('', '', 'Müller', '_MARNM'),
            ],
        );

        $members = $this->buildMembers($husband, 'birth_and_married', $wife);

        self::assertSame([['Hans', 'given', false], ['Müller', 'surname', false]], $this->describeSegments($this->memberAt($members, 0)));
        self::assertSame(
            [['Anna', 'given', false], ['Schmidt', 'surname', false], ['(Müller)', 'married', false]],
            $this->describeSegments($this->memberAt($members, 1)),
        );
        self::assertSame('Anna Schmidt (Müller)', $this->valueOf($this->memberAt($members, 1), 'name'));
    }

    /**
     * The nickname is drawn between the given names and the surname, and the
     * flat name carries it in quotes when the option is on.
     */
    #[Test]
    public function nicknameJoinsTheSegmentsWhenEnabled(): void
    {
        $person = $this->createPerson(
            'I1',
            'M',
            [$this->createName('<span class="NAME" dir="auto" translate="no">Martin <span class="SURN">White</span></span>', 'Martin White')],
            'Chalky',
        );

        $withNickname = $this->buildRoot($person, 'off', null, true);
        $without      = $this->buildRoot($person, 'off');

        self::assertSame(
            [['Martin', 'given', false], ['"Chalky"', 'nickname', false], ['White', 'surname', false]],
            $this->describeSegments($withNickname),
        );
        self::assertSame('Martin "Chalky" White', $this->valueOf($withNickname, 'name'));
        self::assertSame([['Martin', 'given', false], ['White', 'surname', false]], $this->describeSegments($without));
        self::assertSame('Martin White', $this->valueOf($without, 'name'));
    }

    /**
     * The nickname stays between the given names and the surnames when the
     * married surname is added as well.
     */
    #[Test]
    public function marriedSurnameClosesTheListAfterTheNickname(): void
    {
        $person = $this->createPerson(
            'I1',
            'F',
            [
                $this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt', 'Schmidt'),
                $this->createName('', '', 'Müller', '_MARNM'),
            ],
            'Annie',
        );

        $data = $this->buildRoot($person, 'birth_and_married', null, true);

        self::assertSame(
            [
                ['Anna', 'given', false],
                ['"Annie"', 'nickname', false],
                ['Schmidt', 'surname', false],
                ['(Müller)', 'married', false],
            ],
            $this->describeSegments($data),
        );
        self::assertSame('Anna "Annie" Schmidt (Müller)', $this->valueOf($data, 'name'));
    }

    /**
     * The direction flag of the name follows the flat name including the married
     * surname, and the alternative name keeps its own flat text.
     */
    #[Test]
    public function marksARightToLeftNameAndKeepsTheAlternativeNameFlat(): void
    {
        $person = $this->createPerson(
            'I1',
            'M',
            [
                $this->createName('<span class="NAME" dir="auto" translate="no">דוד <span class="SURN">כהן</span></span>', 'דוד כהן'),
                $this->createName('<span class="NAME" dir="auto" translate="no">David <span class="SURN">Cohen</span></span>', 'David Cohen'),
            ],
            '',
            1,
        );

        $data = $this->buildRoot($person, 'off');

        self::assertTrue($this->valueOf($data, 'isNameRtl'));
        self::assertSame('David Cohen', $this->valueOf($data, 'alternativeName'));
        self::assertFalse($this->valueOf($data, 'isAltRtl'));
    }

    /**
     * The chart subject is the first generation, every node gets its own
     * positive ID, and a person beyond the configured generation depth is left out.
     */
    #[Test]
    public function treeStopsAtTheConfiguredGenerationDepth(): void
    {
        $grandchild = $this->createPerson('I3', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $child      = $this->createPerson('I2', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $root       = $this->createPerson('I1', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);

        $child->method('spouseFamilies')->willReturn(new Collection([$this->createFamily(null, [$grandchild])]));
        $root->method('spouseFamilies')->willReturn(new Collection([$this->createFamily(null, [$child])]));

        $structure = $this->buildStructure($root, ['generations' => '2']);

        self::assertSame(1, $this->valueOf($this->memberAt($structure['members'], 0), 'generation'));
        self::assertSame(1, $this->valueOf($this->memberAt($structure['members'], 0), 'id'));

        $childCouple = $this->childrenOfFamily($structure, 0)[0] ?? null;

        self::assertInstanceOf(CoupleNode::class, $childCouple);

        $childData = $this->memberAt($childCouple->getMembers(), 0);

        self::assertSame('I2', $this->valueOf($childData, 'xref'));
        self::assertSame(2, $this->valueOf($childData, 'generation'));
        self::assertSame(2, $this->valueOf($childData, 'id'));
        self::assertSame([], $this->childrenOfFamily(['members' => $childCouple->getMembers(), 'memberFamilies' => $childCouple->getMemberFamilies()], 0));
    }

    /**
     * A family with a known partner stores the partner next to the person and
     * attaches the children of the family to that partner.
     */
    #[Test]
    public function familyWithAPartnerListsThePartnerAndTheChildren(): void
    {
        $partner = $this->createPerson('I2', 'F', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $first   = $this->createPerson('I3', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $second  = $this->createPerson('I4', 'F', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $root    = $this->createPerson('I1', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);

        $root->method('spouseFamilies')->willReturn(new Collection([$this->createFamily($partner, [$first, $second])]));

        $structure = $this->buildStructure($root, ['generations' => '2']);

        self::assertCount(2, $structure['members']);
        self::assertSame('I2', $this->valueOf($this->memberAt($structure['members'], 1), 'xref'));
        self::assertCount(1, $structure['memberFamilies']);
        self::assertSame(1, $this->spouseIndexOf($structure, 0));
        self::assertSame(['I3', 'I4'], $this->childXrefs($structure, 0));
    }

    /**
     * A single family without a recorded partner keeps the children directly
     * under the person and adds no empty partner.
     */
    #[Test]
    public function singleFamilyWithoutAPartnerAddsNoPartnerSlot(): void
    {
        $child = $this->createPerson('I2', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $root  = $this->createPerson('I1', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);

        $root->method('spouseFamilies')->willReturn(new Collection([$this->createFamily(null, [$child])]));

        $structure = $this->buildStructure($root, ['generations' => '2']);

        self::assertCount(1, $structure['members']);
        self::assertCount(1, $structure['memberFamilies']);
        self::assertNull($this->spouseIndexOf($structure, 0));
        self::assertSame(['I2'], $this->childXrefs($structure, 0));
    }

    /**
     * With several families each one gets a partner slot, also when the partner
     * of a family is not recorded.
     */
    #[Test]
    public function severalFamiliesWithoutPartnersEachGetAPartnerSlot(): void
    {
        $firstChild  = $this->createPerson('I2', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $secondChild = $this->createPerson('I3', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $root        = $this->createPerson('I1', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);

        $root->method('spouseFamilies')->willReturn(
            new Collection([
                $this->createFamily(null, [$firstChild]),
                $this->createFamily(null, [$secondChild]),
            ])
        );

        $structure = $this->buildStructure($root, ['generations' => '2']);

        self::assertCount(3, $structure['members']);
        self::assertSame(1, $this->spouseIndexOf($structure, 0));
        self::assertSame(2, $this->spouseIndexOf($structure, 1));
        self::assertSame(['I2'], $this->childXrefs($structure, 0));
        self::assertSame(['I3'], $this->childXrefs($structure, 1));
    }

    /**
     * Without spouses the children of all families are merged into one list,
     * ordered by their birth date.
     */
    #[Test]
    public function hiddenSpousesMergeTheChildrenAndOrderThemByBirthDate(): void
    {
        $name  = [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')];
        $young = $this->createPerson('I2', 'M', $name, '', 0, '1 JAN 1950');
        $old   = $this->createPerson('I3', 'M', $name, '', 0, '1 JAN 1900');
        $mid   = $this->createPerson('I4', 'M', $name, '', 0, '1 JAN 1925');
        $root  = $this->createPerson('I1', 'M', $name);

        $partner = $this->createPerson('I5', 'F', $name);

        $root->method('spouseFamilies')->willReturn(
            new Collection([
                $this->createFamily($partner, [$young, $old]),
                $this->createFamily(null, [$mid]),
            ])
        );

        $structure = $this->buildStructure($root, ['generations' => '2', 'hideSpouses' => '1']);

        self::assertCount(1, $structure['members']);
        self::assertCount(1, $structure['memberFamilies']);
        self::assertSame(0, $this->familyIndexOf($structure, 0));
        self::assertNull($this->spouseIndexOf($structure, 0));
        self::assertSame(['I3', 'I4', 'I2'], $this->childXrefs($structure, 0));
    }

    /**
     * A person without any family has no family entry when the spouses are
     * hidden either.
     */
    #[Test]
    public function personWithoutFamiliesHasNoFamilyEntries(): void
    {
        $root = $this->createPerson('I1', 'M', [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')]);
        $root->method('spouseFamilies')->willReturn(new Collection());

        self::assertSame([], $this->buildStructure($root, ['generations' => '2', 'hideSpouses' => '1'])['memberFamilies']);
        self::assertSame([], $this->buildStructure($root, ['generations' => '2', 'hideSpouses' => '0'])['memberFamilies']);
    }

    /**
     * Without a filter every child is kept, whatever its sex.
     */
    #[Test]
    public function allLinesKeepEveryDescendant(): void
    {
        $structure = $this->buildStructure($this->createMixedFamilyRoot('M'), ['generations' => '3']);

        self::assertSame(
            ['S1', 'D1', 'S2', 'U1', 'DC1', 'DC2', 'G1', 'G2'],
            $this->descendantXrefs($structure['memberFamilies']),
        );
    }

    /**
     * The paternal line follows father to son only, so a daughter and everything
     * below her are left out, and a person of unknown sex is never kept.
     */
    #[Test]
    public function paternalLineKeepsOnlyFathersToSons(): void
    {
        $structure = $this->buildStructure(
            $this->createMixedFamilyRoot('M'),
            ['generations' => '3', 'descendantLine' => 'paternal'],
        );

        self::assertSame(['S1', 'S2', 'G1'], $this->descendantXrefs($structure['memberFamilies']));
    }

    /**
     * The maternal line of a man ends at the man. His family entries stay and
     * none of his children is kept.
     */
    #[Test]
    public function maternalLineOfAMaleSubjectShowsTheSubjectAlone(): void
    {
        $structure = $this->buildStructure(
            $this->createMixedFamilyRoot('M'),
            ['generations' => '3', 'descendantLine' => 'maternal'],
        );

        self::assertCount(1, $structure['memberFamilies']);
        self::assertSame([], $this->descendantXrefs($structure['memberFamilies']));
    }

    /**
     * A woman passes the line to all her children, a son ends it, and a
     * daughter passes it on again.
     */
    #[Test]
    public function maternalLineOfAFemaleSubjectEndsAtTheSons(): void
    {
        $structure = $this->buildStructure(
            $this->createMixedFamilyRoot('F'),
            ['generations' => '3', 'descendantLine' => 'maternal'],
        );

        self::assertSame(['S1', 'D1', 'S2', 'U1', 'DC1', 'DC2'], $this->descendantXrefs($structure['memberFamilies']));
    }

    /**
     * The paternal line of a woman ends at the woman.
     */
    #[Test]
    public function paternalLineOfAFemaleSubjectShowsTheSubjectAlone(): void
    {
        $structure = $this->buildStructure(
            $this->createMixedFamilyRoot('F'),
            ['generations' => '3', 'descendantLine' => 'paternal'],
        );

        self::assertSame([], $this->descendantXrefs($structure['memberFamilies']));
    }

    /**
     * A filtered subject keeps its partner in every mode, only the children go.
     */
    #[Test]
    public function filterLeavesThePartnerOfTheSubjectInPlace(): void
    {
        $structure = $this->buildStructure(
            $this->createMixedFamilyRoot('M'),
            ['generations' => '3', 'descendantLine' => 'maternal'],
        );

        self::assertCount(2, $structure['members']);
        self::assertSame(1, $this->spouseIndexOf($structure, 0));
    }

    /**
     * Hidden spouses merge the children of all families before the order by birth
     * date, and the filter applies to the merged children as well.
     */
    #[Test]
    public function hiddenSpousesApplyTheFilterToTheMergedChildren(): void
    {
        $name = [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')];

        $youngSon     = $this->createLineMember('S1', 'M', [], '1 JAN 1950');
        $oldDaughter  = $this->createLineMember('D1', 'F', [], '1 JAN 1900');
        $middleSon    = $this->createLineMember('S2', 'M', [], '1 JAN 1925');
        $unknownChild = $this->createLineMember('U1', 'U', [], '1 JAN 1930');

        $root = $this->createPerson('I1', 'M', $name);
        $root->method('spouseFamilies')->willReturn(
            new Collection([
                $this->createFamily($this->createPerson('I5', 'F', $name), [$youngSon, $oldDaughter]),
                $this->createFamily(null, [$unknownChild, $middleSon]),
            ])
        );

        $paternal = $this->buildStructure($root, ['generations' => '2', 'hideSpouses' => '1', 'descendantLine' => 'paternal']);

        self::assertSame(['S2', 'S1'], $this->childXrefs($paternal, 0));

        $all = $this->buildStructure($root, ['generations' => '2', 'hideSpouses' => '1']);

        self::assertSame(['D1', 'S2', 'U1', 'S1'], $this->childXrefs($all, 0));
    }

    /**
     * Builds a subject with a partner and a mixed family: two sons, a daughter and
     * a child of unknown sex. The first son has no children, the second son has a
     * son and a daughter, and the daughter has a son and a daughter.
     *
     * @param string $sex The sex of the subject
     *
     * @return Individual&Stub
     */
    private function createMixedFamilyRoot(string $sex): Individual
    {
        $name = [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')];

        $secondSon = $this->createLineMember(
            'S2',
            'M',
            [$this->createFamily(null, [$this->createLineMember('G1', 'M'), $this->createLineMember('G2', 'F')])],
        );

        $daughter = $this->createLineMember(
            'D1',
            'F',
            [$this->createFamily(null, [$this->createLineMember('DC1', 'M'), $this->createLineMember('DC2', 'F')])],
        );

        $root = $this->createPerson('I1', $sex, $name);
        $root->method('spouseFamilies')->willReturn(
            new Collection([
                $this->createFamily(
                    $this->createPerson('I9', ($sex === 'M') ? 'F' : 'M', $name),
                    [
                        $this->createLineMember('S1', 'M'),
                        $daughter,
                        $secondSon,
                        $this->createLineMember('U1', 'U'),
                    ],
                ),
            ])
        );

        return $root;
    }

    /**
     * Creates a person of the descendant line tests with the given families.
     *
     * @param Family[] $families The spouse families of the person
     * @param string   $birth    The GEDCOM birth date of the person
     *
     * @return Individual&Stub
     */
    private function createLineMember(string $xref, string $sex, array $families = [], string $birth = ''): Individual
    {
        $person = $this->createPerson($xref, $sex, [$this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt')], '', 0, $birth);
        $person->method('spouseFamilies')->willReturn(new Collection($families));

        return $person;
    }

    /**
     * Returns the XREFs of all descendants in the order the chart draws them:
     * every child, followed by the descendants of that child.
     *
     * @param array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}> $families
     *
     * @return list<bool|int|string>
     */
    private function descendantXrefs(array $families): array
    {
        $children = [];
        $deeper   = [];

        foreach ($families as $entry) {
            foreach ($entry['children'] as $child) {
                $children[] = $this->valueOf($this->memberAt($child->getMembers(), 0), 'xref');
                $deeper[]   = $this->descendantXrefs($child->getMemberFamilies());
            }
        }

        // Breadth first per level keeps the expectation readable. Siblings come
        // first, then what hangs below them.
        return [...$children, ...array_merge(...$deeper)];
    }

    /**
     * Builds the chart of the person, with an optional partner, and returns the
     * node data of the members of the first couple.
     *
     * @param string $marriedNamesMode The married-names mode of the chart
     *
     * @return NodeData[]
     */
    private function buildMembers(
        Individual&Stub $person,
        string $marriedNamesMode,
        ?Individual $partner = null,
        bool $showNicknames = false,
    ): array {
        $family = self::createStub(Family::class);
        $family->method('children')->willReturn(new Collection());
        $family->method('spouse')->willReturn($partner);

        $person->method('spouseFamilies')->willReturn(
            ($partner instanceof Individual) ? new Collection([$family]) : new Collection()
        );

        return $this->buildStructure(
            $person,
            [
                'generations'      => '2',
                'marriedNamesMode' => $marriedNamesMode,
                'showNicknames'    => $showNicknames ? '1' : '0',
            ],
        )['members'];
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
     * Builds the chart of the person and returns the node data of the person itself.
     *
     * @param string $marriedNamesMode The married-names mode of the chart
     */
    private function buildRoot(
        Individual&Stub $person,
        string $marriedNamesMode,
        ?Individual $partner = null,
        bool $showNicknames = false,
    ): NodeData {
        return $this->memberAt($this->buildMembers($person, $marriedNamesMode, $partner, $showNicknames), 0);
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

    /**
     * Returns the label, kind and preferred flag of every name segment of a node.
     *
     * @return list<array{string, string, bool}>
     */
    private function describeSegments(NodeData $data): array
    {
        $segments = $data->jsonSerialize()['nameSegments'] ?? [];

        self::assertIsArray($segments);

        $described = [];

        foreach ($segments as $segment) {
            self::assertInstanceOf(NameSegment::class, $segment);

            $described[] = [$segment->label, $segment->kind->value, $segment->isPreferred];
        }

        return $described;
    }

    /**
     * Returns a serialised scalar value of a node, as the browser receives it.
     */
    private function valueOf(NodeData $data, string $key): bool|int|string
    {
        $value = $data->jsonSerialize()[$key] ?? '';

        self::assertIsNotArray($value);

        return $value;
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
     * Returns the child couples of the family entry at the given position.
     *
     * @param array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>} $structure
     *
     * @return CoupleNode[]
     */
    private function childrenOfFamily(array $structure, int $position): array
    {
        self::assertArrayHasKey($position, $structure['memberFamilies']);

        return $structure['memberFamilies'][$position]['children'];
    }

    /**
     * Returns the index of the partner of the family entry at the given position.
     *
     * @param array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>} $structure
     */
    private function spouseIndexOf(array $structure, int $position): ?int
    {
        self::assertArrayHasKey($position, $structure['memberFamilies']);

        return $structure['memberFamilies'][$position]['spouseIndex'];
    }

    /**
     * Returns the family index of the family entry at the given position.
     *
     * @param array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>} $structure
     */
    private function familyIndexOf(array $structure, int $position): int
    {
        self::assertArrayHasKey($position, $structure['memberFamilies']);

        return $structure['memberFamilies'][$position]['family'];
    }

    /**
     * Returns the XREFs of the children of the family entry at the given position.
     *
     * @param array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>} $structure
     *
     * @return list<bool|int|string>
     */
    private function childXrefs(array $structure, int $position): array
    {
        return array_values(array_map(
            fn (CoupleNode $couple): bool|int|string => $this->valueOf($this->memberAt($couple->getMembers(), 0), 'xref'),
            $this->childrenOfFamily($structure, $position),
        ));
    }

    /**
     * Creates a woman with a birth name and a married name record.
     *
     * @return Individual&Stub
     */
    private function createMarriedPerson(): Individual
    {
        return $this->createPerson(
            'I1',
            'F',
            [
                $this->createName(self::ANNA_SCHMIDT, 'Anna Schmidt', 'Schmidt'),
                $this->createName(
                    '<span class="NAME" dir="auto" translate="no">Anna <span class="SURN">Müller</span></span>',
                    'Anna Müller',
                    'Müller',
                    '_MARNM',
                ),
            ],
        );
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

    /**
     * Sets up the route factory and the container the facade needs for the update URL.
     */
    private function registerRouting(): void
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

        $container = new readonly class($routeFactory) implements ContainerInterface {
            public function __construct(private RouteFactoryInterface $routeFactory)
            {
            }

            public function get(string $id): RouteFactoryInterface
            {
                return $this->routeFactory;
            }

            public function has(string $id): bool
            {
                return $id === RouteFactoryInterface::class;
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
