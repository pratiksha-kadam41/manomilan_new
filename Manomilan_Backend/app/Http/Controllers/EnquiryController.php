<?php

namespace App\Http\Controllers;

use App\Enquiry;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function index()
    {
        $enquiries = Enquiry::latest()->paginate(10);
        return view('enquiries.list', compact('enquiries'));
    }

    public function create()
    {
        return view('enquiries.create');
    }

    public function store(Request $request)
    {
        // Validate the input data
        // $validatedData = $request->validate([
        //     'melavaNo' => 'required|string|max:255',
        //     'name' => 'required|string|max:255',
        //     'address' => 'required|string',
        //     'city' => 'nullable|string|max:255',
        //     'taluka' => 'nullable|string|max:255',
        //     'dist' => 'nullable|string|max:255',
        //     'contactPersonName1' => 'required|string|max:255',
        //     'contactPersonMobile1' => 'required|string|max:20',
        //     'contactPersonName2' => 'nullable|string|max:255',
        //     'contactPersonMobile2' => 'nullable|string|max:20',
        //     'contactPersonName3' => 'nullable|string|max:255',
        //     'contactPersonMobile3' => 'nullable|string|max:20',
        //     'email' => 'nullable|email|max:255',
        //     'education' => 'nullable|string|max:255',
        //     'DOB' => 'nullable|date',
        //     'time' => 'nullable',
        //     'place' => 'nullable|string|max:255',
        //     'height' => 'nullable|string|max:50',
        //     'potJat' => 'nullable|string|max:255',
        //     'parentName' => 'nullable|string|max:255',
        //     'job' => 'nullable|string|max:255',
        //     'monthlyIncome' => 'nullable|string|max:255',
        //     'expectation' => 'nullable|string|max:255',
        //     'extraInformation' => 'nullable|string|max:255',
        //     'location' => 'nullable|string|max:255',
        //     'mobileNo' => 'nullable|string|max:20',
        //     'status' => 'required|in:active,inactive',
        // ]);

        $enquiry = new Enquiry();

        // Assign values manually
        $enquiry->melavaNo = $request->melavaNo;
        $enquiry->name = $request->name;
        $enquiry->gender = $request->gender;
        $enquiry->address = $request->address;
        $enquiry->city = $request->city;
        $enquiry->taluka = $request->taluka;
        $enquiry->dist = $request->dist;
        $enquiry->contactPersonName1 = $request->contactPersonName1;
        $enquiry->contactPersonMobile1 = $request->contactPersonMobile1;
        $enquiry->contactPersonName2 = $request->contactPersonName2;
        $enquiry->contactPersonMobile2 = $request->contactPersonMobile2;
        $enquiry->contactPersonName3 = $request->contactPersonName3;
        $enquiry->contactPersonMobile3 = $request->contactPersonMobile3;
        $enquiry->email = $request->email;
        $enquiry->education = $request->education;
        $enquiry->DOB = $request->DOB;
        $enquiry->time = $request->time;
        $enquiry->place = $request->place;
        $enquiry->height = $request->height;
        $enquiry->potJat = $request->potJat;
        $enquiry->parentName = $request->parentName;
        $enquiry->job = $request->job;
        $enquiry->monthlyIncome = $request->monthlyIncome;
        $enquiry->expectation = $request->expectation;
        $enquiry->extraInformation = $request->extraInformation;
        $enquiry->location = $request->location;
        $enquiry->mobileNo = $request->mobileNo;
        $enquiry->status = 1;
        $enquiry->updated_by = auth()->id(); // or set manually if needed

        $enquiry->save();

        return redirect()->route('enquiries.index')->with('success', 'Enquiry created successfully.');
    }


    public function edit(Enquiry $enquiry)
    {
        return view('enquiries.edit', compact('enquiry'));
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'melavaNo' => 'required',
            'name' => 'required',
            'address' => 'required',
            'city' => 'required',
            'taluka' => 'required',
            'dist' => 'required',
            'contactPersonName1' => 'required',
            'contactPersonMobile1' => 'required',
            'email' => 'nullable|email',
            'monthlyIncome' => 'nullable|numeric',
            'DOB' => 'nullable|date',
            'time' => 'nullable|date_format:H:i',
            'status' => 'required',
        ]);

        $enquiry->update($data);
        return redirect()->route('enquiries.index')->with('success', 'Enquiry updated successfully.');
    }

    public function show(Enquiry $enquiry)
    {
        return view('enquiries.view', compact('enquiry'));
    }
}
