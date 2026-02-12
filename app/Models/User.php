<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get canvassing groups assigned to this user (staff)
     */
    public function assignedCanvassingGroups()
    {
        return $this->belongsToMany(CanvassingGroup::class, 'canvassing_group_staff', 'staff_id', 'canvassing_group_id')
            ->withPivot('assigned_start_date', 'assigned_end_date')
            ->withTimestamps();
    }

    /**
     * Get canvassing groups created by this user (supervisor)
     */
    public function createdCanvassingGroups()
    {
        return $this->hasMany(CanvassingGroup::class, 'created_by');
    }

    /**
     * Get prospects created by this user in canvassing groups
     */
    public function canvassingGroupProspects()
    {
        return $this->hasMany(CanvassingGroupProspect::class, 'staff_id');
    }
}
