<?php

/**
 * See LICENSE.md file for further details.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees;

use Composer\Autoload\ClassLoader;
use MagicSunday\Webtrees\DescendantsChart\Module;

// Register our namespace
$loader = new ClassLoader();
$loader->addPsr4(
    'MagicSunday\\Webtrees\\DescendantsChart\\',
    __DIR__ . '/src'
);
$loader->register();

// Create and return instance of the module
return app(Module::class);
