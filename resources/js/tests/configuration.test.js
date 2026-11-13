/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import Configuration from "../modules/configuration.js";

const LABELS = { zoom: "Zoom", move: "Move" };

describe("Configuration", () => {
    test("passes every option that PHP owns to its getter", () => {
        const configuration = new Configuration(
            LABELS,
            7,
            "top-bottom",
            false,
            true,
            "SURNAME",
            true,
            true,
        );

        expect(configuration.generations).toBe(7);
        expect(configuration.treeLayout).toBe("top-bottom");
        expect(configuration.openNewTabOnClick).toBe(false);
        expect(configuration.showAlternativeName).toBe(true);
        expect(configuration.showDetailPanel).toBe(true);
        expect(configuration.nameAbbreviation).toBe("SURNAME");
        expect(configuration.rtl).toBe(true);
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
