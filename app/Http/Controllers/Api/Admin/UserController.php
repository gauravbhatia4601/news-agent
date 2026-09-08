<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%'.$request->search.'%')
                ->orWhere('email', 'ilike', '%'.$request->search.'%');
        }

        $users = $query->orderByDesc('created_at')->paginate(25);

        return response()->json($users->through(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'is_admin' => $u->is_admin,
            'created_at' => $u->created_at->toIso8601String(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->is_admin = false;
        $user->save();

        AuditLogService::log('create', 'User', $user->id);

        return response()->json(['data' => $user->fresh()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,'.$id,
        ]);

        $user->update($validated);

        AuditLogService::log('update', 'User', $id);

        return response()->json(['data' => $user->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete your own account'], 422);
        }

        $user = User::findOrFail($id);

        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return response()->json(['message' => 'Cannot delete the last admin user'], 422);
        }

        $user->delete();

        AuditLogService::log('delete', 'User', $id);

        return response()->json(['message' => 'User deleted']);
    }
}
