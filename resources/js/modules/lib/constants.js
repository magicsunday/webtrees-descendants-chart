/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

/**
 * The widths and heights of a single node in each tree layout.
 *
 * @type {number}
 * @const
 */
export const LAYOUT_HORIZONTAL_NODE_WIDTH = 325;
export const LAYOUT_HORIZONTAL_NODE_HEIGHT = 95;
export const LAYOUT_VERTICAL_NODE_WIDTH = 160;
export const LAYOUT_VERTICAL_NODE_HEIGHT = 175;

export const LAYOUT_VERTICAL_NODE_HEIGHT_OFFSET = 30;

/**
 * Tree layout variants.
 *
 * @type {string}
 * @const
 *
 * @see \Fisharebest\Webtrees\Module\PedigreeChartModule
 */
export const LAYOUT_TOPBOTTOM = "down";
export const LAYOUT_BOTTOMTOP = "up";
export const LAYOUT_LEFTRIGHT = "right";
export const LAYOUT_RIGHTLEFT = "left";

/**
 * Gender types.
 *
 * @type {string}
 * @const
 */
export const SEX_MALE = "M";
export const SEX_FEMALE = "F";

/**
 * Inter-box gaps used by the layout. All link-drawing code derives its
 * positions from these constants so changing any value rebalances the
 * whole tree without breaking marriage/elbow geometry.
 *
 * @type {number}
 * @const
 */
// Pixel gap between adjacent person boxes inside one couple.
export const SPOUSE_GAP_PX = 30;
// Pixel gap between adjacent same-parent siblings.
export const SIBLING_GAP_PX = 30;
// Pixel gap between adjacent cross-parent cousins.
export const COUSIN_GAP_PX = 78;
// Cross-axis stagger between successive marriage lines for the same
// real-person. The first spouse's line sits furthest from the box-row
// centre, every subsequent spouse pulls in by one step, the last spouse
// lands at the centre — keeps multi-marriage bundles distinguishable.
export const MARRIAGE_STAGGER_PX = 5;
// Pixels trimmed from each end of a marriage segment so the line stops
// just short of the adjacent box edges instead of touching them.
export const LINE_END_TRIM_PX = 2;
