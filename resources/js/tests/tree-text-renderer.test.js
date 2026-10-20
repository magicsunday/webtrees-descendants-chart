/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { jest } from "@jest/globals";

const measureTextMock = jest.fn(() => 42);

await jest.unstable_mockModule("@magicsunday/webtrees-chart-lib/infra", () => ({
    measureText: measureTextMock,
    truncateNames: jest.fn(),
}));

const { default: TextRenderer } = await import("resources/js/modules/tree/text-renderer.js");
const { default: Name } = await import("resources/js/modules/tree/name.js");
const { default: DateRenderer } = await import("resources/js/modules/tree/date.js");

const buildRenderer = (RendererClass, isDocumentRtl = false) =>
    new RendererClass(
        { style: jest.fn(() => "Arial") },
        { isDocumentRtl },
        { width: 80 },
        { x: -30 },
    );

describe("TextRenderer.textX", () => {
    it("starts at the text box position when no image takes up room", () => {
        expect(buildRenderer(TextRenderer).textX({ withImage: false })).toBe(-30);
    });

    it("shifts by the image width when the element reserves room for an image", () => {
        expect(buildRenderer(TextRenderer).textX({ withImage: true })).toBe(50);
    });

    it("mirrors the position for right-to-left documents", () => {
        expect(buildRenderer(TextRenderer, true).textX({ withImage: true })).toBe(-50);
    });
});

describe("TextRenderer.measureText", () => {
    it("measures with the font family of the svg element", () => {
        measureTextMock.mockClear();

        const width = buildRenderer(TextRenderer).measureText("Jane", "12px", 700);

        expect(width).toBe(42);
        expect(measureTextMock).toHaveBeenCalledWith("Jane", "Arial", "12px", 700);
    });

    it("defaults to the regular font weight", () => {
        measureTextMock.mockClear();

        buildRenderer(TextRenderer).measureText("Jane", "12px");

        expect(measureTextMock).toHaveBeenCalledWith("Jane", "Arial", "12px", 400);
    });
});

describe("renderers sharing the text helpers", () => {
    it.each([
        ["Name", Name],
        ["DateRenderer", DateRenderer],
    ])("%s inherits the helpers instead of redefining them", (_label, RendererClass) => {
        expect(Object.getPrototypeOf(RendererClass)).toBe(TextRenderer);
        expect(Object.hasOwn(RendererClass.prototype, "textX")).toBe(false);
        expect(Object.hasOwn(RendererClass.prototype, "measureText")).toBe(false);
        expect(buildRenderer(RendererClass).textX({ withImage: true })).toBe(50);
    });
});
