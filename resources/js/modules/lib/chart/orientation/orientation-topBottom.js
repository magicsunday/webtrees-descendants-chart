/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import Orientation from "./orientation";
import elbowVertical from "../../tree/elbow/vertical";

/**
 * This class handles the orientation of the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class OrientationTopBottom extends Orientation
{
    /**
     * Constructor.
     *
     * @param {number} boxWidth  The width of a single individual box
     * @param {number} boxHeight The height of a single individual box
     */
    constructor(boxWidth, boxHeight)
    {
        super(boxWidth, boxHeight);

        this._splittNames = true;
    }

    get direction()
    {
        return 1;
    }

    get nodeWidth()
    {
        return this._boxWidth + this._xOffset;
    }

    get nodeHeight()
    {
        return this._boxHeight + this._yOffset;
    }

    norm(d)
    {
        d.y *= this.direction;
    }

    elbow(link)
    {
        return elbowVertical(link, this);
    }
}
