<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\CandidateRequest;

class CandidateRequestController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Show Public Form
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        // We order by ID Descending so that the newest registrations (and latest imports) 
        // appear at the top of the list for the admin to see immediately.
        $query = CandidateRequest::where('status', 1)
                    ->orderBy('id', 'desc');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->get('name') . '%');
        }
        if ($request->filled('occupation')) {
            $query->where('occupation', 'like', '%' . $request->get('occupation') . '%');
        }

        $candidates = $query->paginate(20)->appends($request->only(['name', 'occupation']));
        return view('candidate_requests.index', compact('candidates'));
    }
public function show($id)
{
    $candidate = CandidateRequest::findOrFail($id);
    return view('candidate_requests.show', compact('candidate'));
}
    public function create()
    {
        return view('candidate_requests.create');
    }


    /*
    |--------------------------------------------------------------------------
    | Store Public Registration
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
{
    $request->validate([
        'name' => 'required',
        'birth_date' => 'required',
        'birth_time' => 'required',
        'marital_status' => 'required',
        'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
    ]);

    $imageName = null;

    if ($request->hasFile('image')) {
        $image = $request->file('image');
        $imageName = time().'.'.$image->getClientOriginalExtension();
        $image->move(public_path('candidate_images'), $imageName);
    }

    CandidateRequest::create([
        'name' => $request->name,
        'birth_date' => $request->birth_date,
        'birth_time' => $request->birth_time,
        'height' => $request->height,
        'marital_status' => $request->marital_status,
        'education' => $request->education,
        'address' => $request->address,
        'subcast' => $request->subcast,
        'occupation' => $request->occupation,
        'annual_income' => $request->annual_income,
        'married_brothers' => $request->married_brothers,
        'unmarried_brothers' => $request->unmarried_brothers,
        'married_sisters' => $request->married_sisters,
        'unmarried_sisters' => $request->unmarried_sisters,
        'expectations' => $request->expectations,
        'status' => 1,
        'image' => $imageName
    ]);

    return redirect()->route('paymentpage')->with('success', 'Registration submitted successfully!');
}


    /*
    |--------------------------------------------------------------------------
    | Edit Request (Admin)
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $candidate = CandidateRequest::findOrFail($id);
        return view('candidate_requests.edit', compact('candidate'));
    }


    /*
    |--------------------------------------------------------------------------
    | Update Request (Admin)
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $candidate = CandidateRequest::findOrFail($id);

        $request->validate([
            'name' => 'required',
            'birth_date' => 'required',
            'birth_time' => 'required',
            'terms_accepted' => 'accepted'
        ]);

        // Handle image removal/upload
        $imageName = $candidate->image;
        if ($request->boolean('remove_image')) {
            if (!empty($imageName) && file_exists(public_path('candidate_images/' . $imageName))) {
                @unlink(public_path('candidate_images/' . $imageName));
            }
            $imageName = null;
        } elseif ($request->hasFile('image')) {
            // Replace existing with new upload
            if (!empty($imageName) && file_exists(public_path('candidate_images/' . $imageName))) {
                @unlink(public_path('candidate_images/' . $imageName));
            }
            $image = $request->file('image');
            $imageName = time().'.'.$image->getClientOriginalExtension();
            $image->move(public_path('candidate_images'), $imageName);
        }

        $candidate->update([
            'name' => $request->name,
            'birth_date' => $request->birth_date,
            'birth_time' => $request->birth_time,
            'height' => $request->height,
            'marital_status' => $request->marital_status,
            'education' => $request->education,
            'address' => $request->address,
            'image' => $imageName,
            'subcast' => $request->subcast,
            'occupation' => $request->occupation,
            'annual_income' => $request->annual_income,
            'married_brothers' => $request->married_brothers,
            'unmarried_brothers' => $request->unmarried_brothers,
            'married_sisters' => $request->married_sisters,
            'unmarried_sisters' => $request->unmarried_sisters,
            'expectations' => $request->expectations,
            'status' => $request->status,
            'terms_accepted' => 1,
            'updated_by' => auth()->id() ?? null
        ]);

        return redirect()->route('candidate.edit', ['id' => $candidate->id])
    ->with('success', 'Candidate updated successfully!');
    }
    
    /**
     * Soft remove candidate request by setting status = 0
     */
    public function remove($id)
    {
        $candidate = CandidateRequest::findOrFail($id);
        $candidate->status = 0;
        $candidate->updated_by = auth()->id() ?? null;
        $candidate->save();
        return redirect()->route('candidate.index')->with('success', 'Candidate removed successfully.');
    }

    /**
     * Helper method to parse Date of Birth and Time from various CSV formats.
     * It cleans the input and tries multiple common formats to find a match.
     */
    private function parseDobTime($value)
    {
        if (empty($value)) {
            return [null, null];
        }

        // 1. Clean the input string (remove quotes, fix separators, etc.)
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value); // Remove hidden characters
        $value = str_replace(['"', "'"], '', $value); // Remove quotes
        $value = str_replace(['/', '.'], '-', $value); // Standardize date separators to dash
        $value = str_replace(';', ':', $value); // Standardize time separators to colon
        $value = preg_replace('/\s+/', ' ', $value); // Fix multiple spaces

        // 2. Define the date formats we expect in the CSV
        $formats = [
            'd-m-Y h:i A',  // Example: 11-06-2000 03:20 PM
            'd-m-Y h:iA',   // Example: 11-06-2000 03:20PM
            'd-m-Y H:i',    // Example: 11-06-2000 15:20
            'd-m-Y H:i:s',  // Example: 11-06-2000 15:20:30
        ];

        $dateTimeObject = null;

        // 3. Try to parse the string with each format until one works
        foreach ($formats as $format) {
            $dateTimeObject = \DateTime::createFromFormat($format, $value);

            if ($dateTimeObject instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                // Ensure there are no parsing warnings or errors
                if ($errors['warning_count'] == 0 && $errors['error_count'] == 0) {
                    break;
                }
                $dateTimeObject = null;
            }
        }

        // 4. Final validation and extraction
        if (!$dateTimeObject) {
            return [null, null]; // Return nulls if no format matched
        }

        // Reject obviously wrong years
        $year = (int)$dateTimeObject->format('Y');
        if ($year < 1900 || $year > date('Y')) {
            return [null, null];
        }

        // Return the split Date and Time strings
        return [
            $dateTimeObject->format('Y-m-d'),
            $dateTimeObject->format('H:i:s')
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | CSV Data Import
    |--------------------------------------------------------------------------
    | This method handles the uploading and processing of candidate data from 
    | a CSV file. It ensures data is inserted in the exact order it appears 
    | in the file.
    */
    public function importCSV(Request $request)
    {
        // 1. Basic validation to ensure a file is provided
        $request->validate([
            'file' => 'required|mimes:csv,txt'
        ]);

        // 2. Open the file for reading
        $filePath = $request->file('file')->getRealPath();
        $fileHandle = fopen($filePath, 'r');

        // 3. Skip the header row (first line of the CSV)
        fgetcsv($fileHandle);

        // 4. Use a Database Transaction to ensure all data is saved correctly and sequentially
        \DB::beginTransaction();

        try {
            // Keep track of the row number for logging or debugging
            $rowCount = 0;

            while (($row = fgetcsv($fileHandle)) !== FALSE) {
                // Skip rows where the name (column 2) is empty
                if (empty($row[1])) {
                    continue;
                }

                // Parse Date of Birth and Time using our helper method
                list($birthDate, $birthTime) = $this->parseDobTime($row[2]);

                // Parse Family Details (Brothers/Sisters) from the text column
                $familyDetails = $this->parseFamilyDetails($row[10] ?? '');

                // Create the candidate record in the database
                // Since this runs in a simple loop, the IDs will be assigned sequentially
                \App\CandidateRequest::create([
                    'name'             => trim($row[1]),
                    'birth_date'       => $birthDate,
                    'birth_time'       => $birthTime,
                    'height'           => $row[3] ?? null,
                    'marital_status'   => $row[4] ?? null,
                    'education'        => $row[5] ?? null,
                    'address'          => $row[6] ?? null,
                    'subcast'          => $row[7] ?? null,
                    'occupation'       => $row[8] ?? null,
                    'annual_income'    => $row[9] ?? null,
                    'married_brothers' => $familyDetails['marriedBrothers'],
                    'unmarried_brothers' => $familyDetails['unmarriedBrothers'],
                    'married_sisters'  => $familyDetails['marriedSisters'],
                    'unmarried_sisters'=> $familyDetails['unmarriedSisters'],
                    'expectations'     => $row[11] ?? null,
                    'status'           => 1 // Mark as active
                ]);

                $rowCount++;
            }

            // If everything is successful, save the changes permanently
            \DB::commit();
            fclose($fileHandle);

            return back()->with('success', "Success! {$rowCount} candidates imported in the correct sequence.");

        } catch (\Exception $e) {
            // If any error happens, undo all changes to keep data clean
            \DB::rollBack();
            fclose($fileHandle);
            \Log::error("CSV Import Error: " . $e->getMessage());

            return back()->with('error', 'Something went wrong during the import. The process was stopped to prevent data errors.');
        }
    }

    /**
     * Helper method to extract family member counts from a text string.
     * This makes the main import loop much cleaner and easier to read.
     */
    private function parseFamilyDetails($text)
    {
        $text = strtolower($text);
        
        // Default values
        $details = [
            'marriedBrothers'   => 0,
            'unmarriedBrothers' => 0,
            'marriedSisters'    => 0,
            'unmarriedSisters'  => 0
        ];

        if (empty($text)) {
            return $details;
        }

        // Standardize common terms and spelling variations
        $replacements = [
            'marned' => 'married',
            'umarind' => 'unmarried',
            'brodies' => 'brothers',
            'sister:' => 'sisters',
            'sisters:' => 'sisters',
            ',' => ' ',
            '.' => ' ',
            ':' => ' '
        ];
        $cleanText = str_replace(array_keys($replacements), array_values($replacements), $text);

        // Extract Brother details
        if (preg_match('/brothers.*?married\s*(\d+)/i', $cleanText, $match)) {
            $details['marriedBrothers'] = (int)$match[1];
        }
        if (preg_match('/brothers.*?unmarried\s*(\d+)/i', $cleanText, $match)) {
            $details['unmarriedBrothers'] = (int)$match[1];
        }

        // Extract Sister details
        if (preg_match('/sisters?.*?married\s*(\d+)/i', $cleanText, $match)) {
            $details['marriedSisters'] = (int)$match[1];
        }
        if (preg_match('/sisters?.*?unmarried\s*(\d+)/i', $cleanText, $match)) {
            $details['unmarriedSisters'] = (int)$match[1];
        }

        return $details;
    }

    public function activate($id)
{
    $candidate = CandidateRequest::findOrFail($id);
    $candidate->status = 1;
    $candidate->updated_by = auth()->id() ?? null;
    $candidate->save();

    return redirect()->route('candidate.inactive')->with('success', 'Candidate activated successfully.');
}

    public function inactive(Request $request)
    {
        // Sort by ID Descending to show the most recently removed/inactive candidates first.
        $query = CandidateRequest::where('status', 0)
                    ->orderBy('id', 'desc');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->get('name') . '%');
        }
        if ($request->filled('occupation')) {
            $query->where('occupation', 'like', '%' . $request->get('occupation') . '%');
        }

        $candidates = $query->paginate(20)->appends($request->only(['name', 'occupation']));
        return view('candidate_requests.inactive', compact('candidates'));
    }
}
