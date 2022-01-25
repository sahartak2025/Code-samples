<?php

namespace App\Http\Resources;

use App\Models\TicketMessage;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{

    /**
     * @var TicketMessage
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'creatorName' => $this->resource->creatorName,
            'viewed' => $this->resource->viewed,
            'file' => $this->resource->file,
            'message' => nl2br($this->resource->message),
            'ticket_id' => $this->resource->ticket_id,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
