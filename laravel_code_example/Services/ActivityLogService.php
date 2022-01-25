<?php


namespace App\Services;


use App\Enums\LogLevel;
use App\Enums\LogType;
use App\Models\Log;
use http\Client\Request;
use http\Exception\InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use App\Enums\LogResult;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ActivityLogService implements InterfaceActivityLogService
{

    /* @property string $cUserId*/
    private $cUserId;

    /* @property string $contextId*/
    private $contextId;

    /* @property string $action translation string key from class LogMessage*/
    private $action;

    /* @property int $type from LogType class constants*/
    private $type;

    /* @property array $replacements*/
    private $replacements;

    /* @property int $resultType from LogResult class constants*/
    private $resultType = LogResult::RESULT_NEUTRAL;

    /* @property int $level from LogLevel class constants*/
    private $level = 'info';

    /* @property array $data */
    private $data = [];


    /**
     * Generate new unique contextId and set to $contextId property
     * @return self
     */
    public function generateContextId(): InterfaceActivityLogService
    {
        $this->contextId = Str::uuid();
        return $this;

    }

    /**
     * Returns current context id
     * @return string|null
     */
    public function getContextId(): ?string
    {
        if (!$this->contextId) {
            $this->generateContextId();
        }
        return $this->contextId;
    }

    /**
     * Set given context id to $contextId property
     * @param string $contextId
     * @return InterfaceActivityLogService
     */
    public function setContextId(string $contextId): InterfaceActivityLogService
    {
        $this->contextId = $contextId;
        return $this;

    }

    /**
     * @param string $action
     * @return InterfaceActivityLogService
     */
    public function setAction(string $action): InterfaceActivityLogService
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @param int $type
     * @return InterfaceActivityLogService
     */
    public function setType(int $type): InterfaceActivityLogService
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param array $replacements
     * @return InterfaceActivityLogService
     */
    public function setReplacements(array $replacements = []): InterfaceActivityLogService
    {
        $this->replacements = $replacements;
        return $this;
    }

    /**
     * @param int $resultType
     * @return InterfaceActivityLogService
     */
    public function setResultType(int $resultType): InterfaceActivityLogService
    {
        $this->resultType = $resultType;
        return $this;

    }

    /**
     * @param string $cUserId
     * @return InterfaceActivityLogService
     */
    public function setCUserId(string $cUserId): InterfaceActivityLogService
    {
        $this->cUserId = $cUserId;
        return $this;

    }

    /**
     * @return string|null
     */
    public function getCUserId(): ?string
    {
        if ($this->cUserId) {
            return $this->cUserId;
        }
        if (!in_array($this->type, LogType::USER_LOG_TYPES)) {
            return null;
        }
        return auth()->guard('cUser')->user()->id ?? null;
    }

    /**
     * @return string|null
     */
    public function getBUserId(): ?string
    {
        if (!in_array($this->type, LogType::MANAGER_LOG_TYPES)) {
            return null;
        }
        return auth()->guard('bUser')->user()->id ?? null;
    }

    /**
     * @param int $level
     * @return InterfaceActivityLogService
     */
    public function setLevel(int $level): InterfaceActivityLogService
    {
        $this->level = array_search($level, \App\Enums\LogLevel::LEVEL_BY_NAMES);
        if ($this->level === false) {
            throw new InvalidArgumentException('Invalid level '.$level);
        }
        return $this;

    }

    /**
     * set additional data to be stored as json
     * @param array $additionalData
     * @return InterfaceActivityLogService
     */
    public function setAdditionalData(array $additionalData): InterfaceActivityLogService
    {
        $this->data = $additionalData;
        return $this;

    }

    /**
     * Save log data to db and return model of Log
     * @return bool
     */
    public function log(): bool
    {
        $data = $this->data;
        $data['replacements'] = $this->replacements;
        $context = [
            'data' => $data,
            'type' => $this->type,
            'result' => $this->resultType,
            'c_user_id' => $this->getCUserId(),
            'b_user_id' => $this->getBUserId(),
            'context_id' => $this->getContextId() ?? $this->generateContextId()->getContextId(),
        ];
        if (!$this->action || !$this->type) {
            throw new InvalidArgumentException('missing properties');
        }
        logger()->channel(\App\Logging\CratosLogger::CHANNEL)->log($this->level, $this->action, $context);
        return true;
    }


    /**
     * Save Log
     * @param string $message
     * @param array $replacements
     * @param int $resultType
     * @param int $type
     * @param string|null $contextId
     */
    public function saveLog(string $message, array $replacements, int $resultType, int $type, string $contextId = null, $cUserId = null)
    {
        $this->setAction($message)
            ->setReplacements($replacements)
            ->setResultType($resultType)
            ->setType($type);
        if ($contextId) {
            $this->setContextId($contextId);
        }
        if ($cUserId) {
            $this->setCUserId($cUserId);
        }

        $this->log();
    }
}
