<?php

namespace App\Models;

use App\Traits\RoutesNotifications;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\HasDatabaseNotifications;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class Groups.
 */
class Group extends Model implements Transformable
{
    use TransformableTrait, HasDatabaseNotifications, RoutesNotifications;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'main',
        'type',
        'tags',
    ];

    protected $casts = [
        'tags' => 'collection',
    ];

    protected $appends = ['telegram_id'];

    protected $telegramId;

    public function getTelegramIdAttribute()
    {
        return $this->telegramId;
    }

    public function setTelegramIdAttribute($telegramId)
    {
        $this->telegramId = $telegramId;
    }
}
