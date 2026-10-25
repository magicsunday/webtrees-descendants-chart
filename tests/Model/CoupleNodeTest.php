<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Model;

use MagicSunday\Webtrees\DescendantsChart\Model\CoupleNode;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies how a couple node keeps its members: the real person always comes
 * first and every spouse is appended behind it in registration order.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
#[CoversClass(CoupleNode::class)]
final class CoupleNodeTest extends TestCase
{
    /**
     * Verifies that a new couple holds only its real person, as the first member.
     */
    #[Test]
    public function newCoupleHoldsOnlyItsRealPersonAsFirstMember(): void
    {
        $realPerson = new NodeData();

        $couple = new CoupleNode($realPerson);

        self::assertSame([$realPerson], $couple->getMembers());
    }

    /**
     * Verifies that adding a spouse returns the index of the slot it was stored in.
     */
    #[Test]
    public function addSpouseReturnsTheIndexOfTheStoredSpouse(): void
    {
        $couple = new CoupleNode(new NodeData());

        self::assertSame(1, $couple->addSpouse(new NodeData()));
        self::assertSame(2, $couple->addSpouse(new NodeData()));
    }

    /**
     * Verifies that the spouses follow the real person in the order they were added.
     */
    #[Test]
    public function spousesFollowTheRealPersonInRegistrationOrder(): void
    {
        $realPerson   = new NodeData();
        $firstSpouse  = new NodeData();
        $secondSpouse = new NodeData();

        $couple = new CoupleNode($realPerson);

        $firstIndex  = $couple->addSpouse($firstSpouse);
        $secondIndex = $couple->addSpouse($secondSpouse);

        self::assertSame([$realPerson, $firstSpouse, $secondSpouse], $couple->getMembers());
        self::assertSame([1, 2], [$firstIndex, $secondIndex]);
    }
}
