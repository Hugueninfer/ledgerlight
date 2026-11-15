<?php

arch()->preset()->php();
arch()->preset()->security();

arch('no debug helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('controllers stay thin')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('tenant-owned models use the tenant scope')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait('App\Tenancy\BelongsToTenant')
    ->ignoring('App\Models\Tenant');
