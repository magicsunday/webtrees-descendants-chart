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
use GuzzleHttp\Psr7\ServerRequest;
use MagicSunday\Webtrees\DescendantsChart\Configuration;
use MagicSunday\Webtrees\DescendantsChart\Enum\DescendantLine;
use MagicSunday\Webtrees\DescendantsChart\Module;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\EnglishLocaleFixture;
use MagicSunday\Webtrees\DescendantsChart\Test\Support\ModuleSettingsFixtureTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies how Configuration resolves request parameters and module
 * preferences: the married-names display mode (explicit request value, the new
 * preference, migration from the legacy `default_showMarriedNames` boolean,
 * rejection of invalid values), the settings forwarded on the re-centering
 * route, and the per-request memoisation that keeps the preference query count
 * independent of the tree size.
 *
 * Uses an in-memory SQLite DB because AbstractModule::getPreference() is final
 * and cannot be stubbed; the real implementation reads from the
 * `module_setting` table.
 */
#[CoversClass(Configuration::class)]
#[UsesClass(Module::class)]
#[UsesClass(DescendantLine::class)]
final class ConfigurationTest extends TestCase
{
    use ModuleSettingsFixtureTrait;

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

    /**
     * The descendant line is read from the request parameter.
     */
    #[Test]
    #[DataProvider('descendantLineProvider')]
    public function descendantLineFromRequestParameter(string $value, DescendantLine $expected): void
    {
        $configuration = $this->buildConfiguration(['descendantLine' => $value], []);

        self::assertSame($expected, $configuration->getDescendantLine());
    }

    /**
     * @return iterable<string, array{string, DescendantLine}>
     */
    public static function descendantLineProvider(): iterable
    {
        yield 'all' => ['all', DescendantLine::All];

        yield 'paternal' => ['paternal', DescendantLine::Paternal];

        yield 'maternal' => ['maternal', DescendantLine::Maternal];
    }

    /**
     * Without a request parameter the line shows every descendant.
     */
    #[Test]
    public function descendantLineDefaultsToAll(): void
    {
        self::assertSame(DescendantLine::All, $this->buildConfiguration([], [])->getDescendantLine());
    }

    /**
     * Without a request parameter the module preference decides.
     */
    #[Test]
    public function descendantLineFallsBackToTheModulePreference(): void
    {
        $configuration = $this->buildConfiguration([], ['default_descendantLine' => 'maternal']);

        self::assertSame(DescendantLine::Maternal, $configuration->getDescendantLine());
    }

    /**
     * An unknown request value neither reaches the filter nor overrides the
     * preference with garbage. The preference value is used.
     */
    #[Test]
    public function unknownDescendantLineRequestValueUsesThePreference(): void
    {
        $configuration = $this->buildConfiguration(
            ['descendantLine' => 'sideways'],
            ['default_descendantLine' => 'paternal']
        );

        self::assertSame(DescendantLine::Paternal, $configuration->getDescendantLine());
    }

    /**
     * An unknown value in both places falls back to showing every descendant.
     */
    #[Test]
    public function unknownDescendantLineEverywhereFallsBackToAll(): void
    {
        $configuration = $this->buildConfiguration(
            ['descendantLine' => 'sideways'],
            ['default_descendantLine' => 'upwards']
        );

        self::assertSame(DescendantLine::All, $configuration->getDescendantLine());
    }

    /**
     * The form lists the three lines by their stored value.
     */
    #[Test]
    public function descendantLinesListTheThreeValues(): void
    {
        EnglishLocaleFixture::install();

        self::assertSame(
            ['all', 'paternal', 'maternal'],
            array_keys($this->buildConfiguration([], [])->getDescendantLines())
        );
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
                'descendantLine'      => 'paternal',
                'marriedNamesMode'    => Configuration::MARRIED_NAMES_ONLY,
                'showNicknames'       => '1',
                'openNewTabOnClick'   => '1',
                'showAlternativeName' => '1',
                'showDetailPanel'     => '1',
            ],
            []
        );

        self::assertSame(
            [
                'generations'         => 5,
                'layout'              => Configuration::LAYOUT_TOPBOTTOM,
                'hideSpouses'         => '1',
                'descendantLine'      => 'paternal',
                'showNicknames'       => '1',
                'openNewTabOnClick'   => '1',
                'showAlternativeName' => '1',
                'showDetailPanel'     => '1',
            ],
            $configuration->getRouteToggleParams()
        );
    }

    /**
     * The disabled polarity. `openNewTabOnClick` defaults to on
     * (`default_openNewTabOnClick` is '1'), so an implementation that ignored the
     * request parameter and resolved from the preference alone would emit '1'
     * here — this case is what forces the request value to win, and pins the
     * bool-to-string mapping in the other direction.
     *
     * It does not discriminate a raw passthrough of the query parameters: those
     * are '0' and so is the expectation. Passthrough is caught by `generations`
     * (string in, int out) and by the empty-request case.
     */
    #[Test]
    public function routeParamsCarryTheDisabledDisplaySettings(): void
    {
        $configuration = $this->buildConfiguration(
            [
                'generations'         => '3',
                'layout'              => Configuration::LAYOUT_LEFTRIGHT,
                'hideSpouses'         => '0',
                'descendantLine'      => 'all',
                'marriedNamesMode'    => Configuration::MARRIED_NAMES_OFF,
                'showNicknames'       => '0',
                'openNewTabOnClick'   => '0',
                'showAlternativeName' => '0',
                'showDetailPanel'     => '0',
            ],
            []
        );

        self::assertSame(
            [
                'generations'         => 3,
                'layout'              => Configuration::LAYOUT_LEFTRIGHT,
                'hideSpouses'         => '0',
                'descendantLine'      => 'all',
                'showNicknames'       => '0',
                'openNewTabOnClick'   => '0',
                'showAlternativeName' => '0',
                'showDetailPanel'     => '0',
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
                'default_descendantLine'      => 'maternal',
                'default_marriedNamesMode'    => Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED,
                'default_showNicknames'       => '1',
                'default_openNewTabOnClick'   => '0',
                'default_showAlternativeName' => '1',
                'default_showDetailPanel'     => '1',
            ]
        );

        self::assertSame(
            [
                'generations'         => 7,
                'layout'              => Configuration::LAYOUT_RIGHTLEFT,
                'hideSpouses'         => '1',
                'descendantLine'      => 'maternal',
                'showNicknames'       => '1',
                'openNewTabOnClick'   => '0',
                'showAlternativeName' => '1',
                'showDetailPanel'     => '1',
            ],
            $configuration->getRouteToggleParams()
        );
    }

    /**
     * The married-names mode is an admin preference without a form field on
     * the chart page, so it resolves identically on the re-centered page and
     * never travels in the URL, not even when the request happens to carry it.
     */
    #[Test]
    public function routeParamsLeaveOutAdminOnlyPreferences(): void
    {
        $configuration = $this->buildConfiguration(
            ['marriedNamesMode' => Configuration::MARRIED_NAMES_ONLY],
            ['default_marriedNamesMode' => Configuration::MARRIED_NAMES_BIRTH_AND_MARRIED]
        );

        $params = $configuration->getRouteToggleParams();

        self::assertArrayNotHasKey('marriedNamesMode', $params);
        self::assertArrayNotHasKey('nameAbbreviation', $params);
        self::assertSame(
            [
                'generations',
                'layout',
                'hideSpouses',
                'descendantLine',
                'showNicknames',
                'openNewTabOnClick',
                'showAlternativeName',
                'showDetailPanel',
            ],
            array_keys($params)
        );
    }

    /**
     * The detail panel is off until the request or the administrator turns it on,
     * and the request wins over the module preference in both directions.
     *
     * @param array<string, string> $queryParams
     * @param array<string, string> $preferences
     */
    #[Test]
    #[DataProvider('showDetailPanelProvider')]
    public function showDetailPanelResolvesFromTheRequestThenThePreference(
        array $queryParams,
        array $preferences,
        bool $expected,
    ): void {
        self::assertSame($expected, $this->buildConfiguration($queryParams, $preferences)->getShowDetailPanel());
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>, bool}>
     */
    public static function showDetailPanelProvider(): array
    {
        return [
            'off without request or preference' => [[], [], false],
            'on by the administrator'           => [[], ['default_showDetailPanel' => '1'], true],
            'request switches it on'            => [['showDetailPanel' => '1'], [], true],
            'request switches it off'           => [['showDetailPanel' => '0'], ['default_showDetailPanel' => '1'], false],
        ];
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
     * Runs all per-node getters $rounds times against a FRESH configuration and
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
                $configuration->getDescendantLine();
                $configuration->getMarriedNamesMode();
                $configuration->getShowNicknames();
                $configuration->getShowDetailPanel();
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
