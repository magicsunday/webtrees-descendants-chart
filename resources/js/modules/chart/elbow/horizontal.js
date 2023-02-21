/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

import Point from "../point";

/**
 * Returns the points to draw the horizontal connecting lines between the profile
 * boxes for Left/Right and Right/Left layout.
 *
 * @param {Object}      datum       D3 data object
 * @param {Orientation} orientation The current orientation
 *
 * @returns {Point[]}
 *
 * Curved edges => https://observablehq.com/@bumbeishvili/curved-edges-horizontal-d3-v3-v4-v5-v6
 */
export default function(datum, orientation)
{
    const halfXOffset = orientation.xOffset / 2;
    const halfYOffset = orientation.yOffset / 2;

    let sourceX = datum.source.x,
        sourceY = datum.source.y,
        points  = [];

    if (datum.source.data.family === 0) {
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

        // The line from source/spouse to target
        points = [
            new Point(
                sourceX,
                sourceY
            ),
            new Point(
                targetX,
                sourceY
            ),
            new Point(
                targetX,
                targetY
            ),
            new Point(
                targetX + (orientation.direction() * halfXOffset),
                targetY
            )
        ];

        return points;
    }

    return [
        ...points,
        ...createLinksBetweenSpouses(datum, orientation)
    ];
}

/**
 * Adds the points needed to draw the lines between each spouse.
 *
 * @param {Object}      datum       D3 data object
 * @param {Orientation} orientation The current orientation
 *
 * @return {Point[]}
 */
function createLinksBetweenSpouses(datum, orientation)
{
    // The distance between the connecting lines when there are multiple spouses
    const spouseLineOffset = 5;

    // The distance from the line to the node. Causes the line to stop or begin just before the node,
    // instead of going straight to the node, so that the connection to another spouse is clearer.
    const lineStartOffset = 2;

    let sourceX = datum.source.x,
        points  = [];

    // Handle multiple spouses
    if (datum.source.data.family > 0) {
        sourceX = datum.spouse.x - (datum.source.data.family * orientation.direction() * spouseLineOffset);
    }

    // Add link between first spouse and source
    if (datum.coords === null) {
        points = addLineCoordinates(
            points,
            sourceX,
            datum.spouse.y + (orientation.boxHeight / 2),
            sourceX,
            datum.source.y - (orientation.boxHeight / 2)
        );
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

            points = addLineCoordinates(
                points,
                sourceX,
                startY + startPosOffset,
                sourceX,
                endY - endPosOffset
            );
        }

        // Add last part from previous spouse to actual spouse
        points = addLineCoordinates(
            points,
            sourceX,
            datum.coords[datum.coords.length - 1].y + (orientation.boxHeight / 2) + lineStartOffset,
            sourceX,
            datum.source.y - (orientation.boxHeight / 2)
        );
    }

    return points;
}

/**
 * Add line coordinates to the given list of points.
 *
 * @param {Point[]} points
 * @param {Number}  x0
 * @param {Number}  y0
 * @param {Number}  x1
 * @param {Number}  y1
 *
 * @return {Point[]}
 */
function addLineCoordinates(points, x0, y0, x1, y1)
{
    return [
        ...points,
        ...[
            // Add empty value to force a line skip, so the line pointer
            // will move to the new starting location
            new Point(
                null,
                null
            ),
            new Point(
                x0,
                y0
            ),
            new Point(
                x1,
                y1
            ),
        ]
    ];
}
