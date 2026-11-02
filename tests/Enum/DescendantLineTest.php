<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Enum;

use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Verifies the decision which parent to child link a descendant line keeps,
 * written out as literal verdicts per kind of sex pair.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
#[CoversClass(DescendantLine::class)]
final class DescendantLineTest extends TestCase
{
    /**
     * Every line with a representative parent and child sex pair per partition and the
     * literal verdict. A male parent with a female child, a female parent with a male
     * child and a parent of unknown, other or empty sex are the pairs that tell the lines apart.
     *
     * @return iterable<string, array{DescendantLine, string, string, bool}>
     */
    public static function linkProvider(): iterable
    {
        yield 'all, male to male' => [DescendantLine::All, 'M', 'M', true];

        yield 'all, male to female' => [DescendantLine::All, 'M', 'F', true];

        yield 'all, female to male' => [DescendantLine::All, 'F', 'M', true];

        yield 'all, unknown to other' => [DescendantLine::All, 'U', 'X', true];

        yield 'all, empty sexes' => [DescendantLine::All, '', '', true];

        yield 'paternal, male to male' => [DescendantLine::Paternal, 'M', 'M', true];

        yield 'paternal, male to female' => [DescendantLine::Paternal, 'M', 'F', false];

        yield 'paternal, female to male' => [DescendantLine::Paternal, 'F', 'M', false];

        yield 'paternal, female to female' => [DescendantLine::Paternal, 'F', 'F', false];

        yield 'paternal, male to unknown' => [DescendantLine::Paternal, 'M', 'U', false];

        yield 'paternal, unknown to male' => [DescendantLine::Paternal, 'U', 'M', false];

        yield 'paternal, other to male' => [DescendantLine::Paternal, 'X', 'M', false];

        yield 'paternal, empty sexes' => [DescendantLine::Paternal, '', '', false];

        yield 'maternal, female to male' => [DescendantLine::Maternal, 'F', 'M', true];

        yield 'maternal, female to female' => [DescendantLine::Maternal, 'F', 'F', true];

        yield 'maternal, female to unknown' => [DescendantLine::Maternal, 'F', 'U', true];

        yield 'maternal, female to other' => [DescendantLine::Maternal, 'F', 'X', true];

        yield 'maternal, female to empty child sex' => [DescendantLine::Maternal, 'F', '', true];

        yield 'maternal, male to female' => [DescendantLine::Maternal, 'M', 'F', false];

        yield 'maternal, male to male' => [DescendantLine::Maternal, 'M', 'M', false];

        yield 'maternal, unknown to female' => [DescendantLine::Maternal, 'U', 'F', false];

        yield 'maternal, other to female' => [DescendantLine::Maternal, 'X', 'F', false];

        yield 'maternal, empty parent sex' => [DescendantLine::Maternal, '', 'F', false];
    }

    /**
     * A link passes exactly when the line inherits along it.
     */
    #[Test]
    #[DataProvider('linkProvider')]
    public function keepsALinkOnlyWhereTheLineInherits(DescendantLine $line, string $parentSex, string $childSex, bool $expected): void
    {
        self::assertSame($expected, $line->keepsLink($parentSex, $childSex));
    }

    /**
     * The stored values are the three whitelisted words.
     */
    #[Test]
    public function backingValuesAreTheWhitelistedWords(): void
    {
        self::assertSame('all', DescendantLine::All->value);
        self::assertSame('paternal', DescendantLine::Paternal->value);
        self::assertSame('maternal', DescendantLine::Maternal->value);
        self::assertSame(
            ['all', 'paternal', 'maternal'],
            array_map(static fn (DescendantLine $line): string => $line->value, DescendantLine::cases()),
        );
    }
}
