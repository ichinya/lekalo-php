<?php
declare(strict_types=1);

/** Read a single controller with direct method attributes and literal paths/methods. */
function attribute_routes(array $tokens, object $binding): array
{
    $i = 0;
    $take = static function (string $wanted) use (&$i, $tokens): void {
        if (($tokens[$i++] ?? null) !== $wanted) { throw new UnexpectedValueException('unsupported-controller'); }
    };
    $literal = static function () use (&$i, $tokens): string {
        $value = $tokens[$i++] ?? '';
        if (!preg_match('~^([\x27\x22])([A-Za-z0-9_./{}-]+)\\1$~D', $value, $match)) {
            throw new UnexpectedValueException('computed-route-argument');
        }
        return $match[2];
    };
    $skip = static function (string $open, string $close) use (&$i, $tokens, $take): void {
        $take($open);
        $depth = 1;
        while ($depth && isset($tokens[$i])) {
            $token = $tokens[$i++];
            if ($token === $open) { ++$depth; }
            if ($token === $close) { --$depth; }
        }
        if ($depth) { throw new UnexpectedValueException('unbalanced-controller'); }
    };
    if (($tokens[$i] ?? null) === 'declare') { ++$i; $skip('(', ')'); $take(';'); }
    $namespace = '';
    if (($tokens[$i] ?? null) === 'namespace') { ++$i; $namespace = $tokens[$i++]; $take(';'); }
    $imports = [];
    while (($tokens[$i] ?? null) === 'use') {
        ++$i;
        $class = ltrim($tokens[$i++], '\\');
        $parts = explode('\\', $class);
        $alias = end($parts);
        if (($tokens[$i] ?? null) === 'as') { ++$i; $alias = $tokens[$i++]; }
        $take(';');
        $imports[strtolower($alias)] = strtolower($class);
    }
    if (($tokens[$i] ?? null) === 'final') { ++$i; }
    $take('class'); ++$i;
    if (($tokens[$i] ?? null) === 'extends') { ++$i; ++$i; }
    $take('{');
    $routes = [];
    while (($tokens[$i] ?? null) !== '}') {
        $pending = [];
        while (($tokens[$i] ?? null) === '#[') {
            ++$i;
            $name = strtolower($tokens[$i++] ?? '');
            $resolved = str_starts_with($name, '\\') ? substr($name, 1) : ($imports[$name] ?? strtolower(ltrim($namespace.'\\'.$name, '\\')));
            if ($resolved !== strtolower($binding->class)) { throw new UnexpectedValueException('unknown-attribute'); }
            $take('(');
            if (($tokens[$i] ?? null) === 'path') { ++$i; $take(':'); }
            $path = $literal();
            if (!str_starts_with($path, '/')) { throw new UnexpectedValueException('relative-route'); }
            $take(',');
            if (($tokens[$i] ?? null) === 'name') { ++$i; $take(':'); $literal(); $take(','); }
            $take('methods'); $take(':'); $take('[');
            do {
                $method = $literal();
                if (!preg_match('/^[A-Z]+$/D', $method)) { throw new UnexpectedValueException('invalid-method'); }
                $pending[] = ['method' => $method, 'path' => $path];
                if (($tokens[$i] ?? null) !== ',') { break; }
                ++$i;
            } while (($tokens[$i] ?? null) !== ']');
            $take(']'); $take(')'); $take(']');
        }
        $take('public'); $take('function'); ++$i; $skip('(', ')');
        if (($tokens[$i] ?? null) === ':') { ++$i; ++$i; }
        $skip('{', '}');
        array_push($routes, ...$pending);
    }
    $take('}');
    if ($i !== count($tokens)) { throw new UnexpectedValueException('unknown-controller-tail'); }
    return $routes;
}
