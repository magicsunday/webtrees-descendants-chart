<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Enum;

/**
 * The line of descent the chart follows below its subject.
 *
 * A line is evaluated edge by edge on the link from a parent to a child, by the
 * recorded sex of the two persons. The backing values are the words stored in
 * module preferences and posted by the chart form, so `tryFrom()` is the boundary
 * between request input and typed code.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
enum DescendantLine: string
{
    /**
     * Every descendant, whatever the sex (the unfiltered chart).
     */
    case All = 'all';

    /**
     * The Y-chromosomal line. A father passes it to his sons only.
     */
    case Paternal = 'paternal';

    /**
     * The mitochondrial line. A mother passes it to all her children, while a
     * son does not pass it on.
     */
    case Maternal = 'maternal';

    /**
     * The GEDCOM sex token of a male person.
     */
    private const string SEX_MALE = 'M';

    /**
     * The GEDCOM sex token of a female person.
     */
    private const string SEX_FEMALE = 'F';

    /**
     * Whether the line continues along the link from a parent to a child.
     *
     * A person of unknown or other sex never matches, so no link is kept through
     * such a person.
     *
     * @param string $parentSex The recorded sex of the parent
     * @param string $childSex  The recorded sex of the child
     */
    public function keepsLink(string $parentSex, string $childSex): bool
    {
        return match ($this) {
            self::All      => true,
            self::Paternal => ($parentSex === self::SEX_MALE) && ($childSex === self::SEX_MALE),
            self::Maternal => $parentSex === self::SEX_FEMALE,
        };
    }
}
