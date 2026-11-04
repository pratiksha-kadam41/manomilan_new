<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExposeRecordEditorNames
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        if (! $user instanceof User || $user->portal() !== 'admin') {
            $this->removeEditorNames($payload);
            $response->setData($payload);

            return $response;
        }

        $editorIds = [];
        $this->collectEditorIds($payload, $editorIds);

        if ($editorIds === []) {
            return $response;
        }

        $editorNames = User::query()
            ->whereIn('id', array_values($editorIds))
            ->pluck('name', 'id');

        $this->attachEditorNames($payload, $editorNames->all());
        $response->setData($payload);

        return $response;
    }

    private function removeEditorNames(mixed &$value): void
    {
        if (! is_array($value)) {
            return;
        }

        unset($value['updated_by_user_name']);
        foreach ($value as &$nestedValue) {
            $this->removeEditorNames($nestedValue);
        }
    }

    private function collectEditorIds(mixed $value, array &$editorIds): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach (['updated_by_user_id'] as $field) {
            $id = $value[$field] ?? null;
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $editorIds[(int) $id] = (int) $id;
            }
        }

        foreach ($value as $nestedValue) {
            $this->collectEditorIds($nestedValue, $editorIds);
        }
    }

    private function attachEditorNames(mixed &$value, array $editorNames): void
    {
        if (! is_array($value)) {
            return;
        }

        if (array_key_exists('updated_by_user_id', $value)) {
            $value['updated_by_user_name'] = $editorNames[(int) $value['updated_by_user_id']] ?? null;
        }

        foreach ($value as &$nestedValue) {
            $this->attachEditorNames($nestedValue, $editorNames);
        }
    }
}
