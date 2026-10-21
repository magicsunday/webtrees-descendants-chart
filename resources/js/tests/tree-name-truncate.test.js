/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

// The shared truncation is deliberately not mocked here, because the nickname flag of the
// label entries only has an effect inside it.
const { default: Name } = await import("resources/js/modules/tree/name.js");

/**
 * Builds a name renderer whose text measuring costs ten pixels per character.
 */
function makeName() {
    const name = Object.create(Name.prototype);
    name._svg = { _configuration: { nameAbbreviation: "GIVEN" } };
    name.measureText = (text) => text.length * 10;

    return name;
}

const parent = { style: () => "12px" };

const segments = [
    { label: "Anna", kind: "given", isPreferred: false },
    { label: '"Chalky"', kind: "nickname", isPreferred: false },
    { label: "Schmidt", kind: "surname", isPreferred: false },
];

describe("Name nickname labels", () => {
    it("marks only the nickname segment as a nickname", () => {
        const labels = makeName()
            .createNamesData({ data: { data: { isNameRtl: false, nameSegments: segments } } })
            .flat();

        expect(labels.map((label) => label.isNickname)).toEqual([false, true, false]);
    });

    it("lets the truncation drop the nickname before it abbreviates anything else", () => {
        const name = makeName();
        const labels = name
            .createNamesData({ data: { data: { isNameRtl: false, nameSegments: segments } } })
            .flat();

        const result = name.truncateNamesData(parent, labels, 150);

        expect(result.map((label) => label.label)).toEqual(["Anna", "Schmidt"]);
    });
});
