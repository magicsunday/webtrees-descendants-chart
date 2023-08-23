/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

/**
 * This files defines the internal used structures of objects.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */

import {Node} from "./d3";

/**
 * The plain person data.
 *
 * @typedef {Object} Data
 * @property {Number}   id            The unique ID of the person
 * @property {String}   xref          The unique identifier of the person
 * @property {String}   sex           The sex of the person
 * @property {String}   birth         The birthdate of the person
 * @property {String}   death         The death date of the person
 * @property {String}   timespan      The lifetime description
 * @property {String}   thumbnail     The URL of the thumbnail image
 * @property {String}   name          The full name of the individual
 * @property {String}   preferredName The preferred first name
 * @property {String[]} firstNames    The list of first names
 * @property {String[]} lastNames     The list of last names
 */

/**
 * A person object.
 *
 * @typedef {Object} Person
 * @property {null|Data}          data     The data object of the individual
 * @property {undefined|String[]} spouses  The list of assigned spouses (not available if "spouse" is set)
 * @property {undefined|Object[]} children The list of children of this individual
 * @property {undefined|Number}   family   The family index (0 = first family, 1 = second, ...)
 * @property {undefined|Number}   spouse   The unique ID of the direct spouse of this individual
 */

/**
 * An individual. Extends the D3 Node object.
 *
 * @typedef {Node} Individual
 * @property {Person}       data     The individual data
 * @property {Individual[]} children The children of the node
 * @property {Number}       x        The X-coordinate of the node
 * @property {Number}       y        The Y-coordinate of the node
 */

/**
 * An X/Y coordinate.
 *
 * @typedef {Object} Coordinate
 * @property {Number} x The X-coordinate
 * @property {Number} y The Y-coordinate
 */

/**
 * A link between two nodes.
 *
 * @typedef {Object} Link
 * @property {Individual}                source The source individual
 * @property {null|Individual}           target The target individual
 * @property {null|undefined|Individual} spouse The spouse of the source individual
 * @property {null|Coordinate[]}         coords The list of the spouse coordinates
 */
