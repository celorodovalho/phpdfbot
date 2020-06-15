<?php

namespace App\Repositories;

use App\Contracts\Repositories\OpportunityRepository;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Class OpportunityRepositoryEloquent
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityRepositoryEloquent extends BaseRepository implements OpportunityRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return Opportunity::class;
    }

    /**
     * Specify Validator class name
     *
     * @return mixed
     */
    public function validator(): string
    {
        return CollectedOpportunityValidator::class;
    }

    /**
     * Boot up the repository, pushing criteria
     *
     * @throws RepositoryException
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function make(array $data): Opportunity
    {
        $opportunity = $this->model->newInstance([
            Opportunity::TITLE => $data[Opportunity::TITLE],
            Opportunity::ORIGINAL => $data[Opportunity::ORIGINAL],
        ]);

        $opportunity->{Opportunity::DESCRIPTION} = $data[Opportunity::DESCRIPTION];
        $opportunity->{Opportunity::FILES} = new Collection($data[Opportunity::FILES]);
        $opportunity->{Opportunity::POSITION} = $data[Opportunity::POSITION];
        $opportunity->{Opportunity::COMPANY} = $data[Opportunity::COMPANY];
        $opportunity->{Opportunity::LOCATION} = mb_strtoupper($data[Opportunity::LOCATION]);
        $opportunity->{Opportunity::TAGS} = new Collection($data[Opportunity::TAGS]);
        $opportunity->{Opportunity::SALARY} = $data[Opportunity::SALARY];
        $opportunity->{Opportunity::URLS} = new Collection($data[Opportunity::URLS]);
        $opportunity->{Opportunity::ORIGIN} = new Collection($data[Opportunity::ORIGIN]);
        $opportunity->{Opportunity::EMAILS} = new Collection($data[Opportunity::EMAILS]);
        $opportunity->{Opportunity::STATUS} = Opportunity::STATUS_INACTIVE;

        $opportunity->save();

        return $opportunity;
    }

    /**
     * @param array $data
     * @return Opportunity
     * @throws ValidatorException
     */
    public function createOpportunity(array $data): Opportunity
    {
        $original = SanitizerHelper::sanitizeBody($data[Opportunity::DESCRIPTION] ?? null);

        $files = [];
        /** @var UploadedFile $file */
        if (($file = $data[Opportunity::FILES] ?? null)
            && $file instanceof UploadedFile
            && !($file->getSize() < 50000 && strpos($file->getMimeType(), 'image') !== false)
        ) {
            $fileName = Helper::base64UrlEncode($file->getClientOriginalName())
                . '.' . $file->getClientOriginalExtension();
            $filePath = $file->move(
                'uploads',
                $fileName
            );
            $files[$filePath->getPathname()] = Helper::cloudinaryUpload($filePath);
            File::delete($filePath->getPathname());
        }

        $annotations = '';
        if (filled($files)) {
            $files = array_values($files);

            foreach ($files as $file) {
                if ($annotation = Helper::getImageAnnotation($file)) {
                    $annotations .= $annotation."\n\n";
                }
            }

            if (filled($annotations)) {
                $annotations = "\nTranscrição das imagens:\n" . $annotations;
            }
        }

        $description = SanitizerHelper::sanitizeBody($original . $annotations);

        $opportunity = [
            Opportunity::TITLE => SanitizerHelper::sanitizeSubject(Str::limit($data[Opportunity::TITLE], 50)),
            Opportunity::DESCRIPTION => $description,
            Opportunity::ORIGINAL => $original,
            Opportunity::FILES => filled($files) ? $files : null,
            Opportunity::URLS =>
                ExtractorHelper::extractUrls(
                    $data[Opportunity::URLS] ?? '' . ' ' . $description
                ),
            Opportunity::ORIGIN => [
                'type' => 'web',
                'ip' => $data[Opportunity::ORIGIN],
            ],
            Opportunity::LOCATION => implode(' / ', ExtractorHelper::extractLocation($data[Opportunity::LOCATION])),
            Opportunity::TAGS => ExtractorHelper::extractTags(json_encode($data)),
            Opportunity::EMAILS => SanitizerHelper::replaceMarkdown($data[Opportunity::EMAILS] ?? ''),
            Opportunity::POSITION => $data[Opportunity::POSITION],
            Opportunity::SALARY => $data[Opportunity::SALARY],
            Opportunity::COMPANY => $data[Opportunity::COMPANY],
        ];

        $this->validator->with($opportunity)->passesOrFail(ValidatorInterface::RULE_CREATE);

        /** @var \Illuminate\Database\Eloquent\Collection $hasOpportunities */
        $hasOpportunities = $this->scopeQuery(static function ($query) {
            return $query->withTrashed();
        })->findWhere([
            Opportunity::TITLE => $opportunity[Opportunity::TITLE],
            Opportunity::DESCRIPTION => $opportunity[Opportunity::DESCRIPTION],
        ]);

        if ($hasOpportunities->isEmpty()) {
            return $this->make($opportunity);
        }
        $firstOpportunity = $hasOpportunities->first();
        $firstOpportunity->update($opportunity);
        $firstOpportunity->restore();
        return $hasOpportunities->first();
    }
}
