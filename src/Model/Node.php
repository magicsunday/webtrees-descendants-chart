<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information; please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Model;

use JsonSerializable;

/**
 * This class holds information about a node.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
class Node implements JsonSerializable
{
    /**
     * @var NodeData
     */
    protected NodeData $data;

    /**
     * The ID of the spouse.
     *
     * @var int
     */
    protected int $spouse = 0;

    /**
     * The ID of the family.
     *
     * @var int
     */
    protected int $family = 0;

    /**
     * The list of children.
     *
     * @var Node[]
     */
    protected array $children = [];

    /**
     * The list of all spouses.
     *
     * @var int[]
     */
    protected array $spouses = [];

    /**
     * Constructor.
     *
     * @param NodeData $data
     */
    public function __construct(NodeData $data)
    {
        $this->data = $data;
    }

    /**
     * @return NodeData
     */
    public function getData(): NodeData
    {
        return $this->data;
    }

    /**
     * @param int $spouse
     *
     * @return Node
     */
    public function setSpouse(int $spouse): Node
    {
        $this->spouse = $spouse;
        return $this;
    }

    /**
     * @param int $family
     *
     * @return Node
     */
    public function setFamily(int $family): Node
    {
        $this->family = $family;
        return $this;
    }

    /**
     * @return Node[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param Node[] $children
     *
     * @return Node
     */
    public function setChildren(array $children): Node
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @param int $spouse
     *
     * @return Node
     */
    public function addSpouse(int $spouse): Node
    {
        $this->spouses[] = $spouse;
        return $this;
    }

    /**
     * Returns the relevant data as an array.
     *
     * @return array<string, int|int[]|NodeData|Node[]>
     */
    public function jsonSerialize(): array
    {
        $jsonData = [
            'data'   => $this->data,
            'family' => $this->family,
        ];

        if ($this->spouse !== 0) {
            $jsonData['spouse'] = $this->spouse;
        }

        if (count($this->children) > 0) {
            $jsonData['children'] = $this->children;
        }

        if (count($this->spouses) > 0) {
            $jsonData['spouses'] = $this->spouses;
        }

        return $jsonData;
    }
}
