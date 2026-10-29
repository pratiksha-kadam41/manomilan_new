<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\SuccessStory;
use Illuminate\Support\Facades\Auth;

class SuccessStoryController extends Controller
{
    /**
     * Display active success stories.
     */
    public function index()
    {
        $stories = SuccessStory::where('status', 'Active')
                    ->orderBy('display_order')
                    ->latest()
                    ->get();

        return view('admin.success_stories.index', compact('stories'));
    }

    /**
     * Display inactive success stories.
     */
    public function inactive()
    {
        $stories = SuccessStory::where('status', 'Inactive')
                    ->orderBy('display_order')
                    ->latest()
                    ->get();

        return view('admin.success_stories.inactive', compact('stories'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('admin.success_stories.create');
    }

    /**
     * Store success story.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'groom_name' => 'required|string|max:255',
            'bride_name' => 'required|string|max:255',
            'marriage_date' => 'nullable|date',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'groom_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'bride_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'couple_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'wedding_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'story_title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'full_story' => 'nullable|string',
            'family_message' => 'nullable|string',
            'rating' => 'required|integer|min:1|max:5',
            'is_featured' => 'nullable|boolean',
            'display_order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
        ]);

        $data = $validated;
        $data['is_featured'] = $request->has('is_featured') ? 1 : 0;
        $data['created_by'] = Auth::id();

        // Handle file uploads
        if ($request->hasFile('groom_photo')) {
            $groomPhoto = $request->file('groom_photo');
            $groomPhotoName = time() . '_groom_' . $groomPhoto->getClientOriginalName();
            $groomPhoto->move(public_path('uploads/success_stories'), $groomPhotoName);
            $data['groom_photo'] = 'uploads/success_stories/' . $groomPhotoName;
        }

        if ($request->hasFile('bride_photo')) {
            $bridePhoto = $request->file('bride_photo');
            $bridePhotoName = time() . '_bride_' . $bridePhoto->getClientOriginalName();
            $bridePhoto->move(public_path('uploads/success_stories'), $bridePhotoName);
            $data['bride_photo'] = 'uploads/success_stories/' . $bridePhotoName;
        }

        if ($request->hasFile('couple_photo')) {
            $couplePhoto = $request->file('couple_photo');
            $couplePhotoName = time() . '_couple_' . $couplePhoto->getClientOriginalName();
            $couplePhoto->move(public_path('uploads/success_stories'), $couplePhotoName);
            $data['couple_photo'] = 'uploads/success_stories/' . $couplePhotoName;
        }

        if ($request->hasFile('wedding_photo')) {
            $weddingPhoto = $request->file('wedding_photo');
            $weddingPhotoName = time() . '_wedding_' . $weddingPhoto->getClientOriginalName();
            $weddingPhoto->move(public_path('uploads/success_stories'), $weddingPhotoName);
            $data['wedding_photo'] = 'uploads/success_stories/' . $weddingPhotoName;
        }

        SuccessStory::create($data);

        return redirect()->route('successStories.index')->with('success', 'Success story created successfully!');
    }

    /**
     * View success story.
     */
    public function show($id)
    {
        $story = SuccessStory::findOrFail($id);

        return view('admin.success_stories.show', compact('story'));
    }

    /**
     * Edit success story.
     */
    public function edit($id)
    {
        $story = SuccessStory::findOrFail($id);

        return view('admin.success_stories.edit', compact('story'));
    }

    /**
     * Update success story.
     */
    public function update(Request $request, $id)
    {
        $story = SuccessStory::findOrFail($id);

        $validated = $request->validate([
            'groom_name' => 'required|string|max:255',
            'bride_name' => 'required|string|max:255',
            'marriage_date' => 'nullable|date',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'groom_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'bride_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'couple_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'wedding_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'story_title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'full_story' => 'nullable|string',
            'family_message' => 'nullable|string',
            'rating' => 'required|integer|min:1|max:5',
            'is_featured' => 'nullable|boolean',
            'display_order' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'remove_groom_photo' => 'nullable|boolean',
            'remove_bride_photo' => 'nullable|boolean',
            'remove_couple_photo' => 'nullable|boolean',
            'remove_wedding_photo' => 'nullable|boolean',
        ]);

        $data = $validated;
        $data['is_featured'] = $request->has('is_featured') ? 1 : 0;

        // Handle file removals
        if ($request->has('remove_groom_photo') && $story->groom_photo) {
            if (file_exists(public_path($story->groom_photo))) {
                unlink(public_path($story->groom_photo));
            }
            $data['groom_photo'] = null;
        }

        if ($request->has('remove_bride_photo') && $story->bride_photo) {
            if (file_exists(public_path($story->bride_photo))) {
                unlink(public_path($story->bride_photo));
            }
            $data['bride_photo'] = null;
        }

        if ($request->has('remove_couple_photo') && $story->couple_photo) {
            if (file_exists(public_path($story->couple_photo))) {
                unlink(public_path($story->couple_photo));
            }
            $data['couple_photo'] = null;
        }

        if ($request->has('remove_wedding_photo') && $story->wedding_photo) {
            if (file_exists(public_path($story->wedding_photo))) {
                unlink(public_path($story->wedding_photo));
            }
            $data['wedding_photo'] = null;
        }

        // Handle file uploads
        if ($request->hasFile('groom_photo')) {
            if ($story->groom_photo && file_exists(public_path($story->groom_photo))) {
                unlink(public_path($story->groom_photo));
            }
            $groomPhoto = $request->file('groom_photo');
            $groomPhotoName = time() . '_groom_' . $groomPhoto->getClientOriginalName();
            $groomPhoto->move(public_path('uploads/success_stories'), $groomPhotoName);
            $data['groom_photo'] = 'uploads/success_stories/' . $groomPhotoName;
        }

        if ($request->hasFile('bride_photo')) {
            if ($story->bride_photo && file_exists(public_path($story->bride_photo))) {
                unlink(public_path($story->bride_photo));
            }
            $bridePhoto = $request->file('bride_photo');
            $bridePhotoName = time() . '_bride_' . $bridePhoto->getClientOriginalName();
            $bridePhoto->move(public_path('uploads/success_stories'), $bridePhotoName);
            $data['bride_photo'] = 'uploads/success_stories/' . $bridePhotoName;
        }

        if ($request->hasFile('couple_photo')) {
            if ($story->couple_photo && file_exists(public_path($story->couple_photo))) {
                unlink(public_path($story->couple_photo));
            }
            $couplePhoto = $request->file('couple_photo');
            $couplePhotoName = time() . '_couple_' . $couplePhoto->getClientOriginalName();
            $couplePhoto->move(public_path('uploads/success_stories'), $couplePhotoName);
            $data['couple_photo'] = 'uploads/success_stories/' . $couplePhotoName;
        }

        if ($request->hasFile('wedding_photo')) {
            if ($story->wedding_photo && file_exists(public_path($story->wedding_photo))) {
                unlink(public_path($story->wedding_photo));
            }
            $weddingPhoto = $request->file('wedding_photo');
            $weddingPhotoName = time() . '_wedding_' . $weddingPhoto->getClientOriginalName();
            $weddingPhoto->move(public_path('uploads/success_stories'), $weddingPhotoName);
            $data['wedding_photo'] = 'uploads/success_stories/' . $weddingPhotoName;
        }

        $story->update($data);

        return redirect()->route('successStories.index')->with('success', 'Success story updated successfully!');
    }

    /**
     * Delete success story.
     */
    public function destroy($id)
    {
        $story = SuccessStory::findOrFail($id);

        // Delete associated photos
        if ($story->groom_photo && file_exists(public_path($story->groom_photo))) {
            unlink(public_path($story->groom_photo));
        }
        if ($story->bride_photo && file_exists(public_path($story->bride_photo))) {
            unlink(public_path($story->bride_photo));
        }
        if ($story->couple_photo && file_exists(public_path($story->couple_photo))) {
            unlink(public_path($story->couple_photo));
        }
        if ($story->wedding_photo && file_exists(public_path($story->wedding_photo))) {
            unlink(public_path($story->wedding_photo));
        }

        $story->delete();

        return redirect()->route('successStories.index')->with('success', 'Success story deleted successfully!');
    }

    /**
     * Activate success story.
     */
    public function activate($id)
    {
        $story = SuccessStory::findOrFail($id);
        $story->update(['status' => 'Active']);

        return redirect()->route('successStories.inactive')->with('success', 'Success story activated successfully!');
    }

    /**
     * Deactivate success story.
     */
    public function deactivate($id)
    {
        $story = SuccessStory::findOrFail($id);
        $story->update(['status' => 'Inactive']);

        return redirect()->route('successStories.index')->with('success', 'Success story deactivated successfully!');
    }
}