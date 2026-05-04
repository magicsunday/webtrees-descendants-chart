/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import OrientationCollection from "./chart/orientation-collection.js";
import { LAYOUT_LEFTRIGHT } from "./constants.js";

/**
 * @import { Orientation } from "@magicsunday/webtrees-chart-lib"
 */

/**
 * This class handles the configuration of the application.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Configuration {
    /**
     * Constructor.
     *
     * @param {{zoom: string, move: string}} labels
     * @param {number}   generations
     * @param {string}   treeLayout
     * @param {boolean}  openNewTabOnClick
     * @param {boolean}  showAlternativeName
     * @param {string}   nameAbbreviation One of "GIVEN" or "SURNAME". Resolved server-side from the tree's SURNAME_TRADITION when admin sets it to AUTO.
     * @param {boolean}  rtl
     */
    constructor(
        labels,
        generations = 4,
        treeLayout = LAYOUT_LEFTRIGHT,
        openNewTabOnClick = true,
        showAlternativeName = true,
        nameAbbreviation = "GIVEN",
        rtl = false,
    ) {
        // The layout/orientation of the tree
        this._treeLayout = treeLayout;
        this._orientations = new OrientationCollection();

        this._openNewTabOnClick = openNewTabOnClick;
        this._showAlternativeName = showAlternativeName;
        this._nameAbbreviation = nameAbbreviation;

        //
        this.duration = 750;

        //
        this.padding = 15;

        // Default number of generations to display
        this._generations = generations;

        // Left/Right padding of a text (used with truncation)
        this.textPadding = 8;

        // // Default font size, color and scaling
        this._fontSize = 14;
        this.fontColor = "rgb(0, 0, 0)";

        // Duration of update animation if clicked on a person
        // this.updateDuration = 1250;

        this.rtl = rtl;
        this.labels = labels;
    }

    /**
     * Returns the number of generations to display.
     *
     * @returns {number}
     */
    get generations() {
        return this._generations;
    }

    /**
     * Sets the number of generations to display.
     *
     * @param {number} value The number of generations to display
     */
    set generations(value) {
        this._generations = value;
    }

    /**
     * Returns the tree layout.
     *
     * @returns {string}
     */
    get treeLayout() {
        return this._treeLayout;
    }

    /**
     * Sets the tree layout.
     *
     * @param {string} value Tree layout value
     */
    set treeLayout(value) {
        this._treeLayout = value;
    }

    /**
     * Returns the current orientation.
     *
     * @returns {Orientation}
     */
    get orientation() {
        return this._orientations.get()[this.treeLayout];
    }

    /**
     * Returns TRUE or FALSE depending on whether to open the current individual's details page in a new tab.
     *
     * @returns {boolean}
     */
    get openNewTabOnClick() {
        return this._openNewTabOnClick;
    }

    /**
     * Returns whether to show or hide the alternative name.
     *
     * @returns {boolean}
     */
    get showAlternativeName() {
        return this._showAlternativeName;
    }

    /**
     * Returns the resolved name-abbreviation strategy ("GIVEN" or "SURNAME").
     * The server resolves the tree-pref value (which can also be "AUTO") into
     * one of these two before serialising — the JS layer never sees AUTO.
     *
     * @returns {string}
     */
    get nameAbbreviation() {
        return this._nameAbbreviation;
    }
}
