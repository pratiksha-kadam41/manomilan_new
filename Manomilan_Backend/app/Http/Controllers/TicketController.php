<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Application;
use App\Module;
use App\Ticket;
use App\TicketItem;
use App\TicketStatusHistory;

class TicketController extends Controller
{
    /**
     * Display all active tickets of logged in user.
     */
    public function index(Request $request)
    {
        $query = Ticket::with(['module', 'statusHistory'])
            ->where('user_id', auth()->id())
            ->where('status', 1);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('ticket_status', 'like', '%' . $search . '%');
            });
        }

        $tickets = $query->orderBy('id', 'DESC')->paginate(10);

        return view('software_tickets.index', compact('tickets'));
    }

    /**
     * Display all inactive tickets of logged in user.
     */
    public function inactive(Request $request)
    {
        $query = Ticket::with(['module', 'statusHistory'])
            ->where('user_id', auth()->id())
            ->where('status', 0);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_no', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('ticket_status', 'like', '%' . $search . '%');
            });
        }

        $tickets = $query->orderBy('id', 'DESC')->paginate(10);

        return view('software_tickets.inactive', compact('tickets'));
    }

    /**
     * Raise Ticket Page
     */
    public function create()
    {
        $application = Application::where('app_code', 'Manomilan')->first();
        if (!$application) {
            $application = Application::first();
        }

        $modules = $application ? Module::where('application_id', $application->id)
            ->where('status', 1)
            ->orderBy('module_name')
            ->get() : collect();

        return view('software_tickets.create', compact(
            'application',
            'modules'
        ));
    }

    /**
     * Store Ticket
     */
    public function store(Request $request)
    {
        $request->validate([
            'module_id' => 'required|exists:modules,id',
            'subject' => 'required|max:255',
            'category' => 'required',
            'common_description' => 'nullable',
            'common_attachment' => 'nullable|file|max:10240',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|max:255',
            'items.*.description' => 'required',
            'items.*.item_type' => 'required',
            'items.*.priority' => 'required',
            'items.*.severity' => 'required',
            'items.*.attachment' => 'nullable|file|max:10240',
        ]);

        $createAttempts = 0;
        $maxCreateAttempts = 5;

        try {

            $application = Application::where('app_code', 'Manomilan')->first();
            if (!$application) {
                $application = Application::first();
            }

            $commonAttachment = null;
            if ($request->hasFile('common_attachment')) {
                $file = $request->file('common_attachment');
                $name = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/tickets'), $name);
                $commonAttachment = 'uploads/tickets/' . $name;
            }

            $year = date('Y');
            $prefix = 'TKT-' . $year . '-';
            $ticketNo = $this->generateTicketNo();
            $ticket = null;

            DB::beginTransaction();

            try {

                while ($createAttempts < $maxCreateAttempts) {
                    try {
                        $ticket = Ticket::create([
                            'ticket_no' => $ticketNo,
                            'application_id' => isset($application) ? $application->id : null,
                            'module_id' => $request->module_id,
                            'user_id' => auth()->id(),
                            'subject' => $request->subject,
                            'description' => $request->common_description ?? '',
                            'category' => $request->category,
                            'ticket_status' => 'Open',
                            'attachment' => $commonAttachment,
                            'status' => 1,
                        ]);
                        break;
                    } catch (\Illuminate\Database\QueryException $qe) {
                        if ($createAttempts < $maxCreateAttempts - 1 &&
                            $qe->errorInfo && isset($qe->errorInfo[1]) && (int) $qe->errorInfo[1] === 1062) {
                            $currentNum = (int) substr($ticketNo, -6);
                            $ticketNo = $prefix . str_pad($currentNum + 1, 6, '0', STR_PAD_LEFT);
                            $createAttempts++;
                            usleep(100000);
                            continue;
                        }
                        throw $qe;
                    }
                }

                if (!$ticket) {
                    throw new \RuntimeException('Failed to generate a unique ticket number after several attempts.');
                }

                // Create ticket items
                foreach ($request->items as $itemData) {
                    $itemAttachment = null;
                    if (isset($itemData['attachment']) && $itemData['attachment']) {
                        $file = $itemData['attachment'];
                        $name = time() . '_' . $file->getClientOriginalName();
                        $file->move(public_path('uploads/tickets'), $name);
                        $itemAttachment = 'uploads/tickets/' . $name;
                    }

                    $item = TicketItem::create([
                        'ticket_id' => $ticket->id,
                        'attachment' => $itemAttachment,
                        'title' => $itemData['title'],
                        'description' => $itemData['description'],
                        'item_type' => $itemData['item_type'],
                        'priority' => $itemData['priority'],
                        'severity' => $itemData['severity'],
                        'ticket_status' => 'Open',
                        'status' => 1,
                    ]);

                    // Initial status history for each item
                    TicketStatusHistory::create([
                        'ticket_id' => $ticket->id,
                        'ticket_item_id' => $item->id,
                        'old_status' => 'Open',
                        'new_status' => 'Open',
                        'remarks' => 'Ticket Item Created',
                        'changed_by' => auth()->id(),
                        'created_at' => now(),
                    ]);
                }

                // Refresh ticket items and check status
                $ticket->refresh();
                $this->updateTicketStatusIfAllItemsResolved($ticket);

                DB::commit();

            } catch (\Exception $innerException) {
                DB::rollback();
                throw $innerException;
            }

            return redirect()
                ->route('tickets.index')
                ->with('success', 'Ticket Raised Successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Ticket Details
     */
    public function show($id)
    {
        $ticket = Ticket::with([
            'module',
            'items',
            'comments',
            'statusHistory'
        ])->findOrFail($id);

        return view('software_tickets.show', compact('ticket'));
    }

    /**
     * Edit Ticket
     */
    public function edit($id)
    {
        $ticket = Ticket::with(['items'])->findOrFail($id);

        if ($ticket->ticket_status != 'Open') {
            return redirect()->route('tickets.index')->with('error', 'You can only edit tickets with Open status.');
        }

        $application = Application::where('app_code', 'Manomilan')->first();
        if (!$application) {
            $application = Application::first();
        }

        $modules = $application ? Module::where('application_id', $application->id)
            ->where('status', 1)
            ->orderBy('module_name')
            ->get() : collect();

        return view('software_tickets.edit', compact('ticket', 'modules'));
    }

    /**
     * Update Ticket
     */
    public function update(Request $request, $id)
    {
        // If we're just reactivating
        if ($request->has('status') && $request->status == 1) {
            $ticket = Ticket::findOrFail($id);
            $ticket->update(['status' => 1]);
            return redirect()->route('tickets.inactive')->with('success', 'Ticket Activated Successfully.');
        }

        $ticket = Ticket::findOrFail($id);
        if ($ticket->ticket_status != 'Open') {
            return redirect()->route('tickets.index')->with('error', 'You can only edit tickets with Open status.');
        }

        $request->validate([
            'module_id' => 'required|exists:modules,id',
            'subject' => 'required|max:255',
            'category' => 'required',
            'common_description' => 'nullable',
            'common_attachment' => 'nullable|file|max:10240',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|max:255',
            'items.*.description' => 'required',
            'items.*.item_type' => 'required',
            'items.*.priority' => 'required',
            'items.*.severity' => 'required',
            'items.*.attachment' => 'nullable|file|max:10240',
        ]);

        DB::beginTransaction();

        try {

            $ticket = Ticket::findOrFail($id);

            $commonAttachment = $ticket->attachment;
            if ($request->hasFile('common_attachment')) {
                // Delete old attachment if exists
                if ($ticket->attachment && file_exists(public_path($ticket->attachment))) {
                    unlink(public_path($ticket->attachment));
                }
                $file = $request->file('common_attachment');
                $name = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/tickets'), $name);
                $commonAttachment = 'uploads/tickets/' . $name;
            }

            $ticket->update([
                'module_id' => $request->module_id,
                'subject' => $request->subject,
                'description' => $request->common_description ?? '',
                'category' => $request->category,
                'attachment' => $commonAttachment,
            ]);

            // Get existing item IDs
            $existingItemIds = $ticket->items->pluck('id')->toArray();
            $updatedItemIds = [];

            // Process each item
            foreach ($request->items as $itemData) {
                if (isset($itemData['id']) && in_array($itemData['id'], $existingItemIds)) {
                    // Update existing item
                    $item = TicketItem::find($itemData['id']);
                    $itemAttachment = $item->attachment;

                    if (isset($itemData['attachment']) && $itemData['attachment']) {
                        // Delete old item attachment if exists
                        if ($item->attachment && file_exists(public_path($item->attachment))) {
                            unlink(public_path($item->attachment));
                        }
                        $file = $itemData['attachment'];
                        $name = time() . '_' . $file->getClientOriginalName();
                        $file->move(public_path('uploads/tickets'), $name);
                        $itemAttachment = 'uploads/tickets/' . $name;
                    }

                    $item->update([
                        'title' => $itemData['title'],
                        'description' => $itemData['description'],
                        'item_type' => $itemData['item_type'],
                        'priority' => $itemData['priority'],
                        'severity' => $itemData['severity'],
                        'attachment' => $itemAttachment,
                    ]);
                    $updatedItemIds[] = $itemData['id'];
                } else {
                    // Create new item
                    $itemAttachment = null;
                    if (isset($itemData['attachment']) && $itemData['attachment']) {
                        $file = $itemData['attachment'];
                        $name = time() . '_' . $file->getClientOriginalName();
                        $file->move(public_path('uploads/tickets'), $name);
                        $itemAttachment = 'uploads/tickets/' . $name;
                    }

                    $item = TicketItem::create([
                        'ticket_id' => $ticket->id,
                        'attachment' => $itemAttachment,
                        'title' => $itemData['title'],
                        'description' => $itemData['description'],
                        'item_type' => $itemData['item_type'],
                        'priority' => $itemData['priority'],
                        'severity' => $itemData['severity'],
                        'ticket_status' => 'Open',
                        'status' => 1,
                    ]);
                }
            }

            // Delete items not in updated list
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            if (!empty($itemsToDelete)) {
                TicketItem::whereIn('id', $itemsToDelete)->delete();
            }

            // Refresh ticket items and check status
            $ticket->refresh();
            $this->updateTicketStatusIfAllItemsResolved($ticket);

            DB::commit();

            return redirect()
                ->route('tickets.show', $ticket)
                ->with('success', 'Ticket Updated Successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Deactivate Ticket
     */
    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->update(['status' => 0]);
        return redirect()->route('tickets.index')->with('success', 'Ticket Deactivated Successfully.');
    }

    /**
     * Update Ticket Item Status
     */
    public function updateItemStatus(Request $request, $ticketId, $itemId)
    {
        $request->validate([
            'ticket_status' => 'required|in:Open,In Progress,Resolved',
            'remarks' => 'nullable|string',
        ]);

        $ticket = Ticket::findOrFail($ticketId);
        $item = TicketItem::where('ticket_id', $ticketId)->findOrFail($itemId);

        $oldStatus = $item->ticket_status;

        DB::beginTransaction();
        try {
            $item->update([
                'ticket_status' => $request->ticket_status,
            ]);

            // Add status history
            TicketStatusHistory::create([
                'ticket_id' => $ticket->id,
                'ticket_item_id' => $item->id,
                'old_status' => $oldStatus,
                'new_status' => $request->ticket_status,
                'remarks' => $request->remarks ?? 'Status Updated',
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);

            // Check if all items are resolved and update ticket status
            $this->updateTicketStatusIfAllItemsResolved($ticket);

            DB::commit();

            return redirect()->route('tickets.show', $ticket)->with('success', 'Item status updated successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Check if all items are resolved and update ticket status accordingly
     */
    private function updateTicketStatusIfAllItemsResolved(Ticket $ticket)
    {
        $items = $ticket->items;
        $allResolved = $items->every(function ($item) {
            return $item->ticket_status === 'Resolved';
        });
        $anyInProgress = $items->contains(function ($item) {
            return $item->ticket_status === 'In Progress';
        });

        if ($allResolved) {
            $ticket->update(['ticket_status' => 'Resolved']);
        } elseif ($anyInProgress) {
            $ticket->update(['ticket_status' => 'In Progress']);
        } else {
            $ticket->update(['ticket_status' => 'Open']);
        }
    }

    /**
     * Generate Ticket Number
     * Uses MAX numeric suffix for current year + uniqueness retry loop
     * to avoid duplicate key errors from race conditions or out-of-order rows.
     */
    private function generateTicketNo()
    {
        $year = date('Y');
        $prefix = 'TKT-' . $year . '-';

        $maxNum = Ticket::where('ticket_no', 'like', $prefix . '%')
            ->max(DB::raw("CAST(SUBSTRING(ticket_no, -6) AS UNSIGNED)"));

        $nextNum = $maxNum ? ((int) $maxNum + 1) : 1;

        $attempts = 0;
        $maxAttempts = 20;

        while ($attempts < $maxAttempts) {
            $candidate = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $exists = Ticket::where('ticket_no', $candidate)->exists();
            if (!$exists) {
                return $candidate;
            }

            $nextNum++;
            $attempts++;
        }

        $randomSuffix = str_pad(random_int(900000, 999999), 6, '0', STR_PAD_LEFT);
        $candidate = $prefix . $randomSuffix;
        while (Ticket::where('ticket_no', $candidate)->exists()) {
            $randomSuffix = str_pad(random_int(900000, 999999), 6, '0', STR_PAD_LEFT);
            $candidate = $prefix . $randomSuffix;
        }

        return $candidate;
    }
}