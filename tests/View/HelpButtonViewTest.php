<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\View;

use MagicSunday\Webtrees\DescendantsChart\Test\Support\ViewFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\EnglishLocaleFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\MarkupFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function ob_get_clean;
use function ob_start;
use function trim;

/**
 * Renders the help button of the toolbar with its popover.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
#[CoversNothing]
final class HelpButtonViewTest extends TestCase
{
    /**
     * The rows follow each other in a fixed order, each with its own key and its own mode, so a row
     * that moves to the other mode or to another place changes what the help shows in each mode. The
     * active mode is the default one, so its rows and the rows of both modes are visible while the
     * rows of the detail panel mode start hidden. Every row has a text, and the button has a title,
     * because the icon is its only content. The wording of the texts is not pinned.
     */
    #[Test]
    public function listsTheRowsInTheirOrderWithTheirModes(): void
    {
        $xpath    = MarkupFixture::xpathOf($this->render());
        $expected = [
            ['default', 'Click on a person'],
            ['', 'Tab key'],
            ['panel', 'Click or Enter on a person'],
            ['panel', 'Details panel'],
            ['', 'Ctrl + scroll'],
            ['', 'Click and drag'],
        ];

        $button = MarkupFixture::first($xpath, '//button[contains(concat(" ", @class, " "), " btn-help ")]');

        self::assertNotSame('', $button->getAttribute('title'), 'the icon is the only content, so the title names the button');
        self::assertCount(count($expected), MarkupFixture::elements($xpath, '//tr'));

        foreach ($expected as $index => [$mode, $key]) {
            $row     = '(//tr)[' . ($index + 1) . ']';
            $element = MarkupFixture::first($xpath, $row);

            self::assertSame(
                $mode,
                $element->getAttribute('data-help-mode'),
                'the row ' . $key . ' belongs to the mode ' . ($mode === '' ? 'of both' : $mode)
            );
            self::assertSame($key, trim(MarkupFixture::first($xpath, $row . '/td[1]')->textContent));
            self::assertNotSame('', trim(MarkupFixture::first($xpath, $row . '/td[2]')->textContent), 'the row ' . $key . ' describes what it does');

            if ($mode === 'panel') {
                self::assertStringContainsString('d-none', $element->getAttribute('class'), 'the row ' . $key . ' starts hidden');
            } else {
                self::assertStringNotContainsString('d-none', $element->getAttribute('class'), 'the row ' . $key . ' starts visible');
            }
        }
    }

    /**
     * Renders the help button.
     */
    private function render(): string
    {
        ViewFixture::register();
        EnglishLocaleFixture::install();

        ob_start();

        include __DIR__ . '/../../resources/views/modules/components/buttonbar/help-button.phtml';

        $markup = ob_get_clean();

        self::assertIsString($markup);

        return $markup;
    }
}
