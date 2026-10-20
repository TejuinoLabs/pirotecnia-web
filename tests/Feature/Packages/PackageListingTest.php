<?php

namespace Tests\Feature\Packages;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'description' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => 1, 'phone' => '3121234567']));
        $this->withoutVite();
    }

    public function test_pagination_displays_different_rows_on_each_page(): void
    {
        $ids = $this->createPackages();

        $this->get(route('settings.packages.index'))
            ->assertOk()
            ->assertViewHas('listRows', fn ($rows) => $rows->total() === 16 && $rows->count() === 15 && $rows->pluck('id')->all() === array_slice($ids, 0, 15))
            ->assertSee('rel="next"', false)
            ->assertSee('page=2', false);

        $this->get(route('settings.packages.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('listRows', fn ($rows) => $rows->currentPage() === 2 && $rows->pluck('id')->all() === [$ids[15]])
            ->assertSee('rel="prev"', false);
    }

    public function test_search_filters_all_packages_and_keeps_the_filter_when_paginating(): void
    {
        $ids = $this->createPackages();
        Package::create(['name' => 'Otro catálogo', 'description' => 'Sin coincidencia', 'price' => 200]);

        $this->get(route('settings.packages.index', ['search' => '  boda  ']))
            ->assertOk()
            ->assertViewHas('search', 'boda')
            ->assertViewHas('listRows', fn ($rows) => $rows->total() === 16 && $rows->count() === 15)
            ->assertSee('search=', false)
            ->assertSee('page=2', false)
            ->assertSee('name="search"', false)
            ->assertSee('Limpiar');

        $this->get(route('settings.packages.index', ['search' => 'boda', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('listRows', fn ($rows) => $rows->total() === 16 && $rows->pluck('id')->all() === [$ids[15]]);

        $this->get(route('settings.packages.index', ['search' => 'Paquete 16']))
            ->assertOk()
            ->assertViewHas('listRows', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $ids[15]);
    }

    public function test_search_matches_package_codes_and_handles_no_results(): void
    {
        $package = Package::create(['name' => 'Especial', 'description' => 'Celebración', 'price' => 100]);
        $code = 'PKG-'.str_pad((string) $package->id, 4, '0', STR_PAD_LEFT);

        $this->get(route('settings.packages.index', ['search' => $code]))
            ->assertOk()
            ->assertViewHas('listRows', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $package->id);

        $this->get(route('settings.packages.index', ['search' => 'inexistente']))
            ->assertOk()
            ->assertViewHas('listRows', fn ($rows) => $rows->total() === 0)
            ->assertSee('No se encontraron paquetes');
    }

    private function createPackages(): array
    {
        return collect(range(1, 16))->map(fn ($number) => Package::create([
            'name' => sprintf('Paquete %02d', $number),
            'description' => 'Para BODA',
            'price' => 100,
        ])->id)->all();
    }
}
