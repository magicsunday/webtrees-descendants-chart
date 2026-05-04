import { describe, expect, test } from "@jest/globals";
import {
    buildFamilyTree,
    familyRenderableMembers,
    familyRenderedWidth,
} from "../modules/family-tree.js";

const makePerson = (id, name) => ({ id, name });

// Behaviour-only tests for the polygamy/recursion rules. The trivial
// arithmetic of familyRenderedWidth and the array-shape variants of
// familyRenderableMembers are not tested in isolation — they are
// already exercised through the buildFamilyTree integration cases below
// (recursive children + polygamous shared real-person).

describe("buildFamilyTree", () => {
    test("wraps a polygamous subject's families in a pseudo-root with shared real-person", () => {
        // The chart subject is married to two spouses. Both family-nodes
        // must reference the same `real` so the renderer draws one
        // real-person box; only the first carries `realFirst: true` so
        // the row reads [real][spouse_1][spouse_2] without duplication.
        const couple = {
            members: [makePerson(1, "John"), makePerson(2, "Jane"), makePerson(3, "Mary")],
            memberFamilies: [
                { family: 0, spouseIndex: 1, children: [] },
                { family: 1, spouseIndex: 2, children: [] },
            ],
        };

        const tree = buildFamilyTree(couple);

        expect(tree.kind).toBe("pseudo-root");
        expect(tree.children).toHaveLength(2);
        expect(tree.children[0].realFirst).toBe(true);
        expect(tree.children[1].realFirst).toBe(false);
        expect(tree.children[0].real).toBe(tree.children[1].real);
    });

    test("flattens polygamous descendants into the parent's children list", () => {
        // A child marries two spouses. coupleToFamilies returns one
        // family-node per marriage; those get flatMapped into the
        // parent's children so the row reads as siblings rather than
        // requiring an intermediate pseudo-root inside the parent's
        // branch.
        const couple = {
            members: [makePerson(1, "Grandparent")],
            memberFamilies: [
                {
                    family: 0,
                    spouseIndex: null,
                    children: [
                        {
                            members: [
                                makePerson(2, "MiddleChild"),
                                makePerson(3, "Spouse1"),
                                makePerson(4, "Spouse2"),
                            ],
                            memberFamilies: [
                                { family: 0, spouseIndex: 1, children: [] },
                                { family: 1, spouseIndex: 2, children: [] },
                            ],
                        },
                    ],
                },
            ],
        };

        const tree = buildFamilyTree(couple);

        expect(tree.children).toHaveLength(2);
        expect(tree.children[0].realFirst).toBe(true);
        expect(tree.children[1].realFirst).toBe(false);
        // Both polygamous family-nodes share the same MiddleChild instance.
        expect(tree.children[0].real).toBe(tree.children[1].real);
    });
});

describe("familyRenderableMembers", () => {
    test("hides the real-person box on every family-node after the first", () => {
        // This is the core invariant that prevents the polygamous
        // real-person from being drawn once per spouse-family. Without
        // the realFirst gate the renderer would emit a duplicate box.
        const real = makePerson(1);
        const spouse1 = makePerson(2);
        const spouse2 = makePerson(3);

        const first = { kind: "family", real, spouse: spouse1, realFirst: true, children: [] };
        const second = { kind: "family", real, spouse: spouse2, realFirst: false, children: [] };

        expect(familyRenderableMembers(first)).toEqual([
            { data: real, isReal: true },
            { data: spouse1, isReal: false },
        ]);
        expect(familyRenderableMembers(second)).toEqual([{ data: spouse2, isReal: false }]);
    });
});

describe("familyRenderedWidth", () => {
    test("subsequent polygamous family-node spans one box, not two", () => {
        // Width must shrink to one box on family-nodes where the
        // real-person is hidden, otherwise the row would have a phantom
        // gap where the duplicate real-person would have been.
        const first = { kind: "family", real: {}, spouse: {}, realFirst: true, children: [] };
        const second = { kind: "family", real: {}, spouse: {}, realFirst: false, children: [] };

        expect(familyRenderedWidth(first, 100, 20)).toBe(220); // box + gap + box
        expect(familyRenderedWidth(second, 100, 20)).toBe(100); // box only
    });
});
