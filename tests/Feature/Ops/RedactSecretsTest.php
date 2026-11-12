<?php

use App\Logging\RedactSecrets;
use App\Logging\RedactSecretsTap;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;

function redactedOutput(string $message, array $context = []): string
{
    $record = new LogRecord(new DateTimeImmutable, 'test', Level::Info, $message, $context);

    return (new JsonFormatter)->format((new RedactSecrets)($record));
}

beforeEach(function () {
    config([
        'services.steam.key' => 'STEAMKEY1234567890',
        'services.igdb.client_secret' => 'igdbsecret-abcdef',
    ]);
});

it('scrubs the configured Steam key and IGDB secret from message and context', function () {
    $output = redactedOutput('call with STEAMKEY1234567890', [
        'nested' => ['secret' => 'igdbsecret-abcdef'],
        'count' => 3,
    ]);

    expect($output)
        ->not->toContain('STEAMKEY1234567890')
        ->not->toContain('igdbsecret-abcdef')
        ->toContain('[redacted]')
        ->toContain('"count":3');
});

it('scrubs bearer tokens', function () {
    $output = redactedOutput('Authorization: Bearer xyz', ['header' => 'Bearer abc.def-123']);

    expect($output)->not->toContain('xyz')->not->toContain('abc.def-123')->toContain('Bearer [redacted]');
});

it('scrubs key= query strings even for unknown keys', function () {
    $output = redactedOutput('GET https://api.example.test/x?key=OTHERSECRET&format=json');

    expect($output)->not->toContain('OTHERSECRET')->toContain('format=json');
});

it('leaves ordinary text alone', function () {
    expect(redactedOutput('Imported 12 games'))->toContain('Imported 12 games');
});

it('never lets the Steam key reach a real log channel', function () {
    $path = storage_path('logs/redact-test-'.uniqid().'.log');
    config(['logging.channels.redact_test' => [
        'driver' => 'single',
        'path' => $path,
        'tap' => [RedactSecretsTap::class],
    ]]);

    Log::channel('redact_test')->info('url ?key=STEAMKEY1234567890', ['k' => 'STEAMKEY1234567890']);

    $contents = (string) file_get_contents($path);
    unlink($path);

    expect($contents)->not->toContain('STEAMKEY1234567890')->toContain('[redacted]');
});
