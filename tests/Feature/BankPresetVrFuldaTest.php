<?php

namespace Tests\Feature;

use App\Models\BankPreset;
use Database\Seeders\BankPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankPresetVrFuldaTest extends TestCase
{
    use RefreshDatabase;

    public function test_vr_fulda_vorlage_hat_blz_und_url(): void
    {
        $this->seed(BankPresetSeeder::class);

        $preset = BankPreset::where('name', 'VR Bank Fulda')->firstOrFail();

        $this->assertSame('53060180', $preset->bank_code);
        $this->assertStringContainsString('atruvia.de', $preset->fints_url);
        $this->assertSame('300', $preset->hbci_version);
    }
}
