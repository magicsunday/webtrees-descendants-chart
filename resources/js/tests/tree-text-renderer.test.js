/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { TextRenderer } from "@magicsunday/webtrees-chart-lib/linked-tree";
import Name from "resources/js/modules/tree/name.js";

describe("Name", () => {
    it("inherits the text helpers of the shared renderer instead of redefining them", () => {
        expect(Object.getPrototypeOf(Name)).toBe(TextRenderer);
        expect(Object.hasOwn(Name.prototype, "textX")).toBe(false);
        expect(Object.hasOwn(Name.prototype, "measureText")).toBe(false);
    });
});
