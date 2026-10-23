<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

arch('no debugging or process-killing calls in app')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

/*
 * CSPRNG only: randomness comes from Random\Randomizer (App\Domain\Shared\Randomizers).
 * The legacy generators are predictable (Mersenne Twister) or not random at all.
 */
arch('no legacy random functions in app')
    ->expect(['rand', 'mt_rand', 'mt_srand', 'srand', 'lcg_value', 'shuffle', 'array_rand', 'str_shuffle', 'uniqid'])
    ->not->toBeUsed();

arch('env() is not called from classes')
    ->expect('env')
    ->not->toBeUsed();

test('env() is only called inside config/', function (): void {
    $directories = ['app', 'bootstrap', 'database', 'routes', 'resources/views'];
    $offenders = [];

    foreach ($directories as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.php') || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (preg_match('/(?<![\w>:$])env\s*\(/', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $file->getPathname();
            }
        }
    }

    expect($offenders)->toBe([]);
});

arch('controllers have the Controller suffix')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller');

arch('controllers do not use the DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse([DB::class, 'DB']);

arch('actions are final')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal();

/*
 * Exception: App\Actions\Fortify classes implement Fortify contracts
 * (CreatesNewUsers::create, ResetsUserPasswords::reset), so their entry
 * point name is dictated by the package, not by us.
 */
arch('actions have a handle method')
    ->expect('App\Actions')
    ->classes()
    ->toHaveMethod('handle')
    ->ignoring('App\Actions\Fortify');

arch('enums namespace contains only enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('models extend Eloquent Model')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

arch('User model extends Authenticatable')
    ->expect('App\Models\User')
    ->toExtend(Authenticatable::class);

arch('all app code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

test('authorization checks permissions, never role names', function (): void {
    $root = dirname(__DIR__, 2);
    $scanned = ['app/Http/Controllers', 'app/Actions', 'app/Policies', 'resources/js/components', 'resources/js/pages', 'resources/js/layouts'];
    $offenders = [];

    foreach ($scanned as $directory) {
        if (! is_dir("{$root}/{$directory}")) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (preg_match('/\b(hasRole|hasAnyRole|hasAllRoles)\s*\(/', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('every model has a policy', function (): void {
    $missing = collect(glob(dirname(__DIR__, 2).'/app/Models/*.php') ?: [])
        ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
        ->reject(fn (string $model): bool => class_exists("App\\Policies\\{$model}Policy"))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
