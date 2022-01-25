<?php


namespace App\Services;


use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Models\Backoffice\BUser;
use App\Models\Cabinet\CUser;
use App\Models\TicketMessage;
use Illuminate\Support\Str;

class TicketMessageService
{
    public function get()
    {
        return TicketMessage::all();
    }

    public function storeMessage($data, $creator)
    {
        $ticketMessage = TicketMessage::create([
            'id' => Str::uuid()->toString(),
            'message' => $data['message'],
            'ticket_id' => $data['ticket-id'],
            'massageable_id' => $creator->id,
            'massageable_type' => get_class($creator),
        ]);
        $ticketMessage = TicketMessage::find($ticketMessage->id);
        if (get_class($creator) == CUser::class) {
            EmailFacade::sendNewTicketMessage($creator, $ticketMessage->ticket->ticket_id);

            ActivityLogFacade::saveLog(LogMessage::USER_NEW_TICKET_MESSAGE_ADDED_BACKOFFICE, ['id' => $ticketMessage->ticket->ticket_id],
                LogResult::RESULT_SUCCESS, LogType::TYPE_NEW_TICKET_MESSAGE_ADDED_CABINET, null, $ticketMessage->ticket->to_client);
        } else if (get_class($creator) == BUser::class) {
            EmailFacade::sendNewTicketMessageClient($creator, (new CUserService)->findById($data['to']), $ticketMessage->ticket->ticket_id);
            ActivityLogFacade::saveLog(LogMessage::USER_NEW_TICKET_MESSAGE_ADDED_BACKOFFICE, ['id' => $ticketMessage->ticket->ticket_id],
                LogResult::RESULT_SUCCESS, LogType::TYPE_NEW_TICKET_MESSAGE_ADDED_BACKOFFICE, null, $ticketMessage->ticket->to_client);
        }
        return $ticketMessage;
    }

    public function storeTicketMessageFile($request, $ticketMessage, $ticket = false)
    {
        $file = null;
        if ($ticket) {
            $file = $request->file;
        } else {
            $file = $request->m_file;
        }
        $filename = $ticketMessage->id . '.' . $file->getClientOriginalExtension();
        $file->storeAs('/images/ticket-messages/', $filename);
        $ticketMessage->update(['file' => $filename]);
    }

    public function viewMessage($ticketId, $type)
    {
        TicketMessage::where(['ticket_id' => $ticketId, 'viewed' => false])->where('massageable_type', '!=', $type)->update(['viewed' => true]);
    }

    public function fileBelongsUser($file)
    {
        return TicketMessage::where('file', $file)->whereHas('ticket', function ($q){
            return $q->where('to_client', auth()->user()->id);
        })->first();
    }
}
