<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Support;

use Fisharebest\Webtrees\Site;
use RuntimeException;

use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function rmdir;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Owns the temporary index directory whose media folder holds the image files of a test, and points
 * the site at it, so a person shows an image only when its file is there.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
final class MediaDirectory
{
    /**
     * The index directory, or an empty string while there is none.
     */
    private string $indexDirectory = '';

    /**
     * Creates an empty media folder in a new temporary index directory and makes it the index directory
     * of the site.
     *
     * @param string $prefix The start of the name of the temporary directory
     */
    public function create(string $prefix): void
    {
        $this->indexDirectory = sys_get_temp_dir() . '/' . $prefix . uniqid('', true) . '/';

        if (
            !mkdir($this->indexDirectory . 'media', 0o777, true)
            && !is_dir($this->indexDirectory . 'media')
        ) {
            throw new RuntimeException('The media directory could not be created.');
        }

        Site::setPreference('INDEX_DIRECTORY', $this->indexDirectory);
    }

    /**
     * Writes an image file into the media folder.
     *
     * @param string $name The file name of the image
     */
    public function writeFile(string $name): void
    {
        if (file_put_contents($this->indexDirectory . 'media/' . $name, 'image') !== strlen('image')) {
            throw new RuntimeException('The media file could not be written.');
        }
    }

    /**
     * Removes the media files and the directories, when there are any.
     */
    public function remove(): void
    {
        if ($this->indexDirectory === '') {
            return;
        }

        $files = glob($this->indexDirectory . 'media/*');

        foreach (($files === false) ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->indexDirectory . 'media');
        rmdir($this->indexDirectory);

        $this->indexDirectory = '';
    }
}
