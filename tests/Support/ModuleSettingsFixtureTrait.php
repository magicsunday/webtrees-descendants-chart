<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Support;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Services\ChartService;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\ModuleBase\Testing\ModuleSettingsFixture;
use ReflectionProperty;

/**
 * Provides a module whose preferences come from a real in-memory database.
 *
 * AbstractModule::getPreference() is final and cannot be stubbed, and the real
 * implementation reads from the `module_setting` table, so the tests that need
 * preferences boot a minimal SQLite schema for it.
 */
trait ModuleSettingsFixtureTrait
{
    /**
     * Builds the module with the given preferences, which the shared settings fixture seeds into
     * an in-memory database.
     *
     * @param array<string, string> $preferences
     */
    private function createModuleWithPreferences(array $preferences): Module
    {
        ModuleSettingsFixture::seed('webtrees-descendants-chart', $preferences);

        $chartService = self::createStub(ChartService::class);
        $module       = new Module($chartService, new DataFacade());

        $reflection = new ReflectionProperty(AbstractModule::class, 'name');
        $reflection->setValue($module, 'webtrees-descendants-chart');

        return $module;
    }
}
