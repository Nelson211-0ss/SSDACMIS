# SSD-ACMIS — Staff Orientation

*10 slides — read in order, or jump to your section.*

---

## 1. SSD-ACMIS

The school management system now holding every admission, mark, and payment in one place — a walkthrough for the whole team.

---

## 2. Why we moved

### One register, instead of a shelf of them

Admissions, marks, report cards and fees used to live in separate exercise books and spreadsheets. Now they live in one system, shared by the people who need each piece.

- **One source of truth** — a student's record, marks and fee balance all point back to the same entry, not three different files.
- **Everyone sees only their part** — a teacher opens marks entry, a bursar opens payments; no one has to work in a sheet built for someone else's job.
- **Paperwork prints itself** — admission letters, ID cards, report cards, receipts and exam permits all come straight out of the system, already formatted.

---

## 3. Roles & logins

### Six roles, three doors

Everyone signs in with the account they were given. There's no shared login — and no role can see more than its own door opens.

| Role | Scope | Login |
|---|---|---|
| Admin | Every school in the system | `/login` |
| School Admin | Their one school — staff, HOD & bursar accounts, classes, students | `/login` |
| Staff (Teacher) | Their own classes — attendance, marks | `/login` |
| Head of Department | Their department's marks & results, across classes | `/hod/login` |
| Bursar | Fee structures, payments, receipts, exam permits | `/bursar/login` |
| Student | Their own report card, nothing else | `/login` |

---

## 4. Dashboard

### What greets you after login

The dashboard is a summary, not a maze: how many students, classes and staff are on file, and a shortcut straight to the task you came to do.

- **Counts at a glance** — students, classes and staff for your school (or, for Admin, across every school).
- **Shortcuts** to the pages your role uses most, so nothing is more than one click from login.
- **Announcements** — the same notice board every role reads from. Post once, and staff, HOD and bursar all see it.

---

## 5. Students

### Admitting and recording students

**One at a time** — Students → Add. Pick the class and the admission number is generated for you from that class's prefix — no register to cross-check by hand.

**A whole class at once** — Students → Import. Download the template, fill in first and last name (required — gender, date of birth, section, guardian and address are optional), then upload. The system admits every valid row and lists any it skipped, and why.

**From the same record** — class rosters, ID cards and admission letters are all generated straight from the student record you already entered — nothing gets retyped.

---

## 6. Academic setup

### Classes, subjects, attendance

- **Classes** carry the admission prefix that Slide 5's numbering runs on, plus the class's own teacher.
- **Subjects** are assigned per class — only what's actually offered there shows up later at marks entry.
- **Attendance** is taken per class, per day, by that class's own staff.

---

## 7. Marks & results

### From marks entry to results

Staff enter marks for the subjects and classes they teach. A Head of Department can enter, or simply review, for the whole department at once.

- **Marks entry** is scoped to what you teach — you won't see, or accidentally edit, another teacher's subject.
- **Results roll up automatically** — class standings and a gender performance breakdown, with no manual tallying.

---

## 8. Report cards

### One sheet per student, every time

Pull a single learner's report card any time from Reports — or print the whole school in one pass.

**Single student** — open any student's record and print their report card on demand — for a parent meeting, a transfer, or a re-print.

**Whole school** *(newest addition)* — the booklet lays out one full A4 sheet per student, class after class — the same "Print all" button, whatever the class size or curriculum.

---

## 9. Fees & bursar

### Payments and permits, off the same ledger

- **Fee structure** is set once per term; every student's balance is calculated against it automatically.
- **Recording a payment** prints a receipt on the spot — balance and paid reports, including a CSV export, are one click away.
- **Exam permits** print straight off the same balances, so there's no separate reconciliation to run first.

---

## 10. Before you go

### A few habits that save everyone time

- **Start every CSV import from the downloaded template** — don't rebuild the columns by hand.
- **Check the class and school before you upload or print** — the system files it exactly where you point it.
- **Log out on any shared computer** — your login is your own record of what you did.

> One record, kept once, used everywhere — that's the whole point.

Questions? Ask your school administrator.
