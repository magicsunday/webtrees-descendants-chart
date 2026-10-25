/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "./d3.js";
import NodeDrawer from "./tree/node-drawer.js";
import LinkDrawer from "./tree/link-drawer.js";
import { SPOUSE_GAP_PX } from "./constants.js";
import { familyRenderedWidth } from "./family-tree.js";
import { pickGap } from "./separation.js";
import { buildConnections } from "./tree/connection-builder.js";

/**
 * @import { HierarchyPointNode, TreeLayout } from "d3-hierarchy"
 * @import { LinkedTreeSvg as Svg } from "@magicsunday/webtrees-chart-lib/linked-tree"
 * @import Hierarchy from "./hierarchy.js"
 * @import Configuration from "./configuration.js"
 */

/**
 * Lays out one descendants chart and draws it once on construction. The d3
 * hierarchy is a tree of FamilyNodes (see family-tree.js), each representing
 * one real person, at most one spouse and the families of their children.
 *
 * After d3.tree() lays the family-nodes out, `connection-builder` walks the
 * result and turns it into pure-geometry payloads:
 *   - a flat list of person-box renderables
 *   - a list of FamilyConnection descriptors (father, mother, children,
 *     intermediate-boxes, marriage stagger)
 * The drawers consume those without ever touching the d3 hierarchy themselves,
 * so the line-drawing logic stays generic and independent of where the boxes
 * ended up. The class also supplies the variable-width separation that keeps
 * spouses, siblings and cousins apart.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Tree {
    /**
     * @param {Svg}           svg
     * @param {Configuration} configuration The configuration
     * @param {Hierarchy}     hierarchy     The hierarchical data
     */
    constructor(svg, configuration, hierarchy) {
        this._svg = svg;
        this._configuration = configuration;
        this._hierarchy = hierarchy;

        this._orientation = this._configuration.orientation;

        this._nodeDrawer = new NodeDrawer(this._svg, this._configuration);
        this._linkDrawer = new LinkDrawer(this._svg, this._configuration);

        this.draw();
    }

    /**
     * Returns the box dimension along the sibling/spread axis.
     */
    get _stackBox() {
        return this._orientation.isVertical
            ? this._orientation.boxWidth
            : this._orientation.boxHeight;
    }

    /**
     * Variable-width separation. Family-nodes that share the same `real` sit at
     * spouse-gap distance; same-parent siblings get sibling-gap; cross-parent
     * cousins get cousin-gap. Half-siblings (children of polygamy partners that
     * share one biological parent) are treated as siblings so the polygamy
     * parent row doesn't get pushed apart by the cousin-gap propagating up from
     * the children.
     *
     * @param {HierarchyPointNode<FamilyTreeNode>} left  The left-hand family-node
     * @param {HierarchyPointNode<FamilyTreeNode>} right The right-hand family-node
     *
     * @return {number}
     */
    separation = (left, right) => {
        const baseline = this._stackBox;
        const widthLeft = familyRenderedWidth(left.data, baseline, SPOUSE_GAP_PX);
        const widthRight = familyRenderedWidth(right.data, baseline, SPOUSE_GAP_PX);
        const gap = pickGap(left, right);

        return ((widthLeft + widthRight) / 2 + gap) / baseline;
    };

    /**
     * Lays out the tree and draws its links and person boxes.
     */
    draw() {
        /** @type {TreeLayout<FamilyTreeNode>} */
        const tree = d3
            .tree()
            .nodeSize([this._stackBox, this._orientation.nodeHeight])
            .separation(this.separation);

        // d3.tree() lays the root out in place and returns that same node.
        const root = tree(this._hierarchy.root);
        root.each((node) => {
            this._configuration.orientation.norm(node);
        });

        const { renderedBoxes, connections } = buildConnections(
            root,
            this._orientation,
            this._orientation.isVertical,
        );

        // Lines first so the boxes overlap any rounding-error stubs.
        this._linkDrawer.drawLinks(connections);
        this._nodeDrawer.drawNodes(renderedBoxes);
    }
}
