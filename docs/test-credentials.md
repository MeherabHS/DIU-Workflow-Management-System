# Test User Credentials

These credentials are created by the database seeder (`RolePermissionSeeder`). Use for testing and development only.

| Role | Email | Password |
|------|-------|----------|
| **Admin** | `admin@example.com` | `password` |
| **PM/Manager** | `pm@example.com` | `password` |
| **Coordinator** | `coordinator@example.com` | `password` |
| **Subordinate** | `subordinate@example.com` | `password` |

## Creating Additional Test Users

Admin users can create additional users via the User Management page (`/admin/users`).

## Resetting Test Data

To reset all test data and re-seed:

```bash
php artisan migrate:fresh --seed
```

**Warning:** This will delete all data in the database.

## Seeded Data

After running `php artisan db:seed`:

- 4 users (one per role)
- All 34 permissions created
- All 4 roles created with appropriate permissions

No projects, tasks, or other data is seeded by default. Create these via the application UI or via factories in tests.
