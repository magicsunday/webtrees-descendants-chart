/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../lib/d3.js";
import NodeDrawer from "../lib/tree/node-drawer.js";
import LinkDrawer from "../lib/tree/link-drawer.js";
import { COUSIN_GAP_PX, SIBLING_GAP_PX, SPOUSE_GAP_PX } from "../lib/constants.js";
import { familyRenderedWidth } from "../lib/family-tree.js";
import { buildConnections } from "../lib/tree/connection-builder.js";

/**
 * Lays out one descendants chart. The d3 hierarchy is a tree of
 * FamilyNodes (see family-tree.js); each node represents one
 * (real-person + 0..1 spouse + their children-as-FamilyNodes).
 *
 * After d3.tree() lays the family-nodes out, `connection-builder`
 * walks the result and turns it into pure-geometry payloads:
 *   - a flat list of person-box renderables
 *   - a list of FamilyConnection descriptors (father, mother, children,
 *     intermediate-boxes, marriage stagger)
 * The drawers consume those without ever touching the d3 hierarchy
 * themselves, so the line-drawing logic stays generic and independent
 * of where the boxes ended up.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Tree {
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
     * Returns the box dimension along the sibling/spread axis.
     */
    get _stackBox() {
        return this._orientation.isVertical
            ? this._orientation.boxWidth
            : this._orientation.boxHeight;
    }

    /**
     * Variable-width separation. Family-nodes that share the same `real`
     * sit at spouse-gap distance; same-parent siblings get sibling-gap;
     * cross-parent cousins get cousin-gap.
     */
    separation = (left, right) => {
        const baseline = this._stackBox;
        const widthLeft = familyRenderedWidth(left.data, baseline, SPOUSE_GAP_PX);
        const widthRight = familyRenderedWidth(right.data, baseline, SPOUSE_GAP_PX);

        let gap;
        if (sameRealId(left, right)) {
            gap = SPOUSE_GAP_PX;
        } else if (left.parent === right.parent) {
            gap = SIBLING_GAP_PX;
        } else {
            gap = COUSIN_GAP_PX;
        }

        return ((widthLeft + widthRight) / 2 + gap) / baseline;
    };

    draw(source) {
        const tree = d3
            .tree()
            .nodeSize([this._stackBox, this._orientation.nodeHeight])
            .separation(this.separation);

        tree(this._hierarchy.root);
        this._hierarchy.root.each((node) => {
            this._configuration.orientation.norm(node);
        });

        const { renderedBoxes, connections } = buildConnections(
            this._hierarchy.root,
            this._orientation,
            this._orientation.isVertical,
        );

        // Lines first so the boxes overlap any rounding-error stubs.
        this._linkDrawer.drawLinks(connections, source);
        this._nodeDrawer.drawNodes(renderedBoxes, source);
    }

    centerTree() {
        // TODO Doesn't work
        console.log("centerTree");
    }

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

function sameRealId(left, right) {
    if (!left.data || !right.data) return false;
    if (left.data.kind !== "family" || right.data.kind !== "family") return false;
    if (!left.data.real || !right.data.real) return false;
    return left.data.real.id === right.data.real.id;
}
