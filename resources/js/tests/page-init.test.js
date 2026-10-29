/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { jest } from "@jest/globals";

const registered = [];
const stored = {};
const restoredChecked = {};

const buildChartAjaxUrl = jest.fn(() => "https://example.test/ajax");
const setChartOptionsGlobal = jest.fn();

await jest.unstable_mockModule("@magicsunday/webtrees-chart-lib/chart-core", () => ({
    buildChartAjaxUrl,
    setChartAjaxUrl: jest.fn(),
    setChartOptionsGlobal,
    syncCollapseToggle: jest.fn(),
    Storage: class {
        /**
         * Records the name of the registered option and puts a stored state back into the checkbox
         * of the form, as the real storage does.
         *
         * @param {string} name The option name
         */
        register(name) {
            registered.push(name);

            if (name in restoredChecked) {
                const input = document.querySelector(`input[type="checkbox"][name="${name}"]`);

                if (input) {
                    input.checked = restoredChecked[name];
                }
            }
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

        for (const name of Object.keys(restoredChecked)) {
            delete restoredChecked[name];
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

    it("leaves the switch to the server default while nothing is stored", () => {
        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(buildChartAjaxUrl.mock.calls[0][1].query).toContainEqual({
            key: "showDetailPanel",
            value: null,
        });
    });

    it("does not publish the switch, because the server reads the facts and its value decides", () => {
        stored.showDetailPanel = true;

        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(setChartOptionsGlobal.mock.calls[0][1]).not.toHaveProperty("showDetailPanel");
    });

    /**
     * Renders the form together with the help button, the help popover and the panel checkbox.
     *
     * @param {boolean} panel Whether the checkbox of the detail panel is ticked
     */
    function renderHelp(panel) {
        document.body.innerHTML =
            '<form id="webtrees-descendants-chart-form">' +
            `<input type="checkbox" name="showDetailPanel" value="1"${panel ? " checked" : ""}>` +
            "</form>" +
            '<button class="btn-help" aria-expanded="false" aria-controls="descendants-chart-help-popover">Help</button>' +
            '<div id="descendants-chart-help-popover" class="help-popover d-none" role="region">' +
            '<div data-help-mode="default" id="help-default"></div>' +
            '<div data-help-mode="panel" id="help-panel" class="d-none"></div>' +
            "</div>";
    }

    it("wires the help button to its popover", () => {
        renderHelp(false);

        initPage({ ajaxUrl: "https://example.test/chart" });
        document.querySelector(".btn-help").click();

        expect(
            document.getElementById("descendants-chart-help-popover").classList.contains("d-none"),
        ).toBe(false);
        expect(document.querySelector(".btn-help").getAttribute("aria-expanded")).toBe("true");
    });

    it("shows the help entries of the detail panel mode that the chart loads with", () => {
        renderHelp(true);

        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(document.getElementById("help-panel").classList.contains("d-none")).toBe(false);
        expect(document.getElementById("help-default").classList.contains("d-none")).toBe(true);
    });

    it("shows the help entries of the mode that the stored state restores into the checkbox", () => {
        // The markup renders the checkbox unticked and the storage ticks it, so the help has to follow
        // the restored state and not the state of the markup.
        renderHelp(false);
        restoredChecked.showDetailPanel = true;

        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(document.getElementById("help-panel").classList.contains("d-none")).toBe(false);
        expect(document.getElementById("help-default").classList.contains("d-none")).toBe(true);
    });

    it("shows the default help entries while the detail panel is off", () => {
        renderHelp(false);

        initPage({ ajaxUrl: "https://example.test/chart" });

        expect(document.getElementById("help-default").classList.contains("d-none")).toBe(false);
        expect(document.getElementById("help-panel").classList.contains("d-none")).toBe(true);
    });
});
