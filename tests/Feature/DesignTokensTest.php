<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the design tokens page renders', function () {
    $response = $this->get(route('design.tokens'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('DesignTokens')
    );
});
