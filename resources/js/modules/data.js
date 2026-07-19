/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

/**
 * This files defines the internal used structures of objects.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */

/**
 * The plain person data emitted by the PHP DataFacade.
 *
 * Mirrors `NodeData::jsonSerialize()` one-to-one: every key is always present.
 * A placeholder node (a couple slot with no known individual) is built from a
 * default-constructed `NodeData`, so it carries the same keys with their PHP
 * type defaults — `xref`, `name`, `birth`, … as empty strings, `firstNames` and
 * `lastNames` as empty arrays, `sex` as `"U"`. Consumers therefore detect a
 * placeholder by an empty `xref`, never by a missing property.
 *
 * @typedef {object} Data
 * @property {number}   id              The unique ID of the person
 * @property {string}   xref            The unique identifier of the person (empty for a placeholder)
 * @property {string}   url             The URL of the individual's webtrees page
 * @property {string}   updateUrl       The URL used to re-root the chart on this individual
 * @property {number}   generation      The generation the individual belongs to (1 = chart subject)
 * @property {string}   sex             The sex of the person
 * @property {string}   birth           The birthdate of the person
 * @property {string}   death           The death date of the person
 * @property {string}   timespan        The lifetime description
 * @property {string}   thumbnail       The URL of the thumbnail image
 * @property {string}   silhouette      The URL of the sex-specific silhouette used as onerror fallback
 * @property {string}   name            The full name of the individual
 * @property {string}   preferredName   The preferred first name
 * @property {string[]} firstNames      The list of first names
 * @property {string[]} lastNames       The list of last names
 * @property {string}   nickname        Quoted nickname inserted between given names and surname (empty when not enabled)
 * @property {string}   alternativeName The alternative name of the individual
 * @property {boolean}  isNameRtl       Whether the primary name should render right-to-left
 * @property {boolean}  isAltRtl        Whether the alternative name should render right-to-left
 */

/**
 * One child branch of a couple node, as emitted by `CoupleNode::jsonSerialize()`.
 *
 * @typedef {object} CoupleFamily
 * @property {number}       family      The family index (0 = first family, 1 = second, ...)
 * @property {number|null}  spouseIndex The index into `CoupleNode.members` of the spouse of this
 *                                      branch, or `null` when the branch has no recorded partner
 * @property {CoupleNode[]} children    The descendant couple nodes of this family
 */

/**
 * The wire-format tree emitted by the PHP DataFacade. `members[0]` is always the
 * "real" person; later entries are that person's spouses in registration order.
 *
 * @typedef {object} CoupleNode
 * @property {Data[]}         members        The real person followed by their spouses
 * @property {CoupleFamily[]} memberFamilies The per-family child branches
 */

/**
 * One renderable family-node of the client-side tree built by `family-tree.js`.
 * A polygamous individual contributes several of these sharing the same `real`
 * reference; only the first carries `realFirst: true`.
 *
 * @typedef {object} FamilyNode
 * @property {"family"}     kind      Discriminator of the family-tree node union
 * @property {Data}         real      The real person of this couple
 * @property {Data|null}    spouse    The spouse of this family, or `null` when unknown
 * @property {number}       family    The family index (0 = first family, 1 = second, ...)
 * @property {boolean}      realFirst Whether this node renders the real-person box
 * @property {FamilyNode[]} children  The descendant family-nodes
 */

/**
 * Layout-only root that holds the chart subject's families when there is more
 * than one. The renderer skips it.
 *
 * @typedef {object} PseudoRootNode
 * @property {"pseudo-root"} kind     Discriminator of the family-tree node union
 * @property {FamilyNode[]}  children The chart subject's families
 */

/**
 * Any node of the client-side family tree fed into `d3.hierarchy()`.
 *
 * @typedef {FamilyNode|PseudoRootNode} FamilyTreeNode
 */

/**
 * The person payload bound to a rendered box. The `spouse` flag drives the
 * `.spouse` CSS class and the double `data.data` access in the drawers.
 *
 * @typedef {object} PersonBoxData
 * @property {Data}    data   The person data
 * @property {boolean} spouse Whether this box renders a spouse rather than the real person
 */

/**
 * A positioned person box produced by `connection-builder.js` and bound as the
 * d3 datum of a `g.person` element.
 *
 * @typedef {object} RenderedBox
 * @property {string}        id   The d3 join key, `"<hierarchy node id>-<member index>"`
 * @property {number}        x    The X-coordinate of the box centre
 * @property {number}        y    The Y-coordinate of the box centre
 * @property {PersonBoxData} data The person payload of the box
 */

/**
 * Renderable box descriptor produced by `connection-builder.js` and passed down
 * to the name/date drawing methods. The double `data.data` reflects the
 * d3-hierarchy wrapping of the FamilyNode plus the person payload.
 *
 * @typedef {object} NameElementData
 * @property {PersonBoxData} data
 * @property {boolean} [isRtl]
 * @property {boolean} [isAltRtl]
 * @property {boolean} [withImage]
 */

/**
 * One date row bound to a `text.date` element by `tree/date.js`. Vertical
 * layouts render a single lifetime row (`label` + `withImage`); horizontal
 * layouts render a birth and/or death row that additionally carries the marker
 * icon and the corresponding flag.
 *
 * @typedef {object} DateElementData
 * @property {string}  label     The formatted date text
 * @property {boolean} withImage Whether a highlight image takes up part of the box width
 * @property {string}  [icon]    The birth (★) or death (†) marker, horizontal layouts only
 * @property {boolean} [birth]   Whether this row holds the birthdate
 * @property {boolean} [death]   Whether this row holds the death date
 */

/**
 * @typedef {object} LabelElementData
 * @property {string}  label
 * @property {boolean} isPreferred
 * @property {boolean} isLastName
 * @property {boolean} isNameRtl
 */
