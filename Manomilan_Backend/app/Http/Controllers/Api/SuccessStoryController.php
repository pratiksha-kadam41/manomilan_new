<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\SuccessStory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class SuccessStoryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | PUBLIC: Get Active Stories
    |--------------------------------------------------------------------------
    */

    public function publicIndex()
    {
        $stories = SuccessStory::where('status', 'Active')
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stories,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC: Get Single Story
    |--------------------------------------------------------------------------
    */

    public function publicShow($id)
    {
        $story = SuccessStory::where('status', 'Active')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $story,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN: Get All Stories
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $stories = SuccessStory::orderBy('display_order')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $stories,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN: Get Single Story
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $story = SuccessStory::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $story,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN: Create Story
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'groom_name' => 'required|string|max:255',
            'bride_name' => 'required|string|max:255',

            'marriage_date' => 'nullable|date',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',

            'groom_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'bride_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'couple_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'wedding_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

            'story_title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'full_story' => 'nullable|string',
            'family_message' => 'nullable|string',

            'rating' => 'required|integer|min:1|max:5',
            'is_featured' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',

            'status' => [
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ]);

        try {
            $story = DB::transaction(function () use ($request, $validated) {

                $data = $validated;

                $data['is_featured'] = $request->boolean('is_featured');
                $data['created_by'] = $request->user()->id;

                foreach ([
                    'groom_photo',
                    'bride_photo',
                    'couple_photo',
                    'wedding_photo',
                ] as $field) {

                    if ($request->hasFile($field)) {

                        $data[$field] = $request->file($field)
                            ->store('success_stories', 'public');
                    }
                }

                return SuccessStory::create($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Success story created successfully.',
                'data' => $story,
            ], 201);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create success story.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN: Update Story
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $story = SuccessStory::findOrFail($id);

        $validated = $request->validate([
            'groom_name' => 'required|string|max:255',
            'bride_name' => 'required|string|max:255',

            'marriage_date' => 'nullable|date',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',

            'groom_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'bride_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'couple_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'wedding_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

            'story_title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'full_story' => 'nullable|string',
            'family_message' => 'nullable|string',

            'rating' => 'required|integer|min:1|max:5',
            'is_featured' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',

            'status' => [
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ]);

        try {

            DB::transaction(function () use ($request, $validated, $story) {

                $data = $validated;

                $data['is_featured'] = $request->boolean('is_featured');

                foreach ([
                    'groom_photo',
                    'bride_photo',
                    'couple_photo',
                    'wedding_photo',
                ] as $field) {

                    if ($request->hasFile($field)) {

                        if ($story->$field) {
                            Storage::disk('public')->delete($story->$field);
                        }

                        $data[$field] = $request->file($field)
                            ->store('success_stories', 'public');
                    }
                }

                $story->update($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Success story updated successfully.',
                'data' => $story->fresh(),
            ]);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update success story.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN: Delete Story
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $story = SuccessStory::findOrFail($id);

        foreach ([
            'groom_photo',
            'bride_photo',
            'couple_photo',
            'wedding_photo',
        ] as $field) {

            if ($story->$field) {
                Storage::disk('public')->delete($story->$field);
            }
        }

        $story->delete();

        return response()->json([
            'success' => true,
            'message' => 'Success story deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN: Change Story Status
    |--------------------------------------------------------------------------
    */

    public function updateStatus(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:Active,Inactive',
        ]);

        $story = SuccessStory::findOrFail($id);

        $story->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Story status updated successfully.',
            'data' => $story,
        ]);
    }
}