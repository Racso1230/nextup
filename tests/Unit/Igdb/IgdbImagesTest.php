<?php

use App\Igdb\IgdbImages;

test('it builds a big cover url by default', function () {
    expect(IgdbImages::coverUrl('co1abc'))
        ->toBe('https://images.igdb.com/igdb/image/upload/t_cover_big/co1abc.jpg');
});

test('it builds a url for another size', function () {
    expect(IgdbImages::coverUrl('co1abc', 'thumb'))
        ->toBe('https://images.igdb.com/igdb/image/upload/t_thumb/co1abc.jpg');
});

test('a missing image id gives no url', function () {
    expect(IgdbImages::coverUrl(null))->toBeNull()
        ->and(IgdbImages::coverUrl(''))->toBeNull();
});
