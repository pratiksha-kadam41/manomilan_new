<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MelavaCampaignController extends Controller
{
    public function active(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => DB::table('melava_campaigns')
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn ($campaign) => $this->present($campaign, $request)),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load active Melava events.',
            ], 500);
        }
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $today = now()->toDateString();

        $base = DB::table('melava_campaigns')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($search) use ($term) {
                    $search->where('melava_code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhere('description', 'like', '%' . $term . '%');
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status));

        $stats = (clone $base)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active")
            ->selectRaw("SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive")
            ->selectRaw('SUM(CASE WHEN start_date IS NOT NULL AND start_date > ? THEN 1 ELSE 0 END) AS upcoming', [$today])
            ->selectRaw("SUM(CASE WHEN start_date IS NULL THEN 1 ELSE 0 END) AS undated")
            ->selectRaw(
                'SUM(CASE WHEN start_date IS NOT NULL AND COALESCE(end_date, start_date) < ? THEN 1 ELSE 0 END) AS completed',
                [$today]
            )
            ->selectRaw(
                'SUM(CASE WHEN start_date IS NOT NULL AND start_date <= ? AND COALESCE(end_date, start_date) >= ? THEN 1 ELSE 0 END) AS ongoing',
                [$today, $today]
            )
            ->first();

        $campaigns = (clone $base)
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 20),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1)
            );

        return response()->json([
            'success' => true,
            'data' => $campaigns->getCollection()->map(fn ($campaign) => $this->present($campaign, $request))->values(),
            'pagination' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
                'from' => $campaigns->firstItem(),
                'to' => $campaigns->lastItem(),
            ],
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'active' => (int) ($stats->active ?? 0),
                'inactive' => (int) ($stats->inactive ?? 0),
                'upcoming' => (int) ($stats->upcoming ?? 0),
                'ongoing' => (int) ($stats->ongoing ?? 0),
                'completed' => (int) ($stats->completed ?? 0),
                'undated' => (int) ($stats->undated ?? 0),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'melava_code' => ['required', 'string', 'max:20', 'regex:/^MM-\d{2}$/i', 'unique:melava_campaigns,melava_code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'registration_fee' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'booklet_available' => ['required', 'boolean'],
            'booklet_fee' => [
                Rule::requiredIf($request->boolean('booklet_available')),
                'nullable',
                'numeric',
                'min:0',
                'max:10000000',
            ],
            'qr_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $qrPath = $this->storeQr($request);
        $campaign = MelavaCampaign::create([
            'melava_code' => strtoupper(trim($data['melava_code'])),
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'currency' => strtoupper($data['currency']),
            'registration_fee' => $data['registration_fee'],
            'booklet_available' => (bool) $data['booklet_available'],
            'booklet_fee' => $data['booklet_available'] ? ($data['booklet_fee'] ?? 0) : 0,
            'qr_image_path' => $qrPath,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Melava event created and opened for registration.',
            'data' => $this->present($campaign, $request),
        ], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $campaign = MelavaCampaign::query()->find($id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Melava event not found.'], 404);
        }

        $campaign->status = $data['status'];
        $campaign->save();

        return response()->json([
            'success' => true,
            'data' => $this->present($campaign, $request),
        ]);
    }

    public function update(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $campaign = MelavaCampaign::query()->find($id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Melava event not found.'], 404);
        }

        $data = $request->validate([
            'melava_code' => ['required', 'string', 'max:20', 'regex:/^MM-\d{2}$/i', Rule::unique('melava_campaigns', 'melava_code')->ignore($id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'registration_fee' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'booklet_available' => ['required', 'boolean'],
            'booklet_fee' => [
                Rule::requiredIf($request->boolean('booklet_available')),
                'nullable',
                'numeric',
                'min:0',
                'max:10000000',
            ],
            'qr_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $updates = [
            'melava_code' => strtoupper(trim($data['melava_code'])),
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'currency' => strtoupper($data['currency']),
            'registration_fee' => $data['registration_fee'],
            'booklet_available' => (bool) $data['booklet_available'],
            'booklet_fee' => $data['booklet_available'] ? ($data['booklet_fee'] ?? 0) : 0,
        ];

        if ($request->hasFile('qr_image')) {
            $updates['qr_image_path'] = $this->storeQr($request);
        }

        $oldQrPath = $campaign->qr_image_path;
        DB::transaction(function () use ($campaign, $updates, $oldQrPath) {
            $campaign->fill($updates)->save();

            if (isset($updates['qr_image_path']) && $oldQrPath) {
                $this->deleteQr($oldQrPath);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Melava event updated.',
            'data' => $this->present($campaign->refresh(), $request),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $campaign = MelavaCampaign::query()->find($id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Melava event not found.'], 404);
        }

        $registrationsCount = DB::table('profile_melava_registrations')->where('melava_campaign_id', $id)->count();
        if ($registrationsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'This event cannot be deleted because it has registrations. Close the event instead.',
            ], 409);
        }

        $campaign->delete();
        if ($campaign->qr_image_path) {
            $this->deleteQr($campaign->qr_image_path);
        }

        return response()->json(['success' => true, 'message' => 'Melava event deleted.']);
    }

    private function present(object $campaign, Request $request): array
    {
        $path = (string) ($campaign->qr_image_path ?? '');
        $qrUrl = preg_match('#^https?://#i', $path)
            ? $path
            : ($path !== '' ? $request->getSchemeAndHttpHost().'/'.ltrim($path, '/') : '');
        $today = now()->toDateString();
        $scheduleStatus = 'Undated';
        if ($campaign->start_date) {
            $endDate = $campaign->end_date ?: $campaign->start_date;
            $scheduleStatus = $campaign->start_date > $today
                ? 'Upcoming'
                : ($endDate < $today ? 'Completed' : 'Ongoing');
        }

        $data = [
            'id' => (int) $campaign->id,
            'melava_code' => $campaign->melava_code,
            'title' => $campaign->title,
            'description' => $campaign->description,
            'start_date' => $campaign->start_date,
            'end_date' => $campaign->end_date,
            'currency' => $campaign->currency,
            'fee' => (float) $campaign->registration_fee,
            'registration_fee' => (float) $campaign->registration_fee,
            'booklet_available' => (bool) $campaign->booklet_available,
            'booklet_fee' => (float) $campaign->booklet_fee,
            'qr_image_url' => $qrUrl,
            'status' => $campaign->status,
            'schedule_status' => $scheduleStatus,
        ];

        if ($request->user()?->portal() === 'admin') {
            $data['updated_by_user_id'] = $campaign->updated_by_user_id ?? null;
        }

        return $data;
    }

    private function storeQr(Request $request): string
    {
        $file = $request->file('qr_image');
        $directory = public_path('uploads/melava_qr');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create the Melava QR upload directory.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = Str::uuid().'.'.$extension;
        $file->move($directory, $name);

        return 'uploads/melava_qr/'.$name;
    }

    private function deleteQr(?string $path): void
    {
        if (! $path || preg_match('#^https?://#i', $path)) {
            return;
        }

        $file = public_path(ltrim($path, '/'));
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function isAdmin($user): bool
    {
        return $user && $user->isAdmin();
    }
}
