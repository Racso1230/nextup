<?php

use App\Models\User;

test('the landing page carries meta tags, a social preview and structured data in the server HTML', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="description"')
        ->toContain('<link rel="canonical" href="'.url('/').'"')
        ->toContain('<meta property="og:image" content="'.url('/og-image.png').'"')
        ->toContain('<meta name="twitter:card" content="summary_large_image"')
        ->toContain('"@type":"WebApplication"')
        ->toContain('"name":"Oscar Freeman"');
});

test('the structured data is valid JSON', function () {
    $html = (string) $this->get('/')->getContent();

    preg_match('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $match);
    $data = json_decode($match[1] ?? '', true);

    expect($data)->toBeArray()
        ->and($data['@context'])->toBe('https://schema.org')
        ->and($data['offers']['price'])->toBe('0');
});

test('signed-in pages carry no landing meta tags', function () {
    $html = (string) $this->actingAs(User::factory()->create())->get(route('library'))->getContent();

    expect($html)->not->toContain('og:image')->not->toContain('application/ld+json');
});

test('the social preview image exists', function () {
    expect(public_path('og-image.png'))->toBeFile();
});
