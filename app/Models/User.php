<?php

namespace App\Models;

use App\Support\AdminRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

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

        // Akses panel ditentukan oleh peran, bukan sekadar kolom `level`.
        // level='admin' lama tetap diterima agar akun yang sudah ada tidak
        // langsung terkunci di luar panel.
        if ($this->level === 'admin' && $this->roles()->count() === 0) {
            return true;
        }

        return $this->hasAnyRole([
            AdminRoles::SUPER_ADMIN,
            AdminRoles::ADMIN,
            AdminRoles::OPERATOR,
        ]);
    }

    /**
     * Peran panel yang menentukan batas akses. Mengembalikan null untuk user
     * yang bukan staf (mis. reseller), sehingga tidak ada izin yang bocor.
     */
    public function panelRoleName(): ?string
    {
        foreach ([AdminRoles::SUPER_ADMIN, AdminRoles::ADMIN, AdminRoles::OPERATOR] as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }

        return $this->level === 'admin' ? AdminRoles::ADMIN : null;
    }

    /**
     * Super Admin selalu boleh. Peran lain mengikuti izin yang diberikan,
     * dengan fallback ke izin default perannya bila belum pernah di-seed.
     */
    public function hasAdminPermission(string $permission): bool
    {
        if ($this->hasRole(AdminRoles::SUPER_ADMIN)) {
            return true;
        }

        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        $role = $this->panelRoleName();
        if ($role === null) {
            return false;
        }

        return in_array($permission, AdminRoles::ROLE_PERMISSIONS[$role] ?? [], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(AdminRoles::SUPER_ADMIN);
    }
}
