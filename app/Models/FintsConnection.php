<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** FinTS-Zugang (Modul 1). PIN verschlüsselt. */
class FintsConnection extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'pin' => 'encrypted',
        'active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    protected $hidden = ['pin'];

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
