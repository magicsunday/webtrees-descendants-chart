/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { pickGap } from "resources/js/modules/lib/separation.js";
import {
    COUSIN_GAP_PX,
    SIBLING_GAP_PX,
    SPOUSE_GAP_PX,
} from "resources/js/modules/lib/constants.js";

const family = (realId, parent = null) => ({
    parent,
    data: { kind: "family", real: { id: realId } },
});

describe("pickGap", () => {
    it("returns spouse-gap when both nodes share the same real person (polygamy chain)", () => {
        const root = family(null);
        const a = family(1, root);
        const b = family(1, root);
        expect(pickGap(a, b)).toBe(SPOUSE_GAP_PX);
    });

    it("returns sibling-gap when both nodes have the same parent", () => {
        const root = family(null);
        const a = family(2, root);
        const b = family(3, root);
        expect(pickGap(a, b)).toBe(SIBLING_GAP_PX);
    });

    it("returns sibling-gap for half-siblings under polygamy parents", () => {
        // Polygamy parent chain: same biological parent (#10), two spouses → two
        // family-nodes. Their children are half-siblings (share parent #10).
        const root = family(null);
        const polyA = family(10, root);
        const polyB = family(10, root);
        const childOfA = family(20, polyA);
        const childOfB = family(21, polyB);
        expect(pickGap(childOfA, childOfB)).toBe(SIBLING_GAP_PX);
    });

    it("returns cousin-gap for true cousins (parents are siblings, no shared real)", () => {
        const root = family(null);
        const parentA = family(10, root);
        const parentB = family(11, root);
        const childA = family(20, parentA);
        const childB = family(21, parentB);
        expect(pickGap(childA, childB)).toBe(COUSIN_GAP_PX);
    });

    it("treats parentless nodes (root level) as same-parent siblings", () => {
        // d3 always passes nodes that share a parent, so this is the
        // degenerate case — both .parent are null and the equality check
        // resolves to sibling-gap. Documented to lock the behaviour.
        const a = family(1);
        const b = family(2);
        expect(pickGap(a, b)).toBe(SIBLING_GAP_PX);
    });
});
