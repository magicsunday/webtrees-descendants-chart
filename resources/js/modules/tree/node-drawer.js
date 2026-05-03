/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { SEX_FEMALE, SEX_MALE } from "../constants.js";
import Name from "./name.js";
import DateRenderer from "./date.js";
import ImageBox from "../chart/box/image.js";
import TextBox from "../chart/box/text.js";

/**
 * @import { Selection } from "d3-selection"
 * @import Svg from "../chart/svg.js"
 * @import Hierarchy from "../hierarchy.js"
 * @import Configuration from "../configuration.js"
 */

/**
 * Renders one person box per entry in the renderedBoxes list emitted by
 * `connection-builder.js`. Each entry is already pre-positioned and
 * carries the per-person `data` shape the rest of this class expects.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class NodeDrawer {
    /**
     * @param {Svg}           svg
     * @param {Hierarchy}     hierarchy     The hierarchical data
     * @param {Configuration} configuration The configuration
     */
    constructor(svg, hierarchy, configuration) {
        this._svg = svg;
        this._hierarchy = hierarchy;
        this._configuration = configuration;
        this._orientation = this._configuration.orientation;

        this._image = new ImageBox(this._orientation, 20);
        this._text = new TextBox(this._orientation, this._image);
        this._name = new Name(this._svg, this._orientation, this._image, this._text);
        this._date = new DateRenderer(this._svg, this._orientation, this._image, this._text);
    }

    /**
     * Draw the person boxes.
     *
     * @param {Array} renderedBoxes Pre-positioned box descriptors from connection-builder
     * @param {object} source       The root object
     *
     * @public
     */
    drawNodes(renderedBoxes, source) {
        // Image clip path
        this._svg.defs
            .append("clipPath")
            .attr("id", "clip-image")
            .append("rect")
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height);

        this._svg.visual
            .selectAll("g.person")
            .data(renderedBoxes, (person) => person.id)
            .join(
                (enter) => this.nodeEnter(enter, source),
                (update) => this.nodeUpdate(update),
                (exit) => this.nodeExit(exit, source),
            );

        // Stash the old positions for transition. d3 HierarchyNode does not
        // declare x0/y0 — they are descendants-specific scratch props.
        this._hierarchy.root.eachBefore((node) => {
            const d = /** @type {any} */ (node);
            d.x0 = d.x;
            d.y0 = d.y;
        });
    }

    /**
     * Enter transition (new nodes).
     *
     * @param {Selection<any, any, any, any>}  enter
     * @param {Individual} _source
     *
     * @private
     */
    nodeEnter(enter, _source) {
        return enter
            .append("g")
            .attr("opacity", 0)
            .attr("class", "person")
            .attr("transform", (person) => {
                return `translate(${person.x},${person.y})`;
            })
            .call(
                // Draw the actual person rectangle with opacity of 0.5
                (g) => {
                    g.append("rect")
                        .attr("class", (person) =>
                            person.data.data.sex === SEX_FEMALE
                                ? "female"
                                : person.data.data.sex === SEX_MALE
                                  ? "male"
                                  : "unknown",
                        )
                        .classed("spouse", (person) => person.data.spouse)
                        .attr("rx", 20)
                        .attr("ry", 20)
                        .attr("x", -(this._orientation.boxWidth / 2))
                        .attr("y", -(this._orientation.boxHeight / 2))
                        .attr("width", this._orientation.boxWidth)
                        .attr("height", this._orientation.boxHeight)
                        .attr("fill-opacity", 0.5);

                    g.append("title").text((person) => person.data.data.name);
                },
            )
            .call(
                // Draws the node (including image, names and dates)
                (g) => this.drawNode(g),
            )
            .call((g) => g.transition().duration(this._configuration.duration).attr("opacity", 1));
    }

    /**
     * Update transition (existing nodes).
     *
     * @param {Selection<any, any, any, any>} update
     *
     * @private
     */
    nodeUpdate(update) {
        return update.call((g) =>
            g
                .transition()
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
     * @param {Selection<any, any, any, any>} exit
     * @param {Individual}                    source
     *
     * @private
     */
    nodeExit(exit, source) {
        const src = /** @type {any} */ (source);
        exit.call((g) =>
            g
                .transition()
                .duration(this._configuration.duration)
                .attr("opacity", 0)
                .attr("transform", () => {
                    // Transition exit nodes to the source's position
                    return `translate(${src.x0 ?? 0},${src.y0 ?? 0})`;
                })
                .remove(),
        );
    }

    /**
     * Draws the image and text nodes.
     *
     * @param {Selection<any, any, any, any>} parent The parent element to which the elements are to be attached
     *
     * @private
     */
    drawNode(parent) {
        const enter = parent
            .selectAll("g.image")
            .data((d) => {
                const images = [];

                if (d.data.data.thumbnail) {
                    images.push({
                        image: d.data.data.thumbnail,
                        silhouette: d.data.data.silhouette,
                    });
                }

                return images;
            })
            .enter();

        const group = enter.append("g").attr("class", "image");

        // Plain white background behind the (possibly letterboxed)
        // foreground photo so the boundary against the box is uniform.
        group
            .append("rect")
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("fill", "#FFFFFF");

        // The individual image (crisp foreground). On load failure (broken
        // thumbnail URL, deleted media, network error) swap in the silhouette
        // — otherwise the box renders an empty/broken-image marker that the
        // user can't recover from without reloading.
        group
            .append("image")
            .attr("href", (d) => d.image)
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("clip-path", "url(#clip-image)")
            .on("error", function (_event, d) {
                if (d.silhouette) {
                    this.setAttribute("href", d.silhouette);
                }
            });

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
