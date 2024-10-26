/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../lib/d3";
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
export default class Hierarchy
{
    /**
     * Constructor.
     *
     * @param {Configuration} configuration The application configuration
     */
    constructor(configuration)
    {
        this._configuration = configuration;
        this._nodes         = null;
        this._root          = null;
    }

    /**
     * Initialize the hierarchical chart data.
     *
     * @param {object} data The JSON encoded chart data
     */
    init(data)
    {
        // Adjust box height if we are going to display the alternative names
        if (this._configuration.showAlternativeName) {
            if ((this._configuration.orientation instanceof OrientationTopBottom)
                || (this._configuration.orientation instanceof OrientationBottomTop)
            ) {
                this._configuration.orientation.boxHeight += LAYOUT_VERTICAL_NODE_HEIGHT_OFFSET;
            }
        }

        // // Declares a tree layout and assigns the size
        // const treeLayout = d3.tree()
        //     .nodeSize([this._configuration.orientation.nodeWidth, 0])
        //     .separation(this.separation);

        // Construct root node from the hierarchical data
        this._root = d3.hierarchy(data);

        // Map the root node data to the tree layout
        // treeLayout(this._root);

        // Assign a unique ID to each node
        this._root.descendants().forEach((d, i) => {
            d.id = i;
        });

        // this._nodes = treeLayout(root);
        // this._nodes = this._root.descendants();
        //
        // // Remove the pseudo root node
        // this._nodes.shift();
    }

    /**
     * Returns the nodes.
     *
     * @returns {Individual[]}
     *
     * @public
     */
    get nodes()
    {
        return this._nodes;
    }

    /**
     * Returns the root note.
     *
     * @returns {Individual}
     *
     * @public
     */
    get root()
    {
        return this._root;
    }
}
