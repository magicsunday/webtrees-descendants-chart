/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../../d3.js";
import {FAMILY_LINE_OFFSET_PX, SPOUSE_GAP_PX} from "../../constants.js";

/**
 * Returns the path to draw the vertical connecting lines between couple
 * blocks for the Top/Bottom and Bottom/Top layouts.
 *
 * The line originates between `members[0]` (the real-person) and
 * `members[spouseIndex]` of the source couple — so each polygamous family
 * gets its own line that anchors at the correct partner. When the source
 * couple has multiple families, an extra few-pixel y-offset spreads the
 * outgoing-line bundle so they don't visually overlap each other.
 *
 * @param {{
 *   source: object,
 *   target: object,
 *   spouseIndex: ?number,
 *   familyOrder: number,
 *   familyCount: number,
 * }} link
 * @param {Orientation} orientation
 *
 * @returns {string}
 */
export default function(link, orientation) {
    const halfYOffset = orientation.yOffset / 2;
    const halfBoxHeight = orientation.boxHeight / 2;
    const dir = orientation.direction;
    const boxWidth = orientation.boxWidth;

    // Family-of-origin offset: midway between real-person (members[0]) and
    // the family's spouse (members[spouseIndex]) in spread-axis pixels.
    const sourceXOffset = familyOriginOffset(link, boxWidth);

    const sourceX = link.source.x + sourceXOffset;
    let sourceY = link.source.y + (halfBoxHeight * dir);

    // Spread the multi-family outgoing-line bundle vertically so they don't
    // overlap on the same Y row.
    if (link.familyCount > 1) {
        const offsetSteps = link.familyOrder - Math.floor(link.familyCount / 2);
        sourceY += offsetSteps * FAMILY_LINE_OFFSET_PX * dir;
    }

    const targetX = link.target.x;
    const targetY = link.target.y - (halfBoxHeight * dir);
    const elbowY = targetY - (halfYOffset * dir);

    const path = d3.path();
    path.moveTo(sourceX, sourceY);
    path.lineTo(sourceX, elbowY);
    path.lineTo(targetX, elbowY);
    path.lineTo(targetX, targetY);

    return path.toString();
}

/**
 * Returns the spread-axis offset of the line origin relative to `couple.x`.
 *
 * For the first family, the line starts midway between the real-person
 * (`members[0]`) and that family's spouse (`members[spouseIndex]`). For
 * each additional family, the line starts at the additional spouse's centre
 * — same convention the chart used before the family-node refactor.
 *
 * @param {{spouseIndex: ?number, familyOrder: number, source: {data: {members: object[]}}}} link
 * @param {number} boxWidth
 *
 * @returns {number}
 */
function familyOriginOffset(link, boxWidth) {
    if (link.spouseIndex === null || link.spouseIndex === undefined) {
        return 0;
    }

    const memberCount = link.source.data.members.length;
    const stride = boxWidth + SPOUSE_GAP_PX;
    const start = -((memberCount - 1) * stride) / 2;
    const realOffset = start;                                  // members[0]
    const spouseOffset = start + link.spouseIndex * stride;    // members[spouseIndex]

    if (link.familyOrder === 0) {
        return (realOffset + spouseOffset) / 2;
    }
    return spouseOffset;
}
