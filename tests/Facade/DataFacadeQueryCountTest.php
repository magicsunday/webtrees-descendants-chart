<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Facade;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Factories\CacheFactory;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use GuzzleHttp\Psr7\ServerRequest;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use MagicSunday\Webtrees\ModuleBase\Processor\ImageProcessor;
use MagicSunday\Webtrees\ModuleBase\Support\MediaPreloader;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function file_put_contents;
use function glob;
use function implode;
use function intdiv;
use function is_dir;
use function is_scalar;
use function ksort;
use function max;
use function mkdir;
use function preg_match;
use function rmdir;
use function sort;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Counts the database statements that one request of the chart sends for the media objects, over a
 * real imported tree, and compares the images of the chart with those that the core record
 * factories give without any preload. The tree is a complete descendant tree, so every person of
 * every generation has a partner and two children and the number of people doubles with each
 * generation. The media statements of a request have to grow with the number of generations and
 * not with the number of people, so the tests compare the request with the same request that has
 * the highlight images switched off and derive the bounds from the shape of the tree.
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
final class DataFacadeQueryCountTest extends TestCase
{
    /**
     * The number of generations of the tree and of the chart.
     */
    private const int DEPTH = 9;

    /**
     * The first number of the xrefs of the partners, which lie above the xrefs of the descendants.
     */
    private const int PARTNER_OFFSET = 100000;

    /**
     * The module of the chart, which the facade reads its settings and its routes from.
     */
    private Module $module;

    /**
     * The index directory that holds the media files of the tree, or an empty string while the test
     * has none.
     */
    private string $indexDirectory = '';

    /**
     * Boots webtrees on a real schema and registers the module of the chart with its route.
     */
    protected function setUp(): void
    {
        parent::setUp();

        RealTreeFixture::boot();

        DB::table('module')->insert(['module_name' => 'webtrees-descendants-chart']);

        $this->module = new Module(self::createStub(ChartService::class), new DataFacade());
        $this->module->setName('webtrees-descendants-chart');
        $this->module->boot();
    }

    /**
     * Drops the viewer and restores the minimal database of the other tests.
     */
    protected function tearDown(): void
    {
        Auth::logout();
        Session::clear();
        Site::$preferences = [];
        Registry::cache(new CacheFactory());

        $this->removeMediaFiles();
        RealTreeFixture::restoreMinimalDatabase();

        parent::tearDown();
    }

    /**
     * The media lookup of a request grows with the number of generations and not with the number of
     * people, for the administrator and for a visitor, with and without the spouses. The extra
     * statements of the media are those that the same request sends beyond the request that has the
     * highlight images switched off, so nothing but the media is counted.
     *
     * @param bool $visitor     Whether a visitor requests the chart and not the administrator
     * @param bool $hideSpouses Whether the chart hides the partners
     */
    #[Test]
    #[DataProvider('viewersAndSpouses')]
    public function mediaStatementsGrowWithTheGenerationsAndNotWithThePeople(bool $visitor, bool $hideSpouses): void
    {
        $tree = $this->importDescendants();

        if ($visitor) {
            Auth::logout();
        }

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '');
        $without = count($this->statements($tree, $hideSpouses));

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');
        $with = count($this->statements($tree, $hideSpouses));

        // Up to five statements for each chunk of a generation: the media objects, their pending
        // changes, the links, the families and the individuals that the links point to.
        $budget = 0;

        for ($generation = 1; $generation <= self::DEPTH; ++$generation) {
            $budget += 5 * intdiv((2 * (2 ** ($generation - 1))) + MediaPreloader::CHUNK_SIZE - 1, MediaPreloader::CHUNK_SIZE);
        }

        self::assertGreaterThan(0, $with - $without, 'The media of the chart are looked up.');
        self::assertLessThanOrEqual($budget, $with - $without);
        self::assertLessThan(2 ** self::DEPTH, $with - $without);
    }

    /**
     * No media statement binds as many values as a whole generation holds.
     *
     * @param bool $visitor Whether a visitor requests the chart and not the administrator
     */
    #[Test]
    #[DataProvider('viewers')]
    public function noMediaStatementBindsAWholeGeneration(bool $visitor): void
    {
        $tree = $this->importDescendants();

        // The last generation with its partners holds more people than one chunk, so a lookup that
        // sends a whole generation in one statement would exceed the chunk size.
        $families = [];

        for ($family = 2 ** (self::DEPTH - 2); $family < (2 ** (self::DEPTH - 1)); ++$family) {
            $families[] = 'F' . $family;
        }

        $generationWidth = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->where('l_type', '=', 'FAMS')
            ->whereIn('l_to', $families)
            ->count();

        self::assertGreaterThan(MediaPreloader::CHUNK_SIZE, $generationWidth);

        if ($visitor) {
            Auth::logout();
        }

        $widest = 0;

        foreach ($this->statements($tree, false) as $statement) {
            if (
                str_contains($statement['query'], '"wt_media"')
                || str_contains($statement['query'], '"wt_link"')
            ) {
                $widest = max($widest, count($statement['bindings']));
            }
        }

        self::assertGreaterThan(0, $widest);
        // A chunk of xrefs and the id of the tree.
        self::assertLessThanOrEqual(MediaPreloader::CHUNK_SIZE + 1, $widest);
    }

    /**
     * A tree without any media line adds no statement for the media, because a person without a
     * media line is skipped before the preload asks whether the viewer may see the person. The check
     * for a person reads its families, so a visitor of a tree that hides living people would pay for
     * it otherwise.
     *
     * @param bool $visitor Whether a visitor requests the chart and not the administrator
     */
    #[Test]
    #[DataProvider('viewers')]
    public function peopleWithoutMediaLinesCostNoStatement(bool $visitor): void
    {
        $tree = $this->importDescendants(false);

        if ($visitor) {
            Auth::logout();
        }

        // A first request fills the settings cache of the module, which the later ones read from.
        $this->statements($tree, false);

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '');
        $without = $this->statements($tree, false);

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');
        $with = $this->statements($tree, false);

        self::assertSame($without, $with);
        self::assertSame([], $this->boundMediaXrefs($with));
    }

    /**
     * The images of the chart are those that the highlight image of the person yields without any
     * preload, for the administrator and for a visitor. Half of the people have the file of their
     * image on disk, so the chart shows a thumbnail for them and the silhouette for the others.
     *
     * @param bool $visitor Whether a visitor requests the chart and not the administrator
     */
    #[Test]
    #[DataProvider('viewers')]
    public function theImagesOfTheChartMatchTheHighlightImageOfEveryPerson(bool $visitor): void
    {
        $tree = $this->importDescendants();

        if ($visitor) {
            Auth::logout();
        }

        $images = $this->imagesOfChart($tree, 'I1', 4);

        // Every person of the four generations, partners included, is in the chart.
        self::assertCount(15 + 15, $images);
        self::assertSame($this->imagesOfFactories($tree, array_keys($images)), $images);

        $thumbnails = array_filter($images, static fn (array $image): bool => str_contains($image['thumbnail'], 'media-thumbnail'));

        self::assertNotSame([], $thumbnails, 'Some people show a thumbnail.');
        self::assertLessThan(count($images), count($thumbnails), 'Some people show the silhouette.');
    }

    /**
     * A visitor and a member who is no editor see no media object that is linked to a person they may
     * not see, and the images of the chart are those that the core gives without the preload. The
     * administrator sees every one of them, which shows that the media are in the fixture.
     *
     * @param string              $viewer   The viewer, one of administrator, visitor and member
     * @param array<string, bool> $expected Whether the viewer sees the thumbnail of the person
     */
    #[Test]
    #[DataProvider('privacyViewers')]
    public function mediaObjectsOfHiddenPeopleStayHidden(string $viewer, array $expected): void
    {
        $tree = $this->importPrivacyTree();

        if ($viewer === 'visitor') {
            Auth::logout();
        }

        if ($viewer === 'member') {
            $member = (new UserService())->create('member', 'Member', 'member@example.test', 'secret');
            $tree->setUserPreference($member, UserInterface::PREF_TREE_ROLE, UserInterface::ROLE_MEMBER);
            Auth::login($member);
        }

        $images = $this->imagesOfChart($tree, 'I1', 2);

        self::assertSame($this->imagesOfFactories($tree, array_keys($images)), $images);

        $shown = [];

        foreach ($images as $xref => $image) {
            $shown[$xref] = str_contains($image['thumbnail'], 'media-thumbnail');
        }

        foreach ($expected as $xref => $visible) {
            self::assertArrayHasKey($xref, $shown);
            self::assertSame($visible, $shown[$xref], $viewer . ' and ' . $xref);
        }
    }

    /**
     * The preload binds the media objects of the generations that the chart shows and of nothing
     * beyond them, although the tree is deeper. The partners belong to a generation unless the chart
     * hides them, so their media objects are bound in the one case and are absent in the other.
     *
     * @param bool $hideSpouses Whether the chart hides the partners
     */
    #[Test]
    #[DataProvider('spouseModes')]
    public function theMediaOfTheShownGenerationsAreLoadedAndNothingBeyond(bool $hideSpouses): void
    {
        $tree = $this->importDescendants();

        $expected = [];

        // Four generations of a complete tree hold the people 1 to 15.
        for ($person = 1; $person <= 15; ++$person) {
            $expected[] = 'M' . $person;

            if (!$hideSpouses) {
                $expected[] = 'M' . (self::PARTNER_OFFSET + $person);
            }
        }

        sort($expected);

        self::assertSame($expected, $this->boundMediaXrefs($this->statements($tree, $hideSpouses, 'I1', 4)));
    }

    /**
     * The descendant line decides which children the preload follows, as it does for the walk. The
     * paternal line follows a son of a father only, the maternal line follows every child of a mother,
     * and the unrestricted line follows everybody.
     *
     * @param string       $line     The descendant line of the chart
     * @param string       $root     The xref of the chart subject
     * @param list<string> $expected The media objects that the request binds
     */
    #[Test]
    #[DataProvider('descendantLines')]
    public function theDescendantLineDecidesWhichChildrenAreLoaded(string $line, string $root, array $expected): void
    {
        $tree = $this->importLineage();

        sort($expected);

        self::assertSame($expected, $this->boundMediaXrefs($this->statements($tree, false, $root, 3, $line)));
    }

    /**
     * Pairs every viewer with the state of the hidden spouses.
     *
     * @return array<string, array{bool, bool}>
     */
    public static function viewersAndSpouses(): array
    {
        return [
            'administrator, spouses shown'  => [false, false],
            'administrator, spouses hidden' => [false, true],
            'visitor, spouses shown'        => [true, false],
            'visitor, spouses hidden'       => [true, true],
        ];
    }

    /**
     * Lists the states of the hidden spouses.
     *
     * @return array<string, array{bool}>
     */
    public static function spouseModes(): array
    {
        return [
            'spouses shown'  => [false],
            'spouses hidden' => [true],
        ];
    }

    /**
     * Lists the descendant lines with the media objects of the people that the preload follows in the
     * lineage tree. The lines of the people are named in importLineage().
     *
     * @return array<string, array{string, string, list<string>}>
     */
    public static function descendantLines(): array
    {
        return [
            'all'      => ['all', 'I1', ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8', 'M9', 'M10', 'M13', 'M14']],
            'paternal' => ['paternal', 'I1', ['M1', 'M2', 'M3', 'M5', 'M7']],
            'maternal' => ['maternal', 'I4', ['M4', 'M8', 'M9', 'M10', 'M12', 'M13', 'M14']],
        ];
    }

    /**
     * Lists the viewers.
     *
     * @return array<string, array{bool}>
     */
    public static function viewers(): array
    {
        return [
            'administrator' => [false],
            'visitor'       => [true],
        ];
    }

    /**
     * Lists the viewers of the privacy tree with the people whose thumbnail they see. I1 is a
     * public person with a media object of their own, I3 is public with a media object that only
     * public people link, I7 is public but its media object is also linked to a confidential
     * person, I4 is living (a member sees living people) and I5 is confidential.
     *
     * @return array<string, array{string, array<string, bool>}>
     */
    public static function privacyViewers(): array
    {
        return [
            'administrator' => ['administrator', ['I1' => true, 'I3' => true, 'I7' => true, 'I4' => true, 'I5' => true]],
            'visitor'       => ['visitor', ['I1' => true, 'I3' => true, 'I7' => false, 'I4' => false, 'I5' => false]],
            'member'        => ['member', ['I1' => true, 'I3' => true, 'I7' => false, 'I4' => true, 'I5' => false]],
        ];
    }

    /**
     * Imports the complete descendant tree. The person n has the partner n+PARTNER_OFFSET and the
     * children 2n and 2n+1, and every person and every partner has a media object of their own. The
     * file of the image exists for the people with an odd number only. Everybody is dead and so
     * public, but the tree hides living people, so the visibility of a media object depends on the
     * records that it is linked to.
     *
     * @param bool $withMedia Whether the people have media lines
     */
    private function importDescendants(bool $withMedia = true): Tree
    {
        $people   = (2 ** self::DEPTH) - 1;
        $families = (2 ** (self::DEPTH - 1)) - 1;

        $lines = ['0 HEAD', '1 CHAR UTF-8'];

        for ($person = 1; $person <= $people; ++$person) {
            $lines = [...$lines, ...$this->personLines('I' . $person, 'M', $person, $withMedia)];

            if ($person >= 2) {
                $lines[] = '1 FAMC @F' . intdiv($person, 2) . '@';
            }

            if ($person <= $families) {
                $lines[] = '1 FAMS @F' . $person . '@';
            }
        }

        for ($family = 1; $family <= $families; ++$family) {
            $partner = self::PARTNER_OFFSET + $family;
            $lines   = [...$lines, ...$this->personLines('I' . $partner, 'F', $partner, $withMedia), '1 FAMS @F' . $family . '@'];
        }

        for ($family = 1; $family <= $families; ++$family) {
            $lines[] = '0 @F' . $family . '@ FAM';
            $lines[] = '1 HUSB @I' . $family . '@';
            $lines[] = '1 WIFE @I' . (self::PARTNER_OFFSET + $family) . '@';
            $lines[] = '1 CHIL @I' . (2 * $family) . '@';
            $lines[] = '1 CHIL @I' . ((2 * $family) + 1) . '@';
        }

        $numbers = [];

        for ($person = 1; $person <= $people; ++$person) {
            $numbers[] = $person;
        }

        for ($family = 1; $family <= $families; ++$family) {
            $numbers[] = self::PARTNER_OFFSET + $family;
        }

        foreach ($numbers as $number) {
            $lines[] = '0 @M' . $number . '@ OBJE';
            $lines[] = '1 FILE person' . $number . '.jpg';
            $lines[] = '2 FORM jpg';
        }

        $this->createMediaFiles($numbers);

        $lines[] = '0 TRLR';

        $tree = RealTreeFixture::importGedcom(implode("\n", $lines), 'descendants');

        $tree->setPreference('HIDE_LIVE_PEOPLE', '1');
        $tree->setPreference('SHOW_LIVING_NAMES', (string) Auth::PRIV_PRIVATE);
        $tree->setPreference('SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');

        return $tree;
    }

    /**
     * Returns the record lines of a deceased person.
     *
     * @param string $xref      The xref of the person
     * @param string $sex       The sex of the person
     * @param int    $number    The number of the person
     * @param bool   $withMedia Whether the person has a media line
     *
     * @return list<string>
     */
    private function personLines(string $xref, string $sex, int $number, bool $withMedia): array
    {
        $lines = [
            '0 @' . $xref . '@ INDI',
            '1 NAME Given' . $number . ' /Family' . ($number % 7) . '/',
            '1 SEX ' . $sex,
            '1 BIRT',
            '2 DATE ' . (1500 + ($number % 400)),
            '1 DEAT',
            '2 DATE ' . (1950 + ($number % 40)),
        ];

        if ($withMedia) {
            $lines[] = '1 OBJE @M' . $number . '@';
        }

        return $lines;
    }

    /**
     * Imports a small tree in which the media objects are linked to people that a visitor may not
     * see. I1 and I2 are a public couple. Their children are I3 (public, a media object that only
     * public people link), I4 (living), I5 (confidential) and I7 (public, but its media object is
     * also linked to the confidential I6, who is not in the chart).
     */
    private function importPrivacyTree(): Tree
    {
        $lines = ['0 HEAD', '1 CHAR UTF-8'];

        $people = [
            'I1' => ['M', 'M1', 'FAMS @F1@'],
            'I2' => ['F', '', 'FAMS @F1@'],
            'I3' => ['M', 'M3', 'FAMC @F1@'],
            'I4' => ['M', 'M5', 'FAMC @F1@'],
            'I5' => ['M', 'M7', 'FAMC @F1@'],
            'I6' => ['M', 'M9', ''],
            'I7' => ['M', 'M9', 'FAMC @F1@'],
        ];

        foreach ($people as $xref => [$sex, $media, $family]) {
            $living = $xref === 'I4';

            $lines[] = '0 @' . $xref . '@ INDI';
            $lines[] = '1 NAME Given' . $xref . ' /Family/';
            $lines[] = '1 SEX ' . $sex;
            $lines[] = '1 BIRT';
            $lines[] = '2 DATE ' . ($living ? '1990' : '1850');

            if (!$living) {
                $lines[] = '1 DEAT';
                $lines[] = '2 DATE 1920';
            }

            if (
                ($xref === 'I5')
                || ($xref === 'I6')
            ) {
                $lines[] = '1 RESN confidential';
            }

            if ($media !== '') {
                $lines[] = '1 OBJE @' . $media . '@';
            }

            if ($family !== '') {
                $lines[] = '1 ' . $family;
            }
        }

        $lines = [
            ...$lines,
            '0 @F1@ FAM',
            '1 HUSB @I1@',
            '1 WIFE @I2@',
            '1 CHIL @I3@',
            '1 CHIL @I4@',
            '1 CHIL @I5@',
            '1 CHIL @I7@',
        ];

        $numbers = [1, 3, 5, 7, 9];

        foreach ($numbers as $number) {
            $lines[] = '0 @M' . $number . '@ OBJE';
            $lines[] = '1 FILE person' . $number . '.jpg';
            $lines[] = '2 FORM jpg';
        }

        $this->createMediaFiles($numbers);

        $lines[] = '0 TRLR';

        $tree = RealTreeFixture::importGedcom(implode("\n", $lines), 'privacy');

        $tree->setPreference('HIDE_LIVE_PEOPLE', '1');
        $tree->setPreference('SHOW_LIVING_NAMES', (string) Auth::PRIV_PRIVATE);
        $tree->setPreference('SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');

        return $tree;
    }

    /**
     * Returns the sorted xrefs of the media objects that the media statements of a request bind. A
     * request that reads a media object by itself binds its xref in a statement of its own, so this
     * is the exact set of the media objects that the request looks up.
     *
     * @param list<array{query: string, bindings: list<string>}> $statements The statements of a request
     *
     * @return list<string>
     */
    private function boundMediaXrefs(array $statements): array
    {
        $xrefs = [];

        foreach ($statements as $statement) {
            if (!str_contains($statement['query'], 'from "wt_media"')) {
                continue;
            }

            foreach ($statement['bindings'] as $binding) {
                if (preg_match('/^M\d+$/', $binding) === 1) {
                    $xrefs[] = $binding;
                }
            }
        }

        $xrefs = array_values(array_unique($xrefs));

        sort($xrefs);

        return $xrefs;
    }

    /**
     * Imports a tree of three generations in which both sexes occur among the children. I1 (M) and
     * I2 (F) have the children I3 (M) and I4 (F). I3 and I7 (F) have the children I5 (M) and I6 (F).
     * I8 (M) and I4 have the children I9 (M) and I10 (F). I9 and I13 (F) have the child I11 (F), I14
     * (M) and I10 have the child I12 (M). Every person has a media object M plus the number.
     */
    private function importLineage(): Tree
    {
        $people = [
            1  => ['M', null, 1],
            2  => ['F', null, 1],
            3  => ['M', 1, 2],
            4  => ['F', 1, 3],
            5  => ['M', 2, null],
            6  => ['F', 2, null],
            7  => ['F', null, 2],
            8  => ['M', null, 3],
            9  => ['M', 3, 4],
            10 => ['F', 3, 5],
            11 => ['F', 4, null],
            12 => ['M', 5, null],
            13 => ['F', null, 4],
            14 => ['M', null, 5],
        ];

        $lines = ['0 HEAD', '1 CHAR UTF-8'];

        foreach ($people as $number => [$sex, $childOf, $spouseIn]) {
            $lines = [...$lines, ...$this->personLines('I' . $number, $sex, $number, true)];

            if ($childOf !== null) {
                $lines[] = '1 FAMC @F' . $childOf . '@';
            }

            if ($spouseIn !== null) {
                $lines[] = '1 FAMS @F' . $spouseIn . '@';
            }
        }

        $families = [
            1 => ['I1', 'I2', ['I3', 'I4']],
            2 => ['I3', 'I7', ['I5', 'I6']],
            3 => ['I8', 'I4', ['I9', 'I10']],
            4 => ['I9', 'I13', ['I11']],
            5 => ['I14', 'I10', ['I12']],
        ];

        foreach ($families as $number => [$husband, $wife, $children]) {
            $lines[] = '0 @F' . $number . '@ FAM';
            $lines[] = '1 HUSB @' . $husband . '@';
            $lines[] = '1 WIFE @' . $wife . '@';

            foreach ($children as $child) {
                $lines[] = '1 CHIL @' . $child . '@';
            }
        }

        foreach (array_keys($people) as $number) {
            $lines[] = '0 @M' . $number . '@ OBJE';
            $lines[] = '1 FILE person' . $number . '.jpg';
            $lines[] = '2 FORM jpg';
        }

        $lines[] = '0 TRLR';

        $tree = RealTreeFixture::importGedcom(implode("\n", $lines), 'lineage');

        $tree->setPreference('HIDE_LIVE_PEOPLE', '1');
        $tree->setPreference('SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');

        return $tree;
    }

    /**
     * Creates the files of the images of the odd numbers, in the media folder of a data directory
     * that the tree reads. The other media objects have a missing file.
     *
     * @param list<int> $numbers The numbers of the media objects
     */
    private function createMediaFiles(array $numbers): void
    {
        $this->indexDirectory = sys_get_temp_dir() . '/descendants-chart-' . uniqid('', true) . '/';

        if (
            !mkdir($this->indexDirectory . 'media', 0o777, true)
            && !is_dir($this->indexDirectory . 'media')
        ) {
            self::fail('The media directory could not be created.');
        }

        foreach ($numbers as $number) {
            if ($number % 2 === 1) {
                file_put_contents($this->indexDirectory . 'media/person' . $number . '.jpg', 'image');
            }
        }

        Site::setPreference('INDEX_DIRECTORY', $this->indexDirectory);
    }

    /**
     * Removes the media files and the directories of the test.
     */
    private function removeMediaFiles(): void
    {
        if ($this->indexDirectory === '') {
            return;
        }

        $files = glob($this->indexDirectory . 'media/*');

        foreach (($files === false) ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->indexDirectory . 'media');
        rmdir($this->indexDirectory);

        $this->indexDirectory = '';
    }

    /**
     * Returns the statements of one request on a cold record cache.
     *
     * @param Tree   $tree        The imported tree
     * @param bool   $hideSpouses Whether the chart hides the partners
     * @param string $xref        The xref of the chart subject
     * @param int    $generations The number of generations of the chart
     * @param string $line        The descendant line of the chart
     *
     * @return list<array{query: string, bindings: list<string>}>
     */
    private function statements(
        Tree $tree,
        bool $hideSpouses,
        string $xref = 'I1',
        int $generations = self::DEPTH,
        string $line = 'all',
    ): array {
        Registry::cache(new CacheFactory());

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $this->structure($tree, $xref, $generations, $hideSpouses, $line);

        $log = $connection->getQueryLog();
        $connection->disableQueryLog();

        $statements = [];

        foreach ($log as $entry) {
            $bindings = [];

            foreach ($entry['bindings'] as $binding) {
                $bindings[] = is_scalar($binding) ? (string) $binding : '';
            }

            $statements[] = [
                'query'    => $entry['query'],
                'bindings' => $bindings,
            ];
        }

        return $statements;
    }

    /**
     * Builds the tree structure of the chart for the current viewer.
     *
     * @param Tree   $tree        The imported tree
     * @param string $xref        The xref of the chart subject
     * @param int    $generations The number of generations of the chart
     * @param bool   $hideSpouses Whether the chart hides the partners
     * @param string $line        The descendant line of the chart
     *
     * @return array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>}
     */
    private function structure(Tree $tree, string $xref, int $generations, bool $hideSpouses, string $line = 'all'): array
    {
        $individual = Registry::individualFactory()->make($xref, $tree);
        self::assertInstanceOf(Individual::class, $individual);

        $configuration = new Configuration(
            (new ServerRequest(RequestMethodInterface::METHOD_GET, '/'))->withQueryParams([
                'generations'     => (string) $generations,
                'showDetailPanel' => '0',
                'hideSpouses'     => $hideSpouses ? '1' : '0',
                'descendantLine'  => $line,
            ]),
            $this->module
        );

        $structure = (new DataFacade())
            ->setModule($this->module)
            ->setConfiguration($configuration)
            ->setRoute(Module::ROUTE_DEFAULT)
            ->createTreeStructure($individual);

        self::assertIsArray($structure);

        return $structure;
    }

    /**
     * Returns the images of the people of the chart, by xref, on a cold record cache.
     *
     * @param Tree   $tree        The imported tree
     * @param string $xref        The xref of the chart subject
     * @param int    $generations The number of generations of the chart
     *
     * @return array<string, array{thumbnail: string, silhouette: string}>
     */
    private function imagesOfChart(Tree $tree, string $xref, int $generations): array
    {
        Registry::cache(new CacheFactory());

        $images = $this->collectImages($this->structure($tree, $xref, $generations, false));

        ksort($images);

        return $images;
    }

    /**
     * Collects the images of the members of a couple and of the couples below it.
     *
     * @param array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>} $couple The couple structure
     *
     * @return array<string, array{thumbnail: string, silhouette: string}>
     */
    private function collectImages(array $couple): array
    {
        $images = [];

        foreach ($couple['members'] as $member) {
            $data = $member->jsonSerialize();

            self::assertIsString($data['xref'] ?? null);
            self::assertIsString($data['thumbnail'] ?? null);
            self::assertIsString($data['silhouette'] ?? null);

            $images[$data['xref']] = [
                'thumbnail'  => $data['thumbnail'],
                'silhouette' => $data['silhouette'],
            ];
        }

        foreach ($couple['memberFamilies'] as $family) {
            foreach ($family['children'] as $child) {
                $images = [...$images, ...$this->collectImages($child->jsonSerialize())];
            }
        }

        return $images;
    }

    /**
     * Returns the images that the highlight image of each person yields through the core record
     * factories without any preload, on a cold record cache.
     *
     * @param Tree         $tree  The imported tree
     * @param list<string> $xrefs The xrefs of the people
     *
     * @return array<string, array{thumbnail: string, silhouette: string}>
     */
    private function imagesOfFactories(Tree $tree, array $xrefs): array
    {
        Registry::cache(new CacheFactory());

        $images = [];

        foreach ($xrefs as $xref) {
            $individual = Registry::individualFactory()->make((string) $xref, $tree);
            self::assertInstanceOf(Individual::class, $individual);

            $processor = new ImageProcessor($this->module, $individual);

            $images[(string) $xref] = [
                'thumbnail'  => $processor->getHighlightImageUrl(),
                'silhouette' => $processor->getSilhouetteUrl(),
            ];
        }

        ksort($images);

        return $images;
    }
}
