<?php

use App\Contracts\PaletteAction;
use App\Services\CommandBus;

/*
 * Every command in config/command-bus.php names a handler class as a string.
 * Nothing checks that the class on the other end of that string exists, so a
 * mapping can point at a class nobody ever wrote and stay silent until someone
 * submits the form -- which is how vendor creation came to answer
 *
 *   Command handler class not found:
 *   App\Modules\Accounting\Actions\Vendor\CreateAction
 *
 * on a live server. All five vendor commands were mapped to a directory that
 * did not exist, and credit_note.list to a class that did not either.
 *
 * This is the cheapest possible guard: it needs no database, no company and no
 * fixtures, and it fails the moment a mapping and its class disagree.
 */

test('every mapped command resolves to a class that exists', function () {
    $map = config('command-bus') ?? config('command_bus', []);

    expect($map)->not->toBeEmpty('The command bus map is empty; config is not loading');

    $missing = [];
    foreach ($map as $command => $class) {
        if (! class_exists($class)) {
            $missing[] = "{$command} => {$class}";
        }
    }

    expect($missing)->toBe([], 'Commands mapped to classes that do not exist: '.implode(', ', $missing));
});

test('every mapped command handler is a PaletteAction', function () {
    // CommandBus::resolveHandler() rejects anything else at dispatch time, so a
    // handler that exists but does not implement the contract fails just as
    // late as one that does not exist at all.
    $map = config('command-bus') ?? config('command_bus', []);

    $wrongContract = [];
    foreach ($map as $command => $class) {
        if (! class_exists($class)) {
            continue; // Reported by the test above; not this one's business.
        }

        if (! in_array(PaletteAction::class, class_implements($class) ?: [], true)) {
            $wrongContract[] = "{$command} => {$class}";
        }
    }

    expect($wrongContract)->toBe([], 'Handlers not implementing PaletteAction: '.implode(', ', $wrongContract));
});

test('the bus reports every mapped command as available', function () {
    // has() already combines "is mapped" with "class is loadable". If it
    // disagrees with registered(), the palette is offering a command that
    // cannot run.
    $bus = app(CommandBus::class);

    $unavailable = array_values(array_filter(
        $bus->registered(),
        fn (string $command) => ! $bus->has($command),
    ));

    expect($unavailable)->toBe([], 'Registered but unavailable: '.implode(', ', $unavailable));
});
