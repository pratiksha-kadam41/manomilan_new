<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\SuccessStory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
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
        try {
            $stories = SuccessStory::where('status', 'Active')
                ->orderByDesc('is_featured')
                ->orderBy('display_order')
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stories,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load success stories.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC: Get Single Story
    |--------------------------------------------------------------------------
    */

    public function publicShow($id)
    {
        try {
            $story = SuccessStory::where('status', 'Active')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $story,
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load this success story.',
            ], 500);
        }
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

        try {
            $stories = SuccessStory::orderBy('display_order')
                ->latest()
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $stories,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load success stories.',
            ], 500);
        }
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

        try {
            $story = SuccessStory::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $story,
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load this success story.',
            ], 500);
        }
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

            'groom_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
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

        $uploadedPaths = [];

        try {
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
                    $path = $request->file($field)->store('success_stories', 'public');
                    if ($path === false) {
                        throw new RuntimeException("Unable to store {$field}.");
                    }

                    $uploadedPaths[] = $path;
                    $data[$field] = $path;
                }
            }

            $story = DB::transaction(fn () => SuccessStory::create($data));

            return response()->json([
                'success' => true,
                'message' => 'Success story created successfully.',
                'data' => $story,
            ], 201);

        } catch (Throwable $e) {
            $this->deleteMediaFiles($uploadedPaths);
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

        $uploadedPaths = [];
        $replacedPaths = [];

        try {
            $data = $validated;
            $data['is_featured'] = $request->boolean('is_featured');

            foreach ([
                'groom_photo',
                'bride_photo',
                'couple_photo',
                'wedding_photo',
            ] as $field) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store('success_stories', 'public');
                    if ($path === false) {
                        throw new RuntimeException("Unable to store {$field}.");
                    }

                    $uploadedPaths[] = $path;
                    if ($story->$field) {
                        $replacedPaths[] = $story->$field;
                    }
                    $data[$field] = $path;
                }
            }

            DB::transaction(function () use ($story, $data): void {
                if (!$story->update($data)) {
                    throw new RuntimeException('Success story update was not persisted.');
                }
            });
            $this->deleteMediaFiles($replacedPaths);

            return response()->json([
                'success' => true,
                'message' => 'Success story updated successfully.',
                'data' => $story->fresh(),
            ]);

        } catch (Throwable $e) {
            $this->deleteMediaFiles($uploadedPaths);
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

        try {
            $paths = array_filter([
                $story->groom_photo,
                $story->bride_photo,
                $story->couple_photo,
                $story->wedding_photo,
            ]);

            DB::transaction(function () use ($story): void {
                if (!$story->delete()) {
                    throw new RuntimeException('Success story deletion was not persisted.');
                }
            });
            $this->deleteMediaFiles($paths);

            return response()->json([
                'success' => true,
                'message' => 'Success story deleted successfully.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete success story.',
            ], 500);
        }
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

        try {
            if (!$story->update([
                'status' => $validated['status'],
            ])) {
                throw new RuntimeException('Success story status update was not persisted.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Story status updated successfully.',
                'data' => $story,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update story status.',
            ], 500);
        }
    }

    private function deleteMediaFiles(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            try {
                if (Storage::disk('public')->exists($path)
                    && !Storage::disk('public')->delete($path)) {
                    report(new RuntimeException("Unable to delete success story image: {$path}"));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}