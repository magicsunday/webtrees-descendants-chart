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

    /**
     * The enabled polarity. The boolean settings have to leave as the strings
     * `'1'`/`'0'`, because `Validator::boolean()` compares strictly — an int
     * would match neither branch and fall back to the preference default, which
     * is the very regression this list prevents.
     */
    #[Test]
    public function routeParamsCarryTheEnabledDisplaySettings(): void
    {
        $configuration = $this->buildConfiguration(
            [
                'generations'         => '5',
                'layout'              => Configuration::LAYOUT_TOPBOTTOM,
                'hideSpouses'         => '1',
                'marriedNamesMode'    => Configuration::MARRIED_NAMES_ONLY,
                'showNicknames'       => '1',
                'openNewTabOnClick'   => '1',
                'showAlternativeName' => '1',
            ],
            []
        );

        self::assertSame(
            [
                'generations'         => 5,
                'layout'              => Configuration::LAYOUT_TOPBOTTOM,
                'hideSpouses'         => '1',
                'marriedNamesMode'    => Configuration::MARRIED_NAMES_ONLY,
                'showNicknames'       => '1',
                'openNewTabOnClick'   => '1',
                'showAlternativeName' => '1',
            ],
            $configuration->getRouteToggleParams()
        );
    }

    /**
     * The disabled polarity, which the enabled case cannot discriminate: with
     * both toggles on, a raw passthrough of the query parameters would produce
     * the same result as the intended mapping.
     */
    #[Test]
    public function routeParamsCarryTheDisabledDisplaySettings(): void
    {
        $configuration = $this->buildConfiguration(
            [
                'generations'         => '3',
                'layout'              => Configuration::LAYOUT_LEFTRIGHT,
                'hideSpouses'         => '0',
                'marriedNamesMode'    => Configuration::MARRIED_NAMES_OFF,
                'showNicknames'       => '0',
                'openNewTabOnClick'   => '0',
                'showAlternativeName' => '0',
            ],
            []
        );

        self::assertSame(
            [
                'generations'         => 3,
                'layout'              => Configuration::LAYOUT_LEFTRIGHT,
                'hideSpouses'         => '0',
                'marriedNamesMode'    => Configuration::MARRIED_NAMES_OFF,
                'showNicknames'       => '0',
                'openNewTabOnClick'   => '0',
                'showAlternativeName' => '0',
            ],
            $configuration->getRouteToggleParams()
        );
    }

    /**
     * The scenario the forwarding exists for: without any request parameter the
     * effective value is the module preference, and that resolved value — not an
     * echo of the URL — is what has to travel on.
     */
    #[Test]
    public function routeParamsResolveFromModulePreferencesWhenTheRequestIsEmpty(): void
    {
        $configuration = $this->buildConfiguration(
            [],
            [
                'default_generations'         => '7',
                'default_layout'              => Configuration::LAYOUT_RIGHTLEFT,
                'default_hideSpouses'         => '1',
                'default_marriedNamesMode'    => Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED,
                'default_showNicknames'       => '1',
                'default_openNewTabOnClick'   => '0',
                'default_showAlternativeName' => '1',
            ]
        );

        self::assertSame(
            [
                'generations'         => 7,
                'layout'              => Configuration::LAYOUT_RIGHTLEFT,
                'hideSpouses'         => '1',
                'marriedNamesMode'    => Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED,
                'showNicknames'       => '1',
                'openNewTabOnClick'   => '0',
                'showAlternativeName' => '1',
            ],
            $configuration->getRouteToggleParams()
        );
    }

    /**
     * Pins the per-request query cost: however often the getters are called
     * while the tree is built, each preference is read at most once.
     */
    #[Test]
    public function readsEachPreferenceOnceHoweverOftenTheGettersAreCalled(): void
    {
        // Compares one round against ten rather than asserting a fixed number.
        // A fixed bound would encode today's preference count — getMarriedNamesMode()
        // reads two, so it is six — and would fail on an unrelated future addition
        // while blaming memoisation. It would also pass vacuously if the getters
        // stopped querying at all. Constant cost is the property that matters.
        $oneRound  = $this->countPreferenceQueries(1);
        $tenRounds = $this->countPreferenceQueries(10);

        self::assertGreaterThan(0, $oneRound, 'the first round must actually read the preferences');
        self::assertSame(
            $oneRound,
            $tenRounds,
            'preference lookups are not memoised — the query cost scales with the number of nodes'
        );
    }

    /**
     * Runs all seven per-node getters $rounds times against a FRESH configuration and
     * returns how many `module_setting` queries that produced. The instance has
     * to be fresh per measurement — reusing one would leave it memoised from the
     * previous round and report zero.
     */
    private function countPreferenceQueries(int $rounds): int
    {
        $configuration = $this->buildConfiguration([], []);
        $connection    = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            for ($i = 0; $i < $rounds; ++$i) {
                $configuration->getGenerations();
                $configuration->getLayout();
                $configuration->getHideSpouses();
                $configuration->getMarriedNamesMode();
                $configuration->getShowNicknames();
                $configuration->getOpenNewTabOnClick();
                $configuration->getShowAlternativeName();
            }

            // Counts only the preference reads the assertion talks about, so an
            // unrelated query inside the window cannot change the verdict.
            return count(array_filter($connection->getQueryLog(), $this->isPreferenceQuery(...)));
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    /**
     * Whether a query-log entry is a preference read.
     *
     * Declared as a named method with the log entry's shape rather than an
     * inline closure: without the shape Rector wants a `(string)` cast on the
     * query and PHPStan rejects that same cast as useless, so the two gates
     * contradict each other.
     *
     * @param array{query: string, bindings: array<mixed>, time: float|null} $entry
     */
    private function isPreferenceQuery(array $entry): bool
    {
        return str_contains($entry['query'], 'module_setting');
    }
}
