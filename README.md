# 📋 Campus Attendance System
## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [Installation & Setup](#installation--setup)
- [Default Credentials](#default-credentials)
- [User Roles & Access](#user-roles--access)
- [API Endpoints](#api-endpoints)
- [Security](#security)
- [Screenshots](#screenshots)

---

## Overview

The **Campus Attendance System** is a web-based application that enables educational institutions to manage and track student attendance digitally. The system supports three distinct roles — **Admin**, **Teacher**, and **Student** — each with a dedicated dashboard and access-controlled features.

Teachers can create class sessions, mark attendance in real time, and generate reports. Students can view their own attendance records, while administrators have full control over users, courses, departments, and system-wide settings.

---

## Features

### 👨‍💼 Admin

- Dashboard with live statistics (teachers, students, classes, today's sessions)
- Visual charts: campus-wide attendance doughnut & department-wise bar chart
- Manage departments, courses, teachers, and students
- View system-wide attendance reports
- Configure global settings (school name, minimum attendance threshold, academic year)

### 👩‍🏫 Teacher

- View assigned classes and schedules
- Create sessions with a date and topic
- Mark student attendance per session (Present / Absent / Late / Excused)
- Edit past attendance records via history view
- Generate per-class attendance reports

### 👨‍🎓 Student

- View personal attendance summary per course
- Track attendance percentage against the minimum threshold

### 🔐 Authentication

- Single unified login page for all roles
- Role-based automatic redirect after login
- Password change support
- CSRF-protected forms

---

## Tech Stack

| Layer    | Technology                      |
| -------- | ------------------------------- |
| Backend  | PHP 8.1+ (procedural)           |
| Database | MySQL 8.0+ via PDO              |
| Frontend | Bootstrap 5.3, Font Awesome 6.5 |
| Charts   | Chart.js (via CDN)              |
| AJAX     | Vanilla JavaScript (Fetch API)  |
| Auth     | PHP Sessions + CSRF tokens      |

---

## Project Structure

```
attendance-system/
│
├── index.php                   # Root redirect to login
│
├── auth/
│   ├── login.php               # Login page (all roles)
│   ├── process_login.php       # Login form handler
│   ├── logout.php              # Session destroy & redirect
│   └── change_password.php     # Password change form
│
├── admin/
│   ├── index.php               # Admin dashboard (stats + charts)
│   ├── departments.php         # Department management (CRUD)
│   ├── courses.php             # Course management (CRUD)
│   ├── teachers.php            # Teacher management (CRUD)
│   ├── students.php            # Student management (CRUD)
│   ├── reports.php             # System-wide attendance reports
│   └── settings.php            # Global application settings
│
├── teacher/
│   ├── index.php               # Teacher dashboard
│   ├── my-classes.php          # View assigned classes
│   ├── take-attendance.php     # Create session & mark attendance
│   ├── history.php             # View & edit past sessions
│   └── reports.php             # Per-class attendance reports
│
├── student/
│   ├── index.php               # Student dashboard
│   └── attendance.php          # Personal attendance view
│
├── api/
│   ├── mark_attendance.php     # AJAX endpoint: save attendance records
│   ├── get_students.php        # AJAX endpoint: fetch enrolled students
│   └── chart_data.php          # AJAX endpoint: chart data for dashboards
│
├── includes/
│   ├── auth.php                # Session helpers & role-based access control
│   ├── functions.php           # Shared helper functions
│   ├── header.php              # Common HTML header & navbar
│   └── footer.php              # Common HTML footer & scripts
│
├── config/
│   └── database.php            # DB constants & PDO singleton factory
│
├── assets/
│   ├── css/
│   │   └── style.css           # Application-wide styles
│   └── js/
│       ├── main.js             # Global JS utilities & toast notifications
│       ├── attendance.js       # Attendance marking logic (AJAX)
│       └── charts.js           # Chart.js wrapper functions
│
└── attendance.sql              # Full database schema + seed data
```

---

## Database Schema

The system uses **7 tables** with strict foreign key relationships:

```
departments ──< users (dept_id)
departments ──< courses (dept_id)
courses     ──< classes (course_id)
users       ──< classes (teacher_id)
classes     ──< enrollments (class_id)
users       ──< enrollments (student_id)
classes     ──< sessions (class_id)
users       ──< sessions (teacher_id)
sessions    ──< attendance (session_id)
users       ──< attendance (student_id)
```

| Table         | Description                             |
| ------------- | --------------------------------------- |
| `departments` | Academic departments (name, code)       |
| `users`       | All users: admins, teachers, students   |
| `courses`     | Courses linked to departments           |
| `classes`     | Course sections assigned to a teacher   |
| `enrollments` | Student–class enrollment records        |
| `sessions`    | Individual class sessions (date, topic) |
| `attendance`  | Per-session attendance with status enum |
| `settings`    | Key-value configuration store           |

**Attendance statuses:** `present` · `absent` · `late` · `excused`

---

## Installation & Setup

### Prerequisites

- PHP **8.1** or higher
- MySQL **8.0** or higher
- A web server: **Apache** (with `mod_rewrite`) or **Nginx**
- Recommended local stack: [XAMPP](https://www.apachefriends.org/), [Laragon](https://laragon.org/), or [WAMP](https://www.wampserver.com/)

### Steps

**1. Clone or extract the project**

```bash
git clone <repository-url>
# or extract the ZIP into your web server's document root
```

**2. Place in web root**

Move the `attendance-system/` folder into your server's document root, e.g.:

- XAMPP → `C:/xampp/htdocs/attendance-system/`
- Linux → `/var/www/html/attendance-system/`

**3. Create the database**

Open **phpMyAdmin** or a MySQL client and run the included SQL file:

```sql
SOURCE /path/to/attendance-system/attendance.sql;
```

This will create the `attendance_system` database, all tables, and seed demo data automatically.

**4. Configure the database connection**

Open `config/database.php` and update the credentials if needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_system');
define('DB_USER', 'root');       // Change for production
define('DB_PASS', '');           // Change for production
```

> ⚠️ **Production tip:** Move `config/database.php` above the web root and reference it via an absolute path to avoid exposing credentials.

**5. Set directory permissions (Linux/macOS)**

```bash
chmod -R 755 attendance-system/
chmod -R 775 attendance-system/assets/
```

**6. Start the server and open the app**

```
http://localhost/attendance-system/
```

You will be redirected to the login page automatically.

---

## Default Credentials

| Role    | Email            | Password      |
| ------- | ---------------- | ------------- |
| Admin   | admin@campus.edu | `Admin@123`   |
| Teacher | alice@campus.edu | `Teacher@123` |
| Teacher | bob@campus.edu   | `Teacher@123` |
| Teacher | carol@campus.edu | `Teacher@123` |
| Student | emma@campus.edu  | `Admin@123`   |
| Student | james@campus.edu | `Admin@123`   |

> 🔐 Change all default passwords immediately in a production environment.

---

## User Roles & Access

| Page / Feature              | Admin | Teacher | Student |
| --------------------------- | :---: | :-----: | :-----: |
| Dashboard (own)             |  ✅   |   ✅    |   ✅    |
| Manage departments          |  ✅   |   ❌    |   ❌    |
| Manage courses              |  ✅   |   ❌    |   ❌    |
| Manage teachers             |  ✅   |   ❌    |   ❌    |
| Manage students             |  ✅   |   ❌    |   ❌    |
| View all reports            |  ✅   |   ❌    |   ❌    |
| System settings             |  ✅   |   ❌    |   ❌    |
| View assigned classes       |  ❌   |   ✅    |   ❌    |
| Take attendance             |  ❌   |   ✅    |   ❌    |
| Edit past sessions          |  ❌   |   ✅    |   ❌    |
| Teacher reports (own class) |  ❌   |   ✅    |   ❌    |
| View own attendance         |  ❌   |   ❌    |   ✅    |
| Change password             |  ✅   |   ✅    |   ✅    |

---

## API Endpoints

All API endpoints are located under `api/` and return JSON. They require an active authenticated session.

### `POST /api/mark_attendance.php`

Saves attendance records for a session. Requires `teacher` or `admin` role.

**Request body (JSON):**

```json
{
  "csrf_token": "<token>",
  "session_id": 5,
  "records": [
    { "student_id": 3, "status": "present" },
    { "student_id": 4, "status": "absent" },
    { "student_id": 5, "status": "late" }
  ]
}
```

**Response:**

```json
{ "success": true, "message": "Attendance saved." }
```

---

### `GET /api/get_students.php?session_id=<id>`

Returns the list of enrolled students for a given session.

---

### `GET /api/chart_data.php`

Returns aggregated attendance data used for dashboard charts.

---

## Security

The application implements several security measures:

- **CSRF Protection** — All state-changing forms and AJAX requests validate a per-session CSRF token generated via `bin2hex(random_bytes(32))`.
- **Password Hashing** — Passwords are stored using PHP's `password_hash()` (bcrypt, cost 10) and verified with `password_verify()`.
- **Role-Based Access Control** — Every protected page calls `require_role()`, which checks the session and redirects unauthorized users.
- **PDO Prepared Statements** — All database queries use parameterized statements to prevent SQL injection.
- **Output Escaping** — All user-supplied data rendered in HTML is escaped with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Strict PDO Error Mode** — Database errors are logged server-side, never exposed to the client.
- **Session Hardening** — Sessions are started with strict mode; session IDs are regenerated on login.

---

## Screenshots

> _Screenshots can be added here once the application is running locally._

| View                           | Description                                   |
| ------------------------------ | --------------------------------------------- |
| `/auth/login.php`              | Unified login page for all roles              |
| `/admin/index.php`             | Admin dashboard with charts and activity feed |
| `/teacher/take-attendance.php` | Session creation and attendance marking       |
| `/student/attendance.php`      | Student personal attendance summary           |

---

_Built with PHP, MySQL, Bootstrap 5, and Chart.js._
