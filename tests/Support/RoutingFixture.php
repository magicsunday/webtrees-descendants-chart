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
use OutOfBoundsException;
use Psr\Http\Message\ResponseFactoryInterface;

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
     * Sets up the route factory and the container the facade needs for the update URL. A test that
     * follows a redirect passes the PSR-17 response factory, which the container then hands out too.
     *
     * @param ResponseFactoryInterface|null $responseFactory The response factory that the global redirect() helper resolves
     *
     * @return Map The route map that the route factory hands out, so a test can read back the registered routes
     */
    public static function install(?ResponseFactoryInterface $responseFactory = null): Map
    {
        $routeMap = new Map(new Route());

        $routeFactory = new readonly class($routeMap) implements RouteFactoryInterface {
            public function __construct(private Map $routeMap)
            {
            }

            public function route(string $route_name, array $parameters = []): string
            {
                return '/route/' . $route_name . '?' . http_build_query($parameters);
            }

            public function routeMap(): Map
            {
                return $this->routeMap;
            }
        };

        $services = [RouteFactoryInterface::class => $routeFactory];

        if ($responseFactory instanceof ResponseFactoryInterface) {
            $services[ResponseFactoryInterface::class] = $responseFactory;
        }

        $container = new readonly class($services) implements ContainerInterface {
            /**
             * @param array<string, object> $services The services by id
             */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): object
            {
                if (!isset($this->services[$id])) {
                    throw new OutOfBoundsException('The container has no service ' . $id);
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }

            public function set(string $id, object $object): static
            {
                return $this;
            }
        };

        Registry::routeFactory($routeFactory);
        Registry::container($container);

        return $routeMap;
    }
}
