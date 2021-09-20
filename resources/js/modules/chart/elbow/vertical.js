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
    // The distance between the connecting lines when there are multiple spouses
    const spouseLineOffset = 5;

    const halfXOffset = orientation.xOffset / 2;
    const halfYOffset = orientation.yOffset / 2;

    let sourceX = datum.source.x,
        sourceY = datum.source.y,
        points  = [];

    // No spouse assigned to source node
    if (datum.spouse === null) {
        sourceY += (orientation.boxHeight / 2) * orientation.direction();
    } else {
        // Handle multiple spouses
        if (datum.spouse.index > 0) {
            sourceX += datum.spouse.index * (orientation.boxWidth + halfXOffset);
            sourceY -= datum.spouse.index * orientation.direction() * spouseLineOffset;
        }
    }

    if (datum.target !== null) {
        let targetX = datum.target.x,
            targetY = datum.target.y - (orientation.direction() * ((orientation.boxHeight / 2) + halfYOffset));

        let targetListOfSpouses = [];

        // Check if target person has a spouse assigned in any of its families
        if (datum.target.data.families) {
            targetListOfSpouses = datum.target.data.families.filter(family => !!family.spouse);

            if (targetListOfSpouses.length > 0) {
                targetX -= ((orientation.boxWidth + halfXOffset) / 2);
            }
        }

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
    }

    if (datum.spouse === null) {
        return points;
    }

    // Append vertical lines between source and all spouses
    for (let i = 0; i <= datum.spouse.index; ++i) {
        const spouseX = sourceX + ((i - datum.spouse.index) * (orientation.boxWidth + halfXOffset));

        let startPosOffset = ((i > 0) ? 2 : 0);
        let endPosOffset   = (((i + 1) <= datum.spouse.index) ? 2 : 0);

        points = [
            ...points,
            ...[
                // Add empty value to force a line skip, so the line pointer
                // will move to the new starting location
                new Point(
                    null,
                    null
                ),
                new Point(
                    spouseX - (halfXOffset / 2) + startPosOffset,
                    sourceY
                ),
                new Point(
                    spouseX + (halfXOffset / 2) - endPosOffset,
                    sourceY
                ),
            ]
        ];
    }

    return points;
}
