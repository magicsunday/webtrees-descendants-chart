/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { jest } from "@jest/globals";

const { default: Chart } = await import("resources/js/modules/chart.js");
const d3 = await import("resources/js/modules/d3.js");

/**
 * Builds a stand-in for a chart that has the pieces the click routing uses.
 *
 * @param {{showDetailPanel?: boolean, panel?: object|null}} options The state of the chart
 */
function buildChart({ showDetailPanel = true, panel = { select: jest.fn() } } = {}) {
    const context = {
        _configuration: { showDetailPanel, openNewTabOnClick: false },
        _detailPanel: panel,
        _selection: {
            select: jest.fn(),
            apply: jest.fn(),
            clear: jest.fn(),
            restoreFocus: jest.fn(),
            makeOperable: jest.fn(),
        },
        redirectToIndividual: jest.fn(),
        update: jest.fn(),
    };

    return { context };
}

const person = (overrides = {}) => ({
    data: {
        xref: "I2",
        url: "/individual/I2",
        updateUrl: "/chart/I2",
        generation: 2,
        ...overrides,
    },
});

describe("Chart click routing", () => {
    test("selects the person for the panel and navigates nowhere", () => {
        const { context } = buildChart();
        const data = person();

        Chart.prototype.personClick.call(context, data);

        expect(context._selection.select).toHaveBeenCalledWith(data);
        expect(context._detailPanel.select).toHaveBeenCalledWith(data.data, false);
        expect(context.update).not.toHaveBeenCalled();
        expect(context.redirectToIndividual).not.toHaveBeenCalled();
    });

    test("lets the panel offer centering on a partner of the chart subject", () => {
        const { context } = buildChart();
        const data = { ...person({ generation: 1 }), spouse: true };

        Chart.prototype.personClick.call(context, data);

        const handed = context._detailPanel.select.mock.calls[0][0];

        expect(handed.generation).not.toBe(1);
        expect(handed.updateUrl).toBe("/chart/I2");
        expect(handed.xref).toBe("I2");
        expect(context._selection.select).toHaveBeenCalledWith(data);
        expect(data.data.generation).toBe(1);
    });

    test("hands a partner of a later generation to the panel unchanged", () => {
        const { context } = buildChart();
        const data = { ...person({ generation: 2 }), spouse: true };

        Chart.prototype.personClick.call(context, data);

        expect(context._detailPanel.select.mock.calls[0][0].generation).toBe(2);
    });

    test("hands the chart subject to the panel as the center of the chart", () => {
        const { context } = buildChart();
        const data = { ...person({ generation: 1 }), spouse: false };

        Chart.prototype.personClick.call(context, data);

        expect(context._detailPanel.select.mock.calls[0][0].generation).toBe(1);
    });

    test("selects the root person instead of opening its page", () => {
        const { context } = buildChart();

        Chart.prototype.personClick.call(context, person({ generation: 1 }));

        expect(context._detailPanel.select).toHaveBeenCalledTimes(1);
        expect(context.redirectToIndividual).not.toHaveBeenCalled();
    });

    test("tells the panel when a key triggered the selection", () => {
        const { context } = buildChart();
        const data = person();

        Chart.prototype.personClick.call(context, data, true);

        expect(context._detailPanel.select).toHaveBeenCalledWith(data.data, true);
    });

    test("behaves as before with the panel switched off", () => {
        const { context } = buildChart({ showDetailPanel: false });

        Chart.prototype.personClick.call(context, person());
        Chart.prototype.personClick.call(context, person({ generation: 1 }));

        expect(context.update).toHaveBeenCalledWith("/chart/I2");
        expect(context.redirectToIndividual).toHaveBeenCalledWith("/individual/I2");
        expect(context._selection.select).not.toHaveBeenCalled();
    });

    test("behaves as before when the page has no panel element", () => {
        const { context } = buildChart({ panel: null });

        Chart.prototype.personClick.call(context, person());

        expect(context.update).toHaveBeenCalledWith("/chart/I2");
        expect(context._selection.select).not.toHaveBeenCalled();
    });
});

describe("Chart click binding with real elements", () => {
    /**
     * Builds an SVG with person groups that carry d3 data, and binds the chart to it.
     *
     * @param {object[]} nodes           The d3 data of the person groups
     * @param {boolean}  showDetailPanel Whether the panel is on
     */
    function bindReal(nodes, showDetailPanel = false, lines = []) {
        document.body.innerHTML = '<svg><g class="visual"></g></svg>';

        const visual = d3.select(document.querySelector("g.visual"));
        const personNodes = visual
            .selectAll("g.person")
            .data(nodes)
            .enter()
            .append("g")
            .attr("class", "person");

        // Like the chart draws a box: one text per line with a tspan and a title of its own
        personNodes.each(function () {
            lines.forEach((line) => {
                const text = d3.select(this).append("text");

                // A line is one tspan, or several like the horizontal layouts draw the name parts
                [line].flat().forEach((part) => text.append("tspan").text(part));
                text.append("title").text([line].flat().join(" "));
            });
        });

        // The chart library labels the element with the bare name
        const context = {
            _configuration: { showDetailPanel },
            _svg: { visual },
            _selection: {
                makeOperable: jest.fn((target) => target.attr("aria-label", "Jane Doe")),
                apply: jest.fn(),
            },
            personClick: jest.fn(),
        };

        Chart.prototype.bindClickEventListener.call(context);

        return { context, elements: personNodes.nodes() };
    }

    // The d3 datum is the hierarchy node, its data is the datum of the chart, and that holds the person
    const node = (xref, url) => ({ data: { data: { xref, url } } });

    test("hands the data of a clicked person to the click routing", () => {
        const first = node("I1", "/i/1");
        const { context, elements } = bindReal([first]);

        elements[0].dispatchEvent(new MouseEvent("click"));

        expect(context.personClick).toHaveBeenCalledTimes(1);
        expect(context.personClick).toHaveBeenCalledWith(first.data);
    });

    test("hands the data of a clicked person to the click routing with the panel on", () => {
        const first = node("I1", "/i/1");
        const { context, elements } = bindReal([first], true);

        elements[0].dispatchEvent(new MouseEvent("click"));

        expect(context.personClick).toHaveBeenCalledTimes(1);
        expect(context.personClick).toHaveBeenCalledWith(first.data);
    });

    test("labels an operable person with the text that the box shows", () => {
        // The bare name would not contain the dates that the box shows (WCAG 2.5.3)
        const { elements } = bindReal([node("I1", "/i/1")], true, ["Jane", "Doe", "Born: 1850"]);

        expect(elements[0].getAttribute("aria-label")).toBe("Jane Doe Born: 1850");
    });

    test("separates the tspans of one line in the label", () => {
        // The horizontal layouts draw the parts of a name as tspans of one text
        const { elements } = bindReal([node("I1", "/i/1")], true, [["Jane", "Doe"], "Born: 1850"]);

        expect(elements[0].getAttribute("aria-label")).toBe("Jane Doe Born: 1850");
    });

    test("keeps the label of the chart library for a box without visible text", () => {
        const { elements } = bindReal([node("I1", "/i/1")], true);

        expect(elements[0].getAttribute("aria-label")).toBe("Jane Doe");
    });

    test("labels an operable person with the text that the box shows with the panel off too", () => {
        const { elements } = bindReal([node("I1", "/i/1")], false, ["Jane", "Doe", "Born: 1850"]);

        expect(elements[0].getAttribute("aria-label")).toBe("Jane Doe Born: 1850");
    });

    test("binds no click to a box that has neither an xref nor a url", () => {
        const { context, elements } = bindReal([node("", "")]);

        elements[0].dispatchEvent(new MouseEvent("click"));

        expect(context.personClick).not.toHaveBeenCalled();
    });
});

describe("A real chart with the detail panel", () => {
    // The d3 datum is the hierarchy node, its data is the datum of the chart, and that holds the person
    const node = (xref) => ({
        data: {
            data: {
                xref,
                url: `/individual/${xref}`,
                updateUrl: `/chart/${xref}`,
                generation: 2,
            },
        },
    });

    /**
     * Builds a real chart whose persons are real SVG groups that carry d3 data.
     *
     * @param {object[]} nodes           The d3 data of the person groups
     * @param {boolean}  showDetailPanel Whether the panel is on
     */
    function buildRealChart(nodes, showDetailPanel = true) {
        document.body.innerHTML = '<div id="parent"></div><svg><g class="visual"></g></svg>';

        const visual = d3.select(document.querySelector("g.visual"));
        const chart = new Chart(d3.select("#parent"), {
            showDetailPanel,
            openNewTabOnClick: false,
        });

        chart._svg = { visual };

        return { chart, elements: drawPersons(visual, nodes) };
    }

    /**
     * Draws person groups that carry d3 data, like the chart does on every draw.
     *
     * @param {object}   visual The d3 selection that holds the persons
     * @param {object[]} nodes  The d3 data of the person groups
     */
    function drawPersons(visual, nodes) {
        return visual
            .selectAll("g.person")
            .data(nodes)
            .enter()
            .append("g")
            .attr("class", "person")
            .nodes();
    }

    test("hands the clicked person to the panel that was set on the chart", () => {
        const first = node("I1");
        const { chart } = buildRealChart([first]);
        const panel = { select: jest.fn() };

        chart.detailPanel = panel;
        chart.personClick(first.data);

        expect(panel.select).toHaveBeenCalledTimes(1);
        expect(panel.select.mock.calls[0][0].xref).toBe("I1");
    });

    test("marks the selected person in the drawn chart and no other one", () => {
        const first = node("I1");
        const second = node("I2");
        const { chart, elements } = buildRealChart([first, second]);

        chart.detailPanel = { select: jest.fn() };
        chart.personClick(second.data);

        expect(elements[0].classList.contains("selected")).toBe(false);
        expect(elements[1].classList.contains("selected")).toBe(true);
        expect(elements[1].getAttribute("aria-current")).toBe("true");
    });

    // Both values of the panel option, because the persons are keyboard operable with the panel on
    // and off, and the activation reaches the click routing with the flag that a key was used
    test.each([[true], [false]])(
        "activates a person by Enter on its drawn element and reaches it with the Tab key, panel %s",
        (showDetailPanel) => {
            const first = node("I1");
            const { chart, elements } = buildRealChart([first], showDetailPanel);
            const panel = { select: jest.fn() };
            const personClick = jest.spyOn(chart, "personClick");

            chart.detailPanel = panel;
            chart.update = jest.fn();
            chart.bindClickEventListener();

            expect(elements[0].getAttribute("tabindex")).toBe("0");

            elements[0].dispatchEvent(new KeyboardEvent("keydown", { key: "Enter" }));

            expect(personClick).toHaveBeenCalledTimes(1);
            expect(personClick).toHaveBeenCalledWith(first.data, true);

            if (showDetailPanel) {
                expect(panel.select).toHaveBeenCalledWith(first.data.data, true);
                expect(chart.update).not.toHaveBeenCalled();
            } else {
                expect(chart.update).toHaveBeenCalledWith("/chart/I1");
                expect(panel.select).not.toHaveBeenCalled();
            }
        },
    );

    test("marks the selected person again on the elements of the next draw", () => {
        const { chart, elements } = buildRealChart([node("I1"), node("I2")]);

        chart.detailPanel = { select: jest.fn() };
        chart.bindClickEventListener();
        elements[1].dispatchEvent(new MouseEvent("click", { bubbles: true }));

        expect(elements[1].classList.contains("selected")).toBe(true);

        // A draw creates the person elements anew, so the old marks are gone
        chart._svg.visual.selectAll("g.person").remove();

        const redrawn = drawPersons(chart._svg.visual, [node("I1"), node("I2")]);

        chart.bindClickEventListener();

        expect(redrawn[0].classList.contains("selected")).toBe(false);
        expect(redrawn[1].classList.contains("selected")).toBe(true);
    });
});
