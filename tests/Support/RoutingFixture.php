<?php

/**
 * This file is part of the package magicsunday/webtrees-descendants-chart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\DescendantsChart\Test\Support;

use Aura\Router\Map;
use Aura\Router\Route;
use Fisharebest\Webtrees\Contracts\ContainerInterface;
use Fisharebest\Webtrees\Contracts\RouteFactoryInterface;
use Fisharebest\Webtrees\Registry;

use function http_build_query;

/**
 * Registers the route factory and the container that the facade needs to build the update URL.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-descendants-chart/
 */
final class RoutingFixture
{
    /**
     * Sets up the route factory and the container the facade needs for the update URL.
     */
    public static function install(): void
    {
        $routeFactory = new class implements RouteFactoryInterface {
            public function route(string $route_name, array $parameters = []): string
            {
                return '/route/' . $route_name . '?' . http_build_query($parameters);
            }

            public function routeMap(): Map
            {
                return new Map(new Route());
            }
        };

        $container = new readonly class($routeFactory) implements ContainerInterface {
            public function __construct(private RouteFactoryInterface $routeFactory)
            {
            }

            public function get(string $id): RouteFactoryInterface
            {
                return $this->routeFactory;
            }

            public function has(string $id): bool
            {
                return $id === RouteFactoryInterface::class;
            }

            public function set(string $id, object $object): static
            {
                return $this;
            }
        };

        Registry::routeFactory($routeFactory);
        Registry::container($container);
    }
}
