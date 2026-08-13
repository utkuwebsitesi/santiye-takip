<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php finalize-production-env.php <input-env> <output-env>\n");
    exit(1);
}

[$script, $input, $output] = $argv;
$contents = file_get_contents($input);
if ($contents === false) {
    throw new RuntimeException('Input environment file could not be read.');
}

$required = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
foreach ($required as $name) {
    if (! preg_match('/^'.preg_quote($name, '/').'=(.+)$/m', $contents, $match)
        || trim($match[1], " \t\n\r\0\x0B\"'") === '') {
        throw new RuntimeException($name.' must have a value.');
    }
}

$key = 'base64:'.base64_encode(random_bytes(32));
if (preg_match('/^APP_KEY=.*$/m', $contents)) {
    $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $contents, 1);
} else {
    $contents = preg_replace('/^(APP_ENV=.*)$/m', "$1\nAPP_KEY=".$key, $contents, 1);
}

if (! is_string($contents) || file_put_contents($output, $contents, LOCK_EX) === false) {
    throw new RuntimeException('Output environment file could not be written.');
}

fwrite(STDOUT, $output.PHP_EOL);
