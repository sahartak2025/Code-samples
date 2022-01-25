<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * @var Ticket|null
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'createdByClient' => $this->resource->createdByClient,
            'messages' => TicketMessageResource::collection($this->resource->messages),
            'question' => $this->resource->question,
            'status' => $this->resource->status,
            'subject' => $this->resource->subject,
            'ticket_id' => $this->resource->ticket_id,
            'to_client' => $this->resource->to_client,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
