<?php

namespace App\Http\Controllers\Api;

use App\Application;
use App\Http\Controllers\Controller;
use App\Module;
use App\Ticket;
use App\TicketItem;
use App\TicketStatusHistory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    private const CATEGORIES = ['Requirement', 'Bug', 'Enhancement', 'Support', 'Other'];
    private const ITEM_TYPES = ['Development', 'Bug Fix', 'Enhancement', 'Support', 'Testing'];
    private const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];
    private const SEVERITIES = ['Low', 'Medium', 'High', 'Critical'];

    public function meta()
    {
        $application = Application::query()->where('app_code', 'Manomilan')->first()
            ?? Application::query()->orderBy('id')->first();

        $modules = $application
            ? Module::query()
                ->where('application_id', $application->id)
                ->where('status', true)
                ->orderBy('module_name')
                ->get(['id', 'module_name'])
            : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'modules' => $modules,
                'categories' => self::CATEGORIES,
                'item_types' => self::ITEM_TYPES,
                'priorities' => self::PRIORITIES,
                'severities' => self::SEVERITIES,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $active = (int) $request->input('active', 1) === 0 ? 0 : 1;
        $perPage = min(50, max(1, (int) $request->input('per_page', 10)));
        $search = trim((string) $request->input('search', ''));

        $query = Ticket::query()
            ->where('status', $active)
            ->with([
                'module:id,module_name',
                'items:id,ticket_id,ticket_status',
                'statusHistory' => fn ($history) => $history
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ]);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($search !== '') {
            $query->where(function ($tickets) use ($search) {
                $tickets->where('ticket_no', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhere('ticket_status', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhereHas('module', fn ($modules) => $modules->where('module_name', 'like', '%'.$search.'%'));
            });
        }

        $tickets = Ticket::withoutEvents(fn () => $query
            ->orderByDesc('id')
            ->paginate($perPage));

        $tickets->getCollection()->each(fn (Ticket $ticket) => $ticket->recalculateStatus());
        $tickets->getCollection()->transform(fn (Ticket $ticket) => [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'ticket_status' => $ticket->ticket_status,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at,
            'module' => $ticket->module
                ? ['id' => $ticket->module->id, 'module_name' => $ticket->module->module_name]
                : null,
            'status_history' => $ticket->statusHistory->map(fn (TicketStatusHistory $history) => [
                'id' => $history->id,
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'remarks' => $history->remarks,
                'created_at' => $history->created_at,
            ])->values(),
        ]);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function store(Request $request)
    {
        if ($request->has('items')) {
            return $this->storeWithItems($request);
        }

        return $this->storeLegacyTicket($request);
    }

    private function storeLegacyTicket(Request $request)
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

    private function storeWithItems(Request $request)
    {
        $data = $request->validate([
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
            'common_description' => ['nullable', 'string'],
            'common_attachment' => ['nullable', 'file', 'max:10240'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['required', 'string'],
            'items.*.item_type' => ['required', 'string', 'max:30'],
            'items.*.priority' => ['required', 'string', 'max:20'],
            'items.*.severity' => ['required', 'string', 'max:20'],
            'items.*.attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $user = $request->user();
        $application = Application::query()->where('app_code', 'Manomilan')->first()
            ?? Application::query()->orderBy('id')->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticketing is not configured yet. Please contact the administrator.',
            ], 422);
        }

        $savedFiles = [];
        try {
            $ticket = DB::transaction(function () use ($data, $request, $user, $application, &$savedFiles) {
                $ticketNo = $this->generateTicketNo();
                $prefix = 'TKT-'.now()->format('Y').'-';
                $suffix = (int) substr($ticketNo, -6);
                $ticket = null;
                $attempts = 0;

                while ($attempts < 5) {
                    try {
                        $ticket = Ticket::create([
                            'ticket_no' => $ticketNo,
                            'application_id' => $application->id,
                            'module_id' => $data['module_id'],
                            'user_id' => $user->id,
                            'subject' => $data['subject'],
                            'description' => $data['common_description'] ?? '',
                            'category' => $data['category'],
                            'ticket_status' => 'Open',
                            'attachment' => null,
                            'status' => 1,
                        ]);
                        break;
                    } catch (QueryException $exception) {
                        if ((int) ($exception->errorInfo[1] ?? 0) !== 1062 || $attempts >= 4) {
                            throw $exception;
                        }
                        $attempts++;
                        $suffix++;
                        $ticketNo = $prefix.str_pad((string) $suffix, 6, '0', STR_PAD_LEFT);
                        usleep(100000);
                    }
                }

                if (! $ticket) {
                    throw new \RuntimeException('Failed to generate a unique ticket number after several attempts.');
                }

                if ($request->hasFile('common_attachment')) {
                    $ticket->attachment = $this->storeAttachment($request->file('common_attachment'), $savedFiles);
                    $ticket->save();
                }

                foreach ($data['items'] as $itemData) {
                    $itemAttachment = isset($itemData['attachment'])
                        ? $this->storeAttachment($itemData['attachment'], $savedFiles)
                        : null;

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

                    TicketStatusHistory::create([
                        'ticket_id' => $ticket->id,
                        'ticket_item_id' => $item->id,
                        'old_status' => 'Open',
                        'new_status' => 'Open',
                        'remarks' => 'Ticket Item Created',
                        'changed_by' => $user->id,
                        'created_at' => now(),
                    ]);
                }

                TicketStatusHistory::create([
                    'ticket_id' => $ticket->id,
                    'ticket_item_id' => null,
                    'old_status' => 'Open',
                    'new_status' => 'Open',
                    'remarks' => 'Ticket Created',
                    'changed_by' => $user->id,
                    'created_at' => now(),
                ]);

                $this->recalculateTicketStatus($ticket);
                return $ticket;
            });

            $ticket->load(['application', 'module', 'items', 'comments', 'statusHistory']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket raised successfully.',
                'data' => $ticket,
            ], 201);
        } catch (Throwable $e) {
            foreach ($savedFiles as $path) {
                if (is_file($path)) {
                    File::delete($path);
                }
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Unable to create the ticket right now.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $ticket = Ticket::withoutEvents(fn () => Ticket::with([
            'application',
            'module',
            'items',
            'comments',
            'statusHistory' => fn ($history) => $history->orderBy('created_at')->orderBy('id'),
        ])->find($id));

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        if (! $this->canManageTicket($request, $ticket)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $ticket->recalculateStatus();
        $ticket->setAttribute('attachment_url', $ticket->attachment_url);
        $ticket->items->each(
            fn (TicketItem $item) => $item->setAttribute('attachment_url', $item->attachment_url)
        );

        return response()->json(['success' => true, 'data' => $ticket]);
    }

    public function update(Request $request, $id)
    {
        $ticket = Ticket::find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        if (! $this->canManageTicket($request, $ticket)) {
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

    public function destroy(Request $request, $id)
    {
        $ticket = Ticket::find($id);
        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }
        if (! $this->canManageTicket($request, $ticket)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $ticket->status = 0;
        $ticket->save();

        return response()->json(['success' => true, 'message' => 'Ticket deactivated successfully.']);
    }

    public function activate(Request $request, $id)
    {
        $ticket = Ticket::find($id);
        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }
        if (! $this->canManageTicket($request, $ticket)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $ticket->status = 1;
        $ticket->save();

        return response()->json(['success' => true, 'message' => 'Ticket activated successfully.']);
    }

    private function canManageTicket(Request $request, Ticket $ticket): bool
    {
        $user = $request->user();
        return $user->isAdmin() || (int) $ticket->user_id === (int) $user->id;
    }

    private function generateTicketNo(): string
    {
        $prefix = 'TKT-'.now()->format('Y').'-';
        $maxNumber = Ticket::query()
            ->where('ticket_no', 'like', $prefix.'%')
            ->max(DB::raw('CAST(SUBSTRING(ticket_no, -6) AS UNSIGNED)'));
        $nextNumber = (int) $maxNumber + 1;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $prefix.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
            if (! Ticket::query()->where('ticket_no', $candidate)->exists()) {
                return $candidate;
            }
            $nextNumber++;
        }

        throw new \RuntimeException('Failed to generate a unique ticket number after several attempts.');
    }

    private function storeAttachment(UploadedFile $file, array &$savedFiles): string
    {
        $directory = public_path('uploads/tickets');
        File::ensureDirectoryExists($directory);

        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = $originalName !== '' ? $originalName : 'attachment';
        $fileName = time().'_'.$originalName;
        if (is_file($directory.DIRECTORY_SEPARATOR.$fileName)) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $fileName = time().'_'.Str::slug($baseName).'-'.Str::random(8).($extension ? '.'.$extension : '');
        }

        $file->move($directory, $fileName);
        $savedFiles[] = $directory.DIRECTORY_SEPARATOR.$fileName;

        return 'uploads/tickets/'.$fileName;
    }

    private function recalculateTicketStatus(Ticket $ticket): void
    {
        $items = $ticket->items()->get(['id', 'ticket_id', 'ticket_status']);
        $allResolved = $items->every(fn (TicketItem $item) => $item->ticket_status === 'Resolved');
        $anyInProgress = $items->contains(fn (TicketItem $item) => $item->ticket_status === 'In Progress');

        $ticket->ticket_status = $allResolved
            ? 'Resolved'
            : ($anyInProgress ? 'In Progress' : 'Open');
        $ticket->save();
    }
}
