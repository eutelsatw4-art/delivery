# Delivery Waybill System

Lightweight PHP + MySQL app for creating, viewing, editing, and printing delivery notes and waybills.

## Features
- Login/logout authentication
- Auto-generated Order IDs (`ORD-MMYY-XXXX`)
- Create delivery notes with dynamic item rows
- View and print formatted waybills
- Searchable records dashboard
- Edit existing delivery notes
- Print-ready responsive layout

## Tech
- PHP 8.3
- MySQL
- HTML/CSS, no frameworks

## Local Development
Place the folder in your web root (e.g. `htdocs`) and visit:
```
http://localhost/delivery/login.php
```
Default login: `admin` / `admin`

## Dokploy Deployment
1. Push this repo to GitHub
2. In Dokploy, create a new **Application** from this GitHub repo
3. Add a **MySQL Database** service in Dokploy
4. Link the database to the application
5. Set the following environment variables in the application:
   - `DB_HOST` - your Dokploy database host
   - `DB_NAME` - database name
   - `DB_USER` - database user
   - `DB_PASS` - database password
6. Deploy

Dokploy will build the Docker image automatically from the included `Dockerfile`.
