/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

import * as d3 from "./d3";
import dataUrl from "./common/dataUrl";
import measureText from "./chart/text/measure"
import {SEX_FEMALE, SEX_MALE} from "./constants";
import OrientationLeftRight from "./chart/orientation/orientation-leftRight";
import OrientationRightLeft from "./chart/orientation/orientation-rightLeft";
import OrientationTopBottom from "./chart/orientation/orientation-topBottom";
import OrientationBottomTop from "./chart/orientation/orientation-bottomTop";
import Image from "./chart/box/image.js";
import Text from "./chart/box/text.js";

/**
 * The class handles the creation of the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
export default class Tree
{
    /**
     * Constructor.
     *
     * @param {Svg}           svg
     * @param {Configuration} configuration The configuration
     * @param {Hierarchy}     hierarchy     The hierarchical data
     */
    constructor(svg, configuration, hierarchy)
    {
        this._svg           = svg;
        this._configuration = configuration;
        this._hierarchy     = hierarchy;

        this._hierarchy.root.x0 = 0;
        this._hierarchy.root.y0 = 0;

        this._orientation = this._configuration.orientation;

        this._image = new Image(this._orientation, 20);
        this._text  = new Text(this._orientation, this._image);

        this.draw(this._hierarchy.root);
    }

    /**
     * Returns the separation value.
     *
     * @param {Node} left
     * @param {Node} right
     *
     * @return {Number}
     */
    separation(left, right)
    {
        // The left child has spouses (1 or more), add some space between the nodes
        if (Object.hasOwn(left.data, 'spouses')) {
            return 1.25;
        }

        // The right side is a spouse linked back to the actual child, so add some space
        if (Object.hasOwn(right.data, 'spouse')) {
            return 1.25;
        }

        // Single siblings and cousins should be close
        // to each other if parents are the same
        return left.parent === right.parent ? 1.0 : 2.0;
    }

    /**
     * Draw the tree.
     *
     * @param {Individual} source The root object
     *
     * @public
     */
    draw(source)
    {
        // Declares a tree layout and assigns the size
        const tree = d3.tree()
            .nodeSize([this._configuration.orientation.nodeWidth, this._configuration.orientation.nodeHeight])
            .separation(this.separation);

        // Map the root node data to the tree layout
        this._nodes = tree(this._hierarchy.root);

        // Normalize node coordinates (swap values for left/right layout)
        this._hierarchy.root.each((node) => {
            this._configuration.orientation.norm(node);
        });

        /** @type {Individual[]} */
        let nodes = this._hierarchy.root.descendants();
        let links = [];

        // Remove the pseudo root node
        nodes.shift();

        // Arrange the position of the individual with the first spouse so that they are close to each other.
        // Since the line of connection between the individual and the first spouse begins between these two,
        // this prevents the lines of connection from overlapping with other children and their partners.
        nodes.forEach((node) => {
            if (node.data
                && Array.isArray(node.data.spouses)
                && (node.data.spouses.length >= 1)
            ) {
                // Find the first spouse
                const spouse = this.findSpouseById(node.data.spouses[0], nodes);

                // Position the node directly above the first spouse
                if ((this._orientation instanceof OrientationTopBottom)
                    || (this._orientation instanceof OrientationBottomTop)
                ) {
                    node.x = spouse.x - this._orientation.nodeWidth;
                } else {
                    node.y = spouse.y - this._orientation.nodeWidth;
                }
            }
        });

        // Find the first node with children
        const firstNodeWithChildren = nodes.find(node => node.children && (node.children.length > 0));

        // Center the children between the individual and the spouse
        if (typeof firstNodeWithChildren !== "undefined") {
            const moveBy = this._orientation.nodeWidth / 2;

            firstNodeWithChildren.each((node) => {
                if (
                    Object.hasOwn(node.data, "spouse")
                    && (node.data.spouse !== null)
                    && Object.hasOwn(node, "children")
                    && Array.isArray(node.children)
                    && (node.children.length >= 1)
                ) {
                    this.moveChildren(node, moveBy);
                }
            });
        }

        // Create a list of links between source (node and spouses) and target nodes (children).
        nodes.forEach((node) => {
            const spouse = this.findSpouseById(node.data.spouse, nodes);

            // Process children
            if (Object.hasOwn(node, "children")
                && Array.isArray(node.children)
                && (node.children.length > 0)
            ) {
                node.children.forEach((child) => {
                    // Only add links between real children
                    if ((typeof child.data.spouse === "undefined") || (child.data.spouse === null)) {
                        links.push({
                            spouse: spouse,
                            source: node,
                            target: child,
                            coords: null
                        });
                    }
                });
            }

            if (typeof spouse !== "undefined") {
                let spousesCoords = null;

                // To draw only the respective intermediate lines, we need the information
                // about the position of the previous spouses in the row. The coordinates are attached
                // to the respective link as additional values so that they are available later when
                // calculating the line points.
                if ((typeof spouse.data.spouses !== "undefined") && (spouse.data.spouses.length > 0)) {
                    const indexOfSpouse = spouse.data.spouses.indexOf(node.data.data.id);
                    const spousesBefore = spouse.data.spouses.slice(0, indexOfSpouse);

                    if (spousesBefore.length > 0) {
                        spousesCoords = [];

                        spousesBefore.forEach((id) => {
                            // Find matching spouse in the list of all nodes
                            const spouseBefore = this.findSpouseById(Number(id), nodes);

                            // Keep track of the coordinates
                            if (typeof spouseBefore !== "undefined") {
                                spousesCoords.push({
                                    x: spouseBefore.x,
                                    y: spouseBefore.y
                                })
                            }
                        });
                    }
                }

                if (node.data.data !== null) {
                    // Add link between individual and spouse
                    links.push({
                        spouse: spouse,
                        source: node,
                        target: null,
                        coords: spousesCoords
                    });
                }
            }
        });

        this.drawNodes(nodes, source);
        this.drawLinks(links, source);
    }

    /**
     * Finds the related spouse in a list of individuals for the individual ID passed.
     *
     * @param {Number}       id
     * @param {Individual[]} individuals
     *
     * @return {Individual}
     */
    findSpouseById(id, individuals)
    {
        return individuals.find(
            (spouse) => {
                return (spouse.data.data.id === id);
            }
        );
    }

    /**
     * Moves all child nodes by the specified amount.
     *
     * @param {Individual} individual The individual whose children are to be moved
     * @param {Number}     moveBy     The amount by which to move the child nodes
     */
    moveChildren(individual, moveBy)
    {
        individual.each((child) => {
            if (child.depth !== individual.depth) {
                // - first family only
                // - if more than one child
                // - if child has children too
                if (
                    (individual.data.family === 0)
                    || (individual.children.length !== 1)
                    || (typeof child.children !== "undefined")
                ) {
                    if ((this._orientation instanceof OrientationTopBottom)
                        || (this._orientation instanceof OrientationBottomTop)
                    ) {
                        child.x -= moveBy;
                    } else {
                        child.y -= moveBy;
                    }
                }
            }
        });
    }

    // /**
    //  * Draw the tree.
    //  *
    //  * @public
    //  */
    // update(source)
    // {
    //     let nodes = this._hierarchy.nodes.descendants();
    //     let links = this._hierarchy.nodes.links();
    //
    //     // // Start with only the first few generations of ancestors showing
    //     // nodes.forEach((person) => {
    //     //     if (person.children) {
    //     //         person.children.forEach((child) => this.collapse(child));
    //     //     }
    //     // });
    //
    //     this.drawLinks(links, source);
    //     this.drawNodes(nodes, source);
    //
    //     // Stash the old positions for transition.
    //     nodes.forEach((person) => {
    //         person.x0 = person.x;
    //         person.y0 = person.y;
    //     });
    // }

    /**
     * Draw the person boxes.
     *
     * @param {Individual[]} nodes  Array of descendant nodes
     * @param {Individual}   source The root object
     *
     * @private
     */
    drawNodes(nodes, source)
    {
        // Image clip path
        this._svg
            .defs
            .get()
            .append("clipPath")
            .attr("id", "clip-image")
            .append("rect")
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height);

        this._svg.visual
            .selectAll("g.person")
            .data(nodes, person => person.id)
            .join(
                enter  => this.nodeEnter(enter, source),
                update => this.nodeUpdate(update),
                exit   => this.nodeExit(exit, source)
            );

        // this.centerTree();

        // Stash the old positions for transition
        this._hierarchy.root.eachBefore(d => {
            d.x0 = d.x;
            d.y0 = d.y;
        });
    }

    /**
     * Centers the tree around all visible nodes.
     */
    centerTree()
    {
        // TODO Doesn't work

console.log("centerTree");
        // const zoom = this._svg.zoom.get();
        //
        // d3.select(this._svg)
        //     // .transition()
        //     // .duration(0)
        //     // .delay(100)
        //     .call(
        //         zoom.transform,
        //         d3.zoomIdentity.translate(t.x, t.y).scale(t.k)
        //     );
    }

    /**
     * Enter transition (new nodes).
     *
     * @param {selection}  enter
     * @param {Individual} source
     */
    nodeEnter(enter, source)
    {
        enter
            .append("g")
            .attr("opacity", 0)
            .attr("class", person => "person" + (person.data.spouse ? " spouse" : ""))
            .attr("transform", (person) => {
                return "translate(" + (person.x) + "," + (person.y) + ")";
                // TODO Enable this to zoom from source to person
                // return "translate(" + (source.x0) + "," + (source.y0) + ")";
            })
            // TODO Enable this to collapse/expand node on click
            // .on("click", (event, d) => this.togglePerson(event, d))
            .call(
                // Draw the actual person rectangle with an opacity of 0.5
                g => {
                    g.append("rect")
                        .attr(
                            "class",
                            person => (person.data.data.sex === SEX_FEMALE)
                                ? "female"
                                : (person.data.data.sex === SEX_MALE) ? "male" : "unknown"
                        )

                        .attr("rx", 20)
                        .attr("ry", 20)
                        .attr("x", -(this._orientation.boxWidth / 2))
                        .attr("y", -(this._orientation.boxHeight / 2))
                        .attr("width", this._orientation.boxWidth)
                        .attr("height", this._orientation.boxHeight)
                        .attr("fill-opacity", 0.5);

                    g.append("title")
                        .text(person => person.data.data.name);
                }
            )
            .call(
                // Draws the node (including image, names and dates)
                g => this.drawNode(g)
            )
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    // .delay(1000)
                    .attr("opacity", 1)
                    // TODO Enable this to zoom from source to person
                    // .attr("transform", (person) => {
                    //     return "translate(" + (person.x) + "," + (person.y) + ")";
                    // })
            );
    }

    /**
     * Update transition (existing nodes).
     *
     * @param {selection} update
     */
    nodeUpdate(update)
    {
        update
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    .attr("opacity", 1)
                    .attr("transform", (person) => {
                        return "translate(" + (person.x) + "," + (person.y) + ")";
                    })
            );
    }

    /**
     * Exit transition (nodes to be removed).
     *
     * @param {selection}  exit
     * @param {Individual} source
     */
    nodeExit(exit, source)
    {
        exit
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    .attr("opacity", 0)
                    .attr("transform", () => {
                        // Transition exit nodes to the source's position
                        return "translate(" + (source.x0) + "," + (source.y0) + ")";
                    })
                    .remove()
            );
    }

    /**
     * Draws the image and text nodes.
     *
     * @param {selection} parent The parent element to which the elements are to be attached
     */
    drawNode(parent)
    {
        const enter = parent.selectAll("g.image")
            .data((d) => {
                let images = [];

                if (d.data.data.thumbnail) {
                    images.push({
                        image: d.data.data.thumbnail
                    })
                }

                return images;
            })
            .enter();

        const group = enter.append("g")
            .attr("class", "image");

        // Background of image (only required if thumbnail has transparency (like the silhouettes))
        group
            .append("rect")
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("fill", "rgb(255, 255, 255)");

        // The individual image
        group
            .append("image")
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("clip-path", "url(#clip-image)");

        // Border around image
        group
            .append("rect")
            .attr("x", this._image.x)
            .attr("y", this._image.y)
            .attr("width", this._image.width)
            .attr("height", this._image.height)
            .attr("rx", this._image.rx)
            .attr("ry", this._image.ry)
            .attr("fill", "none")
            .attr("stroke", "rgb(200, 200, 200)")
            .attr("stroke-width", 1.5);

        // Asynchronously load the images
        d3.selectAll("g.image image")
            .each(function (d) {
                let image = d3.select(this);

                dataUrl(d.image)
                    .then(dataUrl => image.attr("xlink:href", dataUrl))
                    .catch((exception) => {
                        console.error(exception);
                    });
            });

        this.appendName(parent);
        this.appendDate(parent);
    }

    /**
     * Update a person's state when they are clicked.
     *
     * @param {Event}  event
     * @param {Person} person The person object containing the individual data
     */
    togglePerson(event, person)
    {
        if (person.children) {
            // Collapse
            person._children = person.children;
            person.children = null;
        } else {
            // Expand
            person.children = person._children;
            person._children = null;
        }

        this.draw(person);
    }

    /**
     * Add the individual names to the given parent element.
     *
     * @param {selection} parent The parent element to which the elements are to be attached
     */
    appendName(parent)
    {
        const name = parent
            .append("g")
            .attr("class", "name");

        // Top/Bottom and Bottom/Top
        if ((this._orientation instanceof OrientationTopBottom)
            || (this._orientation instanceof OrientationBottomTop)
        ) {
            const enter = name.selectAll("text")
                .data(datum => [
                    {
                        data: datum.data,
                        isRtl: datum.data.data.isNameRtl,
                        isAltRtl: datum.data.data.isAltRtl,
                        withImage: true
                    }
                ])
                .enter();

            enter
                .call((g) => {
                    const text = g.append("text")
                        .attr("class", "wt-chart-box-name")
                        .attr("text-anchor", "middle")
                        .attr("direction", d => d.isRtl ? "rtl" : "ltr")
                        .attr("alignment-baseline", "central")
                        .attr("y", this._text.y - 5);

                    this.addNameElements(
                        text,
                        datum => this.createNamesData(text, datum, true, false)
                    );
                })
                .call((g) => {
                    const text = g.append("text")
                        .attr("class", "wt-chart-box-name")
                        .attr("text-anchor", "middle")
                        .attr("direction", d => d.isRtl ? "rtl" : "ltr")
                        .attr("alignment-baseline", "central")
                        .attr("y", this._text.y + 15);

                    this.addNameElements(
                        text,
                        datum => this.createNamesData(text, datum, false, true)
                    );
                });

            // Add alternative name if present
            enter
                .filter(d => d.data.data.alternativeName !== "")
                .call((g) => {
                    const text = g.append("text")
                        .attr("class", "wt-chart-box-name")
                        .attr("text-anchor", "middle")
                        .attr("direction", d => d.isAltRtl ? "rtl" : "ltr")
                        .attr("alignment-baseline", "central")
                        .attr("y", this._text.y + 37)
                        .classed("wt-chart-box-name-alt", true);

                    this.addNameElements(
                        text,
                        datum => this.createAlternativeNamesData(text, datum)
                    );
                });

        // Left/Right and Right/Left
        } else {
            const enter = name.selectAll("text")
                .data(datum => [
                    {
                        data: datum.data,
                        isRtl: datum.data.data.isNameRtl,
                        isAltRtl: datum.data.data.isAltRtl,
                        withImage: datum.data.data.thumbnail !== ""
                    }
                ])
                .enter();

            enter
                .call((g) => {
                    const text = g.append("text")
                            .attr("class", "wt-chart-box-name")
                            .attr("text-anchor", (d) => {
                                if (d.isRtl && this._orientation.isDocumentRtl) {
                                    return "start";
                                }

                                if (d.isRtl || this._orientation.isDocumentRtl) {
                                    return "end";
                                }

                                return "start";
                            })
                            .attr("direction", d => d.isRtl ? "rtl" : "ltr")
                            .attr("x", d => this.textX(d))
                            .attr("y", this._text.y - 10);

                    this.addNameElements(
                        text,
                        datum => this.createNamesData(text, datum, true, true)
                    );
                });

            // Add alternative name if present
            enter
                .filter(datum => datum.data.data.alternativeName !== "")
                .call((g) => {
                    const text = g.append("text")
                        .attr("class", "wt-chart-box-name")
                        .attr("text-anchor", (d) => {
                            if (d.isAltRtl && this._orientation.isDocumentRtl) {
                                return "start";
                            }

                            if (d.isAltRtl || this._orientation.isDocumentRtl) {
                                return "end";
                            }

                            return "start";
                        })
                        .attr("direction", d => d.isAltRtl ? "rtl" : "ltr")
                        .attr("x", d => this.textX(d))
                        .attr("y", this._text.y + 8)
                        .classed("wt-chart-box-name-alt", true);

                    this.addNameElements(
                        text,
                        datum => this.createAlternativeNamesData(text, datum)
                    );
                });
        }
    }

    /**
     * Creates a single <tspan> element for each single name and append it to the
     * parent element. The "tspan" element containing the preferred name gets an
     * additional underline style in order to highlight this one.
     *
     * @param {selection}                       parent The parent element to which the <tspan> elements are to be attached
     * @param {function(*): LabelElementData[]} data
     */
    addNameElements(parent, data)
    {
         parent.selectAll("tspan")
             .data(data)
             .enter()
             .call((g) => {
                 g.append("tspan")
                     .text(datum => datum.label)
                     // Add some spacing between the elements
                     .attr("dx", (datum, index) => {
                         return index !== 0 ? ((datum.isNameRtl ? -1 : 1) * 0.25) + "em" : null;
                     })
                     // Highlight the preferred and last name
                     .classed("preferred", datum => datum.isPreferred)
                     .classed("lastName", datum => datum.isLastName);
             });
    }

    /**
     * Add the individual dates to the given parent element.
     *
     * @param {selection} parent The parent element to which the elements are to be attached
     */
    appendDate(parent)
    {
        const table = parent
            .append("g")
            .attr("class", "table");

        // Top/Bottom and Bottom/Top
        if ((this._orientation instanceof OrientationTopBottom)
            || (this._orientation instanceof OrientationBottomTop)
        ) {
            const enter = table.selectAll("text.date")
                .data(d => [{
                    label: d.data.data.timespan,
                    withImage: true
                }])
                .enter()

            const text = enter.append("text")
                .attr("class", "date")
                .attr("text-anchor", "middle")
                .attr("alignment-baseline", "central")
                .attr("y", this._text.y + 75);

            text.append("title")
                .text(d => d.label);

            const tspan = text.append("tspan");

            tspan.text(d => this.truncateDate(tspan, d.label, this._text.width));

            return;
        }

        const offset = 30;

        const enter = table.selectAll("text")
            .data((d) => {
                let data = [];

                if (d.data.data.birth) {
                    data.push({
                        icon: "★",
                        label: d.data.data.birth,
                        birth: true,
                        withImage: d.data.data.thumbnail !== ""
                    });
                }

                if (d.data.data.death) {
                    data.push({
                        icon: "†",
                        label: d.data.data.death,
                        death: true,
                        withImage: d.data.data.thumbnail !== ""
                    });
                }

                return data;
            })
            .enter();

        enter
            .call((g) => {
                const col1 = g.append("text")
                    .attr("fill", "currentColor")
                    .attr("text-anchor", "middle")
                    .attr("dominant-baseline", "middle")
                    .attr("x", d => this.textX(d))
                    // Minor offset here to better center the icon
                    .attr("y", (d, i) => ((this._text.y + offset) + (i === 0 ? 0 : 21)));

                col1.append("tspan")
                    .text(d => d.icon)
                    .attr("dx", (this._orientation.isDocumentRtl ? -1 : 1) * 5);

                const col2 = g.append("text")
                    .attr("class", "date")
                    .attr("text-anchor", "start")
                    .attr("dominant-baseline", "middle")
                    .attr("x", d => this.textX(d))
                    .attr("y", (d, i) => ((this._text.y + offset) + (i === 0 ? 0 : 20)));

                col2.append("title")
                    .text(d => d.label);

                const tspan = col2.append("tspan");

                tspan.text(d => this.truncateDate(tspan, d.label, this._text.width - (d.withImage ? this._image.width : 0) - 25))
                    .attr("dx", (this._orientation.isDocumentRtl ? -1 : 1) * 15)
                ;
            });
    }

    textX(d)
    {
        const xPos = this._text.x + (d.withImage ? this._image.width : 0);

        // Reverse direction of text elements for RTL layouts
        return this._orientation.isDocumentRtl ? -xPos : xPos;
    }

    /**
     * Creates the data array for the names.
     *
     * @param {Object}          parent
     * @param {NameElementData} datum
     * @param {Boolean}         addFirstNames
     * @param {Boolean}         addLastNames
     *
     * @return {LabelElementData[]}
     */
    createNamesData(parent, datum, addFirstNames, addLastNames)
    {
        /** @var {LabelElementData[]} names */
        let names = [];

        if (addFirstNames === true) {
            names = names.concat(
                datum.data.data.firstNames.map((firstName) => {
                    return {
                        label: firstName,
                        isPreferred: firstName === datum.data.data.preferredName,
                        isLastName: false,
                        isNameRtl: datum.data.data.isNameRtl
                    }
                })
            );
        }

        if (addLastNames === true) {
            // Append the last names
            names = names.concat(
                datum.data.data.lastNames.map((lastName) => {
                    return {
                        label: lastName,
                        isPreferred: false,
                        isLastName: true,
                        isNameRtl: datum.data.data.isNameRtl
                    }
                })
            );
        }

        // // If both first and last names are empty, add the full name as an alternative
        // if (!datum.data.data.firstNames.length
        //     && !datum.data.data.lastNames.length
        // ) {
        //     names = names.concat([{
        //         label: datum.data.data.name,
        //         isPreferred: false,
        //         isLastName: false
        //     }]);
        // }

        const fontSize   = parent.style("font-size");
        const fontWeight = parent.style("font-weight");

        // The total available width that the text can occupy
        let availableWidth = this._text.width;

        if (datum.withImage) {
            if ((this._orientation instanceof OrientationLeftRight)
                || (this._orientation instanceof OrientationRightLeft)
            ) {
                availableWidth -= this._image.width;
            }
        }

        return this.truncateNames(names, fontSize, fontWeight, availableWidth);
    }

    /**
     * Creates the data array for the alternative name.
     *
     * @param {Object}          parent
     * @param {NameElementData} datum
     *
     * @return {LabelElementData[]}
     */
    createAlternativeNamesData(parent, datum)
    {
        let words = datum.data.data.alternativeName.split(/\s+/);

        /** @var {LabelElementData[]} names */
        let names = [];

        // Append the alternative names
        names = names.concat(
            words.map((word) => {
                return {
                    label: word,
                    isPreferred: false,
                    isLastName: false,
                    isNameRtl: datum.data.data.isAltRtl
                }
            })
        );

        const fontSize   = parent.style("font-size");
        const fontWeight = parent.style("font-weight");

        // The total available width that the text can occupy
        let availableWidth = this._text.width;

        if (datum.withImage) {
            if ((this._orientation instanceof OrientationLeftRight)
                || (this._orientation instanceof OrientationRightLeft)
            ) {
                availableWidth -= this._image.width;
            }
        }

        return this.truncateNames(names, fontSize, fontWeight, availableWidth);
    }

    /**
     * Truncates the list of names.
     *
     * @param {LabelElementData[]} names          The names array
     * @param {String}             fontSize       The font size
     * @param {Number}             fontWeight     The font weight
     * @param {Number}             availableWidth The available width
     *
     * @return {LabelElementData[]}
     */
    truncateNames(names, fontSize, fontWeight, availableWidth)
    {
        let text = names.map(item => item.label).join(" ");

        return names
            // Start truncating from the last element to the first one
            .reverse()
            .map((name) => {
                // Select all not preferred and not last names
                if ((name.isPreferred === false)
                    && (name.isLastName === false)
                ) {
                    if (this.measureText(text, fontSize, fontWeight) > availableWidth) {
                        // Keep only the first letter
                        name.label = name.label.slice(0, 1) + ".";
                        text       = names.map(item => item.label).join(" ");
                    }
                }

                return name;
            })
            .map((name) => {
                // Afterward, the preferred ones if text takes still too much space
                if (name.isPreferred === true) {
                    if (this.measureText(text, fontSize, fontWeight) > availableWidth) {
                        // Keep only the first letter
                        name.label = name.label.slice(0, 1) + ".";
                        text       = names.map(item => item.label).join(" ");
                    }
                }

                return name;
            })
            .map((name) => {
                // Finally truncate lastnames
                if (name.isLastName === true) {
                    if (this.measureText(text, fontSize, fontWeight) > availableWidth) {
                        // Keep only the first letter
                        name.label = name.label.slice(0, 1) + ".";
                        text       = names.map(item => item.label).join(" ");
                    }
                }

                return name;
            })
            // Revert reversed order again
            .reverse();
    }

    /**
     * Truncates a date value.
     *
     * @param {Object} object         The D3 object containing the text value
     * @param {String} date           The date value to truncate
     * @param {Number} availableWidth The total available width the text could take
     *
     * @return {String}
     */
    truncateDate(object, date, availableWidth)
    {
        const fontSize   = object.style("font-size");
        const fontWeight = object.style("font-weight");

        let truncated = false;

        // Repeat removing the last char until the width matches
        while ((this.measureText(date, fontSize, fontWeight) > availableWidth) && (date.length > 1)) {
            // Remove last char
            date      = date.slice(0, -1).trim();
            truncated = true;
        }

        // Remove trailing dot if present
        if (date[date.length - 1] === ".") {
            date = date.slice(0, -1).trim();
        }

        return truncated ? (date + "…") : date;
    }

    /**
     * Measures the given text and return its width depending on the used font (including size and weight).
     *
     * @param {String} text
     * @param {String} fontSize
     * @param {Number} fontWeight
     *
     * @returns {Number}
     */
    measureText(text, fontSize, fontWeight = 400)
    {
        const fontFamily = this._svg.get().style("font-family");

        return measureText(text, fontFamily, fontSize, fontWeight);
    }

    /**
     * Draw the connecting lines.
     *
     * @param {Link[]}     links  Array of links
     * @param {Individual} source The root object
     *
     * @private
     */
    drawLinks(links, source)
    {
        this._svg.visual
            .selectAll("path.link")
            .data(links)
            .join(
                enter  => this.linkEnter(enter, source),
                update => this.linkUpdate(update),
                exit   => this.linkExit(exit, source)
            );
    }

    /**
     * Enter transition (new links).
     *
     * @param {selection}  enter
     * @param {Individual} source
     */
    linkEnter(enter, source)
    {
        enter
            .append("path")
            .classed("link", true)
            .attr("d", link => this._orientation.elbow(link))
            .call(
                g => g.transition()
                    .duration(this._configuration.duration)
                    .attr("opacity", 1)
            );
    }

    /**
     * Update transition (existing links).
     *
     * @param {selection} update
     */
    linkUpdate(update)
    {
        // TODO Enable for transitions
        // update
        //     .call(
        //         g => g.transition()
        //             // .duration(this._configuration.duration)
        //             .attr("opacity", 1)
        //             .attr("d", (link) => {
        //                 // link.source.x = source.x;
        //                 // link.source.y = source.y;
        //                 //
        //                 // if (link.target) {
        //                 //     link.target.x = source.x;
        //                 //     link.target.y = source.y;
        //                 // }
        //
        //                 return this._orientation.elbow(link);
        //             })
        //     );
    }

    /**
     * Exit transition (links to be removed).
     *
     * @param {selection}  exit
     * @param {Individual} source
     */
    linkExit(exit, source)
    {
        // TODO Enable for transitions
        // exit
        //     .call(
        //         g => g.transition()
        //             .duration(this._configuration.duration)
        //             .attr("opacity", 0)
        //             .attr("d", (link) => {
        //                 // link.source.x = source.x;
        //                 // link.source.y = source.y;
        //                 //
        //                 // if (link.target) {
        //                 //     link.target.x = source.x;
        //                 //     link.target.y = source.y;
        //                 // }
        //
        //                 return this._orientation.elbow(link);
        //             })
        //             .remove()
        //     );
    }
}
