<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class User
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class User extends Model implements Transformable
{
    use TransformableTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'username',
        'is_bot',
        'first_name',
        'last_name',
        'language_code',
    ];
}
