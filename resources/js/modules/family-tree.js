/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

/**
 * Convert a CoupleNode payload (as emitted by the PHP DataFacade) into a
 * d3-friendly tree of FamilyNodes.
 *
 * Each FamilyNode represents one (real-person + 0..1 spouse + their direct
 * children-as-FamilyNodes). Polygamous individuals contribute multiple
 * FamilyNodes that share the same `real` reference; only the first such node
 * carries `realFirst: true`, every subsequent one renders just its spouse so
 * the row reads as `[real][spouse_1][spouse_2]…` without duplicating the
 * real-person box.
 *
 * The chart subject's families form the top of the returned structure:
 * - one family ⇒ that FamilyNode is the root,
 * - many families ⇒ a `pseudo-root` node holds them as children. Pseudo-roots
 *   are layout-only; the renderer skips them.
 *
 * @param {CoupleNode} coupleData The wire-format couple tree emitted by the PHP DataFacade
 *
 * @returns {FamilyTreeNode}
 */
export function buildFamilyTree(coupleData) {
    const families = coupleToFamilies(coupleData);
    if (families.length === 1) {
        return families[0];
    }
    return {
        kind: "pseudo-root",
        children: families,
    };
}

/**
 * Converts one wire-format couple node into the family-nodes it contributes.
 *
 * @param {CoupleNode} coupleData The couple node to convert
 *
 * @returns {FamilyNode[]}
 */
function coupleToFamilies(coupleData) {
    if (!coupleData || !Array.isArray(coupleData.members) || coupleData.members.length === 0) {
        return [];
    }

    const real = coupleData.members[0];
    const families = Array.isArray(coupleData.memberFamilies) ? coupleData.memberFamilies : [];

    if (families.length === 0) {
        return [
            {
                kind: "family",
                real,
                spouse: null,
                family: 0,
                realFirst: true,
                children: [],
            },
        ];
    }

    return families.map((mf, idx) => {
        const spouse =
            mf.spouseIndex !== null &&
            mf.spouseIndex !== undefined &&
            coupleData.members[mf.spouseIndex]
                ? coupleData.members[mf.spouseIndex]
                : null;

        const children = Array.isArray(mf.children)
            ? mf.children.flatMap((child) => coupleToFamilies(child))
            : [];

        return {
            kind: "family",
            real,
            spouse,
            family: mf.family,
            realFirst: idx === 0,
            children,
        };
    });
}

/**
 * Width of a family-node when rendered, along the spread axis. A family with
 * both real and spouse boxes spans `2 × box + spouseGap`; a spouse-only family
 * or a singleton spans one box.
 *
 * @param {FamilyTreeNode} familyData The family-tree node to measure
 * @param {number}         boxSize    The box extent along the spread axis
 * @param {number}         spouseGap  The gap between the real-person and spouse box
 *
 * @returns {number}
 */
export function familyRenderedWidth(familyData, boxSize, spouseGap) {
    if (familyData?.kind !== "family") {
        return 0;
    }
    const renderedCount =
        (familyData.realFirst && familyData.real ? 1 : 0) + (familyData.spouse ? 1 : 0);
    if (renderedCount === 0) {
        return 0;
    }
    return renderedCount * boxSize + Math.max(0, renderedCount - 1) * spouseGap;
}

/**
 * Returns an array of `{ data, isReal }` entries describing every person box
 * this family-node should render, ordered left-to-right (vertical layout) or
 * top-to-bottom (horizontal layout).
 *
 * @param {FamilyTreeNode} familyData The family-tree node to render
 *
 * @returns {Array<{data: Data, isReal: boolean}>}
 */
export function familyRenderableMembers(familyData) {
    if (familyData?.kind !== "family") {
        return [];
    }
    const list = [];
    if (familyData.realFirst && familyData.real) {
        list.push({ data: familyData.real, isReal: true });
    }
    if (familyData.spouse) {
        list.push({ data: familyData.spouse, isReal: false });
    }
    return list;
}
