/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { jest } from "@jest/globals";

const registered = [];
const stored = {};

const buildChartAjaxUrl = jest.fn(() => "https://example.test/ajax");
const setChartOptionsGlobal = jest.fn();

await jest.unstable_mockModule("@magicsunday/webtrees-chart-lib/chart-core", () => ({
    buildChartAjaxUrl,
    setChartAjaxUrl: jest.fn(),
    setChartOptionsGlobal,
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
        readBool(name) {
            return name in stored ? stored[name] : null;
        }
    },
}));

const { initPage } = await import("resources/js/modules/page-init.js");

describe("initPage", () => {
    beforeEach(() => {
        registered.length = 0;
        buildChartAjaxUrl.mockClear();
        setChartOptionsGlobal.mockClear();

        for (const name of Object.keys(stored)) {
            delete stored[name];
        }

        document.body.innerHTML = '<form id="webtrees-descendants-chart-form"></form>';
    });

    it("registers exactly the options that have a field on the chart page form", () => {
        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(registered.sort()).toEqual([
            "descendantLine",
            "generations",
            "hideSpouses",
            "layout",
            "openNewTabOnClick",
            "showAlternativeName",
            "showDetailPanel",
            "showNicknames",
        ]);
    });

    it("does not register the admin-only married names mode", () => {
        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(registered).not.toContain("marriedNamesMode");
    });

    it("sends the stored state of the detail panel switch to the server with the chart request", () => {
        stored.showDetailPanel = true;

        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(buildChartAjaxUrl.mock.calls[0][1].query).toContainEqual({
            key: "showDetailPanel",
            value: true,
        });
    });

    it("sends a stored off state of the switch to the server as well", () => {
        stored.showDetailPanel = false;

        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(buildChartAjaxUrl.mock.calls[0][1].query).toContainEqual({
            key: "showDetailPanel",
            value: false,
        });
    });

    it("does not publish the switch, because the server reads the facts and its value decides", () => {
        stored.showDetailPanel = true;

        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(setChartOptionsGlobal.mock.calls[0][1]).not.toHaveProperty("showDetailPanel");
    });
});
