<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['transaction_id', 'user_id', 'stars', 'comment'];

    protected function casts(): array
    {
        return ['stars' => 'integer'];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
