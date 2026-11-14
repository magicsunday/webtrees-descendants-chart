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
use MagicSunday\Webtrees\ModuleBase\Processor\ImageProcessor;
use MagicSunday\Webtrees\ModuleBase\Support\MediaPreloader;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_values;
use function count;
use function file_put_contents;
use function glob;
use function implode;
use function intdiv;
use function is_array;
use function is_dir;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function max;
use function mkdir;
use function rmdir;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;

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
    private const int DEPTH = 8;

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

        // One media statement of every kind for each chunk of a generation: the media objects, their
        // pending changes, the links and the records that the links point to.
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
        self::assertLessThanOrEqual(MediaPreloader::CHUNK_SIZE, $widest);
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

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '');
        $without = $this->statements($tree, false);

        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');
        $with = $this->statements($tree, false);

        self::assertSame($this->mediaStatements($without), $this->mediaStatements($with));
        self::assertSame([], $this->mediaStatements($with));
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
     * Lists the viewers of the privacy tree with the people whose thumbnail they see. I1 is a public
     * person with a media object of their own, I3 is public with a media object that only public
     * people link, I7 is public but its media object is also linked to a confidential person, I4 is living (a member sees living people)
     * and I5 is confidential.
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

            if (($xref === 'I5') || ($xref === 'I6')) {
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
     * Keeps the statements that read media objects, or their links.
     *
     * @param list<array{query: string, bindings: list<string>}> $statements The statements of a request
     *
     * @return list<array{query: string, bindings: list<string>}>
     */
    private function mediaStatements(array $statements): array
    {
        return array_values(array_filter(
            $statements,
            static fn (array $statement): bool => str_contains($statement['query'], '"wt_media"')
                || str_contains($statement['query'], '"wt_link"'),
        ));
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

        if (!mkdir($this->indexDirectory . 'media', 0o777, true) && !is_dir($this->indexDirectory . 'media')) {
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
     * @param Tree $tree        The imported tree
     * @param bool $hideSpouses Whether the chart hides the partners
     *
     * @return list<array{query: string, bindings: list<string>}>
     */
    private function statements(Tree $tree, bool $hideSpouses): array
    {
        Registry::cache(new CacheFactory());

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $this->structure($tree, 'I1', self::DEPTH, $hideSpouses);

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
     *
     * @return array<string, mixed>
     */
    private function structure(Tree $tree, string $xref, int $generations, bool $hideSpouses): array
    {
        $individual = Registry::individualFactory()->make($xref, $tree);
        self::assertInstanceOf(Individual::class, $individual);

        $configuration = new Configuration(
            (new ServerRequest(RequestMethodInterface::METHOD_GET, '/'))->withQueryParams([
                'generations'     => (string) $generations,
                'showDetailPanel' => '0',
                'hideSpouses'     => $hideSpouses ? '1' : '0',
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

        $payload = json_decode(
            json_encode($this->structure($tree, $xref, $generations, false), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $images = $this->collectImages($payload);

        ksort($images);

        return $images;
    }

    /**
     * Collects the images of every person in a decoded payload, whatever its depth.
     *
     * @param mixed $node A part of the decoded payload
     *
     * @return array<string, array{thumbnail: string, silhouette: string}>
     */
    private function collectImages(mixed $node): array
    {
        if (!is_array($node)) {
            return [];
        }

        $images = [];

        if (is_string($node['xref'] ?? null) && is_string($node['thumbnail'] ?? null) && is_string($node['silhouette'] ?? null)) {
            $images[$node['xref']] = [
                'thumbnail'  => $node['thumbnail'],
                'silhouette' => $node['silhouette'],
            ];
        }

        foreach ($node as $child) {
            $images = [...$images, ...$this->collectImages($child)];
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
