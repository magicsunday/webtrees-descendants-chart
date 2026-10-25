/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { jest } from "@jest/globals";

const registered = [];

await jest.unstable_mockModule("@magicsunday/webtrees-chart-lib/chart-core", () => ({
    buildChartAjaxUrl: jest.fn(() => "https://example.test/ajax"),
    setChartAjaxUrl: jest.fn(),
    setChartOptionsGlobal: jest.fn(),
    syncCollapseToggle: jest.fn(),
    Storage: class {
        /**
         * Records the name of the registered option.
         *
         * @param {string} name The option name
         */
        register(name) {
            registered.push(name);
        }

        /**
         * Returns no stored number value, as an empty storage does.
         *
         * @return {null}
         */
        readNumber() {
            return null;
        }

        /**
         * Returns no stored string value, as an empty storage does.
         *
         * @return {null}
         */
        readString() {
            return null;
        }

        /**
         * Returns no stored boolean value, as an empty storage does.
         *
         * @return {null}
         */
        readBool() {
            return null;
        }
    },
}));

const { initPage } = await import("resources/js/modules/page-init.js");

describe("initPage", () => {
    beforeEach(() => {
        registered.length = 0;
        document.body.innerHTML = '<form id="webtrees-descendants-chart-form"></form>';
    });

    it("registers exactly the options that have a field on the chart page form", () => {
        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(registered.sort()).toEqual([
            "generations",
            "hideSpouses",
            "layout",
            "openNewTabOnClick",
            "showAlternativeName",
            "showNicknames",
        ]);
    });

    it("does not register the admin-only married names mode", () => {
        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(registered).not.toContain("marriedNamesMode");
    });
});
