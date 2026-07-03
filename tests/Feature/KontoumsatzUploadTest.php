<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class KontoumsatzUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_mehrere_belege_gleichzeitig_hochladen(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Konto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-01',
            'amount' => -100.00,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('uploadFiles', [
                UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4 Beleg A'),
                UploadedFile::fake()->createWithContent('b.pdf', '%PDF-1.4 Beleg B'),
            ])
            ->call('uploadReceipt');

        $this->assertSame(2, $tx->fresh()->receipts()->count());
    }

    /**
     * Nach dem Zuordnen bzw. Lösen eines Belegs muss die gerenderte Liste im
     * SELBEN Request stimmen – ohne dass die Seite manuell neu geladen werden
     * muss. Sichert den Livewire-Cache-Fix (refreshSelected) ab.
     */
    public function test_belegliste_aktualisiert_sofort_nach_zuordnen_und_loesen(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Konto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-01', 'amount' => -50.00,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
        $receipt = Receipt::create([
            'type' => 'incoming_invoice', 'business_id' => $account->business_id,
            'invoice_number' => 'RG-4711', 'gross_amount' => 50.00,
        ]);

        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id);

        // Zuordnen -> Beleg muss sofort in der gerenderten Belegliste erscheinen.
        $component->call('attachReceipt', $receipt->id)
            ->assertSee('RG-4711')
            ->assertSee('Zugeordnete Belege (1)');

        // Lösen -> Beleg muss im selben Request wieder verschwinden.
        $component->call('detachReceipt', $receipt->id)
            ->assertDontSee('RG-4711')
            ->assertSee('Zugeordnete Belege (0)');
    }
}
