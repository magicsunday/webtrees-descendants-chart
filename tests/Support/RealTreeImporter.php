<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Support;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Tree;
use MagicSunday\Webtrees\ModuleBase\Testing\MediaDirectory;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;

use function array_keys;
use function array_push;
use function implode;
use function intdiv;

/**
 * Imports the trees that the descendants chart tests run on into the real schema, and creates the
 * image files of their media objects.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
final readonly class RealTreeImporter
{
    /**
     * The number of generations of the imported descendant tree.
     */
    public const int DEPTH = 9;

    /**
     * The number added to the number of a family to get the number of its partner.
     */
    public const int PARTNER_OFFSET = 100000;

    /**
     * The number added to the number of a family to get the number of the father of its partner.
     */
    public const int PARTNER_FATHER_OFFSET = 200000;

    /**
     * @param MediaDirectory $mediaDirectory The directory that holds the image files of the media objects
     */
    public function __construct(
        private MediaDirectory $mediaDirectory,
    ) {
    }

    /**
     * Imports the descendant tree with partners and media objects, and optionally with partners
     * that have no dates.
     *
     * @param bool $withMedia                Whether the people have media lines
     * @param bool $withPartnersWithoutDates Whether the partners of the first three families have no dates and a parent family
     */
    public function descendants(bool $withMedia = true, bool $withPartnersWithoutDates = false): Tree
    {
        $people   = (2 ** self::DEPTH) - 1;
        $families = (2 ** (self::DEPTH - 1)) - 1;

        $lines = ['0 HEAD', '1 CHAR UTF-8'];

        for ($person = 1; $person <= $people; ++$person) {
            array_push($lines, ...$this->personLines('I' . $person, 'M', $person, $withMedia));

            if ($person >= 2) {
                $lines[] = '1 FAMC @F' . intdiv($person, 2) . '@';
            }

            if ($person <= $families) {
                $lines[] = '1 FAMS @F' . $person . '@';
            }
        }

        for ($family = 1; $family <= $families; ++$family) {
            $partner = self::PARTNER_OFFSET + $family;

            if (
                $withPartnersWithoutDates
                && ($family <= 3)
            ) {
                $lines[] = '0 @I' . $partner . '@ INDI';
                $lines[] = '1 NAME Undated' . $family . ' /Partner/';
                $lines[] = '1 SEX F';
                $lines[] = '1 FAMC @FP' . $family . '@';
                $lines[] = '1 FAMS @F' . $family . '@';

                continue;
            }

            array_push($lines, ...$this->personLines('I' . $partner, 'F', $partner, $withMedia));

            $lines[] = '1 FAMS @F' . $family . '@';
        }

        for ($family = 1; $family <= $families; ++$family) {
            $lines[] = '0 @F' . $family . '@ FAM';
            $lines[] = '1 HUSB @I' . $family . '@';
            $lines[] = '1 WIFE @I' . (self::PARTNER_OFFSET + $family) . '@';
            $lines[] = '1 CHIL @I' . (2 * $family) . '@';
            $lines[] = '1 CHIL @I' . ((2 * $family) + 1) . '@';
        }

        if ($withPartnersWithoutDates) {
            for ($family = 1; $family <= 3; ++$family) {
                $father = self::PARTNER_FATHER_OFFSET + $family;

                array_push($lines, ...$this->personLines('I' . $father, 'M', $father, false));
                $lines[] = '0 @FP' . $family . '@ FAM';
                $lines[] = '1 HUSB @I' . $father . '@';
                $lines[] = '1 CHIL @I' . (self::PARTNER_OFFSET + $family) . '@';
            }
        }

        $numbers = [];

        for ($person = 1; $person <= $people; ++$person) {
            $numbers[] = $person;
        }

        for ($family = 1; $family <= $families; ++$family) {
            $numbers[] = self::PARTNER_OFFSET + $family;
        }

        $lines = [...$lines, ...$this->mediaRecordLines($numbers)];

        $this->createMediaFiles($numbers);

        $lines[] = '0 TRLR';

        return RealTreeFixture::importGedcom(
            implode("\n", $lines),
            'descendants',
            [
                'HIDE_LIVE_PEOPLE'      => '1',
                'SHOW_LIVING_NAMES'     => (string) Auth::PRIV_PRIVATE,
                'SHOW_DEAD_PEOPLE'      => (string) Auth::PRIV_PRIVATE,
                'SHOW_HIGHLIGHT_IMAGES' => '1',
            ],
        );
    }

    /**
     * Imports a couple with four children and media objects, one of which is linked by two
     * people.
     */
    public function privacy(): Tree
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
            $withoutDeath = $xref === 'I4';

            $lines[] = '0 @' . $xref . '@ INDI';
            $lines[] = '1 NAME Given' . $xref . ' /Family/';
            $lines[] = '1 SEX ' . $sex;
            $lines[] = '1 BIRT';
            $lines[] = '2 DATE ' . ($withoutDeath ? '1990' : '1850');

            if (!$withoutDeath) {
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

        $lines = [...$lines, ...$this->mediaRecordLines($numbers)];

        $this->createMediaFiles($numbers);

        $lines[] = '0 TRLR';

        return RealTreeFixture::importGedcom(
            implode("\n", $lines),
            'privacy',
            [
                'HIDE_LIVE_PEOPLE'      => '1',
                'SHOW_LIVING_NAMES'     => (string) Auth::PRIV_PRIVATE,
                'SHOW_DEAD_PEOPLE'      => (string) Auth::PRIV_PRIVATE,
                'SHOW_HIGHLIGHT_IMAGES' => '1',
            ],
        );
    }

    /**
     * Imports a tree in which both sexes occur among the children. I1 (M) and I2 (F) have the
     * children I3 (M) and I4 (F). I3 and I7 (F) have the children I5 (M) and I6 (F). I8 (M) and
     * I4 have the children I9 (M) and I10 (F). I9 and I13 (F) have the child I11 (F), I14 (M)
     * and I10 have the child I12 (M). Each person has a media object M plus the number.
     */
    public function lineage(): Tree
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
            array_push($lines, ...$this->personLines('I' . $number, $sex, $number, true));

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

        $lines = [...$lines, ...$this->mediaRecordLines(array_keys($people))];

        $lines[] = '0 TRLR';

        return RealTreeFixture::importGedcom(
            implode("\n", $lines),
            'lineage',
            [
                'HIDE_LIVE_PEOPLE'      => '1',
                'SHOW_DEAD_PEOPLE'      => (string) Auth::PRIV_PRIVATE,
                'SHOW_HIGHLIGHT_IMAGES' => '1',
            ],
        );
    }

    /**
     * Returns the record lines of a person with a birth and a death date.
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
     * Returns the record lines of the media objects with the given numbers.
     *
     * @param list<int> $numbers The numbers of the media objects
     *
     * @return list<string>
     */
    private function mediaRecordLines(array $numbers): array
    {
        $lines = [];

        foreach ($numbers as $number) {
            $lines[] = '0 @M' . $number . '@ OBJE';
            $lines[] = '1 FILE person' . $number . '.jpg';
            $lines[] = '2 FORM jpg';
        }

        return $lines;
    }

    /**
     * Creates the image files of the odd numbers in a new media folder.
     *
     * @param list<int> $numbers The numbers of the media objects
     */
    private function createMediaFiles(array $numbers): void
    {
        $this->mediaDirectory->create('descendants-chart-');

        foreach ($numbers as $number) {
            if ($number % 2 === 1) {
                $this->mediaDirectory->writeFile('person' . $number . '.jpg');
            }
        }
    }
}
