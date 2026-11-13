/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import Configuration from "../modules/configuration.js";

const LABELS = { zoom: "Zoom", move: "Move" };

// The switches of the constructor in the order of its parameters.
const switches = ["openNewTabOnClick", "showAlternativeName", "rtl", "showDetailPanel"];

/**
 * Builds a configuration in which exactly one switch is on, so that every switch has a pattern of its own.
 *
 * @param {string} enabled The switch that is on
 *
 * @return {Configuration}
 */
function buildWithOnly(enabled) {
    const on = (name) => name === enabled;

    return new Configuration(
        LABELS,
        7,
        "down",
        on("openNewTabOnClick"),
        on("showAlternativeName"),
        "SURNAME",
        on("rtl"),
        on("showDetailPanel"),
    );
}

describe("Configuration", () => {
    test("hands the labels, the generations, the layout and the name abbreviation to their getters", () => {
        const configuration = buildWithOnly("rtl");

        expect(configuration.labels).toBe(LABELS);
        expect(configuration.generations).toBe(7);
        expect(configuration.treeLayout).toBe("down");
        expect(configuration.orientation).toBeDefined();
        expect(configuration.nameAbbreviation).toBe("SURNAME");
    });

    test.each(switches)("hands the switch %s to its own getter and to no other", (enabled) => {
        const configuration = buildWithOnly(enabled);

        for (const name of switches) {
            expect(configuration[name]).toBe(name === enabled);
        }
    });

    test("holds no JavaScript default for an option of the chart page", () => {
        const configuration = new Configuration(LABELS);

        // Configuration.php is the only source of the defaults, and the chart page passes
        // every one of them. The alternative name differed: JavaScript said true, PHP says false.
        expect(configuration.generations).toBeUndefined();
        expect(configuration.treeLayout).toBeUndefined();
        expect(configuration.openNewTabOnClick).toBeUndefined();
        expect(configuration.showAlternativeName).toBeUndefined();
        expect(configuration.showDetailPanel).toBeUndefined();
        expect(configuration.nameAbbreviation).toBeUndefined();
        expect(configuration.rtl).toBeUndefined();
    });
});
