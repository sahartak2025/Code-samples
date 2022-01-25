<?php


namespace App\Services;


use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Enums\TicketMessages;
use App\Enums\TicketStatuses;
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Models\Backoffice\BUser;
use App\Models\Cabinet\CUser;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketService
{
    public function store($data, $creator, $to)
    {
        $ticket = Ticket::create([
            'id' => Str::uuid()->toString(),
            'subject' => $data['subject'],
            'status' => TicketStatuses::STATUS_NEW,
            'question' => $data['question'],
            'to_client' => $to,
            'ticketable_id' => $creator->id,
            'ticketable_type' => get_class($creator),
        ]);
        $ticket = Ticket::find($ticket->id);
        if (get_class($creator) == CUser::class) {
            EmailFacade::sendNewTicket($creator, $ticket->ticket_id);
            ActivityLogFacade::saveLog(LogMessage::USER_NEW_TICKET_ADDED_BACKOFFICE, ['id' => $ticket->ticket_id],
                LogResult::RESULT_SUCCESS, LogType::TYPE_NEW_TICKET_ADDED_CABINET, null, $ticket->to_client);
        } else if (get_class($creator) == BUser::class) {
            EmailFacade::sendNewTicketClient($creator, (new CUserService)->findById($to), $ticket->ticket_id);
            ActivityLogFacade::saveLog(LogMessage::USER_NEW_TICKET_ADDED_BACKOFFICE, ['id' => $ticket->ticket_id],
                LogResult::RESULT_SUCCESS, LogType::TYPE_NEW_TICKET_ADDED_BACKOFFICE, null, $ticket->to_client);
        }
        return $ticket;
    }

    public function getTicketById($id, $toClient)
    {
        $ticket = Ticket::with(['messages' => function($q) {
            $q->with('massageable')->orderByDesc('created_at');
        }])->where(['id' => $id, 'to_client' => $toClient])->first();
        if ($ticket) {
            $ticket->status = TicketStatuses::getName($ticket->status);
        }
        return $ticket;
    }

    public function closeTicket($id)
    {
        $ticket = Ticket::where(['id' => $id])->first();
        if ($ticket) {
            $ticket->update(['status' => TicketStatuses::STATUS_CLOSE]);
            $this->closeTicketLog($ticket);
            return $ticket;
        }
        return null;
    }

    private function closeTicketLog($ticket)
    {
        if (get_class(auth()->user()) == CUser::class) {
            ActivityLogFacade::saveLog(LogMessage::USER_TICKET_CLOSED_BACKOFFICE, ['id' => $ticket->ticket_id],
                LogResult::RESULT_SUCCESS, LogType::TYPE_TICKET_CLOSED_CABINET, null, $ticket->to_client);
        } else if (get_class(auth()->user()) == BUser::class) {
            ActivityLogFacade::saveLog(LogMessage::USER_TICKET_CLOSED_BACKOFFICE, ['id' => $ticket->ticket_id],
                LogResult::RESULT_SUCCESS, LogType::TYPE_TICKET_CLOSED_BACKOFFICE, null, $ticket->to_client);
        }
    }

    public function getActiveTicketsCount()
    {
        $newTickets = Ticket::where(['status' => TicketStatuses::STATUS_NEW, 'to_client' => auth()->id()])->count();
        $openedTicklets = Ticket::where(['status' => TicketStatuses::STATUS_OPEN, 'to_client' => auth()->id()])
            ->whereHas('messages', function ($q) {
                $q->where('viewed', false)
                    ->where('massageable_type', '!=', get_class(auth()->user()))
                    ->where('massageable_id', '!=', auth()->id());
            })->count();
        return $newTickets + $openedTicklets;
    }

    public function getClosedTicketsCount()
    {
        return Ticket::where(['status' => TicketStatuses::STATUS_CLOSE, 'to_client' => auth()->id()])->count();
    }

    public function getBackofficeOpernTicketsCount()
    {
        return Ticket::where('status', TicketStatuses::STATUS_OPEN)
            ->whereHas('messages', function ($q){
                $q->where('viewed', false)
                    ->where('massageable_type', '!=', get_class(auth()->user()))
                    ->where('massageable_id', '!=', auth()->id());;
            })->count();
    }

    public function getBackofficeClosedTicketsCount()
    {
        return Ticket::where('status', TicketStatuses::STATUS_CLOSE)->where('ticketable_type', '!=', BUser::class)->count();
    }

    public function getBackofficeNewTicketsCount()
    {
        return Ticket::where('status', TicketStatuses::STATUS_NEW)->where('ticketable_type', '!=', BUser::class)->count();
    }

    public function getBackofficeActiveTicketsCount()
    {
        $newTickets = Ticket::where('status', TicketStatuses::STATUS_NEW)->count();
        $openedTicklets = Ticket::where('status', TicketStatuses::STATUS_OPEN)
            ->whereHas('messages', function ($q) {
                $q->where('viewed', false)
                    ->where('massageable_type', '!=', get_class(auth()->user()))
                    ->where('massageable_id', '!=', auth()->id());
            })->count();
        return $newTickets + $openedTicklets;
    }

    public function ticketsPaginate($request, $status)
    {
        $queryTicket = Ticket::where('status', $status);
        $this->builder($queryTicket, $request);
        return $queryTicket->orderBy('created_at', 'desc')->paginate(config('cratos.pagination.tickets'));
    }

    private function builder($builder, $request)
    {
        $profile = (new CProfileService())->getCProfileByProfileId($request->client);
        if ($request->client && $profile) {
            $builder->where('ticketable_id', $profile->cUser->id);
        }
        if ($request->number) {
            $builder->where('ticket_id', $request->number);
        }
        if ($request->dateFrom) {
            $builder->where('created_at', '>=', $request->dateFrom . ' 00:00:00');
        }
        if ($request->dateTo) {
            $builder->where('created_at', '<=', $request->dateTo . ' 23:59:59');
        }
        return $builder;
    }

    public function changeStatus($ticketId, $status)
    {
        $ticket = Ticket::find($ticketId);
        if ($ticket) {
            $ticket->update(['status' => $status]);
        }
    }

}
