<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Services\ChartService;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Database\Schema\Blueprint;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Facade\DataFacade;
use MagicSunday\Webtrees\DescendantsChart\Module;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Verifies the married-names display mode resolution: explicit request value,
 * fallback to the new module preference, migration from the legacy
 * `default_showMarriedNames` boolean, and rejection of invalid values.
 *
 * Uses an in-memory SQLite DB because AbstractModule::getPreference() is final
 * and cannot be stubbed; the real implementation reads from the
 * `module_setting` table.
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    /**
     * Boots an in-memory SQLite + minimal `module_setting` schema once per
     * process; subsequent calls just truncate the table and re-seed.
     *
     * @param array<string, string> $preferences
     */
    private function createModuleWithPreferences(array $preferences): Module
    {
        static $initialised = false;

        if ($initialised === false) {
            $database = new DB();
            $database->addConnection([
                'driver'   => 'sqlite',
                'database' => ':memory:',
            ]);
            $database->setAsGlobal();
            $database->bootEloquent();
            DB::connection()->getSchemaBuilder()->create('module_setting', static function (Blueprint $table): void {
                $table->string('module_name');
                $table->string('setting_name');
                $table->string('setting_value');
            });

            $initialised = true;
        }

        DB::table('module_setting')->delete();

        if ($preferences !== []) {
            DB::table('module_setting')->insert(
                array_map(
                    static fn (string $name, string $value): array => [
                        'module_name'   => 'webtrees-descendants-chart',
                        'setting_name'  => $name,
                        'setting_value' => $value,
                    ],
                    array_keys($preferences),
                    array_values($preferences),
                )
            );
        }

        $chartService = self::createStub(ChartService::class);
        $module       = new Module($chartService, new DataFacade());

        $reflection = new ReflectionProperty(AbstractModule::class, 'name');
        $reflection->setValue($module, 'webtrees-descendants-chart');

        return $module;
    }

    /**
     * @param array<string, string> $queryParams
     * @param array<string, string> $preferences
     */
    private function buildConfiguration(array $queryParams, array $preferences): Configuration
    {
        $request = (new ServerRequest(RequestMethodInterface::METHOD_GET, '/'))
            ->withQueryParams($queryParams);

        return new Configuration($request, $this->createModuleWithPreferences($preferences));
    }

    #[Test]
    public function modeFromRequestParameterOff(): void
    {
        $configuration = $this->buildConfiguration(['marriedNamesMode' => 'off'], []);

        self::assertSame(Configuration::MARRIED_NAMES_OFF, $configuration->getMarriedNamesMode());
    }

    #[Test]
    public function modeFromRequestParameterMarriedOnly(): void
    {
        $configuration = $this->buildConfiguration(['marriedNamesMode' => 'married_only'], []);

        self::assertSame(Configuration::MARRIED_NAMES_ONLY, $configuration->getMarriedNamesMode());
    }

    #[Test]
    public function modeFromRequestParameterBirthAndMarried(): void
    {
        $configuration = $this->buildConfiguration(['marriedNamesMode' => 'birth_and_married'], []);

        self::assertSame(Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED, $configuration->getMarriedNamesMode());
    }

    #[Test]
    public function unknownRequestParameterValueFallsBackToOff(): void
    {
        $configuration = $this->buildConfiguration(['marriedNamesMode' => 'totally_invalid'], []);

        self::assertSame(Configuration::MARRIED_NAMES_OFF, $configuration->getMarriedNamesMode());
    }

    #[Test]
    public function newDefaultPreferenceIsUsedWhenNoRequestParameter(): void
    {
        $configuration = $this->buildConfiguration(
            [],
            ['default_marriedNamesMode' => 'birth_and_married']
        );

        self::assertSame(Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED, $configuration->getMarriedNamesMode());
    }

    #[Test]
    public function legacyBooleanPreferenceTrueMigratesToMarriedOnly(): void
    {
        $configuration = $this->buildConfiguration(
            [],
            ['default_showMarriedNames' => '1']
        );

        self::assertSame(Configuration::MARRIED_NAMES_ONLY, $configuration->getMarriedNamesMode());
    }

    #[Test]
    public function legacyBooleanPreferenceFalseMigratesToOff(): void
    {
        $configuration = $this->buildConfiguration(
            [],
            ['default_showMarriedNames' => '0']
        );

        self::assertSame(Configuration::MARRIED_NAMES_OFF, $configuration->getMarriedNamesMode());
    }

    #[Test]
    public function newPreferenceTakesPrecedenceOverLegacyBoolean(): void
    {
        $configuration = $this->buildConfiguration(
            [],
            [
                'default_showMarriedNames' => '1',
                'default_marriedNamesMode' => 'birth_and_married',
            ]
        );

        self::assertSame(Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED, $configuration->getMarriedNamesMode());
    }
}
