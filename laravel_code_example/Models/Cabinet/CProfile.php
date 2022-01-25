<?php

namespace App\Models\Cabinet;

use App\Enums\{AccountStatuses, AccountType, ComplianceLevel, ComplianceRequest,
    Country, CProfileStatuses, Industry, Language, LegalForm};
use App\Models\{Backoffice\BUser,
    BankAccountTemplate,
    CardAccountDetail,
    Commission,
    CryptoAccountDetail,
    Operation,
    RatesCategory,
    RateTemplate,
    Account};
use Illuminate\Database\Eloquent\Model;

/**
 * Class CProfile
 * @package App\Models
 * @property $id
 * @property $created_at
 * @property $updated_at
 * @property $account_type
 * @property $first_name
 * @property $last_name
 * @property $country
 * @property $company_name
 * @property $company_email
 * @property $company_phone
 * @property $industry_type
 * @property $legal_form
 * @property $beneficial_owner
 * @property $contact_email
 * @property $compliance_level
 * @property $status
 * @property $last_login
 * @property $manager_id
 * @property $refferal_of_user
 * @property $profile_id
 * @property $compliance_officer_id
 * @property $date_of_birth
 * @property $city
 * @property $citizenship
 * @property $zip_code
 * @property $address
 * @property $registration_number
 * @property $legal_address
 * @property $trading_address
 * @property $linkedin_link
 * @property $ceo_full_name
 * @property $interface_language
 * @property $currency_rate
 * @property $phone_verified
 * @property $registration_date
 * @property $status_change_text
 * @property $rates_category_id
 * @property $rate_template_id
 * @property $ref
 * @property $ip
 * @property CUser $cUser
 * @property BUser $manager
 * @property RatesCategory $ratesCategory
 * @property BUser $complianceOfficer
 * @property ComplianceRequest[] $complianceRequest
 * @property RateTemplate $rateTemplate
 * @property BankAccountTemplate[] $bankAccountTemplates
 * @property Account[] $accounts
 * @property Operation[] $operations
 */
class CProfile extends Model
{
    protected $guarded = []; //! temporary
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'id' => 'string',
    ];

    // @todo maybe Enum
    const STATUS_NEW = 0;
    const STATUS_PENDING_VERIFICATION = 1;
    const STATUS_READY_FOR_COMPLIANCE = 2;
    const STATUS_ACTIVE = 3;
    const STATUS_BANNED = 4;
    const STATUS_SUSPENDED = 5;
    const STATUS_DELETED = 6;

    // account type constants
    const TYPE_INDIVIDUAL = 1;
    const TYPE_CORPORATE = 2;

    const TYPES_LIST = [
        self::TYPE_INDIVIDUAL => 'enum_type_individual',
        self::TYPE_CORPORATE => 'enum_type_corporate',
    ];

     /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cUser()
    {
        return $this->hasOne(CUser::class, 'c_profile_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function manager()
    {
        return $this->hasOne(BUser::class, 'id', 'manager_id');
    }

    public function getManager()
    {
        return BUser::getBUser();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function ratesCategory()
    {
        return $this->hasOne(RatesCategory::class, 'id', 'rates_category_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function complianceOfficer()
    {
        return $this->hasOne(BUser::class, 'id', 'compliance_officer_id');
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Get status name with color
     * @return string
     */
    public function getStatusWithClass()
    {
        // @todo HTML в коде?
        if (isset(CProfileStatuses::NAMES[$this->status])) {
            return '<span class="text-' . CProfileStatuses::STATUS_CLASSES[$this->status] . '">' . CProfileStatuses::getName($this->status) . '</span>';
        }
        return '';
    }

    /**
     * get full name of profile
     * @return mixed|string
     */
    public function getFullName()
    {
        if ($this->account_type == self::TYPE_INDIVIDUAL) {
            return $this->first_name . ' ' . $this->last_name;
        }
        return $this->company_name;
    }

    /**
     * get full name of profile with verify image for cabinet menu
     * @return mixed|string
     */
    public function getFullNameInCabinet()
    {
        $name = $this->getFullName();
        if ($this->compliance_level != ComplianceLevel::VERIFICATION_NOT_VERIFIED) {
            $name .= ' <img src="'.config('cratos.urls.theme').'images/level-1.png">';
        }
        return $name;
    }

    /**
     * get full name of profile with verify image for cabinet menu
     * @return mixed|string
     */
    public function getVerificationName()
    {
        $levelName = '';
        $complianceLevelList = ComplianceLevel::getList();
        if (!empty($complianceLevelList[$this->compliance_level])) {
            $levelName = $complianceLevelList[$this->compliance_level];
            if ($this->compliance_level != ComplianceLevel::VERIFICATION_NOT_VERIFIED) {
                $levelName .= ' <img src="'.config('cratos.urls.theme').'images/level-1.png">';
            }
        }
        return $levelName;
    }

    /**
     * @return array|string|null
     */
    public function getCountryName()
    {
        return $this->country ? Country::getName($this->country) : '';
    }

    /**
     * @return array|string|null
     */
    public function getIndustryTypeName()
    {
        return $this->industry_type ? Industry::getName($this->industry_type) : '';
    }

    /**
     * @return array|string|null
     */
    public function getLegalFormName()
    {
        return $this->legal_form ? LegalForm::getName($this->legal_form) : '';
    }

    /**
     * @return array|string|null
     */
    public function getLanguageName()
    {
        return $this->interface_language ? Language::getName($this->interface_language) : '';
    }

    /**
     * @return array
     */
    public function getAllowedToChangeStatuses()
    {
        $statusesList = CProfileStatuses::getList();
        unset($statusesList[$this->status]);
        return $statusesList;
    }

    /**
     * returns Profile Compliance requests
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function complianceRequest()
    {
        return $this->hasMany(\App\Models\ComplianceRequest::class, 'c_profile_id', 'id');
    }

    /**
     * returns rate template relation
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function rateTemplate()
    {
        return $this->belongsTo(RateTemplate::class);
    }

    /**
     * returns Bank account temolates
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bankAccountTemplates()
    {
        return $this->hasMany(BankAccountTemplate::class, 'c_profile_id', 'id');
    }

    /**
     * checking if profile has pending compliance request
     * @return bool
     */
    public function hasPendingComplianceRequest()
    {
        return $this->complianceRequest()->where('status', ComplianceRequest::STATUS_PENDING)->exists();
    }

    /**
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function getPendingComplianceRequest()
    {
        return $this->complianceRequest()->where('status', ComplianceRequest::STATUS_PENDING)->first();
    }

    /**
     *  Returns last compliance request with approved status
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function lastApprovedComplianceRequest()
    {
        return $this->lastComplianceRequestByStatus(ComplianceRequest::STATUS_APPROVED);
    }

    /**
     *  Returns last compliance request with retry status
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function retryComplianceRequest()
    {
        return $this->lastComplianceRequestByStatus(ComplianceRequest::STATUS_RETRY);
    }

    /**
     * Returns profile last compliance request by status
     * @param int|null $status
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function lastComplianceRequestByStatus(?int $status = null)
    {
        $query = $this->complianceRequest();
        if ($status){
            $query->where('status', $status);
        }
        return $query->orderBy('updated_at', 'desc')->first();
    }

    /** returns cprofile pending compliance request
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function pendingComplianceRequest()
    {
        return $this->lastComplianceRequestByStatus(ComplianceRequest::STATUS_PENDING);
    }

    /** check if last compliance request was declined, if yes returns declined request
     * @return Model|\Illuminate\Database\Eloquent\Relations\HasMany|object|null
     */
    public function lastRequestIfDeclined()
    {
        $lastComplianceRequest = $this->lastComplianceRequestByStatus();
        if ($lastComplianceRequest && $lastComplianceRequest->status == ComplianceRequest::STATUS_DECLINED) {
            return $lastComplianceRequest;
        }
        return null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function cryptoAccountDetail()
    {
        return $this->hasManyThrough(CryptoAccountDetail::class, Account::class, 'c_profile_id', 'account_id', 'id')
            ->whereHas('account', function($q){
                $q->where('is_external', '!=', AccountType::ACCOUNT_EXTERNAL);
            });
    }

    public function cardAccountDetails()
    {
        return $this->hasManyThrough(CardAccountDetail::class, Account::class, 'c_profile_id', 'account_id', 'id');
    }

    /**
     * returns Commissions relation through rate templates
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function commissions()
    {
        return $this->hasManyThrough(Commission::class, RateTemplate::class, 'id',  'rate_template_id', 'rate_template_id');
    }

    /**
     * returns active Commissions
     */
    public function activeCommissions()
    {
        return $this->commissions()->where('is_active', 1);
    }

    /**
     * returns Commissions relation through rate templates
     * @param $commissionType
     * @param $type
     * @param $currency
     * @return Commission|null
     */
    public function operationCommission($commissionType,  $type, $currency): ?Commission
    {
        return $this->activeCommissions()
            ->where('currency', $currency)
            ->where('commission_type', $commissionType)
            ->where('type', $type)
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accounts()
    {
        return $this->hasMany(Account::class, 'c_profile_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function operations()
    {
        return $this->hasMany(Operation::class, 'c_profile_id', 'id');
    }

    /**
     * @param string $currency
     * @param int $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function accountByCurrencyType(string $currency,int $type)
    {
        return $this->accounts()
            ->whereNotNull('name')
            ->where('status', AccountStatuses::STATUS_ACTIVE)
            ->where('currency', $currency)
            ->where('account_type', $type)
            ->get();
    }

    /**
     * @return array
     */
    public function stepInfo()
    {
        $cUser = $this->cUser;
        // 1 step state
        if ($cUser->email_verified_at) {
            $stepState_1 = 'step-completed';
        }
        elseif (!$cUser->email_verified_at) {
            $stepState_1 = 'step-current';
        }
        else {
            $stepState_1 = 'step-next';
        }

        // 2 step state
        if ($this->status != \App\Enums\CProfileStatuses::STATUS_NEW && $this->status != \App\Enums\CProfileStatuses::STATUS_PENDING_VERIFICATION && $cUser->email_verified_at) {
            $stepState_2 = 'step-completed';
        }
        elseif ($cUser->email_verified_at && $this->status == \App\Enums\CProfileStatuses::STATUS_NEW || $this->status == \App\Enums\CProfileStatuses::STATUS_PENDING_VERIFICATION) {
            $stepState_2 = 'step-current';
        }
        else {
            $stepState_2 = 'step-next';
        }

        // 3 step state
        if ($this->compliance_level != \App\Enums\ComplianceLevel::VERIFICATION_NOT_VERIFIED && $cUser->email_verified_at) {
            $stepState_3 = 'step-completed';
        }
        elseif ($this->status != \App\Enums\CProfileStatuses::STATUS_NEW && $this->status != \App\Enums\CProfileStatuses::STATUS_PENDING_VERIFICATION
            && $this->compliance_level == \App\Enums\ComplianceLevel::VERIFICATION_NOT_VERIFIED) {
            $stepState_3 = 'step-current';
        }
        else {
            $stepState_3 = 'step-next';
        }

        return [
            'stepState_1' => $stepState_1,
            'stepState_2' => $stepState_2,
            'stepState_3' => $stepState_3
        ];
    }

    public function bankDetailAccounts()
    {
        return $this->accounts()
            ->whereNotNull('name')
            ->where('status', AccountStatuses::STATUS_ACTIVE)
            ->whereIn('account_type', [AccountType::TYPE_WIRE_SEPA, AccountType::TYPE_WIRE_SWIFT]);
    }
}
