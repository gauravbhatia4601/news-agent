<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::with('children')->whereNull('parent_id')->orderBy('display_order')->get();

        return response()->json(['data' => $categories->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'display_order' => $c->display_order,
            'children' => $c->children->map(fn ($ch) => [
                'id' => $ch->id,
                'name' => $ch->name,
                'slug' => $ch->slug,
                'description' => $ch->description,
                'display_order' => $ch->display_order,
            ]),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:categories,slug',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }

        $category = Category::create($validated);

        AuditLogService::log('create', 'Category', $category->id);

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:120|unique:categories,slug,'.$id,
            'description' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        AuditLogService::log('update', 'Category', $id);

        return response()->json(['data' => $category->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        AuditLogService::log('delete', 'Category', $id);

        return response()->json(['message' => 'Category deleted']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:categories,id',
            'orders.*.display_order' => 'required|integer',
        ]);

        foreach ($validated['orders'] as $item) {
            Category::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }

        AuditLogService::log('reorder', 'Category', null, ['count' => count($validated['orders'])]);

        return response()->json(['message' => 'Order updated']);
    }
}
