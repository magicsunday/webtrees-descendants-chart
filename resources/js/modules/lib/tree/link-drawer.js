/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../d3.js";
import {LINE_END_TRIM_PX} from "../constants.js";

/**
 * Renders the connection bundles produced by `connection-builder.js`.
 *
 * Each bundle is a pure-geometry FamilyConnection descriptor:
 *   { father, mother, children, intermediateBoxes, marriageStagger }
 * The drawer doesn't know anything about d3, the family-node encoding,
 * or polygamy bookkeeping — it just turns positions into SVG paths.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class LinkDrawer {
    constructor(svg, configuration) {
        this._svg = svg;
        this._configuration = configuration;
        this._orientation = this._configuration.orientation;
    }

    /**
     * Public entry. `connections` is the list returned by
     * `buildConnections()`. Each entry may emit several SVG paths
     * (a marriage chain plus one elbow per child).
     */
    drawLinks(connections, _source) {
        const flatLinks = [];
        for (const connection of connections) {
            if (connection.mother) {
                flatLinks.push({
                    kind: "marriage",
                    d:    this._marriagePath(connection),
                });
            }

            if (connection.children.length > 0) {
                // One path per connection covers all children — the
                // shared source-drop and spine segments only show up
                // once in the path data, so the browser draws each
                // line exactly once.
                flatLinks.push({
                    kind: "elbow",
                    d:    this._elbowsPath(connection),
                });
            }
        }

        this._svg.visual
            .selectAll("path.link")
            .data(flatLinks)
            .join(
                (enter) => enter.append("path")
                    .classed("link", true)
                    .classed("marriage", (link) => link.kind === "marriage")
                    .attr("d", (link) => link.d)
                    .call((selection) => selection.transition()
                        .duration(this._configuration.duration)
                        .attr("opacity", 1)),
                (update) => update.attr("d", (link) => link.d),
                (exit) => exit.remove(),
            );
    }

    /**
     * Marriage line as a chain of segments through the inter-box gaps
     * between father and mother. Adjacent (father, mother) pairs collapse
     * to a single segment in their shared gap; polygamous continuations
     * with intermediate boxes between father and mother emit one segment
     * per gap so the line never crosses an unrelated person's box.
     */
    _marriagePath(connection) {
        const orientation = this._orientation;
        const direction   = orientation.direction;
        const isVertical  = this._orientation.isVertical;
        const stackBox    = isVertical ? orientation.boxWidth : orientation.boxHeight;
        const halfBox     = stackBox / 2;
        const trim        = LINE_END_TRIM_PX;
        const path        = d3.path();

        // Cross-axis position of the line. For polygamous additional
        // marriages the stagger pulls the line off the row centre so
        // each marriage bundle stays visually distinguishable; the
        // last (innermost) marriage runs through the row centre itself.
        const crossAxisCoord = isVertical
            ? connection.father.y + connection.marriageStagger * direction
            : connection.father.x + connection.marriageStagger * direction;

        // Walk father → intermediates → mother in spread-axis order so
        // each segment lands cleanly between two adjacent boxes.
        const spreadAxis = isVertical ? "x" : "y";
        const sequence = [connection.father, ...connection.intermediateBoxes, connection.mother]
            .sort((a, b) => a[spreadAxis] - b[spreadAxis]);

        for (let i = 0; i + 1 < sequence.length; i++) {
            const leftBox  = sequence[i];
            const rightBox = sequence[i + 1];

            const segmentStart = leftBox[spreadAxis]  + halfBox + trim;
            const segmentEnd   = rightBox[spreadAxis] - halfBox - trim;
            if (segmentEnd <= segmentStart) continue;

            this._straightSegment(path, segmentStart, segmentEnd, crossAxisCoord);
        }

        return path.toString();
    }

    /**
     * All elbow lines from one parent block to its children, drawn as a
     * single SVG path. Shared geometry (the source drop and the spine
     * across the elbow row) is emitted exactly once so the renderer
     * doesn't stack multiple strokes on top of each other.
     *
     * Source placement depends on the family shape:
     * - Direct couple (father + mother adjacent): emerge from the marriage
     *   line at the couple midpoint.
     * - Continuation polygamous marriage (mother behind intermediate
     *   boxes): drop from the mother's box edge towards the children.
     * - Singleton parent (no mother): drop from the father's box edge.
     */
    _elbowsPath(connection) {
        const orientation = this._orientation;
        const direction   = orientation.direction;
        const isVertical  = this._orientation.isVertical;
        const halfBoxHeight = orientation.boxHeight / 2;
        const halfBoxWidth  = orientation.boxWidth / 2;
        const halfYOffset   = orientation.yOffset / 2;
        const halfXOffset   = orientation.xOffset / 2;
        const path = d3.path();

        const dropFromEdge = !connection.mother
            || connection.intermediateBoxes.length > 0;
        const dropAnchor = connection.mother || connection.father;

        if (isVertical) {
            const sourceX = dropFromEdge
                ? dropAnchor.x
                : (connection.father.x + connection.mother.x) / 2;
            const sourceY = dropFromEdge
                ? dropAnchor.y + (halfBoxHeight * direction)
                : (connection.father.y + connection.mother.y) / 2
                    + connection.marriageStagger * direction;

            // Children are all on the next generation row, share elbowY.
            const elbowY  = connection.children[0].y
                - (halfBoxHeight * direction)
                - (halfYOffset * direction);
            const targetY = connection.children[0].y - (halfBoxHeight * direction);

            // Source drop down to the elbow row.
            path.moveTo(sourceX, sourceY);
            path.lineTo(sourceX, elbowY);

            // Spine across the elbow row, covering source and every child.
            const childXs = connection.children.map((child) => child.x);
            const spineMin = Math.min(sourceX, ...childXs);
            const spineMax = Math.max(sourceX, ...childXs);
            if (spineMax > spineMin) {
                path.moveTo(spineMin, elbowY);
                path.lineTo(spineMax, elbowY);
            }

            // Per-child drop from the elbow row to the child box top.
            for (const child of connection.children) {
                path.moveTo(child.x, elbowY);
                path.lineTo(child.x, targetY);
            }
        } else {
            const sourceY = dropFromEdge
                ? dropAnchor.y
                : (connection.father.y + connection.mother.y) / 2;
            const sourceX = dropFromEdge
                ? dropAnchor.x + (halfBoxWidth * direction)
                : (connection.father.x + connection.mother.x) / 2
                    + connection.marriageStagger * direction;

            const elbowX  = connection.children[0].x
                - (halfBoxWidth * direction)
                - (halfXOffset * direction);
            const targetX = connection.children[0].x - (halfBoxWidth * direction);

            // Source run-out to the elbow column.
            path.moveTo(sourceX, sourceY);
            path.lineTo(elbowX,  sourceY);

            // Spine across the elbow column, covering source and every child.
            const childYs = connection.children.map((child) => child.y);
            const spineMin = Math.min(sourceY, ...childYs);
            const spineMax = Math.max(sourceY, ...childYs);
            if (spineMax > spineMin) {
                path.moveTo(elbowX, spineMin);
                path.lineTo(elbowX, spineMax);
            }

            // Per-child run from the elbow column to the child box edge.
            for (const child of connection.children) {
                path.moveTo(elbowX,  child.y);
                path.lineTo(targetX, child.y);
            }
        }

        return path.toString();
    }

    /**
     * Emit one straight segment along the spread axis at the given
     * cross-axis coordinate.
     */
    _straightSegment(path, spreadStart, spreadEnd, crossAxisCoord) {
        if (this._orientation.isVertical) {
            path.moveTo(spreadStart, crossAxisCoord);
            path.lineTo(spreadEnd,   crossAxisCoord);
        } else {
            path.moveTo(crossAxisCoord, spreadStart);
            path.lineTo(crossAxisCoord, spreadEnd);
        }
    }
}
