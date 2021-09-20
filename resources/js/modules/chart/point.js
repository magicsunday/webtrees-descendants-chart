/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/**
 * A point.
 */
export default class Point
{
    /**
     * Constructor.
     *
     * @param {null|Number} x The X coordinate
     * @param {null|Number} y The y coordinate
     */
    constructor(x, y)
    {
        this._x = x;
        this._y = y;
    }

    /**
     * Returns the X-coordinate of the point.
     *
     * @returns {null|Number}
     */
    get x()
    {
        return this._x;
    }

    /**
     * Returns the Y-coordinate of the point.
     *
     * @returns {null|Number}
     */
    get y()
    {
        return this._y;
    }
}
