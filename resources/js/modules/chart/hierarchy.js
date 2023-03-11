/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

import * as d3 from "../d3";
import OrientationLeftRight from "./orientation/orientation-leftRight.js";
import OrientationRightLeft from "./orientation/orientation-rightLeft.js";

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
     * @param {Object} data The JSON encoded chart data
     */
    init(data)
    {
        // Construct root node from the hierarchical data
        let root = d3.hierarchy(data);

        // Declares a tree layout and assigns the size
        const tree = d3.tree()
            .nodeSize([this._configuration.orientation.nodeWidth, this._configuration.orientation.nodeHeight])
            .separation((left, right) => this.separation(left, right));

        // Map the root node data to the tree layout
        this._root  = root;
        this._nodes = tree(root);

//         // TODO Calculate height of SVG
//         if ((this._configuration.orientation instanceof OrientationLeftRight)
//             || (this._configuration.orientation instanceof OrientationRightLeft)
//         ) {
//             let x0 = Infinity;
//             let x1 = -x0;
//
//             root.each(d => {
//                 if (d.x > x1) x1 = d.x;
//                 if (d.x < x0) x0 = d.x;
//             });
//         }
//
//         // const height = x1 - x0 + this._configuration.orientation.nodeHeight * 2;
// // console.log(height);

        // Normalize node coordinates (swap values for left/right layout)
        root.each((node) => {
            this._configuration.orientation.norm(node);
        });
    }

    /**
     * Returns the separation value.
     *
     * @param {Node} left
     * @param {Node} right
     *
     * @return {Number}
     */
    separation(left, right)
    {
        // The left child has spouses (1 or more), add some space between the nodes
        if (Object.hasOwn(left.data, 'spouses')) {
            return 1.25;
        }

        // The right side is a spouse that is linked back to the actual child, so add some space
        if (Object.hasOwn(right.data, 'spouse')) {
            return 1.25;
        }

        // Single siblings and cousins should be close
        // to each other if parents are the same
        return left.parent === right.parent ? 1.0 : 2.0;
    }

    /**
     * Returns the nodes.
     *
     * @returns {Individual}
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
