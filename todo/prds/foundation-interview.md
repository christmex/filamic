# Architecture Interview — Student, School, Classroom & Finance Foundation

**Date**: 2026-04-08
**Participants**: Developer (solo) + Claude (AI architect)
**Purpose**: Design robust architecture for multi-branch school management system core

---

## Round 1: Branch & School Structure

**Q: How many branches do you expect to support?**
A: 2-5 branches.

**Q: Is the academic calendar shared globally or per-branch/school?**
A: Global calendar — all branches share the same school year and term dates.

**Q: Do classrooms persist across school years or are new ones created each year?**
A: Persist across years — same "6A" classroom entity reused every year, students rotate in/out via enrollments.

**Q: Can a single School span multiple education levels?**
A: One level per school — SD = elementary only, SMP = junior high only, SMA = senior high only.

---

## Round 2: Student Enrollment & Lifecycle

**Q: Can students join mid-year?**
A: Yes, mid-year enrollment is allowed. Fees charged from enrollment month only, not retroactive.

**Q: How should grade promotion work?**
A: Bulk promotion with exceptions — admin can hold back or skip individual students.

**Q: Can students transfer between branches?**
A: Both within-branch and cross-branch transfers happen in practice.

**Q: What happens to financial data when students transfer or graduate?**
A: Keep everything — all historical invoices and payments stay linked to the student forever.

---

## Round 3: Student Data & Fee Structure

**Q: Is the monthly fee amount the same for all students?**
A: Varies by school, grade, AND individual (scholarships, sibling discounts, etc.).

**Q: For cross-branch transfers, should student IDs stay the same?**
A: The student ULID is global identity (stays the same). Enrollment records are per-branch. Virtual account number changes per branch. NISN (admission_number) is the national student ID — stays forever.

**Q: How should mid-year student fees be handled?**
A: From enrollment month only — not retroactive.

**Q: Can a student be in multiple classrooms?**
A: Only 1 classroom per student. But they can have grouping — some students from different classrooms choose to learn subject A, others choose subject B. These are cross-class learning groups.

---

## Round 4: Fee Configuration & Types

**Q: What is the admission_number on your Student model?**
A: NISN — national student ID that stays with the student forever.

**Q: Where should fee amounts be configured?**
A: On the school/grade level.

**Q: How many fee types beyond monthly + book?**
A: Flexible/unlimited — admin should be able to define custom fee types as needed.

**Q: Is the LearningGroup model what you described for cross-class grouping?**
A: Similar but different — the concept is right but needs adjustment. They are cross-class groups, not within-classroom.

---

## Round 5: Learning Groups, Attendance & Reports

**Q: How should LearningGroup actually work?**
A: Cross-class groups — students from different classrooms grouped together for specific activities or subjects.

**Q: How is attendance recorded?**
A: Daily per student — one attendance record per student per day (present/absent/sick/permitted).

**Q: What does "student report" mean?**
A: Both — term-end report cards (rapor) plus ongoing progress tracking throughout the term.

**Q: Which module to build first after the foundation?**
A: Finance (fees) — get the fee structure, invoicing, and payments working first.

---

## Round 6: Finance Deep Dive

**Q: Should fee types be org-wide or per-branch?**
A: Organization-wide — fee types defined once, used everywhere.

**Q: How are payments received?**
A: Currently manual by admin. Plan to integrate payment gateway soon so payments auto-sync.

**Q: Can a single payment cover multiple invoices?**
A: Both scenarios — sometimes one-to-one, sometimes one parent pays everything at once.

**Q: How do discounts work?**
A: All of the above — could be percentage, fixed amount, or complete fee override depending on the case.

---

## Round 7: Critical Edge Cases

**Q: When a student transfers with outstanding invoices, who collects?**
A: Must settle first — student cannot transfer until all outstanding fees are paid.

**Q: How do fines/penalties work?**
A: Fixed price per day. Admin sets a cutoff day (e.g., the 25th of the month). If payment is after the 25th, count the days late and multiply by a daily rate (e.g., Rp 5,000). Both the cutoff day and the daily rate are configurable and can be changed.

**Q: Do you need parent/guardian data?**
A: Later phase — important but not needed in the foundation.

**Q: What happens to G12 (final grade) students during promotion?**
A: Graduate + alumni — they graduate and move to alumni tracking.

---

## Round 8: System-Wide & Future-Proofing

**Q: Can fee amounts change mid-year?**
A: Yes — admin can update fee amounts mid-year. New invoices use new amount, existing invoices stay as-is.

**Q: How should year-end transition work?**
A: Guided workflow — system provides step-by-step process. But old data must remain accessible/filterable. All transactions and data saving should use the current school year, but old school year data can be accessed when needed.

**Q: Do schools share subjects?**
A: Each school has their own subjects. Subjects have categories that can be more than 1 depth (nested/hierarchical).

**Q: How should the admission module work?**
A: Decide later — not sure yet, will figure out when we get to that module.
