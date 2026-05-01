/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import * as d3 from "../../d3.js";
import {FAMILY_LINE_OFFSET_PX, SPOUSE_GAP_PX} from "../../constants.js";

/**
 * Returns the path to draw the horizontal connecting lines between couple
 * blocks for the Left/Right and Right/Left layouts.
 *
 * The line originates between `members[0]` (the real-person) and
 * `members[spouseIndex]` of the source couple. For each additional family
 * the line starts at the additional spouse's centre instead, so polygamous
 * families each get their own line bundle.
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
    const halfXOffset = orientation.xOffset / 2;
    const halfBoxWidth = orientation.boxWidth / 2;
    const dir = orientation.direction;
    const boxHeight = orientation.boxHeight;

    // In horizontal layout the spread axis is Y, so the family-of-origin
    // offset shifts the line origin along Y.
    const sourceYOffset = familyOriginOffset(link, boxHeight);

    const sourceY = link.source.y + sourceYOffset;
    let sourceX = link.source.x + (halfBoxWidth * dir);

    if (link.familyCount > 1) {
        const offsetSteps = link.familyOrder - Math.floor(link.familyCount / 2);
        sourceX += offsetSteps * FAMILY_LINE_OFFSET_PX * dir;
    }

    const targetX = link.target.x - (halfBoxWidth * dir);
    const targetY = link.target.y;
    const elbowX = targetX - (halfXOffset * dir);

    const path = d3.path();
    path.moveTo(sourceX, sourceY);
    path.lineTo(elbowX, sourceY);
    path.lineTo(elbowX, targetY);
    path.lineTo(targetX, targetY);

    return path.toString();
}

function familyOriginOffset(link, boxHeight) {
    if (link.spouseIndex === null || link.spouseIndex === undefined) {
        return 0;
    }

    const memberCount = link.source.data.members.length;
    const stride = boxHeight + SPOUSE_GAP_PX;
    const start = -((memberCount - 1) * stride) / 2;
    const realOffset = start;
    const spouseOffset = start + link.spouseIndex * stride;

    if (link.familyOrder === 0) {
        return (realOffset + spouseOffset) / 2;
    }
    return spouseOffset;
}
