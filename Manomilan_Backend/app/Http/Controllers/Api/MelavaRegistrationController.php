<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\MelavaRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MelavaRegistrationController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $registrations = MelavaRegistration::with('profile')
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $registrations]);
    }

    public function store(Request $request)
    {
        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', $request->input('username')));

        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:username'],
            'username' => ['nullable', 'string', 'max:255', 'required_without:phone'],
            'email' => ['nullable', 'email', 'max:255'],
            'groomOrBride' => ['required', 'in:bride,groom'],
            'birthDate' => ['nullable', 'date', 'before:today'],
            'maritalStatus' => ['nullable', 'string', 'max:255'],
            'caste' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'source_of_information' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $registration = DB::transaction(function () use ($data, $phone) {
                return MelavaRegistration::create([
                    'fullName' => $data['fullName'],
                    'groomOrBride' => $data['groomOrBride'],
                    'birthDate' => $data['birthDate'] ?? null,
                    'username' => $phone ?: $data['username'],
                    'maritalStatus' => $data['maritalStatus'] ?? null,
                    'caste' => $data['caste'] ?? null,
                    'education' => $data['education'] ?? null,
                    'profession' => $data['profession'] ?? '',
                    'city' => $data['city'] ?? '',
                    'source_of_information' => $data['source_of_information'] ?? null,
                    'other_expectations' => '',
                    'melava_batch' => 'october_2026',
                    'melava_registered_at' => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Melava registration successful.',
                'data' => $registration,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete the Melava registration right now.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $registration = MelavaRegistration::with('profile')->find($id);

        if (! $registration) {
            return response()->json(['success' => false, 'message' => 'Registration not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $registration]);
    }

    private function isAdmin($user): bool
    {
        return $user && ((int) $user->role_id === 1 || (string) $user->user_type === '00');
    }

    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden. Administrator access is required.',
        ], 403);
    }
}
