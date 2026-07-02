<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verknüpfung Tankstelle (Betrieb) ↔ Lieferant mit der Kundennummer, die der
 * Lieferant dieser Tankstelle zugeteilt hat (Modul 4).
 */
class SupplierCustomerNumber extends Model
{
    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}
