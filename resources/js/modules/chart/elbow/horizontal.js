/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

import * as d3 from "../../d3";

/**
 * Returns the path to draw the horizontal connecting lines between the profile
 * boxes for Left/Right and Right/Left layout.
 *
 * @param {Object}      datum       D3 data object
 * @param {Orientation} orientation The current orientation
 *
 * @returns {String}
 *
 * Curved edges => https://observablehq.com/@bumbeishvili/curved-edges-horizontal-d3-v3-v4-v5-v6
 */
export default function(datum, orientation)
{
    const halfXOffset = orientation.xOffset / 2;
    const halfYOffset = orientation.yOffset / 2;

    let sourceX = datum.source.x,
        sourceY = datum.source.y;

    if ((typeof datum.spouse !== "undefined") && (datum.source.data.family === 0)) {
        // For the first family, the link to the child nodes begins between
        // the individual and the first spouse.
        sourceY -= (datum.source.y - datum.spouse.y) / 2;
    } else {
        // For each additional family, the link to the child nodes begins at the additional spouse.
        sourceX += (orientation.boxWidth / 2) * orientation.direction();
    }

    // No spouse assigned to source node
    if (datum.source.data.data === null) {
        sourceX += (orientation.boxWidth / 2) * orientation.direction();
        sourceY -= (orientation.boxHeight / 2) + (halfYOffset / 2);
    }

    if (datum.target !== null) {
        let targetX = datum.target.x - (orientation.direction() * ((orientation.boxWidth / 2) + halfXOffset)),
            targetY = datum.target.y;

        const path = d3.path();

        // The line from source/spouse to target
        path.moveTo(sourceX, sourceY);
        path.lineTo(targetX, sourceY);
        path.lineTo(targetX, targetY);
        path.lineTo(targetX + (orientation.direction() * halfXOffset), targetY);

        return path.toString();
    }

    return createLinksBetweenSpouses(datum, orientation);
}

/**
 * Adds the path needed to draw the lines between each spouse.
 *
 * @param {Object}      datum       D3 data object
 * @param {Orientation} orientation The current orientation
 *
 * @return {String}
 */
function createLinksBetweenSpouses(datum, orientation)
{
    const path = d3.path();

    // The distance between the connecting lines when there are multiple spouses
    const spouseLineOffset = 5;

    // The distance from the line to the node. Causes the line to stop or begin just before the node,
    // instead of going straight to the node, so that the connection to another spouse is clearer.
    const lineStartOffset = 2;

    let sourceX = datum.source.x;

    // Handle multiple spouses
    if (datum.source.data.family > 0) {
        sourceX = datum.spouse.x - (datum.source.data.family * orientation.direction() * spouseLineOffset);
    }

    // Add link between first spouse and source
    if (datum.coords === null) {
        path.moveTo(sourceX, datum.spouse.y + (orientation.boxHeight / 2));
        path.lineTo(sourceX, datum.source.y - (orientation.boxHeight / 2));
    }

    // Append lines between source and all spouses
    if (datum.coords && (datum.coords.length > 0)) {
        for (let i = 0; i < datum.coords.length; ++i) {
            let startY = datum.spouse.y + (orientation.boxHeight / 2);
            let endY   = datum.coords[i].y - (orientation.boxHeight / 2);

            if (i > 0) {
                startY = datum.coords[i - 1].y + (orientation.boxHeight / 2);
            }

            let startPosOffset = ((i > 0) ? lineStartOffset : 0);
            let endPosOffset   = (((i + 1) <= datum.coords.length) ? lineStartOffset : 0);

            path.moveTo(sourceX, startY + startPosOffset);
            path.lineTo(sourceX, endY - endPosOffset);
        }

        // Add last part from previous spouse to actual spouse
        path.moveTo(
            sourceX,
            datum.coords[datum.coords.length - 1].y + (orientation.boxHeight / 2) + lineStartOffset
        );

        path.lineTo(
            sourceX,
            datum.source.y - (orientation.boxHeight / 2)
        );
    }

    return path.toString();
}
