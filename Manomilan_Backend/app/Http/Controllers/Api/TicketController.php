<?php

namespace App\Http\Controllers\Api;

use App\Application;
use App\Http\Controllers\Controller;
use App\Module;
use App\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Ticket::with(['application', 'module', 'items', 'comments']);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $tickets = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required_without:description', 'nullable', 'string', 'max:5000'],
            'description' => ['required_without:message', 'nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:50'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
        ]);

        $applicationId = $data['application_id'] ?? Application::query()->value('id');
        $moduleId = $data['module_id'] ?? Module::query()->value('id');

        if (! $applicationId || ! $moduleId) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticketing is not configured yet. Please contact the administrator.',
            ], 422);
        }

        try {
            $ticket = DB::transaction(function () use ($data, $applicationId, $moduleId, $request) {
                return Ticket::create([
                    'ticket_no' => 'TKT-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                    'application_id' => $applicationId,
                    'module_id' => $moduleId,
                    'user_id' => $request->user()->id,
                    'subject' => $data['subject'],
                    'description' => $data['description'] ?? $data['message'],
                    'category' => $data['category'] ?? 'General',
                    'ticket_status' => 'Open',
                    'status' => 1,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully.',
                'data' => $ticket,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create the ticket right now.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $ticket = Ticket::with(['application', 'module', 'items', 'comments'])->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $user = $request->user();
        if (! $user->isAdmin() && (int) $ticket->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return response()->json(['success' => true, 'data' => $ticket]);
    }

    public function update(Request $request, $id)
    {
        $ticket = Ticket::find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $user = $request->user();
        if (! $user->isAdmin() && (int) $ticket->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['Open', 'In Progress', 'Resolved', 'Closed'])],
        ]);

        try {
            DB::transaction(function () use ($ticket, $data) {
                if (isset($data['subject'])) {
                    $ticket->subject = $data['subject'];
                }
                if (isset($data['status'])) {
                    $ticket->ticket_status = $data['status'];
                }
                $ticket->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully.',
                'data' => $ticket->fresh(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the ticket right now.',
            ], 500);
        }
    }
}
