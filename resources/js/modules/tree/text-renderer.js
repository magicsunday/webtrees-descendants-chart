/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { measureText } from "@magicsunday/webtrees-chart-lib/infra";

/**
 * @import { Orientation } from "@magicsunday/webtrees-chart-lib/infra"
 * @import Svg from "../chart/svg.js"
 * @import ImageBox from "../chart/box/image.js"
 * @import TextBox from "../chart/box/text.js"
 */

/**
 * Shared state and text helpers of the renderers that place text elements next to the
 * image of a person box.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class TextRenderer {
    /**
     * Constructor.
     *
     * @param {Svg}         svg
     * @param {Orientation} orientation
     * @param {ImageBox}    image
     * @param {TextBox}     text
     */
    constructor(svg, orientation, image, text) {
        this._svg = svg;
        this._orientation = orientation;
        this._image = image;
        this._text = text;
    }

    /**
     * Returns the x position of a text element, shifted by the image width when the
     * descriptor reserves room for an image.
     *
     * @param {{withImage?: boolean}} d The element descriptor
     *
     * @returns {number}
     *
     * @protected
     */
    textX(d) {
        const xPos = this._text.x + (d.withImage ? this._image.width : 0);

        // Reverse direction of text elements for RTL layouts
        return this._orientation.isDocumentRtl ? -xPos : xPos;
    }

    /**
     * Measures the given text and return its width depending on the used font
     * (including size and weight).
     *
     * @param {string}        text
     * @param {string}        fontSize
     * @param {string|number} fontWeight
     *
     * @returns {number}
     *
     * @protected
     */
    measureText(text, fontSize, fontWeight = 400) {
        const fontFamily = this._svg.style("font-family");

        return measureText(text, fontFamily, fontSize, fontWeight);
    }
}
