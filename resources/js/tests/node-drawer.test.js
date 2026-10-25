/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { jest } from "@jest/globals";

const d3 = await import("resources/js/modules/d3.js");
const { default: NodeDrawer } = await import("resources/js/modules/tree/node-drawer.js");

/**
 * jsdom does not implement the SVG transform list that d3 reads when it interpolates a
 * transform attribute. This stand-in understands the plain translate(x,y) values the drawer
 * writes, which is all the transitions under test need.
 */
Object.defineProperty(SVGElement.prototype, "transform", {
    configurable: true,
    get() {
        const match = /translate\(([^,)]+),([^)]+)\)/.exec(this.getAttribute("transform") ?? "");

        return {
            baseVal: {
                consolidate: () =>
                    match
                        ? {
                              matrix: {
                                  a: 1,
                                  b: 0,
                                  c: 0,
                                  d: 1,
                                  e: Number(match[1]),
                                  f: Number(match[2]),
                              },
                          }
                        : null,
            },
        };
    },
});

const BOX_WIDTH = 100;
const BOX_HEIGHT = 40;

/**
 * Lets the d3 transitions of the drawer run to their end. The configured duration is zero,
 * so a short real wait is enough for the timer to tick and finish them.
 *
 * @return {Promise<void>}
 */
const settle = () => new Promise((resolve) => setTimeout(resolve, 50));

/**
 * One pre-positioned box descriptor as the connection builder emits it.
 *
 * @param {string} id
 * @param {number} x
 * @param {number} y
 *
 * @return {object}
 */
const box = (id, x, y) => ({
    id,
    x,
    y,
    data: { data: { sex: "F", name: `Person ${id}` }, spouse: false },
});

/**
 * Creates a drawer on a fresh SVG with real d3 selections for the defs and the visual layer.
 * Drawing the content of a single node is replaced by a stub, so only the join of the drawer
 * is under test.
 *
 * @return {{drawer: NodeDrawer, visual: object}}
 */
function createDrawer() {
    document.body.replaceChildren();

    const svg = d3.select(document.body).append("svg");
    const fakeSvg = {
        defs: svg.append("defs"),
        visual: svg.append("g"),
    };
    const configuration = {
        duration: 0,
        orientation: {
            boxWidth: BOX_WIDTH,
            boxHeight: BOX_HEIGHT,
            isVertical: true,
        },
    };

    const drawer = new NodeDrawer(fakeSvg, configuration);
    drawer.drawNode = jest.fn();

    return { drawer, visual: fakeSvg.visual };
}

/**
 * Describes the person groups of the visual layer by id-free attributes, in document order.
 *
 * @param {object} visual
 *
 * @return {object[]}
 */
const describeGroups = (visual) =>
    visual
        .selectAll("g.person")
        .nodes()
        .map((node) => ({
            transform: node.getAttribute("transform").replaceAll(" ", ""),
            opacity: node.getAttribute("opacity"),
            rects: node.querySelectorAll("rect").length,
        }));

describe("NodeDrawer.drawNodes", () => {
    it("draws one positioned group with a rect per box on the first draw", async () => {
        const { drawer, visual } = createDrawer();

        drawer.drawNodes([box("a", 10, 20), box("b", 30, 40)]);
        await settle();

        expect(describeGroups(visual)).toEqual([
            { transform: "translate(10,20)", opacity: "1", rects: 1 },
            { transform: "translate(30,40)", opacity: "1", rects: 1 },
        ]);
        expect(drawer.drawNode).toHaveBeenCalledTimes(1);
    });

    it("moves a kept box and removes a box that is no longer drawn", async () => {
        const { drawer, visual } = createDrawer();

        drawer.drawNodes([box("a", 10, 20), box("b", 30, 40)]);
        await settle();

        drawer.drawNodes([box("b", 70, 80)]);
        await settle();

        expect(describeGroups(visual)).toEqual([
            { transform: "translate(70,80)", opacity: "1", rects: 1 },
        ]);
        expect(
            visual
                .selectAll("title")
                .nodes()
                .map((node) => node.textContent),
        ).toEqual(["Person b"]);
    });

    it("keeps a leaving box at its old position while it fades out", async () => {
        const { drawer, visual } = createDrawer();

        drawer.drawNodes([box("a", 10, 20), box("b", 30, 40)]);
        await settle();

        const leaving = visual.selectAll("g.person").nodes()[0];

        drawer.drawNodes([box("b", 30, 40)]);

        // The transition has been scheduled but has not run yet.
        expect(leaving.isConnected).toBe(true);

        await settle();

        expect(leaving.getAttribute("transform")).toBe("translate(10,20)");
        expect(leaving.getAttribute("opacity")).toBe("0");
        expect(leaving.isConnected).toBe(false);
    });
});
