/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

import * as d3 from "../d3";

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
        let root = d3.hierarchy(data, (person) => {
            let children = [];

            if (person.children && (person.children.length > 0)) {
                children.push(...person.children);
            }

            return children;
        });

        // Declares a tree layout and assigns the size
        const treeLayout = d3.tree()
            .nodeSize([this._configuration.orientation.nodeWidth, 0])
            .separation((left, right) => {
                // The left child has spouses (1 or more), add some space between the nodes
                if (typeof left.data.spouses !== "undefined") {
                    return 0.75;
                }

                // The right side is a spouse which is linked back to the actual child, so add some space
                if (typeof right.data.spouse !== "undefined") {
                    return 0.75;
                }

                // Single siblings and cousins should be close to each other
                return left.parent === right.parent ? 0.5 : 0.75;
            });

        // Map the root node data to the tree layout
        this._root  = root;
        this._nodes = treeLayout(root);
    }

    /**
     * Returns the nodes.
     *
     * @returns {Array}
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
     * @returns {Object}
     *
     * @public
     */
    get root()
    {
        return this._root;
    }
}
