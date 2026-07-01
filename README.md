# DIUS Management Portal

A comprehensive Laravel + Inertia React project management system designed for DIUS (Department of Industrial and Urban Studies) workflow tracking, task management, and institutional repository preservation.

---

## 🏗️ Architecture

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 11 (PHP 8.3+) |
| **Frontend** | React 18 + Inertia.js + TypeScript |
| **Styling** | Tailwind CSS 3 |
| **Database** | MySQL |
| **Auth** | Laravel Breeze + Spatie Laravel Permission |
| **Charts** | Recharts |
| **Icons** | Lucide React |

---

## 🎯 Main Features

### 1. User Management & Authentication
- **Role-based access control** with 4 roles: Admin, PM/Manager, Coordinator, Subordinate
- **34 granular permissions** governing every feature access point
- **Profile photo upload** with validation (JPG/PNG/WebP, max 2MB)
- **Account deactivation** with session termination
- **Admin-only user provisioning** (public registration disabled)

### 2. Project Management
- **Full CRUD** for projects with department assignment
- **Coordinator assignment** with assignment history tracking
- **Project status workflow**: Planned → Active → Submitted → Completed → Archived
- **Finalization to Repository** for institutional preservation
- **Search & filter** by status, title, description, and coordinator

### 3. Task & Work Item Management
- **Hierarchical structure**: Projects → Tasks → Subtasks (Work Items)
- **Subordinate assignment** with role validation
- **Progress tracking** with status updates and notes
- **File attachments** at every level (project, task, subtask)
- **Real-time assignment visibility** scoped by user role

### 4. Workflow Communication
- **Encrypted message threads** at project, task, and subtask levels
- **Message types**: Feedback, Follow-up, Progress Note, Clarification
- **Role-scoped visibility** — subordinates only see their assigned work messages
- **Workflow notifications** for assignments, messages, and progress updates

### 5. AI Requirement-Deliverable Comparison
- **Document analysis** supporting PDF, DOCX, TXT, CSV, XLSX
- **AI-powered matching** of requirements against deliverables
- **Comparison history** with rerun capability
- **Configurable per project/task/subtask**

### 6. Reports & Export
- **7 report types**: Project Progress, Task Status, Coordinator Performance, Subordinate Completion, Repository Preservation, Audit Activity, CSV Export
- **Role-scoped data** — Admin sees all, PM sees managed projects
- **CSV export** with authorization checks
- **No hardcoded data** — all reports query live database

### 7. Repository & Audit Trail
- **Institutional repository** for finalized project preservation
- **Audit logging** of all user actions with IP tracking
- **Admin-only audit access** with full metadata
- **Repository scoping** for coordinators by assigned projects

### 8. Dashboard & Analytics
- **Role-specific dashboards** with KPI cards
- **KPI metrics**: In Progress, Completed, Due, Overdue
- **Project Statuses** list with real-time data
- **Task Status Overview** donut chart
- **Completion Analytics** for last 3 months
- **Cached dashboard data** with 60-second TTL for performance

---

##  Security

### Authentication & Authorization
- **CSRF protection** on all state-changing routes via Inertia/Laravel
- **Policy-based authorization** for all resources (Project, Task, User, WorkflowFile, etc.)
- **Form Request authorization** as defense-in-depth layer
- **Deactivated user middleware** blocks access with automatic logout

### Data Protection
- **Encrypted message bodies** stored in database
- **Private file storage** with controller-mediated download
- **No hardcoded API keys** — all secrets via `.env`
- **Password reset tokens** never exposed in session flash

### Input Validation & Sanitization
- **File upload validation**: type (JPG/PNG/WebP), size (2MB max), no SVG/scripts
- **LIKE wildcard escaping** in all search queries
- **Request body size limits** on AI comparison endpoints
- **Status enum validation** across all controllers

### Rate Limiting
| Endpoint | Limit |
|----------|-------|
| AI Comparison Run | 3 requests/minute |
| Forgot Password | 5 requests/minute |
| Confirm Password | 6 requests/minute |
| Mark All Notifications Read | 10 requests/minute |

### XSS Prevention
- **No `dangerouslySetInnerHTML`** — all paginator labels rendered as plain text
- **React auto-escaping** for all user-generated content
- **AI output** rendered as plain text, not HTML

---

##  Testing (TDD)

### Test Coverage
| Category | Tests | Assertions |
|----------|-------|------------|
| **Authentication** | 10 | 35 |
| **Dashboard Access** | 11 | 48 |
| **Project Assignment Workflow** | 35 | 152 |
| **Project Finalization** | 16 | 92 |
| **Repository Module** | 23 | 93 |
| **Role Boundary Visibility** | 9 | 187 |
| **Role Permission** | 11 | 52 |
| **Task/Subtask Workflow** | 21 | 98 |
| **User Management** | 11 | 57 |
| **Workflow Reports** | 14 | 86 |
| **Workflow Files** | 24 | 118 |
| **Workflow Messages** | 19 | 92 |
| **Workflow Notifications** | 21 | 96 |
| **Security & Validation** | 23 | 105 |
| **UI Visibility & Layout** | 9 | 42 |
| **Other** | 35 | 170 |
| **TOTAL** | **292** | **2,383** |

### Test Execution
```bash
# Run full test suite
php artisan test

# Run specific test groups
php artisan test --filter=UserManagementTest
php artisan test --filter=WorkflowReportsTest
php artisan test --filter=RoleBoundaryVisibilityTest
```

### Key Test Scenarios
- ✅ Deactivated user cannot login or access with existing session
- ✅ Admin cannot change own role or deactivate own account
- ✅ Coordinator cannot update unrelated projects
- ✅ Subordinate cannot access Repository, Reports, or Audit logs
- ✅ Profile photo upload accepts JPG/PNG/WebP, rejects SVG/scripts
- ✅ Profile photo over 2MB is rejected
- ✅ Cross-project file download is blocked
- ✅ Notification ownership enforced — users see only their own
- ✅ CSV exports require authorization and respect role scoping
- ✅ All dashboard KPI values come from live database queries

---

## 📂 Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # Admin-only controllers (User, Audit)
│   │   ├── Auth/           # Authentication controllers
│   │   ├── Concerns/       # Reusable controller traits
│   │   └── *.php           # Feature controllers
│   ├── Middleware/         # Custom middleware (EnsureUserIsActive, etc.)
│   ├── Requests/           # Form request validation classes
│   └── ...
├── Models/                 # Eloquent models with relationships
── Policies/               # Authorization policies per model
├── Services/               # Business logic services
│   ├── AuditLogService.php
│   ├── ReportService.php
│   ├── RequirementDeliverableService.php
│   └── WorkflowNotificationService.php
├── Helpers/
│   └── CacheHelper.php     # Consistent cache key management
└── ...

resources/js/
├── Components/
│   ├── Dius/ui.tsx         # Shared UI components (Avatar, ModuleCard, etc.)
│   └── WorkManagement/     # Feature-specific components
├── Layouts/
│   └── AuthenticatedLayout.tsx
├── Pages/
│   ├── Admin/              # Admin pages (Users, Audit Logs)
│   ├── Dashboards/         # Admin & PM dashboards
│   ├── Profile/            # Profile management
│   ├── Projects/           # Project CRUD pages
│   ├── Reports/            # Report pages
│   └── ...
└── types/index.d.ts        # TypeScript type definitions

database/
── migrations/             # Database schema migrations
── seeders/                # Database seeders
└── factories/              # Model factories for testing

tests/Feature/              # Feature test suite (292 tests)
```

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- Node.js 18+
- MySQL 8.0+

### Installation
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Configure database in .env, then migrate & seed
php artisan migrate --seed

# Build frontend assets
npm run build
```

### Running the Application
```bash
# Start Laravel development server
php artisan serve

# Or use concurrent dev mode
npm run dev
```

### Running Tests
```bash
# Full test suite
php artisan test

# Specific test group
php artisan test --filter=UserManagementTest

# With coverage (requires Xdebug)
php artisan test --coverage
```

---

## 📊 Database Schema

### Core Tables
| Table | Description |
|-------|-------------|
| `users` | User accounts with roles, profile photos, department linkage |
| `departments` | Organizational departments |
| `projects` | Projects with status, deadline, department |
| `project_assignments` | Coordinator assignments with history |
| `tasks` | Tasks within projects |
| `subtasks` | Work items within tasks |
| `subtask_assignments` | Subordinate assignments with history |
| `repository_entries` | Finalized project records |
| `repository_updates` | Timeline updates for repository entries |
| `workflow_messages` | Encrypted communication threads |
| `workflow_files` | File attachments with authorization |
| `workflow_notifications` | User notifications |
| `workflow_audit_logs` | System audit trail |
| `workflow_comparison_configs` | AI comparison configurations |
| `workflow_comparison_results` | AI comparison results |

---

## 🔧 Configuration

### Redis (Recommended for Production)
```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### AI Comparison
```env
AI_COMPARISON_ENABLED=false
AI_PROVIDER=openai_compatible
AI_API_KEY=your-api-key
AI_BASE_URL=https://api.openai.com/v1
AI_MODEL=gpt-4o-mini
```

---

## 📝 License

Meherab Hossain Shafin. All rights reserved.
