/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

import Orientation from "./orientation";
import elbowHorizontal from "../elbow/horizontal";

/**
 * This class handles the orientation of the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class OrientationLeftRight extends Orientation
{
    /**
     * Constructor.
     *
     * @param {Number} boxWidth  The width of a single individual box
     * @param {Number} boxHeight The height of a single individual box
     */
    constructor(boxWidth, boxHeight)
    {
        super(boxWidth, boxHeight);

        this._xOffset = 40;
        this._yOffset = 40;
    }

    direction()
    {
        return 1;
    }

    get nodeWidth()
    {
        return (this._boxHeight * 2) + this._xOffset;
    }

    norm(d)
    {
        // Swap x and y values
        d.y = d.x;
        d.x = this.direction() * d.depth * (this._boxWidth + this._xOffset);
    }

    elbow(d)
    {
        return elbowHorizontal(d, this);
    }
}
