/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../lib/d3.js";
import {LAYOUT_VERTICAL_NODE_HEIGHT_OFFSET} from "../lib/constants.js";
import {buildFamilyTree} from "../lib/family-tree.js";

/**
 * This class handles the hierarchical data.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Hierarchy {
    /**
     * Constructor.
     *
     * @param {Configuration} configuration The application configuration
     */
    constructor(configuration) {
        this._configuration = configuration;
        this._root = null;
    }

    /**
     * Initialize the hierarchical chart data.
     *
     * The wire-format CoupleNode is converted to a tree of FamilyNodes —
     * each (real-person + 0..1 spouse + their children-as-FamilyNodes).
     * Polygamous individuals contribute multiple FamilyNodes that share
     * the same `real`. d3.tree() then centres each family-node directly
     * over its own children.
     *
     * @param {object} data The JSON encoded chart data
     */
    init(data) {
        // Adjust box height if we are going to display the alternative names
        if (this._configuration.showAlternativeName && this._configuration.orientation.isVertical) {
            this._configuration.orientation.boxHeight += LAYOUT_VERTICAL_NODE_HEIGHT_OFFSET;
        }

        const familyTree = buildFamilyTree(data);
        this._root = d3.hierarchy(familyTree);

        // Assign a unique ID to each node
        this._root.descendants().forEach((d, i) => {
            d.id = i;
        });
    }

    /**
     * Returns the root node of the d3 hierarchy.
     *
     * @returns {Node}
     *
     * @public
     */
    get root() {
        return this._root;
    }
}
