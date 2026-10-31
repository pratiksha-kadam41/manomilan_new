# Mano Milan - REST API Documentation

Base URL: http://localhost:8000/api

## Authentication
- POST /register - Register new user
- POST /login - Login and get token
- POST /logout (auth) - Logout
- PUT /change-password (auth) - Change password
- GET /user (auth) - Get authenticated user
- GET /system/my-permissions (auth) - Load the current role's database-backed module permissions

## System Management (Super Admin)
- GET /system - Roles, modules, and system statistics
- POST /system/roles - Create a role
- PUT /system/roles/{role} - Edit or activate/deactivate a role
- DELETE /system/roles/{role} - Delete a role with no assigned users (protected roles cannot be deleted)
- GET /system/roles/{role}/permissions - Read a role's module permission matrix
- PUT /system/roles/{role}/permissions - Replace a role's grants transactionally
- POST /system/modules - Create a module with descriptive name and route metadata
- PUT /system/modules/{module} - Edit or activate/deactivate a module
- DELETE /system/modules/{module} - Delete an unused module
- GET /system/users - Search and list user role assignments
- PATCH /system/users/{user}/role - Assign a role to another user
- GET /system/audit-logs - Paginated, filterable activity log
- GET /system/audit-logs/export - Export filtered audit logs as CSV

Apply the RBAC schema with:
```sh
php artisan migrate --path=database/migrations/2026_10_31_000000_create_dynamic_rbac_and_audit_tables.php
php artisan migrate --path=database/migrations/2026_10_31_000001_separate_dashboard_and_reports_permissions.php
```

Existing roles are enabled by default. Super Admin receives all module permissions; Candidate receives scoped self-service grants. Other roles start with no grants and must be configured by Super Admin. API authorization is deny-by-default for protected authenticated routes.

## Profiles
- GET /profiles (auth) - List profiles
- GET /profiles/{id} (auth) - Get profile
- GET /my-profile (auth) - Get current user's profile
- POST /profiles (auth) - Create profile

## Dashboard
- GET /dashboard (auth) - Dashboard stats
- GET /home-data (auth) - Home data

## Interests
- GET /interests/sent (auth)
- GET /interests/received (auth)
- POST /interests/send (auth)
- POST /interests/{id}/respond (auth)

## Preferences
- GET /preferences (auth)
- POST /preferences (auth)

## Tickets
- GET /tickets (auth)
- POST /tickets (auth)
- GET /tickets/{id} (auth)
- PUT /tickets/{id} (auth)

## Enquiries
- POST /enquiries (public)
- GET /enquiries (auth)
- GET /enquiries/{id} (auth)
- PUT /enquiries/{id} (auth)
- DELETE /enquiries/{id} (auth)

## Melava Registrations
- GET /melava-campaigns/active (public) - Active event titles, backend-set fees and payment QR images
- GET /melava-registrations/settings (public) - Active Melava event settings
- POST /melava-registrations (public) - Submit registration and payment screenshot; include `melava_campaign_id`
- GET /melava-registrations/check?mobile={mobile}&melava_campaign_id={id} (public) - Find a candidate and event registration
- GET /melava-registrations (auth)
- GET /melava-registrations/{id} (auth)
- PUT /melava-registrations/{id}/payment-status (admin) - Verify or reopen payment review
- GET /melava-campaigns (admin) - List Melava batches
- POST /melava-campaigns (admin, multipart/form-data) - Create a batch with fees, booklet option and QR image
- PUT /melava-campaigns/{id}/status (admin) - Open or close registration for a batch

## Workshop Registrations
- POST /workshop-registrations (public)
- GET /workshop-registrations (auth)

## Payments
- POST /payments/upload (auth) - multipart/form-data
- GET /payments/my (auth)
- GET /payments (auth)
- PUT /payments/{id}/status (auth)
