/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { MARRIAGE_STAGGER_PX } from "@magicsunday/webtrees-chart-lib";
import { SPOUSE_GAP_PX } from "../constants.js";
import { familyRenderableMembers } from "../family-tree.js";

/**
 * @import { HierarchyNode } from "d3-hierarchy"
 * @import { Orientation } from "@magicsunday/webtrees-chart-lib"
 */

/**
 * Walks the d3 hierarchy after layout and produces two flat, position-only
 * payloads:
 *
 * - `renderedBoxes`: one entry per person box that should be drawn
 * - `connections`: one entry per family that has children (or a marriage
 *   line to draw), describing the geometric inputs the line drawer needs
 *   (father/mother box positions, list of child positions, optional
 *   intermediate boxes for polygamous marriage chains, marriage stagger)
 *
 * Both lists are pure data — downstream drawers don't need to know
 * anything about the d3-hierarchy or the family-node encoding.
 *
 * @param {HierarchyNode<any>} root             d3-hierarchy root after .tree() laid it out
 * @param {Orientation}        orientation      The active orientation
 * @param {boolean}            isVerticalLayout True for top-bottom / bottom-top
 *
 * @returns {{renderedBoxes: Array, connections: Array}}
 */
export function buildConnections(root, orientation, isVerticalLayout) {
    const stackBox = isVerticalLayout ? orientation.boxWidth : orientation.boxHeight;

    const familyNodes = root.descendants().filter(isFamilyNode);

    const { renderedBoxes, realPositions, motherPositions, allBoxes } = collectRenderedBoxes(
        familyNodes,
        stackBox,
        SPOUSE_GAP_PX,
        isVerticalLayout,
    );

    const connections = familyNodes
        .map((node) =>
            buildConnection(node, realPositions, motherPositions, allBoxes, isVerticalLayout),
        )
        .filter((connection) => connection !== null);

    return { renderedBoxes, connections };
}

function isFamilyNode(node) {
    return node.data && node.data.kind === "family";
}

/**
 * Lay out the person boxes for every family-node and remember:
 *   - the position of the real-person box (keyed by `NodeData.id`, the
 *     unique sequence id assigned by the PHP DataFacade; only set from
 *     the realFirst node so each polygamous real-person resolves to one
 *     shared box),
 *   - the position of the spouse box (keyed by the d3-hierarchy node id,
 *     so each polygamous family-node has its own spouse).
 */
function collectRenderedBoxes(familyNodes, stackBox, spouseGap, isVerticalLayout) {
    const renderedBoxes = [];
    const realPositions = new Map();
    const motherPositions = new Map();
    const allBoxes = [];

    for (const node of familyNodes) {
        const members = familyRenderableMembers(node.data);
        if (members.length === 0) continue;

        const totalSpan = members.length * stackBox + (members.length - 1) * spouseGap;
        const start = -(totalSpan - stackBox) / 2;

        members.forEach((member, idx) => {
            const offset = start + idx * (stackBox + spouseGap);
            const boxX = isVerticalLayout ? node.x + offset : node.x;
            const boxY = isVerticalLayout ? node.y : node.y + offset;
            const pos = { x: boxX, y: boxY };

            renderedBoxes.push({
                id: `${node.id}-${idx}`,
                x: boxX,
                y: boxY,
                data: { data: member.data, spouse: !member.isReal },
            });
            allBoxes.push(pos);

            if (member.isReal && node.data.realFirst) {
                realPositions.set(node.data.real.id, pos);
            }
            if (!member.isReal) {
                motherPositions.set(node.id, pos);
            }
        });
    }

    return { renderedBoxes, realPositions, motherPositions, allBoxes };
}

/**
 * Build one FamilyConnection descriptor for the given family-node, or
 * `null` when the node has neither a marriage line nor children to draw.
 */
function buildConnection(node, realPositions, motherPositions, allBoxes, isVerticalLayout) {
    const realId = node.data.real?.id;
    if (realId === undefined) return null;

    const fatherPos = realPositions.get(realId);
    if (!fatherPos) return null;

    const motherPos = motherPositions.get(node.id) || null;
    const children = collectChildPositions(node, realPositions);

    if (children.length === 0 && !motherPos) return null;

    return {
        father: fatherPos,
        mother: motherPos,
        children,
        intermediateBoxes: collectIntermediateBoxes(
            fatherPos,
            motherPos,
            allBoxes,
            isVerticalLayout,
        ),
        marriageStagger: marriageStaggerFor(node),
    };
}

/**
 * Children for one family. d3-children may contain multiple family-nodes
 * for the same person (= polygamous child with several spouses) — those
 * represent ONE genealogical child, so dedupe by real.id and target each
 * unique child's rendered real-person box.
 */
function collectChildPositions(node, realPositions) {
    const dChildren = Array.isArray(node.children) ? node.children : [];
    const seen = new Set();
    const positions = [];

    for (const child of dChildren) {
        if (!isFamilyNode(child)) continue;
        const childRealId = child.data.real?.id;
        if (childRealId === undefined || seen.has(childRealId)) continue;
        seen.add(childRealId);

        const childRealPos = realPositions.get(childRealId);
        if (childRealPos) {
            positions.push(childRealPos);
        }
    }

    return positions;
}

/**
 * For polygamous continuation marriages the father and mother sit on the
 * same row but with other people's boxes between them. Find those (by
 * scanning all rendered boxes at the father's row) so the line drawer can
 * chain segments through their inter-box gaps.
 */
function collectIntermediateBoxes(fatherPos, motherPos, allBoxes, isVerticalLayout) {
    if (!motherPos) return [];

    const onSameRow = isVerticalLayout
        ? (b) => Math.abs(b.y - fatherPos.y) < 1
        : (b) => Math.abs(b.x - fatherPos.x) < 1;
    const between = isVerticalLayout
        ? (b) =>
              Math.min(fatherPos.x, motherPos.x) < b.x && b.x < Math.max(fatherPos.x, motherPos.x)
        : (b) =>
              Math.min(fatherPos.y, motherPos.y) < b.y && b.y < Math.max(fatherPos.y, motherPos.y);

    const result = [];
    for (const box of allBoxes) {
        if (box === fatherPos || box === motherPos) continue;
        if (!onSameRow(box)) continue;
        if (!between(box)) continue;
        result.push(box);
    }

    const axis = isVerticalLayout ? "x" : "y";
    result.sort((a, b) => a[axis] - b[axis]);

    return result;
}

/**
 * Cross-axis stagger for the marriage line of a polygamous-group entry.
 * The first marriage of a real-person sits furthest from the row centre,
 * each subsequent marriage pulls one step closer, the last marriage lands
 * on the centre.
 */
function marriageStaggerFor(node) {
    if (!node.parent || !Array.isArray(node.parent.children)) return 0;
    const realId = node.data?.real?.id;
    if (realId === undefined) return 0;

    const group = node.parent.children.filter(
        (sibling) => sibling.data?.kind === "family" && sibling.data.real?.id === realId,
    );
    if (group.length <= 1) return 0;

    const ownIndex = group.indexOf(node);
    if (ownIndex < 0) return 0;

    return MARRIAGE_STAGGER_PX * (group.length - 1 - ownIndex);
}
