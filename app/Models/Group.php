<?php

namespace App\Models;

use App\Enums\GroupTypes;
use App\Traits\RoutesNotifications;
use App\Traits\TelegramIdentifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\HasDatabaseNotifications;
use Illuminate\Support\Collection;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class Groups.
 * @property string $name
 * @property string $title
 * @property boolean $main
 * @property integer $type
 * @property Collection $tags
 */
class Group extends Model implements Transformable
{
    use TransformableTrait, HasDatabaseNotifications, RoutesNotifications, TelegramIdentifiable;

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

    public function getTitleAttribute()
    {
        switch ($this->type) {
            case GroupTypes::TYPE_CHANNEL:
            case GroupTypes::TYPE_GROUP:
                return str_replace('@', '', $this->name);
            case GroupTypes::TYPE_MAILING:
                return explode('@', $this->name)[0];
            case GroupTypes::TYPE_GITHUB:
            default:
                return $this->name;
        }
    }
}
