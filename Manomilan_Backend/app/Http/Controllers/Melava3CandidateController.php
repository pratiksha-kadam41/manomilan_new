<?php

namespace App\Http\Controllers;

use App\Profile;
use Illuminate\Http\Request;

class Melava3CandidateController extends Controller
{
    /**
     * Candidates registered for the Melava 3 batch (MM3 profiles of the
     * current Melava batch). This list is shown to the Guest Viewer role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $q = $request->q;
        $gender = $request->gender;
        $reg_type = $request->reg_type;

        $profiles = Profile::where('profile_id', 'like', 'MM3-%')
            ->where('melava_batch', self::CURRENT_MELAVA_BATCH)
            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('profile_id', 'like', "%{$q}%")
                        ->orWhere('fullName', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhereRaw('DATE_FORMAT(created_at, "%d-%m-%Y") like ?', ["%{$q}%"]);
                });
            })
            ->when($gender && $gender !== 'all', function ($query) use ($gender) {
                $query->where('groomOrBride', $gender);
            })
            ->when($reg_type && $reg_type !== 'all', function ($query) use ($reg_type) {
                $query->where('registration_type', $reg_type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends(['q' => $q, 'gender' => $gender, 'reg_type' => $reg_type]);

        return view('admin.melava3.list', compact('profiles', 'q', 'gender', 'reg_type'));
    }
}
