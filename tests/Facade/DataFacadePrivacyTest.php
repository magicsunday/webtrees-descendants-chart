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
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function ctype_digit;
use function file_put_contents;
use function implode;
use function is_dir;
use function json_encode;
use function mkdir;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function rmdir;
use function str_contains;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * Drives the real webtrees privacy engine through the data path of the chart. The tree is a real
 * imported GEDCOM in a real schema, and the payload comes from the facade the chart uses, so
 * nothing about the visibility of a person is stubbed.
 *
 * The tree holds a public line of deceased people, a deceased child with a confidential
 * restriction, and a living child with a place, an image, a marriage and a living child of its
 * own. The confidential and the living people are the private ones. A visitor may see none of
 * them. A member who is no editor sees the living people and the name of the confidential one,
 * and the importing administrator sees all of them. The test reads the administrator payload
 * first, so the data of the private people is shown to be in the fixture and in the payload, and
 * only then drops to the other viewers, so that the absence of the data is the gate and not an
 * empty fixture. The public people are the positive control, so that the absence is not an empty
 * chart. A family is private for a viewer who may not see one of its members,
 * so the marriage of the public people sits in a family without private members.
 *
 * A private person stays in the chart as a stub, as it does in the core charts. The stub holds
 * the existence of the person, its xref, its sex and its position in the tree, and nothing else.
 * The name of the stub is the "Private" placeholder, and the dates, the image and the facts are
 * empty. A guessed xref of a private person therefore yields the same stub.
 *
 * The webtrees record factory caches the records of a request, and a cached record keeps the
 * names it extracted for the first viewer. A request has one viewer, so the test clears that
 * cache whenever the viewer changes.
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
final class DataFacadePrivacyTest extends TestCase
{
    /**
     * The name of the confidential deceased child (I3). The tree setting for the names of private
     * people lets a member see it, so it is hidden from a visitor only.
     *
     * @var list<string>
     */
    private const array CONFIDENTIAL_NAME_TOKENS = [
        'Childly',
        'Confidentialchild',
        'Конфиденциальный',
    ];

    /**
     * The dates and places of the confidential deceased child (I3). Every one is shown to the
     * administrator and must reach neither the visitor nor the member.
     *
     * @var list<string>
     */
    private const array CONFIDENTIAL_TOKENS = [
        'Hiddenchildplace',
        '1880',
        '1950',
    ];

    /**
     * Values that exist only on the living people (I4, I6, I7) and the marriage that lists them.
     * A member sees them, a visitor must not.
     *
     * @var list<string>
     */
    private const array LIVING_TOKENS = [
        'Livingson',
        'Livinghidden',
        'Живой',
        'Hiddenlivingplace',
        '1931',
        'Livingpartner',
        'Hiddenpartnerplace',
        '1935',
        'Livinggrandchild',
        'Hiddengrandplace',
        '1962',
        'Hiddenmarriageplace',
        '1955',
        'media-thumbnail',
    ];

    /**
     * Values of the public deceased people, which every viewer sees.
     *
     * @var list<string>
     */
    private const array PUBLIC_TOKENS = [
        'Rootline',
        'Publicroot',
        'Rootbirthplace',
        'Rootdeathplace',
        '1850',
        '1920',
        'Rootwife',
        'Publicwife',
        'Wifebirthplace',
        '1855',
        '1925',
        'Publicmarriageplace',
        '1878',
        'Sonlaw',
        'Publicsonlaw',
        '1882',
        '1956',
        'Daughterly',
        'Publicdaughter',
        'Daughterbirthplace',
        '1885',
        '1965',
    ];

    /**
     * The module of the chart.
     */
    private Module $module;

    /**
     * The index directory of the media file, which the test creates and removes, or an empty
     * string while there is none.
     */
    private string $indexDirectory = '';

    /**
     * Boots webtrees, builds a fresh schema in memory and logs in an administrator, the way the
     * import of a tree runs in production.
     */
    protected function setUp(): void
    {
        parent::setUp();

        RealTreeFixture::boot();

        // The media file of the living person has to exist, because the image of a person is only
        // referenced when its file is there.
        $this->indexDirectory = sys_get_temp_dir() . '/descendants-chart-privacy-' . uniqid('', true) . '/';

        if (!mkdir($this->indexDirectory . 'media', 0o777, true) && !is_dir($this->indexDirectory . 'media')) {
            throw new RuntimeException('The media directory could not be created.');
        }

        if (file_put_contents($this->indexDirectory . 'media/hiddenlivingphoto.jpg', 'image') !== strlen('image')) {
            throw new RuntimeException('The media file could not be written.');
        }

        Site::setPreference('INDEX_DIRECTORY', $this->indexDirectory);

        DB::table('module')->insert(['module_name' => 'webtrees-descendants-chart']);

        $this->module = new Module(self::createStub(ChartService::class), new DataFacade());
        $this->module->setName('webtrees-descendants-chart');
        $this->module->boot();
    }

    /**
     * Drops the viewer and the media directory of the test.
     */
    protected function tearDown(): void
    {
        Auth::logout();
        Session::clear();
        Site::$preferences = [];
        Registry::cache(new CacheFactory());

        if ($this->indexDirectory !== '') {
            unlink($this->indexDirectory . 'media/hiddenlivingphoto.jpg');

            rmdir($this->indexDirectory . 'media');
            rmdir($this->indexDirectory);
        }

        RealTreeFixture::restoreMinimalDatabase();

        parent::tearDown();
    }

    /**
     * The administrator control. Every private value is in the payload of the importing
     * administrator, and so is the public data, so the tests below measure the gate.
     */
    #[Test]
    public function administratorPayloadHoldsTheDataOfThePrivatePeople(): void
    {
        $tree = $this->importTree();

        $payload = $this->payload($tree, 'I1');

        foreach ([...self::CONFIDENTIAL_NAME_TOKENS, ...self::CONFIDENTIAL_TOKENS, ...self::LIVING_TOKENS, ...self::PUBLIC_TOKENS] as $token) {
            self::assertTrue($this->occursIn($payload, $token), 'The administrator should see ' . $token);
        }
    }

    /**
     * A visitor gets no name, date, place or fact of a confidential or a living person and no
     * image reference of a living person, and the public deceased people are still there.
     */
    #[Test]
    public function visitorPayloadHoldsNothingOfThePrivatePeople(): void
    {
        $tree = $this->importTree();

        // The control runs as the administrator and fills the record cache with the administrator view.
        self::assertTrue($this->occursIn($this->payload($tree, 'I1'), 'Livingson'));

        Registry::cache(new CacheFactory());
        Auth::logout();

        $payload = $this->payload($tree, 'I1');

        foreach (self::PUBLIC_TOKENS as $token) {
            self::assertTrue($this->occursIn($payload, $token), 'The visitor should see ' . $token);
        }

        foreach ([...self::CONFIDENTIAL_NAME_TOKENS, ...self::CONFIDENTIAL_TOKENS, ...self::LIVING_TOKENS] as $token) {
            self::assertFalse($this->occursIn($payload, $token), 'The visitor must not see ' . $token);
        }
    }

    /**
     * A member who is no editor sees the living people. Of the person with a confidential
     * restriction the member sees the name, which the tree setting for the names of private people
     * allows, and no date, place or fact. The fixture pins that setting to members.
     */
    #[Test]
    public function memberWhoIsNoEditorSeesTheLivingButNoDetailsOfTheConfidentialPerson(): void
    {
        $tree = $this->importTree();

        self::assertTrue($this->occursIn($this->payload($tree, 'I1'), 'Confidentialchild'));

        $member = (new UserService())->create('member', 'Member', 'member@example.test', 'secret');
        $tree->setUserPreference($member, UserInterface::PREF_TREE_ROLE, UserInterface::ROLE_MEMBER);

        Registry::cache(new CacheFactory());
        Auth::login($member);

        $payload = $this->payload($tree, 'I1');

        foreach ([...self::PUBLIC_TOKENS, ...self::LIVING_TOKENS, ...self::CONFIDENTIAL_NAME_TOKENS] as $token) {
            self::assertTrue($this->occursIn($payload, $token), 'The member should see ' . $token);
        }

        foreach (self::CONFIDENTIAL_TOKENS as $token) {
            self::assertFalse($this->occursIn($payload, $token), 'The member must not see ' . $token);
        }
    }

    /**
     * A chart centred on a guessed private xref shows the stub of the core charts, which is the
     * existence of the person, its xref and its sex, and nothing more.
     */
    #[Test]
    public function guessedPrivateXrefYieldsTheCoreCompatibleStub(): void
    {
        $tree = $this->importTree();

        self::assertTrue($this->occursIn($this->payload($tree, 'I3'), 'Confidentialchild'));

        Registry::cache(new CacheFactory());
        Auth::logout();

        $structure = $this->structure($tree, 'I3');
        $member    = $structure['members'][0] ?? null;

        self::assertInstanceOf(NodeData::class, $member);

        $data = $member->jsonSerialize();

        self::assertSame('I3', $data['xref'] ?? null);
        self::assertSame('M', $data['sex'] ?? null);
        self::assertSame(1, $data['generation'] ?? null);
        self::assertSame('Private', $data['name'] ?? null);

        foreach (['birth', 'death', 'timespan', 'thumbnail', 'silhouette'] as $field) {
            self::assertSame('', $data[$field] ?? null, $field . ' of the stub should be empty');
        }

        $payload = json_encode($structure, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach ([...self::CONFIDENTIAL_NAME_TOKENS, ...self::CONFIDENTIAL_TOKENS] as $token) {
            self::assertFalse($this->occursIn($payload, $token), 'The stub must not carry ' . $token);
        }
    }

    /**
     * The tooltip may show a silhouette once the tree preference for silhouettes is on, and the
     * silhouette URL is the only carrier of that image. A visitor gets none for a person that the
     * privacy engine hides, whereas a person that the visitor may see gets one.
     */
    #[Test]
    public function visitorGetsNoSilhouetteUrlForAPrivatePersonWhenSilhouettesAreOn(): void
    {
        $tree = $this->importTree();

        $tree->setPreference('USE_SILHOUETTE', '1');

        // The control runs as the administrator, who sees the private person with a silhouette.
        $administrator = $this->structure($tree, 'I3')['members'][0] ?? null;

        self::assertInstanceOf(NodeData::class, $administrator);
        self::assertNotSame('', $administrator->jsonSerialize()['silhouette'] ?? '');

        Registry::cache(new CacheFactory());
        Auth::logout();

        $private = $this->structure($tree, 'I3')['members'][0] ?? null;
        $public  = $this->structure($tree, 'I1')['members'][0] ?? null;

        self::assertInstanceOf(NodeData::class, $private);
        self::assertInstanceOf(NodeData::class, $public);
        self::assertSame('', $private->jsonSerialize()['silhouette'] ?? null);
        self::assertNotSame('', $public->jsonSerialize()['silhouette'] ?? '');
    }

    /**
     * The page title names the person only where the viewer may see the name, so a guessed xref
     * of a private person leaves the name out of the title of a visitor.
     */
    #[Test]
    public function pageTitleLeavesTheNameOfAPrivatePersonOutForAVisitor(): void
    {
        $tree = $this->importTree();

        $private = Registry::individualFactory()->make('I3', $tree);
        self::assertInstanceOf(Individual::class, $private);

        self::assertStringContainsString('Confidentialchild', $this->module->pageTitle($private));

        Registry::cache(new CacheFactory());
        Auth::logout();

        $private = Registry::individualFactory()->make('I3', $tree);
        self::assertInstanceOf(Individual::class, $private);

        self::assertSame('Descendants chart', $this->module->pageTitle($private));
    }

    /**
     * Returns whether the payload holds the token. A numeric token only counts as a whole number,
     * because the payload also holds asset URLs with a numeric hash parameter that can contain the
     * digits of a year inside a longer number, and a JSON unicode escape does not count as digits.
     *
     * @param string $payload The JSON text
     * @param string $token   The value to look for
     */
    private function occursIn(string $payload, string $token): bool
    {
        if (!ctype_digit($token)) {
            return str_contains($payload, $token);
        }

        $text = preg_replace('/\\\\u[0-9a-fA-F]{4}/', ' ', $payload) ?? $payload;

        return preg_match('/(?<!\d)' . preg_quote($token, '/') . '(?!\d)/', $text) === 1;
    }

    /**
     * Returns the JSON payload of the chart as the current viewer.
     *
     * @param Tree   $tree The imported tree
     * @param string $xref The xref of the chart subject
     */
    private function payload(Tree $tree, string $xref): string
    {
        return json_encode($this->structure($tree, $xref), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Builds the tree structure with the detail panel on, so that every kind of data the chart
     * carries is in the payload, the facts with their places and the images included.
     *
     * @param Tree   $tree The imported tree
     * @param string $xref The xref of the chart subject
     *
     * @return array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>}
     */
    private function structure(Tree $tree, string $xref): array
    {
        $individual = Registry::individualFactory()->make($xref, $tree);
        self::assertInstanceOf(Individual::class, $individual);

        $configuration = new Configuration(
            (new ServerRequest(RequestMethodInterface::METHOD_GET, '/'))->withQueryParams([
                'generations'     => '4',
                'showDetailPanel' => '1',
                'hideSpouses'     => '0',
            ]),
            $this->module,
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
     * Imports the fixture tree as the administrator and turns the privacy engine on. Living people
     * are hidden from visitors and the dead are public, so a visitor sees a person only when the
     * person is dead and not restricted.
     */
    private function importTree(): Tree
    {
        $gedcom = implode("\n", [
            '0 HEAD',
            '1 CHAR UTF-8',
            '0 @I1@ INDI',
            '1 NAME Rootline /Publicroot/',
            '1 SEX M',
            '1 BIRT',
            '2 DATE 1 JAN 1850',
            '2 PLAC Rootbirthplace',
            '1 DEAT',
            '2 DATE 1 JAN 1920',
            '2 PLAC Rootdeathplace',
            '1 FAMS @F1@',
            '0 @I2@ INDI',
            '1 NAME Rootwife /Publicwife/',
            '1 SEX F',
            '1 BIRT',
            '2 DATE 1 JAN 1855',
            '2 PLAC Wifebirthplace',
            '1 DEAT',
            '2 DATE 1 JAN 1925',
            '1 FAMS @F1@',
            '0 @I3@ INDI',
            '1 NAME Childly /Confidentialchild/',
            '1 NAME Конфиденциальный /Конфиденциальный/',
            '2 TYPE aka',
            '1 SEX M',
            '1 RESN confidential',
            '1 BIRT',
            '2 DATE 1 JAN 1880',
            '2 PLAC Hiddenchildplace',
            '1 DEAT',
            '2 DATE 1 JAN 1950',
            '1 FAMC @F1@',
            '0 @I4@ INDI',
            '1 NAME Livingson /Livinghidden/',
            '1 NAME Живой /Живой/',
            '2 TYPE aka',
            '1 SEX M',
            '1 BIRT',
            '2 DATE 1 JAN 1931',
            '2 PLAC Hiddenlivingplace',
            '1 OBJE @M1@',
            '1 FAMC @F1@',
            '1 FAMS @F2@',
            '0 @I5@ INDI',
            '1 NAME Daughterly /Publicdaughter/',
            '1 SEX F',
            '1 BIRT',
            '2 DATE 1 JAN 1885',
            '2 PLAC Daughterbirthplace',
            '1 DEAT',
            '2 DATE 1 JAN 1965',
            '1 FAMC @F1@',
            '1 FAMS @F3@',
            '0 @I8@ INDI',
            '1 NAME Sonlaw /Publicsonlaw/',
            '1 SEX M',
            '1 BIRT',
            '2 DATE 1 JAN 1882',
            '1 DEAT',
            '2 DATE 1 JAN 1956',
            '1 FAMS @F3@',
            '0 @I6@ INDI',
            '1 NAME Livingpartner /Livingpartner/',
            '1 SEX F',
            '1 BIRT',
            '2 DATE 1 JAN 1935',
            '2 PLAC Hiddenpartnerplace',
            '1 FAMS @F2@',
            '0 @I7@ INDI',
            '1 NAME Livinggrandchild /Livinggrandchild/',
            '1 SEX M',
            '1 BIRT',
            '2 DATE 1 JAN 1962',
            '2 PLAC Hiddengrandplace',
            '1 FAMC @F2@',
            '0 @F1@ FAM',
            '1 HUSB @I1@',
            '1 WIFE @I2@',
            '1 CHIL @I3@',
            '1 CHIL @I4@',
            '1 CHIL @I5@',
            '0 @F2@ FAM',
            '1 HUSB @I4@',
            '1 WIFE @I6@',
            '1 CHIL @I7@',
            '1 MARR',
            '2 DATE 1 JAN 1955',
            '2 PLAC Hiddenmarriageplace',
            '0 @F3@ FAM',
            '1 HUSB @I8@',
            '1 WIFE @I5@',
            '1 MARR',
            '2 DATE 1 JAN 1878',
            '2 PLAC Publicmarriageplace',
            '0 @M1@ OBJE',
            '1 FILE hiddenlivingphoto.jpg',
            '2 FORM jpg',
            '3 TYPE photo',
            '0 TRLR',
        ]);

        $tree = RealTreeFixture::importGedcom($gedcom, 'privacy');

        $tree->setPreference('HIDE_LIVE_PEOPLE', '1');
        $tree->setPreference('SHOW_PRIVATE_RELATIONSHIPS', '1');
        $tree->setPreference('SHOW_LIVING_NAMES', (string) Auth::PRIV_USER);
        $tree->setPreference('SHOW_DEAD_PEOPLE', (string) Auth::PRIV_PRIVATE);
        $tree->setPreference('SHOW_HIGHLIGHT_IMAGES', '1');

        return $tree;
    }
}
