/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../lib/d3";
import NodeDrawer from "../lib/tree/node-drawer";
import LinkDrawer from "../lib/tree/link-drawer";
import OrientationTopBottom from "../lib/chart/orientation/orientation-topBottom";
import OrientationBottomTop from "../lib/chart/orientation/orientation-bottomTop";
import {LAYOUT_VERTICAL_NODE_HEIGHT_OFFSET} from "../lib/constants.js";

/**
 * The class handles the creation of the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Tree
{
    /**
     * Constructor.
     *
     * @param {Svg}           svg
     * @param {Configuration} configuration The configuration
     * @param {Hierarchy}     hierarchy     The hierarchical data
     */
    constructor(svg, configuration, hierarchy)
    {
        this._svg           = svg;
        this._configuration = configuration;
        this._hierarchy     = hierarchy;

        this._hierarchy.root.x0 = 0;
        this._hierarchy.root.y0 = 0;

        this._orientation = this._configuration.orientation;

        this._nodeDrawer = new NodeDrawer(this._svg, this._hierarchy, this._configuration);
        this._linkDrawer = new LinkDrawer(this._svg, this._configuration);

        this.draw(this._hierarchy.root);
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

        // The right side is a spouse linked back to the actual child, so add some space
        if (Object.hasOwn(right.data, 'spouse')) {
            return 1.25;
        }

        // Single siblings and cousins should be close
        // to each other if parents are the same
        return left.parent === right.parent ? 1.0 : 2.0;
    }

    /**
     * Draw the tree.
     *
     * @param {Individual} source The root object
     *
     * @public
     */
    draw(source)
    {
        // Declares a tree layout and assigns the size
        const tree = d3.tree()
            .nodeSize([this._configuration.orientation.nodeWidth, this._configuration.orientation.nodeHeight])
            .separation(this.separation);

        // Map the root node data to the tree layout
        this._nodes = tree(this._hierarchy.root);

        // Normalize node coordinates (swap values for left/right layout)
        this._hierarchy.root.each((node) => {
            this._configuration.orientation.norm(node);
        });

        /** @type {Individual[]} */
        let nodes = this._hierarchy.root.descendants();
        let links = [];

        // Remove the pseudo root node
        nodes.shift();

        // Arrange the position of the individual with the first spouse so that they are close to each other.
        // Since the line of connection between the individual and the first spouse begins between these two,
        // this prevents the lines of connection from overlapping with other children and their partners.
        nodes.forEach((node) => {
            if (node.data
                && Array.isArray(node.data.spouses)
                && (node.data.spouses.length >= 1)
            ) {
                // Find the first spouse
                const spouse = this.findSpouseById(node.data.spouses[0], nodes);

                // Position the node directly above the first spouse
                if ((this._orientation instanceof OrientationTopBottom)
                    || (this._orientation instanceof OrientationBottomTop)
                ) {
                    node.x = spouse.x - this._orientation.nodeWidth;
                } else {
                    node.y = spouse.y - this._orientation.nodeWidth;
                }
            }
        });

        // Find the first node with children
        const firstNodeWithChildren = nodes.find(node => node.children && (node.children.length > 0));

        // Center the children between the individual and the spouse
        if (typeof firstNodeWithChildren !== "undefined") {
            const moveBy = this._orientation.nodeWidth / 2;

            firstNodeWithChildren.each((node) => {
                if (
                    Object.hasOwn(node.data, "spouse")
                    && (node.data.spouse !== null)
                    && Object.hasOwn(node, "children")
                    && Array.isArray(node.children)
                    && (node.children.length >= 1)
                ) {
                    this.moveChildren(node, moveBy);
                }
            });
        }

        // Create a list of links between source (node and spouses) and target nodes (children).
        nodes.forEach((node) => {
            const spouse = this.findSpouseById(node.data.spouse, nodes);

            // Process children
            if (Object.hasOwn(node, "children")
                && Array.isArray(node.children)
                && (node.children.length > 0)
            ) {
                node.children.forEach((child) => {
                    // Only add links between real children
                    if ((typeof child.data.spouse === "undefined") || (child.data.spouse === null)) {
                        links.push({
                            spouse: spouse,
                            source: node,
                            target: child,
                            coords: null
                        });
                    }
                });
            }

            if (typeof spouse !== "undefined") {
                let spousesCoords = null;

                // To draw only the respective intermediate lines, we need the information
                // about the position of the previous spouses in the row. The coordinates are attached
                // to the respective link as additional values so that they are available later when
                // calculating the line points.
                if ((typeof spouse.data.spouses !== "undefined") && (spouse.data.spouses.length > 0)) {
                    const indexOfSpouse = spouse.data.spouses.indexOf(node.data.data.id);
                    const spousesBefore = spouse.data.spouses.slice(0, indexOfSpouse);

                    if (spousesBefore.length > 0) {
                        spousesCoords = [];

                        spousesBefore.forEach((id) => {
                            // Find matching spouse in the list of all nodes
                            const spouseBefore = this.findSpouseById(id, nodes);

                            // Keep track of the coordinates
                            if (typeof spouseBefore !== "undefined") {
                                spousesCoords.push({
                                    x: spouseBefore.x,
                                    y: spouseBefore.y
                                })
                            }
                        });
                    }
                }

                if (node.data.data !== null) {
                    // Add link between individual and spouse
                    links.push({
                        spouse: spouse,
                        source: node,
                        target: null,
                        coords: spousesCoords
                    });
                }
            }
        });

        // To avoid artifacts caused by rounding errors when drawing the links,
        // we draw them first so that the nodes can then overlap them.
        this._linkDrawer.drawLinks(links, source);
        this._nodeDrawer.drawNodes(nodes, source);
    }

    /**
     * Finds the related spouse in a list of individuals for the individual ID passed.
     *
     * @param {Number}       id
     * @param {Individual[]} individuals
     *
     * @return {Individual}
     *
     * @private
     */
    findSpouseById(id, individuals)
    {
        return individuals.find(
            (spouse) => {
                return (spouse.data.data.id === id);
            }
        );
    }

    /**
     * Moves all child nodes by the specified amount.
     *
     * @param {Individual} individual The individual whose children are to be moved
     * @param {Number}     moveBy     The amount by which to move the child nodes
     *
     * @private
     */
    moveChildren(individual, moveBy)
    {
        individual.each((child) => {
            if (child.depth !== individual.depth) {
                // - first family only
                // - if more than one child
                // - if child has children too
                if (
                    (individual.data.family === 0)
                    || (individual.children.length !== 1)
                    || (typeof child.children !== "undefined")
                ) {
                    if ((this._orientation instanceof OrientationTopBottom)
                        || (this._orientation instanceof OrientationBottomTop)
                    ) {
                        child.x -= moveBy;
                    } else {
                        child.y -= moveBy;
                    }
                }
            }
        });
    }

    // /**
    //  * Draw the tree.
    //  *
    //  * @public
    //  */
    // update(source)
    // {
    //     let nodes = this._hierarchy.nodes.descendants();
    //     let links = this._hierarchy.nodes.links();
    //
    //     // // Start with only the first few generations of ancestors showing
    //     // nodes.forEach((person) => {
    //     //     if (person.children) {
    //     //         person.children.forEach((child) => this.collapse(child));
    //     //     }
    //     // });
    //
    //     this.drawLinks(links, source);
    //     this.drawNodes(nodes, source);
    //
    //     // Stash the old positions for transition.
    //     nodes.forEach((person) => {
    //         person.x0 = person.x;
    //         person.y0 = person.y;
    //     });
    // }

    /**
     * Centers the tree around all visible nodes.
     */
    centerTree()
    {
        // TODO Doesn't work

console.log("centerTree");
        // const zoom = this._svg.zoom.get();
        //
        // d3.select(this._svg)
        //     // .transition()
        //     // .duration(0)
        //     // .delay(100)
        //     .call(
        //         zoom.transform,
        //         d3.zoomIdentity.translate(t.x, t.y).scale(t.k)
        //     );
    }

    /**
     * Update a person's state when they are clicked.
     *
     * @param {Event}  event
     * @param {Person} person The person object containing the individual data
     */
    togglePerson(event, person)
    {
        if (person.children) {
            // Collapse
            person._children = person.children;
            person.children = null;
        } else {
            // Expand
            person.children = person._children;
            person._children = null;
        }

        this.draw(person);
    }
}
