<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::query();
        if ($user && $user->role_id != 1 && $user->user_type != '00') {
            $query->where('user_id', $user->id);
        }
        $tickets = $query->with('user')->latest()->paginate($request->get('per_page', 20));
        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'open',
        ]);

        return response()->json(['success' => true, 'message' => 'Ticket created successfully', 'data' => $ticket], 201);
    }

    public function show(Request $request, $id)
    {
        $ticket = Ticket::with('comments')->find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }
        $user = $request->user();
        if ($user && $user->role_id != 1 && $user->user_type != '00' && $ticket->user_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        return response()->json(['success' => true, 'data' => $ticket]);
    }

    public function update(Request $request, $id)
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }
        $user = $request->user();
        if ($user && $user->role_id != 1 && $user->user_type != '00' && $ticket->user_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $ticket->update($request->only(['subject', 'status']));
        return response()->json(['success' => true, 'message' => 'Ticket updated successfully', 'data' => $ticket]);
    }
}

