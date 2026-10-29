<?php

namespace App\Http\Controllers;

use App\MelavaPendingRegistration;
use App\MelavaRegistration;
use App\User;
use App\Profile;
use App\Preference;
use App\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MelavaRegistrationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
public function index(Request $request)
{
    $batch = $request->query('batch');
    $booklet = $request->query('booklet', 'all');
    $search = trim((string) $request->query('q', ''));

    $batchLabels = [
        'MM1' => 'Melava 1 (MM1)',
        'MM2' => 'Melava 2 (MM2)',
        'MM3' => 'Melava 3 (MM3)',
    ];

    $pageTitle = isset($batchLabels[$batch])
        ? $batchLabels[$batch] . ' - Candidate List'
        : 'Melava Candidate List';

    if ($batch) {
        $query = DB::table('profiles')
            ->where('profiles.active', 'active')
            ->whereIn('profiles.registration_type', ['melava', 'existing'])
            ->where(function ($q) use ($batch) {
                $q->where('profiles.registration_number', 'like', $batch . '%')
                    ->orWhere('profiles.profile_id', 'like', $batch . '%');
            })
            // Older batches (MM1 / MM2) exist only as profiles and have no
            // melava_registrations row, so the join must not drop them.
            ->leftJoin(
                'melava_registrations',
                'profiles.id',
                '=',
                'melava_registrations.profile_id'
            )
            // Keep only the latest registration per candidate so duplicate
            // melava_registrations rows do not repeat the same person.
            ->where(function ($q) {
                $q->whereNull('melava_registrations.id')
                    ->orWhereIn('melava_registrations.id', function ($sub) {
                        $sub->selectRaw('MAX(mr2.id)')
                            ->from('melava_registrations as mr2')
                            ->whereColumn('mr2.profile_id', 'profiles.id');
                    });
            });

        // Preserve the existing Booklet filter.
        if ($booklet === 'yes') {
            $query->where('melava_registrations.is_booklet_req', 1);
        } elseif ($booklet === 'no') {
            $query->where(function ($q) {
                $q->where('melava_registrations.is_booklet_req', 0)
                    ->orWhereNull('melava_registrations.is_booklet_req');
            });
        }

        // Search registration number, name, mobile, or registration date.
        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($q) use ($like) {
                $q->where('profiles.registration_number', 'like', $like)
                    ->orWhere('profiles.profile_id', 'like', $like)
                    ->orWhere('profiles.fullName', 'like', $like)
                    ->orWhere('profiles.phone', 'like', $like)
                    ->orWhere('profiles.source_of_information', 'like', $like)
                    ->orWhere('profiles.groomOrBride', 'like', $like)
                    ->orWhere('profiles.registration_type', 'like', $like)
                    ->orWhere('profiles.professionalCity', 'like', $like)
                    ->orWhere('melava_registrations.fullName', 'like', $like)
                    ->orWhere('melava_registrations.username', 'like', $like)
                    ->orWhere('melava_registrations.source_of_information', 'like', $like)
                    ->orWhere('melava_registrations.groomOrBride', 'like', $like)
                    ->orWhere('melava_registrations.city', 'like', $like)
                    ->orWhereRaw("DATE_FORMAT(profiles.created_at, '%d-%m-%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(profiles.updated_at, '%d-%m-%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(melava_registrations.created_at, '%d-%m-%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(melava_registrations.updated_at, '%d-%m-%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(melava_registrations.melava_registered_at, '%d-%m-%Y') LIKE ?",[$like]);
            });
        }

        $query->select(
            'profiles.id as _profile_id',
            'profiles.fullName',
            'profiles.phone as username',
            'profiles.source_of_information',
            'profiles.groomOrBride',
            'profiles.professionalCity as city',
            'profiles.created_at',
            'profiles.updated_at',
            'profiles.registration_number',
            'profiles.registration_type',
            'melava_registrations.id as id',
            'melava_registrations.link_token',
            'melava_registrations.is_booklet_req',
            'melava_registrations.melava_registered_at',
            'melava_registrations.created_at as melava_created_at',
            DB::raw(
                'COALESCE(
                    melava_registrations.biodata,
                    profiles.identificationProof
                ) as biodata'
            )
        )
        ->orderByRaw(
            'COALESCE(melava_registrations.melava_registered_at, profiles.created_at) IS NULL ASC'
        )
        ->orderByRaw(
            'COALESCE(melava_registrations.melava_registered_at, profiles.created_at) DESC'
        );

        $registrations = $query->distinct()->paginate(10);


    } else {
        $query = DB::table('melava_registrations')
            ->leftJoin(
                'profiles',
                'melava_registrations.profile_id',
                '=',
                'profiles.id'
            )
            ->select(
                'melava_registrations.*',
                'profiles.registration_type',
                'profiles.registration_number',
                'profiles.profile_id as linked_profile_id',
                DB::raw('CASE
                    WHEN profiles.registration_type IS NOT NULL
                        THEN profiles.registration_type
                    WHEN melava_registrations.is_existing_website_user = 1
                        THEN "existing"
                    ELSE "melava"
                END as display_registration_type')
            )
            ->where('profiles.active', 'active')
            // Keep only the latest registration per candidate so duplicate
            // melava_registrations rows do not repeat the same person.
            ->whereIn('melava_registrations.id', function ($sub) {
                $sub->selectRaw('MAX(mr2.id)')
                    ->from('melava_registrations as mr2')
                    ->whereColumn('mr2.profile_id', 'melava_registrations.profile_id');
            });

        // Preserve the existing Booklet filter for the general list as well.
        if ($booklet === 'yes') {
            $query->where('melava_registrations.is_booklet_req', 1);
        } elseif ($booklet === 'no') {
            $query->where(function ($q) {
                $q->where('melava_registrations.is_booklet_req', 0)
                    ->orWhereNull('melava_registrations.is_booklet_req');
            });
        }

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($q) use ($like) {
                $q->where('profiles.registration_number', 'like', $like)
                    ->orWhere('profiles.profile_id', 'like', $like)
                    ->orWhere('melava_registrations.fullName', 'like', $like)
                    ->orWhere('profiles.fullName', 'like', $like)
                    ->orWhere('melava_registrations.username', 'like', $like)
                    ->orWhere('profiles.phone', 'like', $like)
                    ->orWhere('melava_registrations.source_of_information', 'like', $like)
                    ->orWhere('profiles.source_of_information', 'like', $like)
                    ->orWhere('melava_registrations.groomOrBride', 'like', $like)
                    ->orWhere('profiles.groomOrBride', 'like', $like)
                    ->orWhere('profiles.registration_type', 'like', $like)
                    ->orWhereRaw("CASE WHEN profiles.registration_type IS NOT NULL THEN profiles.registration_type WHEN melava_registrations.is_existing_website_user = 1 THEN 'existing' ELSE 'melava' END LIKE ?", [$like])
                    ->orWhere('melava_registrations.city', 'like', $like)
                    ->orWhere('profiles.professionalCity', 'like', $like)
                    ->orWhereRaw("DATE_FORMAT(melava_registrations.created_at, '%d-%m-%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(melava_registrations.updated_at, '%d-%m-%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(profiles.created_at, '%d-%m-%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(profiles.updated_at, '%d-%m-%Y') LIKE ?", [$like]);
            });
        }

        $registrations = $query
            ->orderByRaw( 'COALESCE(melava_registrations.melava_registered_at, profiles.created_at) IS NULL ASC')
            ->orderByRaw('COALESCE(melava_registrations.melava_registered_at, profiles.created_at) DESC')
            ->paginate(10);
    }

    // Keep filters/search in pagination links.
    $registrations->appends([
        'batch' => $batch,
        'booklet' => $booklet,
        'q' => $search,
    ]);

    return view(
        'admin.melava.index',
        compact('registrations', 'pageTitle', 'batch', 'booklet', 'search')
    );
}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $registration = null;
        return view('admin.melava.create', compact('registration'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'fullName' => 'required|string|max:255',
            'groomOrBride' => 'required|string',
            'birthDate' => 'required|date',
            'username' => 'required|digits:10',
            'maritalStatus' => 'nullable|string',
            'caste' => 'nullable|string',
            'subCaste' => 'nullable|string',
            'education' => 'nullable|string',
            'profession' => 'required|string',
            'city' => 'required|string',
            'address' => 'required|string',
            'height' => 'nullable|string',
            'parents_contact' => 'nullable|digits:10',
            'fathersName' => 'nullable|string',
            'mothersName' => 'nullable|string',
            'noOfBrothers' => 'nullable|integer',
            'noOfSisters' => 'nullable|integer',
            'age_from' => 'nullable|integer',
            'age_to' => 'nullable|integer',
            'height_from' => 'nullable|string',
            'height_to' => 'nullable|string',
            'preferred_education' => 'nullable|string',
            'preferred_working_field' => 'nullable|string',
            'preferred_caste' => 'nullable|string',
            'preferred_location' => 'nullable|string',
            'other_expectations' => 'nullable|string',
            'source_of_information' => 'required|string|max:255',
            'birth_verification_doc' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
            'candidate_photos' => 'nullable|array|max:5',
            'candidate_photos.*' => 'nullable|image|max:5120',
        ]);

        $data = $request->all();
        $data['updated_by'] = Auth::id();
        $data['melava_batch'] = self::CURRENT_MELAVA_BATCH;
        if (!isset($data['link_token'])) {
            $data['link_token'] = Str::random(40);
        }

        if ($request->hasFile('birth_verification_doc')) {
            $file = $request->file('birth_verification_doc');
            $filename = time() . '_birth_' . $file->getClientOriginalName();
            $uploadDirectory = public_path('uploads/melava_birth_docs');
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $file->move($uploadDirectory, $filename);
            $data['birth_verification_doc'] = 'uploads/melava_birth_docs/' . $filename;
        }

        if ($request->hasFile('candidate_photos')) {
            $candidatePhotos = [];
            $photoUploadDir = public_path('uploads/melava_candidate_photos');
            if (!file_exists($photoUploadDir)) {
                mkdir($photoUploadDir, 0777, true);
            }
            foreach ($request->file('candidate_photos') as $index => $photo) {
                if ($photo->isValid()) {
                    $photoFilename = time() . '_photo_' . $index . '_' . $photo->getClientOriginalName();
                    $photo->move($photoUploadDir, $photoFilename);
                    $candidatePhotos[] = 'uploads/melava_candidate_photos/' . $photoFilename;
                }
            }
            if (!empty($candidatePhotos)) {
                $data['candidate_photos'] = json_encode($candidatePhotos);
            }
        }

        DB::beginTransaction();

        try {
            $registration = null;
            $successMessage = 'Registration added successfully.';

            // Path 1: Update existing draft/record by registration_id
            if ($request->has('registration_id') && !empty($request->registration_id)) {
                $registration = MelavaRegistration::find($request->registration_id);
                if ($registration) {
                    $registration->update($data);
                    $successMessage = 'Registration updated successfully.';
                }
            }

            // Path 2: Fallback Duplication Check - update by username if exists
            if (!$registration) {
                $existingRegistration = MelavaRegistration::where('username', $data['username'])->first();
                if ($existingRegistration) {
                    $existingRegistration->update($data);
                    $registration = $existingRegistration;
                    $successMessage = 'Existing Registration updated successfully.';
                }
            }

            // Path 3: Brand new registration
            if (!$registration) {
                $registration = MelavaRegistration::create($data);
            }

            // =====================================================
            // SHARED: Ensure User, Profile, and Preference exist
            // Runs for ALL three paths above
            // =====================================================

            // 1. Find or Create User
            $existingUser = User::where('username', $registration->username)->first();
            if (!$existingUser) {
                $user = User::create([
                    'username' => $registration->username,
                    'name' => $registration->fullName,
                    'password' => Hash::make('manomilana'),
                    'user_type' => 21,
                    'role_id' => 3,
                    'email' => $registration->username . '@melava.com',
                    'status' => 'active',
                    'is_password_changed' => 0,
                ]);
            } else {
                $user = $existingUser;
            }

            // 2. Create Profile if user doesn't already have one
            if (!$user->profile_id) {
                $profile = Profile::create([
                    'registration_type'         => 'melava',
                    'melava_batch'              => self::CURRENT_MELAVA_BATCH,
                    'fullName'                  => $registration->fullName,
                    'groomOrBride'              => $registration->groomOrBride,
                    'profilePhoto'              => 'default.png',
                    'birthDate'                 => $registration->birthDate,
                    'maritalStatus'             => $registration->maritalStatus ?? '',
                    'caste'                     => $registration->caste ?? '',
                    'subCaste'                  => $registration->subCaste ?? '',
                    'education'                 => $registration->education ?? '',
                    'profession'                => $registration->profession ?? '',
                    'professionalCity'          => $registration->city ?? '',
                    'address'                   => $registration->address ?? '',
                    'height'                    => $registration->height ?? '',
                    'referenceContactNumber'    => $registration->parents_contact ?? '',
                    'fathersName'               => $registration->fathersName ?? '',
                    'mothersName'               => $registration->mothersName ?? '',
                    'noOfBrothers'              => isset($registration->noOfBrothers) ? (int) $registration->noOfBrothers : null,
                    'noOfSisters'               => isset($registration->noOfSisters) ? (int) $registration->noOfSisters : null,
                    'source_of_information'     => $registration->source_of_information ?? '',
                    'phone'                     => $registration->username,
                    'income_currency'           => 'INR',
                    'profileVerificationStatus' => 'Pending',
                    'identificationProof'       => !empty($registration->biodata) ? $registration->biodata : (!empty($registration->birth_verification_doc) ? $registration->birth_verification_doc : null),
                    'birthDateProof'            => !empty($registration->birth_verification_doc) ? $registration->birth_verification_doc : null,
                    'additional_photos'         => !empty($registration->candidate_photos) ? $registration->candidate_photos : null,
                ]);

                $user->profile_id = $profile->id;
                $user->save();

                // 3. Create Preference
                Preference::create([
                    'user_id'                   => $user->id,
                    'age_from'                  => $registration->age_from ?? null,
                    'age_to'                    => $registration->age_to ?? null,
                    'height_from'               => $registration->height_from ?? null,
                    'height_to'                 => $registration->height_to ?? null,
                    'preferred_education'       => $registration->preferred_education ?? null,
                    'preferred_working_field'   => $registration->preferred_working_field ?? null,
                    'preferred_caste'           => $registration->preferred_caste ?? null,
                    'preferred_location'        => $registration->preferred_location ?? null,
                    'other_expectations'        => $registration->other_expectations ?? null,
                ]);
            } else {
                $profile = Profile::find($user->profile_id);

                // Existing candidate registering for the current Melava batch.
                // If they do not already hold a valid current-batch serial
                // (e.g. they still carry an old-format Melava 2 serial), clear
                // it so the Profile saving hook issues a fresh age-group-wise
                // serial number.
                if (
                    $profile &&
                    in_array($profile->registration_type, ['melava', 'existing'], true) &&
                    !Profile::hasCurrentMelavaSerial($profile->serial_no, $profile->melava_batch)
                ) {
                    $profile->melava_batch = self::CURRENT_MELAVA_BATCH;
                    $profile->serial_no = null;
                    $profile->save();
                }
            }

            // 4. Link MelavaRegistration -> User/Profile
            $registration->user_id = $user->id;
            $registration->profile_id = $profile->id ?? $user->profile_id;
            $registration->is_existing_website_user = 1;
            $registration->save();

            DB::commit();
            return redirect()->route('melava.create')->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in Melava store: " . $e->getMessage());
            return back()
                ->withInput($request->except(['biodata', 'birth_verification_doc', 'candidate_photos']))
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $registration = MelavaRegistration::findOrFail($id);
        return view('admin.melava.create', compact('registration'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $registration = MelavaRegistration::findOrFail($id);
            $registration->delete();

            DB::commit();
            return redirect()->route('melava.index')->with('success', 'Registration deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in Melava destroy: " . $e->getMessage());
            return back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    /**
     * Auto-save registration draft when mobile number is entered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
public function autoSave(Request $request)
{
    try {

        // Validate only the mobile number and existing-user flag.
        $request->validate([
            'username' => 'required|digits:10',
            'is_existing_website_user' => 'required|in:0,1',
        ]);

        $mobile = trim($request->username);
        $isExisting = (int) $request->is_existing_website_user;

        /*
        |--------------------------------------------------------------------------
        | EXISTING WEBSITE USER
        |--------------------------------------------------------------------------
        |
        | If user selected YES, check whether the mobile exists in users.
        | DO NOT create/update melava_registrations here.
        |
        */
        if ($isExisting === 1) {

            $user = User::where('username', $mobile)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'exists' => false,
                    'message' => 'This mobile number is not registered on the website. Please select "No" and continue as a new user.'
                ], 422);
            }

            $profile = null;

            if ($user->profile_id) {
                $profile = Profile::find($user->profile_id);
            }

            return response()->json([
                'success' => true,
                'exists' => true,
                'is_existing_website_user' => true,

                'user_id' => $user->id,
                'profile_id' => $user->profile_id,

                'user' => $user,
                'profile' => $profile,

                'message' => 'Existing website user found successfully. Your details can be loaded.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | NEW WEBSITE USER
        |--------------------------------------------------------------------------
        |
        | User selected NO.
        |
        | Check whether this mobile already exists in users.
        | If it exists, DON'T allow the registration.
        |
        */
        $existingUser = User::where('username', $mobile)->first();

        if ($existingUser) {

            return response()->json([
                'success' => false,
                'exists' => true,
                'is_existing_website_user' => false,

                'message' => 'This mobile number is already website registered. Please select "Yes" for already registered user.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | NEW MOBILE
        |--------------------------------------------------------------------------
        |
        | Mobile is genuinely new.
        |
        | IMPORTANT:
        | We DO NOT create:
        | - users
        | - profiles
        | - melava_registrations
        |
        | Everything will be created only after payment screenshot submission.
        |
        */

        return response()->json([
            'success' => true,
            'exists' => false,
            'is_existing_website_user' => false,

            'message' => 'Mobile number is available for new registration.'
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'success' => false,
            'message' => $e->validator->errors()->first()
        ], 422);

    } catch (\Exception $e) {

        \Illuminate\Support\Facades\Log::error('Melava public autoSave error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Unable to verify the mobile number. Please try again.'
        ], 500);
    }
}

    /**
     * Upload payment screenshot and generate user/profile/preferences.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
public function uploadPayment(Request $request)
{
    $request->validate([
        'registration_id' => 'required|exists:melava_registrations,id',
        'payment_screenshot' => 'required|image|max:5120',
    ]);

    $registration = MelavaRegistration::findOrFail(
        $request->registration_id
    );

    // ---------------------------------------------------------
    // 1. Basic validation
    // ---------------------------------------------------------

    if (
        empty($registration->fullName) ||
        empty($registration->username)
    ) {
        return back()->with(
            'error',
            'Registration must have a name and mobile number before payment submission.'
        );
    }

    // ---------------------------------------------------------
    // 2. Determine registration type
    // ---------------------------------------------------------

    $isExistingWebsiteUser =
        (int) $registration->is_existing_website_user === 1;

    // ---------------------------------------------------------
    // 3. Start DB transaction
    // ---------------------------------------------------------

    DB::beginTransaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | 4. Upload payment screenshot
        |--------------------------------------------------------------------------
        */

        if (!$request->hasFile('payment_screenshot')) {
            throw new \Exception(
                'Payment screenshot is required.'
            );
        }

        $file = $request->file('payment_screenshot');

        $filename =
            time() . '_payment_' .
            $file->getClientOriginalName();

        $file->move(
            public_path('uploads/payment_screenshots'),
            $filename
        );

        $paymentPath =
            'uploads/payment_screenshots/' . $filename;


        /*
        |--------------------------------------------------------------------------
        | 5. EXISTING WEBSITE USER
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Existing website user already has:
        |     users
        |     profiles
        |
        | We MUST NOT create either again.
        |
        */

        if ($isExistingWebsiteUser) {

            // -------------------------------------------------
            // Find existing user
            // -------------------------------------------------

            $user = null;

            // First try the user_id stored in Melava registration
            if ($registration->user_id) {

                $user = User::find(
                    $registration->user_id
                );
            }

            // Fallback: search by mobile number
            if (!$user) {

                $user = User::where(
                    'username',
                    $registration->username
                )->first();
            }

            // Existing user must exist
            if (!$user) {

                throw new \Exception(
                    'Existing website user could not be found.'
                );
            }


            // -------------------------------------------------
            // Existing user's profile
            // -------------------------------------------------

            $profileId = $registration->profile_id;

            if (!$profileId) {
                $profileId = $user->profile_id;
            }

            if (!$profileId) {

                throw new \Exception(
                    'Existing website user does not have a profile.'
                );
            }


            $profile = Profile::find($profileId);

            if (!$profile) {

                throw new \Exception(
                    'Existing website profile could not be found.'
                );
            }


            // -------------------------------------------------
            // DO NOT CREATE:
            //
            // User
            // Profile
            // Preference
            // -------------------------------------------------

            $registration->user_id =
                $user->id;

            $registration->profile_id =
                $profile->id;

            $registration->is_existing_website_user =
                1;

            $registration->melava_batch =
                self::CURRENT_MELAVA_BATCH;

            // Existing candidate registering for the current Melava batch.
            // Replace any stale (old-format) serial number with a fresh
            // age-group-wise Melava 3 serial.
            if (
                $profile &&
                in_array($profile->registration_type, ['melava', 'existing'], true) &&
                !Profile::hasCurrentMelavaSerial($profile->serial_no, $profile->melava_batch)
            ) {
                $profile->melava_batch = self::CURRENT_MELAVA_BATCH;
                $profile->serial_no = null;
                $profile->save();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 6. NEW WEBSITE USER
        |--------------------------------------------------------------------------
        |
        | Only here we create:
        |
        | User
        | Profile
        | Preference
        |
        */

        else {

            // -------------------------------------------------
            // FINAL DUPLICATE MOBILE CHECK
            // -------------------------------------------------
            //
            // This is extremely important.
            //
            // JavaScript checking is NOT enough.
            //

            $existingUser = User::where(
                'username',
                $registration->username
            )->first();

            if ($existingUser) {

                throw new \Exception(
                    'This mobile number is already website registered. Please select "Yes, I am already registered".'
                );
            }


            // -------------------------------------------------
            // Create User
            // -------------------------------------------------

            $user = User::create([
                'username' => $registration->username,

                'name' =>
                    $registration->fullName,

                'password' =>
                    Hash::make('manomilana'),

                'user_type' => 21,

                'role_id' => 3,

                'email' =>
                    $registration->username .
                    '@melava.com',

                'status' =>
                    'active',

                'is_password_changed' =>
                    0,
            ]);


            // -------------------------------------------------
            // Generate Profile ID
            // -------------------------------------------------

            $profileIdString =
                'AM' .
                Str::upper(
                    Str::random(8)
                );


            // -------------------------------------------------
            // Create Profile
            // -------------------------------------------------

            $profile = Profile::create([

                'registration_type'         => 'melava',
                'melava_batch'              => self::CURRENT_MELAVA_BATCH,

                'profile_id' =>
                    $profileIdString,

                'fullName' =>
                    $registration->fullName,

                'groomOrBride' =>
                    $registration->groomOrBride,

                'profilePhoto' =>
                    'default.png',

                'birthDate' =>
                    $registration->birthDate,

                'maritalStatus' =>
                    $registration->maritalStatus,

                'caste' =>
                    $registration->caste,

                'subCaste' =>
                    $registration->subCaste,

                'education' =>
                    $registration->education,

                'profession' =>
                    $registration->profession,

                'professionalCity' =>
                    $registration->city,

                'address' =>
                    $registration->address,

                'height' =>
                    $registration->height,

                'referenceContactNumber' =>
                    $registration->parents_contact,

                'fathersName' =>
                    $registration->fathersName,

                'mothersName' =>
                    $registration->mothersName,

                'noOfBrothers' =>
                    $registration->noOfBrothers,

                'noOfSisters' =>
                    $registration->noOfSisters,

                'source_of_information' =>
                    $registration->source_of_information,

                'phone' =>
                    $registration->username,

                'income_currency' =>
                    'INR',

                'profileVerificationStatus' =>
                    'Pending',

                'identificationProof' =>
                    !empty($registration->biodata) ? $registration->biodata : (!empty($registration->birth_verification_doc) ? $registration->birth_verification_doc : null),

                'birthDateProof' =>
                    !empty($registration->birth_verification_doc) ? $registration->birth_verification_doc : null,

                'additional_photos' =>
                    !empty($registration->candidate_photos) ? $registration->candidate_photos : null,
            ]);


            // -------------------------------------------------
            // Connect User -> Profile
            // -------------------------------------------------

            $user->profile_id =
                $profile->id;

            $user->save();


            // -------------------------------------------------
            // Connect Melava -> User/Profile
            // -------------------------------------------------

            $registration->user_id =
                $user->id;

            $registration->profile_id =
                $profile->id;

            $registration->is_existing_website_user =
                0;


            // -------------------------------------------------
            // Create Preference
            // -------------------------------------------------

            Preference::create([

                'user_id' =>
                    $user->id,

                'age_from' =>
                    $registration->age_from,

                'age_to' =>
                    $registration->age_to,

                'height_from' =>
                    $registration->height_from,

                'height_to' =>
                    $registration->height_to,

                'preferred_education' =>
                    $registration->preferred_education,

                'preferred_working_field' =>
                    $registration->preferred_working_field,

                'preferred_caste' =>
                    $registration->preferred_caste,

                'preferred_location' =>
                    $registration->preferred_location,

                'other_expectations' =>
                    $registration->other_expectations,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 7. Save payment screenshot in Melava Registration
        |--------------------------------------------------------------------------
        */

        $registration->payment_screenshot =
            $paymentPath;

        $registration->melava_batch =
            self::CURRENT_MELAVA_BATCH;

        $registration->save();


        /*
        |--------------------------------------------------------------------------
        | 8. Create Payment Record
        |--------------------------------------------------------------------------
        */

        Payment::create([

            'user_id' =>
                $registration->user_id,

            'photo_upload' =>
                $paymentPath,

            'updated_by' =>
                Auth::check()
                    ? Auth::user()->name
                    : 'Public Melava Registration',
        ]);


        /*
        |--------------------------------------------------------------------------
        | 9. Commit EVERYTHING
        |--------------------------------------------------------------------------
        */

        DB::commit();


        /*
        |--------------------------------------------------------------------------
        | 10. Success
        |--------------------------------------------------------------------------
        */

        return back()->with(
            'success',
            'Payment uploaded and Melava registration completed successfully.'
        );


    } catch (\Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | Rollback database
        |--------------------------------------------------------------------------
        */

        DB::rollBack();

        Log::error(
            'Error in Melava uploadPayment: ' .
            $e->getMessage()
        );

        return back()
            ->withInput($request->except(['payment_screenshot']))
            ->with(
                'error',
                $e->getMessage()
            );
    }
}

    /**
     * Show the public form for the candidate to fill/edit.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */


    /**
     * Upload a booklet payment screenshot separately from the website
     * registration payment. This does not generate or modify a user/profile.
     */
    public function uploadBookletPayment(Request $request)
    {
        $request->validate([
            'registration_id' => 'required|exists:melava_registrations,id',
            'booklet_payment_screenshot' => 'required|image|max:5120',
        ]);

        $registration = MelavaRegistration::findOrFail($request->registration_id);
        $file = $request->file('booklet_payment_screenshot');
        $uploadDirectory = public_path('uploads/booklet_payment_screenshots');

        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            return back()->with('error', 'Unable to create the booklet screenshot upload directory.');
        }

        $extension = $file->getClientOriginalExtension();
        $filename = 'booklet_' . $registration->id . '_' . time() . '_' . Str::random(8) . ($extension ? '.' . $extension : '');
        $relativePath = 'uploads/booklet_payment_screenshots/' . $filename;
        $absolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

        try {
            $file->move($uploadDirectory, $filename);

            DB::table('melava_registrations')
                ->where('id', $registration->id)
                ->update([
                    'booklet_payment_screenshot' => $relativePath,
                    'is_booklet_req' => 1,
                ]);
        } catch (\Exception $e) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            Log::error('Error uploading Melava booklet payment screenshot: ' . $e->getMessage());
            return back()->with('error', 'Unable to upload booklet payment screenshot. Please try again.');
        }

        return back()->with('success', 'Booklet payment screenshot uploaded successfully. Booklet request has been set to Yes.');
    }

    // --- Public Registration Methods ---

    /**
     * Show the public registration form (New Registration).
     */
    public function publicRegistrationForm()
    {
        $registration = null;
        return view('admin.melava.public_create', compact('registration'));
    }

    /**
     * Handle the submission of a new public registration.
     */
public function publicRegistrationStore(Request $request)
{
    $request->validate([
        'is_existing_website_user' => 'required|in:0,1',
        'fullName' => 'required|string|max:255',
        'groomOrBride' => 'required|string',
        'birthDate' => 'required|date',
        'username' => 'required|digits:10',
        'maritalStatus' => 'nullable|string',
        'caste' => 'nullable|string',
        'subCaste' => 'nullable|string',
        'education' => 'nullable|string',
        'profession' => 'required|string',
        'professionalCity' => 'required|string',
        'city' => 'nullable|string',
        'address' => 'required|string',
        'permanent_address' => 'nullable|string',
        'height' => 'nullable|string',
        'weight' => 'nullable|string',
        'havingSpecsLenses' => 'nullable|string',
        'bloodGroup' => 'nullable|string',
        'motherTongue' => 'nullable|string',
        'language' => 'nullable|string',
        'specialCases' => 'nullable|string',
        'knowAboutMyself' => 'nullable|string',
        'religion' => 'nullable|string',
        'timeOfBirth' => 'nullable|string',
        'birthTime_ampm' => 'nullable|string',
        'placeOfBirth' => 'nullable|string',
        'degreeDiplomaName1' => 'nullable|string',
        'degreeDiplomaName2' => 'nullable|string',
        'specialisation1' => 'nullable|string',
        'specialisation2' => 'nullable|string',
        'universityBoardCollege' => 'nullable|string',
        'additionalEducation1' => 'nullable|string',
        'additionalEducation2' => 'nullable|string',
        'cityOfEducation' => 'nullable|string',
        'fieldOfWorking' => 'nullable|string',
        'organisationWorkingIn' => 'nullable|string',
        'designation' => 'nullable|string',
        'lengthOfEmployment' => 'nullable|string',
        'monthlyIncome' => 'nullable|string',
        'annualIncome' => 'nullable|string',
        'hobbies' => 'nullable|string',
        'extraCurricularActivities' => 'nullable|string',
        'achievements' => 'nullable|string',
        'sportsAndFitnessLikings' => 'nullable|string',
        'cookingSkills' => 'nullable|string',
        'activeOnSocialSites' => 'nullable|string',
        'instagram' => 'nullable|string',
        'facebook' => 'nullable|string',
        'hasMedicalHistory' => 'nullable|string',
        'personalMedicalHistory' => 'nullable|string',
        'currentMedicalProblems' => 'nullable|string',
        'medications' => 'nullable|string',
        'parents_contact' => 'nullable|digits:10',
        'fathersName' => 'nullable|string',
        'fatherAlive' => 'nullable|string',
        'fatherContactNumber' => 'nullable|string',
        'fathersOccupation' => 'nullable|string',
        'fathersNativePlace' => 'nullable|string',
        'mothersName' => 'nullable|string',
        'motherAlive' => 'nullable|string',
        'motherContactNumber' => 'nullable|string',
        'mothersOccupation' => 'nullable|string',
        'mothersNativePlace' => 'nullable|string',
        'noOfBrothers' => 'nullable|integer',
        'brothersMarried' => 'nullable|integer',
        'noOfSisters' => 'nullable|integer',
        'sistersMarried' => 'nullable|integer',
        'parentsIntercasteMarriage' => 'nullable|string',
        'parentsDifferentCaste' => 'nullable|string',
        'familyValues' => 'nullable|string',
        'familyFinancialStatus' => 'nullable|string',
        'familyAnnualIncome' => 'nullable|string',
        'profileCreatorName' => 'nullable|string',
        'profileCreatorRelation' => 'nullable|string',
        'referenceName' => 'nullable|string',
        'referenceRelation' => 'nullable|string',
        'referenceAddress' => 'nullable|string',
        'referenceContactNumber' => 'nullable|string',
        'referenceEmail' => 'nullable|string',
        'age_from' => 'nullable|integer',
        'age_to' => 'nullable|integer',
        'height_from' => 'nullable|string',
        'height_to' => 'nullable|string',
        'preferred_religion' => 'nullable|string',
        'preferred_education' => 'nullable|string',
        'working_partner' => 'nullable|string',
        'preferred_working_field' => 'nullable|string',
        'preferred_caste' => 'nullable|string',
        'preferred_sub_caste' => 'nullable|string',
        'preferred_location' => 'nullable|string',
        'expected_income' => 'nullable|string',
        'family_type' => 'nullable|string',
        'financial_background' => 'nullable|string',
        'family_values' => 'nullable|string',
        'preferred_country' => 'nullable|string',
        'preferred_state' => 'nullable|string',
        'preferred_city' => 'nullable|string',
        'other_expectations' => 'nullable|string',
        'biodata' => 'nullable|mimes:pdf,doc,docx|max:5120',
        'birth_verification_doc' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
        'candidate_photos' => 'nullable|array|max:5',
        'candidate_photos.*' => 'nullable|image|max:5120',
        'source_of_information' => 'required|string|max:255',
        'booklet_required' => 'nullable|boolean',
        'user_id' => 'nullable|integer',
        'profile_id' => 'nullable|integer',
        'profilePhoto' => 'nullable|image|max:5120',
        'identificationProof' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
        'birthDateProof' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
        'educationProof' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
        'additionalPhotos' => 'nullable|array|max:4',
        'additionalPhotos.*' => 'nullable|image|max:5120',
        ]);

        // Build timeOfBirth on the server from the selected time fields.
        $hour = $request->input('birth_hour');
        $minute = $request->input('birth_minute');
        $ampm = strtoupper(trim((string) $request->input('birthTime_ampm')));

        if (
            $hour !== null && $hour !== '' &&
            $minute !== null && $minute !== '' &&
            in_array($ampm, ['AM', 'PM'], true)
        ) {
            $hour = (int) $hour;
            $minute = (int) $minute;

            if ($hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59) {
                if ($ampm === 'AM' && $hour === 12) {
                    $hour = 0;
                } elseif ($ampm === 'PM' && $hour !== 12) {
                    $hour += 12;
                }

                $request->merge([
                    'timeOfBirth' => sprintf('%02d:%02d:00', $hour, $minute),
                ]);
            }
        }

    $mobile = trim($request->username);
    $isExisting = (int) $request->is_existing_website_user;

    $safeOldInput = $request->except([
        'biodata', 'payment_screenshot', 'birth_verification_doc',
        'candidate_photos', 'profilePhoto', 'identificationProof',
        'birthDateProof', 'educationProof', 'additionalPhotos'
    ]);

    /*
    |--------------------------------------------------------------------------
    | EXISTING WEBSITE USER
    |--------------------------------------------------------------------------
    */

    if ($isExisting === 1) {

        $user = User::where('username', $mobile)->first();

        if (!$user) {
            return back()
                ->withInput($safeOldInput)
                ->with('error', 'This mobile number is not registered on the website.');
        }

        $profile = Profile::find($user->profile_id);

        if (!$profile) {
            return back()
                ->withInput($safeOldInput)
                ->with('error', 'Profile not found for this website user.');
        }

        $data = $request->except([
            '_token',
            'payment_screenshot',
            'biodata',
            'birth_verification_doc',
            'candidate_photos'
        ]);

        $data['user_id'] = $user->id;
        $data['profile_id'] = $user->profile_id;
        $data['is_existing_website_user'] = 1;
        $data['is_booklet_req'] = $request->boolean('booklet_required');

        if ($request->hasFile('biodata')) {
            $file = $request->file('biodata');
            $filename = time() . '_' . $file->getClientOriginalName();
            $uploadDirectory = public_path('uploads/melava_biodata');
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $file->move($uploadDirectory, $filename);
            $data['biodata'] = 'uploads/melava_biodata/' . $filename;
        }

        if ($request->hasFile('birth_verification_doc')) {
            $file = $request->file('birth_verification_doc');
            $filename = time() . '_birth_' . $file->getClientOriginalName();
            $uploadDirectory = public_path('uploads/melava_birth_docs');
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $file->move($uploadDirectory, $filename);
            $data['birth_verification_doc'] = 'uploads/melava_birth_docs/' . $filename;
        }

        if ($request->hasFile('candidate_photos')) {
            $candidatePhotos = [];
            $photoUploadDir = public_path('uploads/melava_candidate_photos');
            if (!file_exists($photoUploadDir)) {
                mkdir($photoUploadDir, 0777, true);
            }
            foreach ($request->file('candidate_photos') as $index => $photo) {
                if ($photo->isValid()) {
                    $photoFilename = time() . '_photo_' . $index . '_' . $photo->getClientOriginalName();
                    $photo->move($photoUploadDir, $photoFilename);
                    $candidatePhotos[] = 'uploads/melava_candidate_photos/' . $photoFilename;
                }
            }
            if (!empty($candidatePhotos)) {
                $data['candidate_photos'] = json_encode($candidatePhotos);
            }
        }

        if ($request->hasFile('profilePhoto')) {
            $file = $request->file('profilePhoto');
            $filename = time() . '_profile_' . $file->getClientOriginalName();
            $uploadDirectory = public_path('uploads/profile_photos');
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $file->move($uploadDirectory, $filename);
            $data['profilePhoto'] = 'uploads/profile_photos/' . $filename;
        }

        if ($request->hasFile('identificationProof')) {
            $file = $request->file('identificationProof');
            $filename = time() . '_idproof_' . $file->getClientOriginalName();
            $uploadDirectory = public_path('uploads/melava_documents');
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $file->move($uploadDirectory, $filename);
            $data['identificationProof'] = 'uploads/melava_documents/' . $filename;
        }

        if ($request->hasFile('birthDateProof')) {
            $file = $request->file('birthDateProof');
            $filename = time() . '_birthproof_' . $file->getClientOriginalName();
            $uploadDirectory = public_path('uploads/melava_documents');
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $file->move($uploadDirectory, $filename);
            $data['birthDateProof'] = 'uploads/melava_documents/' . $filename;
        }

        if ($request->hasFile('educationProof')) {
            $file = $request->file('educationProof');
            $filename = time() . '_edproof_' . $file->getClientOriginalName();
            $uploadDirectory = public_path('uploads/melava_documents');
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true);
            }
            $file->move($uploadDirectory, $filename);
            $data['educationProof'] = 'uploads/melava_documents/' . $filename;
        }

        if ($request->hasFile('additionalPhotos')) {
            $additionalPhotos = [];
            $photoUploadDir = public_path('uploads/melava_additional_photos');
            if (!file_exists($photoUploadDir)) {
                mkdir($photoUploadDir, 0777, true);
            }
            foreach ($request->file('additionalPhotos') as $index => $photo) {
                if ($photo->isValid()) {
                    $photoFilename = time() . '_addphoto_' . $index . '_' . $photo->getClientOriginalName();
                    $photo->move($photoUploadDir, $photoFilename);
                    $additionalPhotos[] = 'uploads/melava_additional_photos/' . $photoFilename;
                }
            }
            if (!empty($additionalPhotos)) {
                $data['additionalPhotos'] = json_encode($additionalPhotos);
            }
        }

        $token = Str::random(40);

        MelavaPendingRegistration::create([
            'token' => $token,
            'mobile' => $data['username'],
            'is_existing_website_user' => true,
            'user_id' => $data['user_id'] ?? null,
            'profile_id' => $data['profile_id'] ?? null,
            'registration_data' => json_encode($data),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        return redirect()->route(
            'melava.public_payment_page',
            $token
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NEW WEBSITE USER
    |--------------------------------------------------------------------------
    */

    $existingUser = User::where('username', $mobile)->first();

    if ($existingUser) {
        return back()
            ->withInput($safeOldInput)
            ->with('error',
                'This mobile number is already website registered. Please select "Yes, already registered" and fetch your details.'
            );
    }

    $data = $request->except([
        '_token',
        'payment_screenshot',
        'biodata',
        'birth_verification_doc',
        'candidate_photos',
        'profilePhoto',
        'identificationProof',
        'birthDateProof',
        'educationProof',
        'additionalPhotos'
    ]);

    $data['is_existing_website_user'] = 0;
    $data['user_id'] = null;
    $data['profile_id'] = null;
    $data['is_booklet_req'] = $request->boolean('booklet_required');

    if ($request->hasFile('biodata')) {
        $file = $request->file('biodata');
        $filename = time() . '_' . $file->getClientOriginalName();
        $uploadDirectory = public_path('uploads/melava_biodata');
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }
        $file->move($uploadDirectory, $filename);
        $data['biodata'] = 'uploads/melava_biodata/' . $filename;
    }

    if ($request->hasFile('birth_verification_doc')) {
        $file = $request->file('birth_verification_doc');
        $filename = time() . '_birth_' . $file->getClientOriginalName();
        $uploadDirectory = public_path('uploads/melava_birth_docs');
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }
        $file->move($uploadDirectory, $filename);
        $data['birth_verification_doc'] = 'uploads/melava_birth_docs/' . $filename;
    }

    if ($request->hasFile('candidate_photos')) {
        $candidatePhotos = [];
        $photoUploadDir = public_path('uploads/melava_candidate_photos');
        if (!file_exists($photoUploadDir)) {
            mkdir($photoUploadDir, 0777, true);
        }
        foreach ($request->file('candidate_photos') as $index => $photo) {
            if ($photo->isValid()) {
                $photoFilename = time() . '_photo_' . $index . '_' . $photo->getClientOriginalName();
                $photo->move($photoUploadDir, $photoFilename);
                $candidatePhotos[] = 'uploads/melava_candidate_photos/' . $photoFilename;
            }
        }
        if (!empty($candidatePhotos)) {
            $data['candidate_photos'] = json_encode($candidatePhotos);
        }
    }

    if ($request->hasFile('profilePhoto')) {
        $file = $request->file('profilePhoto');
        $filename = time() . '_profile_' . $file->getClientOriginalName();
        $uploadDirectory = public_path('uploads/profile_photos');
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }
        $file->move($uploadDirectory, $filename);
        $data['profilePhoto'] = 'uploads/profile_photos/' . $filename;
    }

    if ($request->hasFile('identificationProof')) {
        $file = $request->file('identificationProof');
        $filename = time() . '_idproof_' . $file->getClientOriginalName();
        $uploadDirectory = public_path('uploads/melava_documents');
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }
        $file->move($uploadDirectory, $filename);
        $data['identificationProof'] = 'uploads/melava_documents/' . $filename;
    }

    if ($request->hasFile('birthDateProof')) {
        $file = $request->file('birthDateProof');
        $filename = time() . '_birthproof_' . $file->getClientOriginalName();
        $uploadDirectory = public_path('uploads/melava_documents');
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }
        $file->move($uploadDirectory, $filename);
        $data['birthDateProof'] = 'uploads/melava_documents/' . $filename;
    }

    if ($request->hasFile('educationProof')) {
        $file = $request->file('educationProof');
        $filename = time() . '_edproof_' . $file->getClientOriginalName();
        $uploadDirectory = public_path('uploads/melava_documents');
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }
        $file->move($uploadDirectory, $filename);
        $data['educationProof'] = 'uploads/melava_documents/' . $filename;
    }

    if ($request->hasFile('additionalPhotos')) {
        $additionalPhotos = [];
        $photoUploadDir = public_path('uploads/melava_additional_photos');
        if (!file_exists($photoUploadDir)) {
            mkdir($photoUploadDir, 0777, true);
        }
        foreach ($request->file('additionalPhotos') as $index => $photo) {
            if ($photo->isValid()) {
                $photoFilename = time() . '_addphoto_' . $index . '_' . $photo->getClientOriginalName();
                $photo->move($photoUploadDir, $photoFilename);
                $additionalPhotos[] = 'uploads/melava_additional_photos/' . $photoFilename;
            }
        }
        if (!empty($additionalPhotos)) {
            $data['additionalPhotos'] = json_encode($additionalPhotos);
        }
    }

    $token = Str::random(40);

    MelavaPendingRegistration::create([
        'token' => $token,
        'mobile' => $data['username'],
        'is_existing_website_user' => false,
        'user_id' => null,
        'profile_id' => null,
        'registration_data' => json_encode($data),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    return redirect()->route(
        'melava.public_payment_page',
        $token
    );
}

    public function publicPaymentPage($token)
    {
        // Find the pending registration using its unique token
        $pendingRegistration = MelavaPendingRegistration::where(
            'token',
            $token
        )
        ->where('status', 'pending')
        ->where(function ($query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })
        ->first();

        // If no valid pending registration exists, return to registration
        if (!$pendingRegistration) {
            return redirect()
                ->route('melava.public_create')
                ->with(
                    'error',
                    'Your registration link is invalid or has expired. Please register again.'
                );
        }

        // Decode the saved registration details
        $data = json_decode(
            $pendingRegistration->registration_data,
            true
        );

        if (!is_array($data)) {
            return redirect()
                ->route('melava.public_create')
                ->with(
                    'error',
                    'Unable to retrieve your registration details. Please contact support.'
                );
        }

        // Display the existing payment page
        return view('admin.melava.payment', [
            'data' => $data,
            'token' => $pendingRegistration->token,
        ]);
    }

    public function publicShow(Request $request, $id)
    {
        $registration = MelavaRegistration::findOrFail($id);

        if ($registration->link_token !== $request->query('token')) {
            abort(403, 'This link has expired or is invalid.');
        }

        return view('admin.melava.public_create', compact('registration'));
    }

    /**
     * Handle the public form submission.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function publicUpdate(Request $request, $id)
    {
        $registration = MelavaRegistration::findOrFail($id);

        // Validate Token
        if ($registration->link_token !== $request->input('token')) {
            abort(403, 'This link has expired or is invalid.');
        }

        $validatedData = $request->validate([
            'fullName' => 'required|string|max:255',
            'groomOrBride' => 'required|string',
            'birthDate' => 'required|date',
            'username' => 'required|digits:10',
            'maritalStatus' => 'nullable|string',
            'caste' => 'nullable|string',
            'subCaste' => 'nullable|string',
            'education' => 'nullable|string',
            'profession' => 'required|string',
            'city' => 'required|string',
            'height' => 'nullable|string',
            'parents_contact' => 'nullable|digits:10',
            'fathersName' => 'nullable|string',
            'mothersName' => 'nullable|string',
            'noOfBrothers' => 'nullable|integer',
            'noOfSisters' => 'nullable|integer',
            'age_from' => 'nullable|integer',
            'age_to' => 'nullable|integer',
            'height_from' => 'nullable|string',
            'height_to' => 'nullable|string',
            'preferred_education' => 'nullable|string',
            'preferred_working_field' => 'nullable|string',
            'preferred_caste' => 'nullable|string',
            'preferred_location' => 'nullable|string',
            'other_expectations' => 'nullable|string',
            'biodata' => 'nullable|mimes:pdf,doc,docx|max:5120',
            'birth_verification_doc' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
            'candidate_photos' => 'nullable|array|max:5',
            'candidate_photos.*' => 'nullable|image|max:5120',
            'source_of_information' => 'required|string|max:255',
            'booklet_required' => 'nullable|boolean',
        ]);

        $data = $request->all();
        $data['is_booklet_req'] = $request->boolean('booklet_required');

        DB::beginTransaction();
        try {
            if ($request->hasFile('biodata')) {
                $file = $request->file('biodata');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/melava_biodata'), $filename);
                $data['biodata'] = 'uploads/melava_biodata/' . $filename;
            }

            if ($request->hasFile('birth_verification_doc')) {
                $file = $request->file('birth_verification_doc');
                $filename = time() . '_birth_' . $file->getClientOriginalName();
                $uploadDirectory = public_path('uploads/melava_birth_docs');
                if (!file_exists($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true);
                }
                $file->move($uploadDirectory, $filename);
                $data['birth_verification_doc'] = 'uploads/melava_birth_docs/' . $filename;
            }

            if ($request->hasFile('candidate_photos')) {
                $existingPhotos = $registration->candidate_photos ?? [];
                if (!is_array($existingPhotos)) {
                    $existingPhotos = [];
                }
                $photoUploadDir = public_path('uploads/melava_candidate_photos');
                if (!file_exists($photoUploadDir)) {
                    mkdir($photoUploadDir, 0777, true);
                }
                foreach ($request->file('candidate_photos') as $index => $photo) {
                    if ($photo->isValid()) {
                        $photoFilename = time() . '_photo_' . $index . '_' . $photo->getClientOriginalName();
                        $photo->move($photoUploadDir, $photoFilename);
                        $existingPhotos[] = 'uploads/melava_candidate_photos/' . $photoFilename;
                    }
                }
                $data['candidate_photos'] = json_encode($existingPhotos);
            }

            $data['link_token'] = Str::random(40);

            $registration->update($data);

            DB::commit();
            return redirect()->route('melava.public_success')
                ->with('success', 'Registration updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in Melava publicUpdate: " . $e->getMessage());
            return back()
                ->withInput($request->except(['biodata', 'birth_verification_doc', 'candidate_photos']))
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    /**
     * Show the success page with QR code.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
public function publicSuccess()
{
    return view('admin.melava.success');
}

    /**
     * Handle public payment screenshot upload.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // public function publicUploadPayment(Request $request, $id)
    // {
    //     $registration = MelavaRegistration::findOrFail($id);

    //     $request->validate([
    //         'payment_screenshot' => 'required|image|max:5120', // 5MB max
    //     ]);

    //     if ($request->hasFile('payment_screenshot')) {
    //         $file = $request->file('payment_screenshot');
    //         $filename = time() . '_payment_' . $file->getClientOriginalName();
    //         $file->move(public_path('uploads/payment_screenshots'), $filename);
            
    //         $registration->payment_screenshot = 'uploads/payment_screenshots/' . $filename;
    //         $registration->save();
    //     }

    //     return redirect()->back()->with('success', 'Payment screenshot uploaded successfully. Your registration is under review.');
    // }

    /**
     * Generate and download payment receipt.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function generateReceipt($id)
    {
        $registration = MelavaRegistration::findOrFail($id);

        return view('admin.melava.receipt', compact('registration'));
    }

public function checkExistingWebsiteUser(Request $request)
{
    $request->validate([
        'mobile' => 'required|digits:10',
    ]);

    $mobile = trim($request->mobile);

    // 1. Check for an existing pending Melava registration.
    $pending = MelavaPendingRegistration::where('mobile', $mobile)
        ->where('status', 'pending')
        ->where(function ($query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })
        ->latest('id')
        ->first();

    if ($pending) {
        $registrationData = json_decode(
            $pending->registration_data,
            true
        );

        if (is_array($registrationData)) {
            return response()->json([
                'success' => true,
                'type' => 'pending_registration',
                'message' => 'Your previous Melava registration was found. Your saved details can be restored.',
                'pending_registration' => [
                    'token' => $pending->token,
                    'registration_data' => $registrationData,
                    'is_existing_website_user' => (bool) $pending->is_existing_website_user,
                    'user_id' => $pending->user_id,
                    'profile_id' => $pending->profile_id,
                ],
            ]);
        }
    }

    // 2. No valid pending registration: check the website user.
    $user = User::where('username', $mobile)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'type' => 'new_candidate',
            'message' => 'No previous registration was found. You can continue as a new candidate.',
        ]);
    }

    if (!$user->profile_id) {
        return response()->json([
            'success' => false,
            'type' => 'website_user_without_profile',
            'message' => 'A website account exists, but its profile information is unavailable.',
        ]);
    }

    $profile = Profile::find($user->profile_id);

    if (!$profile) {
        return response()->json([
            'success' => false,
            'type' => 'website_user_without_profile',
            'message' => 'A website account exists, but its profile information is unavailable.',
        ]);
    }

    return response()->json([
        'success' => true,
        'type' => 'website_user',
        'message' => 'Website registration found.',
        'user' => [
            'id' => $user->id,
            'profile_id' => $user->profile_id,
            'username' => $user->username,
        ],
        'profile' => $profile,
    ]);
}

public function checkMobileForNewRegistration(Request $request)
{
    $request->validate([
        'mobile' => 'required|digits:10',
    ]);

    $mobile = trim($request->mobile);

    $user = User::where('username', $mobile)->first();

    if ($user) {
        return response()->json([
            'exists' => true,
            'message' => 'This mobile number is already website registered. Please select "Yes, I am already registered".'
        ], 409);
    }

    return response()->json([
        'exists' => false,
        'message' => 'This mobile number is available.'
    ]);
}

public function publicUploadPayment(Request $request, $token)
{
    // ---------------------------------------------------------
    // 1. Validate payment screenshot
    // ---------------------------------------------------------

    $request->validate([
        'payment_screenshot' => 'required|image|max:5120',
    ]);

    // ---------------------------------------------------------
    // 2. Retrieve pending registration from DATABASE
    // ---------------------------------------------------------

    $pendingRegistration = MelavaPendingRegistration::where(
        'token',
        $token
    )
    ->where('status', 'pending')
    ->where(function ($query) {
        $query->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
    })
    ->first();

    // ---------------------------------------------------------
    // 3. Validate pending registration
    // ---------------------------------------------------------

    if (!$pendingRegistration) {
        return redirect()
            ->route('melava.public_create')
            ->with(
                'error',
                'Your registration link is invalid or has expired. Please register again.'
            );
    }

    // Decode saved registration details
    $data = json_decode(
        $pendingRegistration->registration_data,
        true
    );

    if (!is_array($data)) {
        return redirect()
            ->route('melava.public_create')
            ->with(
                'error',
                'Unable to retrieve your registration details. Please contact support.'
            );
    }

    // ---------------------------------------------------------
    // 4. Basic registration data validation
    // ---------------------------------------------------------

    if (
        empty($data['username']) ||
        empty($data['fullName'])
    ) {
        return redirect()
            ->route('melava.public_create')
            ->with(
                'error',
                'Registration information is incomplete. Please register again.'
            );
    }

    // ---------------------------------------------------------
    // 5. Determine registration type
    // ---------------------------------------------------------

    $isExisting = (
        isset($data['is_existing_website_user']) &&
        (int) $data['is_existing_website_user'] === 1
    );

    // ---------------------------------------------------------
    // 5b. FIELD ALIAS NORMALIZATION
    //     - form sends professionalCity, old code uses city
    //     - form sends referenceContactNumber, old code uses parents_contact
    //     - form sends additionalPhotos[] vs additional_photos column
    // ---------------------------------------------------------
    if (empty($data['city']) && !empty($data['professionalCity'])) {
        $data['city'] = $data['professionalCity'];
    }
    if (empty($data['professionalCity']) && !empty($data['city'])) {
        $data['professionalCity'] = $data['city'];
    }
    if (empty($data['parents_contact']) && !empty($data['referenceContactNumber'])) {
        $data['parents_contact'] = $data['referenceContactNumber'];
    }
    if (empty($data['referenceContactNumber']) && !empty($data['parents_contact'])) {
        $data['referenceContactNumber'] = $data['parents_contact'];
    }

    DB::beginTransaction();

    try {

        // =====================================================
        // 6. UPLOAD PAYMENT SCREENSHOT
        // =====================================================

        if (!$request->hasFile('payment_screenshot')) {
            throw new \Exception(
                'Payment screenshot is required.'
            );
        }

        $file = $request->file('payment_screenshot');

        $filename =
            time() .
            '_payment_' .
            $file->getClientOriginalName();

        $uploadDirectory =
            public_path('uploads/payment_screenshots');

        // Create directory if it doesn't exist
        if (!file_exists($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        $file->move(
            $uploadDirectory,
            $filename
        );

        $paymentPath =
            'uploads/payment_screenshots/' . $filename;


        // =====================================================
        // VARIABLES
        // =====================================================

        $user = null;
        $profile = null;


        // =====================================================
        // 7. EXISTING WEBSITE USER
        // =====================================================

        if ($isExisting) {

            /*
             * User selected YES.
             *
             * We MUST use the existing:
             *
             * users
             * profiles
             *
             * We must NOT create:
             *
             * new user
             * new profile
             * new preference
             */

            $user = User::where(
                'username',
                $data['username']
            )->first();

            if (!$user) {

                throw new \Exception(
                    'Existing website user was not found.'
                );
            }


            // -------------------------------------------------
            // Find existing profile
            // -------------------------------------------------

            if (!$user->profile_id) {

                throw new \Exception(
                    'Existing website user does not have a profile.'
                );
            }

            $profile = Profile::find(
                $user->profile_id
            );

            if (!$profile) {

                throw new \Exception(
                    'Profile for the existing website user was not found.'
                );
            }

            // -------------------------------------------------
            // UPDATE existing profile with melava form data
            // -------------------------------------------------

            $prefix = 'MM3-';
            $lastProfile = \App\Profile::where('registration_number', 'like', $prefix . '%')
                ->orderByRaw('CAST(SUBSTRING(registration_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
                ->first();

            $nextNumber = 1;
            if ($lastProfile && preg_match('/' . $prefix . '(?:B|G)?(\d+)$/', $lastProfile->registration_number, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }
            $newRegistrationNumber = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Existing Melava-2 candidates must be issued a fresh
            // age-group-wise Melava 3 serial. If they do not already hold a
            // current-batch serial, clear the stale one so the Profile saving
            // hook regenerates it.
            $hasCurrentSerial = Profile::hasCurrentMelavaSerial($profile->serial_no, $profile->melava_batch);

            $profileUpdateData = [
                // Identifiers
                'registration_number'       => $newRegistrationNumber,
                'profile_id'                => $newRegistrationNumber,
                'registration_type'         => 'existing',
                'serial_no'                 => $hasCurrentSerial ? $profile->serial_no : null,

                // Basic Details
                'fullName'                  => $data['fullName'] ?? $profile->fullName,
                'groomOrBride'              => $data['groomOrBride'] ?? $profile->groomOrBride,
                'birthDate'                 => $data['birthDate'] ?? $profile->birthDate,
                'maritalStatus'             => $data['maritalStatus'] ?? $profile->maritalStatus,
                'caste'                     => $data['caste'] ?? $profile->caste,
                'subCaste'                  => $data['subCaste'] ?? $profile->subCaste,
                'religion'                  => $data['religion'] ?? $profile->religion,
                'weight'                    => $data['weight'] ?? $profile->weight,
                'havingSpecsLenses'         => $data['havingSpecsLenses'] ?? $profile->havingSpecsLenses,
                'bloodGroup'                => $data['bloodGroup'] ?? $profile->bloodGroup,
                'motherTongue'              => $data['motherTongue'] ?? $profile->motherTongue,
                'language'                  => $data['language'] ?? $profile->language,
                'specialCases'              => $data['specialCases'] ?? $profile->specialCases,
                'knowAboutMyself'           => $data['knowAboutMyself'] ?? $profile->knowAboutMyself,
                'address'                   => $data['address'] ?? $profile->address,
                'permanent_address'         => $data['permanent_address'] ?? $profile->permanent_address,
                'height'                    => $data['height'] ?? $profile->height,
                // Horoscope
                'timeOfBirth'               => $data['timeOfBirth'] ?? $profile->timeOfBirth,
                'birthTime_ampm'            => $data['birthTime_ampm'] ?? $profile->birthTime_ampm,
                'placeOfBirth'              => $data['placeOfBirth'] ?? $profile->placeOfBirth,
                // Educational
                'education'                 => $data['education'] ?? $profile->education,
                'degreeDiplomaName1'        => $data['degreeDiplomaName1'] ?? $profile->degreeDiplomaName1,
                'degreeDiplomaName2'        => $data['degreeDiplomaName2'] ?? $profile->degreeDiplomaName2,
                'specialisation1'           => $data['specialisation1'] ?? $profile->specialisation1,
                'specialisation2'           => $data['specialisation2'] ?? $profile->specialisation2,
                'universityBoardCollege'    => $data['universityBoardCollege'] ?? $profile->universityBoardCollege,
                'additionalEducation1'      => $data['additionalEducation1'] ?? $profile->additionalEducation1,
                'additionalEducation2'      => $data['additionalEducation2'] ?? $profile->additionalEducation2,
                'cityOfEducation'           => $data['cityOfEducation'] ?? $profile->cityOfEducation,
                // Professional
                'profession'                => $data['profession'] ?? $profile->profession,
                'fieldOfWorking'            => $data['fieldOfWorking'] ?? $profile->fieldOfWorking,
                'organisationWorkingIn'     => $data['organisationWorkingIn'] ?? $profile->organisationWorkingIn,
                'professionalCity'          => $data['professionalCity'] ?? $data['city'] ?? $profile->professionalCity,
                'designation'               => $data['designation'] ?? $profile->designation,
                'lengthOfEmployment'        => $data['lengthOfEmployment'] ?? $profile->lengthOfEmployment,
                'monthlyIncome'             => $data['monthlyIncome'] ?? $profile->monthlyIncome,
                'annualIncome'              => $data['annualIncome'] ?? $profile->annualIncome,
                // Hobbies
                'hobbies'                   => $data['hobbies'] ?? $profile->hobbies,
                'extraCurricularActivities' => $data['extraCurricularActivities'] ?? $profile->extraCurricularActivities,
                'achievements'              => $data['achievements'] ?? $profile->achievements,
                'sportsAndFitnessLikings'   => $data['sportsAndFitnessLikings'] ?? $profile->sportsAndFitnessLikings,
                'cookingSkills'             => $data['cookingSkills'] ?? $profile->cookingSkills,
                'activeOnSocialSites'       => $data['activeOnSocialSites'] ?? $profile->activeOnSocialSites,
                'instagram'                 => $data['instagram'] ?? $profile->instagram,
                'facebook'                  => $data['facebook'] ?? $profile->facebook,
                // Medical
                'hasMedicalHistory'         => $data['hasMedicalHistory'] ?? $profile->hasMedicalHistory,
                'personalMedicalHistory'    => $data['personalMedicalHistory'] ?? $profile->personalMedicalHistory,
                'currentMedicalProblems'    => $data['currentMedicalProblems'] ?? $profile->currentMedicalProblems,
                'medications'               => $data['medications'] ?? $profile->medications,
                // Family
                'fathersName'               => $data['fathersName'] ?? $profile->fathersName,
                'fatherAlive'               => $data['fatherAlive'] ?? $profile->fatherAlive,
                'fatherContactNumber'       => $data['fatherContactNumber'] ?? $profile->fatherContactNumber,
                'fathersOccupation'         => $data['fathersOccupation'] ?? $profile->fathersOccupation,
                'fathersNativePlace'        => $data['fathersNativePlace'] ?? $profile->fathersNativePlace,
                'mothersName'               => $data['mothersName'] ?? $profile->mothersName,
                'motherAlive'               => $data['motherAlive'] ?? $profile->motherAlive,
                'motherContactNumber'       => $data['motherContactNumber'] ?? $profile->motherContactNumber,
                'mothersOccupation'         => $data['mothersOccupation'] ?? $profile->mothersOccupation,
                'mothersNativePlace'        => $data['mothersNativePlace'] ?? $profile->mothersNativePlace,
                'noOfBrothers'              => $data['noOfBrothers'] ?? $profile->noOfBrothers,
                'brothersMarried'           => $data['brothersMarried'] ?? $profile->brothersMarried,
                'noOfSisters'               => $data['noOfSisters'] ?? $profile->noOfSisters,
                'sistersMarried'            => $data['sistersMarried'] ?? $profile->sistersMarried,
                'parentsIntercasteMarriage' => $data['parentsIntercasteMarriage'] ?? $profile->parentsIntercasteMarriage,
                'parentsDifferentCaste'     => $data['parentsDifferentCaste'] ?? $profile->parentsDifferentCaste,
                'familyValues'              => $data['familyValues'] ?? $profile->familyValues,
                'familyFinancialStatus'     => $data['familyFinancialStatus'] ?? $profile->familyFinancialStatus,
                'familyAnnualIncome'        => $data['familyAnnualIncome'] ?? $profile->familyAnnualIncome,
                'profileCreatorName'        => $data['profileCreatorName'] ?? $profile->profileCreatorName,
                'profileCreatorRelation'    => $data['profileCreatorRelation'] ?? $profile->profileCreatorRelation,
                // Reference
                'referenceName'             => $data['referenceName'] ?? $profile->referenceName,
                'referenceRelation'         => $data['referenceRelation'] ?? $profile->referenceRelation,
                'referenceAddress'          => $data['referenceAddress'] ?? $profile->referenceAddress,
                'referenceContactNumber'    => $data['referenceContactNumber'] ?? $data['parents_contact'] ?? $profile->referenceContactNumber,
                'referenceEmail'            => $data['referenceEmail'] ?? $profile->referenceEmail,
                // Meta
                'source_of_information'     => $data['source_of_information'] ?? $profile->source_of_information,
                'phone'                     => $data['username'] ?? $profile->phone,
                'melava_batch'              => self::CURRENT_MELAVA_BATCH,
            ];

            // Profile Photo
            if (!empty($data['profilePhoto'])) {
                $profileUpdateData['profilePhoto'] = $data['profilePhoto'];
            } elseif (!empty($data['candidate_photos'])) {
                $cp = is_string($data['candidate_photos']) ? json_decode($data['candidate_photos'], true) : $data['candidate_photos'];
                if (is_array($cp) && count($cp) > 0) {
                    $profileUpdateData['profilePhoto'] = $cp[0];
                }
            }
            // Legacy biodata -> identificationProof
            if (!empty($data['biodata'])) {
                $profileUpdateData['identificationProof'] = $data['biodata'];
            }
            // New field name identificationProof
            if (!empty($data['identificationProof'])) {
                $profileUpdateData['identificationProof'] = $data['identificationProof'];
            }
            // Legacy birth_verification_doc -> birthDateProof (+ fallback id proof)
            if (!empty($data['birth_verification_doc'])) {
                $profileUpdateData['birthDateProof'] = $data['birth_verification_doc'];
                if (empty($profileUpdateData['identificationProof'])) {
                    $profileUpdateData['identificationProof'] = $data['birth_verification_doc'];
                }
            }
            // New field name birthDateProof
            if (!empty($data['birthDateProof'])) {
                $profileUpdateData['birthDateProof'] = $data['birthDateProof'];
                if (empty($profileUpdateData['identificationProof'])) {
                    $profileUpdateData['identificationProof'] = $data['birthDateProof'];
                }
            }
            // Education Proof
            if (!empty($data['educationProof'])) {
                $profileUpdateData['educationProof'] = $data['educationProof'];
            }
            // Candidate photos (legacy) -> additional_photos
            if (!empty($data['candidate_photos'])) {
                $profileUpdateData['additional_photos'] = $data['candidate_photos'];
            }
            // New field additionalPhotos[] -> additional_photos (merge with existing)
            if (!empty($data['additionalPhotos'])) {
                $existingPhotos = is_string($profile->additional_photos) ? json_decode($profile->additional_photos, true) : ($profile->additional_photos ?? []);
                if (!is_array($existingPhotos)) $existingPhotos = [];
                $newPhotos = is_string($data['additionalPhotos']) ? json_decode($data['additionalPhotos'], true) : $data['additionalPhotos'];
                if (is_array($newPhotos)) {
                    $merged = array_merge($existingPhotos, $newPhotos);
                    $profileUpdateData['additional_photos'] = json_encode($merged);
                }
            }

            $profile->update($profileUpdateData);

            // -------------------------------------------------
            // UPDATE existing user's Preference if exists
            // -------------------------------------------------
            $existingPref = Preference::where('user_id', $user->id)->first();
            if ($existingPref) {
                $prefUpdateData = [
                    'preferred_religion'      => $data['preferred_religion'] ?? $existingPref->preferred_religion,
                    'preferred_caste'         => $data['preferred_caste'] ?? $existingPref->preferred_caste,
                    'preferred_sub_caste'     => $data['preferred_sub_caste'] ?? $existingPref->preferred_sub_caste,
                    'age_from'                => isset($data['age_from']) ? (int) $data['age_from'] : $existingPref->age_from,
                    'age_to'                  => isset($data['age_to']) ? (int) $data['age_to'] : $existingPref->age_to,
                    'height_from'             => $data['height_from'] ?? $existingPref->height_from,
                    'height_to'               => $data['height_to'] ?? $existingPref->height_to,
                    'preferred_education'     => $data['preferred_education'] ?? $existingPref->preferred_education,
                    'working_partner'         => $data['working_partner'] ?? $existingPref->working_partner,
                    'preferred_working_field' => $data['preferred_working_field'] ?? $existingPref->preferred_working_field,
                    'expected_income'         => $data['expected_income'] ?? $existingPref->expected_income,
                    'family_type'             => $data['family_type'] ?? $existingPref->family_type,
                    'financial_background'    => $data['financial_background'] ?? $existingPref->financial_background,
                    'family_values'           => $data['family_values'] ?? $existingPref->family_values,
                    'preferred_country'       => $data['preferred_country'] ?? $existingPref->preferred_country,
                    'preferred_state'         => $data['preferred_state'] ?? $existingPref->preferred_state,
                    'preferred_city'          => $data['preferred_city'] ?? $data['preferred_location'] ?? $existingPref->preferred_city,
                    'preferred_location'      => $data['preferred_city'] ?? $data['preferred_location'] ?? $existingPref->preferred_location,
                    'other_expectations'      => $data['other_expectations'] ?? $existingPref->other_expectations,
                ];
                $existingPref->update($prefUpdateData);
            } else {
                // Create preference if missing
                Preference::create([
                    'user_id'                   => $user->id,
                    'preferred_religion'        => $data['preferred_religion'] ?? 'Jain',
                    'preferred_caste'           => $data['preferred_caste'] ?? null,
                    'preferred_sub_caste'       => $data['preferred_sub_caste'] ?? null,
                    'age_from'                  => isset($data['age_from']) ? (int) $data['age_from'] : null,
                    'age_to'                    => isset($data['age_to']) ? (int) $data['age_to'] : null,
                    'height_from'               => $data['height_from'] ?? null,
                    'height_to'                 => $data['height_to'] ?? null,
                    'preferred_education'       => $data['preferred_education'] ?? null,
                    'working_partner'           => $data['working_partner'] ?? null,
                    'preferred_working_field'   => $data['preferred_working_field'] ?? null,
                    'expected_income'           => $data['expected_income'] ?? null,
                    'family_type'               => $data['family_type'] ?? null,
                    'financial_background'      => $data['financial_background'] ?? null,
                    'family_values'             => $data['family_values'] ?? null,
                    'preferred_country'         => $data['preferred_country'] ?? 'India',
                    'preferred_state'           => $data['preferred_state'] ?? 'Maharashtra',
                    'preferred_city'            => $data['preferred_city'] ?? $data['preferred_location'] ?? null,
                    'preferred_location'        => $data['preferred_city'] ?? $data['preferred_location'] ?? null,
                    'other_expectations'        => $data['other_expectations'] ?? null,
                ]);
            }

        }


        // =====================================================
        // 8. NEW WEBSITE USER
        // =====================================================

        else {

            /*
             * User selected NO.
             *
             * Before creating anything, perform a FINAL
             * duplicate check.
             *
             * This protects against someone registering the
             * same mobile number between the first JS check
             * and payment submission.
             */

            $existingUser = User::where(
                'username',
                $data['username']
            )->first();

            if ($existingUser) {

                DB::rollBack();

                return redirect()
                    ->route('melava.public_create')
                    ->with(
                        'error',
                        'This mobile number is already website registered. Please select "Yes" for existing website user.'
                    );
            }


            // =================================================
            // VALIDATE NOT-NULL PROFILE COLUMNS BEFORE INSERT
            // =================================================

            if (empty($data['birthDate'])) {
                throw new \Exception('Date of Birth is required.');
            }
            if (empty($data['groomOrBride'])) {
                throw new \Exception('Groom / Bride selection is required.');
            }
            if (empty($data['fullName'])) {
                throw new \Exception('Full Name is required.');
            }


            // =================================================
            // CREATE PROFILE
            // =================================================

            // Merge additional_photos sources (candidate_photos legacy + additionalPhotos new)
            $mergedAdditionalPhotos = [];
            if (!empty($data['candidate_photos'])) {
                $cp = is_string($data['candidate_photos']) ? json_decode($data['candidate_photos'], true) : $data['candidate_photos'];
                if (is_array($cp)) $mergedAdditionalPhotos = array_merge($mergedAdditionalPhotos, $cp);
            }
            if (!empty($data['additionalPhotos'])) {
                $ap = is_string($data['additionalPhotos']) ? json_decode($data['additionalPhotos'], true) : $data['additionalPhotos'];
                if (is_array($ap)) $mergedAdditionalPhotos = array_merge($mergedAdditionalPhotos, $ap);
            }
            $mergedAdditionalPhotosEncoded = !empty($mergedAdditionalPhotos) ? json_encode($mergedAdditionalPhotos) : null;

            // Compute which document serves as identificationProof
            $idProof = null;
            if (!empty($data['identificationProof'])) {
                $idProof = $data['identificationProof'];
            } elseif (!empty($data['biodata'])) {
                $idProof = $data['biodata'];
            } elseif (!empty($data['birthDateProof'])) {
                $idProof = $data['birthDateProof'];
            } elseif (!empty($data['birth_verification_doc'])) {
                $idProof = $data['birth_verification_doc'];
            }

            $birthProof = null;
            if (!empty($data['birthDateProof'])) {
                $birthProof = $data['birthDateProof'];
            } elseif (!empty($data['birth_verification_doc'])) {
                $birthProof = $data['birth_verification_doc'];
            }

            $profilePhotoVal = 'default.png';
            if (!empty($data['profilePhoto'])) {
                $profilePhotoVal = $data['profilePhoto'];
            } elseif (!empty($mergedAdditionalPhotos)) {
                $profilePhotoVal = $mergedAdditionalPhotos[0];
            }

            $profile = Profile::create([
                // Registration meta
                'registration_type'         => 'melava',
                'melava_batch'              => self::CURRENT_MELAVA_BATCH,

                // Basic Details
                'fullName'                  => $data['fullName'],
                'groomOrBride'              => $data['groomOrBride'],
                'profilePhoto'              => $profilePhotoVal,
                'religion'                  => $data['religion'] ?? 'Jain',
                'birthDate'                 => $data['birthDate'],
                'maritalStatus'             => $data['maritalStatus'] ?? '',
                'caste'                     => $data['caste'] ?? '',
                'subCaste'                  => $data['subCaste'] ?? '',
                'weight'                    => $data['weight'] ?? '',
                'havingSpecsLenses'         => $data['havingSpecsLenses'] ?? 'None',
                'bloodGroup'                => $data['bloodGroup'] ?? '',
                'motherTongue'              => $data['motherTongue'] ?? '',
                'language'                  => $data['language'] ?? '',
                'specialCases'              => $data['specialCases'] ?? '',
                'knowAboutMyself'           => $data['knowAboutMyself'] ?? '',
                'address'                   => $data['address'] ?? '',
                'permanent_address'         => $data['permanent_address'] ?? $data['address'] ?? '',
                'height'                    => $data['height'] ?? '',

                // Horoscope
                'timeOfBirth'               => !empty($data['timeOfBirth']) ? $data['timeOfBirth'] : null,
                'birthTime_ampm'            => $data['birthTime_ampm'] ?? '',
                'placeOfBirth'              => $data['placeOfBirth'] ?? '',

                // Educational
                'education'                 => $data['education'] ?? '',
                'degreeDiplomaName1'        => $data['degreeDiplomaName1'] ?? '',
                'degreeDiplomaName2'        => $data['degreeDiplomaName2'] ?? '',
                'specialisation1'           => $data['specialisation1'] ?? '',
                'specialisation2'           => $data['specialisation2'] ?? '',
                'universityBoardCollege'    => $data['universityBoardCollege'] ?? '',
                'additionalEducation1'      => $data['additionalEducation1'] ?? '',
                'additionalEducation2'      => $data['additionalEducation2'] ?? '',
                'cityOfEducation'           => $data['cityOfEducation'] ?? '',

                // Professional
                'profession'                => $data['profession'] ?? '',
                'fieldOfWorking'            => $data['fieldOfWorking'] ?? '',
                'organisationWorkingIn'     => $data['organisationWorkingIn'] ?? '',
                'professionalCity'          => $data['professionalCity'] ?? $data['city'] ?? '',
                'designation'               => $data['designation'] ?? '',
                'lengthOfEmployment'        => $data['lengthOfEmployment'] ?? '',
                'monthlyIncome'             => $data['monthlyIncome'] ?? '',
                'annualIncome'              => $data['annualIncome'] ?? '',
                'income_currency'           => 'INR',

                // Hobbies
                'hobbies'                   => $data['hobbies'] ?? '',
                'extraCurricularActivities' => $data['extraCurricularActivities'] ?? '',
                'achievements'              => $data['achievements'] ?? '',
                'sportsAndFitnessLikings'   => $data['sportsAndFitnessLikings'] ?? '',
                'cookingSkills'             => $data['cookingSkills'] ?? '',
                'activeOnSocialSites'       => $data['activeOnSocialSites'] ?? '',
                'instagram'                 => $data['instagram'] ?? '',
                'facebook'                  => $data['facebook'] ?? '',

                // Medical
                'personalMedicalHistory'    => $data['personalMedicalHistory'] ?? '',
                'currentMedicalProblems'    => $data['currentMedicalProblems'] ?? '',
                'medications'               => $data['medications'] ?? '',

                // Family
                'fathersName'               => $data['fathersName'] ?? '',
                'fatherAlive'               => $data['fatherAlive'] ?? '',
                'fatherContactNumber'       => $data['fatherContactNumber'] ?? '',
                'fathersOccupation'         => $data['fathersOccupation'] ?? '',
                'fathersNativePlace'        => $data['fathersNativePlace'] ?? '',
                'mothersName'               => $data['mothersName'] ?? '',
                'motherAlive'               => $data['motherAlive'] ?? '',
                'motherContactNumber'       => $data['motherContactNumber'] ?? '',
                'mothersOccupation'         => $data['mothersOccupation'] ?? '',
                'mothersNativePlace'        => $data['mothersNativePlace'] ?? '',
                'noOfBrothers'              => isset($data['noOfBrothers']) ? (int) $data['noOfBrothers'] : null,
                'brothersMarried'           => isset($data['brothersMarried']) ? (int) $data['brothersMarried'] : null,
                'noOfSisters'               => isset($data['noOfSisters']) ? (int) $data['noOfSisters'] : null,
                'sistersMarried'            => isset($data['sistersMarried']) ? (int) $data['sistersMarried'] : null,
                'parentsIntercasteMarriage' => $data['parentsIntercasteMarriage'] ?? '',
                'parentsDifferentCaste'     => $data['parentsDifferentCaste'] ?? '',
                'familyValues'              => $data['familyValues'] ?? '',
                'familyFinancialStatus'     => $data['familyFinancialStatus'] ?? '',
                'familyAnnualIncome'        => $data['familyAnnualIncome'] ?? '',
                'profileCreatorName'        => $data['profileCreatorName'] ?? '',
                'profileCreatorRelation'    => $data['profileCreatorRelation'] ?? '',

                // Reference
                'referenceName'             => $data['referenceName'] ?? '',
                'referenceRelation'         => $data['referenceRelation'] ?? '',
                'referenceAddress'          => $data['referenceAddress'] ?? '',
                'referenceContactNumber'    => $data['referenceContactNumber'] ?? $data['parents_contact'] ?? '',
                'referenceEmail'            => $data['referenceEmail'] ?? '',

                // Meta
                'source_of_information'     => $data['source_of_information'] ?? '',
                'phone'                     => $data['username'],
                'email'                     => !empty($data['email']) ? $data['email'] : null,

                // Status
                'profileVerificationStatus' => 'Pending',

                // Documents
                'identificationProof'       => $idProof,
                'birthDateProof'            => $birthProof,
                'educationProof'            => !empty($data['educationProof']) ? $data['educationProof'] : null,
                'additional_photos'         => $mergedAdditionalPhotosEncoded,
            ]);


            // =================================================
            // CREATE USER
            // =================================================

            $user = User::create([

                'name' =>
                    $data['fullName'] ?? '',

                'email' =>
                    $data['username'] . '@melava.com',

                'username' =>
                    $data['username'],

                'password' =>
                    Hash::make('manomilana'),

                'status' =>
                    'active',

                'is_password_changed' =>
                    0,

                'user_type' =>
                    21,

                'role_id' =>
                    3,
            ]);


            // -------------------------------------------------
            // Explicitly connect User -> Profile
            // -------------------------------------------------

            $user->profile_id =
                $profile->id;

            $user->save();


            // =================================================
            // CREATE PREFERENCE
            // =================================================

            Preference::create([
                'user_id'                   => $user->id,
                'preferred_religion'        => $data['preferred_religion'] ?? 'Jain',
                'preferred_caste'           => $data['preferred_caste'] ?? null,
                'preferred_sub_caste'       => $data['preferred_sub_caste'] ?? null,
                'age_from'                  => isset($data['age_from']) ? (int) $data['age_from'] : null,
                'age_to'                    => isset($data['age_to']) ? (int) $data['age_to'] : null,
                'height_from'               => $data['height_from'] ?? null,
                'height_to'                 => $data['height_to'] ?? null,
                'preferred_education'       => $data['preferred_education'] ?? null,
                'working_partner'           => $data['working_partner'] ?? null,
                'preferred_working_field'   => $data['preferred_working_field'] ?? null,
                'expected_income'           => $data['expected_income'] ?? null,
                'family_type'               => $data['family_type'] ?? null,
                'financial_background'      => $data['financial_background'] ?? null,
                'family_values'             => $data['family_values'] ?? null,
                'preferred_location'        => $data['preferred_city'] ?? $data['preferred_location'] ?? null,
                'preferred_country'         => $data['preferred_country'] ?? 'India',
                'preferred_state'           => $data['preferred_state'] ?? 'Maharashtra',
                'preferred_city'            => $data['preferred_city'] ?? $data['preferred_location'] ?? null,
                'other_expectations'        => $data['other_expectations'] ?? null,
            ]);
        }


        // =====================================================
        // 9. CREATE MELAVA REGISTRATION
        // =====================================================

        /*
         * IMPORTANT:
         *
         * This is the FIRST time we insert into
         * melava_registrations for the public registration.
         *
         * Therefore no melava_registrations record exists
         * before payment submission.
         */

        // Compute document field fallbacks (new field names -> legacy column names)
        $biodataFallback = $data['biodata'] ?? (!empty($data['identificationProof']) ? $data['identificationProof'] : null);
        $birthDocFallback = $data['birth_verification_doc'] ?? (!empty($data['birthDateProof']) ? $data['birthDateProof'] : null);
        $candidatePhotosFallback = !empty($data['candidate_photos']) ? $data['candidate_photos'] : (!empty($data['additionalPhotos']) ? $data['additionalPhotos'] : null);
        $preferredLocFallback = $data['preferred_location'] ?? ($data['preferred_city'] ?? null);
        $parentsContactFallback = $data['parents_contact'] ?? ($data['referenceContactNumber'] ?? '');
        $cityFallback = $data['city'] ?? ($data['professionalCity'] ?? '');

        $registration = MelavaRegistration::create([

            'user_id' =>
                $user ? $user->id : null,

            'profile_id' =>
                $profile ? $profile->id : null,

            'is_existing_website_user' =>
                $isExisting ? 1 : 0,

            'melava_batch' =>
                self::CURRENT_MELAVA_BATCH,

            'melava_registered_at' => now(),

            'fullName' =>
                $data['fullName'] ?? '',

            'groomOrBride' =>
                $data['groomOrBride'] ?? '',

            'birthDate' =>
                $data['birthDate'] ?? null,

            'username' =>
                $data['username'],

            'maritalStatus' =>
                $data['maritalStatus'] ?? '',

            'caste' =>
                $data['caste'] ?? '',

            'subCaste' =>
                $data['subCaste'] ?? '',

            'education' =>
                $data['education'] ?? '',

            'profession' =>
                $data['profession'] ?? '',

            'city' =>
                $cityFallback,

            'address' =>
                $data['address'] ?? '',

            'height' =>
                $data['height'] ?? '',

            'parents_contact' =>
                $parentsContactFallback,

            'fathersName' =>
                $data['fathersName'] ?? '',

            'mothersName' =>
                $data['mothersName'] ?? '',

            'noOfBrothers' =>
                $data['noOfBrothers'] ?? null,

            'noOfSisters' =>
                $data['noOfSisters'] ?? null,

            'age_from' =>
                $data['age_from'] ?? null,

            'age_to' =>
                $data['age_to'] ?? null,

            'height_from' =>
                $data['height_from'] ?? null,

            'height_to' =>
                $data['height_to'] ?? null,

            'preferred_education' =>
                $data['preferred_education'] ?? null,

            'preferred_working_field' =>
                $data['preferred_working_field'] ?? null,

            'preferred_caste' =>
                $data['preferred_caste'] ?? null,

            'preferred_location' =>
                $preferredLocFallback,

            'other_expectations' =>
                $data['other_expectations'] ?? '',

            'biodata' =>
                $biodataFallback,

            'birth_verification_doc' =>
                $birthDocFallback,

            'candidate_photos' =>
                $candidatePhotosFallback,

            'source_of_information' =>
                $data['source_of_information'] ?? null,

            'is_booklet_req' =>
                !empty($data['is_booklet_req']),

            'payment_screenshot' =>
                $paymentPath,

            'link_token' =>
                $token,

            'updated_by' =>
                Auth::check()
                    ? Auth::user()->name
                    : 'Public',
        ]);


        // =====================================================
        // 10. CREATE PAYMENT RECORD
        // =====================================================

        Payment::create([

            'user_id' =>
                $user ? $user->id : null,

            'photo_upload' =>
                $paymentPath,

            'updated_by' =>
                Auth::check()
                    ? Auth::user()->name
                    : 'Public',
        ]);


        // =====================================================
        // 11. UPDATE PENDING REGISTRATION STATUS
        // =====================================================

        $pendingRegistration->status = 'completed';
        $pendingRegistration->user_id = $user ? $user->id : null;
        $pendingRegistration->profile_id = $profile ? $profile->id : null;
        $pendingRegistration->save();


        // =====================================================
        // 12. COMMIT EVERYTHING
        // =====================================================

        DB::commit();


        // =====================================================
        // 13. REMOVE OLD SESSION DATA
        // =====================================================

        session()->forget([
            'melava_public_registration',
            'melava_public_token'
        ]);


        // =====================================================
        // 14. REDIRECT TO SUCCESS PAGE
        // =====================================================

        return redirect()
            ->route('melava.public_success')
            ->with(
                'success',
                'Melava registration and payment submitted successfully.'
            );


    } catch (\Exception $e) {

        // =====================================================
        // ROLLBACK DATABASE
        // =====================================================

        DB::rollBack();

        Log::error(
            'Melava public payment error: ' .
            $e->getMessage()
        );

        return back()
            ->withInput($request->except(['payment_screenshot']))
            ->with(
                'error',
                'Unable to complete registration: ' .
                $e->getMessage()
            );
    }
}

}

