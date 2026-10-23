<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('does not expose the style guide outside the local environment', function (): void {
    expect(app()->isLocal())->toBeFalse()
        ->and(Route::has('dev.styleguide'))->toBeFalse();

    $this->get('/dev/styleguide')->assertNotFound();
});
