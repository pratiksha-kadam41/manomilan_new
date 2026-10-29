<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MelavaCampaignController extends Controller
{
    public function active(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => DB::table('melava_campaigns')
                ->where('status', 'active')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($campaign) => $this->present($campaign, $request)),
        ]);
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => DB::table('melava_campaigns')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($campaign) => $this->present($campaign, $request)),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
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
        $now = now();
        $id = DB::table('melava_campaigns')->insertGetId([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'currency' => strtoupper($data['currency']),
            'registration_fee' => $data['registration_fee'],
            'booklet_available' => (bool) $data['booklet_available'],
            'booklet_fee' => $data['booklet_available'] ? ($data['booklet_fee'] ?? 0) : 0,
            'qr_image_path' => $qrPath,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $campaign = DB::table('melava_campaigns')->where('id', $id)->first();

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

        $exists = DB::table('melava_campaigns')->where('id', $id)->exists();
        if (! $exists) {
            return response()->json(['success' => false, 'message' => 'Melava event not found.'], 404);
        }

        DB::table('melava_campaigns')
            ->where('id', $id)
            ->update(['status' => $data['status'], 'updated_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $this->present(DB::table('melava_campaigns')->where('id', $id)->first(), $request),
        ]);
    }

    private function present(object $campaign, Request $request): array
    {
        $path = (string) ($campaign->qr_image_path ?? '');
        $qrUrl = preg_match('#^https?://#i', $path)
            ? $path
            : ($path !== '' ? $request->getSchemeAndHttpHost().'/'.ltrim($path, '/') : '');

        return [
            'id' => (int) $campaign->id,
            'title' => $campaign->title,
            'description' => $campaign->description,
            'currency' => $campaign->currency,
            'fee' => (float) $campaign->registration_fee,
            'registration_fee' => (float) $campaign->registration_fee,
            'booklet_available' => (bool) $campaign->booklet_available,
            'booklet_fee' => (float) $campaign->booklet_fee,
            'qr_image_url' => $qrUrl,
            'status' => $campaign->status,
        ];
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

    private function isAdmin($user): bool
    {
        return $user && ((int) $user->role_id === 1 || (string) $user->user_type === '00');
    }
}
