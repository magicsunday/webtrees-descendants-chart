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

            if (person.families) {
                person.families.forEach((family) => {
                    if (this._configuration.hideSpouses) {
                        family.spouse = null;
                    }

                    if (family.children && (family.children.length > 0)) {
                        return children.push(...family.children);
                    }
                });
            }

            return children;
        });

        // Declares a tree layout and assigns the size
        const treeLayout = d3.tree()
            .nodeSize([this._configuration.orientation.nodeWidth, 0])
            .separation((left, right) => {
                // Left or right has families
                if (left.data.families || right.data.families) {
                    let value = 0.5;

                    if (left.data.families) {
                        const listOfSpouses = left.data.families.filter(family => !!family.spouse);

                        // Add some offset for each assigned spouse
                        value += 0.5 + ((listOfSpouses.length - 1) * 0.5);
                    }

                    if (right.data.families) {
                        const listOfSpouses = right.data.families.filter(family => !!family.spouse);

                        // Add some offset for each assigned spouse
                        value += 0.5 + ((listOfSpouses.length - 1) * 0.5);
                    }

                    return value;
                }

                // Single siblings and cousins should be close to each other
                return 0.5;
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
