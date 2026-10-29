<?php

namespace App\Http\Controllers\Api;

use App\DocumentVerification;
use App\Http\Controllers\Controller;
use App\Profile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileController extends Controller
{
    private const DOCUMENT_KEYS = [
        'identificationProof',
        'birthDateProof',
        'addressProof',
        'educationProof',
        'professionProof',
        'incomeProof',
        'casteCertificate',
        'medicalFitnessCertificate',
        'selfieVerification',
    ];

    private const STATUSES = ['pending', 'verified', 'rejected', 'not_eligible'];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Profile::query()
            ->with(['user', 'paymentScreenshots', 'melavaRegistrations'])
            ->where('active', 'active');

        if ($user && $user->profile_id) {
            $query->where('id', '!=', $user->profile_id);
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('fullName', 'like', "%{$term}%")
                    ->orWhere('profile_id', 'like', "%{$term}%")
                    ->orWhere('registration_number', 'like', "%{$term}%")
                    ->orWhere('caste', 'like', "%{$term}%")
                    ->orWhere('education', 'like', "%{$term}%")
                    ->orWhere('profession', 'like', "%{$term}%");
            });
        }

        if ($request->filled('groomOrBride') && $request->input('groomOrBride') !== 'all') {
            $query->where('groomOrBride', $request->input('groomOrBride'));
        }

        if ($request->filled('religion')) {
            $query->where('religion', $request->input('religion'));
        }

        if ($request->filled('caste')) {
            $query->where('caste', $request->input('caste'));
        }

        $profiles = $query->latest()->paginate((int) $request->input('per_page', 20));

        $profiles->getCollection()->transform(fn (Profile $profile) => $this->presentProfile($profile));

        return response()->json(['success' => true, 'data' => $profiles]);
    }

    public function allProfiles(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $q = trim((string) $request->input('q', ''));
        $gender = $request->input('gender');
        $regType = $request->input('reg_type');

        $sortBy = $request->input('sort_by', 'registration_date');
        if (! in_array($sortBy, ['registration_number', 'registration_date', 'serial_no'], true)) {
            $sortBy = 'registration_date';
        }

        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Profile::query()
            ->with(['user', 'paymentScreenshots', 'melavaRegistrations'])
            ->where('profiles.active', 'active')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('profiles.profile_id', 'like', "%{$q}%")
                        ->orWhere('profiles.registration_number', 'like', "%{$q}%")
                        ->orWhere('profiles.serial_no', 'like', "%{$q}%")
                        ->orWhere('profiles.fullName', 'like', "%{$q}%")
                        ->orWhere('profiles.profileVerificationStatus', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('username', 'like', "%{$q}%"));
                });
            })
            ->when($gender && $gender !== 'all', fn ($query) => $query->where('profiles.groomOrBride', $gender))
            ->when($regType && $regType !== 'all', function ($query) use ($regType) {
                if ($regType === 'melava_existing') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->whereIn('profiles.registration_type', ['melava', 'existing'])
                        ->where(function ($sub) {
                            $sub->where('profiles.registration_number', 'like', 'MM3%')
                                ->orWhere('profiles.profile_id', 'like', 'MM3%');
                        });
                } elseif ($regType === 'melava') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->where('profiles.registration_type', 'melava')
                        ->where(function ($sub) {
                            $sub->where('profiles.registration_number', 'like', 'MM3%')
                                ->orWhere('profiles.profile_id', 'like', 'MM3%');
                        });
                } else {
                    $query->where('profiles.registration_type', $regType);
                }
            });

        if ($sortBy === 'registration_number') {
            $query->orderByRaw("COALESCE(NULLIF(profiles.registration_number, ''), profiles.profile_id) {$sortOrder}");
        } elseif ($sortBy === 'serial_no') {
            $query->orderBy('profiles.serial_no', $sortOrder);
        } else {
            $query->orderBy('profiles.created_at', $sortOrder);
        }

        $profiles = $query->paginate(10);

        return response()->json([
            'data' => $profiles->getCollection()->map(fn (Profile $profile) => $this->presentProfile($profile))->values(),
            'current_page' => $profiles->currentPage(),
            'last_page' => $profiles->lastPage(),
            'per_page' => $profiles->perPage(),
            'total' => $profiles->total(),
            'from' => $profiles->firstItem(),
            'to' => $profiles->lastItem(),
        ]);
    }

    public function export(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $query = Profile::query()
            ->with(['user', 'paymentScreenshots', 'melavaRegistrations'])
            ->where('active', 'active');

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($term) {
                $sub->where('profile_id', 'like', "%{$term}%")
                    ->orWhere('registration_number', 'like', "%{$term}%")
                    ->orWhere('fullName', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('username', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('gender') && $request->input('gender') !== 'all') {
            $query->where('groomOrBride', $request->input('gender'));
        }

        if ($request->filled('reg_type') && $request->input('reg_type') !== 'all') {
            $query->where('registration_type', $request->input('reg_type'));
        }

        $filename = 'profiles-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Registration No', 'Full Name', 'Gender', 'Mobile', 'Registered On',
                'Verification Status', 'Payment Verified', 'Registration Type',
            ]);

            $query->orderByDesc('created_at')->chunk(500, function ($profiles) use ($out) {
                foreach ($profiles as $profile) {
                    $row = $this->presentProfile($profile);
                    fputcsv($out, [
                        $row['registration_number'],
                        $row['full_name'],
                        $row['gender'],
                        $row['mobile'],
                        $row['registration_date'],
                        $row['status'],
                        $row['payment_verified'] ? 'Yes' : 'No',
                        $row['reg_type'],
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show(Request $request, $id)
    {
        $profile = Profile::with(['user', 'paymentScreenshots', 'melavaRegistrations'])->find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->presentProfile($profile)]);
    }

    public function myProfile(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->profile_id) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $profile = Profile::with(['user', 'paymentScreenshots', 'melavaRegistrations'])
            ->find($user->profile_id);

        return response()->json([
            'success' => true,
            'data' => $profile ? $this->presentProfile($profile, true) : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        if ($user->profile_id) {
            return response()->json([
                'success' => false,
                'message' => 'A profile is already linked to this account.',
            ], 422);
        }

        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'groomOrBride' => ['required', Rule::in(['bride', 'groom'])],
            'birthDate' => ['required', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'registration_type' => ['nullable', Rule::in(['quick', 'melava', 'existing'])],
        ]);

        try {
            $profile = DB::transaction(function () use ($data, $user) {
                $profile = Profile::create($data);
                $user->profile_id = $profile->id;
                $user->save();

                return $profile;
            });

            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully.',
                'data' => $this->presentProfile($profile->fresh(['user']), true),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create the profile right now.',
            ], 500);
        }
    }

    public function deactivate(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        if ($profile->active === 'deactive') {
            return response()->json(['success' => true, 'message' => 'Profile is already inactive.']);
        }

        try {
            DB::transaction(function () use ($profile) {
                $profile->active = 'deactive';
                $profile->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Profile deactivated successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to deactivate this profile right now.',
            ], 500);
        }
    }

    public function verification(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        $verification = DocumentVerification::firstOrCreate(['profile_id' => $profile->id]);

        $documents = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            $documents[$key] = [
                'file_url' => $this->fileUrl($profile->{$key} ?? null),
                'status' => $this->cleanStatus($verification->{$key.'_status'} ?? 'pending'),
                'remarks' => (string) ($verification->{$key.'_remarks'} ?? ''),
            ];
        }

        return response()->json([
            'profile' => [
                'id' => $profile->id,
                'full_name' => $profile->fullName,
                'registration_number' => $profile->registration_number ?: $profile->profile_id,
                'registration_date' => optional($profile->created_at)->format('d M, Y'),
                'photo_url' => $this->fileUrl($profile->profilePhoto),
            ],
            'documents' => $documents,
        ]);
    }

    public function verify(Request $request, $id)
    {
        if (! $this->isAdmin($request->user())) {
            return $this->forbidden();
        }

        $profile = Profile::find($id);

        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
        }

        $rules = [];
        foreach (self::DOCUMENT_KEYS as $key) {
            $rules[$key.'_status'] = ['required', Rule::in(self::STATUSES)];
            $rules[$key.'_remarks'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        try {
            DB::transaction(function () use ($profile, $data) {
                DocumentVerification::updateOrCreate(['profile_id' => $profile->id], $data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Verification saved successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save the verification right now.',
            ], 500);
        }
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

    private function presentProfile(Profile $profile, bool $detailed = false): array
    {
        $user = $profile->user;
        $paymentVerified = $profile->paymentScreenshots->contains('payment_verified', true)
            || $profile->melavaRegistrations->contains('payment_verified', true);

        $data = [
            'id' => $profile->id,
            'registration_number' => $profile->registration_number ?: $profile->profile_id,
            'profile_id' => $profile->profile_id,
            'serial_no' => $profile->serial_no,
            'photo_url' => $this->fileUrl($profile->profilePhoto),
            'full_name' => $profile->fullName,
            'mobile' => $user->username ?? $profile->phone,
            'email' => $profile->email ?? $user->email ?? null,
            'registration_date' => optional($profile->created_at)->format('d-m-Y'),
            'status' => $profile->profileVerificationStatus,
            'payment_verified' => $paymentVerified,
            'gender' => $profile->groomOrBride,
            'reg_type' => $profile->registration_type,
            'city' => $profile->location ?: $profile->professionalCity ?: $profile->cityOfEducation,
            'education' => $profile->education,
            'profession' => $profile->profession,
            'age' => $profile->birthDate ? Carbon::parse($profile->birthDate)->age : null,
        ];

        if ($detailed) {
            $data['profile_completion'] = (int) $profile->profile_completion;
            $data['caste'] = $profile->caste;
            $data['marital_status'] = $profile->maritalStatus;
            $data['religion'] = $profile->religion;
            $data['height'] = $profile->height;
            $data['about'] = $profile->knowAboutMyself;
        }

        return $data;
    }

    private function cleanStatus($status): string
    {
        return in_array($status, self::STATUSES, true) ? $status : 'pending';
    }

    private function fileUrl($path): string
    {
        if (! is_string($path) || trim($path) === '') {
            return '';
        }

        $path = trim($path);

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return url(ltrim($path, '/'));
    }
}
