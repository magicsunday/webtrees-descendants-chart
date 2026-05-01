/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../lib/d3.js";
import NodeDrawer from "../lib/tree/node-drawer.js";
import LinkDrawer from "../lib/tree/link-drawer.js";
import OrientationTopBottom from "../lib/chart/orientation/orientation-topBottom.js";
import OrientationBottomTop from "../lib/chart/orientation/orientation-bottomTop.js";
import {COUSIN_GAP_PX, SIBLING_GAP_PX, SPOUSE_GAP_PX} from "../lib/constants.js";

/**
 * The class handles the creation of the tree.
 *
 * Each d3 node represents one family ("couple node") that holds 1..N members
 * (real-person + 0..N spouses). d3.tree() lays out the couple nodes; the
 * renderer expands each node into its individual person boxes at draw time.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Tree {
    /**
     * Constructor.
     *
     * @param {Svg}           svg
     * @param {Configuration} configuration The configuration
     * @param {Hierarchy}     hierarchy     The hierarchical data
     */
    constructor(svg, configuration, hierarchy) {
        this._svg = svg;
        this._configuration = configuration;
        this._hierarchy = hierarchy;

        this._hierarchy.root.x0 = 0;
        this._hierarchy.root.y0 = 0;

        this._orientation = this._configuration.orientation;

        this._nodeDrawer = new NodeDrawer(this._svg, this._hierarchy, this._configuration);
        this._linkDrawer = new LinkDrawer(this._svg, this._configuration);

        this.draw(this._hierarchy.root);
    }

    /**
     * Returns the box dimension along the sibling/spread axis. For vertical
     * layouts that's `boxWidth`, for horizontal layouts the boxes stack along
     * y so we use `boxHeight`. d3-tree's `nodeSize.x` works in this same axis
     * after the orientation's `norm()` swap.
     */
    get _stackBox() {
        return ((this._orientation instanceof OrientationTopBottom)
            || (this._orientation instanceof OrientationBottomTop))
            ? this._orientation.boxWidth
            : this._orientation.boxHeight;
    }

    /**
     * The width (along the sibling axis) occupied by a couple node when
     * rendered. Singletons take one box; couples take two boxes plus the
     * inner spouse gap; trios add another (box + gap), and so on.
     */
    coupleWidth(node) {
        const memberCount = (node.data && Array.isArray(node.data.members))
            ? node.data.members.length
            : 1;
        const box = this._stackBox;
        return box + Math.max(0, memberCount - 1) * (box + SPOUSE_GAP_PX);
    }

    /**
     * Variable-width separation. Returned in `nodeSize.x` units which we set
     * to `_stackBox`. Math: required centre-to-centre distance equals the
     * sum of each node's half-width plus the appropriate sibling/cousin gap.
     * d3.tree()'s apportion() step still handles wider sub-tree contours on
     * top, so this is the minimum buffer rather than a collision-avoidance
     * value.
     */
    separation = (left, right) => {
        const baseline = this._stackBox;
        const halfLeft  = this.coupleWidth(left)  / 2;
        const halfRight = this.coupleWidth(right) / 2;
        const gap = (left.parent === right.parent) ? SIBLING_GAP_PX : COUSIN_GAP_PX;
        return (halfLeft + halfRight + gap) / baseline;
    };

    /**
     * Draw the tree.
     *
     * @param {Individual} source The root object
     *
     * @public
     */
    draw(source) {
        // nodeSize.x = boxWidth (160) is the baseline against which separation()
        // returns multipliers; nodeSize.y stays at the orientation default for
        // generation spacing.
        const tree = d3.tree()
            .nodeSize([this._orientation.boxWidth, this._orientation.nodeHeight])
            .separation(this.separation);

        this._nodes = tree(this._hierarchy.root);

        // Normalize node coordinates (swap values for left/right layout)
        this._hierarchy.root.each((node) => {
            this._configuration.orientation.norm(node);
        });

        /** @type {Node[]} */
        const nodes = this._hierarchy.root.descendants();

        // Build the link list. Two kinds:
        //   "elbow"    — parent → child couple, originates at the matching
        //                family's spouse position (so polygamous families
        //                each get their own line bundle)
        //   "marriage" — straight line between consecutive members of a couple
        //
        // Children are flattened into `node.children` by the hierarchy
        // accessor in the same order as `memberFamilies[*].children`, so we
        // walk both lists in parallel to recover the family-of-origin.
        const links = [];
        nodes.forEach((node) => {
            const memberCount = (node.data && Array.isArray(node.data.members))
                ? node.data.members.length
                : 0;

            // Marriage lines: one per pair (real-person, members[k]) so each
            // spouse has its own line back to the real-person, even in
            // polygamous couples. The k-index is also used by the renderer to
            // stagger the lines so multiple marriages don't overlap.
            for (let k = 1; k < memberCount; k++) {
                links.push({
                    kind:         "marriage",
                    couple:       node,
                    spouseIdx:    k,
                    spouseCount:  memberCount - 1,
                });
            }

            const dChildren = Array.isArray(node.children) ? node.children : [];
            if (dChildren.length === 0) {
                return;
            }

            const families = (node.data && Array.isArray(node.data.memberFamilies))
                ? node.data.memberFamilies
                : [{ family: 0, spouseIndex: null, children: dChildren.map((c) => c.data) }];

            let cursor = 0;
            families.forEach((family, familyOrder) => {
                const familyChildCount = Array.isArray(family.children)
                    ? family.children.length
                    : 0;

                for (let i = 0; i < familyChildCount; i++) {
                    const target = dChildren[cursor + i];
                    if (target) {
                        links.push({
                            kind:        "elbow",
                            source:      node,
                            target:      target,
                            spouseIndex: family.spouseIndex,
                            familyOrder: familyOrder,
                            familyCount: families.length,
                        });
                    }
                }

                cursor += familyChildCount;
            });
        });

        // Draw lines first so the person boxes overlap any rounding-error stubs.
        this._linkDrawer.drawLinks(links, source);
        this._nodeDrawer.drawNodes(nodes, source);
    }

    /**
     * Centers the tree around all visible nodes.
     */
    centerTree() {
        // TODO Doesn't work
        console.log("centerTree");
    }

    /**
     * Update a person's state when they are clicked.
     *
     * @param {Event}  event
     * @param {Person} person The person object containing the individual data
     */
    togglePerson(_event, person) {
        if (person.children) {
            person._children = person.children;
            person.children = null;
        } else {
            person.children = person._children;
            person._children = null;
        }

        this.draw(person);
    }
}
