<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Profile;
use App\DocumentVerification;
use App\UserInterest;
use App\Preference;
use Illuminate\Support\Facades\Auth;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Meeting;
use App\Mail\MeetingScheduled;
use App\ProfileVisitor;
use App\PaymentScreenshot;
use App\MelavaRegistration;

class ProfilesController extends Controller
{
    // Add this method to your ProfileController

    public function downloadBiodata($id)
    {

        // Only super admin can download biodata
        if (Auth::user()->role_id != 1) {
            return redirect()->route('home')->with('error', 'Only super admins can download Biodata.');
        }
        // Check if current user is an unverified candidate
        if (Auth::user()->user_type == '21') {
            $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
            if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
                return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
            }
        }

        $profile = Profile::with('user')->findOrFail($id);

        $pdf = Pdf::loadView('biodata-pdf', compact('profile'))
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top', 0)
            ->setOption('margin-right', 0)
            ->setOption('margin-bottom', 0)
            ->setOption('margin-left', 0);

        $filename = 'Biodata_' . str_replace(' ', '_', $profile->fullName) . '_' . $profile->profile_id . '.pdf';

        return $pdf->download($filename);
    }
    public function index()
    {
        // Check if current user is an unverified candidate
        // if (Auth::user()->user_type == '21') {
        //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();

        //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
        //         return redirect()->route('home')
        //             ->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
        //     }
        // }

        $q = request('q');
        $gender = request('gender');
        $reg_type = request('reg_type');
        $type = request('type');
        
        // Default sorting for reports is serial_no ASC, for others registration_date DESC
        $defaultSortBy = $type ? 'serial_no' : 'registration_date';
        $defaultSortOrder = $type ? 'asc' : 'desc';

        $sortBy = request('sort_by', $defaultSortBy);
        $sortOrder = request('sort_order', $defaultSortOrder);

        // Allow only the supported sorting columns and directions.
        if (!in_array($sortBy, ['registration_number', 'registration_date', 'serial_no'], true)) {
            $sortBy = $defaultSortBy;
        }

        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = $defaultSortOrder;
        }

        $profiles = Profile::with([
            'user',
            'paymentScreenshots',
            'melavaRegistrations',
        ])
            ->where('profiles.active', 'active')

            // REGISTRATION TYPE VISIBILITY (User can only see profiles of their own registration type)
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            // SEARCH
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profiles.profile_id', 'like', "%{$q}%")
                        ->orWhere('profiles.serial_no', 'like', "%{$q}%")
                        ->orWhere('profiles.fullName', 'like', "%{$q}%")
                        ->orWhere('profiles.profileVerificationStatus', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                        ->orWhereHas('user', function ($q3) use ($q) {
                            $q3->where('username', 'like', "%{$q}%");
                        });
                });
            })

            // GENDER FILTER
            ->when($gender && $gender !== 'all', function ($query) use ($gender) {
                $query->where('profiles.groomOrBride', $gender);
            })

            // REGISTRATION TYPE FILTER (URL parameter override for admins)
            ->when($reg_type && $reg_type !== 'all', function ($query) use ($reg_type) {
                if ($reg_type === 'melava_existing') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->whereIn('profiles.registration_type', ['melava', 'existing'])
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } elseif ($reg_type === 'melava') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->where('profiles.registration_type', 'melava')
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } else {
                    $query->where('registration_type', $reg_type);
                }
            })

            // TYPE FILTER
            ->when($type, function ($query) use ($type) {

                // Show only current melava batch for reports
                $query->where('melava_batch', self::CURRENT_MELAVA_BATCH);

                // ALL MELAVA PROFILES (new "melava" registrations + re-registered "existing" candidates)
                if ($type === 'all') {

                    $query->whereIn(
                        'registration_type',
                        ['melava', 'existing']
                    );

                } elseif ($type === '1998-and-above') {

                    $query->whereYear(
                        'birthDate',
                        '>=',
                        1998
                    )
                        ->where(
                            'maritalStatus',
                            'Unmarried'
                        );

                } elseif ($type === '1992-and-1997') {

                    $query->whereYear(
                        'birthDate',
                        '>=',
                        1992
                    )
                        ->whereYear(
                            'birthDate',
                            '<=',
                            1997
                        )
                        ->where(
                            'maritalStatus',
                            'Unmarried'
                        );

                } elseif ($type === '1984-and-1991') {

                    $query->whereYear(
                        'birthDate',
                        '>=',
                        1984
                    )
                        ->whereYear(
                            'birthDate',
                            '<=',
                            1991
                        )
                        ->where(
                            'maritalStatus',
                            'Unmarried'
                        );

                } elseif ($type === '1983-and-below') {

                    $query->whereYear(
                        'birthDate',
                        '<=',
                        1983
                    )
                        ->where(
                            'maritalStatus',
                            'Unmarried'
                        );

                } elseif ($type === 'widows-divorce') {

                    $query->whereIn(
                        'maritalStatus',
                        [
                            'Widowed',
                            'Divorced',
                            'Widower',
                            'Separated'
                        ]
                    );
                }
            })

            // Apply sorting
            ->when($sortBy === 'registration_number', function ($query) use ($sortOrder) {
                $query->orderByRaw(
                    "COALESCE(NULLIF(profiles.registration_number, ''), profiles.profile_id) {$sortOrder}"
                );
            })
            ->when($sortBy === 'registration_date', function ($query) use ($sortOrder) {
                $query->orderByRaw("
                    COALESCE(
                        (
                            SELECT MAX(mr.melava_registered_at)
                            FROM melava_registrations AS mr
                            WHERE mr.profile_id = profiles.id
                            AND mr.melava_batch = 'october_2026'
                        ),
                        profiles.created_at
                    ) IS NULL ASC
                ")
                ->orderByRaw("
                    COALESCE(
                        (
                            SELECT MAX(mr.melava_registered_at)
                            FROM melava_registrations AS mr
                            WHERE mr.profile_id = profiles.id
                            AND mr.melava_batch = 'october_2026'
                        ),
                        profiles.created_at
                    ) {$sortOrder}
                ");
            })
            ->when($sortBy === 'serial_no', function ($query) use ($sortOrder) {
                $query->orderBy('serial_no', $sortOrder);
            })
            ->paginate(10)
            ->appends([
                'q' => $q,
                'gender' => $gender,
                'reg_type' => $reg_type,
                'type' => $type,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]);

        // REPORT PAGE
        if ($type) {

            $templates = [];
            $templatesPath = public_path('landingPageAssets/img/templates');

            if (file_exists($templatesPath) && is_dir($templatesPath)) {
                $templates = array_diff(scandir($templatesPath), ['..', '.']);
            }

            return view('admin.reports.list', compact(
                'profiles',
                'q',
                'type',
                'templates'
            ));
        }

        return view('admin.profiles.list', compact(
            'profiles',
            'q',
            'gender',
            'reg_type'
        ));
    }

    public function inactiveProfiles()
    {
        $q = request('q');
        $gender = request('gender');
        $reg_type = request('reg_type');

        $profiles = Profile::with('user', 'paymentScreenshots')
            ->where('profiles.active', 'deactive')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profiles.profile_id', 'like', "%{$q}%")
                        ->orWhere('profiles.serial_no', 'like', "%{$q}%")
                        ->orWhere('profiles.fullName', 'like', "%{$q}%")
                        ->orWhere('profiles.profileVerificationStatus', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                        ->orWhereHas('user', function ($q3) use ($q) {
                            $q3->where('username', 'like', "%{$q}%");
                        });
                });
            })
            ->when($gender && $gender !== 'all', function ($query) use ($gender) {
                $query->where('profiles.groomOrBride', $gender);
            })
            ->when($reg_type && $reg_type !== 'all', function ($query) use ($reg_type) {
                if ($reg_type === 'melava_existing') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->whereIn('profiles.registration_type', ['melava', 'existing'])
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } elseif ($reg_type === 'melava') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->where('profiles.registration_type', 'melava')
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } else {
                    $query->where('registration_type', $reg_type);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends([
                'q' => $q,
                'gender' => $gender,
                'reg_type' => $reg_type
            ]);

        return view('admin.profiles.inactiveProfiles', compact(
            'profiles',
            'q',
            'gender',
            'reg_type'
        ));
    }

    public function deactivate($id)
    {
        DB::beginTransaction();
        try {
            $profile = Profile::findOrFail($id);
            $profile->active = 'deactive';
            $profile->save();

            $user = User::where('profile_id', $profile->id)->first();
            if ($user) {
                $user->status = 'inactive';
                $user->save();
            }
            DB::commit();
            return redirect()->back()->with('success', 'Profile deactivated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function activate($id)
    {
        DB::beginTransaction();
        try {
            $profile = Profile::findOrFail($id);
            $profile->active = 'active';
            $profile->save();

            $user = User::where('profile_id', $profile->id)->first();
            if ($user) {
                $user->status = 'active';
                $user->save();
            }
            DB::commit();
            return redirect()->back()->with('success', 'Profile activated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }



    public function exportProfiles(Request $request)
    {
        $q = $request->query('q');
        $gender = $request->query('gender');
        $reg_type = $request->query('reg_type');
        $type = $request->query('type');

        // Default sorting for reports is serial_no ASC, for others registration_date DESC
        $defaultSortBy = $type ? 'serial_no' : 'registration_date';
        $defaultSortOrder = $type ? 'asc' : 'desc';

        $sortBy = $request->get('sort_by', $defaultSortBy);
        $sortOrder = $request->get('sort_order', $defaultSortOrder);

        // Allow only the supported sorting columns and directions.
        if (!in_array($sortBy, ['registration_number', 'registration_date', 'serial_no'], true)) {
            $sortBy = $defaultSortBy;
        }

        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = $defaultSortOrder;
        }

        $profiles = Profile::with([
                'user',
                'paymentScreenshots',
                'melavaRegistrations' => function ($query) {
                    $query->where('melava_batch', 'october_2026')
                        ->whereNotNull('melava_registered_at')
                        ->orderBy('melava_registered_at', 'desc');
                },
            ])
            ->where('profiles.active', 'active')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profiles.profile_id', 'like', "%{$q}%")
                        ->orWhere('profiles.serial_no', 'like', "%{$q}%")
                        ->orWhere('profiles.fullName', 'like', "%{$q}%")
                        ->orWhere('profiles.profileVerificationStatus', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                        ->orWhereHas('user', function ($q3) use ($q) {
                            $q3->where('username', 'like', "%{$q}%");
                        });
                });
            })
            ->when($gender && $gender !== 'all', function ($query) use ($gender) {
                $query->where('profiles.groomOrBride', $gender);
            })
            ->when($reg_type && $reg_type !== 'all', function ($query) use ($reg_type) {
                if ($reg_type === 'melava_existing') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->whereIn('profiles.registration_type', ['melava', 'existing'])
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } elseif ($reg_type === 'melava') {
                    $query->whereIn('profiles.id', DB::table('melava_registrations')->select('profile_id'))
                        ->where('profiles.registration_type', 'melava')
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } else {
                    $query->where('registration_type', $reg_type);
                }
            })
            ->when(Auth::check() && Auth::user()->user_type == '51', function ($query) {
                $query->where('registration_type', 'melava');
            })

            ->when($type, function ($query) use ($type) {

                // Show only current melava batch for reports
                $query->where('melava_batch', self::CURRENT_MELAVA_BATCH);

                if ($type === '1998-and-above') {

                    $query->whereYear('birthDate', '>=', 1998)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === '1992-and-1997') {

                    $query->whereYear('birthDate', '>=', 1992)
                        ->whereYear('birthDate', '<=', 1997)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === '1984-and-1991') {

                    $query->whereYear('birthDate', '>=', 1984)
                        ->whereYear('birthDate', '<=', 1991)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === '1983-and-below') {

                    $query->whereYear('birthDate', '<=', 1983)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === 'widows-divorce') {

                    $query->whereIn('maritalStatus', [
                        'Widowed',
                        'Divorced',
                        'Widower',
                        'Separated'
                    ]);

                }
            })
            // Apply sorting
            ->when($sortBy === 'registration_number', function ($query) use ($sortOrder) {
                $query->orderByRaw(
                    "COALESCE(NULLIF(profiles.registration_number, ''), profiles.profile_id) {$sortOrder}"
                );
            })
            ->when($sortBy === 'registration_date', function ($query) use ($sortOrder) {
                $query->orderByRaw(" COALESCE( ( SELECT MAX(mr.melava_registered_at) FROM melava_registrations AS mr WHERE mr.profile_id = profiles.id AND mr.melava_batch = 'october_2026' ), profiles.created_at ) IS NULL ASC ") 
                      ->orderByRaw(" COALESCE( ( SELECT MAX(mr.melava_registered_at) FROM melava_registrations AS mr WHERE mr.profile_id = profiles.id AND mr.melava_batch = 'october_2026' ), profiles.created_at ) {$sortOrder} ");
            })
            ->when($sortBy === 'serial_no', function ($query) use ($sortOrder) {
                $query->orderBy('serial_no', $sortOrder);
            })
            ->get();

        $filename = "profiles_export_" . date('Y-m-d_H-i-s') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($profiles) {
            $handle = fopen('php://output', 'w');

            // Add headers
            
        fputcsv($handle, [
            'Serial No',
            'Registration No',
            'Full Name',
            'Mobile Number',
            'Email',
            'DOB',
            'Date of Registration',
            'Gender',
            'Registration Type',
            'Booklet Required',
            'Marital Status',
            'Caste',
            'Education',
            'Profession',
            'City',
            'Mother Name',
            'Mother Phone Number',
            'Father Name',
            'Father Phone Number',
            'Source of Information',
            'Status'
        ]); 


            foreach ($profiles as $profile) {
                fputcsv($handle, [
                    $profile->serial_no ?? 'N/A',
                    $profile->registration_number ?? $profile->profile_id,
                    $profile->fullName,
                    $profile->phone ?? ($profile->user->username ?? 'N/A'),
                    $profile->email ?? 'N/A',
                    $profile->birthDate
                        ? date('d-m-Y', strtotime($profile->birthDate))
                        : 'N/A',
                    (function () use ($profile) {
                        $melavaRegistration = $profile->melavaRegistrations->first();

                        $registrationDate = $melavaRegistration
                            ? $melavaRegistration->melava_registered_at
                            : $profile->created_at;

                        return $registrationDate
                            ? date('d-m-Y', strtotime($registrationDate))
                            : 'N/A';
                    })(),
                    ucfirst($profile->groomOrBride ?? 'N/A'),

                    // Correct registration type
                    (function () use ($profile) {
                        switch ($profile->registration_type) {
                            case 'melava':
                                return 'Melava Registration';
                            case 'existing':
                                return 'Existing Registration';
                            case 'quick':
                                return 'Quick Registration';
                            default:
                                return ucfirst($profile->registration_type ?? 'Unknown');
                        }
                    })(),

                    (function () use ($profile) {
                        $melavaRegistration = \App\MelavaRegistration::where(
                            'profile_id',
                            $profile->id
                        )
                        ->where('melava_batch', 'october_2026')
                        ->orderBy('melava_registered_at', 'desc')
                        ->first();

                        if (!$melavaRegistration) {
                            return 'N/A';
                        }

                        return (int) $melavaRegistration->is_booklet_req === 1
                            ? 'Yes'
                            : 'No';
                    })(),

                        
                $profile->maritalStatus ?? 'N/A',
                $profile->caste ?? 'N/A',
                $profile->education ?? 'N/A',
                $profile->profession ?? 'N/A',
                $profile->professionalCity ?? 'N/A',

                // Parent details - Excel export only
                $profile->mothersName ?? 'N/A',
                $profile->motherContactNumber ?? 'N/A',
                $profile->fathersName ?? 'N/A',
                $profile->fatherContactNumber ?? 'N/A',

                // Source of Information
                (function () use ($profile) {
                    $melavaRegistration = $profile->melavaRegistrations->first();
                    return $melavaRegistration ? ($melavaRegistration->source_of_information ?? 'N/A') : 'N/A';
                })(),

                // Status must remain last
                $profile->profileVerificationStatus ?? 'N/A',
                                
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function printIdCards()
    {
        return view('admin.reports.print_id_cards');
    }

    public function getAllFilteredProfiles(Request $request)
    {
        $q = $request->q;
        $gender = $request->gender;
        $reg_type = $request->reg_type;
        $type = $request->type;

        $profiles = Profile::query()
            ->where('active', 'active')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            // Search
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profile_id', 'like', "%{$q}%")
                        ->orWhere('serial_no', 'like', "%{$q}%")
                        ->orWhere('fullName', 'like', "%{$q}%");
                });
            })

            // Gender filter
            ->when($gender && $gender !== 'all', function ($query) use ($gender) {
                $query->where('groomOrBride', $gender);
            })

            // Registration type filter
            ->when($reg_type && $reg_type !== 'all', function ($query) use ($reg_type) {
                $query->where('registration_type', $reg_type);
            })
            ->when(Auth::check() && Auth::user()->user_type == '51', function ($query) {
                $query->where('registration_type', 'melava');
            })

            // Type filter
            ->when($type, function ($query) use ($type) {

                if ($type === '1999-and-above') {

                    $query->whereYear('birthDate', '>=', 1999)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === '1995-and-1998') {

                    $query->whereYear('birthDate', '>=', 1995)
                        ->whereYear('birthDate', '<=', 1998)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === '1985-and-1994') {

                    $query->whereYear('birthDate', '>=', 1985)
                        ->whereYear('birthDate', '<=', 1994)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === '1984-and-below') {

                    $query->whereYear('birthDate', '<=', 1984)
                        ->where('maritalStatus', 'Unmarried');

                } elseif ($type === 'widows-divorce') {

                    $query->whereIn('maritalStatus', [
                        'Widowed',
                        'Divorced',
                        'Widower',
                        'Separated'
                    ]);
                }
            })

            ->orderBy('created_at', 'desc')
            ->get();

        // Return same serial no as UI
        $selectedProfiles = $profiles->map(function ($profile) {

            return [
                'id' => $profile->id,
                'fullName' => $profile->fullName,
                'profileId' => $profile->profile_id,
                'srNo' => $profile->serial_no ?? '-'
            ];
        });

        return response()->json($selectedProfiles);
    }

    public function generateReceipt($id)
    {
        $profile = Profile::with([
            'user',
            'melavaRegistrations' => function ($query) {
                $query->where('payment_verified', true);
            },
        ])->findOrFail($id);
        return view('admin.profiles.receipt', compact('profile'));
    }

    public function printReportCard(Request $request, $id)
    {
        $profile = Profile::findOrFail($id);
        $srNo = $request->query('sr_no', 'N/A');
        return view('admin.profiles.report_print', compact('profile', 'srNo'));
    }

    public function showPaymentScreenshot($id)
    {
        $profile = Profile::with([
            'paymentScreenshots',
            'melavaRegistrations' => function ($query) {
                $query->whereNotNull('payment_screenshot')
                    ->orWhereNotNull('booklet_payment_screenshot');
            },
        ])->findOrFail($id);
        return view('admin.profiles.payment_screenshot', compact('profile'));
    }

    public function uploadPaymentScreenshotAdmin(Request $request, $id)
    {
        if (Auth::user()->role_id != 1) {
            return redirect()->back()->with('error', 'Access denied. Only Super Admins can upload payment screenshots.');
        }

        $request->validate([
            'payment_screenshot' => 'required|image|max:5120',
        ]);

        $profile = Profile::findOrFail($id);

        if ($request->hasFile('payment_screenshot')) {
            $file = $request->file('payment_screenshot');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/payment_screenshots'), $filename);

            PaymentScreenshot::create([
                'profile_id' => $profile->id,
                'screenshot_path' => 'uploads/payment_screenshots/' . $filename,
                'payment_verified' => false,
            ]);

            return redirect()->back()->with('success', 'Payment screenshot uploaded successfully!');
        }

        return redirect()->back()->with('error', 'Please select an image to upload.');
    }

    public function verifyPayment($id)
    {
        $paymentScreenshot = PaymentScreenshot::findOrFail($id);
        $paymentScreenshot->update(['payment_verified' => true]);

        return redirect()->back()->with('success', 'Payment verified successfully!');
    }

    public function verifyMelavaPayment($id)
    {
        $registration = \App\MelavaRegistration::findOrFail($id);
        $registration->update(['payment_verified' => true]);

        return redirect()->back()->with('success', 'Melava payment verified successfully!');
    }

    public function verifyMelavaBookletPayment($id)
    {
        $registration = \App\MelavaRegistration::findOrFail($id);
        $registration->update(['booklet_payment_verified' => true]);

        return redirect()->back()->with('success', 'Melava booklet payment verified successfully!');
    }

    public function verifiedProfiles()
    {
        $profiles = Profile::where('profileVerificationStatus', 'Completed')
            ->where('active', 'active')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('admin.profiles.verifiedProfiles', compact('profiles'));
    }

    public function visitors()
    {
        $userProfile = Profile::find(Auth::user()->profile_id);

        if (!$userProfile) {
            return redirect()->route('home')->with('error', 'Profile not found.');
        }

        $visitorUserIds = ProfileVisitor::where('profile_id', $userProfile->id)->pluck('visitor_id');
        $visitorProfileIds = User::whereIn('id', $visitorUserIds)->pluck('profile_id')->filter(function ($val) use ($userProfile) {
            return $val != $userProfile->id;
        });

        $profiles = Profile::whereIn('id', $visitorProfileIds)
            ->where('active', 'active')
            ->where('profileVerificationStatus', 'Completed')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.profiles.visitors', compact('profiles'));
    }

    public function showComparison($profileId)
    {
        // Find the primary profile
        $profile = Profile::findOrFail($profileId);
        if (!$this->canViewProfileByRegistrationType($profile)) {
            abort(404);
        }

        // Get the user associated with this profile
        $user = User::where('profile_id', $profile->id)->first();

        // Get the preferences for this profile
        $preferences = null;
        if ($user) {
            $preferences = Preference::where('user_id', $user->id)->first();
        }

        // Calculate age from birth date
        $age = null;
        if ($profile->birthDate) {
            $age = \Carbon\Carbon::parse($profile->birthDate)->age;
        }

        return response()->json([
            'profile' => [
                'fullName' => $profile->fullName,
                'profile_id' => $profile->profile_id,
                'groomOrBride' => $profile->groomOrBride,
                'profilePhoto' => $profile->profilePhoto,
                'age' => $age,
                'height' => $profile->height,
                'education' => $profile->education,
                'profession' => $profile->profession,
                'monthlyIncome' => $profile->monthlyIncome,
                'caste' => $profile->caste,
                'subCaste' => $profile->subCaste,
                'motherTongue' => $profile->motherTongue,
                'maritalStatus' => $profile->maritalStatus,
            ],
            'preferences' => $preferences ? [
                'preferred_religion' => $preferences->preferred_religion,
                'preferred_caste' => $preferences->preferred_caste,
                'preferred_sub_caste' => $preferences->preferred_sub_caste,
                'age_from' => $preferences->age_from,
                'age_to' => $preferences->age_to,
                'height_from' => $preferences->height_from,
                'height_to' => $preferences->height_to,
                'preferred_location' => $preferences->preferred_location,
                'preferred_country' => $preferences->preferred_country,
                'preferred_state' => $preferences->preferred_state,
                'preferred_city' => $preferences->preferred_city,
                'other_expectations' => $preferences->other_expectations,
            ] : null
        ]);
    }

    public function editDetails($id)
    {
        if (Auth::check() && Auth::user()->user_type == '51') {
            return redirect()->route('home')
                ->with('error', 'Guest users are not allowed to edit profiles.');
        }

        $profile = Profile::findOrFail($id);




        $user = User::where('profile_id', $profile->id)->first();

        $preference = $user
            ? Preference::where('user_id', $user->id)->first()
            : null;

        return view(
            'admin.profiles.editDetails',
            compact('profile', 'preference')
        );
    }

    public function updateDetails(Request $request, $id)
    {
        if (Auth::check() && Auth::user()->user_type == '51') {
            return redirect()->route('home')->with('error', 'Guest users are not allowed to update profiles.');
        }
        $profile = Profile::findOrFail($id);



        // Ensure timeOfBirth is strictly in 24-hour format
        if ($request->has('timeOfBirth') && $request->input('timeOfBirth')) {
            $time = $request->input('timeOfBirth');
            if (preg_match('/(\d{1,2}):(\d{2})(?:\s*(AM|PM))?/i', $time, $matches)) {
                $hh = (int) $matches[1];
                $mm = $matches[2];
                if (isset($matches[3]) && $matches[3]) {
                    $ampm = strtoupper(trim($matches[3]));
                    if ($ampm === 'PM' && $hh < 12)
                        $hh += 12;
                    if ($ampm === 'AM' && $hh === 12)
                        $hh = 0;
                }
                $request->merge(['timeOfBirth' => sprintf('%02d:%02d:00', $hh, $mm)]);
            }
        }

        $request->validate([
            'phone' => 'required|string|max:20',
            'fullName' => 'required|string|max:255',
            'source_of_information' => 'required|string|max:255',
            'groomOrBride' => 'required',
            'religion' => 'required|string|max:255',
            'caste' => 'required|string|max:255',
            'subCaste' => 'required_if:caste,Shwetambar,Digambar|nullable|string|max:255',
            'maritalStatus' => 'required|string|max:255',
            'birthDate' => 'required|date',
            'timeOfBirth' => 'required',
            'birthTime_ampm' => 'required|string',
            'placeOfBirth' => 'required|string|max:255',
            'height' => ['required', 'string', 'regex:/^(\d+)\s*(?:ft|\'|feet)\s*(\d+)\s*(?:in|"|inches)?$/i'],
            'weight' => 'required|numeric',
            'bloodGroup' => 'required|string|max:10',
            'motherTongue' => 'required|string|max:255',
            'havingSpecsLenses' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:255',
            'address' => 'required|string',
            'permanent_address' => 'required|string',
            'education' => 'required|string|max:255',
            'degreeDiplomaName1' => 'required|string|max:255',
            'profession' => 'required|string|max:255',
            'fieldOfWorking' => 'required|string|max:255',
            'professionalCity' => 'required|string|max:255',
            'monthlyIncome' => 'required|numeric',
            'income_currency' => 'nullable|string|in:INR,USD,EUR,GBP',
            'annualIncome' => 'nullable|numeric',
            'hobbies' => 'required|string|max:255',
            'hasMedicalHistory' => 'required|in:Yes,No',
            'fathersName' => 'required|string|max:255',
            'fatherAlive' => 'required|in:Yes,No',
            'fathersOccupation' => 'required_if:fatherAlive,Yes|nullable|string|max:255',
            'fatherContactNumber' => 'required_if:fatherAlive,Yes|nullable|digits:10',
            'mothersName' => 'required|string|max:255',
            'motherAlive' => 'required|in:Yes,No',
            'mothersOccupation' => 'required_if:motherAlive,Yes|nullable|string|max:255',
            'motherContactNumber' => 'required_if:motherAlive,Yes|nullable|digits:10',
            'noOfBrothers' => 'required|numeric',
            'brothersMarried' => 'nullable|numeric',
            'noOfSisters' => 'required|numeric',
            'sistersMarried' => 'nullable|numeric',
            'referenceName' => 'required|string|max:255',
            'referenceRelation' => 'required|string|max:255',
            'referenceAddress' => 'nullable|string|max:255',
            'referenceContactNumber' => 'required|digits:10',
            'working_partner' => 'nullable|string',
            'preferred_caste' => 'nullable|string|max:255',
            'preferred_sub_caste' => 'nullable|string|max:255',
            'preferred_country' => 'nullable|string|max:255',
            'preferred_state' => 'nullable|string|max:255',
            'preferred_city' => 'nullable|string|max:255',
            'financial_background' => 'nullable|string',
            'age_from' => 'nullable|numeric',
            'age_to' => 'nullable|numeric',
            'profilePhoto' => $profile->profilePhoto ? 'nullable|image|mimes:jpeg,png,jpg,gif|max:51200' : 'required|image|mimes:jpeg,png,jpg,gif|max:51200',
            'additionalPhotos' => 'nullable|array|max:4',
            'additionalPhotos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:51200',
            'identificationProof' => $profile->identificationProof ? 'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200' : 'required|file|mimes:pdf,jpg,png,jpeg|max:51200',
            'birthDateProof' => $profile->birthDateProof ? 'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200' : 'required|file|mimes:pdf,jpg,png,jpeg|max:51200',
            'educationProof' => 'nullable|file|mimes:pdf,jpg,png,jpeg|max:51200',
        ]);

        $proofs = [
            'identificationProof',
            'birthDateProof',
            'educationProof'
        ];

        $preferenceFields = [
            'preferred_religion',
            'preferred_caste',
            'preferred_sub_caste',
            'age_from',
            'age_to',
            'height_from',
            'height_to',
            'preferred_education',
            'working_partner',
            'preferred_working_field',
            'expected_income',
            'family_type',
            'financial_background',
            'family_values',
            'preferred_country',
            'preferred_state',
            'preferred_city',
            'other_expectations'
        ];

        $preferenceData = $request->only($preferenceFields);
        $data = $request->except(array_merge(['_token', 'profilePhoto', 'additionalPhotos'], $proofs, $preferenceFields));

        $currency = $request->input('income_currency', 'INR');
        if (!in_array($currency, ['INR', 'USD', 'EUR', 'GBP'])) {
            $currency = 'INR';
        }

        $rate = 1;
        if ($currency === 'USD')
            $rate = 96.56;
        elseif ($currency === 'EUR')
            $rate = 110.73;
        elseif ($currency === 'GBP')
            $rate = 130.53;

        $originalMonthly = $request->input('monthlyIncome');
        $originalAnnual = $request->input('annualIncome');

        $data['income_currency'] = $currency;
        $data['original_monthly_income'] = is_numeric($originalMonthly) ? floatval($originalMonthly) : null;
        $data['original_annual_income'] = is_numeric($originalAnnual) ? floatval($originalAnnual) : null;

        if (is_numeric($originalMonthly)) {
            $data['monthlyIncome'] = floatval($originalMonthly) * $rate;
        }
        if (is_numeric($originalAnnual)) {
            $data['annualIncome'] = floatval($originalAnnual) * $rate;
        }

        // Handle Profile Photo Upload
        if ($request->hasFile('profilePhoto')) {
            if ($profile->profilePhoto && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->profilePhoto)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->profilePhoto);
            }
            $data['profilePhoto'] = $request->file('profilePhoto')->store('profile_photos', 'public');
        }

        // Handle Proof Documents
        foreach ($proofs as $proof) {
            if ($request->hasFile($proof)) {
                if ($profile->$proof && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->$proof)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->$proof);
                }
                $data[$proof] = $request->file($proof)->store('uploads', 'public');
            }
        }

        // Handle Additional Photos (Append)
        if ($request->hasFile('additionalPhotos')) {
            $currentPhotos = $profile->additional_photos ?? [];
            if (!is_array($currentPhotos)) {
                $currentPhotos = [];
            }

            foreach ($request->file('additionalPhotos') as $photo) {
                $path = $photo->store('additional_photos', 'public');
                $currentPhotos[] = $path;
            }
            $data['additional_photos'] = $currentPhotos;
        }

        $profile->update($data);
        $profile->calculateCompletionPercentage();

        $user = User::where('profile_id', $profile->id)->first();
        if (!$user) {
            // Create a placeholder user to associate preferences if the profile was created without one
            $username = $profile->phone ?? $profile->profile_id ?? uniqid('user_');
            $user = User::create([
                'username' => $username,
                'name' => $profile->fullName ?? 'Unknown',
                'email' => $profile->email ?? ($username . '@aaryansmanomilan.com'),
                'password' => bcrypt('manomilana'),
                'user_type' => '21',
                'role_id' => 3,
                'status' => 'active',
                'profile_id' => $profile->id
            ]);
        }

        if ($user && is_array($preferenceData)) {
            Preference::updateOrCreate(
                ['user_id' => $user->id],
                $preferenceData
            );
        }

        // Re-calculate one last time to ensure everything including preferences is considered
        $profile->refresh();
        $completion = $profile->calculateCompletionPercentage();

        if (!$profile->hasCompletedMandatoryFields()) {
            return redirect()->route('profiles.editDetails', $profile->id)
                ->with('success', 'Information saved. Please complete all mandatory fields to access the dashboard.');
        }

        return redirect()->route('home')->with('success', 'Profile updated successfully! Welcome to the dashboard.');
    }

    public function editVerifier($id)
    {
        $profile = Profile::find($id);
        $verification = DocumentVerification::firstOrCreate(['profile_id' => $profile->id]);
        return view('admin.profiles.editVerifier', compact('profile', 'verification'));
    }


    public function storeVerifier(Request $request, Profile $profile)
    {
        // Define the validation rules based on your form fields
        $rules = [
            'identificationProof_status' => 'required|in:pending,verified,rejected,not_eligible',
            'identificationProof_remarks' => 'nullable|string|max:255',
            'birthDateProof_status' => 'required|in:pending,verified,rejected,not_eligible',
            'birthDateProof_remarks' => 'nullable|string|max:255',
            'addressProof_status' => 'nullable|in:pending,verified,rejected,not_eligible',
            'addressProof_remarks' => 'nullable|string|max:255',
            'educationProof_status' => 'nullable|in:pending,verified,rejected,not_eligible',
            'educationProof_remarks' => 'nullable|string|max:255',
            'professionProof_status' => 'required|in:pending,verified,rejected,not_eligible',
            'professionProof_remarks' => 'nullable|string|max:255',
            'incomeProof_status' => 'required|in:pending,verified,rejected,not_eligible',
            'incomeProof_remarks' => 'nullable|string|max:255',
            'casteCertificate_status' => 'required|in:pending,verified,rejected,not_eligible',
            'casteCertificate_remarks' => 'nullable|string|max:255',
            'medicalFitnessCertificate_status' => 'required|in:pending,verified,rejected,not_eligible',
            'medicalFitnessCertificate_remarks' => 'nullable|string|max:255',
            'selfieVerification_status' => 'required|in:pending,verified,rejected,not_eligible',
            'selfieVerification_remarks' => 'nullable|string|max:255',
        ];

        // Validate the request data
        $request->validate($rules);

        // Find or create the document verification record
        $verification = DocumentVerification::updateOrCreate(
            ['profile_id' => $profile->id],
            $request->only(array_keys($rules)) // Get only the validated fields
        );

        $profile->syncDocumentVerificationStatus();

        if ($profile->profileVerificationStatus === 'Completed') {
            // Keep the original MM2B/MM2G registration number as the profile_id for consistency
            if ($profile->registration_number) {
                $profile->profile_id = $profile->registration_number;
            }
            $profile->save();
        }

        return redirect()->back()->with('success', 'Document verification updated successfully!');
    }

    public function unverifiedProfiles(Request $request)
    {
        // Super Admin access check
        if (Auth::user()->user_type !== '00') {
            abort(403, 'Unauthorized');
        }

        $q = request('q');
        $gender = request('gender');
        $reg_type = request('reg_type');

        $query = Profile::with('user', 'paymentScreenshots')
            ->where('active', 'active')
            ->where(function ($query) {
                $query->where('profileVerificationStatus', '!=', 'Completed')
                    ->orWhereNull('profileVerificationStatus');
            });

        // SEARCH
        if ($q) {
            $query->where(function ($q2) use ($q) {
                $q2->where('profile_id', 'like', "%{$q}%")
                    ->orWhere('serial_no', 'like', "%{$q}%")
                    ->orWhere('fullName', 'like', "%{$q}%")
                    ->orWhereRaw('DATE_FORMAT(created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                    ->orWhereHas('user', function ($q3) use ($q) {
                        $q3->where('username', 'like', "%{$q}%");
                    });
            });
        }

        // GENDER FILTER
        if ($gender && $gender !== 'all') {
            $query->where('groomOrBride', $gender);
        }

        // REGISTRATION TYPE FILTER
        if ($reg_type && $reg_type !== 'all') {
            $query->where('registration_type', $reg_type);
        }

        $profiles = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends([
                'q' => $q,
                'gender' => $gender,
                'reg_type' => $reg_type
            ]);

        $searchRouteName = 'profiles.unverifiedProfiles';
        $exportRouteName = 'profile.export';
        $title = 'Unverified Profiles';

        return view('admin.profiles.list', compact(
            'profiles',
            'searchRouteName',
            'exportRouteName',
            'title'
        ));
    }

    public function show(Profile $profile)
    {
        if ((int) Auth::user()->profile_id !== (int) $profile->id && !$this->canViewProfileByRegistrationType($profile)) {
            abort(404);
        }

        // Eager load user.preference and paymentScreenshots to avoid N+1
        $profile->load('user.preference', 'paymentScreenshots');
        $preference = optional($profile->user)->preference;

        // Get templates for biodata download - handle case when directory doesn't exist
        $templates = [];
        $templatesPath = public_path('landingPageAssets/img/templates');
        if (file_exists($templatesPath) && is_dir($templatesPath)) {
            $templates = array_diff(scandir($templatesPath), array('..', '.'));
        }

        // Check if this is the logged-in user's own profile
        if (Auth::user()->profile_id !== $profile->id) {

            // Check if current user is an unverified candidate
            // if (Auth::user()->user_type == '21') {
            //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
            //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
            //         return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
            //     }
            // }

            // Record Profile Visit
            if (Auth::check()) {
                ProfileVisitor::firstOrCreate([
                    'profile_id' => $profile->id,
                    'visitor_id' => Auth::id()
                ]);
            }

            // Redirect to limited view if viewing someone else's profile
            return view('admin.profiles.show', compact('profile', 'preference', 'templates'));
        }

        return view('admin.profiles.show', compact('profile', 'preference', 'templates'));
    }

    // public function showCandidate($id)
    // {
    //     $profile = Profile::findOrFail($id);
    //     $profile->load('user', 'preference');
    //     $preference = $profile->preference;

    //     // If viewing own profile, redirect to full view
    //     if (Auth::user()->profile_id === $profile->id) {
    //         return redirect()->route('profiles.show', $profile->id);
    //     }

    //     // Check if current user is an unverified candidate
    //     if (Auth::user()->user_type == '21') {
    //         $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
    //         if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
    //             return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
    //         }
    //     }

    //     // Record Profile Visit
    //     if (Auth::check()) {
    //         ProfileVisitor::firstOrCreate([
    //             'profile_id' => $profile->id,
    //             'visitor_id' => Auth::id()
    //         ]);
    //     }

    //     return view('admin.profiles.view_candidate', compact('profile', 'preference'));
    // }

    public function showCandidate($id)
    {
        $profile = Profile::findOrFail($id);

        // If viewing own profile
        if (Auth::check() && Auth::user()->profile_id === $profile->id) {
            return redirect()->route(
                'profiles.show',
                $profile->id
            );
        }

        if (!$this->canViewProfileByRegistrationType($profile)) {
            abort(404);
        }

        $profile->load('user.preference');
        $preference = optional($profile->user)->preference;

        // Record Profile Visit
        if (Auth::check()) {
            ProfileVisitor::firstOrCreate([
                'profile_id' => $profile->id,
                'visitor_id' => Auth::id()
            ]);
        }

        // Check if there is an accepted interest between the current user and this profile
        $hasAcceptedInterest = false;
        $canViewDirectContact = false;
        
        if (Auth::check() && Auth::user()->user_type == '21') {
            $myUserId = Auth::id();
            $myProfileId = Auth::user()->profile_id;

            // Check if I sent an interest to them and they accepted it
            $sentAndAccepted = \App\UserInterest::where('user_id', $myUserId)
                ->where('profile_id', $profile->id)
                ->where('status', 'Accepted')
                ->exists();

            // Check if they sent an interest to me and I accepted it
            $receivedAndAccepted = false;
            if ($profile->user) {
                $receivedAndAccepted = \App\UserInterest::where('user_id', $profile->user->id)
                    ->where('profile_id', $myProfileId)
                    ->where('status', 'Accepted')
                    ->exists();
            }

            $hasAcceptedInterest = $sentAndAccepted || $receivedAndAccepted;
            // --------------------------------------------------
            // Age Based Direct Contact Visibility
            // --------------------------------------------------

            

            $loggedInProfile = Profile::find(Auth::user()->profile_id);

                if ($loggedInProfile && $loggedInProfile->birthDate && $profile->birthDate) {

                    $loggedAge = Carbon::parse($loggedInProfile->birthDate)->age;
                    $candidateAge = Carbon::parse($profile->birthDate)->age;

                    // Logged in Groom
                    if ($loggedInProfile->groomOrBride == 'groom') {

                        $canViewDirectContact =
                            ($candidateAge >= ($loggedAge - 6))
                            &&
                            ($candidateAge <= ($loggedAge + 1));

                    }

                    // Logged in Bride
                    elseif ($loggedInProfile->groomOrBride == 'bride') {

                        $canViewDirectContact =
                            ($candidateAge >= ($loggedAge - 1))
                            &&
                            ($candidateAge <= ($loggedAge + 6));

                    }

                }

            }
        

        return view(
            'admin.profiles.view_candidate',
            compact(
                'profile',
                'preference',
                'hasAcceptedInterest',
                'canViewDirectContact'
            )
        );
    }



    public function destroy(Profile $profile)
    {
        $profile->active = 'deactive';
        $profile->save();
        return redirect()->back()->with('success', 'Profile deleted successfully!');
    }

    /**
     * Delete a specific document from a profile.
     * Restricted to Super Admins only.
     */
    public function deleteDocument(Request $request, Profile $profile)
    {


        $documentType = $request->input('document_type');

        // Only allow deletion of profile photos and additional photos by the user
        $allowedFields = [
            'profilePhoto',
            'additionalPhotos'
        ];


        if (!in_array($documentType, $allowedFields)) {
            return redirect()->back()->with('error', 'Invalid document field.');
        }

        if ($documentType == 'additionalPhotos') {
            $photoPath = $request->input('photo_path');
            if ($photoPath) {
                $photos = $profile->additional_photos ?? [];
                if (($key = array_search($photoPath, $photos)) !== false) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
                    }
                    unset($photos[$key]);
                    $profile->additional_photos = array_values($photos);
                    $profile->save();
                    return redirect()->back()->with('success', 'Additional photo deleted successfully.');
                }
            }
            return redirect()->back()->with('error', 'Photo not found.');
        }

        // Check if the document exists
        $filePath = $profile->$documentType;

        // DELETE PROFILE PHOTO
        if ($documentType == 'profilePhoto') {

            if (
                $filePath &&
                \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)
            ) {

                \Illuminate\Support\Facades\Storage::disk('public')->delete($filePath);
            }

            $profile->profilePhoto = null;

            $profile->save();

            return redirect()->back()
                ->with('success', 'Profile photo deleted successfully.');
        }



        if ($filePath) {
            // Remove the file from storage
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($filePath);
            }

            // Clear the database column
            $profile->$documentType = null;
            $profile->save();

            return redirect()->back()->with('success', 'Document has been deleted successfully.');
        }

        return redirect()->back()->with('error', 'No document found to delete.');
    }


    // public function showMatchmakingDashboard($profileId)
    // {
    //     // Find the primary profile or return a 404 error if not found.
    //     $profile = Profile::findOrFail($profileId);

    //     // Fetch all profiles that have an accepted interest from the primary profile.
    //     $matchedProfiles = Profile::join('user_interests', 'profiles.id', '=', 'user_interests.profile_id')
    //                                ->where('user_interests.user_id', $profile->id)
    //                                ->where('user_interests.status', 'accepted')
    //                                ->select('profiles.*')
    //                                ->get();

    //     return view('admin.profileMeetings.showMeetingSchedulars', compact('profile', 'matchedProfiles'));
    // }



    public function getBiodataDetails($id)
    {
        $profile = Profile::with('user', 'preference')->findOrFail($id);
        if (!$this->canViewProfileByRegistrationType($profile)) {
            abort(404);
        }

        // Calculate age
        $age = null;
        if ($profile->birthDate) {
            $age = \Carbon\Carbon::parse($profile->birthDate)->age;
        }

        $currentDate = \Carbon\Carbon::now()->format('m-d');
        $isSpecialDays = in_array($currentDate, ['05-30', '05-31']);
        $canViewContact = $isSpecialDays || (Auth::check() && (Auth::user()->role_id == 1 || Auth::user()->user_type == '00' || Auth::user()->user_type == '51'));

        return response()->json([
            'profile' => $profile,
            'preference' => $profile->preference,
            'age' => $age,
            'dob_formatted' => $profile->birthDate ? \Carbon\Carbon::parse($profile->birthDate)->format('d M, Y') : 'N/A',
            'today_is_special' => $canViewContact
        ]);
    }








    public function search(Request $request)
    {
        // Check if current user is an unverified candidate
        // if (Auth::user()->user_type == '21') {
        //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
        //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
        //         return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
        //     }
        // }

        // Get the authenticated user's gender.
        $loggedInUserGender = Auth::user()->gender ?? 'unknown';
        $profile = Auth::user()->profile_id
            ? Profile::where('id', Auth::user()->profile_id)->first()
            : null;
        if ($profile) {
            $loggedInUserGender = $profile->groomOrBride;
        }

        // Determine the gender of profiles to search for.
        if ($loggedInUserGender === 'bride') {
            $targetGender = 'groom';
        } elseif ($loggedInUserGender === 'groom') {
            $targetGender = 'bride';
        } else {
            // If the user's gender is unknown or a guest, show all profiles.
            $targetGender = null;
        }

        $profilesQuery = Profile::query()
            ->whereHas('user')
            ->where('active', 'active')
            // Allow current Melava batch candidates (October 2026) to be searchable 
            // even if Pending, as per Melava event requirements.
            ->where(function($q) {
                $q->where('profileVerificationStatus', 'Completed')
                  ->orWhere(function($q2) {
                      $q2->where('melava_batch', self::CURRENT_MELAVA_BATCH)
                         ->whereIn('registration_type', ['melava', 'existing']);
                  });
            })

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            });
        // A direct lookup by name / serial no / profile id must be able to find
        // the exact candidate, so the (mixed-case) gender filter is only applied
        // while browsing without a keyword.
        if ($targetGender && !$request->filled('q')) {
            $profilesQuery->where('groomOrBride', $targetGender);
        }

        // Apply filters from the search form.

        // Marital Status Filter
        $profilesQuery->when($request->filled('marital_status'), function ($query) use ($request) {
            return $query->where('maritalStatus', $request->input('marital_status'));
        });

        $profilesQuery->when($request->filled('age_from'), function ($query) use ($request) {
            $minAge = $request->input('age_from');
            $minDob = Carbon::now()->subYears($minAge)->endOfDay();
            return $query->whereDate('birthDate', '<=', $minDob);
        });

        $profilesQuery->when($request->filled('age_to'), function ($query) use ($request) {
            $maxAge = $request->input('age_to');
            $minDob = \Carbon\Carbon::now()->subYears($maxAge + 1)->startOfDay();
            return $query->whereDate('birthDate', '>', $minDob);
        });

        $profilesQuery->when($request->filled('height_from'), function ($query) use ($request) {
            return $query->where('height', '>=', $request->input('height_from'));
        });

        $profilesQuery->when($request->filled('height_to'), function ($query) use ($request) {
            return $query->where('height', '<=', $request->input('height_to'));
        });

        $profilesQuery->when($request->filled('preferred_religion'), function ($query) use ($request) {
            return $query->where('religion', 'like', '%' . $request->input('preferred_religion') . '%');
        });

        $profilesQuery->when($request->filled('preferred_caste'), function ($query) use ($request) {
            return $query->where('caste', 'like', '%' . $request->input('preferred_caste') . '%');
        });

        $profilesQuery->when($request->filled('preferred_sub_caste'), function ($query) use ($request) {
            return $query->where('subCaste', 'like', '%' . $request->input('preferred_sub_caste') . '%');
        });

        // Removed body_type as it's not in the form and not in the model fillable

        $profilesQuery->when($request->filled('preferred_country'), function ($query) use ($request) {
            $term = $request->input('preferred_country');
            return $query->where(function ($q) use ($term) {
                $q->where('professionalCity', 'like', '%' . $term . '%')
                    ->orWhere('fathersNativePlace', 'like', '%' . $term . '%')
                    ->orWhere('mothersNativePlace', 'like', '%' . $term . '%')
                    ->orWhere('cityOfEducation', 'like', '%' . $term . '%');
            });
        });

        $profilesQuery->when($request->filled('preferred_state'), function ($query) use ($request) {
            $term = $request->input('preferred_state');
            return $query->where(function ($q) use ($term) {
                $q->where('professionalCity', 'like', '%' . $term . '%')
                    ->orWhere('fathersNativePlace', 'like', '%' . $term . '%')
                    ->orWhere('mothersNativePlace', 'like', '%' . $term . '%')
                    ->orWhere('cityOfEducation', 'like', '%' . $term . '%');
            });
        });

        $profilesQuery->when($request->filled('preferred_city'), function ($query) use ($request) {
            $term = $request->input('preferred_city');
            return $query->where(function ($q) use ($term) {
                $q->where('professionalCity', 'like', '%' . $term . '%')
                    ->orWhere('fathersNativePlace', 'like', '%' . $term . '%')
                    ->orWhere('mothersNativePlace', 'like', '%' . $term . '%')
                    ->orWhere('cityOfEducation', 'like', '%' . $term . '%');
            });
        });

        // The 'other_expectations' field acts as a generic keyword search
        $profilesQuery->when($request->filled('other_expectations'), function ($query) use ($request) {
            $term = $request->input('other_expectations');
            return $query->where(function ($q) use ($term) {
                $q->where('knowAboutMyself', 'like', '%' . $term . '%')
                    ->orWhere('hobbies', 'like', '%' . $term . '%')
                    ->orWhere('specialCases', 'like', '%' . $term . '%')
                    ->orWhere('familyValues', 'like', '%' . $term . '%')
                    ->orWhere('profession', 'like', '%' . $term . '%')
                    ->orWhere('designation', 'like', '%' . $term . '%')
                    ->orWhere('education', 'like', '%' . $term . '%')
                    ->orWhere('fieldOfWorking', 'like', '%' . $term . '%');

            });
        });
        $profilesQuery->when($request->filled('q'), function ($query) use ($request) {

            $q = trim($request->input('q'));

            // Serial numbers are stored with an age-group prefix and a space
            // (e.g. "A3 021"). Ignore spaces and case while matching so a query
            // like "A3021" or "a3 021" still finds the exact candidate.
            $serial = strtoupper(str_replace(' ', '', $q));

            $query->where(function ($q2) use ($q, $serial) {

                // Search by Serial No (A3 021 / A3021 / B104 / C205)
                $q2->whereRaw(
                    "REPLACE(UPPER(serial_no), ' ', '') = ?",
                    [$serial]
                )

                    // Profile ID
                    ->orWhere(
                        'profile_id',
                        'like',
                        "%{$q}%"
                    )

                    // Full Name
                    ->orWhere(
                        'fullName',
                        'like',
                        "%{$q}%"
                    )

                    // Phone / Username
                    ->orWhereHas(
                        'user',
                        function ($q3) use ($q) {
                            $q3->where(
                                'username',
                                'like',
                                "%{$q}%"
                            );
                        }
                    );
            });
        });

        $profiles = $profilesQuery->paginate(15);

        // Return the view with the filtered profiles.
        return view('admin.profiles.profile_search', compact('profiles'));
    }

    public function markInterested(Request $request)
    {
        // Check if current user is an unverified candidate
        // if (Auth::user()->user_type == '21') {
        //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
        //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
        //         return redirect()->back()->with('error', 'Unverified profiles should not be able to mark interest. Please complete your verification.');
        //     }
        // }

        // Simple validation
        $request->validate(['profile_id' => 'required|integer|exists:profiles,id']);

        // Check if the user is authenticated
        if (!Auth::check()) {
            return redirect()->back()->with('error', 'Please login to show interest.');
        }

        $userId = Auth::id();
        $profileId = $request->input('profile_id');

        // Check if the interest record already exists to prevent duplicates
        $exists = DB::table('user_interests')
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'You have already sent an interest in this candidate.');
        }

        try {
            // Log the interest in the database (requires a 'user_interests' table)
            DB::table('user_interests')->insert([
                'user_id' => $userId,
                'profile_id' => $profileId,
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return redirect()->back()->with('success', 'Interest successfully marked.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to mark interest.');
        }
    }

    public function acceptedInterestProfiles()
    {
        // Check if current user is an unverified candidate
        // if (Auth::user()->user_type == '21') {
        //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
        //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
        //         return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
        //     }
        // }

        $profileId = Auth::user()->profile_id;
        $userId = Auth::id();

        // Interests received by me and accepted
        $sendersUserIds = UserInterest::where('profile_id', $profileId)->where('status', 'Accepted')->pluck('user_id');
        $sendersProfileIds = User::whereIn('id', $sendersUserIds)->pluck('profile_id')->toArray();

        // Interests sent by me and accepted
        $receiversProfileIds = UserInterest::where('user_id', $userId)->where('status', 'Accepted')->pluck('profile_id')->toArray();

        $allAcceptedProfileIds = array_unique(array_merge($sendersProfileIds, $receiversProfileIds));

        $profiles = Profile::whereIn('id', $allAcceptedProfileIds)

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            ->paginate(10);
        return view('admin.profiles.accepted_profiles', compact('profiles'));
    }

    public function ourSuggestionsProfiles()
    {
        // Check if current user is an unverified candidate
        // if (Auth::user()->user_type == '21') {
        //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
        //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
        //         return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
        //     }
        // }

        $user = Auth::user();
        $userProfile = $user->profile;

        if (!$userProfile) {
            $profiles = collect();
            return view('admin.profiles.suggestions', compact('profiles'))->with('error', 'Please complete your profile to get suggestions.');
        }

        // Start Query with basic filters (Active/Verified/Has User, Opposite Gender)
        $query = Profile::where('id', '!=', $userProfile->id)
            ->whereHas('user')
            ->where('active', 'active')
            ->where('profileVerificationStatus', 'Completed')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($q) {
                $this->applyRegistrationTypeScope($q);
            });

        // Filter by Gender/Role (Groom looking for Bride, Bride looking for Groom)
        // Check for 'Groom' or 'Male' (case-insensitive)
        if (strcasecmp($userProfile->groomOrBride, 'Groom') == 0 || strcasecmp($userProfile->groomOrBride, 'Male') == 0) {
            $query->where(function ($q) {
                $q->where('groomOrBride', 'Bride')->orWhere('groomOrBride', 'Female');
            });
        } else {
            $query->where(function ($q) {
                $q->where('groomOrBride', 'Groom')->orWhere('groomOrBride', 'Male');
            });
        }

        // Apply suggestions based on the user's saved preferences
        $preferences = Preference::where('user_id', $user->id)->first();

        if ($preferences) {
            // STRICT FILTER: Age Range Only
            if (!empty($preferences->age_from) && !empty($preferences->age_to)) {
                // Calculate dates: age_from 25 means born <= today-25 years
                // age_to 30 means born >= today-30 years
                $minDate = \Carbon\Carbon::now()->subYears($preferences->age_to + 1)->addDay()->format('Y-m-d');
                $maxDate = \Carbon\Carbon::now()->subYears($preferences->age_from)->format('Y-m-d');
                $query->whereBetween('birthDate', [$minDate, $maxDate]);
            }

            // NON-STRICT PREFERENCES (Prioritize perfectly matched profiles without hiding others)
            $orderSelects = [];
            $orderBindings = [];

            // Religion
            if (!empty($preferences->preferred_religion)) {
                $orderSelects[] = "IF(religion = ?, 1, 0)";
                $orderBindings[] = $preferences->preferred_religion;
            }

            // Caste
            if (!empty($preferences->preferred_caste)) {
                $orderSelects[] = "IF(caste = ?, 1, 0)";
                $orderBindings[] = $preferences->preferred_caste;
            }

            // Sub Caste
            if (!empty($preferences->preferred_sub_caste)) {
                $orderSelects[] = "IF(subCaste LIKE ?, 1, 0)";
                $orderBindings[] = '%' . $preferences->preferred_sub_caste . '%';
            }

            // Height Range
            if (!empty($preferences->height_from) && !empty($preferences->height_to)) {
                $orderSelects[] = "IF(height >= ? AND height <= ?, 1, 0)";
                $orderBindings[] = $preferences->height_from;
                $orderBindings[] = $preferences->height_to;
            }

            // Location (City)
            if (!empty($preferences->preferred_city)) {
                $term = '%' . $preferences->preferred_city . '%';
                $orderSelects[] = "IF(professionalCity LIKE ? OR fathersNativePlace LIKE ? OR mothersNativePlace LIKE ? OR cityOfEducation LIKE ?, 1, 0)";
                $orderBindings[] = $term;
                $orderBindings[] = $term;
                $orderBindings[] = $term;
                $orderBindings[] = $term;
            }

            // Apply priority sorting if any non-strict preferences exist
            if (!empty($orderSelects)) {
                $orderByRaw = '(' . implode(' + ', $orderSelects) . ') DESC';
                $query->orderByRaw($orderByRaw, $orderBindings);
            }
        }

        $query->orderBy('created_at', 'desc');

        $query->when(request()->filled('q'), function ($q) {
            $searchTerm = trim(request('q'));
            $q->where(function ($q2) use ($searchTerm) {
                $q2->where('serial_no', strtoupper($searchTerm))
                    ->orWhere('profile_id', 'like', "%{$searchTerm}%")
                    ->orWhere('fullName', 'like', "%{$searchTerm}%")
                    ->orWhereHas('user', function ($q3) use ($searchTerm) {
                        $q3->where('username', 'like', "%{$searchTerm}%");
                    });
            });
        });

        $profiles = $query->paginate(10)->appends(request()->query());

        return view('admin.profiles.suggestions', compact('profiles'));
    }

    /**
     * Show profiles that the user has shown interest in.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function interstShownProfiles()
    {
        // Check if current user is an unverified candidate
        // if (Auth::user()->user_type == '21') {
        //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
        //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
        //         return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
        //     }
        // }

        $profileId = Auth::user()->profile_id;
        $users = UserInterest::where('profile_id', $profileId)->where('status', 'Pending')->pluck('user_id');
        $profileIds = User::whereIn('id', $users)->pluck('profile_id');
        $profiles = Profile::whereIn('id', $profileIds)
            ->where('profileVerificationStatus', 'Completed')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            ->paginate(10);
        return view('admin.profiles.interest_shown', compact('profiles'));
    }

    /**
     * Show profiles that the user has shown interest in, but the other user has not reciprocated.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function rejectedInterestProfiles()
    {
        // Check if current user is an unverified candidate
        // if (Auth::user()->user_type == '21') {
        //     $currentUserProfile = Profile::where('id', Auth::user()->profile_id)->first();
        //     if (!$currentUserProfile || $currentUserProfile->profileVerificationStatus !== 'Completed') {
        //         return redirect()->route('home')->with('error', 'Unverified profiles should not be able to view other profiles. Please complete your verification.');
        //     }
        // }

        $profileId = Auth::user()->profile_id;
        $userId = Auth::id();

        // Interests received by me and rejected
        $sendersUserIds = UserInterest::where('profile_id', $profileId)->where('status', 'Rejected')->pluck('user_id');
        $sendersProfileIds = User::whereIn('id', $sendersUserIds)->pluck('profile_id')->toArray();

        // Interests sent by me and rejected
        $receiversProfileIds = UserInterest::where('user_id', $userId)->where('status', 'Rejected')->pluck('profile_id')->toArray();

        $allRejectedProfileIds = array_unique(array_merge($sendersProfileIds, $receiversProfileIds));

        $profiles = Profile::whereIn('id', $allRejectedProfileIds)
            ->where('profileVerificationStatus', 'Completed')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            ->paginate(10);

        return view('admin.profiles.rejected_profiles', compact('profiles'));
    }

    public function acceptInterest(Request $request)
    {
        $request->validate(['profile_id' => 'required|exists:profiles,id']);

        // The profile_id from request is the Sender's Profile ID.
        // We need to find the User ID associated with this Profile ID.
        $senderUserId = User::where('profile_id', $request->input('profile_id'))->value('id');

        if (!$senderUserId) {
            return redirect()->back()->with('error', 'Sender user not found.');
        }

        $myProfileId = Auth::user()->profile_id;

        // Find the interest record where the sender (user_id) is interested in me (profile_id)
        $interest = UserInterest::where('user_id', $senderUserId)
            ->where('profile_id', $myProfileId)
            ->first();

        if ($interest) {
            $interest->update(['status' => 'Accepted']);
            return redirect()->back()->with('success', 'Interest accepted successfully. You can now view their details.');
        }

        return redirect()->back()->with('error', 'Interest record not found.');
    }

    /**
     * Reject a pending interest request from another user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function rejectInterest(Request $request)
    {
        $request->validate(['profile_id' => 'required|exists:profiles,id']);

        // The profile_id from request is the Sender's Profile ID.
        // We need to find the User ID associated with this Profile ID.
        $senderUserId = User::where('profile_id', $request->input('profile_id'))->value('id');

        if (!$senderUserId) {
            return redirect()->back()->with('error', 'Sender user not found.');
        }

        $myProfileId = Auth::user()->profile_id;

        // Find the interest record where the sender (user_id) is interested in me (profile_id)
        $interest = UserInterest::where('user_id', $senderUserId)
            ->where('profile_id', $myProfileId)
            ->first();

        if ($interest) {
            $interest->update(['status' => 'Rejected']);
            return redirect()->back()->with('success', 'Interest rejected.');
        }

        return redirect()->back()->with('error', 'Interest record not found.');
    }

    /**
     * Show profiles that the current user has shown interest in (Outgoing).
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function interestedProfiles()
    {
        $userId = Auth::id();
        // Fetch all interests sent by me
        $interests = \App\UserInterest::where('user_id', $userId)->get();
        $profileIds = $interests->pluck('profile_id')->toArray();

        $query = Profile::whereIn('id', $profileIds)
            ->where('profileVerificationStatus', 'Completed')

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && Auth::user()->role_id != 1 && Auth::user()->user_type != '00', function ($q) {
                $this->applyRegistrationTypeScope($q);
            });

        // Apply search query
        $query->when(request()->filled('q'), function ($q) {
            $searchTerm = trim(request('q'));
            $q->where(function ($q2) use ($searchTerm) {
                $q2->where('serial_no', strtoupper($searchTerm))
                    ->orWhere('profile_id', 'like', "%{$searchTerm}%")
                    ->orWhere('fullName', 'like', "%{$searchTerm}%")
                    ->orWhereHas('user', function ($q3) use ($searchTerm) {
                        $q3->where('username', 'like', "%{$searchTerm}%");
                    });
            });
        });

        $profiles = $query->paginate(10)->appends(request()->query());

        // Map status to each profile
        $interestsMap = $interests->keyBy('profile_id');
        foreach ($profiles as $profile) {
            $profile->interestStatus = $interestsMap[$profile->id]->status ?? 'Pending';
        }

        return view('admin.profiles.interested_profiles', compact('profiles'));
    }

    public function markAttendance($id)
    {
        try {
            $profile = Profile::findOrFail($id);

            // Find the candidate's Melava registration
            $registration = MelavaRegistration::where(
                'profile_id',
                $profile->id
            )
            ->where('melava_batch', $profile->melava_batch ?? 'october_2026')
            ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Melava registration not found for this candidate.'
                ], 404);
            }

            // Mark attendance only once
            if ($registration->attendance_status !== 'present') {
                $registration->attendance_status = 'present';
                $registration->attended_at = now();
                $registration->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Attendance marked as Present successfully.'
            ]);

        } catch (\Throwable $e) {
            \Log::error('Error marking Melava attendance: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to update attendance.'
            ], 500);
        }
    }

    public function exportMelavaAttendance()
    {
        $batch = 'october_2026';

        $registrations = MelavaRegistration::with('profile')
            ->where('melava_batch', $batch)
            ->where('attendance_status', 'present')
            ->whereHas('profile')
            ->get()
            ->sortBy(function ($registration) {
                return $registration->profile->serial_no ?? '';
            });

        $filename = 'melava_attendance_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($registrations) {
            $file = fopen('php://output', 'w');

            // Support Marathi and other Unicode characters in Excel
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Candidate Profile ID',
                'Serial Number',
                'Candidate Name',
                'Mobile Number',
                'Mother Name',
                'Father Name',
                'Gender',
                'Marital Status',
                'Attendance Status',
                'Attendance Date & Time',
            ]);

            foreach ($registrations as $registration) {
                $profile = $registration->profile;

                fputcsv($file, [
                    $profile->profile_id ?? '',
                    $profile->serial_no ?? '',
                    $profile->fullName ?? '',
                    $profile->phone ?? '',
                    $profile->mothersName ?? '',
                    $profile->fathersName ?? '',
                    $profile->groomOrBride ?? '',
                    $profile->maritalStatus ?? '',
                    'Present',
                    $registration->attended_at
                        ? \Carbon\Carbon::parse($registration->attended_at)->format('d-m-Y h:i A')
                        : '',
                ]);
            }

            fclose($file);
        }, 200, $headers);
    }


}
 
