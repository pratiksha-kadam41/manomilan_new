# Mano Milan - REST API Documentation

Base URL: http://localhost:8000/api

## Authentication
- POST /register - Register new user
- POST /login - Login and get token
- POST /logout (auth) - Logout
- PUT /change-password (auth) - Change password
- GET /user (auth) - Get authenticated user

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
- POST /melava-registrations (public)
- GET /melava-registrations (auth)
- GET /melava-registrations/{id} (auth)

## Workshop Registrations
- POST /workshop-registrations (public)
- GET /workshop-registrations (auth)

## Payments
- POST /payments/upload (auth) - multipart/form-data
- GET /payments/my (auth)
- GET /payments (auth)
- PUT /payments/{id}/status (auth)

