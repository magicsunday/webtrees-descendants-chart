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
 * evaluated for every combination of recorded sexes.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
#[CoversClass(DescendantLine::class)]
final class DescendantLineTest extends TestCase
{
    /**
     * Every line, parent sex, child sex and the expected verdict.
     *
     * @return iterable<string, array{DescendantLine, string, string, bool}>
     */
    public static function linkProvider(): iterable
    {
        $sexes = ['M', 'F', 'X', 'U'];

        foreach ($sexes as $parent) {
            foreach ($sexes as $child) {
                yield "all, parent $parent, child $child" => [DescendantLine::All, $parent, $child, true];

                yield "paternal, parent $parent, child $child" => [
                    DescendantLine::Paternal,
                    $parent,
                    $child,
                    ($parent === 'M') && ($child === 'M'),
                ];

                yield "maternal, parent $parent, child $child" => [
                    DescendantLine::Maternal,
                    $parent,
                    $child,
                    $parent === 'F',
                ];
            }
        }

        yield 'paternal, empty sexes' => [DescendantLine::Paternal, '', '', false];

        yield 'maternal, empty sex' => [DescendantLine::Maternal, '', 'F', false];

        yield 'all, empty sexes' => [DescendantLine::All, '', '', true];
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
