<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\ReportNote;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_hinweise_seite_rendert_mit_gespeicherten_karten(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Die Seite wählt beim Start das erste Konto (nach Label) + aktuellen Monat.
        $account = BankAccount::orderBy('label')->first()
            ?? BankAccount::create(['label' => 'AAA Konto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        $note = ReportNote::create([
            'bank_account_id' => $account->id,
            'period' => Carbon::now()->startOfMonth(),
            'heading' => 'AZ 11 Blatt 99',
            'sort_order' => 0,
        ]);
        $note->lines()->create(['amount' => '95000', 'text' => "Zeile A\nmehrzeilig", 'sort_order' => 0]);

        // Render der Seite mit geladenen (per loadCards stabil verschlüsselten) Karten
        // darf nicht fehlschlagen (das war die Ursache des Reorder-Fehlers).
        $this->actingAs(User::firstOrFail())
            ->get('/admin/steuerbuero-hinweise')
            ->assertSuccessful()
            ->assertSee('AZ 11 Blatt 99');
    }
}
