<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Model;

use JsonSerializable;

/**
 * One d3-hierarchy node in the descendants tree, representing an individual
 * together with their spouse(s). Modelling each couple as a single node lets
 * d3.tree() centre the parent block over its children automatically — no
 * post-layout shifts, no overlap-prone work-arounds.
 *
 * Wire format:
 *
 *     {
 *         "members": [<NodeData real-person>, <NodeData spouse 1>, <NodeData spouse 2>, …],
 *         "memberFamilies": [
 *             {"family": 0, "spouseIndex": 1, "children": [<CoupleNode>, …]},
 *             {"family": 1, "spouseIndex": 2, "children": [<CoupleNode>, …]}
 *         ]
 *     }
 *
 * `members[0]` is always the "real" person; later entries are spouses in
 * registration order. `memberFamilies[].spouseIndex` points into `members`
 * (or is `null` when the child branch belongs to the real-person alone, e.g.
 * unknown/missing spouse). `children` are the sub-couple nodes for the
 * descendants of that family.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
class CoupleNode implements JsonSerializable
{
    /**
     * Members of this couple. Index 0 is the real-person, later indices are spouses.
     *
     * @var NodeData[]
     */
    protected array $members = [];

    /**
     * Per-family child branches. Each entry references the spouse from
     * `$members` by index (or null when the branch has no known spouse).
     *
     * @var array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>
     */
    protected array $memberFamilies = [];

    /**
     * Constructor.
     *
     * @param NodeData $realPerson The chart subject of this couple node
     */
    public function __construct(NodeData $realPerson)
    {
        $this->members[] = $realPerson;
    }

    /**
     * Append a spouse to the couple. Returns the index assigned to the spouse
     * in the `members` array so callers can wire up the matching family entry.
     */
    public function addSpouse(NodeData $spouse): int
    {
        $this->members[] = $spouse;

        return array_key_last($this->members);
    }

    /**
     * Register a family branch. `spouseIndex` is the position of the relevant
     * spouse inside `$members` (typically the index returned by `addSpouse()`),
     * or `null` for branches without a recorded partner.
     *
     * @param CoupleNode[] $children
     */
    public function addFamily(int $family, ?int $spouseIndex, array $children): self
    {
        $this->memberFamilies[] = [
            'family'      => $family,
            'spouseIndex' => $spouseIndex,
            'children'    => $children,
        ];

        return $this;
    }

    /**
     * @return NodeData[]
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * @return array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>
     */
    public function getMemberFamilies(): array
    {
        return $this->memberFamilies;
    }

    /**
     * Flatten every family's children into a single list. Used by callers
     * that need to walk the descendants without caring which family each
     * child belongs to (for example birthdate sorting when spouses are hidden).
     *
     * @return CoupleNode[]
     */
    public function getAllChildren(): array
    {
        $all = [];

        foreach ($this->memberFamilies as $family) {
            foreach ($family['children'] as $child) {
                $all[] = $child;
            }
        }

        return $all;
    }

    /**
     * Replace the family list with the provided one. Used by the
     * hide-spouses sort pass to write back re-ordered children without
     * having to mutate the array entries in place.
     *
     * @param array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}> $families
     */
    public function setSortedFamilies(array $families): self
    {
        $this->memberFamilies = $families;

        return $this;
    }

    /**
     * @return array{members: NodeData[], memberFamilies: array<int, array{family: int, spouseIndex: int|null, children: CoupleNode[]}>}
     */
    public function jsonSerialize(): array
    {
        return [
            'members'        => $this->members,
            'memberFamilies' => $this->memberFamilies,
        ];
    }
}
