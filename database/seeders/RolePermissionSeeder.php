<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'resource' => 'dashboard', 'action' => 'view'],
            ['name' => 'View Articles', 'slug' => 'articles.view', 'resource' => 'articles', 'action' => 'view'],
            ['name' => 'Edit Articles', 'slug' => 'articles.edit', 'resource' => 'articles', 'action' => 'edit'],
            ['name' => 'Delete Articles', 'slug' => 'articles.delete', 'resource' => 'articles', 'action' => 'delete'],
            ['name' => 'View Topics', 'slug' => 'topics.view', 'resource' => 'topics', 'action' => 'view'],
            ['name' => 'Edit Topics', 'slug' => 'topics.edit', 'resource' => 'topics', 'action' => 'edit'],
            ['name' => 'Delete Topics', 'slug' => 'topics.delete', 'resource' => 'topics', 'action' => 'delete'],
            ['name' => 'View Categories', 'slug' => 'categories.view', 'resource' => 'categories', 'action' => 'view'],
            ['name' => 'Edit Categories', 'slug' => 'categories.edit', 'resource' => 'categories', 'action' => 'edit'],
            ['name' => 'Delete Categories', 'slug' => 'categories.delete', 'resource' => 'categories', 'action' => 'delete'],
            ['name' => 'Trigger Discovery', 'slug' => 'discovery.trigger', 'resource' => 'discovery', 'action' => 'trigger'],
            ['name' => 'Manage Generation', 'slug' => 'generation.manage', 'resource' => 'generation', 'action' => 'manage'],
            ['name' => 'View Users', 'slug' => 'users.view', 'resource' => 'users', 'action' => 'view'],
            ['name' => 'Edit Users', 'slug' => 'users.edit', 'resource' => 'users', 'action' => 'edit'],
            ['name' => 'View Audit Logs', 'slug' => 'audit.view', 'resource' => 'audit', 'action' => 'view'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'resource' => 'settings', 'action' => 'manage'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], [
            'name' => 'Administrator',
            'description' => 'Full access to all admin features',
        ]);

        $editorRole = Role::firstOrCreate(['slug' => 'editor'], [
            'name' => 'Editor',
            'description' => 'Can manage articles and topics, view dashboard',
        ]);

        $viewerRole = Role::firstOrCreate(['slug' => 'viewer'], [
            'name' => 'Viewer',
            'description' => 'Read-only access to articles and topics',
        ]);

        $adminRole->permissions()->sync(Permission::pluck('id'));

        $editorPerms = Permission::whereIn('slug', [
            'dashboard.view', 'articles.view', 'articles.edit',
            'topics.view', 'topics.edit', 'categories.view',
            'discovery.trigger', 'generation.manage',
        ])->pluck('id');
        $editorRole->permissions()->sync($editorPerms);

        $viewerPerms = Permission::whereIn('slug', [
            'dashboard.view', 'articles.view', 'topics.view', 'categories.view',
        ])->pluck('id');
        $viewerRole->permissions()->sync($viewerPerms);

        $adminUser = User::where('email', 'admin@thetrustjournal.com')->first();
        if ($adminUser) {
            $adminUser->roles()->sync([$adminRole->id]);
        }
    }
}