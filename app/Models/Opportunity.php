<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
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

    /**
     * @var Collection
     */
    private $filesArray;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->initFiles();
    }

    /**
     * Initiate the file array
     */
    public function initFiles()
    {
        $this->filesArray = new Collection($this->files);
    }

    /**
     * @return Collection
     */
    public function getFilesList(): Collection
    {
        if (empty($this->filesArray) || !$this->filesArray->isNotEmpty()) {
            $this->filesArray = $this->getFilesAttribute();
        }
        return $this->filesArray;
    }

    /**
     * Add file to collection
     *
     * @param string|array|PhotoSize $file
     */
    public function addFile($file = null)
    {
        $this->filesArray->add($file);
    }

    /**
     * Check if there is file in collection
     *
     * @return bool
     */
    public function hasFile(): bool
    {
        return $this->filesArray ? $this->filesArray->isNotEmpty() : false;
    }

    /**
     * Get the files property
     *
     * @return Collection
     */
    public function getFilesAttribute(): Collection
    {
        if (is_string($this->files) && strlen($this->files) > 0) {
            return collect(json_decode($this->files));
        }
        return $this->files;
    }

    /**
     * Before save/update
     */
    public static function boot(): void
    {
        parent::boot();

        static::creating(function (Opportunity $opportunity) {
            if ($opportunity->filesArray->isNotEmpty()) {
                $opportunity->files = optional($opportunity->filesArray)->toJson();
            }
        });

        static::updating(function (Opportunity $opportunity) {
            if ($opportunity->filesArray->isNotEmpty()) {
                $opportunity->files = optional($opportunity->filesArray)->toJson();
            }
        });
    }
}
