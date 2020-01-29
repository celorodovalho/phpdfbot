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
 *
 * @property string     $name
 * @property string     $title
 * @property boolean    $main
 * @property boolean    $admin
 * @property integer    $type
 * @property Collection $tags
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
        'admin',
        'type',
        'tags',
    ];

    protected $casts = [
        'tags' => 'collection',
    ];

    /**
     * @return mixed|string
     */
    public function getTitleAttribute()
    {
        switch ($this->type) {
            case GroupTypes::CHANNEL:
            case GroupTypes::GROUP:
                return str_replace('@', '', $this->name);
            case GroupTypes::MAILING:
                return explode('@', $this->name)[0];
            case GroupTypes::GITHUB:
            default:
                return $this->name;
        }
    }
}
