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
import { initHelpPopover, syncHelpMode } from "@magicsunday/webtrees-chart-lib/infra";

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
    storage.register("descendantLine");
    storage.register("openNewTabOnClick");
    storage.register("showAlternativeName");
    storage.register("showNicknames");
    storage.register("showDetailPanel");

    syncCollapseToggle(storage);
    initHelpPopover();

    const form = /** @type {HTMLFormElement} */ (
        document.getElementById("webtrees-descendants-chart-form")
    );
    const layoutInput = /** @type {HTMLInputElement|null} */ (form.elements.namedItem("layout"));
    if (layoutInput) {
        layoutInput.value = storage.readString("layout", "") ?? "";
    }

    /**
     * Resolved user options. `null` here means "user has not overridden the
     * server default"; chart.phtml falls back to the PHP-side value via `??`.
     *
     * Only options that the chart re-evaluates client-side (no form submit)
     * belong here. hideSpouses / descendantLine / showNicknames are
     * server-rendered via DataFacade so they are persisted in localStorage via
     * storage.register() above (to keep the form state across reloads) but do
     * NOT need to ship in chartOptions. Only options with a field on the chart
     * page form are registered, so an admin-only preference is never registered.
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

    // The server reads the facts of the panel only while its switch is on, so the stored state of
    // the checkbox, which the form shows, has to reach the server with the request as well.
    const ajaxUrl = buildChartAjaxUrl(config.ajaxUrl, {
        query: [
            { key: "generations", value: storage.readString("generations") },
            { key: "showDetailPanel", value: storage.readBool("showDetailPanel") },
        ],
    });
    setChartAjaxUrl("descendants-chart-url", ajaxUrl);

    // The chart takes its mode from the stored state that the request above carries, so the help
    // shows the entries of that mode.
    syncHelpMode("default");
}
