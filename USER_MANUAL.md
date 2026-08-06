# SSD-ACMIS User Manual & System Guide

**SSD Academic Management Information System (SSD-ACMIS)**  
Version 1.0 · SSD IT Solutions

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Signing In & Out](#3-signing-in--out)
4. [User Roles & Portals](#4-user-roles--portals)
5. [Navigation Overview](#5-navigation-overview)
6. [Dashboard](#6-dashboard)
7. [School Administration](#7-school-administration)
8. [Student Management](#8-student-management)
9. [Staff & Teaching Setup](#9-staff--teaching-setup)
10. [Classes & Subjects](#10-classes--subjects)
11. [Attendance](#11-attendance)
12. [Marks, Results & Reports](#12-marks-results--reports)
13. [Fees Management (Bursar Portal)](#13-fees-management-bursar-portal)
14. [HOD Portal](#14-hod-portal)
15. [Announcements](#15-announcements)
16. [Settings & School Branding](#16-settings--school-branding)
17. [Password & Account Management](#17-password--account-management)
18. [Grading System Reference](#18-grading-system-reference)
19. [Tips & Troubleshooting](#19-tips--troubleshooting)
20. [Quick Reference](#20-quick-reference)

---

## 1. Introduction

### What is SSD-ACMIS?

SSD-ACMIS is a school management system built for **secondary schools**. It helps administrators, teachers, Heads of Department (HODs), bursars, and students manage day-to-day academic and financial operations from one secure web application.

### What the system does

| Area | Capabilities |
|------|--------------|
| **Student records** | Admission, profiles, photos, class placement, printable rosters and admission letters |
| **Staff management** | Teacher accounts, subject expertise, teaching assignments |
| **Academic management** | Marks entry, term results, class rankings, printable report cards |
| **Attendance** | Daily class attendance (present, absent, late) |
| **Fees** | Fee structures, billing, payments, receipts, balance reports, exam permits |
| **Communication** | School-wide announcements |

### Academic model

SSD-ACMIS is designed around the **South Sudan secondary school structure**:

- **Forms:** Form 1 through Form 4
- **Terms:** 3 terms per academic year
- **Exams:** Mid-term (maximum 30 marks) + End-of-term (maximum 70 marks) = **100 marks per subject**
- **Results:** published separately for each assessment — **Mid-term** (each subject out of 30) and **End of term** (mid + end, out of 100)
- **Streams:** Form 3 and Form 4 students may be in **Science** or **Arts** streams, which affects which subjects appear on their report cards
- **Grading:** Letter grades A through F based on percentage scores

### Who should use this manual?

| Reader | Start with |
|--------|------------|
| New school administrator | Sections 2, 3, 7, 8, 9, 10, 16 |
| Teacher / staff member | Sections 3, 6, 8, 11, 12 |
| Head of Department (HOD) | Sections 3, 14, 12 |
| Bursar | Sections 3, 13 |
| Student | Sections 3, 12 (own reports), 13 (My Fees) |

---

## 2. Getting Started

### System requirements

- **Web browser:** Chrome, Firefox, Safari, or Edge (latest versions recommended)
- **Server (for IT staff):** PHP 8.1+, MySQL 5.7+ / MariaDB 10.4+, Apache with URL rewriting enabled

### First-time installation (IT administrator)

1. Place the SSDACMIS folder in your web server directory (e.g. `htdocs/SSDACMIS` on XAMPP).
2. Start **Apache** and **MySQL**.
3. Copy `.env.example` to `.env` and configure database credentials.
4. Run the installer:
   - **Browser:** open `http://your-server/SSDACMIS/public/install.php`
   - **Or command line:**
     ```bash
     mysql -u root ssdacmis < database/schema.sql
     php database/migrate.php
     ```
5. **Delete `public/install.php`** after a successful installation.
6. Open the login page and sign in with the default school administrator account (see [Quick Reference](#20-quick-reference)).

### Recommended first-time school setup

After installation, complete these steps in order:

1. **School branding** — Ensure your school name, motto, logo, contact details, and head teacher information are configured.
2. **Change your password** — Do not keep the default password.
3. **Subjects** — Review the curriculum and tick which subjects your school offers.
4. **Classes** — Confirm or create classes (e.g. Form 1A, 2A, 3A, 4A) and assign class teachers.
5. **Staff** — Create teacher accounts.
6. **Teaching** — Assign each teacher to their class and subject combinations.
7. **HODs & Bursars** — Create portal accounts for department heads and the bursar.
8. **Students** — Admit students into their classes.
9. **Test the workflow** — Enter marks → view results → print a report card → set fees → record a payment.

---

## 3. Signing In & Out

### How to sign in

1. Open your school's SSD-ACMIS URL (e.g. `http://localhost/SSDACMIS/public/login`).
2. Enter your **email address** and **password**.
3. Click **Sign in**.

The system automatically sends you to the correct portal based on your role:

| Your role | Where you land |
|-----------|----------------|
| School Admin, Staff, Student | Main dashboard (`/dashboard`) |
| Head of Department (HOD) | HOD portal (`/hod`) |
| Bursar | Bursar portal (`/bursar`) |

> **Note:** All users sign in from the same login page. There is no separate HOD or Bursar login screen — those URLs redirect to the main login page.

### Signing out

Click your **user menu** (top-right of the screen) and select **Sign out**.

Each portal has its own session. Signing out of the HOD or Bursar portal does not affect an administrator session open in another browser tab.

### Account lockout

After **8 failed login attempts**, your account is temporarily locked for **5 minutes**. Wait and try again, or contact your administrator.

### Forgot your password?

1. On the login page, click **Forgot?** (or go to `/forgot-password`).
2. Enter your registered email address.
3. Check your email for a reset link (valid for 1 hour).
4. Follow the link and set a new password (minimum 8 characters).

> If email is not configured on the server, contact your school administrator to reset your password manually.

---

## 4. User Roles & Portals

SSD-ACMIS uses **role-based access control**. Each user sees only the menus and data they are permitted to access.

### Role summary

| Role | Portal | What they can do |
|------|--------|------------------|
| **School Admin** | Main | Full management of the school: students, staff, classes, marks, reports, HODs, bursars, and school branding |
| **Staff (Teacher)** | Main | Manage students, take attendance, enter marks for assigned classes, view reports for assigned/homeroom classes |
| **HOD** | HOD Portal | Enter marks for all subjects across Forms 1–4, department reports, results, announcements |
| **Bursar** | Bursar Portal | Fees setup, student billing, payments, receipts, financial reports, exam permits |
| **Student** | Main | View own report cards, fee balance, and announcements |

### Permission highlights

- **Mark entry** is restricted to staff with teaching assignments, HODs, and school administrators.
- **Student deletion** is restricted to school administrators.
- **Bursars** cannot access academic modules. **HODs** cannot access the main admin areas.

### Staff who are also HODs

If a teacher is assigned as a **department head** in the Teaching module, they are automatically redirected to the **HOD portal** when they sign in, even though their account role is `staff`.

---

## 5. Navigation Overview

### Main portal sidebar

Available menu items depend on your role:

| Menu | School Admin | Staff | Student |
|------|:------------:|:-----:|:-------:|
| Overview (Dashboard) | ✓ | ✓ | ✓ |
| Students | ✓ | ✓ | — |
| Staff | ✓ | — | — |
| HODs | ✓ | — | — |
| Bursars | ✓ | — | — |
| Classes | ✓ | ✓ | — |
| Subjects | ✓ | ✓ | — |
| Teaching | ✓ | — | — |
| Marks | ✓ | ✓ | — |
| Results | ✓ | ✓ | — |
| Reports | ✓ | ✓ | ✓ |
| Attendance | ✓ | ✓ | — |
| My Fees | — | — | ✓ |
| Announcements | ✓ | ✓ | ✓ |

### HOD portal sidebar

- Overview
- HOD Dashboard
- Students (read-only, by class)
- Department Marks
- Department Reports
- Results
- Announcements

### Bursar portal sidebar

- Dashboard
- Fees Setup
- Students (fee balances)
- Payments
- Paid Report
- Balances
- Exam Permits

---

## 6. Dashboard

The dashboard is your home screen after signing in. It shows a snapshot of school activity.

### What you will see

**School Administrator:**
- Student, staff, class, and subject counts
- Enrollment charts and demographics
- Recent student admissions
- HOD and bursar account status
- Unassigned students alert
- Today's attendance summary
- Latest announcements

**Staff:**
- Overview relevant to your assigned classes
- Quick links to marks and attendance

**Student:**
- Personal dashboard with links to your report cards and fees

**HOD / Bursar:**
- Automatically redirected to their dedicated portal dashboard

---

## 7. School Administration

This section covers the responsibilities of the **School Administrator** — the person who oversees daily operations and configures the school in SSD-ACMIS.

### Your responsibilities as School Administrator

As the school administrator, you are responsible for:

- Admitting and managing student records
- Creating and managing staff, HOD, and bursar accounts
- Setting up classes, subjects, and teaching assignments
- Overseeing attendance, marks, results, and reports
- Ensuring fees are configured and collected by the bursar
- Posting school-wide announcements
- Maintaining school branding on official documents

### Recommended term-start checklist

1. Review **Subjects** — confirm which subjects are offered this year.
2. Review **Classes** — confirm class teachers and admission prefixes.
3. Verify **Teaching assignments** — every class/subject has a teacher assigned.
4. Confirm **HOD** and **Bursar** accounts are active.
5. Admit new students and assign them to classes.
6. Ask the bursar to set the active fee period and fee structure for the new term.
7. Communicate login credentials to all staff.

### Managing portal accounts

| Account type | Where to create | Portal on login |
|--------------|-----------------|-----------------|
| Staff (teachers) | Staff → Add Staff | Main dashboard |
| HOD | HODs → Add HOD | HOD portal |
| Bursar | Bursars → Add Bursar | Bursar portal |

Each new account receives a random password by email. Ask all users to change their password on first login.

---

## 8. Student Management

**Who can access:** School Administrators and Staff  
**Who can delete students:** School Administrators

### Viewing students

1. Go to **Students** in the sidebar.
2. Use the **search box** to find students by name or admission number — results update instantly without reloading the page.
3. Filter by class if needed.

### Admitting a new student

1. Click **Add Student**.
2. Fill in the required details:

| Field | Description |
|-------|-------------|
| Full name | Student's legal name |
| Gender | Male or Female |
| Date of birth | Used on reports |
| Class | Determines admission number prefix (e.g. F1A001) |
| Section | Day scholar or Boarding |
| Stream | Science or Arts (Form 3 and 4 only) |
| Guardian name & phone | Emergency contact |
| Address | Residential address |
| Photo | Optional passport photo (upload or camera capture) |

3. Click **Save**.

The **admission number** is generated automatically from the class prefix (configured in Classes).

### Editing a student

1. Find the student in the list.
2. Click **Edit**.
3. Update the details and save.

### Student photo

- Upload a photo from file, or use the **camera capture** option on supported devices.
- To remove a photo, open the student edit form and click **Delete Photo**.

### Printable documents

**School Administrators** can generate:

| Document | How to access |
|----------|---------------|
| **Class roster** | Students → **Print Roster** — printable list of all students in a class |
| **Admission letter** | Students → select student → **Admission Letter** — official welcome letter with school branding |
| **Bulk admission letters** | Students → **Admission Letters** — generate letters for multiple students |

### Student portal accounts

The system supports student logins (to view report cards and fees), but **student accounts are not created automatically** during admission. Your administrator must provision student login accounts separately if student self-service access is required.

---

## 9. Staff & Teaching Setup

### Creating staff accounts

**Who can do this:** School Administrator

1. Go to **Staff** → **Add Staff**.
2. Enter: full name, email, phone, role (`staff` or `school_admin`), and subjects taught.
3. Save.

A **random password** is generated and sent to the staff member's email. Ask them to change it on first login.

### Assigning teaching responsibilities

1. Go to **Teaching**.
2. Click **Add Assignment**.
3. Select: **Staff member** + **Class** + **Subject**.
4. Save.

> Teachers can only enter marks for subjects and classes they are assigned to.

### Assigning department heads (HODs)

1. On the **Teaching** page, scroll to the **Department Heads** section.
2. Assign a staff member to head a subject category: **Core**, **Science**, **Arts**, or **Optional**.
3. That staff member will be redirected to the HOD portal on login.

### Dedicated HOD accounts

For HODs who are not regular teaching staff:

1. Go to **HODs** → **Add HOD**.
2. Enter name, email, and department label.
3. Save — credentials are emailed automatically.

### Bursar accounts

1. Go to **Bursars** → **Add Bursar**.
2. Enter name and email.
3. Save — the bursar signs in at the main login page and is sent to the Bursar portal.

---

## 10. Classes & Subjects

### Classes

**Who can view:** School Administrators and Staff  
**Who can manage:** School Administrators

1. Go to **Classes**.
2. Each class shows: form level, class teacher, student count, and admission prefix.

**School Administrators can:**
- Create new classes
- Assign a **class teacher** (homeroom teacher)
- Set the **admission prefix** (e.g. `F1A` generates admission numbers like F1A001, F1A002…)
- Delete empty classes

### Subjects (Curriculum)

1. Go to **Subjects**.
2. Review the pre-loaded curriculum (core, science, arts, and optional subjects).
3. Tick **Offered** for subjects your school teaches this year.
4. Unchecked subjects are hidden from mark entry and new report cards, but **historical grades are preserved**.

**School Administrators can** add custom subjects or remove unused ones.

---

## 11. Attendance

**Who can access:** School Administrators and Staff

### Recording daily attendance

1. Go to **Attendance**.
2. Select the **class** and **date**.
3. For each student, mark: **Present**, **Absent**, or **Late**.
4. Click **Save**.

Attendance records are stored per class per day and appear on the school administrator's dashboard summary.

---

## 12. Marks, Results & Reports

This is the core academic workflow of SSD-ACMIS.

### Understanding the marking structure

| Exam type | Maximum marks | Weight |
|-----------|----------------|--------|
| Mid-term | 30 | 30% of subject total |
| End-of-term | 70 | 70% of subject total |
| **Subject total** | **100** | Mid + End combined |

### Two separate result sets per term

Results and report cards are published at **two assessment stages**, and every Results and
Reports page has an **Assessment** selector for choosing between them:

| Assessment | What it counts | Subject is out of |
|------------|----------------|-------------------|
| **Mid-term** | Mid-term marks only | **30** |
| **End of term** | Mid-term + end-of-term combined | **100** |

Each stage has its own averages, letter grades and class positions. The mid-term result set is
complete as soon as mid-term marks are saved, and it **does not change** when end-of-term marks
are entered later — a mid-term report card printed in week 6 still reads the same in week 12.
End-of-term is the default selection everywhere.

### Step 1: Enter marks

**Who can enter marks:** Staff (assigned subjects), HODs (all subjects), School Administrators

1. Go to **Marks**.
2. Select the **academic year**, **term** (1, 2, or 3), and **exam type** (Mid-term or End-of-term).
3. Choose your entry method:

| Method | Best for |
|--------|----------|
| **Per-subject entry** | Individual teachers entering marks for their assigned class/subject |
| **Department matrix** | HODs entering all subjects in a department for one class at once |

4. Enter scores for each student.
5. Click **Save**.

> **Form 3 & 4:** Students in Science stream see science subjects; Arts stream students see arts subjects. The system filters automatically.

### Step 2: View results

1. Go to **Results**.
2. Select academic year, term, and **assessment** (Mid-term or End of term).
3. Click a class to see:
   - Subject totals and letter grades per student — out of 30 at mid-term, out of 100 at end of term
   - Class average
   - Student positions (rankings use competition ranking: 1, 2, 2, 4…), ranked within the chosen assessment

### Step 3: Generate reports

1. Go to **Reports**.
2. Choose report type:

| Report | Description |
|--------|-------------|
| **Student report card** | Individual student's full term report with all subjects, grades, and school branding |
| **Class report** | Matrix showing all students and subjects for a class |
| **Class booklet** | Landscape A4 printable booklet for an entire class — ideal for parent meetings |

3. Select the student or class, academic year, term, and **assessment**.
4. Click to view or print.

A **mid-term** report card shows one Mid column with each subject out of 30, and its reference
code carries `/MID/`. An **end-of-term** card shows Mid, End and Total with each subject out of
100, and carries `/END/`. Both include: school logo, motto, head teacher name and signature,
subject marks, grades, class position, and remarks.

### Students viewing their own reports

Students with login accounts can go to **Reports** and view **only their own** report cards.

---

## 13. Fees Management (Bursar Portal)

The fees module is a **separate portal** for bursars. Sign in with your bursar account to access it.

### Setting the active period

At the top of every bursar page, set the **Academic Year** and **Term**. All fee operations use this period until you change it.

### Fees setup

1. Go to **Fees Setup**.
2. Define fee amounts for each combination of:
   - **Form level** (1–4)
   - **Section** (Day or Boarding)
   - **Academic year**
3. Save the structure.

Student bills are **automatically synced** from this structure when you view the Students page.

### Managing student bills

1. Go to **Students** in the bursar portal.
2. Browse or search for a student.
3. Click a student to see their bill breakdown, payments, and outstanding balance.

### Recording a payment

1. Go to **Payments**.
2. Select the student.
3. Enter: amount paid, payment date, and payment method.
4. Save — a **receipt number** is generated automatically.
5. Click **Print Receipt** to produce a printable receipt with school branding.

### Financial reports

| Report | Purpose |
|--------|---------|
| **Paid Report** | List of students who have fully paid for the term |
| **Balances** | Students with outstanding fees |
| **Export CSV** | Download financial data for spreadsheet analysis |
| **Print** | Printable versions of paid and balance reports |

### Examination permits

Exam permits are issued **only to students whose fees are fully paid** for the active term.

1. Go to **Exam Permits**.
2. Review the list of eligible students.
3. Click **Print** to generate printable examination permits with school branding and head teacher signature.

---

## 14. HOD Portal

The HOD portal gives department heads a focused workspace for academic oversight.

### HOD Dashboard

Shows department-level statistics and quick links to marks and reports.

### Students (read-only)

View the student list organised by class. HODs cannot edit student records from this portal.

### Department Marks

Enter marks for **all subjects** in your department across all Forms 1–4. This is the fastest way for HODs to complete mark entry for an entire class.

### Department Reports & Results

Same reporting and results tools as the main portal, scoped to the HOD portal navigation.

### Announcements

HODs can post announcements visible to all school users.

---

## 15. Announcements

**Who can read:** All signed-in users (all roles)  
**Who can post:** School Administrators, Staff, and HODs

### Posting an announcement

1. Go to **Announcements**.
2. Type your message in the text area.
3. Click **Post**.

Announcements appear on the dashboard and the announcements page for all users in your school.

---

## 16. Settings & School Branding

**Who can access:** School Administrator

### School identity

Your school's branding appears across the system — on the login page, sidebar, report cards, admission letters, receipts, and exam permits. Configure the following:

| Setting | Used on |
|---------|---------|
| School name | Sidebar, login page, all official documents |
| School motto | Login page, report card headers |
| School logo | Sidebar, report cards, admission letters, receipts, exam permits |
| Phone, email, address | Official letterheads |
| Head teacher name & title | Report cards, admission letters, exam permits |
| Head teacher signature | Scanned signature image on official documents |
| Theme accent colour | User interface colour (15 preset palettes available) |

Contact your IT support team if you need help uploading a logo or signature image. Accepted formats are JPG or PNG, under 2 MB.

### Grading scale

The default grading scale follows the South Sudan standard (see [Section 18](#18-grading-system-reference)). Grade boundaries can be customised if your school uses a different scale — contact IT support to adjust the grading configuration.

---

## 17. Password & Account Management

### Changing your password (while signed in)

1. Click your **user menu** (top-right).
2. Select **Change Password**.
3. Enter your current password and new password (minimum 8 characters).
4. Confirm and save.

### Resetting a forgotten password

See [Signing In & Out — Forgot your password?](#forgot-your-password)

### Administrator-created accounts

When an administrator creates a staff, HOD, bursar, or school admin account:

- A **random secure password** is generated automatically.
- The password is **emailed** to the new user.
- If email delivery fails, the password is shown on screen — copy it immediately and share it securely with the user.
- **Always ask new users to change their password** on first login.

---

## 18. Grading System Reference

### Default letter grades

| Grade | Percentage range | Remark |
|-------|-----------------|--------|
| **A** | 80 – 100% | Excellent |
| **B** | 70 – 79% | Very Good |
| **C** | 60 – 69% | Good |
| **D** | 50 – 59% | Fair |
| **E** | 40 – 49% | Pass |
| **F** | Below 40% | Fail |

### How subject totals are calculated

Each assessment is scored on its own scale:

```
Mid-term result:    Subject Total = Mid-term mark                     = max 30
                                    (max 30)

End-of-term result: Subject Total = Mid-term mark + End-of-term mark  = max 100
                                    (max 30)        (max 70)
```

Grades and averages always come from the percentage of whichever scale applies, so a
mid-term mark of 24/30 grades as 80% (A) on the mid-term report.

### Class positions

Student rankings use **competition ranking** (1, 2, 2, 4…). Two students with the same average share the same position; the next position is skipped. Positions are computed per assessment, so a student can place differently at mid-term and at end of term.

### Form 3 & 4 stream filtering

| Stream | Subjects shown |
|--------|---------------|
| Science | Core + Science subjects |
| Arts | Core + Arts subjects |
| Neither / Form 1 & 2 | Core + Optional subjects |

---

## 19. Tips & Troubleshooting

### Common issues

| Problem | Solution |
|---------|----------|
| Cannot sign in | Check email and password. Wait 5 minutes if locked out. Use Forgot Password or contact your admin. |
| Page shows "Access denied" | Your role does not have permission for that page. Contact your school administrator. |
| Marks not saving | Ensure you selected the correct year, term, and exam type. Check that you are assigned to that class/subject. |
| Report card shows no grades | Check the **Assessment** selector. A mid-term card needs mid-term marks; an end-of-term card counts mid + end. |
| End-of-term marks missing from a report | The report is set to **Mid-term**, which never counts end-of-term marks. Switch Assessment to **End of term**. |
| Student not in fee list | Ensure the bursar has set the correct academic year/term and that fee structure exists for that form level. |
| Email not received | SMTP may not be configured. Ask your IT administrator to check `.env` mail settings. |
| Logo not appearing on reports | Upload the logo through your school branding settings. Ensure the file is a JPG or PNG under 2 MB. |

### Best practices

1. **Change default passwords immediately** after installation.
2. **Set up teaching assignments** before the term starts so teachers can enter marks on day one.
3. **Tick offered subjects** at the start of each academic year.
4. **Set the bursar period** (year + term) at the start of each term before recording payments.
5. **Back up your database regularly** — ask your IT administrator to schedule automatic backups.
6. **Delete `install.php`** after installation to prevent unauthorised database resets.

### Getting help

- Contact your school administrator for account and access issues.
- Contact SSD IT Solutions for technical support: support@ssd-acmis.local
- IT administrators should refer to `DEPLOYMENT.md` and `README.md` for server configuration.

---

## 20. Quick Reference

### Default accounts (change passwords after first login)

| Role | Email | Default password |
|------|-------|-----------------|
| School Administrator | `admin@school.local` | `admin123` |
| Head of Department | `hod@school.local` | `hod123` |
| Bursar | `bursar@school.local` | `bursar123` |

### Key URLs

| Page | URL path |
|------|----------|
| Login | `/login` |
| Dashboard | `/dashboard` |
| HOD Portal | `/hod` |
| Bursar Portal | `/bursar` |
| Forgot Password | `/forgot-password` |
| Change Password | `/account/password` |

### Academic year format

Use the format `YYYY/YYYY` (e.g. `2025/2026`). The system offers the current year and two years on either side.

### Term structure

| Term | Typical period |
|------|---------------|
| Term 1 | First third of the academic year |
| Term 2 | Middle third |
| Term 3 | Final third |

### Document types available

| Document | Generated from |
|----------|---------------|
| Student report card | Reports → Student |
| Class report matrix | Reports → Class |
| Class booklet (A4 landscape) | Reports → Class → Booklet |
| Class roster | Students → Print Roster |
| Admission letter | Students → Admission Letter |
| Payment receipt | Bursar → Payments → Receipt |
| Examination permit | Bursar → Exam Permits |
| Fees paid / balances report | Bursar → Reports |

---

*SSD-ACMIS · SSD IT Solutions · Nelson O. Ochan*  
*For technical deployment instructions, see `DEPLOYMENT.md` and `README.md`.*
