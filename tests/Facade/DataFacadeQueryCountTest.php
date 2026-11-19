<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Facade;

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
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\RealTreeChart;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\RealTreeImporter;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use MagicSunday\Webtrees\ModuleBase\Processor\ImageProcessor;
use MagicSunday\Webtrees\ModuleBase\Support\MediaPreloader;
use MagicSunday\Webtrees\ModuleBase\Testing\MediaDirectory;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\StatementLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_keys;
use function array_replace;
use function array_unique;
use function array_values;
use function count;
use function intdiv;
use function ksort;
use function max;
use function preg_match;
use function sort;
use function str_contains;

/**
 * Counts the media statements of one chart request over an imported descendant tree and
 * compares the images of the chart with those that the image processor gives without the preload.
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
     * The chart of the module on the real schema.
     */
    private RealTreeChart $chart;

    /**
     * The media directory of the imported trees.
     */
    private MediaDirectory $mediaDirectory;

    /**
     * The importer of the trees that the tests run on.
     */
    private RealTreeImporter $importer;

    /**
     * Boots webtrees and registers the module of the chart with its route.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->chart          = RealTreeChart::boot(self::createStub(ChartService::class));
        $this->mediaDirectory = new MediaDirectory();
        $this->importer       = new RealTreeImporter($this->mediaDirectory);
    }

    /**
     * Drops the viewer and restores the minimal database.
     */
    protected function tearDown(): void
    {
        Auth::logout();
        Session::clear();
        Site::$preferences = [];
        Registry::cache(new CacheFactory());

        $this->mediaDirectory->remove();
        RealTreeFixture::restoreMinimalDatabase();

        parent::tearDown();
    }

    /**
     * Pins the statements that the highlight images add to one request. The administrator looks up
     * the media objects and their pending changes for each chunk of a generation. A visitor reads
     * the links of the media objects and the records that the links point to as well.
     *
     * @param bool $visitor     Whether a visitor requests the chart and not the administrator
     * @param bool $hideSpouses Whether the chart hides the partners
     */
    #[Test]
    #[DataProvider('viewersAndSpouses')]
    public function mediaStatementsGrowWithTheGenerationsAndNotWithThePeople(bool $visitor, bool $hideSpouses): void
    {
        $tree = $this->importer->descendants();

        if ($visitor) {
            Auth::logout();
        }

        // The first request of a run sends a few statements that later requests do not, so one request
        // goes unmeasured to keep the counts comparable.
        $this->mediaStatements($tree, RealTreeImporter::DEPTH, $hideSpouses);

        $extra = $this->mediaStatements($tree, RealTreeImporter::DEPTH, $hideSpouses);
        $fewer = $this->mediaStatements($tree, RealTreeImporter::DEPTH - 1, $hideSpouses);

        // The administrator pays for the media objects and their pending changes, a visitor sees no
        // pending changes but pays for the media objects, their links and the people and families
        // that the links point to.
        $statementsPerChunk = $visitor ? 4 : 2;

        self::assertSame(
            $statementsPerChunk * $this->chunks(RealTreeImporter::DEPTH, $hideSpouses),
            $extra['total'],
        );
        self::assertSame(
            $statementsPerChunk * $this->chunks(RealTreeImporter::DEPTH - 1, $hideSpouses),
            $fewer['total'],
        );

        // One more generation adds the statements of its own chunks and nothing else.
        self::assertSame(
            $statementsPerChunk * $this->chunksOfGeneration(RealTreeImporter::DEPTH, $hideSpouses),
            $extra['total'] - $fewer['total'],
        );

        // The pending changes are looked up for the administrator only.
        self::assertSame($visitor ? 0 : $this->chunks(RealTreeImporter::DEPTH, $hideSpouses), $extra['changes']);
        self::assertSame($visitor ? 0 : $this->chunks(RealTreeImporter::DEPTH - 1, $hideSpouses), $fewer['changes']);
        self::assertLessThan(2 ** RealTreeImporter::DEPTH, $extra['total']);
    }

    /**
     * Asserts that the media and link statements bind at most one chunk of values plus one.
     *
     * @param bool $visitor Whether a visitor requests the chart and not the administrator
     */
    #[Test]
    #[DataProvider('viewers')]
    public function noMediaStatementBindsAWholeGeneration(bool $visitor): void
    {
        $tree = $this->importer->descendants();

        // A generation of the tree exceeds the chunk size.
        $families = [];

        for ($family = 2 ** (RealTreeImporter::DEPTH - 2); $family < (2 ** (RealTreeImporter::DEPTH - 1)); ++$family) {
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
        // One value more than the chunk size is allowed.
        self::assertLessThanOrEqual(MediaPreloader::CHUNK_SIZE + 1, $widest);
    }

    /**
     * Compares the complete statement sequences with and without highlight images for a tree
     * without media lines.
     *
     * @param bool $visitor Whether a visitor requests the chart and not the administrator
     */
    #[Test]
    #[DataProvider('viewers')]
    public function peopleWithoutMediaLinesCostNoStatement(bool $visitor): void
    {
        $tree = $this->importer->descendants(false, true);

        if ($visitor) {
            Auth::logout();
        }

        // A warm-up request that is not measured.
        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '');
        $this->statements($tree, false);

        $without = $this->statements($tree, false);

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');
        $with = $this->statements($tree, false);

        self::assertSame($without, $with);
        self::assertSame([], $this->boundMediaXrefs($with));
    }

    /**
     * Compares the images of the chart with those that the image processor gives without the preload.
     *
     * @param bool $visitor Whether a visitor requests the chart and not the administrator
     */
    #[Test]
    #[DataProvider('viewers')]
    public function theImagesOfTheChartMatchTheHighlightImageOfEveryPersonOfTheChart(bool $visitor): void
    {
        $tree = $this->importer->descendants();

        if ($visitor) {
            Auth::logout();
        }

        $images = $this->imagesOfChart($tree, 'I1', 4);

        // The people of four generations and their partners.
        self::assertCount(15 + 15, $images);
        self::assertSame($this->imagesOfFactories($tree, array_keys($images)), $images);

        $thumbnails = array_filter(
            $images,
            static fn (array $image): bool => str_contains($image['thumbnail'], 'media-thumbnail'),
        );

        self::assertNotSame([], $thumbnails, 'Some people show a thumbnail.');
        self::assertLessThan(count($images), count($thumbnails), 'Some people show the silhouette.');
    }

    /**
     * Compares the images of the chart with those that the image processor gives without the
     * preload, and the thumbnail of each listed person with the expected value of the viewer.
     *
     * @param string              $viewer   The viewer, one of administrator, visitor and member
     * @param array<string, bool> $expected Whether the viewer sees the thumbnail of the person
     */
    #[Test]
    #[DataProvider('privacyViewers')]
    public function mediaObjectsOfHiddenPeopleStayHidden(string $viewer, array $expected): void
    {
        $tree = $this->importer->privacy();

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
     * Asserts the exact media objects that a request of four generations binds, with and
     * without the partners.
     *
     * @param bool $hideSpouses Whether the chart hides the partners
     */
    #[Test]
    #[DataProvider('spouseModes')]
    public function theMediaOfTheShownGenerationsAreLoadedAndNothingBeyond(bool $hideSpouses): void
    {
        $tree = $this->importer->descendants();

        $expected = [];

        // The people 1 to 15 and, while the partners are shown, their partners.
        for ($person = 1; $person <= 15; ++$person) {
            $expected[] = 'M' . $person;

            if (!$hideSpouses) {
                $expected[] = 'M' . (RealTreeImporter::PARTNER_OFFSET + $person);
            }
        }

        sort($expected);

        $statements = $this->statements($tree, $hideSpouses, 'I1', 4);

        self::assertSame($expected, $this->boundMediaXrefs($statements));
        $this->assertOnlyBatchedMediaLookups($statements);
    }

    /**
     * Asserts the exact media objects that a request binds for a descendant line and that each
     * media statement carries an in list.
     *
     * @param string       $line     The descendant line of the chart
     * @param string       $root     The xref of the chart subject
     * @param list<string> $expected The media objects that the request binds
     */
    #[Test]
    #[DataProvider('descendantLines')]
    public function theDescendantLineDecidesWhichChildrenAreLoaded(string $line, string $root, array $expected): void
    {
        $tree = $this->importer->lineage();

        sort($expected);

        $statements = $this->statements($tree, false, $root, 3, $line);

        self::assertSame($expected, $this->boundMediaXrefs($statements));
        $this->assertOnlyBatchedMediaLookups($statements);
    }

    /**
     * Pairs the administrator and the visitor with the state of the hidden spouses.
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
     * Lists the descendant lines with the media objects that a request binds.
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
     * Lists the administrator and the visitor.
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
     * Lists the viewers with the expected thumbnail of the people of the chart of the privacy tree. The
     * chart shows I1, the father, and his children. I3 is a dead son, I4 a living son, I5 a dead son
     * with a restriction notice and I7 a dead son who shares a media object with the person I6, who
     * has a restriction notice.
     *
     * @return array<string, array{string, array<string, bool>}>
     */
    public static function privacyViewers(): array
    {
        return [
            'administrator' => [
                'administrator',
                ['I1' => true, 'I3' => true, 'I7' => true, 'I4' => true, 'I5' => true],
            ],
            'visitor' => ['visitor', ['I1' => true, 'I3' => true, 'I7' => false, 'I4' => false, 'I5' => false]],
            'member'  => ['member', ['I1' => true, 'I3' => true, 'I7' => false, 'I4' => true, 'I5' => false]],
        ];
    }

    /**
     * Asserts that the request has media statements and that each of them carries an in list.
     *
     * @param list<array{query: string, bindings: list<string>}> $statements The statements of a request
     */
    private function assertOnlyBatchedMediaLookups(array $statements): void
    {
        $inspected = 0;

        foreach ($statements as $statement) {
            if (str_contains($statement['query'], 'from "wt_media"')) {
                self::assertStringContainsString('"m_id" in', $statement['query']);
                ++$inspected;
            }
        }

        self::assertGreaterThan(0, $inspected);
    }

    /**
     * Returns the sorted xrefs that the media statements of a request bind.
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
        int $generations = RealTreeImporter::DEPTH,
        string $line = 'all',
    ): array {
        Registry::cache(new CacheFactory());

        return StatementLog::capture(function () use ($tree, $xref, $generations, $hideSpouses, $line): void {
            $this->chart->structure($tree, $xref, $generations, $hideSpouses, $line);
        });
    }

    /**
     * Returns the statements that the highlight images add to the request of the given depth, which are
     * the statements beyond the same request with the highlight images switched off, and how many of
     * them look up pending changes.
     *
     * @param Tree $tree        The imported tree
     * @param int  $generations The number of generations of the chart
     * @param bool $hideSpouses Whether the chart hides the partners
     *
     * @return array{total: int, changes: int}
     */
    private function mediaStatements(Tree $tree, int $generations, bool $hideSpouses): array
    {
        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '');
        $without = $this->statements($tree, $hideSpouses, 'I1', $generations);

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');
        $with = $this->statements($tree, $hideSpouses, 'I1', $generations);

        return [
            'total'   => count($with) - count($without),
            'changes' => $this->changeStatements($with) - $this->changeStatements($without),
        ];
    }

    /**
     * Counts the statements that read the pending changes.
     *
     * @param list<array{query: string, bindings: list<string>}> $statements The statements of a request
     */
    private function changeStatements(array $statements): int
    {
        return count(array_filter(
            $statements,
            static fn (array $statement): bool => str_contains($statement['query'], '"wt_change"'),
        ));
    }

    /**
     * Returns the number of chunks that the generations up to the given one are looked up in.
     *
     * @param int  $generations The number of generations of the chart
     * @param bool $hideSpouses Whether the chart hides the partners
     */
    private function chunks(int $generations, bool $hideSpouses): int
    {
        $chunks = 0;

        for ($generation = 1; $generation <= $generations; ++$generation) {
            $chunks += $this->chunksOfGeneration($generation, $hideSpouses);
        }

        return $chunks;
    }

    /**
     * Returns the number of chunks that one generation of the imported tree is looked up in. The people
     * of a generation have partners in the tree while a later generation exists.
     *
     * @param int  $generation  The generation to count
     * @param bool $hideSpouses Whether the chart hides the partners
     */
    private function chunksOfGeneration(int $generation, bool $hideSpouses): int
    {
        $width = 1 << ($generation - 1);

        if (
            !$hideSpouses
            && ($generation < RealTreeImporter::DEPTH)
        ) {
            $width *= 2;
        }

        return intdiv($width + MediaPreloader::CHUNK_SIZE - 1, MediaPreloader::CHUNK_SIZE);
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

        $images = $this->collectImages($this->chart->structure($tree, $xref, $generations, false));

        ksort($images);

        return $images;
    }

    /**
     * Collects the images of the members of a couple and of the couples below it.
     *
     * @param array{
     *     members: NodeData[],
     *     memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>
     * } $couple The couple structure
     *
     * @return array<string, array{thumbnail: string, silhouette: string}>
     */
    private function collectImages(array $couple): array
    {
        $images = [];

        foreach ($couple['members'] as $member) {
            $memberData = $member->jsonSerialize();

            self::assertIsString($memberData['xref'] ?? null);
            self::assertIsString($memberData['thumbnail'] ?? null);
            self::assertIsString($memberData['silhouette'] ?? null);

            $images[$memberData['xref']] = [
                'thumbnail'  => $memberData['thumbnail'],
                'silhouette' => $memberData['silhouette'],
            ];
        }

        $chunks = [$images];

        foreach ($couple['memberFamilies'] as $family) {
            foreach ($family['children'] as $child) {
                $chunks[] = $this->collectImages($child->jsonSerialize());
            }
        }

        return array_replace(...$chunks);
    }

    /**
     * Returns the images that the image processor yields for the given people, on a cold record
     * cache.
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

            $processor = new ImageProcessor($this->chart->module(), $individual);

            $images[(string) $xref] = [
                'thumbnail'  => $processor->getHighlightImageUrl(),
                'silhouette' => $processor->getSilhouetteUrl(),
            ];
        }

        ksort($images);

        return $images;
    }
}
