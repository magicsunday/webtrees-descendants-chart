/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

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
 * positions from these constants so changing any value rebalances the whole
 * tree without breaking marriage/elbow geometry.
 *
 * @type {number}
 * @const
 */
// Pixel gap between adjacent person boxes inside one couple.
export const SPOUSE_GAP_PX = 30;
// Pixel gap between adjacent same-parent siblings.
// 30 lifts the centre-to-centre distance to 1.0 × pedigree-nodeWidth
// (= 160 + 30) — the per-sibling spacing standard set by issue #74's
// pedigree fix.
export const SIBLING_GAP_PX = 30;
// Pixel gap between adjacent cross-parent cousins.
// 78 lifts the centre-to-centre distance to ≈1.25 × pedigree-nodeWidth
// (= 1.25 × (160 + 30) = 237.5; (160 + 78) = 238). Same 1.25 ratio
// pedigree's separation() uses for cousins, so descendants and pedigree
// stay visually aligned.
export const COUSIN_GAP_PX = 78;
