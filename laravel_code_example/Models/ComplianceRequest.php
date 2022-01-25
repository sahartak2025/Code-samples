<?php

namespace App\Models;

use App\Models\Cabinet\CProfile;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ComplianceRequest
 * @package App\Models
 * @property $id
 * @property $c_profile_id
 * @property $compliance_level
 * @property $status
 * @property $message
 * @property $applicant_id
 * @property $created_at
 * @property $updated_at
 * @property $context_id
 * @property Operation $operation
 * @property CProfile $cProfile
 */
class ComplianceRequest  extends Model
{
    protected $guarded = []; //! temporary
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];
    
    /**
     * find Compliance request by appilicant id and status
     * @param string $applicantId
     * @param int|null $status
     * @return ComplianceRequest|null
     */
    public static function findByApplicantId(string $applicantId, ?int $status = null): ?self
    {
        return static::findByApplicantIdQuery($applicantId, $status)->orderBy('compliance_level', 'desc')->orderBy('updated_at', 'desc')->first();
    }

    /**
     * Returns Applicant requests statuses as array
     * @param string $applicantId
     * @param int|null $status
     * @param int $limit
     * @param string|null $orderColumn
     * @param string|null $orderDirection
     * @return mixed
     */
    public static function findApplicantRequestStatuses(string $applicantId, ?int $status = null, $limit = 5, ?string $orderColumn = null, ?string $orderDirection = null)
    {
        return static::findByApplicantIdQuery($applicantId, $status, $limit, $orderColumn, $orderDirection)->pluck('status')->toArray();
    }
    
    /**
     * returns Compliance request by appilicant id and status query
     * @param string $applicantId
     * @param int|null $status
     * @param int $limit
     * @param string|null $orderColumn
     * @param string|null $orderDirection
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function findByApplicantIdQuery(string $applicantId, ?int $status = null, ?int $limit = null, ?string $orderColumn = null, ?string $orderDirection = null)
    {
        $query = static::query()->where('applicant_id', $applicantId);
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($limit) {
            $query->limit($limit);
        }
        if ($orderColumn && $orderDirection) {
            $query->orderBy($orderColumn, $orderDirection);
        }

        return $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cProfile()
    {
        return $this->hasOne(CProfile::class, 'id', 'c_profile_id');
    }

    /**
     * Returns SumSub client verification pdf report url
     * @return string
     */
    public function getPdfUrl() {
        return config('cratos.sum_sub.api_url') . "/checkus/#/applicants/{$this->applicant_id}/applicantReport";
    }
    /**
     * Returns SumSub client profile url
     * @return string
     */
    public function getApplicantUrl() {
        return config('cratos.sum_sub.api_url') . "/checkus/#/applicant/{$this->applicant_id}";
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function operation()
    {
        return $this->hasOne(Operation::class, 'compliance_request_id', 'id');
    }
}
