<?php

namespace App\Models;

use App\Traits\RoutesNotifications;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\HasDatabaseNotifications;
use Illuminate\Support\Collection;
use Prettus\Repository\Contracts\Transformable;
use Prettus\Repository\Traits\TransformableTrait;

/**
 * Class Opportunity
 *
 * @package App\Models
 *
 * @property string     $id
 * @property string     $title
 * @property string     $position
 * @property string     $description
 * @property string     $original
 * @property string     $salary
 * @property string     $company
 * @property string     $location
 * @property int        $telegram_id
 * @property int        $status
 * @property int        $telegram_user_id
 * @property string     $approver
 * @property Collection $files
 * @property Collection $urls
 * @property Collection $origin
 * @property Collection $tags
 * @property Collection $emails
 * @property Carbon     created_at
 * @property Carbon     updated_at
 */
class Opportunity extends Model implements Transformable
{

    use SoftDeletes, TransformableTrait, HasDatabaseNotifications, RoutesNotifications;

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;

    public const COMPANY = 'company';
    public const LOCATION = 'location';
    public const FILES = 'files';
    public const DESCRIPTION = 'description';
    public const ORIGINAL = 'original';
    public const TITLE = 'title';
    public const URLS = 'urls';
    public const ORIGIN = 'origin';
    public const TAGS = 'tags';
    public const POSITION = 'position';
    public const SALARY = 'salary';
    public const EMAILS = 'emails';
    public const TELEGRAM_ID = 'telegram_id';
    public const STATUS = 'status';
    public const TELEGRAM_USER_ID = 'telegram_user_id';
    public const APPROVER = 'approver';

    /**
     * @var array
     */
    protected $fillable = [
        self::TITLE,
        self::POSITION,
        self::DESCRIPTION,
        self::ORIGINAL,
        self::SALARY,
        self::COMPANY,
        self::LOCATION,
        self::FILES,
        self::TELEGRAM_ID,
        self::STATUS,
        self::TELEGRAM_USER_ID,
        self::URLS,
        self::ORIGIN,
        self::TAGS,
        self::EMAILS,
        self::APPROVER,
    ];

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $casts = [
        self::FILES => 'collection',
        self::ORIGIN => 'collection',
        self::TAGS => 'collection',
        self::EMAILS => 'collection',
        self::URLS => 'collection',
    ];

    /**
     * @param $file
     */
    public function addFile($file): void
    {
        $this->files = $this->files->concat([$file]);
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return implode(', ', [
            $this->title,
            $this->position,
            $this->location,
            $this->description,
            $this->tags,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function notification()
    {
        return $this->hasMany(DatabaseNotification::class, 'model_id', 'id');
    }
}
