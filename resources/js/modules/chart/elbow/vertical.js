/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

import Point from "../point";

/**
 * Returns the points to draw the vertical connecting lines between the profile
 * boxes for Top/Bottom and Bottom/Top layout.
 *
 * @param {Object}      datum       D3 data object
 * @param {Orientation} orientation The current orientation
 *
 * @returns {Point[]}
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
        sourceX -= (datum.source.x - datum.spouse.x) / 2;
    } else {
        // For each additional family, the link to the child nodes begins at the additional spouse.
        sourceY += (orientation.boxHeight / 2) * orientation.direction();
    }

    // No spouse assigned to source node
    if (datum.source.data.data === null) {
        sourceX -= (orientation.boxWidth / 2) + (halfXOffset / 2);
        sourceY += (orientation.boxHeight / 2) * orientation.direction();
    }

    if (datum.target !== null) {
        let targetX = datum.target.x,
            targetY = datum.target.y - (orientation.direction() * ((orientation.boxHeight / 2) + halfYOffset));

        // The line from source/spouse to target
        points = [
            new Point(
                sourceX,
                sourceY
            ),
            new Point(
                sourceX,
                targetY
            ),
            new Point(
                targetX,
                targetY
            ),
            new Point(
                targetX,
                targetY + (orientation.direction() * halfYOffset)
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

    let sourceY = datum.source.y,
        points  = [];

    // Handle multiple spouses
    if (datum.source.data.family > 0) {
        sourceY = datum.spouse.y - (datum.source.data.family * orientation.direction() * spouseLineOffset);
    }

    // Add link between first spouse and source
    if (datum.coords === null) {
        points = addLineCoordinates(
            points,
            datum.spouse.x + (orientation.boxWidth / 2),
            sourceY,
            datum.source.x - (orientation.boxWidth / 2),
            sourceY
        );
    }

    // Append lines between source and all spouses
    if (datum.coords && (datum.coords.length > 0)) {
        for (let i = 0; i < datum.coords.length; ++i) {
            let startX = datum.spouse.x + (orientation.boxWidth / 2);
            let endX   = datum.coords[i].x - (orientation.boxWidth / 2);

            if (i > 0) {
                startX = datum.coords[i - 1].x + (orientation.boxWidth / 2);
            }

            let startPosOffset = ((i > 0) ? lineStartOffset : 0);
            let endPosOffset   = (((i + 1) <= datum.coords.length) ? lineStartOffset : 0);

            points = addLineCoordinates(
                points,
                startX + startPosOffset,
                sourceY,
                endX - endPosOffset,
                sourceY,
            );
        }

        // Add last part from previous spouse to actual spouse
        points = addLineCoordinates(
            points,
            datum.coords[datum.coords.length - 1].x + (orientation.boxWidth / 2) + lineStartOffset,
            sourceY,
            datum.source.x - (orientation.boxWidth / 2),
            sourceY
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
