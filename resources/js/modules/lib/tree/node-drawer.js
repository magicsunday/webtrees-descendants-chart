/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import {SEX_FEMALE, SEX_MALE, SPOUSE_GAP_PX} from "../constants.js";
import * as d3 from "../d3.js";
import Name from "./name.js";
import Date from "./date.js";
import Image from "../chart/box/image.js";
import Text from "../chart/box/text.js";
import OrientationTopBottom from "../chart/orientation/orientation-topBottom.js";
import OrientationBottomTop from "../chart/orientation/orientation-bottomTop.js";

/**
 * The class handles the creation of the tree.
 *
 * Each d3 node represents one couple (1..N members). drawNodes() expands
 * every couple into per-member renderables before joining the SVG selection,
 * so the rest of the rendering code keeps the simple "one box per item"
 * shape it always had.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class NodeDrawer {
    /**
     * Constructor.
     *
     * @param {Svg}           svg
     * @param {Hierarchy}     hierarchy     The hierarchical data
     * @param {Configuration} configuration The configuration
     */
    constructor(svg, hierarchy, configuration) {
        this._svg = svg;
        this._hierarchy = hierarchy;
        this._configuration = configuration;
        this._orientation = this._configuration.orientation;

        this._image = new Image(this._orientation, 20);
        this._text = new Text(this._orientation, this._image);
        this._name = new Name(this._svg, this._orientation, this._image, this._text);
        this._date = new Date(this._svg, this._orientation, this._image, this._text);
    }

    /**
     * Returns the visual axis along which the members of one couple are
     * arranged. Vertical-flow charts spread members horizontally (axis "x");
     * horizontal-flow charts spread them vertically (axis "y").
     */
    get _spreadAxis() {
        return ((this._orientation instanceof OrientationTopBottom)
            || (this._orientation instanceof OrientationBottomTop))
            ? "x" : "y";
    }

    /**
     * Expands every couple node into per-member renderables, preserving the
     * `data.data` shape the rest of this class expects (sex/name/etc.).
     *
     * @param {Node[]} coupleNodes
     *
     * @return {Array<{id: string, x: number, y: number, data: {data: object, spouse: boolean}, _couple: Node, _memberIndex: number}>}
     */
    flattenCoupleNodes(coupleNodes) {
        // Members spread along the axis perpendicular to the chart's flow:
        // vertical-flow charts spread members horizontally (x, box dim = width);
        // horizontal-flow charts spread them vertically (y, box dim = height).
        const axis = this._spreadAxis;
        const stackBox = axis === "x"
            ? this._orientation.boxWidth
            : this._orientation.boxHeight;
        const gap = SPOUSE_GAP_PX;
        const renderables = [];

        for (const couple of coupleNodes) {
            const members = (couple.data && Array.isArray(couple.data.members))
                ? couple.data.members
                : [];

            if (members.length === 0) {
                continue;
            }

            const totalSpan = members.length * stackBox + (members.length - 1) * gap;
            const start = -(totalSpan - stackBox) / 2;

            members.forEach((memberData, memberIndex) => {
                const offset = start + memberIndex * (stackBox + gap);
                renderables.push({
                    id: `${couple.id}-${memberIndex}`,
                    x: axis === "x" ? couple.x + offset : couple.x,
                    y: axis === "y" ? couple.y + offset : couple.y,
                    data: {
                        data: memberData,
                        spouse: memberIndex > 0,
                    },
                    _couple: couple,
                    _memberIndex: memberIndex,
                });
            });
        }

        return renderables;
    }

    /**
     * Draw the person boxes.
     *
     * @param {Node[]} coupleNodes Array of couple-node descendants from d3.tree()
     * @param {object} source      The root object
     *
     * @public
     */
    drawNodes(coupleNodes, source) {
        // Image clip path
        this._svg
            .defs
            .append("clipPath")
            .attr("id", "clip-image")
            .append("rect")
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height);

        const renderables = this.flattenCoupleNodes(coupleNodes);

        this._svg.visual
            .selectAll("g.person")
            .data(renderables, person => person.id)
            .join(
                enter => this.nodeEnter(enter, source),
                update => this.nodeUpdate(update),
                exit => this.nodeExit(exit, source),
            );

        // Stash the old positions for transition
        this._hierarchy.root.eachBefore(d => {
            d.x0 = d.x;
            d.y0 = d.y;
        });
    }

    /**
     * Enter transition (new nodes).
     *
     * @param {Selection}  enter
     * @param {Individual} source
     *
     * @private
     */
    nodeEnter(enter, _source) {
        enter
            .append("g")
            .attr("opacity", 0)
            .attr("class", "person")
            .attr("transform", (person) => {
                return `translate(${person.x},${person.y})`;
            })
            .call(
                // Draw the actual person rectangle with opacity of 0.5
                g => {
                    g.append("rect")
                        .attr(
                            "class",
                            person => (person.data.data.sex === SEX_FEMALE)
                                ? "female"
                                : (person.data.data.sex === SEX_MALE) ? "male" : "unknown",
                        )
                        .classed("spouse", person => person.data.spouse)
                        .attr("rx", 20)
                        .attr("ry", 20)
                        .attr("x", -(this._orientation.boxWidth / 2))
                        .attr("y", -(this._orientation.boxHeight / 2))
                        .attr("width", this._orientation.boxWidth)
                        .attr("height", this._orientation.boxHeight)
                        .attr("fill-opacity", 0.5);

                    g.append("title")
                        .text(person => person.data.data.name);
                },
            )
            .call(
                // Draws the node (including image, names and dates)
                g => this.drawNode(g),
            )
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    .attr("opacity", 1),
            );
    }

    /**
     * Update transition (existing nodes).
     *
     * @param {Selection} update
     *
     * @private
     */
    nodeUpdate(update) {
        update
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    .attr("opacity", 1)
                    .attr("transform", (person) => {
                        return `translate(${person.x},${person.y})`;
                    }),
            );
    }

    /**
     * Exit transition (nodes to be removed).
     *
     * @param {Selection}  exit
     * @param {Individual} source
     *
     * @private
     */
    nodeExit(exit, source) {
        exit
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    .attr("opacity", 0)
                    .attr("transform", () => {
                        // Transition exit nodes to the source's position
                        return `translate(${source.x0 ?? 0},${source.y0 ?? 0})`;
                    })
                    .remove(),
            );
    }

    /**
     * Draws the image and text nodes.
     *
     * @param {Selection} parent The parent element to which the elements are to be attached
     *
     * @private
     */
    drawNode(parent) {
        const enter = parent.selectAll("g.image")
            .data((d) => {
                const images = [];

                if (d.data.data.thumbnail) {
                    images.push({
                        image: d.data.data.thumbnail,
                    });
                }

                return images;
            })
            .enter();

        const group = enter.append("g")
            .attr("class", "image");

        // Background of image (only required if thumbnail has transparency (like the silhouettes))
        group
            .append("rect")
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("fill", "rgb(255, 255, 255)");

        // The individual image
        group
            .append("image")
            .attr("href", (d) => d.image)
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("clip-path", "url(#clip-image)");

        // Border around image
        group
            .append("rect")
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("fill", "none")
            .attr("stroke", "rgb(200, 200, 200)")
            .attr("stroke-width", 1.5);

        this._name.appendName(parent);
        this._date.appendDate(parent);
    }
}
