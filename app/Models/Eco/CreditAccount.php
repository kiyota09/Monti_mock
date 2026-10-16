<?php

namespace App\Models\Eco;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Updated to match the new migration schema.
     */
    protected $fillable = [
        'client_id',            // Links to the business client
        'outstanding_balance',  // Tracks current debt
        'is_good_payer',        // ECO Manager flag for verification
    ];

    /**
     * Relationship: The business client this credit account belongs to.
     * Allows the ECO Manager to see the company name and history.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Helper: Check if the client has enough available credit for a new order.
     * Compares the client's credit_limit to the current outstanding balance.
     */
    public function hasAvailableCredit(float $amount): bool
    {
        $creditLimit = (float) $this->client->credit_limit; //

        return ($creditLimit - $this->outstanding_balance) >= $amount; //
    }
}
