/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file distributed with this source code.
 */

import { jest } from "@jest/globals";

// The shared truncation is replaced by an identity function that records its call, so these
// tests pin what the name renderer draws and passes on, not what the abbreviation algorithm does.
const truncateNamesMock = jest.fn((names) => names);

await jest.unstable_mockModule("@magicsunday/webtrees-chart-lib/infra", () => ({
    measureText: jest.fn(() => 0),
    truncateNames: truncateNamesMock,
}));

const d3 = await import("resources/js/modules/d3.js");
const { default: Name } = await import("resources/js/modules/tree/name.js");

const TEXT_BOX = { x: -30, y: 10, width: 100 };
const IMAGE_BOX = { width: 60 };

/**
 * The name segments of the wire format, in display order.
 */
const given = (label) => ({ label, kind: "given", isPreferred: false });
const preferred = (label) => ({ label, kind: "given", isPreferred: true });
const nickname = (label) => ({ label: `"${label}"`, kind: "nickname", isPreferred: false });
const surname = (label) => ({ label, kind: "surname", isPreferred: false });
const married = (label) => ({ label, kind: "married", isPreferred: false });

/**
 * The wire data of one person, with every key present like the PHP node data emits it.
 *
 * @param {object} overrides
 *
 * @return {object}
 */
function person(overrides) {
    return {
        isNameRtl: false,
        nameSegments: [],
        alternativeName: "",
        isAltRtl: false,
        thumbnail: "",
        ...overrides,
    };
}

/**
 * Draws the name renderer for one person into a fresh SVG and describes every text element
 * it appended. A tspan is described by its text, followed by "[u]" when it is underlined and
 * by "[last]" when it carries the last name class.
 *
 * @param {object}  data
 * @param {object}  [options]
 * @param {boolean} [options.vertical]
 * @param {boolean} [options.documentRtl]
 * @param {boolean} [options.showAlternativeName]
 *
 * @return {object[]}
 */
function render(data, { vertical = true, documentRtl = false, showAlternativeName = false } = {}) {
    document.body.replaceChildren();

    const parent = d3
        .select(document.body)
        .append("svg")
        .selectAll("g")
        .data([{ data: { data } }])
        .enter()
        .append("g");

    new Name(
        { _configuration: { showAlternativeName, nameAbbreviation: "GIVEN" } },
        { isVertical: vertical, isDocumentRtl: documentRtl },
        IMAGE_BOX,
        TEXT_BOX,
    ).appendName(parent);

    return [...document.querySelectorAll("svg text")].map((text) => ({
        class: text.getAttribute("class"),
        y: text.getAttribute("y"),
        x: text.getAttribute("x"),
        direction: text.getAttribute("direction"),
        baseline: text.getAttribute("dominant-baseline"),
        anchor: text.getAttribute("text-anchor"),
        tspans: [...text.querySelectorAll("tspan")].map(
            (tspan) =>
                tspan.textContent +
                (tspan.getAttribute("text-decoration") === "underline" ? "[u]" : "") +
                (tspan.classList.contains("lastName") ? "[last]" : ""),
        ),
        dx: [...text.querySelectorAll("tspan")].map((tspan) => tspan.getAttribute("dx")),
    }));
}

/**
 * Returns the tspan descriptions of every text element, line by line.
 *
 * @param {object[]} texts
 *
 * @return {string[][]}
 */
const lines = (texts) => texts.map((text) => text.tspans);

const ANNA_MARIA = person({
    nameSegments: [given("Anna"), preferred("Maria"), surname("Schmidt")],
});

const PARTICLE = person({
    nameSegments: [preferred("Hans"), given("von"), surname("Berg")],
});

const NICKNAME = person({
    nameSegments: [given("Martin"), nickname("Chalky"), surname("White")],
});

const MARRIED = person({
    nameSegments: [preferred("Anna"), surname("Schmidt"), married("(Müller)")],
});

const REPEATED = person({
    nameSegments: [preferred("Anna"), preferred("Anna"), preferred("Anna"), surname("Schmidt")],
});

const SAME_FIRST_AND_LAST = person({
    nameSegments: [preferred("Maria"), surname("Maria")],
});

describe("Name.appendName in a vertical layout", () => {
    beforeEach(() => {
        truncateNamesMock.mockClear();
    });

    it("draws the given names and the surname as two lines", () => {
        const texts = render(ANNA_MARIA);

        expect(lines(texts)).toEqual([["Anna", "Maria[u]"], ["Schmidt[last]"]]);
        expect(texts.map((text) => text.y)).toEqual(["5", "25"]);
        expect(texts.map((text) => text.class)).toEqual(["wt-chart-box-name", "wt-chart-box-name"]);
        expect(texts.map((text) => text.anchor)).toEqual(["middle", "middle"]);
        expect(texts.map((text) => text.baseline)).toEqual(["central", "central"]);
        expect(texts.map((text) => text.direction)).toEqual(["ltr", "ltr"]);
    });

    it("keeps a particle with the given names", () => {
        expect(lines(render(PARTICLE))).toEqual([["Hans[u]", "von"], ["Berg[last]"]]);
    });

    it("draws the quoted nickname after the given names", () => {
        expect(lines(render(NICKNAME))).toEqual([["Martin", '"Chalky"'], ["White[last]"]]);
    });

    it("draws the married surname next to the birth surname", () => {
        expect(lines(render(MARRIED))).toEqual([["Anna[u]"], ["Schmidt[last]", "(Müller)[last]"]]);
    });

    it("draws a repeated given name once per occurrence", () => {
        expect(lines(render(REPEATED))).toEqual([
            ["Anna[u]", "Anna[u]", "Anna[u]"],
            ["Schmidt[last]"],
        ]);
    });

    it("tells a surname apart from an equal given name", () => {
        expect(lines(render(SAME_FIRST_AND_LAST))).toEqual([["Maria[u]"], ["Maria[last]"]]);
    });

    it("draws only the surname line first when there is no given name", () => {
        const texts = render(person({ nameSegments: [surname("Schmidt")] }));

        expect(lines(texts).filter((line) => line.length > 0)).toEqual([["Schmidt[last]"]]);
        expect(lines(texts)[0]).toEqual(["Schmidt[last]"]);
    });

    it("draws only the given names when there is no surname", () => {
        const texts = render(person({ nameSegments: [given("Anna")] }));

        expect(lines(texts).filter((line) => line.length > 0)).toEqual([["Anna"]]);
        expect(lines(texts)[0]).toEqual(["Anna"]);
    });

    it("draws no name for a placeholder without any name part", () => {
        const texts = render(person({}));

        expect(lines(texts).flat()).toEqual([]);
    });

    it("spaces the elements of a line by a quarter em", () => {
        const texts = render(ANNA_MARIA);

        expect(texts[0]?.dx).toEqual([null, "0.25em"]);
        expect(texts[1]?.dx).toEqual([null]);
    });

    it("mirrors the spacing and the direction for a right-to-left name", () => {
        const texts = render(
            person({
                isNameRtl: true,
                nameSegments: [given("דוד"), given("אברהם"), surname("כהן")],
            }),
        );

        expect(lines(texts)).toEqual([["דוד", "אברהם"], ["כהן[last]"]]);
        expect(texts.map((text) => text.direction)).toEqual(["rtl", "rtl"]);
        expect(texts[0]?.dx).toEqual([null, "-0.25em"]);
    });

    it("passes the full text width on for the truncation of every line", () => {
        render(ANNA_MARIA);

        expect(truncateNamesMock.mock.calls.map((call) => call[1])).toEqual([100, 100]);
    });

    it("draws the alternative name below the name when enabled", () => {
        const texts = render(person({ ...ANNA_MARIA, alternativeName: "Anna Maria Meier" }), {
            showAlternativeName: true,
        });

        expect(lines(texts)).toEqual([
            ["Anna", "Maria[u]"],
            ["Schmidt[last]"],
            ["Anna", "Maria", "Meier"],
        ]);
        expect(texts[2]?.y).toBe("50");
        expect(texts[2]?.class).toBe("wt-chart-box-name");
        expect(texts[2]?.anchor).toBe("middle");
        expect(texts[2]?.baseline).toBe("central");
        expect(texts[2]?.dx).toEqual([null, "0.25em", "0.25em"]);
    });

    it("draws no alternative name when it is empty or disabled", () => {
        expect(lines(render(ANNA_MARIA, { showAlternativeName: true }))).toHaveLength(2);
        expect(
            lines(
                render(person({ ...ANNA_MARIA, alternativeName: "Anna Meier" }), {
                    showAlternativeName: false,
                }),
            ),
        ).toHaveLength(2);
    });

    it("direction of the alternative name follows its own flag", () => {
        const texts = render(
            person({
                ...ANNA_MARIA,
                alternativeName: "דוד כהן",
                isAltRtl: true,
            }),
            { showAlternativeName: true },
        );

        expect(texts[2]?.direction).toBe("rtl");
        expect(texts[2]?.dx).toEqual([null, "-0.25em"]);
        expect(texts[0]?.direction).toBe("ltr");
    });
});

describe("Name.appendName in a horizontal layout", () => {
    beforeEach(() => {
        truncateNamesMock.mockClear();
    });

    it("draws the whole name in one line", () => {
        const texts = render(ANNA_MARIA, { vertical: false });

        expect(lines(texts)).toEqual([["Anna", "Maria[u]", "Schmidt[last]"]]);
        expect(texts[0]?.y).toBe("0");
        expect(texts[0]?.class).toBe("wt-chart-box-name");
        expect(texts[0]?.baseline).toBeNull();
        expect(texts[0]?.dx).toEqual([null, "0.25em", "0.25em"]);
    });

    it("keeps the order of particle, nickname and married surname", () => {
        expect(lines(render(PARTICLE, { vertical: false }))).toEqual([
            ["Hans[u]", "von", "Berg[last]"],
        ]);
        expect(lines(render(NICKNAME, { vertical: false }))).toEqual([
            ["Martin", '"Chalky"', "White[last]"],
        ]);
        expect(lines(render(MARRIED, { vertical: false }))).toEqual([
            ["Anna[u]", "Schmidt[last]", "(Müller)[last]"],
        ]);
    });

    it("draws repeated and equal given and surnames in order", () => {
        expect(lines(render(REPEATED, { vertical: false }))).toEqual([
            ["Anna[u]", "Anna[u]", "Anna[u]", "Schmidt[last]"],
        ]);
        expect(lines(render(SAME_FIRST_AND_LAST, { vertical: false }))).toEqual([
            ["Maria[u]", "Maria[last]"],
        ]);
    });

    it("draws a name with a single kind of part", () => {
        expect(
            lines(
                render(person({ nameSegments: [surname("Schmidt")] }), {
                    vertical: false,
                }),
            ),
        ).toEqual([["Schmidt[last]"]]);
        expect(
            lines(render(person({ nameSegments: [given("Anna")] }), { vertical: false })),
        ).toEqual([["Anna"]]);
        expect(lines(render(person({}), { vertical: false }))).toEqual([[]]);
    });

    it("starts at the text box and reserves the image width when a highlight image is shown", () => {
        const withoutImage = render(ANNA_MARIA, { vertical: false });
        const withImage = render(person({ ...ANNA_MARIA, thumbnail: "image.jpg" }), {
            vertical: false,
        });

        expect(withoutImage[0]?.x).toBe("-30");
        expect(withImage[0]?.x).toBe("30");
        expect(withoutImage[0]?.anchor).toBe("start");
    });

    it("reduces the available width by the image width", () => {
        truncateNamesMock.mockClear();
        render(ANNA_MARIA, { vertical: false });
        render(person({ ...ANNA_MARIA, thumbnail: "image.jpg" }), { vertical: false });

        expect(truncateNamesMock.mock.calls.map((call) => call[1])).toEqual([100, 40]);
    });

    it("anchors a right-to-left name at the end and mirrors a right-to-left document", () => {
        const rtlName = person({
            isNameRtl: true,
            nameSegments: [given("דוד"), surname("כהן")],
        });

        expect(render(rtlName, { vertical: false })[0]?.anchor).toBe("end");
        expect(render(rtlName, { vertical: false })[0]?.direction).toBe("rtl");
        expect(render(rtlName, { vertical: false })[0]?.dx).toEqual([null, "-0.25em"]);
        expect(render(rtlName, { vertical: false, documentRtl: true })[0]?.anchor).toBe("start");
        expect(render(ANNA_MARIA, { vertical: false, documentRtl: true })[0]?.anchor).toBe("end");
        expect(render(ANNA_MARIA, { vertical: false, documentRtl: true })[0]?.x).toBe("30");
    });

    it("draws the alternative name below the name when enabled", () => {
        const texts = render(person({ ...ANNA_MARIA, alternativeName: "Anna Maria Meier" }), {
            vertical: false,
            showAlternativeName: true,
        });

        expect(lines(texts)).toEqual([
            ["Anna", "Maria[u]", "Schmidt[last]"],
            ["Anna", "Maria", "Meier"],
        ]);
        expect(texts[1]?.y).toBe("18");
        expect(texts[1]?.class).toBe("wt-chart-box-name");
        expect(texts[1]?.anchor).toBe("start");
        expect(texts[1]?.baseline).toBeNull();
        expect(texts[1]?.x).toBe("-30");
    });

    it("anchors a right-to-left alternative name at the end", () => {
        const texts = render(
            person({ ...ANNA_MARIA, alternativeName: "דוד כהן", isAltRtl: true }),
            { vertical: false, showAlternativeName: true },
        );

        expect(texts[1]?.anchor).toBe("end");
        expect(texts[1]?.direction).toBe("rtl");
        expect(texts[0]?.anchor).toBe("start");
    });
});

describe("Name.createNamesData", () => {
    /**
     * Describes the groups of a person as the kind of each label, marking the last name group
     * labels with a trailing "!" and the preferred ones with a leading "*".
     *
     * @param {object[]} nameSegments
     *
     * @return {string[][]}
     */
    function groupsOf(nameSegments) {
        const name = Object.create(Name.prototype);

        return name
            .createNamesData({ data: { data: person({ nameSegments }) } })
            .map((group) =>
                group.map(
                    (label) =>
                        (label.isPreferred ? "*" : "") +
                        label.label +
                        (label.isLastName ? "!" : ""),
                ),
            );
    }

    it("groups a run of given names and nicknames, and a run of surnames and married surnames", () => {
        expect(
            groupsOf([
                given("José"),
                preferred("Antonio"),
                nickname("Toni"),
                surname("Gómez"),
                surname("Iglesias"),
                married("(Müller)"),
            ]),
        ).toEqual([
            ["José", "*Antonio", '"Toni"'],
            ["Gómez!", "Iglesias!", "(Müller)!"],
        ]);
    });

    it("keeps the display order when a nickname follows the surnames", () => {
        expect(groupsOf([surname("Anonymous"), nickname("Jonny")])).toEqual([
            ["Anonymous!"],
            ['"Jonny"'],
        ]);
    });

    it("creates no group for a part the name does not have", () => {
        expect(groupsOf([given("Madonna")])).toEqual([["Madonna"]]);
        expect(groupsOf([surname("Anonymous")])).toEqual([["Anonymous!"]]);
        expect(groupsOf([])).toEqual([]);
    });

    it("carries the direction of the name on every label", () => {
        const name = Object.create(Name.prototype);
        const groups = name.createNamesData({
            data: {
                data: person({ isNameRtl: true, nameSegments: [given("דוד"), surname("כהן")] }),
            },
        });

        expect(groups.flat().map((label) => label.isNameRtl)).toEqual([true, true]);
    });
});
