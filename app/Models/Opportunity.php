<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\PhotoSize;

/**
 * Class Opportunity
 * @package App\Models
 *
 * @property string $id
 * @property string $title
 * @property string $position
 * @property string $description
 * @property string $salary
 * @property string $company
 * @property string $location
 * @property int $telegram_id
 * @property int $status
 * @property Collection $files
 */
class Opportunity extends Model
{

    use SoftDeletes;

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const CALLBACK_APPROVE = 'approve';
    public const CALLBACK_REMOVE = 'remove';

    protected $fillable = [
        'title',
        'position',
        'description',
        'salary',
        'company',
        'location',
        'files',
        'telegram_id',
        'status',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'files' => 'collection',
    ];

    /**
     * @return Collection
     */
    public function getFilesList(): Collection
    {
        return $this->files;
    }

    /**
     * Add file to collection
     *
     * @param string|array|PhotoSize $file
     */
    public function addFile($file = null)
    {
        $this->files->add($file);
    }

    /**
     * Check if there is file in collection
     *
     * @return bool
     */
    public function hasFile(): bool
    {
        return $this->files ? $this->files->isNotEmpty() : false;
    }

    /**
     *
     */
    protected static function boot()
    {
        parent::boot(); //because we want the parent boot to be run as well
        static::creating(function(Opportunity $model){
            $model->files = new Collection();
        });
    }
}
