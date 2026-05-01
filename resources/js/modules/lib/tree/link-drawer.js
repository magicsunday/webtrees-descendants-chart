/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import {
    elbowsPath,
    LINE_END_TRIM_PX,
    marriagePath,
} from "@magicsunday/webtrees-chart-lib";

/**
 * Renders the connection bundles produced by `connection-builder.js`.
 *
 * Each bundle is a pure-geometry FamilyConnection descriptor:
 *   { father, mother, children, intermediateBoxes, marriageStagger }
 * The drawer turns those into SVG paths via the axis-agnostic helpers
 * shared with webtrees-pedigree-chart through chart-lib.
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
        const isVertical  = orientation.isVertical;
        const halfBox     = (isVertical ? orientation.boxWidth : orientation.boxHeight) / 2;
        const stagger     = connection.marriageStagger * orientation.direction;

        // Cross-axis position of the line. For polygamous additional
        // marriages the stagger pulls the line off the row centre so
        // each marriage bundle stays visually distinguishable; the
        // last (innermost) marriage runs through the row centre itself.
        const crossAxisCoord = isVertical
            ? connection.father.y + stagger
            : connection.father.x + stagger;

        return marriagePath({
            sequence:       [connection.father, ...connection.intermediateBoxes, connection.mother],
            isVertical,
            halfBox,
            trim:           LINE_END_TRIM_PX,
            crossAxisCoord,
        });
    }

    /**
     * All elbow lines from one parent block to its children, drawn as a
     * single SVG path via chart-lib's `elbowsPath`. Source placement
     * depends on the family shape:
     * - Direct couple (father + mother adjacent): emerge from the marriage
     *   line at the couple midpoint.
     * - Continuation polygamous marriage (mother behind intermediate
     *   boxes): drop from the mother's box edge towards the children.
     * - Singleton parent (no mother): drop from the father's box edge.
     */
    _elbowsPath(connection) {
        const orientation     = this._orientation;
        const direction       = orientation.direction;
        const isVertical      = orientation.isVertical;
        const halfBoxCross    = (isVertical ? orientation.boxHeight : orientation.boxWidth) / 2;
        const halfOffsetCross = (isVertical ? orientation.yOffset   : orientation.xOffset)  / 2;

        const dropFromEdge = !connection.mother || connection.intermediateBoxes.length > 0;
        const dropAnchor   = connection.mother || connection.father;

        let source;
        if (dropFromEdge) {
            source = isVertical
                ? {x: dropAnchor.x, y: dropAnchor.y + (halfBoxCross * direction)}
                : {x: dropAnchor.x + (halfBoxCross * direction), y: dropAnchor.y};
        } else {
            const stagger = connection.marriageStagger * direction;
            source = isVertical
                ? {
                    x: (connection.father.x + connection.mother.x) / 2,
                    y: (connection.father.y + connection.mother.y) / 2 + stagger,
                }
                : {
                    x: (connection.father.x + connection.mother.x) / 2 + stagger,
                    y: (connection.father.y + connection.mother.y) / 2,
                };
        }

        return elbowsPath({
            source,
            children:        connection.children,
            isVertical,
            halfBoxCross,
            halfOffsetCross,
            direction,
        });
    }
}
