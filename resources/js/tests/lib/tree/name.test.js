/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import Name from "resources/js/modules/lib/tree/name.js";

/**
 * Builds a Name instance whose measureText() is deterministic
 * (each character costs 10 pixels) so truncateNames() can be exercised
 * without a real chart-lib measurement.
 */
function makeName() {
    const name = Object.create(Name.prototype);
    name.measureText = (text) => text.length * 10;
    return name;
}

describe("Name.truncateNames", () => {
    it("keeps a bracketed entry intact when the line fits", () => {
        const name = makeName();
        const names = [
            { label: "Anna",     isPreferred: false, isLastName: false, isNameRtl: false },
            { label: "Schmidt",  isPreferred: false, isLastName: true,  isNameRtl: false },
            { label: "(Müller)", isPreferred: false, isLastName: true,  isNameRtl: false },
        ];

        // "Anna Schmidt (Müller)" = 21 chars * 10 = 210px; allow 1000px (no truncation).
        const result = name.truncateNames(names, "12px", 400, 1000);

        expect(result.map(n => n.label)).toEqual(["Anna", "Schmidt", "(Müller)"]);
    });

    it("drops a bracketed entry entirely when the line overflows (no '(.' truncation)", () => {
        const name = makeName();
        const names = [
            { label: "Anna",      isPreferred: false, isLastName: false, isNameRtl: false },
            { label: "Zschimmer", isPreferred: false, isLastName: true,  isNameRtl: false },
            { label: "(Müller)",  isPreferred: false, isLastName: true,  isNameRtl: false },
        ];

        // Whole text "Anna Zschimmer (Müller)" = 23 chars * 10 = 230px; allow 100px so the
        // last-name truncation pass is forced to act on at least the bracketed entry.
        const result = name.truncateNames(names, "12px", 400, 100);

        // The bracketed entry must be filtered out entirely — never reduced to "(."
        expect(result.every(n => n.label !== "(.")).toBe(true);
        expect(result.map(n => n.label)).not.toContain("(Müller)");
        expect(result.map(n => n.label)).not.toContain("(.");
    });
});
