<?php

test('root redirects unauthenticated visitors to login and configures Asia/Jakarta and id locale', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('app.locale'))->toBe('id');

    $this->get('/')->assertRedirect(route('login'));
});
