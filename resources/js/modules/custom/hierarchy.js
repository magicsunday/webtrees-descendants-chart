/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../lib/d3.js";
import OrientationTopBottom from "../lib/chart/orientation/orientation-topBottom.js";
import OrientationBottomTop from "../lib/chart/orientation/orientation-bottomTop.js";
import {LAYOUT_VERTICAL_NODE_HEIGHT_OFFSET} from "../lib/constants.js";

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
        this._nodes = null;
        this._root = null;
    }

    /**
     * Initialize the hierarchical chart data.
     *
     * Each node represents one family — see PHP `CoupleNode`. The d3-children
     * of a family node is the concatenation of every `memberFamilies[].children`
     * entry, so d3.tree() lays the descendants out under the couple as a whole
     * (no per-spouse subtree positioning).
     *
     * @param {object} data The JSON encoded chart data
     */
    init(data) {
        // Adjust box height if we are going to display the alternative names
        if (this._configuration.showAlternativeName) {
            if ((this._configuration.orientation instanceof OrientationTopBottom)
                || (this._configuration.orientation instanceof OrientationBottomTop)
            ) {
                this._configuration.orientation.boxHeight += LAYOUT_VERTICAL_NODE_HEIGHT_OFFSET;
            }
        }

        this._root = d3.hierarchy(
            data,
            (datum) => {
                if (!datum || !Array.isArray(datum.memberFamilies)) {
                    return null;
                }

                const children = [];
                for (const family of datum.memberFamilies) {
                    if (Array.isArray(family.children)) {
                        for (const child of family.children) {
                            children.push(child);
                        }
                    }
                }

                return children.length > 0 ? children : null;
            }
        );

        // Assign a unique ID to each node
        this._root.descendants().forEach((d, i) => {
            d.id = i;
        });
    }

    /**
     * Returns the nodes.
     *
     * @returns {Individual[]}
     *
     * @public
     */
    get nodes() {
        return this._nodes;
    }

    /**
     * Returns the root note.
     *
     * @returns {Individual}
     *
     * @public
     */
    get root() {
        return this._root;
    }
}
