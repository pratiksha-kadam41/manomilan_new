<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Profile;
use Illuminate\Support\Facades\Auth;

class NriController extends Controller
{
    private $keywords = ['USA', 'US', 'UK', 'United Kingdom', 'England', 'Scotland', 'Australia', 'Canada', 'Dubai', 'UAE', 'Singapore', 'Germany', 'France', 'Denmark', 'Sweden', 'New Zealand', 'Malaysia', 'Netherlands', 'Japan', 'Virginia', 'Melbourne', 'Copenhagen', 'London', 'Chicago', 'Texas', 'Seattle', 'Amsterdam', 'Winnipeg', 'NJ', 'California', 'Dallas', 'Austin', 'Arizona', 'Hartford', 'Tokyo', 'Kuala Lumpur', 'Glasgow'];

    public function index(Request $request)
    {
        $q = $request->query('q');
        $gender = $request->query('gender');
        $reg_type = $request->query('reg_type');
        $keywords = $this->keywords;

        $isAdmin = Auth::check() && (Auth::user()->role_id == 1 || Auth::user()->user_type == '00');

        $profilesQuery = Profile::with('user', 'paymentScreenshots')
            ->where('profiles.active', 'active')
            ->where(function($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) <= 2) {
                        $query->orWhereRaw('profiles.professionalCity REGEXP ?', ['\\b' . $keyword . '\\b']);
                    } else {
                        $query->orWhere('profiles.professionalCity', 'LIKE', '%' . $keyword . '%');
                    }
                }
            })

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && !$isAdmin, function ($query) {
                $this->applyRegistrationTypeScope($query);
            });

        if (!$isAdmin) {
            // Standard Candidate Filter Logic
            $profilesQuery->where('profileVerificationStatus', 'Completed');
            $loggedInProfile = Profile::where('id', Auth::user()->profile_id)->first();
            
            if ($loggedInProfile) {
                $profilesQuery->where('id', '!=', $loggedInProfile->id);
                $targetGender = (strcasecmp($loggedInProfile->groomOrBride, 'Groom') == 0 || strcasecmp($loggedInProfile->groomOrBride, 'Male') == 0) ? 'Bride' : 'Groom';
                $profilesQuery->where(function($query) use ($targetGender) {
                    if ($targetGender === 'Bride') {
                        $query->where('groomOrBride', 'Bride')->orWhere('groomOrBride', 'Female');
                    } else {
                        $query->where('groomOrBride', 'Groom')->orWhere('groomOrBride', 'Male');
                    }
                });
            }

            // Apply standard search filters from profile_search.blade.php
            $profilesQuery->when($request->filled('marital_status'), function ($query) use ($request) {
                return $query->where('maritalStatus', $request->input('marital_status'));
            });

            $profilesQuery->when($request->filled('age_from'), function ($query) use ($request) {
                $minAge = $request->input('age_from');
                $maxDob = \Carbon\Carbon::now()->subYears($minAge)->endOfDay();
                return $query->whereDate('birthDate', '<=', $maxDob);
            });

            $profilesQuery->when($request->filled('age_to'), function ($query) use ($request) {
                $maxAge = $request->input('age_to');
                $minDob = \Carbon\Carbon::now()->subYears($maxAge + 1)->startOfDay();
                return $query->whereDate('birthDate', '>', $minDob);
            });

            // $profilesQuery->when($request->filled('preferred_religion'), function ($query) use ($request) {
            //     return $query->where('religion', 'like', '%' . $request->input('preferred_religion') . '%');
            // });
            // $profilesQuery->when($request->filled('preferred_caste'), function ($query) use ($request) {
            //     return $query->where('caste', 'like', '%' . $request->input('preferred_caste') . '%');
            // });
            $profilesQuery->when($request->filled('preferred_sub_caste'), function ($query) use ($request) {
                return $query->where('profiles.subCaste', 'like', '%' . $request->input('preferred_sub_caste') . '%');
            });
            $profilesQuery->when($request->filled('preferred_city'), function ($query) use ($request) {
                $term = $request->input('preferred_city');
                return $query->where(function ($q) use ($term) {
                    $q->where('profiles.professionalCity', 'like', '%' . $term . '%')
                        ->orWhere('profiles.fathersNativePlace', 'like', '%' . $term . '%')
                        ->orWhere('profiles.mothersNativePlace', 'like', '%' . $term . '%')
                        ->orWhere('profiles.cityOfEducation', 'like', '%' . $term . '%');
                });
            });
            $profilesQuery->when($request->filled('other_expectations'), function ($query) use ($request) {
                $term = $request->input('other_expectations');
                return $query->where(function ($q) use ($term) {
                    $q->where('profiles.knowAboutMyself', 'like', '%' . $term . '%')
                        ->orWhere('profiles.hobbies', 'like', '%' . $term . '%')
                        ->orWhere('profiles.specialCases', 'like', '%' . $term . '%')
                        ->orWhere('profiles.familyValues', 'like', '%' . $term . '%')
                        ->orWhere('profiles.profession', 'like', '%' . $term . '%')
                        ->orWhere('profiles.designation', 'like', '%' . $term . '%')
                        ->orWhere('profiles.education', 'like', '%' . $term . '%')
                        ->orWhere('profiles.fieldOfWorking', 'like', '%' . $term . '%');
                });
            });
        }

        $profilesQuery->when($q, function ($query) use ($q) {
            $query->where(function ($q2) use ($q) {
                $q2->where('profiles.profile_id', 'like', "%{$q}%")
                    ->orWhere('profiles.fullName', 'like', "%{$q}%")
                    ->orWhere('profiles.profileVerificationStatus', 'like', "%{$q}%")
                    ->orWhereRaw('DATE_FORMAT(profiles.created_at, "%d-%m-%Y") like ?', ["%{$q}%"])
                    ->orWhereHas('user', function ($q3) use ($q) {
                        $q3->where('username', 'like', "%{$q}%");
                    });
            });
        });

        if ($isAdmin) {
            $profilesQuery->when($gender && $gender !== 'all', function ($query) use ($gender) {
                $query->where('profiles.groomOrBride', $gender);
            });
            $profilesQuery->when($reg_type && $reg_type !== 'all', function ($query) use ($reg_type) {
                if ($reg_type === 'melava_existing') {
                    $query->whereIn('profiles.id', \DB::table('melava_registrations')->select('profile_id'))
                        ->whereIn('profiles.registration_type', ['melava', 'existing'])
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } elseif ($reg_type === 'melava') {
                    $query->whereIn('profiles.id', \DB::table('melava_registrations')->select('profile_id'))
                        ->where('profiles.registration_type', 'melava')
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } else {
                    $query->where('profiles.registration_type', $reg_type);
                }
            });
        }

        $profiles = $profilesQuery->orderBy('created_at', 'desc')->paginate($isAdmin ? 10 : 15)->appends($request->all());

        $searchRouteName = 'profiles.nriProfiles';
        $exportRouteName = 'profiles.nriExport';

        if (!$isAdmin) {
            return view('admin.profiles.profile_search', compact('profiles', 'searchRouteName'));
        }

        return view('admin.profiles.list', compact(
            'profiles',
            'q',
            'gender',
            'reg_type',
            'searchRouteName',
            'exportRouteName'
        ));
    }

    public function export(Request $request)
    {
        $q = $request->query('q');
        $gender = $request->query('gender');
        $reg_type = $request->query('reg_type');
        $keywords = $this->keywords;

        $isAdmin = Auth::check() && (Auth::user()->role_id == 1 || Auth::user()->user_type == '00');

        $profiles = Profile::with('user', 'paymentScreenshots')
            ->where('profiles.active', 'active')
            ->where(function($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) <= 2) {
                        $query->orWhereRaw('profiles.professionalCity REGEXP ?', ['\\b' . $keyword . '\\b']);
                    } else {
                        $query->orWhere('profiles.professionalCity', 'LIKE', '%' . $keyword . '%');
                    }
                }
            })

            // REGISTRATION TYPE VISIBILITY
            ->when(Auth::check() && !$isAdmin, function ($query) {
                $this->applyRegistrationTypeScope($query);
            })

            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profiles.profile_id', 'like', "%{$q}%")
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
                    $query->whereIn('profiles.id', \DB::table('melava_registrations')->select('profile_id'))
                        ->whereIn('profiles.registration_type', ['melava', 'existing'])
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } elseif ($reg_type === 'melava') {
                    $query->whereIn('profiles.id', \DB::table('melava_registrations')->select('profile_id'))
                        ->where('profiles.registration_type', 'melava')
                        ->where(function ($q) {
                            $q->where('profiles.registration_number', 'LIKE', 'MM3%')
                                ->orWhere('profiles.profile_id', 'LIKE', 'MM3%');
                        });
                } else {
                    $query->where('profiles.registration_type', $reg_type);
                }
            })
            ->when(Auth::check() && Auth::user()->user_type == '51', function ($query) {
                $query->where('profiles.registration_type', 'melava');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = "nri_profiles_export_" . date('Y-m-d_H-i-s') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($profiles) {
            $handle = fopen('php://output', 'w');

            // Add headers
            fputcsv($handle, [
                'Registration No',
                'Full Name',
                'Mobile Number',
                'Email',
                'DOB',
                'Date of Registration',
                'Gender',
                'Registration Type',
                'Marital Status',
                'Caste',
                'Education',
                'Profession',
                'City',
                'Status'
            ]);

            foreach ($profiles as $profile) {
                fputcsv($handle, [
                    $profile->registration_number ?? $profile->profile_id,
                    $profile->fullName,
                    $profile->phone ?? ($profile->user->username ?? 'N/A'),
                    $profile->email ?? 'N/A',
                    $profile->birthDate ? date('d-m-Y', strtotime($profile->birthDate)) : 'N/A',
                    date('d-m-Y', strtotime($profile->created_at)),
                    ucfirst($profile->groomOrBride ?? 'N/A'),
                    $profile->registration_type === 'melava' ? 'Melava' : 'Quick',
                    $profile->maritalStatus ?? 'N/A',
                    $profile->caste ?? 'N/A',
                    $profile->education ?? 'N/A',
                    $profile->profession ?? 'N/A',
                    $profile->professionalCity ?? 'N/A',
                    $profile->profileVerificationStatus
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
