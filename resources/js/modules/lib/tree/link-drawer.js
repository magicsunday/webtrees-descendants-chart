/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../d3.js";
import OrientationTopBottom from "../chart/orientation/orientation-topBottom.js";
import OrientationBottomTop from "../chart/orientation/orientation-bottomTop.js";
import {MARRIAGE_STAGGER_PX, SPOUSE_GAP_PX} from "../constants.js";

/**
 * The class handles the creation of the tree connecting paths.
 *
 * Two link kinds are produced by `Tree.draw()`:
 *   - "elbow"    — parent couple → child couple, drawn via the orientation's
 *                  orthogonal elbow function
 *   - "marriage" — straight segment connecting two adjacent members of the
 *                  same couple, computed locally below
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class LinkDrawer {
    /**
     * Constructor.
     *
     * @param {Svg}           svg
     * @param {Configuration} configuration The configuration
     */
    constructor(svg, configuration) {
        this._svg = svg;
        this._configuration = configuration;
        this._orientation = this._configuration.orientation;
    }

    /**
     * Returns true when the active orientation lays generations along the
     * vertical axis, i.e. couples spread horizontally inside one row.
     */
    get _isVerticalLayout() {
        return (this._orientation instanceof OrientationTopBottom)
            || (this._orientation instanceof OrientationBottomTop);
    }

    /**
     * Builds the SVG path for a marriage line linking two adjacent members
     * of one couple. Stops a few px before each box edge so the line doesn't
     * visually merge with the box border.
     *
     * @param {object} link `{ kind: "marriage", couple, leftIdx }`
     *
     * @returns {string}
     */
    /**
     * Marriage from `members[0]` (real-person) to `members[spouseIdx]`,
     * drawn as a chain of short segments through every inter-box gap on
     * the way. This avoids running the line through the box rectangles
     * (which would be hidden by them) and matches the pre-refactor visual
     * pattern where every marriage was a discrete, fully-visible path.
     *
     * Each marriage gets its own cross-axis stagger so simultaneous
     * marriages of one real-person stay distinguishable.
     *
     * @param {{couple: object, spouseIdx: number, spouseCount: number}} link
     */
    marriagePath(link) {
        const couple = link.couple;
        const spouseIdx = link.spouseIdx;
        const stackBox = this._isVerticalLayout
            ? this._orientation.boxWidth
            : this._orientation.boxHeight;
        const gap = SPOUSE_GAP_PX;
        const memberCount = couple.data.members.length;

        const stride = stackBox + gap;
        const start = -((memberCount - 1) * stride) / 2;
        // Stagger from 0 (first spouse, line in main gap) and grow outward
        // for each additional spouse.
        const stagger = MARRIAGE_STAGGER_PX * (spouseIdx - 1);

        const path = d3.path();
        const lineEndOffset = 2;

        // Walk through every inter-box gap from member[0] up to member[spouseIdx],
        // emitting one short segment per gap so the line never crosses a box.
        for (let i = 0; i < spouseIdx; i++) {
            const leftCentre = start + i * stride;
            const rightCentre = leftCentre + stride;
            const segStart = leftCentre + (stackBox / 2) + lineEndOffset;
            const segEnd   = rightCentre - (stackBox / 2) - lineEndOffset;

            if (this._isVerticalLayout) {
                path.moveTo(couple.x + segStart, couple.y + stagger);
                path.lineTo(couple.x + segEnd,   couple.y + stagger);
            } else {
                path.moveTo(couple.x + stagger, couple.y + segStart);
                path.lineTo(couple.x + stagger, couple.y + segEnd);
            }
        }

        return path.toString();
    }

    /**
     * Draw the connecting lines.
     *
     * @param {Link[]}     links  Array of links (mixed elbow + marriage)
     * @param {Individual} source The root object
     *
     * @public
     */
    drawLinks(links, source) {
        this._svg.visual
            .selectAll("path.link")
            .data(links)
            .join(
                enter => this.linkEnter(enter, source),
                update => this.linkUpdate(update),
                exit => this.linkExit(exit, source),
            );
    }

    /**
     * Enter transition (new links).
     *
     * @param {Selection}  enter
     * @param {Individual} source
     *
     * @private
     */
    linkEnter(enter, _source) {
        enter
            .append("path")
            .classed("link", true)
            .classed("marriage", link => link.kind === "marriage")
            .attr("d", link => link.kind === "marriage"
                ? this.marriagePath(link)
                : this._orientation.elbow(link))
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    .attr("opacity", 1),
            );
    }

    /**
     * Update transition (existing links).
     *
     * @param {Selection} update
     *
     * @private
     */
    linkUpdate(_update) {
        // TODO Enable for transitions
    }

    /**
     * Exit transition (links to be removed).
     *
     * @param {Selection}  exit
     * @param {Individual} source
     *
     * @private
     */
    linkExit(_exit, _source) {
        // TODO Enable for transitions
    }
}
