<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    public const LEVELS = ['admin', 'vip', 'biasa', 'member'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
        'level',
        'status',
        'phone',
        'whatsapp',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'integer',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function balanceMutations()
    {
        return $this->hasMany(BalanceMutation::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function priceFieldForProduct(): string
    {
        return match ($this->level) {
            'admin' => 'price_vip',
            'vip' => 'price_vip',
            'biasa' => 'price_biasa',
            default => 'price_guest',
        };
    }

    public function priceFor(Product $product): int
    {
        $field = $this->priceFieldForProduct();

        return (int) $product->{$field};
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        return $this->hasRole('admin') || $this->level === 'admin';
    }
}
