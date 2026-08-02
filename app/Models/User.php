<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use TracksChanges;

    protected $fillable = [
        'name',
        'username',
        'password',
        'email',
        'role_id',
        'customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function hasPermission($ability)
    {
        foreach ($this->role->permissions as $permission) {
            if ($permission->name === $ability) {
                return true;
            }
        }

        return false;
    }

    public function hasCustomer()
    {
        if ($this->customer_id) {
            return true;
        }

        return false;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Ein vom Seeder angelegter Demo-Zugang, der auf der Demo-Instanz nicht
     * veraendert werden darf: Wer das Admin-Passwort aendert oder das Konto
     * loescht, sperrt alle uebrigen Besucher aus.
     *
     * Ausserhalb des Demo-Modus ist niemand geschuetzt - auf einer echten
     * Installation soll ein Admin seine Benutzer natuerlich verwalten koennen.
     */
    public function istDemoGeschuetzt(): bool
    {
        return (bool) config('app.demo')
            && in_array($this->username, config('custom.demo_protected_users', []), true);
    }
}
