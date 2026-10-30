<?php

namespace App\Http\Controllers\Candidate;

use App\Models\CandidateNotification;
use Illuminate\Http\Request;
use Throwable;

class NotificationController extends CandidateController
{
    public function index(Request $request)
    {
        try {
            $user = $this->candidate($request);
            $filters = $request->validate([
                'filter' => ['nullable', 'in:all,unread,read'],
                'type' => ['nullable', 'string', 'max:50'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $query = CandidateNotification::where('user_id', $user->id);

            if (($filters['filter'] ?? 'all') === 'unread') {
                $query->whereNull('read_at');
            }
            if (($filters['filter'] ?? 'all') === 'read') {
                $query->whereNotNull('read_at');
            }
            if (! empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            $paginator = $query->latest()->paginate((int) ($filters['per_page'] ?? 12));
            $paginator->setCollection(
                $paginator->getCollection()->map(fn (CandidateNotification $notification) => $this->present($notification))
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'from' => $paginator->firstItem() ?: 0,
                        'to' => $paginator->lastItem() ?: 0,
                    ],
                    'unread_count' => CandidateNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load notifications right now.',
            ], 500);
        }
    }

    public function unreadCount(Request $request)
    {
        try {
            $user = $this->candidate($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => CandidateNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load your notification count right now.',
            ], 500);
        }
    }

    public function markRead(Request $request, int $id)
    {
        try {
            $user = $this->candidate($request);
            $notification = CandidateNotification::where('user_id', $user->id)->find($id);

            if (! $notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            if (! $notification->read_at) {
                $notification->read_at = now();
                $notification->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'data' => [
                    'notification' => $this->present($notification),
                    'unread_count' => CandidateNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update this notification right now.',
            ], 500);
        }
    }

    public function markAllRead(Request $request)
    {
        try {
            $user = $this->candidate($request);
            CandidateNotification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
                'data' => ['unread_count' => 0],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update your notifications right now.',
            ], 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $user = $this->candidate($request);
            $notification = CandidateNotification::where('user_id', $user->id)->find($id);

            if (! $notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification removed.',
                'data' => [
                    'unread_count' => CandidateNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                ],
            ]);
        } catch (Throwable $e) {
            $this->rethrowClientError($e);
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to remove this notification right now.',
            ], 500);
        }
    }

    private function present(CandidateNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data ?: [],
            'read' => $notification->read_at !== null,
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }
}
