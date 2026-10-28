/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

const { default: Configuration } = await import("resources/js/modules/configuration.js");

describe("Configuration showDetailPanel", () => {
    test("is off by default", () => {
        expect(new Configuration({ zoom: "", move: "" }).showDetailPanel).toBe(false);
    });

    test("takes the option as the last constructor argument", () => {
        const configuration = new Configuration(
            { zoom: "", move: "" },
            5,
            "left-right",
            false,
            true,
            "SURNAME",
            true,
            true,
        );

        expect(configuration.showDetailPanel).toBe(true);
        expect(configuration.rtl).toBe(true);
        expect(configuration.nameAbbreviation).toBe("SURNAME");
        expect(configuration.generations).toBe(5);
    });
});
