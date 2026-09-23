<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_european_portuguese(): void
    {
        $this->assertSame('pt_PT', app()->getLocale());
        $this->assertSame('É obrigatória a indicação de um valor para o campo e-mail.', __('validation.required', ['attribute' => 'e-mail']));
    }

    public function test_translations_are_shared_with_the_frontend(): void
    {
        $this->actingAs(User::factory()->inWorkspace()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'pt_PT')
                ->has('translations'));
    }

    public function test_translation_file_is_valid_json(): void
    {
        $this->assertIsArray(json_decode(File::get(lang_path('pt_PT.json')), true, flags: JSON_THROW_ON_ERROR));
    }
}
