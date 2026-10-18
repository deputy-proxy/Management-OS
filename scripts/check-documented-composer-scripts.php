<?php

$composer = json_decode(file_get_contents(__DIR__.'/../composer.json'), true, flags: JSON_THROW_ON_ERROR);
$scriptNames = array_keys($composer['scripts'] ?? []);
$builtInCommands = ['audit', 'install', 'validate'];
$documents = [
    __DIR__.'/../.github/AI_DEVELOPMENT_RULES.md',
    __DIR__.'/../.chatgpt/osingle.md',
];
$errors = [];

foreach ($documents as $document) {
    $content = file_get_contents($document);

    preg_match_all('/`composer\s+([a-z][a-z0-9:_-]*)\b[^`]*`/i', $content, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[1] as [$command, $offset]) {
        $command = strtolower($command);

        if (in_array($command, $builtInCommands, true) || in_array($command, $scriptNames, true)) {
            continue;
        }

        $errors[] = sprintf('%s documents `composer %s`, but it is neither an allowed Composer CLI command nor a defined Composer script.', basename($document), $command);
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

echo "Documented Composer commands are valid.".PHP_EOL;