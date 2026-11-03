<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\WorkshopEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class WorkshopEventController extends Controller
{
    public function active()
    {
        try {
            $events = WorkshopEvent::query()
                ->where('status', 'active')
                ->orderBy('start_date')
                ->orderBy('id')
                ->get()
                ->map(fn (WorkshopEvent $event): array => $this->present($event));

            return response()->json(['success' => true, 'data' => $events]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load workshop details.',
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = WorkshopEvent::query()->withCount('registrations');

            if ($request->filled('status') && in_array($request->input('status'), ['active', 'inactive'], true)) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($builder) use ($search): void {
                    $builder->where('workshop_code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            }

            $events = $query->orderByDesc('created_at')
                ->paginate(min(max((int) $request->input('per_page', 10), 1), 50));
            $events->getCollection()->transform(fn (WorkshopEvent $event): array => $this->present($event));

            return response()->json([
                'success' => true,
                'data' => $events->items(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
                'stats' => [
                    'total' => WorkshopEvent::query()->count(),
                    'active' => WorkshopEvent::query()->where('status', 'active')->count(),
                    'inactive' => WorkshopEvent::query()->where('status', 'inactive')->count(),
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load workshop events.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->merge([
            'workshop_code' => strtoupper(trim((string) $request->input('workshop_code'))),
            'currency' => strtoupper(trim((string) $request->input('currency'))),
        ]);
        $validated = $request->validate($this->rules());
        $newImagePath = null;
        $saved = false;

        try {
            $newImagePath = $request->file('qr_image')->store('workshop-events/qr', 'public');
            if (! $newImagePath) {
                throw new RuntimeException('Unable to store the workshop QR image.');
            }
            $eventAttributes = array_diff_key($validated, ['qr_image' => true]);
            $event = DB::transaction(function () use ($eventAttributes, $newImagePath): WorkshopEvent {
                return WorkshopEvent::query()->create([
                    ...$eventAttributes,
                    'qr_image_path' => $newImagePath,
                ]);
            });
            $saved = true;

            return response()->json([
                'success' => true,
                'message' => 'Workshop event created successfully.',
                'data' => $this->present($event),
            ], 201);
        } catch (Throwable $exception) {
            if ($newImagePath && ! $saved) {
                try {
                    if (! Storage::disk('public')->delete($newImagePath)) {
                        report(new RuntimeException('Unable to remove the workshop QR image after creation failed.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create the workshop event.',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $event = WorkshopEvent::query()->findOrFail($id);
        $request->merge([
            'workshop_code' => strtoupper(trim((string) $request->input('workshop_code'))),
            'currency' => strtoupper(trim((string) $request->input('currency'))),
        ]);
        $validated = $request->validate($this->rules($event->id, $request->hasFile('qr_image')));
        $oldImagePath = $event->qr_image_path;
        $newImagePath = null;
        $saved = false;

        try {
            if ($request->hasFile('qr_image')) {
                $newImagePath = $request->file('qr_image')->store('workshop-events/qr', 'public');
                if (! $newImagePath) {
                    throw new RuntimeException('Unable to store the replacement workshop QR image.');
                }
            }

            $event = DB::transaction(function () use ($event, $validated, $newImagePath): WorkshopEvent {
                $event->fill(array_diff_key($validated, ['qr_image' => true]));
                if ($newImagePath) {
                    $event->qr_image_path = $newImagePath;
                }
                $event->save();

                return $event->refresh();
            });
            $saved = true;

            if ($newImagePath && $oldImagePath) {
                try {
                    if (! Storage::disk('public')->delete($oldImagePath)) {
                        report(new RuntimeException('Unable to remove the replaced workshop QR image.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Workshop event updated successfully.',
                'data' => $this->present($event),
            ]);
        } catch (Throwable $exception) {
            if ($newImagePath && ! $saved) {
                try {
                    if (! Storage::disk('public')->delete($newImagePath)) {
                        report(new RuntimeException('Unable to remove the replacement workshop QR image after update failed.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the workshop event.',
            ], 500);
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        try {
            $event = WorkshopEvent::query()->findOrFail($id);
            $event->status = $validated['status'];
            $event->save();

            return response()->json([
                'success' => true,
                'message' => 'Workshop event status updated successfully.',
                'data' => $this->present($event),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the workshop event status.',
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $event = WorkshopEvent::query()->withCount('registrations')->findOrFail($id);
            if ($event->registrations_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This event has registrations and cannot be deleted. Set it to inactive instead.',
                ], 422);
            }

            $imagePath = $event->qr_image_path;
            DB::transaction(fn () => $event->delete());
            if ($imagePath) {
                try {
                    if (! Storage::disk('public')->delete($imagePath)) {
                        report(new RuntimeException('Unable to remove the deleted workshop QR image.'));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            return response()->json(['success' => true, 'message' => 'Workshop event deleted successfully.']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete the workshop event.',
            ], 500);
        }
    }

    private function rules(?int $eventId = null, bool $imageOptional = false): array
    {
        return [
            'workshop_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('workshop_events', 'workshop_code')->ignore($eventId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'registration_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'qr_image' => [$imageOptional ? 'nullable' : 'required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    private function present(WorkshopEvent $event): array
    {
        return [
            'id' => $event->id,
            'workshop_code' => $event->workshop_code,
            'title' => $event->title,
            'description' => $event->description,
            'start_date' => $event->start_date?->format('Y-m-d'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'currency' => $event->currency,
            'registration_fee' => (string) $event->registration_fee,
            'qr_image_path' => $event->qr_image_path,
            'qr_image_url' => Storage::disk('public')->url($event->qr_image_path),
            'status' => $event->status,
            'registrations_count' => $event->registrations_count ?? null,
            'created_at' => $event->created_at?->toISOString(),
        ];
    }
}
