/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { COUSIN_GAP_PX, SIBLING_GAP_PX, SPOUSE_GAP_PX } from "./constants.js";

/**
 * Pick the gap constant that should be enforced between two adjacent
 * family-nodes during d3-tree layout.
 *
 * - same `real` → spouse-gap (polygamy chain entries of the same person)
 * - same parent OR half-siblings across polygamy → sibling-gap
 * - anything else → cousin-gap
 */
export function pickGap(left, right) {
    if (sameRealId(left, right)) return SPOUSE_GAP_PX;
    if (left.parent === right.parent) return SIBLING_GAP_PX;
    if (halfSiblings(left, right)) return SIBLING_GAP_PX;
    return COUSIN_GAP_PX;
}

function sameRealId(left, right) {
    if (!left.data || !right.data) return false;
    if (left.data.kind !== "family" || right.data.kind !== "family") return false;
    if (!left.data.real || !right.data.real) return false;
    return left.data.real.id === right.data.real.id;
}

/**
 * Two nodes are half-siblings when their parent family-nodes share the
 * same `real` person — i.e. one biological parent had multiple partners
 * and each partner is the head of its own family-node.
 */
function halfSiblings(left, right) {
    if (!left.parent || !right.parent) return false;
    return sameRealId(left.parent, right.parent);
}
