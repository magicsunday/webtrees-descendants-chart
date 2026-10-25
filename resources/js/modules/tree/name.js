/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { truncateNames } from "@magicsunday/webtrees-chart-lib/infra";
import { TextRenderer } from "@magicsunday/webtrees-chart-lib/linked-tree";
import * as d3 from "../d3.js";

/**
 * @import { Selection } from "d3-selection"
 * @import Configuration from "../configuration.js"
 */

/**
 * The kinds of name segments that belong to the first names of a person. Every other kind
 * belongs to the last names.
 *
 * @type {string[]}
 */
const FIRST_NAME_KINDS = ["given", "nickname"];

/**
 * The class handles the creation of the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Name extends TextRenderer {
    /**
     * Add the individual names to the given parent element.
     *
     * @param {Selection<any, any, any, any>} parent The parent element to which the elements are to be attached
     *
     * @public
     */
    appendName(parent) {
        const name = parent.append("g").attr("class", "name");

        // Top/Bottom and Bottom/Top
        if (this._orientation.isVertical) {
            const that = this;

            const enter = name
                .selectAll("text")
                .data((datum) => [
                    {
                        data: datum.data,
                        isRtl: datum.data.data.isNameRtl,
                        isAltRtl: datum.data.data.isAltRtl,
                        // Always arrange the text at the same position regardless if an image is displayed or not
                        withImage: true,
                    },
                ])
                .enter();

            enter.each(function (datum) {
                const element = d3.select(this);
                const nameGroups = that.createNamesData(datum);
                const availableWidth = that.getAvailableWidth(datum);

                nameGroups.forEach((nameGroup, index) => {
                    const text = element
                        .append("text")
                        .attr("class", "wt-chart-box-name")
                        .attr("direction", (datum) => (datum.isRtl ? "rtl" : "ltr"))
                        .attr("text-anchor", "middle")
                        .attr("dominant-baseline", "central")
                        .attr("y", that._text.y - 5 + index * 20);

                    that.addNameElements(
                        text,
                        that.truncateNamesData(text, nameGroup, availableWidth),
                    );
                });
            });

            this.appendAlternativeName(enter, this._text.y + 40, (text) =>
                text.attr("text-anchor", "middle").attr("dominant-baseline", "central"),
            );

            // Left/Right and Right/Left
        } else {
            const enter = name
                .selectAll("text")
                .data((datum) => [
                    {
                        data: datum.data,
                        isRtl: datum.data.data.isNameRtl,
                        isAltRtl: datum.data.data.isAltRtl,
                        withImage: datum.data.data.thumbnail !== "",
                    },
                ])
                .enter();

            enter.call((g) => {
                const text = g
                    .append("text")
                    .attr("class", "wt-chart-box-name")
                    .attr("direction", (datum) => (datum.isRtl ? "rtl" : "ltr"))
                    .attr("text-anchor", (datum) => this.getTextAnchor(datum.isRtl))
                    .attr("x", (datum) => this.textX(datum))
                    .attr("y", this._text.y - 10);

                this.addNameElements(text, (datum) => {
                    // Merge the given name and surname groups, as we display the whole name in one line
                    const combined = this.createNamesData(datum).flat();

                    return this.truncateNamesData(text, combined, this.getAvailableWidth(datum));
                });
            });

            this.appendAlternativeName(enter, this._text.y + 8, (text) =>
                text
                    .attr("text-anchor", (datum) => this.getTextAnchor(datum.isAltRtl))
                    .attr("x", (datum) => this.textX(datum)),
            );
        }
    }

    /**
     * Adds the alternative name below the name of every person that has one, when the
     * chart is configured to show it.
     *
     * @param {Selection<any, any, any, any>}                    enter    The entering name element descriptors
     * @param {number}                                           y        The y position of the alternative name text
     * @param {(text: Selection<any, any, any, any>) => void}    position Sets the layout dependent attributes that place the text
     *
     * @private
     */
    appendAlternativeName(enter, y, position) {
        if (!this._svg._configuration.showAlternativeName) {
            return;
        }

        enter
            .filter((datum) => datum.data.data.alternativeName !== "")
            .call((g) => {
                const text = g
                    .append("text")
                    .attr("class", "wt-chart-box-name")
                    .attr("direction", (datum) => (datum.isAltRtl ? "rtl" : "ltr"))
                    .attr("y", y);

                position(text);

                this.addNameElements(text, (datum) =>
                    this.truncateNamesData(
                        text,
                        this.createAlternativeNamesData(datum),
                        this.getAvailableWidth(datum),
                    ),
                );
            });
    }

    /**
     * Returns the text anchor of a horizontally laid out name. The text of a right-to-left
     * name grows towards the start of the line, which a right-to-left document mirrors again.
     *
     * @param {boolean} isRtl Whether the text is written right to left
     *
     * @return {string}
     *
     * @private
     */
    getTextAnchor(isRtl) {
        if (isRtl && this._orientation.isDocumentRtl) {
            return "start";
        }

        if (isRtl || this._orientation.isDocumentRtl) {
            return "end";
        }

        return "start";
    }

    /**
     * Returns the total available width that the text can occupy.
     *
     * @param {NameElementData} datum
     *
     * @return {number}
     *
     * @private
     */
    getAvailableWidth(datum) {
        // The total available width that the text can occupy
        let availableWidth = this._text.width;

        if (datum.withImage && !this._orientation.isVertical) {
            availableWidth -= this._image.width;
        }

        return availableWidth;
    }

    /**
     * Creates a single <tspan> element for each single name and append it to
     * the parent element. The "tspan" element containing the preferred name
     * gets an additional underline style to highlight this one.
     *
     * @param {Selection<any, any, any, any>}                          parent The parent element to which the <tspan> elements are to be attached
     * @param {LabelElementData[] | ((arg0: any) => LabelElementData[])} data
     *
     * @private
     */
    addNameElements(parent, data) {
        parent
            .selectAll("tspan")
            .data(data)
            .enter()
            .call((g) => {
                g.append("tspan")
                    .text((datum) => datum.label)
                    // Add some spacing between the elements
                    .attr("dx", (datum, index) => {
                        return index === 0 ? null : `${(datum.isNameRtl ? -1 : 1) * 0.25}em`;
                    })
                    // Highlight the preferred and last name
                    .attr("text-decoration", (datum) => (datum.isPreferred ? "underline" : null))
                    .classed("lastName", (datum) => datum.isLastName);
            });
    }

    /**
     * Creates the label groups of the name from its ordered name segments. A run of given
     * name and nickname segments forms the group of the first names, a run of surname and
     * married surname segments the group of the last names.
     *
     * @param {NameElementData} datum
     *
     * @return {LabelElementData[][]}
     *
     * @private
     */
    createNamesData(datum) {
        const { nameSegments, isNameRtl } = datum.data.data;

        /** @type {LabelElementData[][]} */
        const groups = [];
        /** @type {LabelElementData[] | null} */
        let group = null;
        let isLastNameGroup = false;

        for (const segment of nameSegments) {
            const isLastName = !FIRST_NAME_KINDS.includes(segment.kind);

            // A change between first names and last names starts the next group
            if (group === null || isLastName !== isLastNameGroup) {
                group = [];
                groups.push(group);
                isLastNameGroup = isLastName;
            }

            group.push({
                label: segment.label,
                isPreferred: segment.isPreferred,
                isLastName: isLastName,
                isNickname: segment.kind === "nickname",
                isNameRtl: isNameRtl,
            });
        }

        return groups;
    }

    /**
     * Creates the data array for the names.
     *
     * @param {Selection<any, any, any, any>} parent
     * @param {LabelElementData[]}            names
     * @param {number}                        availableWidth
     *
     * @return {LabelElementData[]}
     *
     * @private
     */
    truncateNamesData(parent, names, availableWidth) {
        const fontSize = parent.style("font-size");
        const fontWeight = parent.style("font-weight");

        return /** @type {LabelElementData[]} */ (
            truncateNames(
                names,
                availableWidth,
                (text) => this.measureText(text, fontSize, fontWeight),
                {
                    strategy: /** @type {Configuration} */ (this._svg._configuration)
                        .nameAbbreviation,
                    // Married-name suffixes like "(Müller)" are supplementary;
                    // drop them entirely instead of truncating to "(.".
                    dropEmptyBracketed: true,
                },
            )
        );
    }

    /**
     * Creates the data array for the alternative name.
     *
     * @param {NameElementData} datum
     *
     * @return {LabelElementData[]}
     *
     * @private
     */
    createAlternativeNamesData(datum) {
        const words = datum.data.data.alternativeName.split(/\s+/);

        /** @type {LabelElementData[]} */
        let names = [];

        // Append the alternative names
        names = names.concat(
            words.map((word) => {
                return {
                    label: word,
                    isPreferred: false,
                    isLastName: false,
                    isNickname: false,
                    isNameRtl: datum.data.data.isAltRtl,
                };
            }),
        );

        return names;
    }
}
