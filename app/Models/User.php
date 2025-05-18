<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'phone',
        'password',
        'avatar',
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

    // Quan hệ: Người dùng gửi lời mời
    public function sentFriendRequests()
    {
        return $this->hasMany(Friend::class, 'user_id');
    }

    // Quan hệ: Người dùng nhận lời mời
    public function receivedFriendRequests(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friends', 'friend_id', 'user_id')
                    ->wherePivot('status', 'pending')
                    ->withTimestamps()
                    ->select('users.id', 'users.name', 'users.avatar');
    }

    //Lấy danh sách bạn bè (status = accepted)
    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friends', 'user_id', 'friend_id')
                    ->wherePivot('status', 'accepted')
                    ->withPivot('alias')
                    ->withTimestamps()
                    ->select('users.id', 'users.name', 'users.avatar');// Chỉ định rõ các cột từ bảng users
    }

    // Thêm quan hệ để lấy tất cả yêu cầu bạn bè (bao gồm pending)
    public function friendRequests(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friends', 'user_id', 'friend_id')
                    ->withPivot('status', 'alias')
                    ->withTimestamps();
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }


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
}