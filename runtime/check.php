<?php
declare(strict_types=1);

// A bounded static route table reader. Treat all project PHP as data.
function route_tokens(string $source): array
{
    if (!function_exists('token_get_all') && function_exists('dl') && PHP_SHLIB_SUFFIX === 'so') {
        @dl('tokenizer.so');
    }
    $tokens = [];
    foreach (token_get_all($source, TOKEN_PARSE) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
            $tokens[] = $token[1];
        } else {
            $tokens[] = $token;
        }
    }
    return $tokens;
}

// Accept direct, unconditional static registrations in the selected files.
// Refuse groups, conditionals, chained modifiers and computed paths rather
// than attributing a nested declaration to the wrong URL.
function read_routes(array $tokens, object $binding): array
{
    $class = strtolower($binding->class);
    $aliases = [];
    $routes = [];
    $i = 0;
    while (isset($tokens[$i])) {
        if (array_slice($tokens, $i, 7) === ['declare', '(', 'strict_types', '=', '1', ')', ';']) {
            $i += 7;
            continue;
        }
        if (strtolower($tokens[$i]) === 'use') {
            $name = ltrim($tokens[++$i] ?? '', '\\');
            $parts = explode('\\', $name);
            $alias = end($parts);
            ++$i;
            if (strtolower($tokens[$i] ?? '') === 'as') {
                $alias = $tokens[++$i] ?? '';
                ++$i;
            }
            if (($tokens[$i++] ?? '') !== ';') { throw new UnexpectedValueException('import'); }
            $aliases[strtolower($alias)] = strtolower($name);
            continue;
        }
        $receiver = strtolower($tokens[$i++]);
        $resolved = $receiver[0] === '\\' ? substr($receiver, 1) : ($aliases[$receiver] ?? '');
        if ($resolved !== $class || ($tokens[$i++] ?? '') !== '::') { throw new UnexpectedValueException('receiver'); }
        $method = $binding->methods->{strtolower($tokens[$i++] ?? '')} ?? null;
        if (!is_string($method) || !preg_match('/^[A-Z]+$/D', $method) || ($tokens[$i++] ?? '') !== '(') {
            throw new UnexpectedValueException('method');
        }
        $literal = $tokens[$i++] ?? '';
        if (!preg_match('~^([\x27\x22])(/[a-zA-Z0-9_./{}-]*)\\1$~D', $literal, $path)
            || ($tokens[$i++] ?? '') !== ',') { throw new UnexpectedValueException('path'); }

        // The callback may contain nested code. Its contents never contribute
        // routes. Token parsing has already checked PHP syntax.
        $depth = 1;
        while ($depth > 0 && isset($tokens[$i])) {
            $token = $tokens[$i++];
            if ($token === '(') { ++$depth; }
            if ($token === ')') { --$depth; }
        }
        if ($depth !== 0 || ($tokens[$i++] ?? '') !== ';') { throw new UnexpectedValueException('registration'); }
        $routes[] = ['method' => $method, 'path' => $path[2]];
    }
    return $routes;
}

function contract_facts(object $input): object
{
    $facts = new stdClass();
    if ($input->rules === []) { return $facts; }
    $binding = null;
    foreach ($input->rules as $pack) {
        $reader = $pack->rules->bindings->{'php.static-router/v1'} ?? null;
        if ($reader === null) { continue; }
        if (!is_object($reader) || !is_string($reader->class ?? null) || $reader->class === ''
            || !is_object($reader->methods ?? null) || !is_array($reader->files ?? null)
            || $reader->files === [] || ($binding !== null && $reader != $binding)) {
            throw new UnexpectedValueException('incompatible-reader-binding');
        }
        $binding = $reader;
    }
    if ($binding === null) { throw new UnexpectedValueException('missing-reader-binding'); }
    $routes = [];
    foreach ($input->files as $file) {
        if (in_array($file->path, $binding->files, true)) {
            array_push($routes, ...read_routes(route_tokens($file->content), $binding));
        }
    }
    usort($routes, static fn(array $a, array $b): int =>
        [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);
    foreach ($input->rules as $pack) {
        $checks = new stdClass();
        foreach ($pack->rules->checks as $check) { $checks->{$check->id} = ['routes' => $routes]; }
        $facts->{$pack->id} = $checks;
    }
    return $facts;
}

try {
    $q = json_decode(stream_get_contents(STDIN), false, 512, JSON_THROW_ON_ERROR);
    if ($q->protocol !== 'lekalo/runtime/v1') { exit(2); }
    if ($q->operation === 'describe') {
        $result = ['id' => 'lekalo-php', 'version' => '0.2.0',
            'capabilities' => ['http.routes/v1'], 'operations' => ['check'], 'rules_api' => ['lekalo/contract-rules/v1']];
    } elseif ($q->operation === 'check') {
        try { $result = ['state' => 'complete', 'facts' => contract_facts($q->input)]; }
        catch (Throwable $e) { $result = ['state' => 'partial', 'facts' => new stdClass()]; }
    } else { exit(2); }
    echo json_encode(['protocol' => $q->protocol, 'request_id' => $q->request_id, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) { exit(2); }
