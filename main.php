<?php
/**
 * Source-mode entry of the `lekalo-php` language adapter: the exact
 * production process surface of the language kernel WITHOUT any
 * framework provider registered. The kernel plus its language modules
 * load in the fixed order the unified transitional artifact keeps
 * (issue #241 stage 4); the framework families (scenario emission,
 * route emission, native gates, migrations) resolve through the
 * provider registry and refuse typed (`provider-missing`) until a
 * compatible framework package — e.g. `lekalo-php-laravel` — registers
 * itself in the same process.
 */

declare(strict_types=1);

require __DIR__ . '/src/provider.php';
require __DIR__ . '/src/analyzer.php';
require __DIR__ . '/src/strict-profile.php';
require __DIR__ . '/src/kernel.php';
// The scenario map and the type generator modules (issues #56, #58):
// the kernel lazy-loads them, so the source-mode entry requires them
// explicitly to keep the same 1:1 module surface as the unified
// artifact.
require __DIR__ . '/src/scenario-map.php';
require __DIR__ . '/src/type-policy.php';
require __DIR__ . '/src/type-map.php';
require __DIR__ . '/src/type-codec.php';
require __DIR__ . '/src/type-emit.php';
require __DIR__ . '/src/type-bindings.php';

exit(main());
