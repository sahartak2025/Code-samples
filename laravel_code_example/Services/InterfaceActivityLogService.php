<?php
namespace App\Services;

use App\Models\Log;
use Illuminate\Database\Eloquent\Model;

interface InterfaceActivityLogService
{
    /**
     * Generate new unique contextId and set to $contextId property
     * @return self
     */
    public function generateContextId(): self;

    /**
     * Returns current context id
     * @return string|null
     */
    public function getContextId(): ?string;

    /**
     * Set given context id to $contextId property
     * @param string $contextId
     * @return self
     */
    public function setContextId(string $contextId): self;

    /**
     * @param string $action
     * @return InterfaceActivityLogService
     */
    public function setAction(string $action): self;

    /**
     * @param int $type
     * @return InterfaceActivityLogService
     */
    public function setType(int $type): self;

    /**
     * @param array $replacements
     * @return InterfaceActivityLogService
     */
    public function setReplacements(array $replacements): self;

    /**
     * @param int $resultType
     * @return InterfaceActivityLogService
     */
    public function setResultType(int $resultType): self;

    /**
     * @param string $cUserId
     * @return InterfaceActivityLogService
     */
    public function setCUserId(string $cUserId): self;

    /**
     * @param int $level
     * @return InterfaceActivityLogService
     */
    public function setLevel(int $level): self;

    /**
     * set additional data to be stored as json
     * @param array $additionalData
     * @return InterfaceActivityLogService
     */
    public function setAdditionalData(array $additionalData): self;

    /**
     * Save log data to db and return model of Log
     * @return bool
     */
    public function log():bool;

}
