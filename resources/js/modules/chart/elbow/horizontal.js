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
        sourceX += (orientation.boxWidth / 2) * orientation.direction();
    } else {
        // Handle multiple spouses
        if (datum.spouse.index > 0) {
            sourceX -= datum.spouse.index * orientation.direction() * spouseLineOffset;
            sourceY += datum.spouse.index * (orientation.boxHeight + halfYOffset);
        }
    }

    if (datum.target !== null) {
        let targetX = datum.target.x - (orientation.direction() * ((orientation.boxWidth / 2) + halfXOffset)),
            targetY = datum.target.y;

        let targetListOfSpouses = [];

        // Check if target person has a spouse assigned in any of its families
        if (datum.target.data.families) {
            targetListOfSpouses = datum.target.data.families.filter(family => !!family.spouse);

            if (targetListOfSpouses.length > 0) {
                targetY -= ((orientation.boxHeight + halfYOffset) / 2);
            }
        }

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
    }

    // let path = "M" + sourceX + "," + sourceY
    //     + "H" + targetX
    //     + "V" + targetY
    //     + "H" + (targetX + (orientation.direction() * halfXOffset));

    if (datum.spouse === null) {
        return points;
    }

    // // Test with quadratic curve
    // return "M " + sourceX + "," + sourceY
    //     + " H " + (targetX - 10)
    //     + " Q " + (targetX) + " " + (sourceY) + ", " + targetX + " " + (sourceY - (sourceY < targetY ? -10 : 10))
    //     + " V " + (targetY + (sourceY < targetY ? -10 : 10))
    //     + " Q " + (targetX) + " " + (targetY) + ", " + (targetX + 10) + " " + (targetY)
    //     + " H " + (targetX + (orientation.direction() * halfXOffset))

    // Append vertical lines between source and all spouses
    for (let i = 0; i <= datum.spouse.index; ++i) {
        const spouseY = sourceY + ((i - datum.spouse.index) * (orientation.boxHeight + halfYOffset));

        // let offset  = 4;
        // let subPath = "M" + sourceX + "," + (spouseY - (halfYOffset / 2));
        //
        // if (i > 0) {
        //     subPath = "M" + (sourceX - 2.5) + "," + (spouseY - (halfYOffset / 2) + (offset / 2))
        //         + "a2.5 2.5 0 1 0 5 0"
        //         + "M" + (sourceX) + "," + (spouseY - (halfYOffset / 2) + offset);
        // }
        //
        // let verticalY = spouseY + (halfYOffset / 2);
        //
        // if (((i + 1) <= datum.spouse.index)) {
        //     verticalY -= offset;
        // }
        //
        // subPath += "V" + verticalY;
        //
        // if ((i + 1) <= datum.spouse.index) {
        //     subPath +="M" + (sourceX + 2.5) + "," + (spouseY + (halfYOffset / 2) - (offset / 2))
        //         + "a2.5 2.5 0 1 0 -5 0";
        // }
        //
        // path += subPath;

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
                    sourceX,
                    spouseY - (halfYOffset / 2) + startPosOffset
                ),
                new Point(
                    sourceX,
                    spouseY + (halfYOffset / 2) - endPosOffset
                ),
            ]
        ];
    }

    return points;
}
