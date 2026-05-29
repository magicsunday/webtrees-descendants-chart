/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import {
    buildChartAjaxUrl,
    setChartAjaxUrl,
    setChartOptionsGlobal,
    Storage,
    syncCollapseToggle,
} from "@magicsunday/webtrees-chart-lib/chart-core";

/**
 * Initialises the descendants chart page: restores form values from
 * localStorage, sets up event listeners, builds the initial AJAX URL, and
 * publishes the resolved chart options under the WebtreesDescendantsChart UMD
 * global so chart.phtml getters can read user overrides.
 *
 * @param {object} config
 * @param {string} config.ajaxUrl The base AJAX endpoint URL
 */
export function initPage(config) {
    const storage = new Storage("webtrees-descendants-chart");

    storage.register("generations");
    storage.register("layout");
    storage.register("hideSpouses");
    storage.register("marriedNamesMode");
    storage.register("openNewTabOnClick");
    storage.register("showAlternativeName");
    storage.register("showNicknames");

    syncCollapseToggle(storage);

    const form = /** @type {HTMLFormElement} */ (
        document.getElementById("webtrees-descendants-chart-form")
    );
    const layoutInput = /** @type {HTMLInputElement|null} */ (form.elements.namedItem("layout"));
    if (layoutInput) {
        layoutInput.value = storage.readString("layout", "");
    }

    /**
     * Resolved user options. `null` here means "user has not overridden the
     * server default"; chart.phtml falls back to the PHP-side value via `??`.
     *
     * Only options that the chart re-evaluates client-side (no form submit)
     * belong here. hideSpouses / marriedNamesMode / showNicknames are
     * server-rendered via DataFacade so they are persisted in localStorage via
     * storage.register() above (to keep the form state across reloads) but do
     * NOT need to ship in chartOptions.
     *
     * @type {{
     *   generations: number|null,
     *   treeLayout: string|null,
     *   openNewTabOnClick: boolean|null,
     *   showAlternativeName: boolean|null,
     * }}
     */
    const chartOptions = {
        generations: storage.readNumber("generations"),
        treeLayout: storage.readString("layout"),
        openNewTabOnClick: storage.readBool("openNewTabOnClick"),
        showAlternativeName: storage.readBool("showAlternativeName"),
    };

    // WebtreesDescendantsChart is the UMD global exposed by the chart-page
    // bundle; chart.phtml reads chartOptions from it.
    setChartOptionsGlobal("WebtreesDescendantsChart", chartOptions);

    const ajaxUrl = buildChartAjaxUrl(config.ajaxUrl, {
        query: [{ key: "generations", value: storage.readString("generations") }],
    });
    setChartAjaxUrl("descendants-chart-url", ajaxUrl);
}
