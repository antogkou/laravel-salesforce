<?php

declare(strict_types=1);

arch()->preset()->php();

// arch()->preset()->strict();

arch()->preset()->security()->ignoring('assert');

arch('strict types')
    ->expect('Antogkou\LaravelSalesforce')
    ->toUseStrictTypes();

arch('avoid open for extension')
    ->expect('Antogkou\LaravelSalesforce')
    ->classes()
    ->toBeFinal();

arch('ensure no extends')
    ->expect('Antogkou\LaravelSalesforce')
    ->classes()
    ->not->toBeAbstract();

arch('avoid mutation')
    ->expect('Antogkou\LaravelSalesforce')
    ->classes()
    ->toBeReadonly()
    ->ignoring([
        'Antogkou\LaravelSalesforce\Exceptions',
        'Antogkou\LaravelSalesforce\SalesforceServiceProvider',
    ]);

arch('avoid inheritance')
    ->expect('Antogkou\LaravelSalesforce')
    ->classes()
    ->toExtendNothing()
    ->ignoring([
        'Antogkou\LaravelSalesforce\Exceptions',
        'Antogkou\LaravelSalesforce\SalesforceServiceProvider',
        'Antogkou\LaravelSalesforce\Facades',
    ]);
