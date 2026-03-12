# PWMS — Party Worker Management System

A comprehensive web-based platform for managing political campaign workers, coordinating field tasks, tracking performance, and analyzing voter sentiment across constituencies, wards, and booths.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Chart.js](https://img.shields.io/badge/Chart.js-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)
![OpenStreetMap](https://img.shields.io/badge/OpenStreetMap-7EBC6F?style=for-the-badge&logo=openstreetmap&logoColor=white)

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Screenshots](#screenshots)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Default Credentials](#default-credentials)
- [Usage](#usage)
- [API Endpoints](#api-endpoints)
- [Security](#security)
- [Gamification System](#gamification-system)
- [Contributing](#contributing)
- [License](#license)

---

## Features

### Admin Panel
| Feature | Description |
|---------|-------------|
| **Dashboard** | KPI cards, task status charts, constituency breakdown, top performers |
| **Worker Management** | Add/edit/delete workers with geographic assignments (constituency/ward/booth) |
| **Task Management** | Create, assign, and track tasks with priority levels and campaign types |
| **Live Map** | Real-time worker locations on OpenStreetMap with time-range filtering |
| **Check-in Tracking** | Monitor worker check-ins/check-outs with geolocation data |
| **Leaderboard** | Gamified rankings with points, levels, and badge tracking |
| **AI Sentiment Analysis** | Analyze voter feedback — positive/neutral/negative breakdown by ward |
| **Smart Task Assignment** | AI-powered worker recommendations based on performance, workload, and proximity |
| **Performance Reports** | Automated reports with date ranges, booth breakdown, and PDF/print export |
| **Area Management** | Define constituencies, wards, and booths with GPS coordinates |
| **Data Export** | Export workers, tasks, and reports to CSV/Excel |

### Worker Portal
| Feature | Description |
|---------|-------------|
| **Dashboard** | Personal stats, completion rate, rank, level/XP progress, recent activity |
| **My Tasks** | View assigned tasks, update status, add remarks |
| **GPS Check-in** | Geolocation check-in/check-out with 500m geo-fencing validation |
| **Voter Feedback** | Collect and submit voter feedback with auto sentiment analysis |
| **Route Planner** | OSRM-based optimized routing between assigned task locations |
| **Profile & Badges** | View earned badges, points history, and performance stats |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP (Procedural) |
| **Database** | MySQL (InnoDB, UTF-8MB4) |
| **Frontend** | HTML5, CSS3 (Glassmorphism), Vanilla JavaScript |
| **Charts** | Chart.js |
| **Maps** | OpenStreetMap + Leaflet.js |
| **Routing** | OSRM (Open Source Routing Machine) |
| **Icons** | Font Awesome 6.4.0 |
| **Fonts** | Inter (Google Fonts) |

---

## Screenshots

> Add screenshots to the `assets/images/` folder and reference them here.

```
Coming soon...
```

---

## Project Structure

```
pwms/
├── index.php                  # Entry point — redirects to login
├── login.php                  # Authentication page
├── logout.php                 # Session destruction
├── admin/                     # Admin panel pages
│   ├── dashboard.php          # Admin dashboard with KPIs & charts
│   ├── workers.php            # Worker list with search & filters
│   ├── worker_add.php         # Add new worker
│   ├── worker_edit.php        # Edit existing worker
│   ├── users.php              # User account management
│   ├── user_add.php           # Add new user account
│   ├── tasks.php              # Task list with filters
│   ├── task_add.php           # Create & assign tasks
│   ├── task_view.php          # Task detail view
│   ├── areas.php              # Constituency/ward/booth management
│   ├── checkins.php           # Worker check-in logs
│   ├── map.php                # Live worker location map
│   ├── leaderboard.php        # Gamification rankings
│   ├── sentiment.php          # AI sentiment analysis dashboard
│   ├── smart_assign.php       # AI-powered task assignment
│   ├── reports.php            # Area-based reports
│   ├── performance_report.php # Date-range performance reports
│   ├── report_template.php    # Report HTML template
│   ├── report_view.php        # Saved report viewer
│   └── export.php             # Data export (CSV/Excel)
├── worker/                    # Worker portal pages
│   ├── dashboard.php          # Worker personal dashboard
│   ├── my_tasks.php           # Assigned tasks management
│   ├── checkin.php            # GPS check-in/check-out
│   ├── feedback.php           # Voter feedback collection
│   ├── profile.php            # Profile & badges
│   └── route.php              # Route optimization planner
├── api/                       # AJAX API endpoints
│   ├── notifications.php      # Notification fetch & mark-read
│   └── upload_proof.php       # Task proof image upload
├── config/                    # Configuration & helpers
│   ├── db.php                 # Database connection & app settings
│   ├── auth.php               # Authentication, CSRF, security headers
│   ├── gamification.php       # Points, levels, badges system
│   ├── analytics.php          # Sentiment analysis & smart assignment
│   └── pagination.php         # Pagination helper
├── includes/                  # Shared layout components
│   ├── header.php             # HTML head & meta tags
│   ├── sidebar.php            # Navigation sidebar
│   └── footer.php             # Footer & closing tags
├── assets/
│   ├── css/style.css          # Main stylesheet (glassmorphism + dark mode)
│   ├── js/main.js             # Client-side logic (sidebar, notifications, etc.)
│   └── images/                # Static images & logos
├── uploads/
│   └── proofs/                # Uploaded task proof images
└── database/
    └── setup.sql              # Full database schema + sample data
```

---

## Installation

### Prerequisites

- **PHP** >= 7.4
- **MySQL** >= 5.7
- **Apache** with `mod_rewrite` enabled
- **XAMPP** / **WAMP** / **LAMP** or any PHP-capable web server

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/Rsmiyani/pwms.git
   ```

2. **Move to your web server directory**
   ```bash
   # For XAMPP on Windows:
   mv pwms C:\xampp\htdocs\Party-worker

   # For LAMP on Linux:
   mv pwms /var/www/html/Party-worker
   ```

3. **Create the database**
   ```bash
   mysql -u root -p -e "CREATE DATABASE pwms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

4. **Import the schema and sample data**
   ```bash
   mysql -u root -p pwms < database/setup.sql
   ```

5. **Configure the application** — edit `config/db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'pwms');

   // Set BASE_URL based on your setup:
   // Root deployment:        define('BASE_URL', '');
   // Subdirectory (XAMPP):   define('BASE_URL', '/Party-worker');
   ```

6. **Set upload directory permissions** (Linux/Mac):
   ```bash
   chmod 755 uploads/proofs/
   ```

7. **Open in browser**
   ```
   http://localhost/Party-worker/
   ```

---

## Database Setup

The `database/setup.sql` file creates all required tables:

| Table | Purpose |
|-------|---------|
| `users` | Admin & worker login accounts |
| `workers` | Worker profiles with geographic assignments |
| `tasks` | Campaign task definitions |
| `task_assignments` | Worker-to-task mappings with status |
| `worker_checkins` | GPS check-in/check-out records |
| `task_proofs` | Uploaded proof images |
| `notifications` | Real-time notification messages |
| `voter_feedback` | Voter feedback with sentiment scores |
| `worker_points` | Gamification point transactions |
| `worker_badges` | Earned achievement badges |
| `performance_reports` | Saved report snapshots |
| `login_attempts` | Brute-force protection logs |
| `areas` | Booth/constituency geographic data |

---

## Configuration

All configuration is in the `config/` directory:

| File | Purpose |
|------|---------|
| `db.php` | Database credentials, `BASE_URL`, password policy |
| `auth.php` | Session security, CSRF tokens, security headers, helper functions |
| `gamification.php` | Point values, level thresholds, badge definitions |
| `analytics.php` | Sentiment analysis engine, smart assignment algorithm |
| `pagination.php` | Pagination rendering helper |

---

## Default Credentials

| Role | Phone | Password |
|------|-------|----------|
| **Admin** | `9999999999` | `admin123` |

> **Note:** Change the default admin password immediately after first login.

Sample worker accounts are also created by `setup.sql` — check the SQL file for details.

---

## Usage

### Admin Workflow
1. Log in with admin credentials
2. **Manage Areas** — Define constituencies, wards, and booths with GPS coordinates
3. **Add Workers** — Register field workers and assign them to areas
4. **Create Tasks** — Define campaign tasks with priority and deadline
5. **Smart Assign** — Use AI recommendations to assign tasks to best-fit workers
6. **Monitor** — Track progress via dashboard, live map, and check-in logs
7. **Analyze** — Review sentiment reports, leaderboard, and performance analytics
8. **Export** — Generate and download reports

### Worker Workflow
1. Log in with worker credentials
2. **Check In** — GPS check-in at assigned booth location
3. **View Tasks** — See assigned tasks, update status as work progresses
4. **Upload Proof** — Attach photo evidence of completed tasks
5. **Collect Feedback** — Record voter feedback (auto-analyzed for sentiment)
6. **Plan Route** — Optimize travel path between assigned locations
7. **Track Progress** — View points, badges, and leaderboard rank

---

## API Endpoints

### Notifications — `api/notifications.php`

| Method | Action | Description |
|--------|--------|-------------|
| `GET` | Fetch | Returns unread count + last 20 notifications (JSON) |
| `POST` | `mark_read` | Mark a single notification as read |
| `POST` | `mark_all_read` | Mark all notifications as read |

- Rate limited: 60 requests/minute per session
- CSRF token required on POST requests

### Proof Upload — `api/upload_proof.php`

| Method | Action | Description |
|--------|--------|-------------|
| `POST` | Upload | Upload a proof image (JPG/PNG/GIF/WEBP, max 5 MB) |

- Validates file type, size, and worker ownership
- Awards 10 points on successful upload

---

## Security

| Measure | Implementation |
|---------|---------------|
| **Authentication** | Phone + bcrypt password hashing |
| **Session Security** | HTTPOnly, SameSite=Strict, Secure flag |
| **CSRF Protection** | Token-based validation on all POST actions |
| **SQL Injection** | Prepared statements with parameterized queries |
| **Brute Force** | Per-phone + per-IP attempt tracking, 15-min lockout |
| **XSS Prevention** | Output escaping + Content Security Policy headers |
| **File Upload** | MIME validation, 5 MB limit, safe filename generation |
| **Open Redirect** | Redirect URL validation on upload endpoints |
| **Rate Limiting** | 60 req/min on notification API |
| **Security Headers** | X-Frame-Options, X-Content-Type-Options, Referrer-Policy |

---

## Gamification System

### Points

| Action | Points |
|--------|--------|
| Complete Low-priority task | 10 |
| Complete Medium-priority task | 25 |
| Complete High-priority task | 50 |
| Check-in at booth | 5 |
| Upload task proof | 10 |

### Levels

| Level | Points Required |
|-------|----------------|
| Bronze | 0 – 99 |
| Silver | 100 – 299 |
| Gold | 300 – 599 |
| Platinum | 600+ |

### Badges (13 Achievements)

| Badge | Requirement |
|-------|-------------|
| First Task | Complete 1 task |
| Task Master | Complete 10 tasks |
| Task Legend | Complete 50 tasks |
| Centurion | Complete 100 tasks |
| High Achiever | Complete 10 high-priority tasks |
| Regular | 10 check-ins |
| Field Warrior | 50 check-ins |
| 100 Check-ins | 100 check-ins |
| Early Bird | Check-in before 7:00 AM |
| Proof Pro | Upload 20 proofs |
| Silver Rank | Reach Silver level |
| Gold Rank | Reach Gold level |
| Platinum Rank | Reach Platinum level |

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit your changes: `git commit -m "Add your feature"`
4. Push to the branch: `git push origin feature/your-feature`
5. Open a Pull Request

---

## License

This project is open source and available under the [MIT License](LICENSE).

---

<p align="center">
  Made with ❤️ for efficient campaign management
</p>
