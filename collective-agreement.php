<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Collective Agreement 2022–2025 — BVTU</title>
  <meta name="description" content="SD54 Bulkley Valley Teachers' Union Collective Agreement 2022–2025. Full text of all articles covering salary, leaves, working conditions, employment rights, and professional development.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .ca-article { margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid var(--border); }
    .ca-article:last-child { border-bottom: none; }
    .ca-article h3 { font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: .75rem; }
    .ca-article p { font-size: .93rem; color: var(--gray-700); line-height: 1.7; margin-bottom: .5rem; max-width: 780px; }
    .ca-section-label { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .07em; color: var(--white); background: var(--primary); padding: .2rem .65rem;
      border-radius: 100px; margin-bottom: 1rem; }
    .ca-toc { background: var(--off-white); border: 1px solid var(--border); border-radius: var(--radius);
      padding: 1.5rem 1.75rem; margin-bottom: 3rem; }
    .ca-toc h2 { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem; }
    .ca-toc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .25rem .5rem; }
    .ca-toc a { font-size: .83rem; color: var(--blue); }
    .ca-note { background: var(--accent); border-left: 4px solid var(--primary); border-radius: 0 var(--radius-s) var(--radius-s) 0;
      padding: .85rem 1.1rem; margin-bottom: 2rem; font-size: .9rem; color: var(--gray-700); }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <button class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Collective Agreement 2022–2025</h1>
      <p>SD54 Bulkley Valley · Effective July 1, 2022 to June 30, 2025</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">
      <div class="ca-note">
        This is the full text of the SD54 Bulkley Valley Collective Agreement. Use the search or Ask AI button
        to find specific articles, salary information, leave entitlements, or working conditions.
      </div>
      <div class="ca-toc">
        <h2>Table of Contents</h2>
        <div class="ca-toc-grid">
          <a href="#article-a-28-exclusions-from-the-bargaining-unit">ARTICLE A.28:EXCLUSIONS FROM THE BARGAINING UNIT</a>
          <a href="#article-b-24-positions-of-special-responsibility">ARTICLE B.24:POSITIONS OF SPECIAL RESPONSIBILITY</a>
          <a href="#article-c-28-probationary-appointments">ARTICLE C.28:PROBATIONARY APPOINTMENTS</a>
          <a href="#article-d-31-teacher-involvement-in-planning-new-schools">ARTICLE D.31:TEACHER INVOLVEMENT IN PLANNING NEW SCHOOLS</a>
          <a href="#article-e-29-falsely-accused-employee-assistance">ARTICLE E.29:FALSELY ACCUSED EMPLOYEE ASSISTANCE</a>
          <a href="#article-f-25-professional-autonomy">ARTICLE F.25:PROFESSIONAL AUTONOMY</a>
          <a href="#article-g-37-self-funded-plan">ARTICLE G.37:SELF-FUNDED PLAN</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no-3-a">LETTER OF UNDERSTANDING No. 3. a</a>
          <a href="#letter-of-understanding-no-3-b">LETTER OF UNDERSTANDING No. 3.b</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#preamble">PREAMBLE</a>
          <a href="#article-a-1-term-continuation-and-renegotiation">ARTICLE A.1:TERM, CONTINUATION AND RENEGOTIATION</a>
          <a href="#article-a-2-recognition-of-the-union">ARTICLE A.2:RECOGNITION OF THE UNION</a>
          <a href="#article-a-3-membership-requirement">ARTICLE A.3:MEMBERSHIP REQUIREMENT</a>
          <a href="#article-a-4-local-and-bctf-dues-deduction">ARTICLE A.4:LOCAL AND BCTF DUES DEDUCTION</a>
          <a href="#article-a-5-committee-membership">ARTICLE A.5:COMMITTEE MEMBERSHIP</a>
          <a href="#article-a-6-grievance-procedure">ARTICLE A.6:GRIEVANCE PROCEDURE</a>
          <a href="#article-a-7-expedited-arbitration">ARTICLE A.7:EXPEDITED ARBITRATION</a>
          <a href="#article-a-8-leave-for-provincial-contract-negotiations">ARTICLE A.8:LEAVE FOR PROVINCIAL CONTRACT NEGOTIATIONS</a>
          <a href="#article-a-9-legislative-change">ARTICLE A.9:LEGISLATIVE CHANGE</a>
          <a href="#article-a-10-leave-for-regulatory-business-as-per-the-teachers-act">ARTICLE A.10:LEAVE FOR REGULATORY BUSINESS AS PER THE TEACHERS ACT</a>
          <a href="#article-a-21-management-rights">ARTICLE A.21:MANAGEMENT RIGHTS</a>
          <a href="#article-a-22-bvtu-rights">ARTICLE A.22:BVTU RIGHTS</a>
          <a href="#article-a-23-picket-line-protection">ARTICLE A.23:PICKET LINE PROTECTION</a>
          <a href="#article-a-24-copy-of-agreement">ARTICLE A.24:COPY OF AGREEMENT</a>
          <a href="#article-a-25-staff-orientation">ARTICLE A.25:STAFF ORIENTATION</a>
          <a href="#article-a-26-no-contracting-out">ARTICLE A.26:NO CONTRACTING OUT</a>
          <a href="#article-a-27-education-assistants">ARTICLE A.27:EDUCATION ASSISTANTS</a>
          <a href="#article-a-28-exclusions-from-the-bargaining-unit">ARTICLE A.28:EXCLUSIONS FROM THE BARGAINING UNIT</a>
          <a href="#article-b-1-salary">ARTICLE B.1:SALARY</a>
          <a href="#article-b-2-ttoc-call-pay-and-benefits">ARTICLE B.2:TTOC CALL PAY AND BENEFITS</a>
          <a href="#article-b-3-salary-determination-for-employees-in-adult-education">ARTICLE B.3:SALARY DETERMINATION FOR EMPLOYEES IN ADULT EDUCATION</a>
          <a href="#article-b-4-ei-rebate">ARTICLE B.4:EI REBATE</a>
          <a href="#article-b-5-registered-retirement-savings-plan">ARTICLE B.5:REGISTERED RETIREMENT SAVINGS PLAN</a>
          <a href="#article-b-6-salary-indemnity-plan-allowance">ARTICLE B.6:SALARY INDEMNITY PLAN ALLOWANCE</a>
          <a href="#article-b-7-reimbursement-for-personal-property-loss">ARTICLE B.7:REIMBURSEMENT FOR PERSONAL PROPERTY LOSS</a>
          <a href="#article-b-8-optional-twelve-month-pay-plan">ARTICLE B.8:OPTIONAL TWELVE-MONTH PAY PLAN</a>
          <a href="#article-b-9-pay-periods">ARTICLE B.9:PAY PERIODS</a>
          <a href="#article-b-10-reimbursement-for-mileage-and-insurance">ARTICLE B.10:REIMBURSEMENT FOR MILEAGE AND INSURANCE</a>
          <a href="#article-b-11-benefits">ARTICLE B.11:BENEFITS</a>
          <a href="#article-b-12-category-5">ARTICLE B.12:CATEGORY 5+</a>
          <a href="#article-b-13-board-payment-of-speech-language-pathologists-and-school-psychologists-professional-fees">ARTICLE B.13:BOARD PAYMENT OF SPEECH LANGUAGE PATHOLOGISTS’ AND SCHOOL PSYCHOLOGISTS’ PROFESSIONAL FEES</a>
          <a href="#article-b-14-experience-recognition">ARTICLE B.14:EXPERIENCE RECOGNITION</a>
          <a href="#article-b-21-salary-schedule-placement">ARTICLE B.21:SALARY SCHEDULE PLACEMENT</a>
          <a href="#article-b-22-salary">ARTICLE B.22:SALARY</a>
          <a href="#article-b-23-allowances">ARTICLE B.23:ALLOWANCES</a>
          <a href="#article-b-24-positions-of-special-responsibility">ARTICLE B.24:POSITIONS OF SPECIAL RESPONSIBILITY</a>
          <a href="#article-c-1-resignation">ARTICLE C.1:RESIGNATION</a>
          <a href="#article-c-2-seniority">ARTICLE C.2:SENIORITY</a>
          <a href="#article-c-3-evaluation">ARTICLE C.3:EVALUATION</a>
          <a href="#article-c-4-ttoc-employment">ARTICLE C.4:TTOC EMPLOYMENT</a>
          <a href="#article-c-21-layoff-re-engagement-severance-pay">ARTICLE C.21:LAYOFF, RE-ENGAGEMENT &amp; SEVERANCE PAY</a>
          <a href="#article-c-22-employment-on-continuing-contract">ARTICLE C.22:EMPLOYMENT ON CONTINUING CONTRACT</a>
          <a href="#article-c-23-procedures-for-discipline-dismissal-when-based-on-misconduct">ARTICLE C.23:PROCEDURES FOR DISCIPLINE/DISMISSAL WHEN BASED ON MISCONDUCT</a>
          <a href="#article-c-24-procedures-for-discipline-dismissal-when-based-on-performance">ARTICLE C.24:PROCEDURES FOR DISCIPLINE/DISMISSAL WHEN BASED ON PERFORMANCE</a>
          <a href="#article-c-25-part-time-teachers-rights">ARTICLE C.25:PART TIME TEACHERS&#x27; RIGHTS</a>
          <a href="#article-c-26-temporary-teachers-employment-rights">ARTICLE C.26:TEMPORARY TEACHERS&#x27; EMPLOYMENT RIGHTS</a>
          <a href="#article-c-27-teacher-teaching-on-call-employment-rights">ARTICLE C.27:TEACHER-TEACHING-ON-CALL EMPLOYMENT RIGHTS</a>
          <a href="#article-c-28-probationary-appointments">ARTICLE C.28:PROBATIONARY APPOINTMENTS</a>
          <a href="#article-d-1-class-size-and-teacher-workload">ARTICLE D.1:CLASS SIZE AND TEACHER WORKLOAD</a>
          <a href="#article-d-2-class-composition-and-inclusion">ARTICLE D.2:CLASS COMPOSITION AND INCLUSION</a>
          <a href="#article-d-3-non-enrolling-staffing-ratios">ARTICLE D.3:NON-ENROLLING STAFFING RATIOS</a>
          <a href="#article-d-4-preparation-time">ARTICLE D.4:PREPARATION TIME</a>
          <a href="#article-d-5-middle-schools">ARTICLE D.5:MIDDLE SCHOOLS</a>
          <a href="#article-d-6-alternate-school-calendar">ARTICLE D.6:ALTERNATE SCHOOL CALENDAR</a>
          <a href="#article-d-20-lunch-hour-supervision">ARTICLE D.20:LUNCH HOUR SUPERVISION</a>
          <a href="#article-d-21-extra-curricular-activities">ARTICLE D.21:EXTRA CURRICULAR ACTIVITIES</a>
          <a href="#article-d-22-staff-meetings">ARTICLE D.22:STAFF MEETINGS</a>
          <a href="#article-d-23-technological-change">ARTICLE D.23:TECHNOLOGICAL CHANGE</a>
          <a href="#article-d-24-health-and-safety-committee">ARTICLE D.24:HEALTH AND SAFETY COMMITTEE</a>
          <a href="#article-d-25-budget-process">ARTICLE D.25:BUDGET PROCESS</a>
          <a href="#article-d-26-instructional-time">ARTICLE D.26:INSTRUCTIONAL TIME</a>
          <a href="#article-d-27-beginning-teachers">ARTICLE D.27:BEGINNING TEACHERS</a>
          <a href="#article-d-28-regular-work-year">ARTICLE D.28:REGULAR WORK YEAR</a>
          <a href="#article-d-29-home-education">ARTICLE D.29:HOME EDUCATION</a>
          <a href="#article-d-30-mentor-beginning-teacher-program">ARTICLE D.30:MENTOR/BEGINNING TEACHER PROGRAM</a>
          <a href="#article-d-31-teacher-involvement-in-planning-new-schools">ARTICLE D.31:TEACHER INVOLVEMENT IN PLANNING NEW SCHOOLS</a>
          <a href="#article-e-1-non-sexist-environment">ARTICLE E.1:NON-SEXIST ENVIRONMENT</a>
          <a href="#article-e-2-harassment-sexual-harassment">ARTICLE E.2:HARASSMENT/SEXUAL HARASSMENT</a>
          <a href="#article-e-21-assignments-in-school">ARTICLE E.21:ASSIGNMENTS IN SCHOOL</a>
          <a href="#article-e-22-posting-and-filling-vacant-positions">ARTICLE E.22:POSTING AND FILLING VACANT POSITIONS</a>
          <a href="#article-e-23-transfers-and-assignments">ARTICLE E.23:TRANSFERS AND ASSIGNMENTS</a>
          <a href="#article-e-24-evaluation-of-teachers-effectiveness">ARTICLE E.24:EVALUATION OF TEACHERS EFFECTIVENESS</a>
          <a href="#article-e-25-no-discrimination">ARTICLE E.25:NO DISCRIMINATION</a>
          <a href="#article-e-26-personnel-files">ARTICLE E.26:PERSONNEL FILES</a>
          <a href="#article-e-27-race-relations">ARTICLE E.27:RACE RELATIONS</a>
          <a href="#article-e-28-student-parent-appeals">ARTICLE E.28:STUDENT/PARENT APPEALS</a>
          <a href="#article-e-29-falsely-accused-employee-assistance">ARTICLE E.29:FALSELY ACCUSED EMPLOYEE ASSISTANCE</a>
          <a href="#article-f-1-professional-development-funding">ARTICLE F.1:PROFESSIONAL DEVELOPMENT FUNDING</a>
          <a href="#article-f-21-professional-development-funding-and-control">ARTICLE F.21:PROFESSIONAL DEVELOPMENT FUNDING AND CONTROL</a>
          <a href="#article-f-22-professional-development-days">ARTICLE F.22:PROFESSIONAL DEVELOPMENT DAYS</a>
          <a href="#article-f-23-curriculum-educational-change-implementation">ARTICLE F.23:CURRICULUM/EDUCATIONAL CHANGE IMPLEMENTATION</a>
          <a href="#article-f-24-school-assessment-and-accreditation">ARTICLE F.24:SCHOOL ASSESSMENT AND ACCREDITATION</a>
          <a href="#article-f-25-professional-autonomy">ARTICLE F.25:PROFESSIONAL AUTONOMY</a>
          <a href="#article-g-1-portability-of-sick-leave">ARTICLE G.1:PORTABILITY OF SICK LEAVE</a>
          <a href="#article-g-2-compassionate-care-leave">ARTICLE G.2:COMPASSIONATE CARE LEAVE</a>
          <a href="#article-g-3-employment-standards-act-leaves">ARTICLE G.3:EMPLOYMENT STANDARDS ACT LEAVES</a>
          <a href="#article-g-4-bereavement-leave">ARTICLE G.4:BEREAVEMENT LEAVE</a>
          <a href="#article-g-5-unpaid-discretionary-leave">ARTICLE G.5:UNPAID DISCRETIONARY LEAVE</a>
          <a href="#article-g-6-leave-for-union-business">ARTICLE G.6:LEAVE FOR UNION BUSINESS</a>
          <a href="#article-g-7-ttocs-conducting-union-business">ARTICLE G.7:TTOCs CONDUCTING UNION BUSINESS</a>
          <a href="#article-g-8-ttocs-conducting-union-business-negotiating-team">ARTICLE G.8:TTOCs – CONDUCTING UNION BUSINESS NEGOTIATING TEAM</a>
          <a href="#article-g-9-temporary-principal-vice-principal-leave">ARTICLE G.9:TEMPORARY PRINCIPAL / VICE-PRINCIPAL LEAVE</a>
          <a href="#article-g-10-teachers-returning-from-parenting-and-compassionate-leaves">ARTICLE G.10:TEACHERS RETURNING FROM PARENTING AND COMPASSIONATE LEAVES</a>
          <a href="#article-g-11-cultural-leave-for-aboriginal-employees">ARTICLE G.11:CULTURAL LEAVE FOR ABORIGINAL EMPLOYEES</a>
          <a href="#article-g-12-maternity-pregnancy-leave-supplemental-employment-benefits">ARTICLE G.12:MATERNITY/PREGNANCY LEAVE SUPPLEMENTAL EMPLOYMENT BENEFITS</a>
          <a href="#article-g-21-applications">ARTICLE G.21:APPLICATIONS</a>
          <a href="#article-g-22-teacher-illness">ARTICLE G.22:TEACHER ILLNESS</a>
          <a href="#article-g-23-maternity">ARTICLE G.23:MATERNITY</a>
          <a href="#article-g-24-parental-leave">ARTICLE G.24:PARENTAL LEAVE</a>
          <a href="#article-g-25-parenthood">ARTICLE G.25:PARENTHOOD</a>
          <a href="#article-g-26-paternity">ARTICLE G.26:PATERNITY</a>
          <a href="#article-g-27-adoption">ARTICLE G.27:ADOPTION</a>
          <a href="#article-g-28-illness-in-the-family">ARTICLE G.28:ILLNESS IN THE FAMILY</a>
          <a href="#article-g-29-medical-examination">ARTICLE G.29:MEDICAL EXAMINATION</a>
          <a href="#article-g-30-medical-emergency">ARTICLE G.30:MEDICAL EMERGENCY</a>
          <a href="#article-g-31-jury-duty">ARTICLE G.31:JURY DUTY</a>
          <a href="#article-g-32-court-witness">ARTICLE G.32:COURT WITNESS</a>
          <a href="#article-g-33-long-service-discretionary">ARTICLE G.33:LONG SERVICE DISCRETIONARY</a>
          <a href="#article-g-34-extended-personal">ARTICLE G.34:EXTENDED PERSONAL</a>
          <a href="#article-g-35-elections">ARTICLE G.35:ELECTIONS</a>
          <a href="#article-g-36-educational">ARTICLE G.36:EDUCATIONAL</a>
          <a href="#article-g-37-self-funded-plan">ARTICLE G.37:SELF-FUNDED PLAN</a>
          <a href="#signatures">SIGNATURES</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#appendix">Appendix</a>
          <a href="#appendix-1-provincial-matters">Appendix 1 – Provincial Matters</a>
          <a href="#appendix">Appendix</a>
          <a href="#appendix-2-local-matters">Appendix 2 – Local Matters</a>
          <a href="#appendix-1-and">Appendix 1 and</a>
          <a href="#letter-of-understanding-no">Letter of Understanding No.</a>
          <a href="#letter-of-understanding-no">Letter of Understanding No.</a>
          <a href="#letter-of-understanding-no">Letter of Understanding No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no-3-a">LETTER OF UNDERSTANDING No. 3. a</a>
          <a href="#letter-of-understanding-no-3-b">LETTER OF UNDERSTANDING No. 3.b</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING No.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#schedule-a-of-all-restored-collective-agreement-provisions">Schedule “A” of All Restored Collective Agreement Provisions</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
          <a href="#letter-of-understanding-no">LETTER OF UNDERSTANDING NO.</a>
        </div>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section A: The Collective Bargaining Relationship</h2>
      <div class="ca-article" id="article-a-28-exclusions-from-the-bargaining-unit">
        <h3>ARTICLE A.28:EXCLUSIONS FROM THE BARGAINING UNIT</h3>
        <p>SECTION BSALARY AND ECONOMIC BENEFITS24</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section B: Salary and Economic Benefits</h2>
      <div class="ca-article" id="article-b-24-positions-of-special-responsibility">
        <h3>ARTICLE B.24:POSITIONS OF SPECIAL RESPONSIBILITY</h3>
        <p>SECTION CEMPLOYMENT RIGHTS43</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section C: Employment Rights</h2>
      <div class="ca-article" id="article-c-28-probationary-appointments">
        <h3>ARTICLE C.28:PROBATIONARY APPOINTMENTS</h3>
        <p>SECTION DWORKING CONDITIONS57</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section D: Working Conditions</h2>
      <div class="ca-article" id="article-d-31-teacher-involvement-in-planning-new-schools">
        <h3>ARTICLE D.31:TEACHER INVOLVEMENT IN PLANNING NEW SCHOOLS</h3>
        <p>SECTION EPERSONNEL PRACTICES68</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section E: School and Teaching Environment</h2>
      <div class="ca-article" id="article-e-29-falsely-accused-employee-assistance">
        <h3>ARTICLE E.29:FALSELY ACCUSED EMPLOYEE ASSISTANCE</h3>
        <p>SECTION FPROFESSIONAL RIGHTS81</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section F: Professional Development</h2>
      <div class="ca-article" id="article-f-25-professional-autonomy">
        <h3>ARTICLE F.25:PROFESSIONAL AUTONOMY</h3>
        <p>SECTION GLEAVES OF ABSENCE85</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section G: Leaves of Absence</h2>
      <div class="ca-article" id="article-g-37-self-funded-plan">
        <h3>ARTICLE G.37:SELF-FUNDED PLAN</h3>
        <p>LETTERS OF UNDERSTANDING98</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Designation of Provincial and Local Matters98</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Agreed Understanding of the Term Teacher Teaching on Call111</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no-3-a">
        <h3>LETTER OF UNDERSTANDING No. 3. a</h3>
        <p>Re: Section 4 of Bill 27 Education Services Collective Agreement Act112</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no-3-b">
        <h3>LETTER OF UNDERSTANDING No. 3.b</h3>
        <p>Re: Section 27.4 Education Services Collective Agreement Act112</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Employment Equity – Indigenous Peoples113</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Teacher Supply and Demand Initiatives114</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Article C.2. – Porting of Seniority – Separate Seniority Lists118</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Article C.2 – Porting of Seniority &amp; Article G.1 Portability of Sick Leave – Simultaneously Holding Part-Time Appointments in Two Different Districts120</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Article C.2 – Porting of Seniority – Laid off Teachers who are Currently on the Recall List122</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Provincial Extended Health Benefit Plan124</p>
        <p>Appendix A to Letter of Understanding No. 9126</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>Re: Recruitment and Retention for Teachers at Beaverdell and Big White Elementary Schools128</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Article C.4 TTOC Employment – TTOC Experience Credit Transfer within a District129</p>
        <p>TEACHER NOTICE: LOU 11 – TTOC EXPERIENCE TRANSFER REQUEST – FORM A131</p>
        <p>Re: August 31st transfers for TTOC experience accrued up to and including June 30th131</p>
        <p>TEACHER NOTICE: LOU 11 - TTOC EXPERIENCE TRANSFER REQUEST – FORM B132</p>
        <p>Re: December 31st transfers for TTOC experience accrued up to and including November 15th132</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Agreement Regarding Restoration of Class Size, Composition, Ratios and Ancillary Language133</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Committee to Discuss Indigenous Peoples Recognition and Reconciliation141</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Cultural Leave for Aboriginal Employees142</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Structural Review Committees143</p>
        <p>1.Tri-partite sub-committee to review the split-of-issues143</p>
        <p>2.Review of local bargaining trial procedure143</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Benefits Improvements144</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>Re: Employment Equity – Groups That Face Disadvantage145</p>
        <p>INDEX147</p>
        <p>BETWEEN:</p>
        <p>THE BOARD OF EDUCATION OF SCHOOL DISTRICT NO. 54 (BULKLEY VALLEY), a corporate body established pursuant to the School Act, hereinafter referred to as &quot;the Board&quot;</p>
        <p>AND:</p>
        <p>THE BULKLEY VALLEY TEACHERS&#x27; UNION a local of the British Columbia Teachers’ Federation herein after referred to as the “BVTU”.</p>
        <p>THE PARTIES AGREE AS FOLLOWS:</p>
      </div>

      <div class="ca-article" id="preamble">
        <h3>PREAMBLE</h3>
        <p>It is recognized that it is in the best interests of both parties as well as those served by the school system that harmonious relations and settled conditions of employment be maintained.</p>
        <p>This Agreement recognizes the duty of the School Board and the BVTU to cooperate fully to provide the highest quality of educational service possible. It is further recognized that it is in the mutual interest of the School Board, and the BVTU to provide for the efficient and orderly operation of the schools within the School District under methods which will further, to the fullest extent possible, the education of the pupils in the School District.</p>
        <p>Note:This is a working document intended to set out the agreed upon terms and conditions of employment between BCTF and BCPSEA as those terms and conditions apply in S.D. No. 54 (Bulkley Valley). In the event of a dispute, the original source documents will be applicable.</p>
        <p>SECTION ATHE COLLECTIVE BARGAINING RELATIONSHIP</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section A: The Collective Bargaining Relationship</h2>
      <div class="ca-article" id="article-a-1-term-continuation-and-renegotiation">
        <h3>ARTICLE A.1:TERM, CONTINUATION AND RENEGOTIATION</h3>
        <p>In this Collective Agreement, &quot;Previous Collective Agreement&quot; means the Collective Agreement that was in effect between the two parties for the period July 1, 2019, to June 30, 2022, including any amendments agreed to by the parties during that period.</p>
        <p>Except as otherwise specifically provided, this Collective Agreement is effective July 1, 2022, to June 30, 2025. The parties agree that not less than four (4) months preceding the expiry of this Collective Agreement, they will commence collective bargaining in good faith with the object of renewal or revision of this Collective Agreement and the concluding of a Collective Agreement for the subsequent period.</p>
        <p>In the event that a new Collective Agreement is not in place by June 30, 2025, the terms of this Collective Agreement are deemed to remain in effect until the date on which a new Collective Agreement is concluded.</p>
        <p>All terms and conditions of the Previous Collective Agreement are included in the Collective Agreement, except where a term or condition has been amended or modified in accordance with this Collective Agreement.</p>
        <p>4.a.If employees are added to the bargaining unit established under section 5 of the Public Education Labour Relations Act during the term of this Collective Agreement, the parties shall negotiate terms and conditions that apply to those employees.</p>
        <p>b.If the parties are unable to agree on terms and conditions applicable to those employees, either party may refer the issues in dispute to a mutually acceptable arbitrator who shall have jurisdiction to impose terms and conditions.</p>
        <p>c.If the parties are unable to agree on an arbitrator, either party may request the Director of the Collective Agreement Arbitration Bureau to appoint an arbitrator.</p>
        <p>5.a.Changes in those local matters agreed to by a local and the employer will amendthe Previous Collective Agreement provisions and form part of this Collective Agreement, subject to Article A.1.5.b below.</p>
        <p>b.A local and the employer must agree to the manner and timing of implementation of a change in a local matter.</p>
        <p>c.i.This Collective Agreement continues previous agreements between the parties with respect to the designation of provincial and local matters (See Letter of Understanding No. 1).</p>
        <p>ii.The parties may agree to another designation which is consistent with the Public Education Labour Relations Act.</p>
      </div>

      <div class="ca-article" id="article-a-2-recognition-of-the-union">
        <h3>ARTICLE A.2:RECOGNITION OF THE UNION</h3>
        <p>1.The BCPSEA recognizes the BCTF as the sole and exclusive bargaining agent for the negotiation and administration of all terms and conditions of employment of all employees within the bargaining unit for which the BCTF is established as the bargaining agent pursuant to PELRA and subject to the provisions of this Collective Agreement.</p>
        <p>2.Pursuant to PELRA, the employer in each district recognizes the local in that district as the teachers&#x27; union for the negotiation in that district of all terms and conditions of employment determined to be local matters, and for the administration of this Collective Agreement in that district subject to PELRA and the Provincial Matters Agreement.</p>
        <p>3.The BCTF recognizes BCPSEA as the accredited bargaining agent for every school board in British Columbia. BCPSEA has the exclusive authority to bargain collectively for the school boards and to bind the school boards by Collective Agreement in accordance with Section 2 of Schedule 2 of PELRA.</p>
      </div>

      <div class="ca-article" id="article-a-3-membership-requirement">
        <h3>ARTICLE A.3:MEMBERSHIP REQUIREMENT</h3>
        <p>1.All employees covered by this Collective Agreement shall, as a condition of employment, become and remain members of the British Columbia Teachers’ Federation and the local(s) in the district(s) in which they are employed, subject to Article A.3.2.</p>
        <p>2.Where provisions of the Previous Local Agreement or the Previous Letter of Understanding in a district exempted specified employees from the requirement of membership, those provisions shall continue unless and until there remain no exempted employees in that district. All terms and conditions of exemption contained in the Previous Local Agreement or the Previous Letter of Understanding shall continue to apply. An exempted employee whose employment is terminated for any reason and who is subsequently rehired, or who subsequently obtains membership, shall become and/or remain a member of the BCTF and the respective local in accordance with this Collective Agreement.</p>
      </div>

      <div class="ca-article" id="article-a-4-local-and-bctf-dues-deduction">
        <h3>ARTICLE A.4:LOCAL AND BCTF DUES DEDUCTION</h3>
        <p>1.The employer agrees to deduct from the salary of each employee covered by this Collective Agreement an amount equal to the fees of the BCTF according to the scale established pursuant to its constitution and by-laws, inclusive of the fees of the local in the district, according to the scale established pursuant to its constitution and by-laws, and shall remit the same to the BCTF and the local respectively. The employer further agrees to deduct levies of the BCTF or of the local established in accordance with their constitutions and by-laws, and remit the same to the appropriate body.</p>
        <p>2.At the time of hiring, the employer shall require all new employees to complete and sign the BCTF and Local application for membership and assignment of fees form. The BCTF agrees to supply the appropriate forms. Completed forms shall be forwarded to the local in a time and manner consistent with the Previous Local Agreement or the existing practice of the parties.</p>
        <p>3.The employer will remit the BCTF fees and levies by direct electronic transfer from the district office where that is in place, or through inter-bank electronic transfer. The transfer of funds to the BCTF will be remitted by the 15th of the month following the deduction.</p>
        <p>4.The form and timing of the remittance of local fees and levies shall remain as they are at present unless they are changed by mutual agreement between the local and the employer.</p>
        <p>5.The employer shall provide to the BCTF and the local at the time of remittance an account of the fees and levies, including a list of employees and amounts paid.</p>
      </div>

      <div class="ca-article" id="article-a-5-committee-membership">
        <h3>ARTICLE A.5:COMMITTEE MEMBERSHIP</h3>
        <p>1.Local representatives on committees specifically established by this Collective Agreement shall be appointed by the local.</p>
        <p>2.In addition, if the employer wishes to establish a committee which includes bargaining unit members, it shall notify the local about the mandate of the committee and the local shall appoint the representatives. The local will consider the mandate of the committee when appointing the representatives. If the employer wishes to discuss the appointment of a representative, the superintendent or designate, and the president or designate of the local may meet and discuss the matter.</p>
        <p>3.Release time with pay shall be provided by the employer to any employee who is a representative on a committee referred to in Article A.5.1 and A.5.2 above, in order to attend meetings that occur during normal instructional hours. Teacher Teaching on Call (TTOC) costs shall be borne by the employer.</p>
        <p>When a TTOC is appointed to a committee referred to in Article A.5.1 and A.5.2 above, and the committee meets during normal instructional hours, the TTOC shall be paid pursuant to the provisions in each district respecting TTOC Pay and Benefits. A TTOC attending a “half-day” meeting shall receive a half-day’s pay. If the meeting extends past a “half-day,” the TTOC shall receive a full-day’s pay.</p>
      </div>

      <div class="ca-article" id="article-a-6-grievance-procedure">
        <h3>ARTICLE A.6:GRIEVANCE PROCEDURE</h3>
        <p>1.Preamble</p>
        <p>The parties agree that this article constitutes the method and procedure for a final and conclusive settlement of any dispute (hereinafter referred to as &quot;the grievance&quot;) respecting the interpretation, application, operation or alleged violation of this Collective Agreement, including a question as to whether a matter is arbitrable.</p>
        <p>Steps in Grievance Procedure</p>
        <p>2.Step One</p>
        <p>a.The local or an employee alleging a grievance (&quot;the grievor&quot;) shall request a meeting with the employer official directly responsible, and at such meeting they shall attempt to resolve the grievance summarily. Where the grievor is not the local, the grievor shall be accompanied at this meeting by a representative appointed by the local.</p>
        <p>b.The grievance must be raised within thirty (30) working days of the alleged violation, or within thirty (30) working days of the party becoming reasonably aware of the alleged violation.</p>
        <p>3.Step Two</p>
        <p>a.If the grievance is not resolved at Step One of the grievance procedure within ten (10) working days of the date of the request made for a meeting referred to in Article A.6.2.a the grievance may be referred to Step Two of the grievance procedure by letter, through the president or designate of the local to the superintendent or designate. The superintendent or designate shall forthwith meet with the president or designate of the local, and attempt to resolve the grievance.</p>
        <p>b.The grievance shall be presented in writing giving the general nature of the grievance.</p>
        <p>4.Step Three</p>
        <p>a.If the grievance is not resolved within ten (10) working days of the referral to Step Two in Article A.6.3.a the local may, within a further ten (10) working days, by letter to the superintendent or official designated by the district, refer the grievance to Step Three of the grievance procedure. Two representatives of the local and two representatives of the employer shall meet within ten (10) working days and attempt to resolve the grievance.</p>
        <p>If both parties agree and the language of the previous Local Agreement stipulates:</p>
        <p>the number of representatives of each party at Step Three shall be three; and/or</p>
        <p>ii.at least one of the employer representatives shall be a trustee.</p>
        <p>b.If the grievance involves a Provincial Matters issue, in every case a copy of the letter shall be sent to BCPSEA and the BCTF.</p>
        <p>5.Omitting Steps</p>
        <p>a.Nothing in this Collective Agreement shall prevent the parties from mutually agreeing to refer a grievance to a higher step in the grievance procedure.</p>
        <p>b.Grievances of general application may be referred by the local, BCTF, the employer or BCPSEA directly to Step Three of the grievance procedure.</p>
        <p>6.Referral to Arbitration: Local Matters</p>
        <p>a.If the grievance is not resolved at Step Three within ten (10) working days of the meeting referred to in Article A.6.4, the local or the employer where applicable may refer a Local Matters Grievance, as defined in Appendix 2 and Addenda, to arbitration within a further fifteen (15) working days.</p>
        <p>b.The referral to arbitration shall be in writing and should note that it is a Local Matters Grievance. The parties shall agree upon an arbitrator within ten (10) working days of such notice.</p>
        <p>7.Referral to Arbitration: Provincial Matters</p>
        <p>If the grievance is not resolved at Step Three within ten (10) working days of the meeting referred to in Article A.6.4, the BCTF or BCPSEA where applicable may refer a Provincial Matters Grievance, as defined in Appendix 1 and Addenda, to arbitration within a further fifteen (15) working days.</p>
        <p>b.The referral to arbitration shall be in writing and should note that it is a Provincial Matters Grievance. The parties shall agree upon an arbitrator within ten (10) working days of such notice.</p>
        <p>c.Review Meeting:</p>
        <p>i.Either the BCTF or BCPSEA may request in writing a meeting to review the issues in a Provincial Matters Grievance that has been referred to arbitration.</p>
        <p>ii.Where the parties agree to hold such a meeting, it shall be held within ten (10) working days of the request, and prior to the commencement of the arbitration hearing. The scheduling of such a meeting shall not alter in any way the timelines set out in Article A.6.7.a and A.6.7.b of this article.</p>
        <p>iii.Each party shall determine who shall attend the meeting on its behalf.</p>
        <p>8.Arbitration (Conduct of)</p>
        <p>a.All grievances shall be heard by a single arbitrator unless the parties mutually agree to submit a grievance to a three-person arbitration board.</p>
        <p>b.The arbitrator shall determine the procedure in accordance with relevant legislation and shall give full opportunity to both parties to present evidence and make representations. The arbitrator shall hear and determine the difference or allegation and shall render a decision within sixty (60) days of the conclusion of the hearing.</p>
        <p>c.All discussions and correspondence during the grievance procedure or arising from Article A.6.7.c shall be without prejudice and shall not be admissible at an arbitration hearing except for formal documents related to the grievance procedure, i.e., the grievance form, letters progressing the grievance, and grievance responses denying the grievance.</p>
        <p>d.Authority of the Arbitrator:</p>
        <p>i.It is the intent of both parties to this Collective Agreement that no grievance shall be defeated merely because of a technical error in processing the grievance through the grievance procedure. To this end an arbitrator shall have the power to allow all necessary amendments to the grievance and the power to waive formal procedural irregularities in the processing of a grievance in order to determine the real matter in dispute and to render a decision according to equitable principles and the justice of the case.</p>
        <p>ii.The arbitrator shall not have jurisdiction to alter or change the provisions of the Collective Agreement or to substitute new ones.</p>
        <p>iii.The provisions of this article do not override the provisions of the B.C. Labour Relations Code.</p>
        <p>The decision of the arbitrator shall be final and binding.</p>
        <p>Each party shall pay one half of the fees and expenses of the arbitrator.</p>
        <p>9.General</p>
        <p>a.After a grievance has been initiated, neither the employer&#x27;s nor BCPSEA&#x27;s representatives will enter into discussion or negotiations with respect to the grievance, with the grievor or any other member(s) of the bargaining unit without the consent of the local or the BCTF.</p>
        <p>b.The time limits in this grievance procedure may be altered by mutual written consent of the parties.</p>
        <p>c.If the local or the BCTF does not present a grievance to the next higher level, they shall not be deemed to have prejudiced their position on any future grievance.</p>
        <p>d.No employee shall suffer any form of discipline, discrimination or intimidation by the employer as a result of having filed a grievance or having taken part in any proceedings under this article.</p>
        <p>e.i.Any employee whose attendance is required at any grievance meeting pursuant to this article, shall be released without loss of pay when such meeting is held during instructional hours. If a Teacher Teaching on Call (TTOC) is required, such costs shall be borne by the employer;</p>
        <p>Any employee whose attendance is required at an arbitration hearing shall be released without loss of pay when attendance is required during instructional hours; and</p>
        <p>iii.Unless the previous Local Agreement specifically provides otherwise, the party that requires an employee to attend an arbitration hearing shall bear the costs for any TTOC that may be required.</p>
      </div>

      <div class="ca-article" id="article-a-7-expedited-arbitration">
        <h3>ARTICLE A.7:EXPEDITED ARBITRATION</h3>
        <p>Scope</p>
        <p>By mutual agreement, the parties may refer a grievance to the following expedited arbitration process.</p>
        <p>Process</p>
        <p>a.The grievance shall be referred to one of the following arbitrators:</p>
        <p>i.Mark Brown</p>
        <p>ii.Irene Holden</p>
        <p>iii.Chris Sullivan</p>
        <p>iv.Elaine Doyle</p>
        <p>v.Judi Korbin</p>
        <p>vi.John Hall</p>
        <p>b.The parties may agree to an alternate arbitrator in a specific case and may add to or delete from the list of arbitrators by mutual agreement.</p>
        <p>c.Within three (3) days of the referral, the arbitrator shall convene a case management call to determine the process for resolving the dispute. The case management process shall include a time frame for the exchange of particulars and documents, a timeframe for written submissions if directed by the arbitrator, an agreed statement of facts, or any other process considered by the arbitrator to be effective in ensuring an expeditious resolution to the dispute. The parties will endeavour to exchange information as stipulated in the case management process within seven (7) days.</p>
        <p>d.If an oral hearing is scheduled by the arbitrator it shall be held within fourteen (14) days of the referral to the arbitrator. The hearing shall be concluded within one (1) day.</p>
        <p>e.The written submissions shall not exceed ten (10) pages in length.</p>
        <p>f.As the process is intended to be informal and non-legal, neither party will be represented by outside legal counsel.</p>
        <p>g.The parties will use a limited number of authorities.</p>
        <p>h.The arbitrator will issue a decision within five (5) days of the conclusion of the arbitration or submission process.</p>
        <p>Prior to rendering a decision, the arbitrator may assist the parties in mediating a resolution.</p>
        <p>j.All decisions of the arbitrator are final and binding and are to be limited in application to the particular grievance and are without prejudice. They shall be of no precedential value and shall not thereafter be referred to by the parties in respect of any other matter.</p>
        <p>k.Neither party shall appeal or seek to review a decision of the arbitrator.</p>
        <p>l.The arbitrator retains jurisdiction with respect to any issues arising from their decision.</p>
        <p>m.Except as set out herein, the arbitrator under this process shall have the powers and jurisdiction of an arbitrator prescribed in the Labour Relations Code of British Columbia.</p>
        <p>n.The parties shall equally share the costs of the fees and expenses of the arbitrator.</p>
        <p>o.Representatives of BCPSEA and BCTF will meet yearly to review the expedited arbitration process.</p>
      </div>

      <div class="ca-article" id="article-a-8-leave-for-provincial-contract-negotiations">
        <h3>ARTICLE A.8:LEAVE FOR PROVINCIAL CONTRACT NEGOTIATIONS</h3>
        <p>1.The employer shall grant a leave of absence without pay to an employee designated by the BCTF for the purpose of preparing for, participating in or conducting negotiations as a member of the provincial bargaining team of the BCTF.</p>
        <p>2.To facilitate the administration of this clause, when leave without pay is granted, the employer shall maintain salary and benefits for the employee and the BCTF shall reimburse the employer for the salary costs.</p>
        <p>3.Any other leaves of absence granted for provincial bargaining activities shall be granted on the basis that the salary and benefits of the employees continue and the BCTF shall reimburse the employer for the salary costs of any teacher employed to replace a teacher granted leave.</p>
        <p>4.Any leaves of absence granted for local bargaining activities shall be granted in accordance with the Previous Local Agreement.</p>
      </div>

      <div class="ca-article" id="article-a-9-legislative-change">
        <h3>ARTICLE A.9:LEGISLATIVE CHANGE</h3>
        <p>1.In this article, “legislation” means any new or amended statute, regulation, Minister’s Order, or Order in Council which arises during the term of the Collective Agreement or subsequent bridging period.</p>
        <p>2.a.Should legislation render any part of the Collective Agreement null and void, or substantially alter the operation or effect of any of its provisions, the remainder of the provisions of the Collective Agreement shall remain in full force and effect.</p>
        <p>b.In that event, the parties shall meet forthwith to negotiate in good faith modifications to the Collective Agreement which shall achieve, to the full extent legally possible, its original intent.</p>
        <p>3.If, within thirty (30) days of either party&#x27;s request for such meeting, the parties cannot agree on such modifications, or cannot agree that the Collective Agreement has been affected by legislation, either party may refer the matter(s) in dispute to arbitration pursuant to Article A.6 (Grievance Procedure).</p>
        <p>4.The arbitrator&#x27;s authority shall be limited to deciding whether this article applies and, if so, adding to, deleting from or otherwise amending, to the full extent legally possible, the article(s) directly affected by legislation.</p>
      </div>

      <div class="ca-article" id="article-a-10-leave-for-regulatory-business-as-per-the-teachers-act">
        <h3>ARTICLE A.10:LEAVE FOR REGULATORY BUSINESS AS PER THE TEACHERS ACT</h3>
        <p>Upon written request to the Superintendent or designate from the Ministry of Education, an employee who is appointed or elected to the BC Teachers’ Council or appointed to the Disciplinary or Professional Conduct Board shall be entitled to a leave of absence with pay and shall be deemed to be in the full employ of the board as defined in Article G.6.1.b.</p>
        <p>Upon written request to the superintendent or designate from the Ministry of Education, a Teacher Teaching on Call (TTOC) who is appointed or elected to the BC Teachers’ Council or appointed to the Disciplinary and Professional Conduct Board shall be considered on leave and shall be deemed to be in the full employ of the Board as defined in Article A.10.1 above. TTOCs shall be paid in accordance with the Collective Agreement.</p>
        <p>Leave pursuant to Article A.10.1 and A.10.2 above shall not count toward any limits on the number of days and/or teachers on leave in the provisions in Article G.6.</p>
        <p>LOCAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-a-21-management-rights">
        <h3>ARTICLE A.21:MANAGEMENT RIGHTS</h3>
        <p>A.21.1The Bulkley Valley Teachers&#x27; Union recognizes the responsibility and the right of the Board to manage and operate the school district in accordance with its responsibilities and commitments.</p>
        <p>A.21.2The right to assign duties and to manage and direct employees is vested exclusively in the Board except as otherwise specifically provided for in this Agreement.</p>
        <p>A.21.3Such rights shall be exercised in a manner that is reasonable and non-discriminatory.</p>
        <p>A.21.4It is expressly understood that all rights not specifically covered by this Agreement shall remain the rights of the school district.</p>
      </div>

      <div class="ca-article" id="article-a-22-bvtu-rights">
        <h3>ARTICLE A.22:BVTU RIGHTS</h3>
        <p>A.22.1President Release Time</p>
        <p>a.Release time from teaching duties will be granted to the president of the BVTU for the purpose of conducting BVTU business.</p>
        <p>b.The amount of release time required will be determined annually by the BVTU and requests for such leave shall be in writing and received by the Superintendent of Schools by May 31 and earlier if possible.</p>
        <p>c.The Board will continue to pay the president their full salary and provide the benefits specified in this Agreement. The BVTU will reimburse the Board for the cost of such salary and benefits.</p>
        <p>d.The president shall be deemed to be in the full employ of the Board.</p>
        <p>e.The president shall inform the Board of the number of days (or partial days), if any, that they were absent from their duties due to illness. Such days will be deducted from the president&#x27;s accumulated sick leave credits.</p>
        <p>f.The teacher returning to full teaching duties from a term or terms as president shall be assigned to the position held prior to the release or to a comparable position, which is acceptable to the teacher. The teacher has the right to refuse a maximum of two (2) jobs under this clause.</p>
        <p>g.In the event that the president is unable to continue to fulfill the presidential duties for health reasons, the Board shall grant release time for another BVTU member to assume the duties of the president; the former president will go on sick leave while the new president will be subject to the provisions in Article A.22.1.c to A.22.1.f above.</p>
        <p>A.22.2Officers of the BVTU Release Time</p>
        <p>a.Provided that adequate notice is given, release time without loss of pay will be given to:</p>
        <p>i.any employee covered by this agreement who is a member of the Executive Committee, Representative Assembly, a committee or task force of either the BVTU, the BCTF, the CTF, the Teacher Regulation Branch or appointed an elected official representative or delegate of the BVTU or BCTF to carry out BVTU business.</p>
        <p>ii.teachers who are members of the BVTU bargaining committee to attend meetings of that committee.</p>
        <p>iii.teachers called by the BVTU to appear as witnesses before an arbitration board or the Labour Relations Board.</p>
        <p>iv.one staff representative from each school to attend one day of staff representative training per year.</p>
        <p>b.The Board will share at 50% of the cost for Teachers-Teaching-On-Call for up to six (6) members of the BVTU bargaining committee while engaged in negotiations with the Board. The BVTU will be limited to a maximum of two teachers from any one school for negotiation purposes with the Board.</p>
        <p>c.Leave taken under this provision will be counted as .6 for a morning or part thereof and .4 for an afternoon or part thereof.</p>
        <p>d.Employees taking leave under this provision are considered full time employees.</p>
        <p>e.The BVTU shall reimburse the Board for Teachers-Teaching-On-Call costs.</p>
        <p>f.When teacher representatives are requested by the Board to meet on union/management matters, they shall suffer no loss of pay for time spent. The Board shall bear the full cost of Teachers-Teaching-On-Call costs.</p>
        <p>A.22.3Leave to Serve on Affiliated Organizations</p>
        <p>a.Provided that adequate notice is given the Board shall grant a leave of absence without pay to a member who is elected/appointed to a full time position with an affiliated organization or the Teacher Regulation Branch, without loss of seniority.</p>
        <p>b.A teacher who is on leave under this provision shall have access to the Board&#x27;s benefit plans only if such benefits are not provided by the organization/affiliation outlined in Article A.22.3.a. Any access by employees to the Board&#x27;s benefit plans under this article will require the employee to pay 100% of the cost.</p>
        <p>c.A teacher who is on leave under this provision shall be entitled on written notice at least one month prior to the end of a school year, to return to employment with the Board effective the commencement of the next school year, and shall be entitled to the assignment previously held or to a reasonably comparable assignment acceptable to the Teacher. The teacher will be permitted to refuse a maximum of two (2) jobs under this provision.</p>
        <p>A.22.4School Staff Representatives</p>
        <p>a.Representatives of the BVTU of each school shall have the right to:</p>
        <p>i.convene Union meetings in the school to conduct Union business provided such meetings are held out-of-school time;</p>
        <p>ii.investigate or participate in solving a grievance or arbitration;</p>
        <p>iii.be present upon request at a meeting between a member and a Principal/Vice-Principal or Board official. If a Principal/Vice-Principal or Board official requests the meeting during school hours, the member will not lose any pay.  If such a meeting involves the disciplining of an employee, a representative of the BVTU must be present.</p>
        <p>A.22.5Access to Work site</p>
        <p>a.Representatives of the BVTU, in consultation with the Principal or designate, shall have the right to transact union business on school property at all reasonable times. Such activities or use are not to interfere with classroom instruction and facilities and equipment shall be properly booked.</p>
        <p>b. The employer shall permit use of equipment, such as chairs and tables at no cost for the Annual General BVTU meeting. All other operating cost of equipment and supplies consumed by the BVTU shall be reimbursed to the employer.</p>
        <p>A.22.6Bulletin Boards</p>
        <p>a.The BVTU has the right to post notices of activities and matters of Union concern. The Board will provide bulletin boards in all staff rooms in all school buildings for this purpose. All notices shall be authorized by the BVTU.</p>
        <p>A.22.7Internal Mail</p>
        <p>a.The BVTU shall have access to the District mail service, employee mail boxes and electronic mail for communication to teachers, free of charge, provided any increased volume does not add extra costs to the employer.</p>
        <p>A.22.8School Staff Committees</p>
        <p>a.A Staff Committee shall be established in each school if the staff so wishes.</p>
        <p>b.The size and membership of the Staff Committee shall be determined by the staff except that the Principal or their delegate shall be a member.</p>
        <p>c.Staff Committees shall meet to discuss issues relating to the school staff.</p>
        <p>d.The Principal and/or Vice-Principal shall consider all recommendations made to them by the Staff Committee.</p>
        <p>e.Should recommendations not be acted upon, the Staff Committee will be advised of the reasons and in writing if requested by the committee.</p>
        <p>f.Any provisions of this Article are grievable in regard to process only. Process refers to the procedural steps spelled out in this Article A.22.8.</p>
        <p>A.22.9Access to Information</p>
        <p>a.The Board will provide the BVTU with a list of employees showing the following information, no later than October 15th:</p>
        <p>i.names</p>
        <p>ii.mailing and email addresses</p>
        <p>iii.phone numbers</p>
        <p>iv.grid placement</p>
        <p>v.seniority</p>
        <p>vi.staff assignment.</p>
        <p>vii.total teacher FTE for each school</p>
        <p>b.The Board will also provide notification on hirings, assignment changes, layoffs, retirements, suspensions, discharges, resignations, leaves and postings as they occur.</p>
        <p>c.No later than October 15th the Board and BVTU will match the lists of:</p>
        <p>i.leaves,</p>
        <p>ii.teachers on temporary appointments, and</p>
        <p>iii.positions considered to be temporary.</p>
        <p>d.Upon request the Board agrees to furnish minutes and agendas of public Board meetings, annual audited financial statements and the preliminary and final budget as is approved by the Board that is public information.</p>
      </div>

      <div class="ca-article" id="article-a-23-picket-line-protection">
        <h3>ARTICLE A.23:PICKET LINE PROTECTION</h3>
        <p>A.23.1All employees covered under this Agreement may refuse to cross or work behind a legally constituted picket line as defined under the Labour Relations Code.</p>
        <p>A.23.2Failure to cross such a picket line shall not be considered a violation of this Agreement nor shall it be cause for disciplinary action.</p>
        <p>A.23.3Any employee failing to report to work under this clause shall be considered absent without pay.</p>
        <p>A.23.4Teachers shall not be required to perform duties or work normally performed by employees in a legal strike or lockout except for emergent matters which would threaten the safety of students.</p>
      </div>

      <div class="ca-article" id="article-a-24-copy-of-agreement">
        <h3>ARTICLE A.24:COPY OF AGREEMENT</h3>
        <p>A.24.1The Board shall provide every employee with an electronic copy of this Agreement within thirty (30) working days of the completion of the melding process after ratification. The format of such copy shall be agreed between the Board and the BVTU.</p>
        <p>A.25.2The employer shall provide and maintain two (2) printed copies of the collective agreement at every school site.</p>
      </div>

      <div class="ca-article" id="article-a-25-staff-orientation">
        <h3>ARTICLE A.25:STAFF ORIENTATION</h3>
        <p>A.25.1All new employees of the Board shall receive an orientation provided by the Board and the BVTU early in the first term.</p>
        <p>A.25.2The orientation shall acquaint employees with the basic operation of the School District and the school as well as the rights and responsibilities of the Collective Agreement. Costs shall be shared equally between the parties.</p>
      </div>

      <div class="ca-article" id="article-a-26-no-contracting-out">
        <h3>ARTICLE A.26:NO CONTRACTING OUT</h3>
        <p>A.26.1All work performed by members of the bargaining unit shall continue to be performed by members of the bargaining unit. The Board shall not contract out instructional services of a type and kind normally or regularly performed by members of the bargaining unit, except as mutually agreed between the Board and the BVTU.</p>
      </div>

      <div class="ca-article" id="article-a-27-education-assistants">
        <h3>ARTICLE A.27:EDUCATION ASSISTANTS</h3>
        <p>A.27.1The teacher is responsible for the direct instructional supervision of education assistants assigned to their classroom. The principal or designate is responsible for the employment supervision of the education assistants assigned to their schools.</p>
        <p>A.27.2Education assistants shall not assume any direct instructional responsibility for providing educational programs but may assist the teacher in:</p>
        <p>a.providing assistance to individual students and groups of students.</p>
        <p>b.evaluating students</p>
        <p>c.maintaining student records or reports to parents</p>
        <p>d.providing advice to students</p>
        <p>e.supervision of students</p>
        <p>A.27.3Education assistants shall not be used as alternatives for qualified professional staff including librarians, teachers-teaching-on-call and counsellors.</p>
        <p>A.27.4Education assistants shall not be used as an alternative for lowering the pupil/teacher ratio, or lowering class sizes.</p>
        <p>A.27.5Teachers shall not write formal evaluations on education assistants. Principals shall have the responsibility for completing evaluation reports on the performance of educational assistants, in consultation with the teacher(s).</p>
      </div>

      <div class="ca-article" id="article-a-28-exclusions-from-the-bargaining-unit">
        <h3>ARTICLE A.28:EXCLUSIONS FROM THE BARGAINING UNIT</h3>
        <p>A.28.1Any position that is currently included in the bargaining unit may not be excluded from the bargaining unit without the agreement of the parties.</p>
        <p>A.28.2The Board shall notify the BVTU of all new positions offered in the district and send to the BVTU a written job description of the new position.</p>
        <p>A.28.3The inclusion or exclusion of new positions shall be determined on the basis that the position involves:</p>
        <p>a.any of the functions outlined in the Labour Relations Code as the basis for exclusion from the definition of an &quot;employee&quot;; or</p>
        <p>b.the functions of a director of instruction as provided by the School Act; or</p>
        <p>c.includes any duties regarding the supervision and evaluation of teachers as designated to principals and vice principals in the School Act.</p>
        <p>A.28.4Failure by the parties to reach mutual agreement under Article A.28.3 shall result in either party referring the matter directly to arbitration pursuant to Article A.6.</p>
        <p>SECTION BSALARY AND ECONOMIC BENEFITS</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section B: Salary and Economic Benefits</h2>
      <div class="ca-article" id="article-b-1-salary">
        <h3>ARTICLE B.1:SALARY</h3>
        <p>1.The local salary grids are amended to reflect the following general wage increases:</p>
        <p>Effective July 1, 2022</p>
        <p>$427 to each step of the salary grid; and</p>
        <p>3.24%</p>
        <p>Effective July 1, 2023</p>
        <p>by the annualized average of BC Consumer Price Index (CPI) over twelve months starting on March 1, 2022 (Cost of Living Adjustment) to a minimum of 5.5% and a maximum of 6.75%, calculated as per B.1.9</p>
        <p>Effective July 1, 2024</p>
        <p>by the annualized average of BC Consumer Price Index (CPI) over twelve months starting on March 1, 2023 (Cost of Living Adjustment) to a minimum of 2.0% and a maximum of 3.0%, calculated as per B.1.9</p>
        <p>Where collective bargaining is concluded after June 30, 2022, retroactivity of general wage increases will be applied as follows:</p>
        <p>Teachers employed on the date of ratification and who were employed on July 1, 2022 shall receive retroactive payment of wages to July 1, 2022.</p>
        <p>Teachers hired after July 1, 2022 and who were employed on the date of ratification, shall have their retroactive pay pro-rated from their date of hire to the date of ratification.</p>
        <p>Teachers who retired between July 1, 2022 and the date of ratification, shall have their retroactive pay pro-rated from July 1, 2022 to their date of retirement.</p>
        <p>The following allowances shall be adjusted in accordance with the percentage increases in B.1.1 above:</p>
        <p>Department Head</p>
        <p>Positions of Special Responsibility</p>
        <p>First Aid</p>
        <p>One-Room School</p>
        <p>Isolation and Related Allowances</p>
        <p>Moving/Relocation</p>
        <p>Recruitment &amp; Retention</p>
        <p>Mileage/Auto not to exceed the CRA maximum rate</p>
        <p>The following allowances shall not be adjusted by the percentage increases in B.1.1 above:</p>
        <p>Per Diems</p>
        <p>Housing</p>
        <p>Pro D (unless formula-linked to the grid)</p>
        <p>Clothing</p>
        <p>Classroom Supplies</p>
        <p>Effective July 1, 2022, each local salary grid shall be restructured to eliminate the first step of each grid.</p>
        <p>Effective July 1, 2023, the local salary grids are amended to provide a 0.3% increase to the top step of the salary grid.</p>
        <p>Effective July 1, 2024, the local salary grids are amended to provide a 0.11% increase to the top step of the salary grid.</p>
        <p>Teachers Teaching on Call (TTOCs) on the first step of the salary grid, who accept a contract will be paid at the second step of the salary grid for the term of the contract. Temporary/term contract and continuing employees will be placed on the second step of the grid or at a higher step in accordance with the local placement on the scale provisions.</p>
        <p>2023 and 2024 Cost of Living Adjustments (COLA)</p>
        <p>The provincial parties agree that in determining the level of any Cost of Living Adjustments (COLAs) that will be paid out starting on the first pay period after July 1, 2023 and July 1, 2024, respectively, the &quot;annualized average of BC CPI over twelve months” in B.1.1 means the Latest 12-month Average (Index) % Change reported by BC Stats in March for British Columbia for the twelve months starting at the beginning of March the preceding year and concluding at the end of the following February. The percentage change reported by BC Stats that will form the basis for determining any COLA increase is calculated to one decimal point. The Latest 12-month Average Index, as defined by BC Stats, is a 12-month moving average of the BC consumer price indexes of the most recent 12 months. This figure is calculated by averaging index levels over the applicable 12 months.</p>
        <p>The Latest 12-month Average % Change is reported publicly by BC Stats in the monthly BC Stats Consumer Price Index Highlights report. The BC Stats Consumer Price Index Highlights report released in mid-March will contain the applicable figure for the 12 months concluding at the end of February.</p>
        <p>For reference purposes only, the annualized average of BC CPI over twelve months from March 1, 2021 to February 28, 2022 was 3.4%.</p>
      </div>

      <div class="ca-article" id="article-b-2-ttoc-call-pay-and-benefits">
        <h3>ARTICLE B.2:TTOC CALL PAY AND BENEFITS</h3>
        <p>1.The employer will ensure compliance with vacation provisions under the Employment Standards Act in respect of the payment of vacation pay.</p>
        <p>2.For the purposes of Employment Insurance, the employer shall report for a Teacher Teaching on Call (TTOC), the same number of hours worked as would be reported for a day worked by a teacher on a continuing contract.</p>
        <p>3.A TTOC shall be entitled to the mileage/kilometre allowance, rate or other payment for transportation costs, as defined by the Collective Agreement, for which the employee they are replacing is entitled to claim.</p>
        <p>4.TTOCs shall be eligible, subject to plan limitations, to participate in the benefit plans in the Collective Agreement, provided that they pay the full cost of benefit premiums.</p>
        <p>5.TTOCs shall be paid an additional compensation of $11 over daily rate in lieu of benefits. This benefit will be prorated for part days worked but in no case will be less than $5.50. Any and all provisions in the Previous Collective Agreement that provided additional or superior provisions in respect of payment in lieu of benefits shall remain part of the Collective Agreement.</p>
        <p>6.Rate of Pay:</p>
        <p>An Employee who is employed as a TTOC shall be paid 1/189 of their category classification and experience, to a maximum of the rate at Category 5 Step 8, for each full day worked.</p>
        <p>LOCAL PROVISIONS</p>
        <p>B.2.7Rate of Pay</p>
        <p>The rate of pay will be as follows:</p>
        <p>a.Teachers-Teaching-On-Call who hold a valid B.C. Teaching Certificate shall be paid in accordance with B.2.6.</p>
        <p>b.Employees who do not have a valid B.C. Teaching Certificate shall receive the following rates:</p>
        <p>i.on the first and up to and including the fifth consecutive teaching day of any one assignment the rate shall be:</p>
        <p>ii.on the sixth day and up to and including the twentieth consecutive teaching day on any one assignment the rate shall be:</p>
        <p>c.Teachers-Teaching-On-Call who replace part-time teachers shall be paid on a pro-rated basis on the above rates for the percentage of hours taught during a teaching day.</p>
        <p>d.A teaching day for the purpose of this section shall mean a day, or part of a day for those replacing part-time teachers, or attendance for instruction in the classroom.</p>
        <p>e.Non-instructional days shall be counted and paid for as a teaching day only from the twenty-first and subsequent consecutive teaching days on any one assignment; notwithstanding the generality of the foregoing, a Teacher-Teaching-On-Call may be requested to attend a non-instructional day prior to the twenty-first teaching day in which case the day shall be paid for and counted as a teaching day. Service shall not be considered broken by a non-instructional day.</p>
        <p>f.All rates of pay in all categories shall include holiday pay.</p>
        <p>g.It shall be the responsibility of the Teacher-Teaching-On-Call to provide proof of their teacher certification and teaching experience to the Board prior to accepting a Teacher-Teaching-On-Call assignment. If such proof is not presented to the Board the Teacher-Teaching-On-Call will be placed on the rate of an uncertified employee as per Article B.2.7.b.</p>
        <p>h.A Teacher-Teaching-On-Call shall be paid sixty (60) percent of a day’s pay for being called out for a morning assignment and forty (40) percent when called out for an afternoon assignment.</p>
        <p>i.A Teacher-Teaching-On-Call shall be paid fifty (50) percent of a day&#x27;s pay for each Kindergarten session taught.</p>
        <p>j.A Teacher-Teaching-On-Call’s service shall not be considered broken by a Professional Development day.</p>
        <p>B.2.8Sick Leave</p>
        <p>Sick leave provisions, in accordance with Article G.22 of this Agreement, shall become an entitlement from the twenty-first and subsequent consecutive teaching days on any one assignment. The qualifying period shall be calculated from the first day of the assignment.</p>
        <p>B.2.9Experience Credit</p>
        <p>a.Certified Teachers-Teaching-On-Call accruing one hundred and sixty (160) days of teacher teaching on call time will be granted one (1) increment, payable on the next increment date, subject to the provision by the Teacher-Teaching-On-Call of acceptable proof of substitute time in School District #54 (Bulkley Valley). This clause will affect substitute days taught after January 1, 1986, or earlier if substantiated by the Teacher-Teaching-On-Call, and prior to September 19, 2014. Acceptable proof of teaching time will require that the Teacher-Teaching-On-Call produce statements of earnings and deductions as supplied with each pay cheque.</p>
        <p>b.Effective September 19, 2014, Teacher-Teaching-On-Call experience credit and increments will be accrued and awarded in accordance with Article C.4 Teacher Teaching On Call Employment.</p>
      </div>

      <div class="ca-article" id="article-b-3-salary-determination-for-employees-in-adult-education">
        <h3>ARTICLE B.3:SALARY DETERMINATION FOR EMPLOYEES IN ADULT EDUCATION</h3>
        <p>[Article B.3 is not applicable in School District #54 (Bulkley Valley).]</p>
      </div>

      <div class="ca-article" id="article-b-4-ei-rebate">
        <h3>ARTICLE B.4:EI REBATE</h3>
        <p>1.The employer shall remit monthly to the BCTF Salary Indemnity Fund the proportionate share of the employment insurance premium reduction set out in the Previous Local Agreement. Where the proportionate share is not expressed in the Previous Local Agreement, the employer shall remit monthly to the BCTF Salary Indemnity Fund an amount consistent with the past practice of the local parties. The amount remitted on behalf of any employee shall not be less than 5/12 of said reduction.</p>
        <p>The employer shall calculate each employee’s share of the savings which have been remitted pursuant to Article B.4.1 above and include that amount as part of the employee’s taxable income on the yearly T4 slip.</p>
      </div>

      <div class="ca-article" id="article-b-5-registered-retirement-savings-plan">
        <h3>ARTICLE B.5:REGISTERED RETIREMENT SAVINGS PLAN</h3>
        <p>In this Article:</p>
        <p>a.“the BCTF Plan” means the Group RRSP entered into by the Federation and Royal Trust or a successor to that plan; [Applicable in School District #54 (Bulkley Valley).]</p>
        <p>b.“alternative plan” means a group RRSP, including the BCTF Plan, which was entered into prior to the coming into force of this Article, and which is still in effect as of that date. [Not Applicable in School District #54 (Bulkley Valley).]</p>
        <p>Where an alternative plan exists in a district pursuant to Article B.5.1.b that plan shall remain in effect.</p>
        <p>The BCTF Plan shall be made available in all districts not included in Article B.5.2.</p>
        <p>The employer shall deduct from the monthly salary of employees, as at the end of the month following enrollment, contributions in a fixed dollar amount specified by the employee on behalf of any employee who elects to participate in the BCTF Plan. The employer shall remit these amounts to the designated trustee no later than the 15th of the month following the month in which the deduction is made.</p>
        <p>The employer shall make available, to present employees on request and to new employees at the time of hire, enrollment forms and other forms required for participation in the BCTF Plan. Completed forms shall be processed and forwarded to the designated trustee by the employer.</p>
        <p>If in any month, an employee is not in receipt of sufficient net pay to cover the monthly payroll deduction amount for any reason, the contribution to the BCTF Plan for that employee shall not be made for that month. If the employee wishes to make up any missed contribution(s), the employee shall make arrangements for same directly with the designated trustee.</p>
        <p>Employees shall have the opportunity to enroll or re-enroll in the BCTF Plan as follows:</p>
        <p>a.between September 1 and September 30 or December 15 and January 15 in any school year;</p>
        <p>b.no later than sixty (60) days following the commencement of employment.</p>
        <p>An employee may withdraw from participation in the BCTF Plan where they have provided thirty (30) days’ written notice to the employer.</p>
        <p>There shall be no minimum monthly or yearly contribution required of any employee who participates in the BCTF Plan.</p>
        <p>Participating employees may vary the amount of their individual contributions to the BCTF Plan on either or both of October 31 and January 31 in any school year, provided that written notice of such change has been provided to the employer no later than September 30 for changes to be effective October 31, and December 31 for changes to be effective January 31.</p>
        <p>The BCTF Plan established in a district pursuant to Article B.5.3 shall be made available to employees on a continuing contract of employment and employees on term or temporary contracts of employment as defined in the Previous Local Agreement.</p>
      </div>

      <div class="ca-article" id="article-b-6-salary-indemnity-plan-allowance">
        <h3>ARTICLE B.6:SALARY INDEMNITY PLAN ALLOWANCE</h3>
        <p>The employer shall pay monthly to each employee eligible to participate in the BCTF Salary Indemnity Plan an allowance equal to 2.0% of salary earned in that month to assist in offsetting a portion of the costs of the BCTF Salary Indemnity Plan.</p>
        <p>In paying this allowance, it is understood that the employer takes no responsibility or liability with respect to the BCTF Salary Indemnity Plan.</p>
        <p>The BCTF agrees not to alter eligibility criteria under the Plan to include groups of employees not included as of July 1, 2006.</p>
      </div>

      <div class="ca-article" id="article-b-7-reimbursement-for-personal-property-loss">
        <h3>ARTICLE B.7:REIMBURSEMENT FOR PERSONAL PROPERTY LOSS</h3>
        <p>1.Private Vehicle Damage</p>
        <p>Where an employee’s vehicle is damaged by a student at a worksite or an approved school function, or as a direct result of the employee being employed by the employer, the employer shall reimburse the employee the lesser of actual vehicle damage repair costs, or the cost of any deductible portion of insurance coverage on that vehicle up to a maximum of $600.</p>
        <p>2.Personally Owned Professional Material</p>
        <p>The employer shall reimburse an employee to a maximum of $150 for loss, damage or personal insurance deductible to personally owned professional material brought to the employee’s workplace to assist in the execution of the employee’s duties, provided that:</p>
        <p>The loss or damage is not the result of negligence on the part of the employee claiming compensation;</p>
        <p>b.The claim for loss or damage exceeds ten (10) dollars;</p>
        <p>c.If applicable, a copy of the claim approval from their insurance carrier shall be provided to the employer;</p>
        <p>The appropriate Principal or Vice-Principal reports that the loss was sustained while on assignment for the employer.</p>
        <p>Note: Any and all superior or additional provisions contained in the Previous Collective Agreement shall remain part of the Collective Agreement</p>
      </div>

      <div class="ca-article" id="article-b-8-optional-twelve-month-pay-plan">
        <h3>ARTICLE B.8:OPTIONAL TWELVE-MONTH PAY PLAN</h3>
        <p>[Articles B.8.1 to B.8.10 are not applicable in School District #54 (Bulkley Valley).  See Article B.8.11.]</p>
        <p>LOCAL PROVISIONS</p>
        <p>B.8.11Twelve Month Salary Payments</p>
        <p>a.At their option, teachers may elect to defer 2/12 of their net pay on a monthly basis for ten (10) months, which will be paid out in four (4) equal payments during July and August.</p>
        <p>b.To take advantage of this option, teachers must apply in writing prior to September 15 of the school year. In the event a teacher wishes to revert to 10 monthly payments in any following school year, notice of this intent must be provided before September 15 of the new school year.</p>
      </div>

      <div class="ca-article" id="article-b-9-pay-periods">
        <h3>ARTICLE B.9:PAY PERIODS</h3>
        <p>[Articles B.9.1 to B.9.3 are not applicable in School District #54 (Bulkley Valley).  See Article B.9.4.]</p>
        <p>LOCAL PROVISIONS</p>
        <p>B.9.4Mid-Month Advance</p>
        <p>a.There shall be a mid-month advance of each teacher&#x27;s salary paid on the 16th of each month in an amount equal to approximately one-half the teacher&#x27;s net salary. Each teacher shall receive notice of this advance each September and January.</p>
      </div>

      <div class="ca-article" id="article-b-10-reimbursement-for-mileage-and-insurance">
        <h3>ARTICLE B.10:REIMBURSEMENT FOR MILEAGE AND INSURANCE</h3>
        <p>1.[PCA Article B.10.1 is not applicable in School District #54 (Bulkley Valley).  See Article B.10.6 below.]</p>
        <p>2.The mileage reimbursement rate established in Article B.10.1 shall be increased by $0.05/kilometre for travel that is approved and required on unpaved roads.</p>
        <p>3.The employer shall reimburse an employee who is required to use their personal vehicle for school district purposes, the difference in premium costs between ICBC rate Class 002 (Pleasure to/from Work) and ICBC rate Class 007 (Business Class) where the employee is required to purchase additional insurance in order to comply with ICBC regulations respecting the use of one’s personal vehicle for business purposes.</p>
        <p>[Article B.10.4 is not applicable in School District #54 (Bulkley Valley).]</p>
        <p>Note: Any and all superior or additional provisions contained in the Previous Collective Agreement shall remain part of the Collective Agreement.</p>
        <p>LOCAL PROVISIONS</p>
        <p>B.10.6Mileage Allowance</p>
        <p>a.Teachers who use their personal vehicle on approved Board business shall be reimbursed at the standard School District rate.</p>
        <p>b.Teachers who are transferred by the Board to assignments involving duties in more than one school shall receive mileage allowance.</p>
      </div>

      <div class="ca-article" id="article-b-11-benefits">
        <h3>ARTICLE B.11:BENEFITS</h3>
        <p>The employer will provide the Provincial Extended Health Benefit Plan as set out in Appendix A to Letter of Understanding No. 9.</p>
        <p>The employer shall provide the local with a copy of the group benefits contract in effect for the Provincial Extended Health Benefit Plan and shall provide the local with a copy of the financial/actuarial statements made available to the employer from the benefit provider.</p>
        <p>Teachers Teaching on Call (TTOCs) shall have access to the Provincial Extended Health Benefit Plan. TTOCs accessing the Plan shall pay 100 percent (100%) of the premium costs.</p>
        <p>The Provincial Extended Health Benefit Plan shall allow for dual coverage and the co-ordination of benefits.</p>
        <p>Note: this language applies only where the local union has voted to adopt the Provincial Extended Health Benefit Plan.</p>
        <p>Local Provisions</p>
        <p>B.11.5General Benefits</p>
        <p>a.The Board shall provide each employee with an application or enrollment form for participation in the medical, dental, extended health and group life insurance benefit plans. In the event an employee does not wish to participate in any particular benefit plan where opting out is an option, the application or enrollment form must be so noted by the teacher and kept on file by the Board.</p>
        <p>b.The Board shall advise each employee by letter at the end of October, and all employees hired subsequent to that date at the end of the first month of employment, of those benefit plans available to employees, the cost of those plans, and of those plans in which the employee is enrolled.</p>
        <p>c.The Board shall advise each employee in writing at the end of September, December and March of their accumulated sick leave.</p>
        <p>d.The Board shall ensure that benefits begin from the starting date of employment.</p>
        <p>e.Benefit coverage shall be extended to the end of the teaching month in which employment ends and at the end of August when employment ends at the end of the school year.</p>
        <p>f.The Board shall notify all part time teachers and Teachers-Teaching-On-Call that they are required to contribute to the Teachers&#x27; Pension Plan.</p>
        <p>B.11.6Medical Benefits</p>
        <p>a.Medical Services Plan</p>
        <p>Teachers not otherwise covered by a medical services plan may become members of the Medical Services Plan of B.C. The Board shall pay 100% of the premium cost.</p>
        <p>b.Extended Health Care</p>
        <p>The Board shall pay 100% of the premium cost of the Provincial Extended Health Benefit Plan. The Board shall pay 100% of the premium cost of a supplemental travel rider.</p>
        <p>c.Dental Plan</p>
        <p>The Board shall pay 80% of the premium cost of a mutually agreed upon Dental Plan. Participation in the plan is a condition of employment for eligible teachers commencing employment on or after September of 1990. The plan shall provide the following coverage:</p>
        <p>Plan &quot;A&quot; 90%</p>
        <p>Plan &quot;B&quot; 70%</p>
        <p>Plan &quot;C&quot; 60% with a lifetime maximum of $2500.00. Effective July 1, 2015, Plan C coverage is 75% with a lifetime maximum of $5000.00.</p>
        <p>d.Group Life Insurance</p>
        <p>The Board shall pay 100% of the premiums of a mutually agreed upon plan equivalent to the BCSTA/BCTF Group Insurance Plan &quot;B&quot;. Participation in the plan will be a condition of employment.</p>
        <p>e.Optional Term Life</p>
        <p>The Board will take applications and make the required deductions for the Voluntary Group Life Insurance plan. Teachers new to the district may apply for the plan upon employment; all other teachers may apply for the plan in September.</p>
        <p>f.Benefits During Leave</p>
        <p>i.The Board and teacher shall continue to contribute their respective shares of the cost of maintaining coverage under B.C. Medical Services Plan, the Extended Health Benefits Plan, BCTF/BCSTA Group Life Insurance, and Dental Plan, where applicable, during the period a teacher is on medical leave of absence to a maximum of one (1) year after expiration of statutory sick leave.</p>
        <p>ii.A teacher on an approved leave of absence shall be entitled to the Benefit Articles of this Agreement provided that the teacher pay 100% of the premiums.</p>
        <p>g.Death Benefits</p>
        <p>In the event of a teacher&#x27;s death the benefits package of that teacher will remain in effect under the existing circumstances for four months following the month in which the death occurs and salary will continue for two months from the date of death.</p>
      </div>

      <div class="ca-article" id="article-b-12-category-5">
        <h3>ARTICLE B.12:CATEGORY 5+</h3>
        <p>Eligibility for Category 5+</p>
        <p>An employee with a Teacher Qualification Service (TQS) Category 5 and an additional 30 semester credits, or equivalent, as accepted by TQS;</p>
        <p>i.Credits must be equivalent to standards in British Columbia’s public universities in the opinion of the TQS.</p>
        <p>ii.Credits must be in no more than two (2) areas of study relevant to the British Columbia public school system.</p>
        <p>iii.At least 24 semester credits of the total requirement of 30 semester credits, or equivalent, must be completed at the senior level.</p>
        <p>Post undergraduate diplomas agreed to by the TQS; or</p>
        <p>Other courses or training recognized by the TQS.</p>
        <p>Criteria for Category 5+</p>
        <p>The eligibility requirements pursuant to Article B.12.1 must not have been used to obtain Category 5.</p>
        <p>3.Salary Rate Calculation</p>
        <p>a.Category 5+ shall be seventy-four percent (74%) of the difference between Category 5 and Category 6 except where a superior salary rate calculation remained as at March 31, 2006 and/or during the term of the 2006-2011 Provincial Collective Agreement.</p>
        <p>4.Application for Category 5+</p>
        <p>a.BCPSEA and the BCTF agree that the TQS shall be responsible for the evaluation of eligibility and criteria for Category 5+ pursuant to Article B.12.1 and Article B.12.2 and the assignment of employees to Category 5+.</p>
        <p>b.BCPSEA and the BCTF agree that disputes with respect to the decisions of TQS made pursuant to Article B.12.1 and Article B.12.2 shall be adjudicated through the TQS Reviews and Appeals processes and are not grievable.</p>
      </div>

      <div class="ca-article" id="article-b-13-board-payment-of-speech-language-pathologists-and-school-psychologists-professional-fees">
        <h3>ARTICLE B.13:BOARD PAYMENT OF SPEECH LANGUAGE PATHOLOGISTS’ AND SCHOOL PSYCHOLOGISTS’ PROFESSIONAL FEES</h3>
        <p>Each Board of Education shall pay, upon proof of receipt, fees required for annual Professional Certification required to be held for employment by School Psychologists and Speech Language Pathologists.</p>
      </div>

      <div class="ca-article" id="article-b-14-experience-recognition">
        <h3>ARTICLE B.14:EXPERIENCE RECOGNITION</h3>
        <p>Effective July 1, 2022 employees who have worked as a teacher (or in a BCTF bargaining unit equivalent position) in British Columbia while employed by:</p>
        <p>a First Nation, as defined in section 1 of the School Act, that is operating a school;</p>
        <p>a Community Education Authority, as established by one or more participating First Nations under the First Nations Jurisdiction over Education in British Columbia Act (Canada), that is operating a school; or</p>
        <p>a treaty First Nation that is operating a school under the treaty First Nation’s laws;</p>
        <p>shall receive credit for their work experience for the purposes of placement on the salary scale.</p>
        <p>LOCAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-b-21-salary-schedule-placement">
        <h3>ARTICLE B.21:SALARY SCHEDULE PLACEMENT</h3>
        <p>B.21.1Initial Placement</p>
        <p>a.Placement on scale shall be determined in accordance with the category assigned by the Teacher Qualification Service and in accordance with years of experience as determined by Article B.21.2 of this Agreement.</p>
        <p>b.At the time of appointment, the Board shall advise the teacher, in writing, of the documentation required to establish initial scale placement; the requirement to advise the Board if any delay is expected in meeting deadlines, and the procedures for redesignation and appeal of any decision with respect to scale placement.</p>
        <p>c.Each teacher shall submit all documentation required by the Board to establish salary placement. Such documentation shall be submitted within three (3) months of commencement of employment or change in categorization or certification. The teacher shall be responsible for advising the Board, in writing, if any delays which occur in obtaining the documentation necessitate an extension of the limits.</p>
        <p>d.The Board shall not refuse a request for extension of the time limits.</p>
        <p>e.The actual change in category will occur upon verification by the Teacher Qualification Service.</p>
        <p>f.The effective date of such change shall be the first of the month in which the teacher notifies the Secretary Treasurer of the Board, in writing, that they have completed the requirements for the category change, or the effective date of the certificate granted by the Teacher Qualification Service, whichever is later.</p>
        <p>g.Part time teachers shall be paid on a pro-rata basis according to their fraction of full time and shall be entitled to full benefits.</p>
        <p>B.21.2Experience Recognition</p>
        <p>a.Experience as determined below shall govern placement of all teachers in their salary categories.</p>
        <p>b.One year&#x27;s teaching experience will be defined as a minimum of the full time equivalent of eight (8) months continuous teaching in one (1) school year or calendar year.</p>
        <p>c.Periods of part-time, Teacher-Teaching-On-Call within School District No. 54 from January 1, 1986 or earlier if substantiated by the teacher, and short term appointments may be added together for accumulation of years of experience credit.</p>
        <p>d.Part-time teaching shall be pro-rated to full-time equivalent.</p>
        <p>e.All teaching experience shall be recognized and credited for placement on the salary schedule.</p>
        <p>f.Teachers with certification in industrial subjects, who are teaching more than 50% of their time in industrial subjects, shall be granted 50% of journeyperson&#x27;s experience subject to the provisions of such other clauses of this Agreement as may apply. The maximum payable is the maximum of the teacher&#x27;s category.</p>
        <p>g.A teacher who was granted experience recognition under this provision and who is no longer teaching the required proportion of time in the subject shall not have such recognition withdrawn but shall remain at the same experience step until regular experience recognition brings about an advancement, all of this provided the change in assignment was not at the teacher&#x27;s request.</p>
        <p>h.Experience credit shall be earned for:</p>
        <p>i.Secondment to the BVTU, the British Columbia Teachers&#x27; Federation, or the Canadian Teachers&#x27; Federation.</p>
        <p>ii.Secondment to the Ministry of Education.</p>
        <p>iii.Secondment to a recognized university or college.</p>
        <p>iv.Secondment to the Teacher Regulation Branch.</p>
        <p>v.Service with Canadian University Service Overseas or the Canadian International Development Agency.</p>
        <p>vi.Absence while on paid statutory sick leave, extended leave or WCB leave.</p>
        <p>vii.Absence while on maternity leave.</p>
        <p>viii.Absence while serving as the President of the BVTU.</p>
        <p>ix.Absence while on approved educational leave.</p>
        <p>B.21.3Increment Dates</p>
        <p>a.Increment dates shall be effective on the first of the month following the month in which an increment is earned. A teacher can not earn more than one increment in a twelve (12) month period.</p>
        <p>B.21.4Letters of Permission</p>
        <p>a.Persons with Letters of Permission shall be placed and held (except as provided in Article B.21.4.b) at category 4-0 for those persons lacking a University degree and at 4-0 for those persons with a University degree which the Teacher Regulation Branch does not recognize for certification purposes. Upon attainment of a regular certification, credit will be granted to a teacher, on the increment pattern, for years of teaching experience calculated in accordance with Article B.21.2 (Experience Recognition) while teaching on a Letter of Permission.</p>
        <p>b.An increment shall be granted for the first 4 steps on the grid for letter of permission teachers. Additional increments beyond step 4 shall be granted when a teacher has earned 3 credits for each year of service beyond step 4.</p>
      </div>

      <div class="ca-article" id="article-b-22-salary">
        <h3>ARTICLE B.22:SALARY</h3>
        <p>B.22.1Explanation of TQS Categories</p>
        <p>B.22.2No Cut</p>
        <p>a.No teacher shall suffer a reduction in salary or benefits as a result of implementation of this contract.</p>
        <p>B.22.3Part Payment</p>
        <p>a.The daily rate and the formula for calculating the daily rate shall be defined as 1/200 of the current annual salary of the employee.</p>
        <p>b.Whenever a salary deduction or adjustment is calculated on a daily basis it shall be on the basis of the appropriate daily rate of the employee&#x27;s existing annual salary at the time of absence.</p>
        <p>c.For teachers commencing after the first day in the school year their first month shall be calculated for days taught in the month in accordance with the appropriate daily rate. Each subsequent month shall be &quot;on&quot; scale as provided by the salary schedule.</p>
        <p>d.Teachers leaving the district&#x27;s service before the last teaching day in a month shall be paid on a pro rata basis according to the number of teaching days in that month.</p>
        <p>e.When an employee is employed on a less than full-time basis, and that employee is required by the Board to work beyond their normal hours of employment, that employee shall be remunerated in accordance with their daily rate.</p>
        <p>f.Teachers who are going on an approved leave without pay before the end of the month will be deducted at the appropriate daily rate for all regularly scheduled teaching days remaining in that month.</p>
        <p>B.22.4Salary Grid Increased Pursuant to Article B.1</p>
        <p>SCHOOL DISTRICT #54 (BULKLEY VALLEY) TEACHER GRIDS</p>
        <p>JULY 1, 2022 – JUNE 30, 2023</p>
        <p>JULY 1, 2023 – JUNE 30, 2024</p>
        <p>JULY 1, 2024 – JUNE 30, 2025</p>
      </div>

      <div class="ca-article" id="article-b-23-allowances">
        <h3>ARTICLE B.23:ALLOWANCES</h3>
        <p>[See also LOU No. 5 for the Remote &amp; Rural Allowance payable effective July 1, 2008.]</p>
        <p>B.23.1Designated Teacher in Charge</p>
        <p>a.A teacher assigned the responsibilities of an administrator on a replacement basis during the absence of the administrator will receive an allowance of 15% of that teacher&#x27;s salary on a daily basis. A teacher teaching on call will be provided if required.</p>
        <p>B.23.2Department Head --Release Time</p>
        <p>a.Department Heads shall receive 12 1/2 percent release time to fulfill the responsibilities of the position.</p>
      </div>

      <div class="ca-article" id="article-b-24-positions-of-special-responsibility">
        <h3>ARTICLE B.24:POSITIONS OF SPECIAL RESPONSIBILITY</h3>
        <p>B.24.1The Board, in consultation with the BVTU, will draw up job descriptions for all Positions of Special Responsibility. These descriptions shall be the recognized job descriptions for such positions.</p>
        <p>B.24.2The Board, in consultations with the BVTU, shall prepare a new job description whenever a new position of Responsibility is created or whenever the duties of any such position are changed or increased. When such a position is created or changed, any allowance to be paid shall be subject to negotiations between the Board and the Teachers&#x27; Union.</p>
        <p>B.24.3Existing Positions of Special Responsibility shall not be eliminated or changed substantially without prior consultation with the BVTU, and all such positions shall be considered voluntary.</p>
        <p>SECTION CEMPLOYMENT RIGHTS</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section C: Employment Rights</h2>
      <div class="ca-article" id="article-c-1-resignation">
        <h3>ARTICLE C.1:RESIGNATION</h3>
        <p>An employee may resign from the employ of the employer on thirty (30) days’ prior written notice to the employer or such shorter period as mutually agreed. Such agreement shall not be unreasonably denied.</p>
        <p>The employer shall provide the local with a copy of any notice of resignation when it is received.</p>
      </div>

      <div class="ca-article" id="article-c-2-seniority">
        <h3>ARTICLE C.2:SENIORITY</h3>
        <p>Except as provided in this article, “seniority” means an employee’s aggregate length of service with the employer as determined in accordance with the provisions of the Previous Collective Agreement.</p>
        <p>Porting Seniority</p>
        <p>Despite Article C.2.1 above, an employee who achieves continuing contract status in another school district shall be credited with up to twenty (20) years of seniority accumulated in other school districts in B.C.</p>
        <p>Seniority Verification Process</p>
        <p>i.The new school district shall provide the employee with the necessary verification form at the time the employee achieves continuing contract status.</p>
        <p>ii.The employee must initiate the seniority verification process and forward the necessary verification forms to the previous school district(s) within one hundred and twenty (120) days of receiving a continuing appointment in the new school district.</p>
        <p>iii.The previous school district(s) shall make every reasonable effort to retrieve and verify the seniority credits which the employee seeks to port.</p>
        <p>Teacher Teaching on Call (TTOC)</p>
        <p>A TTOC shall accumulate seniority for days of service which are paid pursuant to Article B.2.6.</p>
        <p>For the purpose of calculating seniority credit:</p>
        <p>i.Service as a TTOC shall be credited:</p>
        <p>1.one half (1/2) day for up to one half (1/2) day worked;</p>
        <p>2.one (1) day for greater than one half (1/2) day worked up to one (1) day worked.</p>
        <p>ii.Nineteen (19) days worked shall be equivalent to one (1) month;</p>
        <p>iii.One hundred and eighty-nine (189) days shall be equivalent to one (1) year.</p>
        <p>Seniority accumulated pursuant to Article C.2.3.a and C.2.3.b, shall be included as aggregate service with the employer when a determination is made in accordance with Article C.2.1.</p>
        <p>An employee on a temporary or term contract shall accumulate seniority for all days of service on a temporary or term contract.</p>
        <p>No employee shall accumulate more than one (1) year of seniority credit in any school year.</p>
        <p>LOCAL PROVISIONS</p>
        <p>C.2.7Principle of Security</p>
        <p>a.The Board and the BVTU agree that increased length of service in the employment of the Board entitles teachers to commensurate increase in security of teaching employment.</p>
        <p>C.2.8Definition of Seniority</p>
        <p>a.In this Article, seniority means a teacher&#x27;s aggregate length of service with the Board inclusive of service under temporary appointment and part time teaching.</p>
        <p>b.For the purpose of calculating length of service part time teaching shall be credited as if it were full-time service.</p>
        <p>c.Where a teacher is appointed as an Administrative Officer in the School District and is subsequently offered a teaching position in that School District, they shall, for the purposes of seniority only, be deemed to have been a teacher in the School District both during the period that they were employed as an Administrative Officer and during the period that they were employed as a teacher in the School District.</p>
        <p>d.In addition to the provisions of Article C.2.8.a through C.2.8.c, the seniority for a teacher on a continuing contract shall include:</p>
        <p>Teacher-Teaching-On-Call seniority accumulated pursuant to PCA Article C.2.3; and</p>
        <p>seniority ported in accordance with PCA Article C.2.2 provided that in no case, shall a teacher be credited with more than one (1) year of seniority for any school year.</p>
        <p>C.2.9Interpretation</p>
        <p>a.When the seniority of two or more teachers is equal pursuant to Article C.2.8 above, the teacher with the greatest present continuous employment with the Board shall be deemed to have the greatest seniority.</p>
        <p>b.Only for the purposes of this Article, when the seniority of two or more teachers is equal pursuant to Article C.2.9.a, the teacher with the greatest number of days of Teacher-Teaching-On-Call service with the Board since January 1, 1986 shall be deemed to have the greatest seniority.</p>
        <p>c.When the seniority of two or more teachers is equal pursuant to Article C.2.9.b, the teacher with the earliest date of acceptance of employment with the Board shall be deemed to have the greatest seniority.</p>
        <p>d.When service is equal according to Article C.2.9.a and C.2.9.b and C.2.9.c the teacher with the greatest service recognized for increment purposes shall be deemed to have the greatest seniority.</p>
        <p>e.For the purpose of this Article, leaves of absence granted by the Board in excess of one month shall not count toward length of service with the Board, except:</p>
        <p>i.maternity leave;</p>
        <p>ii.educational leave;</p>
        <p>iii.parenthood leave;</p>
        <p>iv.leave for duties with the BVTU or the British Columbia Teachers&#x27; Federation, the Teacher Regulation Branch, or the Canadian Teachers Federation;</p>
        <p>v.secondment to the Ministry of Education, a Faculty of Education, or pursuant to a recognized teacher exchange program;</p>
        <p>vi.long term sick leave;</p>
        <p>vii.leave for teaching with the Department of National Defence or Canadian Universities Service Overseas or similar organization.;</p>
        <p>viii.elected office at the municipal, provincial or federal level;</p>
        <p>ix.extended compassionate leave and Compassionate Care Leave (PCA Article G.2).</p>
        <p>f.Any approved leave of absence shall preserve continuity of service, but shall not add to seniority, except in accordance with Article C.2.9.e above.</p>
        <p>g.A teacher terminated and subsequently re-hired while covered by this Agreement shall be deemed to have unbroken service for seniority purposes but time of layoff shall not contribute to seniority.</p>
        <p>C.2.10Seniority List</p>
        <p>a.The Board shall, by October 15 of each year, forward to the BVTU a list of all teachers employed by the Board, in order of seniority calculated according to Article C.2.8, setting out the length of seniority as of September 1 of that year.</p>
        <p>b.The Board shall maintain a re-engagement list which will be available to members of the BVTU. When the list changes, or when positions have been filled, all members on the list will be so advised, along with the President of the BVTU. It is the obligation of each person on the list to keep the Board advised of their proper mailing address.</p>
        <p>PROVINCIAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-c-3-evaluation">
        <h3>ARTICLE C.3:EVALUATION</h3>
        <p>1.The purposes of evaluation provisions include providing employees with feedback, and employers and employees with the opportunity and responsibility to address concerns. Where a grievance proceeds to arbitration, the arbitrator must consider these purposes, and may relieve on just and reasonable terms against breaches of time limits or other procedural requirements.</p>
        <p>[Note: See also Article E.24 Evaluation of Teachers Effectiveness]</p>
      </div>

      <div class="ca-article" id="article-c-4-ttoc-employment">
        <h3>ARTICLE C.4:TTOC EMPLOYMENT</h3>
        <p>1.Experience Credit</p>
        <p>For the purpose of this article, a Teacher Teaching on Call (TTOC) shall be credited with one (1) day of experience for each full-time equivalent day worked.</p>
        <p>One hundred seventy (170) full-time equivalent days credited shall equal one (1) year of experience.</p>
        <p>2.Increment Date for Salary Grid Placement</p>
        <p>Upon achieving one (1) year of experience, an increment shall be awarded on the first of the month following the month in which the experience accumulation is earned.</p>
        <p>[Note: see also B.2.9 Experience Credit, B.21.2 Experience Recognition and LOU 16 (a), (b), (c) and Forms A and B of LOU 16 (c)]</p>
        <p>LOCAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-c-21-layoff-re-engagement-severance-pay">
        <h3>ARTICLE C.21:LAYOFF, RE-ENGAGEMENT &amp; SEVERANCE PAY</h3>
        <p>C.21.1Definition of Qualifications</p>
        <p>a.In this Article, &quot;necessary qualifications&quot; in respect of a teaching position means a reasonable expectation, based on the teaching certification, training, education, experience and capability of a teacher that that teacher will be able to perform duties of the position in a satisfactory manner, following a reasonable period of familiarization.</p>
        <p>C.21.2Security of Employment based on Seniority and Qualifications</p>
        <p>a.When the Board determines, for educational or budgetary reasons, that it is necessary to reduce the total number of teachers employed on a continuing contract by the Board, the teachers to be retained on the teaching staff of the District shall be those who have the greatest seniority, provided that they possess the necessary qualifications for the positions available.</p>
        <p>C.21.3Definition of Termination or Layoff</p>
        <p>a.For the purposes of this Article &quot;termination&quot; (or &quot;layoff&quot;) includes:</p>
        <p>i.the termination of teachers on a continuing contract,</p>
        <p>ii.the termination of a temporary contract teacher prior to the end of the term of the contract,</p>
        <p>C.21.4Notice Period</p>
        <p>a.The Board shall give each teacher it intends to terminate pursuant to this Agreement (as early as possible but no less than forty-five (45) days notice) in writing, such notice to be effective at the end of the first term on December 30 or the second term on June 30, and to contain the reason for the termination, and a list of the teaching positions, if any, in respect of which the Board proposes to retain a teacher with less seniority. The Board shall concurrently forward a copy of such notice to the BVTU. The requirement that the effective date of the notice be at the end of a school term does not apply where the Board makes an appointment to a position which is temporarily vacant and which the Board reasonably believes will cease to be vacant at a time other than the end of a school term.</p>
        <p>C.21.5Teachers&#x27; Rights of Re-engagement</p>
        <p>a.When a position on the teaching staff of the District becomes available the Board shall, notwithstanding any other provision except Article C.21.5.d of this Agreement, first offer re-engagement to the teacher who has the most seniority among those terminated pursuant to this Article, provided that teacher possesses the necessary qualifications for the available position. If that teacher declines the offer, the position shall be offered to the teacher with the next greatest seniority and the necessary qualifications, and the process shall be repeated until the position is filled. All positions shall be filled in this manner while there are remaining teachers who have been terminated pursuant to this Article.</p>
        <p>b.A teacher who is offered re-engagement pursuant to Article C.21.5.a shall inform the Board whether or not the offer is accepted, within three (3) business days of the receipt of such offer.</p>
        <p>c.The Board shall allow ten (10) business days from an acceptance of an offer under Article C.21.5.b for the teacher to commence teaching duties, provided that, where the teacher is required to give a longer period of notice to another employer, such a longer period shall be allowed but in no case longer than thirty (30) days.</p>
        <p>d.A teacher&#x27;s right to re-engagement under this Article is lost:</p>
        <p>i.if the teacher elects to receive severance pay under Article C.21.7;</p>
        <p>ii.if the teacher twice refuses to accept continuing appointments, not requiring a change in community of residence, for which they possess the necessary qualifications excluding maternity leave; or</p>
        <p>iii.if two (2) years elapse from the date of termination under this Article and the teacher has not been re-engaged.</p>
        <p>e.A teacher&#x27;s right to re-engagement under this Article is not lost if:</p>
        <p>i.the teacher accepts or refuses a position that is other than the fraction of teaching time that they previously held;</p>
        <p>ii.the teacher accepts or refuses a position that is for less than a full school year;</p>
        <p>iii.the teacher would be entitled to maternity leave or education leave.</p>
        <p>f.Upon re-engagement, a teacher shall be entitled to a continuing appointment to the teaching staff of the District if the position is a continuing one. If a teacher is offered and accepts a temporary appointment, it shall not affect their right to re-engagement as a continuing teacher, nor otherwise affect their right to re-engagement, severance, or two (2) year recall. Acceptance of a temporary appointment will pause the two (2) year limit for recall for the duration of the temporary appointment.</p>
        <p>g.The Board shall send postings of available positions to teachers who are on the recall list.</p>
        <p>C.21.6Benefits</p>
        <p>a.A teacher who retains rights of re-engagement pursuant to Article C.21.5 shall be entitled, if otherwise eligible, to maintain participation in all benefits provided in this Agreement by payment of the full cost of such benefits to the Board. The Board will continue the regular sharing arrangement for the first two months following termination.</p>
        <p>b.A teacher re-engaged pursuant to this Article shall be entitled to all sick leave credits accumulated at the date of termination.</p>
        <p>C.21.7Severance Pay</p>
        <p>a.A teacher on continuing appointment who has one or more years of continuous employment and who is terminated, save and except a teacher who is terminated or dismissed pursuant to Section 15 and Section 92(3) of the School Act, may elect to receive severance pay at any time within the first twenty (20) calendar months, before the teacher&#x27;s right to re-engagement pursuant to Article C.21.5 is lost.</p>
        <p>b.Severance pay shall be calculated at the rate of five (5) per cent of one (1) year&#x27;s salary for each year of continuous service, to a maximum of one (1) year&#x27;s salary. Severance pay shall be calculated on the teacher&#x27;s salary at the time of their termination.</p>
        <p>c.A teacher who receives severance pay pursuant to this Article and who, notwithstanding Article C.21.5, is subsequently re-hired by the Board, shall retain any payment made under the terms of this Article. In such case, the calculation of years of service for purposes of seniority shall commence with the date of such re-hiring.</p>
      </div>

      <div class="ca-article" id="article-c-22-employment-on-continuing-contract">
        <h3>ARTICLE C.22:EMPLOYMENT ON CONTINUING CONTRACT</h3>
        <p>C.22.1All teachers appointed by the Board to the teaching staff of the district shall be appointed to a continuing contract of employment, except for temporary appointments made in accordance with the following provisions:</p>
        <p>a.to replace a teacher on continuing contract who is absent or on leave for any reason, or</p>
        <p>b.replace a teacher on a temporary appointment, or</p>
        <p>c.fill a position that is temporarily created for program reasons for one school year or less, or fill a position that is temporarily created for enrollment fluctuations for less than one school year, or</p>
        <p>d.fill a position that has been vacated by a teacher during the school year, or</p>
        <p>e.Teachers-Teaching-On-Call as provided for in Article C.27.</p>
      </div>

      <div class="ca-article" id="article-c-23-procedures-for-discipline-dismissal-when-based-on-misconduct">
        <h3>ARTICLE C.23:PROCEDURES FOR DISCIPLINE/DISMISSAL WHEN BASED ON MISCONDUCT</h3>
        <p>C.23.1The Board shall not discipline or dismiss any person bound by this Agreement save and except for just and reasonable cause.</p>
        <p>C.23.2Prior to when the Board proceeds with disciplinary action including dismissal, it shall immediately inform the teacher and the BVTU in writing.</p>
        <p>C.23.3Where a teacher is under investigation by the Board for any cause, the teacher and the BVTU shall be immediately advised in writing of that fact and the particulars of the allegation of which the Board is currently aware unless substantial grounds exist for concluding that such notification would prejudice the investigation. In any event, the teacher and the BVTU shall be notified at the earliest reasonable time and before any action is taken by the Board.</p>
        <p>C.23.4The teacher shall have, and be advised of, the right to be accompanied by a representative(s) of the BVTU at any meeting held under this Article.</p>
        <p>C.23.5Unless the teacher, or the BVTU at the request of the teacher, waives the right to such meeting, the Board shall not discipline or dismiss any person bound by this Agreement unless it has, prior to considering such action, held a meeting between the teacher and the Superintendent or designate and the Board with the teacher entitled to be present, in respect of which:</p>
        <p>a.the teacher and the BVTU shall be given seventy two (72) hours&#x27; notice of the meeting which shall take place no later than fourteen (14) working days after notice is received by the teacher;</p>
        <p>b.at the time such notice is given, the teacher shall be given a full and complete statement in writing of the grounds for the contemplated action and all documents that will be considered at the meeting;</p>
        <p>c.the teacher or the BVTU on behalf of the teacher may file a written reply to the allegations prior to the meeting;</p>
        <p>d.the teacher and/or their advocate has the right to hear all evidence, to receive copies of all documents, to call witnesses, to make submissions and to question any person presenting evidence;</p>
        <p>e.the decision of the Board shall be communicated in writing to the teacher and the BVTU, and shall contain a statement of the grounds for the decision.</p>
        <p>C.23.6Where a teacher is suspended under Section 15(5) of the School Act, the Board shall, prior to taking further action under Section 15(7) of the School Act, hold a meeting in accordance with the process outlined in Article C.23.5, unless the right to such meeting is waived by the teacher.</p>
        <p>C.23.7The BVTU shall have the option of referring a grievance regarding dismissal of a teacher directly to arbitration pursuant to Article A.6.</p>
        <p>C.23.8At an arbitration in respect of discipline or dismissal of a teacher, no material from the teacher&#x27;s file may be presented unless the material was brought to the teacher&#x27;s attention at least three (3) working days prior to the first arbitration hearing.</p>
        <p>C.23.9These matters shall be considered personnel matters and as such the Board shall not release to the media or the public, information in respect of the suspension or dismissal of a teacher except when the results of the suspension or dismissal of the teacher has been upheld by an arbitration hearing or by a court. During the interim period, while a decision is being made by an Arbitration Board or a court, the Board agrees to confer with the BVTU before any press release is made. Any details beyond basic facts shall only be made public after consultation with the BVTU.</p>
        <p>C.23.10The Board has the right to bring any additional resource people to any meetings held under this Article, including the Superintendent, the Assistant Superintendent and the Secretary Treasurer.</p>
        <p>C.23.11The BVTU has the right to bring any additional resource people to any meetings held under this Article.</p>
        <p>C.23.12Where an employee has been suspended on grounds set out in Section 15 (4) of the School Act, this employee shall be reinstated with full pay for the period of such suspension, unless on the final disposition of the matter by the Board, the charge is found to be substantiated.</p>
        <p>C.23.13Conduct of a teacher in non school hours, off school premises, and which is not in connection with the employment duties of the teacher, shall not be grounds for discipline unless such conduct impairs the teacher&#x27;s ability to perform assigned teacher duties in a satisfactory manner.</p>
      </div>

      <div class="ca-article" id="article-c-24-procedures-for-discipline-dismissal-when-based-on-performance">
        <h3>ARTICLE C.24:PROCEDURES FOR DISCIPLINE/DISMISSAL WHEN BASED ON PERFORMANCE</h3>
        <p>C.24.1The Board shall not dismiss a teacher on the basis of less than satisfactory performance of teaching duties except where the Board has received at least three consecutive unsatisfactory reports pursuant to Article E.24 of this Agreement indicating that the learning situation in the class or classes of the teacher is less than satisfactory.</p>
        <p>a.The reports referred to in Article C.24.1 above shall have been prepared in accordance with the process established in Article E.24 of this Agreement.</p>
        <p>C.24.2The reports shall be prepared pursuant to the School Act and in accordance with the following conditions:</p>
        <p>a.The reports shall have been issued in a period of not less than twelve (12) months to a maximum twenty four (24) calendar months; such period not including any leave of absence granted under Article G.35 or an agreed upon plan of assistance;</p>
        <p>b.At least one (1) of the reports shall be a report of a Superintendent of Schools or an Assistant Superintendent of Schools;</p>
        <p>c.The other two (2) reports shall include reports of:</p>
        <p>i.the Superintendent of Schools, or an Assistant Superintendent of Schools;</p>
        <p>ii.a Director of Instruction, or</p>
        <p>iii.the Principal/Vice Principal of a school to which the teacher is assigned.</p>
        <p>d.The three (3) reports shall be completed independently by three (3) different evaluators.</p>
        <p>C.24.3In the event that a teacher receives a less than satisfactory report, the teacher may:</p>
        <p>a.request a transfer and, where there is mutual agreement, the Board shall proceed with the transfer or,</p>
        <p>b.request and be granted leave of absence without pay of up to one (1) year for the purpose of taking a program of professional or academic instruction, in which case subsequent evaluation shall be undertaken not less than three (3) months nor more than six (6) months after the teacher has returned to teaching duties. The period of leave shall not count for purposes of Article C.24.2.</p>
        <p>C.24.4Where the Board intends to dismiss a teacher on grounds of less than satisfactory teaching situation, it shall, no later than two (2) calendar months prior to the end of a school term, notify the teacher and the President of the BVTU of such intention and provide an opportunity for the teacher and, if desired, their representative to meet with the Superintendent or designate and the Board within fourteen (14) days of such notice.</p>
        <p>C.24.5Where, subsequent to such meeting, the Board decides to dismiss a teacher, it shall issue notice of dismissal at least one month prior to the end of a school term, to be effective at the end of that school term, setting out the particulars as contained within the evaluation reports.</p>
      </div>

      <div class="ca-article" id="article-c-25-part-time-teachers-rights">
        <h3>ARTICLE C.25:PART TIME TEACHERS&#x27; RIGHTS</h3>
        <p>C.25.1A teacher with a continuing full time appointment to the teaching staff may, without prejudice to that appointment, request a part time appointment for a year or less. If denied, reasons for denial will be provided in writing.</p>
        <p>C.25.2At the end of a part time assignment of one year or less, the teacher involved shall be deemed to be in the position they vacated for the purposes of the school staff realignment process for the following year unless a further part time appointment is requested by March 1st for the following school year and is granted.</p>
        <p>C.25.3A teacher with a continuing part time appointment may, without prejudice to that appointment, apply for an additional temporary part time appointment for a specified fraction of time or a full time appointment.</p>
        <p>C.25.4Teachers on part time continuing appointment, or part time temporary appointment, may request a full time continuing appointment and shall be considered with other applicants pursuant to Article E.22.</p>
        <p>C.25.5Two teachers employed full time by the Board may jointly request a job sharing assignment.</p>
        <p>C.25.6Job sharing teachers shall:</p>
        <p>a.have their salary pro-rated according to the percentage of time worked by each teacher;</p>
        <p>b.receive Board benefit contributions as if both teachers were full time teachers;</p>
        <p>c.when one of the teachers agrees to work due to the temporary absence or illness of the other teacher, receive payment at full prorated scale placement for all such work.</p>
        <p>C.25.7A part time teacher&#x27;s assignment in elementary schools shall be time tabled such that work periods in any one day are not separated by blocks of time for which the teacher is not paid.</p>
        <p>C.25.8Every effort will be made when scheduling part-time teachers in middle and secondary schools prior to the completion of the timetable to avoid assignment in which there are work periods which are separated by blocks of time for which the teacher is not paid.</p>
      </div>

      <div class="ca-article" id="article-c-26-temporary-teachers-employment-rights">
        <h3>ARTICLE C.26:TEMPORARY TEACHERS&#x27; EMPLOYMENT RIGHTS</h3>
        <p>C.26.1Each year the Board shall provide the BVTU (no later than Sept. 15th and a revised list by Jan. 15th) with a list of temporary teachers in order of seniority, and a list of positions the Board considers temporarily existing or temporarily vacant for the school year.</p>
        <p>C.26.2a.If a teacher has completed ten aggregate months in temporary assignments, four months of these ten being full time continuous or part time equivalent, and providing that no less than satisfactory report was received, then the teacher shall be placed on a continuing contract or be given full recall rights.</p>
        <p>b.For the purposes of Article C.26.2.a only:</p>
        <p>i.Definitions</p>
        <p>1.“Ten (10) aggregate months”, as set out in the aforementioned article, shall mean one hundred and eight-five (185) aggregate “days in session” while employed on a temporary contract(s) pursuant to the provisions of the Previous Local Agreement which provide for such contracts of employment and;</p>
        <p>2.“Days in Session” shall have the same meaning as found in the School Act Calendar Regulations (B.C. Reg. 189/93) as revised by the Ministry of Education at March 13, 2000 and;</p>
        <p>“Day(s) shall be calculated as follows:</p>
        <p>.5 or greater of a day(s) taught shall equal 1.0 FTE day(s);</p>
        <p>b.Less than .5 of a day(s) taught shall equal .5 FTE day(s).</p>
        <p>The parties agree that the definitions in Article C.26.2.b above shall have no application to the reference to “4 months of these 10 being full time continuous or part time equivalent” in Article C.26.2.</p>
        <p>C.26.3No temporary appointment shall extend beyond June 30 of a school year.</p>
        <p>C.26.4In the case of a temporary teacher whose assignment is for a full school year, two (2) less than satisfactory reports will be required before a teacher loses the right to a continuing contract or full recall rights.  The first of these reports shall be written no later than February 1 of the school year.</p>
      </div>

      <div class="ca-article" id="article-c-27-teacher-teaching-on-call-employment-rights">
        <h3>ARTICLE C.27:TEACHER-TEACHING-ON-CALL EMPLOYMENT RIGHTS</h3>
        <p>C.27.1Temporary Appointment</p>
        <p>a.Twenty (20) days continuous teaching on the same assignment, shall entitle a Teacher-Teaching-On-Call to a temporary appointment made retroactive to the start of the assignment.</p>
        <p>C.27.2List</p>
        <p>a.The Board shall maintain a list of approved Teachers-Teaching-On-Call indicating those who have teaching certificates.  A copy of this list shall be forwarded to all schools early in the first term and be updated early in the second term each year.  Copies shall be provided to the BVTU.</p>
        <p>b.Placement of a person’s name on the list shall be subject to the approval of the Superintendent.</p>
        <p>c.A person’s name shall not be removed from the list without just and reasonable cause.</p>
        <p>C.27.3Hiring</p>
        <p>a.In appointing Teachers-Teaching-On-Call, the Board shall, pursuant to Section 19 of the School Act, select a person on the list qualified for the assignment who possesses a valid B.C. Teaching Certificate, in preference to a person not possessing such a certificate.</p>
        <p>b.The Board may appoint persons not on the list to a Teacher-Teaching-On-Call assignment only in the event that no available person on the list possesses the necessary qualifications for the assignment.</p>
        <p>c.The Board shall provide, when the Principal/Vice-Principal in conjunction with the teacher deem it necessary, a Teacher-Teaching-On-Call for teachers absent from instructional responsibility due to illness or other approved leave.</p>
        <p>d.A Teacher-Teaching-On-Call’s assignment shall be determined by the Principal/Vice-Principal but where the assignment includes a spare and the teacher being replaced has left specific duties for the Teacher-Teaching-On-Call during that spare, the Teacher-Teaching-On-Call shall not normally be given other duties.</p>
        <p>C.27.4Evaluation</p>
        <p>a.A Teacher-Teaching-On-Call may request a written evaluation and the request will not be unreasonably denied.</p>
      </div>

      <div class="ca-article" id="article-c-28-probationary-appointments">
        <h3>ARTICLE C.28:PROBATIONARY APPOINTMENTS</h3>
        <p>C.28.1The Board may during the first nine months of a teacher&#x27;s assignment (exclusive of any leave of absence during or extending beyond those months) terminate the teacher&#x27;s contract and appoint the teacher as a probationary teacher.</p>
        <p>C.28.2No teacher shall be placed on a probationary appointment unless they have received a teaching report indicating less than satisfactory performance.</p>
        <p>C.28.3No report shall be written on a teacher within the first thirty (30) teaching days of their employment.</p>
        <p>C.28.4At the time the teacher is placed on a probationary appointment, they shall be informed of their right to have the BVTU assist them in this matter. An appropriate plan of assistance shall be formulated in consultation with the teacher and BVTU and the Board.</p>
        <p>C.28.5A second report shall not be prepared within forty five (45) teaching days of the report mentioned in Article C.28.2.</p>
        <p>C.28.6If the second report indicates unsatisfactory performance the Board may terminate the teacher&#x27;s employment given notice of twenty (20) teaching days.</p>
        <p>C.28.7The Board may rescind a probationary appointment at any time, but no teacher shall be on a probationary appointment for more than 10 teaching months.</p>
        <p>C.28.8Reports shall be written as per Article E.24, of this contract.</p>
        <p>SECTION DWORKING CONDITIONS</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section D: Working Conditions</h2>
      <div class="ca-article" id="article-d-1-class-size-and-teacher-workload">
        <h3>ARTICLE D.1:CLASS SIZE AND TEACHER WORKLOAD</h3>
        <p>Note: This table is a summary of the K-3 class size limits and is provided for reference only. The parties must refer to the language in full when applying the Collective Agreement. In particular, parties should review Letter of Understanding No. 12 Re: Agreement Regarding Restoration of Class Size, Composition, Ratios and Ancillary Language (“LOU No. 12”) Class Size provisions – paragraphs 6 – 9.</p>
        <p>Local language:</p>
        <p>D.1.1The board and the B.V.T.U. agree to the following class size limits:</p>
        <p>[Note: Section 76.1 Class Size of the School Act as amended also applies that currently limits a combined 3/4 class to 24 students.]</p>
        <p>[Note: Section 76.1 Class Size of the School Act as amended also applies that currently limits any grades 4 to 12 class to 30 students unless it is appropriate for student learning (See section.76.1.(2.1).a), or a prescribed category of class (See section.76.1.(2.1).b). ]</p>
        <p>D.1.2After September 30th of each year, the size of a class shall not exceed the numbers in D.1.1 above by more than two students, except for the following reasons:</p>
        <p>a larger group requested by the teacher(s) to fulfill a particular educational purpose and the Administrative Officer have agreed.</p>
        <p>D.1.3An Administrative Officer may assign a student to any classroom on a temporary basis while a practical alternative is being determined. The teacher shall be advised of the situation. Such as assignment should normally not exceed 10 teaching days.</p>
        <p>D.1.4.Staggered start for new entrants to Kindergarten will be considered appropriate in consultation between the Administrative Officer and the teacher.</p>
      </div>

      <div class="ca-article" id="article-d-2-class-composition-and-inclusion">
        <h3>ARTICLE D.2:CLASS COMPOSITION AND INCLUSION</h3>
        <p>No provincial language.</p>
        <p>Local language:</p>
        <p>Role of the School Based Team</p>
        <p>D.2.1The School Based Team shall be a problem solving support group for the classroom teacher. The School Based Team shall, at the request of the teacher:</p>
        <p>collect and interpret assessment and historical data pertaining to students referred to it.</p>
        <p>assist in preparing an educational program for students referred to the School Based Team.</p>
        <p>review the educational plan of any student who is referred by the classroom teacher.</p>
        <p>provide referrals to district specialists for additional evaluation and/or support.</p>
        <p>D.2.2The S.B.T. shall elect its own chairperson.</p>
        <p>D.2.3The S.B.T. shall be comprised of the following personnel:</p>
        <p>Special Education/Resource Teachers responsible for the program of the student at the school,</p>
        <p>At least one Administrative Officer,</p>
        <p>Teachers to whom the student is or will likely be assigned,</p>
        <p>Outside Resource Personnel as needed, parents as needed.</p>
        <p>D.2.4Where the Board fails to implement a recommendation made by the S.B.T., the S.B.T. will be advised of the reasons in writing.</p>
        <p>Mainstreaming / Integration</p>
        <p>D.2.5The Board and Union agree that the Integration/mainstreaming of special needs students is to be encouraged. Both parties shall work together to ensure that conditions for a positive educational experience exists for both the special needs student and the other pupils in the regular classroom. Special needs students are those identified by the school based team and recognized for funding purposes by the Ministry of Education.</p>
        <p>D.2.6When mainstreaming of students with special needs is being considered, the Administrative Officer, the District Principal for Special Education when available, the School Based Team and the teachers involved shall first meet to consider all aspects of the students’ needs and make appropriate plans prior to the students’ placement.</p>
        <p>D.2.7The planning team referred to in D.2.6 above shall give consideration to:</p>
        <p>the degree of integration projected.</p>
        <p>material and personnel support.</p>
        <p>training of the receiving teacher(s) once the in-service needs have been identified.</p>
        <p>diagnosis and planning time.</p>
        <p>receiving class/classes composition.</p>
        <p>the school and district’s ability to supply the individual program projected.</p>
        <p>D.2.8Class size and composition shall be given major consideration by the district staff, teachers and administrators affected when classroom placement is to be determined for the student with special needs.</p>
        <p>D.2.9No more than two (2) special needs students shall be integrated into one classroom.</p>
        <p>D.2.10Support for the mainstreaming/integration program shall be dependent upon availability of designated funding. Those supports include:</p>
        <p>release time.</p>
        <p>teacher assistant allocation.</p>
        <p>special equipment and resources.</p>
        <p>appropriate facilities.</p>
        <p>completion of an individual educations program (I.E.P.).</p>
        <p>D.2.11Curriculum and/or materials modification which may be required by students with special needs shall be provided to the receiving teacher where feasible.</p>
      </div>

      <div class="ca-article" id="article-d-3-non-enrolling-staffing-ratios">
        <h3>ARTICLE D.3:NON-ENROLLING STAFFING RATIOS</h3>
        <p>Note: This table is a summary of the provincial non-enrolling teacher staffing ratios and is provided for reference only. The parties must refer to Letter of Understanding No. 12 Re: Agreement Regarding Restoration of Class Size, Composition, Ratios and Ancillary Language (“LOU No. 12”) in full when applying the ratios.</p>
        <p>Where the ratio below is from a source other than LOU No. 12, it is a lower ratio and has replaced the ratio in LOU No. 12.</p>
      </div>

      <div class="ca-article" id="article-d-4-preparation-time">
        <h3>ARTICLE D.4:PREPARATION TIME</h3>
        <p>[Articles D.4.1 to D.4.3 are not applicable in School District #54 (Bulkley Valley).  See Articles D.4.4 to D.4.7.]</p>
        <p>LOCAL PROVISIONS</p>
        <p>D.4.4All full-time secondary teachers shall receive preparation time equal to 12.5% of their instructional time.</p>
        <p>D.4.5All full-time elementary teachers shall receive preparation time equal to 100 minutes per week. (One hundred ten (110) minutes effective June 30, 2019.)</p>
        <p>D.4.6All full time middle school teachers shall receive preparation time equal to 10% of their instructional time.</p>
        <p>D.4.7Part time teachers teaching 60% or more shall receive prep time pro-rated to their instructional time.</p>
      </div>

      <div class="ca-article" id="article-d-5-middle-schools">
        <h3>ARTICLE D.5:MIDDLE SCHOOLS</h3>
        <p>1.Where there are no negotiated provisions concerning the implementation or operation of a middle school program, this article shall govern the implementation or operation of a middle school program in a school district.</p>
        <p>2.Should the employer seek to establish a middle school program in one or more schools in a district, the employer and the local shall meet, no later than ten (10) working days from a decision of the employer to implement a middle school program, in order to negotiate any alternate or additional provisions to the Collective Agreement which are necessary to accommodate the intended middle school program.</p>
        <p>3.In the absence of any other agreement with respect to the instructional day and preparation time, the provisions of the Collective Agreement with regard to secondary schools shall apply to middle schools.</p>
        <p>4.If the employer and the local are unable to agree on what, if any, alternate or additional provisions of the Collective Agreement are necessary to accommodate the intended middle school program(s), either party may refer the matter(s) in dispute to expedited arbitration for final and binding resolution pursuant to Article D.5.5 below.</p>
        <p>5.a.The jurisdiction of the arbitrator shall be limited to the determination of alternate or additional provisions necessary to accommodate the intended middle school program(s).</p>
        <p>b.In the event the arbitration is not concluded prior to the implementation of the middle school program, the arbitrator will have remedial authority to make appropriate retroactive modifications and adjustments to the agreement.</p>
        <p>c.The arbitration shall convene within thirty (30) working days of referral to arbitration in accordance with the following:</p>
        <p>i.Within ten (10) working days of the matter being referred to arbitration, the parties shall identify all issues in dispute;</p>
        <p>ii.Within a further five (5) working days, there shall be a complete disclosure of particulars and documents;</p>
        <p>iii.Within a further five (5) working days, the parties shall exchange initial written submissions;</p>
        <p>iv.The hearing shall commence within a further ten (10) working days; and</p>
        <p>v.The arbitrator shall render a final and binding decision within fifteen (15) working days of the arbitration concluding.</p>
        <p>6.Where a middle school program has been established on or prior to ratification of the 2006-2011 Provincial Collective Agreement, the existing provisions shall be retained unless the parties mutually agree that they should be amended.</p>
      </div>

      <div class="ca-article" id="article-d-6-alternate-school-calendar">
        <h3>ARTICLE D.6:ALTERNATE SCHOOL CALENDAR</h3>
        <p>1.In this article, an alternative school calendar is a school calendar that differs from the standard school calendar as specified in Schedule 1 (Supplement) of the School Calendar Regulation 114/02.</p>
        <p>2.When a school district intends to implement an alternate school calendar, written notification shall be provided to the local no later than forty (40) working days prior to its implementation. The employer and the local shall meet within five (5) working days following receipt of such notice to negotiate modifications to the provisions of the agreement that are directly or indirectly affected by the proposed change(s). The aforesaid modifications shall preserve, to the full legal extent possible, the original intent of the agreement.</p>
        <p>3.The process outlined below in Article D.6.4 through Article D.6.7 applies only to modifications to the school calendar that include a four-day school week, a nine-day fortnight, or a year round calendar.</p>
        <p>4.If the parties cannot agree on the modifications required, including whether or not a provision(s) is/are directly or indirectly affected by the proposed alternate school calendar, the matter(s) in dispute may be referred, by either party, to expedited arbitration pursuant to Article D.6.6 below for final and binding resolution.</p>
        <p>5.The jurisdiction of the arbitrator shall be limited to the modifications of the agreement necessary to accommodate the alternate school calendar.</p>
        <p>6.In the event the arbitration is not concluded prior to the implementation of the alternate school calendar, the arbitrator will have remedial authority to make retroactive modifications and adjustments to the agreement.</p>
        <p>7.The arbitration shall convene within thirty (30) working days of referral to arbitration in accordance with the following:</p>
        <p>a.Within ten (10) working days of the matter being referred to arbitration, the parties shall identify all issues in dispute;</p>
        <p>b.Within a further five (5) working days, there shall be a complete disclosure of particulars and documents;</p>
        <p>c.Within a further five (5) working days, the parties shall exchange initial written submissions;</p>
        <p>d.The hearing shall commence within a further ten (10) working days; and</p>
        <p>e.The arbitrator shall render a final and binding decision within a further fifteen (15) working days.</p>
        <p>8.Where an alternate school calendar has been established prior to the ratification of the Collective Agreement, existing agreements that accommodate the alternate school calendar shall be retained unless the parties agree that they should be amended.</p>
        <p>Note:BCTF will provide a list of acceptable arbitrators from the current list of arbitrators available through the Collective Agreement Arbitration Bureau.</p>
        <p>LOCAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-d-20-lunch-hour-supervision">
        <h3>ARTICLE D.20:LUNCH HOUR SUPERVISION</h3>
        <p>D.20.1Each teacher shall be entitled to a lunch period free from assigned teaching or supervision duties.</p>
      </div>

      <div class="ca-article" id="article-d-21-extra-curricular-activities">
        <h3>ARTICLE D.21:EXTRA CURRICULAR ACTIVITIES</h3>
        <p>D.21.1In this Agreement, extracurricular programs and activities include all those that are beyond the provincially prescribed and locally determined curricula of the school district.</p>
        <p>D.21.2It is recognized that extracurricular activities are vital to effective schools. The Board and the BVTU consider it desirable that teachers participate in extracurricular activities.</p>
        <p>D.21.3The Board agrees that all teachers sponsoring extracurricular activities do so on a voluntary basis and according to individual talents and wishes.</p>
        <p>D.21.4While voluntarily involved in extracurricular activities, teachers shall be considered to be acting in the employ of the Board, for purposes of liability of the Board and coverage by the Board&#x27;s insurance.</p>
      </div>

      <div class="ca-article" id="article-d-22-staff-meetings">
        <h3>ARTICLE D.22:STAFF MEETINGS</h3>
        <p>D.22.1Seven days notice of regular staff meetings shall be given, including the agenda of items to be considered except for emergency meetings.</p>
        <p>D.22.2All staff members shall have the right to place items on the staff meeting agenda.</p>
        <p>D.22.3Written minutes of staff meetings shall be kept and circulated to all staff members.</p>
        <p>D.22.4Teachers shall not be required to attend staff meetings which commence prior to one (1) hour before classes begin or commence later than one (1) hour after the dismissal of pupils.</p>
      </div>

      <div class="ca-article" id="article-d-23-technological-change">
        <h3>ARTICLE D.23:TECHNOLOGICAL CHANGE</h3>
        <p>D.23.1Definition</p>
        <p>a.For the purposes of this Agreement the term &quot;technological change&quot; shall be understood to mean changes introduced by the District in areas of automation, new equipment or new material different in nature, type, application or quantity from that previously utilized, and language of instruction, where such change or changes affect the terms and conditions or security of employment of members of the BVTU or alters the basis on which the Agreement was negotiated.</p>
        <p>D.23.2Notice and Discussion</p>
        <p>a.When it is determined that the introduction of a technological change is under consideration or is to be introduced, the Board shall notify the BVTU in writing. Such notice shall be given at least ninety (90) days before the term in which the introduction of the technological change is intended. Once such notice is given, the Board agrees to discuss the matter with the BVTU.</p>
        <p>D.23.3Information</p>
        <p>a.The notice of intent to introduce a technological change shall contain:</p>
        <p>i.the nature of the change;</p>
        <p>ii.the effective date of the change;</p>
        <p>iii.the approximate number, type and location of BVTU members likely to be affected by the change.</p>
        <p>b.The Board shall update this information as new developments arise and modifications are made.</p>
        <p>D.23.4Negotiation</p>
        <p>a.Once notice of technological change has been given pursuant to Article D.23.2 of this Agreement, the Board shall within thirty (30) days begin negotiations with the BVTU on ways in which employees in the bargaining unit who may be affected can adjust to the effects of the technological change.</p>
        <p>D.23.5Agreement</p>
        <p>a.The Board and the BVTU agree that this Article represents the Agreement between the Board and the BVTU on technological change, as contemplated by the Labour Relations Code.</p>
      </div>

      <div class="ca-article" id="article-d-24-health-and-safety-committee">
        <h3>ARTICLE D.24:HEALTH AND SAFETY COMMITTEE</h3>
        <p>D.24.1A District Health and Safety Committee, comprised of Employer, BVTU and CUPE Local 2145 representatives, shall be established by the employer. Committee Terms of reference shall be developed by the committee and include provisions for the following:</p>
        <p>1.membership</p>
        <p>2.purpose &amp; scope</p>
        <p>3.procedural matters</p>
        <p>D.24.2The Committee shall be composed of not fewer than six (6) members chosen by and representing the BVTU, the employer and CUPE equally.</p>
        <p>D.24.3Terms of reference for the safety committee may include, but are not limited to:</p>
        <p>a.Meet monthly, except July and August, to discuss safety related matters</p>
        <p>b.Promote safety in the district through the distribution of information</p>
        <p>c.Develop and maintain a safety awareness program</p>
        <p>d.Perform safety inspections in all district buildings, work and play areas</p>
        <p>e.Make recommendations for enhancing safety with respect to procedures, equipment, buildings, vehicles, etc</p>
        <p>f.Provide assistance to school safety committees in the investigation of safety-related issues.</p>
        <p>D.24.4The district committee, in consultation with the site joint health and safety committee, will assess concerns regarding cleanliness, temperature, ventilation, lighting, humidity, sound level and other physical conditions conducive to effective learning in compliance with Occupational Health &amp; Safety regulations of the Workers Compensation Act of BC.</p>
        <p>D.24.5The Board shall make every reasonable attempt to meet the requirements of WHMIS.</p>
      </div>

      <div class="ca-article" id="article-d-25-budget-process">
        <h3>ARTICLE D.25:BUDGET PROCESS</h3>
        <p>D.25.1Each year in the preparation of the district annual budget the BVTU shall be invited to the annual public presentation of the draft budget. The BVTU is welcome to provide oral and/or written comments.</p>
      </div>

      <div class="ca-article" id="article-d-26-instructional-time">
        <h3>ARTICLE D.26:INSTRUCTIONAL TIME</h3>
        <p>D.26.1Each full time elementary teacher’s regular weekly assignment shall not exceed 25 hours of instructional time inclusive of preparation time as provided in this agreement.</p>
        <p>D.26.2Each full time secondary teacher&#x27;s regular weekly assignment shall not exceed 27 1/2 hours of instructional time inclusive of preparation time as provided in this Agreement.</p>
        <p>D.26.3Each full time Middle School teacher&#x27;s regular weekly assignment shall not exceed 26 3/4 hours of instructional time inclusive of preparation time as provided for in this Agreement.</p>
        <p>D.26.4Instructional time shall be defined as classroom instruction plus homeroom, time between classes, and preparation time.</p>
      </div>

      <div class="ca-article" id="article-d-27-beginning-teachers">
        <h3>ARTICLE D.27:BEGINNING TEACHERS</h3>
        <p>D.27.1Beginning teachers shall be provided with teaching conditions to help them in their adjustment to teaching. The conditions shall include, but not be limited to:</p>
        <p>a.a teaching assignment whereby the most demanding classes are not the responsibility of a beginning teacher.</p>
        <p>b.a mentor&#x27;s program.</p>
        <p>c.an in-school orientation and induction program.</p>
      </div>

      <div class="ca-article" id="article-d-28-regular-work-year">
        <h3>ARTICLE D.28:REGULAR WORK YEAR</h3>
        <p>D.28.1The annual salary established for employees covered by this agreement shall be payable in respect of the teacher&#x27;s regular work year.</p>
        <p>D.28.2The days in session in the regular work year for the teacher shall include:</p>
        <p>a.5 Professional Development Days;</p>
        <p>b.one year end administrative day;</p>
        <p>c.two school-community interaction days if included in the finalized provincial calendar;</p>
        <p>d.two 1/2 days in which there is early dismissal for Parent/Teacher Conferences;</p>
        <p>e.one 1/2 day in which there is early dismissal for school start-up in September.</p>
        <p>D.28.3All such days in session shall be scheduled between the Tuesday after Labour Day and the closing date in the provincial calendar.</p>
        <p>D.28.4Christmas break shall be according to the Provincial Calendar.</p>
        <p>D.28.5Spring break shall be according to the Provincial Calendar.</p>
        <p>D.28.6On or before May 30th of each school year, a School Calendar for the next school year shall be drafted by the Superintendent, and the President of the BVTU, pursuant to Regulation 8 and forwarded to the Board.</p>
        <p>D.28.7Work outside of the regular work year established above shall be voluntary. In this regard, teachers shall have the right to refuse requests to perform such work.  Teachers who are requested and have agreed to undertake such work shall have the choice of being paid for the days worked as per Article B.22.3.a or of taking the equivalent number of days off during the year with pay.</p>
      </div>

      <div class="ca-article" id="article-d-29-home-education">
        <h3>ARTICLE D.29:HOME EDUCATION</h3>
        <p>D.29.1Educational services that may be required for home education students (as defined in the School Act and Regulations) shall be provided by a member of the Union or an Administrative Officer. Home Education students, when assigned to a specific teacher, shall constitute a discrete part of the teacher’s assignment.</p>
      </div>

      <div class="ca-article" id="article-d-30-mentor-beginning-teacher-program">
        <h3>ARTICLE D.30:MENTOR/BEGINNING TEACHER PROGRAM</h3>
        <p>D.30.1The mentor/beginning teacher program shall be administered and facilitated by the BVTU and the Board.</p>
        <p>D.30.2A mentor is a teacher who voluntarily agrees to mentor a beginning teacher and who:</p>
        <p>a.may have experience in assignments similar to that of a beginning teacher;</p>
        <p>b.has informed the BVTU of a willingness to serve as a mentor.</p>
        <p>D.30.3Participation in the mentor/beginning teacher program is voluntary.  The relationship of the mentor/beginning teacher shall be in confidence.</p>
        <p>D.30.4The pairing of mentor/beginning teacher and the continuation of the pairing for up to one year shall be by mutual agreement of the mentor and the beginning teacher.</p>
        <p>D.30.5The mentor/beginning teacher program will not comprise any part of the evaluation of a teacher.</p>
        <p>D.30.6Each mentor/beginning teacher pair shall be provided release time for conducting observations, demonstration teaching, collaboration, consultation and professional development activities.</p>
        <p>D.30.7The number of days of release time shall be based on the amount of the grants received by the Board targeted for this program.</p>
        <p>D.30.8The number of days of release time may be increased when Principal/Vice-Principal, the mentor, and beginning teachers agree to an increase, subject to the approval by the Superintendent.</p>
      </div>

      <div class="ca-article" id="article-d-31-teacher-involvement-in-planning-new-schools">
        <h3>ARTICLE D.31:TEACHER INVOLVEMENT IN PLANNING NEW SCHOOLS</h3>
        <p>D.31.1When new school construction or major school renovations are planned in a school, the Board shall consult with the school staff of the school affected to assist in the planning process.</p>
        <p>D.31.2Where a new school is being built the Board shall invite the BVTU to send a representative to the Building Committee to provide input and assist with the planning process.</p>
        <p>SECTION EPERSONNEL PRACTICES</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section E: School and Teaching Environment</h2>
      <div class="ca-article" id="article-e-1-non-sexist-environment">
        <h3>ARTICLE E.1:NON-SEXIST ENVIRONMENT</h3>
        <p>1.A non-sexist environment is defined as that in which there is no discrimination against employees based on sex, gender identity or expression, including by portraying them in gender stereotyped roles, refusing to acknowledge their identity, or by omitting their contributions.</p>
        <p>2.The employer does not condone and will not tolerate any expression of sexism. In September of each school year the employer and the local shall jointly notify administrative officers and staff, in writing, of their commitment to a non-sexist environment.</p>
        <p>The employer and the local shall promote a non-sexist environment through the development, distribution, integration and implementation of anti-sexist educational programs, activities, and learning resources for both staff and students.</p>
        <p>Prior to October 31st of each school year, principals or vice-principals will add to the agenda of a regularly scheduled staff meeting a review of anti-sexist educational programs, activities and learning resources.</p>
      </div>

      <div class="ca-article" id="article-e-2-harassment-sexual-harassment">
        <h3>ARTICLE E.2:HARASSMENT/SEXUAL HARASSMENT</h3>
        <p>General</p>
        <p>The employer recognizes the right of all employees to work, to conduct business and otherwise associate free from harassment or sexual harassment, including harassment based on the grounds in the Human Rights Code of BC.</p>
        <p>The employer considers harassment in any form to be totally unacceptable and will not tolerate its occurrence. Proven harassers shall be subject to discipline and/or corrective actions. Such actions may include:</p>
        <p>counselling;</p>
        <p>courses that develop an awareness of harassment;</p>
        <p>verbal warning, written warning, transfer, suspension or dismissal.</p>
        <p>No employee shall be subject to reprisal, threat of reprisal or discipline as the result of filing a complaint of harassment or sexual harassment which the complainant reasonably believes to be valid.</p>
        <p>There will be no harassment and/or discrimination against any member of the local because they are participating in the activities of the local or carrying out duties as a representative of the local.</p>
        <p>All parties involved in a complaint agree to deal with the complaint expeditiously and to respect confidentiality.</p>
        <p>The complainant and/or the alleged offender, if a member(s) of the Local, may at the choice of the employee be accompanied by a representative(s) of the Local at all meetings in this procedure.</p>
        <p>Definitions</p>
        <p>Harassment includes:</p>
        <p>any improper behaviour that would be cruel and/or offensive to any reasonable person, is unwelcome, and which the initiator knows or ought reasonably to know would be unwelcome; or</p>
        <p>objectionable conduct, comment, materials or display made on either a one-time or continuous basis that would demean, belittle, intimidate, or humiliate any reasonable person; or</p>
        <p>the exercise of power or authority in a manner which serves no legitimate work purpose and which a person ought reasonably to know is inappropriate; or</p>
        <p>misuses of power or authority such as exclusion, intimidation, threats, coercion and blackmail; or</p>
        <p>sexual harassment.</p>
        <p>Sexual harassment includes:</p>
        <p>any comment, look, suggestion, physical contact, or real or implied action of a sexual nature which creates an uncomfortable working environment for the recipient, made by a person who knows or ought reasonably to know such behaviour is unwelcome; or</p>
        <p>any circulation or display of visual or written material of a sexual nature that has the effect of creating an uncomfortable working environment; or</p>
        <p>an implied promise of reward for complying with a request of a sexual nature; or</p>
        <p>a sexual advance made by a person in authority over the recipient that includes or implies a threat or an expressed or implied denial of an opportunity which would otherwise be granted or available and may include a reprisal or a threat of reprisal made after a sexual advance is rejected.</p>
        <p>Resolution Procedure</p>
        <p>Step 1 – Informal Resolution Process</p>
        <p>Note: Step 1 (Informal Resolution Process) is not required in order to proceed to Step 2 (Formal Complaint Process).</p>
        <p>At any point in the Informal Resolution Process, should the administrator determine that a formal process is required, they will stop the informal process and inform the complainant and respondent in writing.</p>
        <p>The complainant may choose to speak to or correspond directly with the alleged harasser to express their feelings about the situation.</p>
        <p>Before proceeding to Step 2, the complainant may approach their administrative officer, staff representative or other contact person to discuss potential means of resolving the complaint and to request assistance in resolving the matter. The assistance may include the administrative officer meeting with the alleged harasser to communicate the concern and the request that the behaviour stop. If the matter is resolved to the complainant&#x27;s satisfaction the matter is deemed to be resolved.</p>
        <p>d.If the matter is not resolved, the administrator may meet with the complainant and respondent separately, and may invite them to participate in a facilitated discussion. All parties involved must agree to respect confidentiality.</p>
        <p>In the circumstances where a respondent has acknowledged responsibility, the employer may advise the respondent in writing of the standard of conduct expected by the employer. Such a memo shall be non-disciplinary in nature and may be referred to only to establish that the respondent has been advised of the expected standard of conduct.</p>
        <p>Step 2 – Formal Complaint Process</p>
        <p>If a complainant chooses not to meet with the alleged harasser, or no agreement for resolution of the complaint has been reached, or an agreement for resolution has been breached by the alleged harasser, a complaint may be filed with the superintendent or designate.</p>
        <p>The complaint should include a description of the specific incident(s) that form the basis of the complaint and the definitions of sexual harassment/harassment which may apply; however, the form of the complaint will in no way restrict the investigation or its conclusions.</p>
        <p>The complainant may request that the employer consider an alternative dispute resolution process to attempt to resolve the complaint.</p>
        <p>The employer shall notify in writing the alleged harasser of the complaint and provide notice of complaint or investigation.</p>
        <p>In the event the superintendent is involved either as the complainant or alleged harasser, the complaint shall, at the complainant&#x27;s discretion, be immediately referred to either BCPSEA or a third party who shall have been named by prior agreement of the employer and the local who shall proceed to investigate the complaint in accordance with Step 3 and report to the board.</p>
        <p>Step 3 – Formal Resolution Process</p>
        <p>The employer shall review the particulars of the complaint as provided by the complainant pursuant to Article E.2.10.a. The employer may request further particulars from the complainant, including information about any requested alternative dispute resolution process. Upon the conclusion of such a review, the employer shall:</p>
        <p>initiate an investigation of the complaint and appoint an investigator pursuant to Article E.2.11.c below, or;</p>
        <p>recommend mediation or other alternative dispute resolution processes to resolve the complaint.</p>
        <p>Should the complainant not agree with the process described in Article E.2.11.a.ii, the employer shall initiate an investigation. The employer shall provide notice of investigation.</p>
        <p>The investigation or other formal resolution process shall be conducted by a person who shall have training and/or experience in investigating complaints of harassment.</p>
        <p>The complainant may request an investigator, mediator or facilitator who:</p>
        <p>is of the same gender as the complainant;</p>
        <p>is Indigenous, and/or has cultural knowledge and sensitivity if a complainant self-identifies as Indigenous;</p>
        <p>is a person of colour if the complainant is a person of colour.</p>
        <p>Where practicable the request(s) will not be denied.</p>
        <p>Where there is an investigation, the investigation shall be conducted as soon as is reasonably possible and shall be completed in twenty (20) working days unless otherwise agreed to by the parties, such agreement not to be unreasonably withheld.</p>
        <p>Participation in mediation or an alternative dispute resolution process (per Article E.2.11.a.ii) shall not preclude an employee from making a new complaint should the harassment continue or resume following this process.</p>
        <p>Remedies</p>
        <p>Where the investigation determines harassment has taken place, the complainant shall, when appropriate, be entitled to but not limited to:</p>
        <p>reinstatement of sick leave used as a result of the harassment;</p>
        <p>any necessary counselling where EFAP services are fully utilised or where EFAP cannot provide the necessary services to deal with the negative effects of the harassment;</p>
        <p>redress of any career advancement or success denied due to the negative effects of the harassment;</p>
        <p>recovery of other losses and/or remedies which are directly related to the harassment.</p>
        <p>Where the investigator has concluded that harassment or sexual harassment has occurred, and the harasser is a member of the bargaining unit, any disciplinary sanctions that are taken against the harasser shall be done in accordance with provisions in the agreement regarding discipline for misconduct.</p>
        <p>The local and the complainant shall be informed in writing whether there was a finding of harassment, and whether disciplinary action was or was not taken.</p>
        <p>If the harassment results in the transfer of an employee it shall be the harasser who is transferred, except where the complainant requests to be transferred.</p>
        <p>If the employer fails to follow the provisions of the Collective Agreement, or the complainant is not satisfied with the remedy, the complainant may initiate a grievance at Step 3 of Article A.6 (Grievance Procedure). In the event the alleged harasser is the superintendent, the parties agree to refer the complaint directly to expedited arbitration.</p>
        <p>Training</p>
        <p>The employer, in consultation with the local, shall be responsible for developing and implementing an ongoing harassment and sexual harassment awareness program for all employees.</p>
        <p>Where a program currently exists and meets the criteria listed in this agreement, such a program shall be deemed to satisfy the provisions of this article. This awareness program shall be scheduled at least once annually for all new employees to attend.</p>
        <p>The awareness program shall include but not be limited to:</p>
        <p>the definitions of harassment and sexual harassment as outlined in this Agreement;</p>
        <p>understanding situations that are not harassment or sexual harassment, including the exercise of an employer&#x27;s managerial and/or supervisory rights and responsibilities;</p>
        <p>developing an awareness of behaviour that is illegal and/or inappropriate;</p>
        <p>outlining strategies to prevent harassment and sexual harassment;</p>
        <p>a review of the resolution procedures of Article E.2;</p>
        <p>understanding malicious complaints and the consequences of such;</p>
        <p>outlining any Board policy for dealing with harassment and sexual harassment;</p>
        <p>outlining laws dealing with harassment and sexual harassment which apply to employees in B.C.</p>
        <p>LOCAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-e-21-assignments-in-school">
        <h3>ARTICLE E.21:ASSIGNMENTS IN SCHOOL</h3>
        <p>E.21.1Teacher assignments shall reflect the teacher&#x27;s professional training, teaching experience, and personal preference of the teacher as well as an equitable distribution of the workload. Additional factors, pertaining particularly to beginning teachers, shall include the number of courses, preparation requirements and class composition.</p>
        <p>E.21.2A staff meeting shall be held prior to June 15th annually for the purpose of discussing the proposed timetable and staff assignments for the next school year.</p>
        <p>E.21.3Assignments within a school shall be based on the qualifications, training, experience, equitable distribution of workload, and personal preference of the teacher as well as the needs of the school, and shall not be used for disciplinary purposes.</p>
      </div>

      <div class="ca-article" id="article-e-22-posting-and-filling-vacant-positions">
        <h3>ARTICLE E.22:POSTING AND FILLING VACANT POSITIONS</h3>
        <p>E.22.1In this Article &quot;vacancy&quot; means a newly created position or an existing position vacated by the incumbent which the Board intends to fill. All vacancies of 20 days or more shall be posted. All teachers in the District are eligible to apply for all vacancies.  During spring post and fill (May to June 30), when a teacher has applied for and accepted an assignment for the coming year, that teacher will not be eligible to apply for further assignments which are posted unless the assignments are continuing or will increase their FTE for the following year.</p>
        <p>a.Positions that become vacant during the school year shall be filled with a teacher on a temporary contract except for a position requiring special skills that may be filled as continuing in accordance with Article E.22.</p>
        <p>b.In such circumstances where the Board intends to fill such a vacancy on a continuing basis during the school year the BVTU president will be consulted before any decision is made by the Board.</p>
        <p>c.Where a position has been posted on a temporary basis and a decision has been made to fill this position on a continuing basis, the position shall be re-posted in the District.</p>
        <p>d.The fact that a local teacher applying for a position that will be filled on a continuing basis causes disruption to that teacher&#x27;s school shall not be a factor influencing consideration of their application.</p>
        <p>E.22.2It is recognized that school-based staffing re-alignment occurs in schools on an ongoing basis to meet the evolving needs of the school.</p>
        <p>a.The Principal/Vice-Principal in consultation with the teacher representatives elected by the Staff to the Teacher Planning Committee, will develop annually or as necessary:</p>
        <p>i.a tentative timetable.</p>
        <p>ii.proposed teaching assignments for the staff eligible to participate in the re-alignment process of the school.</p>
        <p>1.A teacher who is not satisfied with a proposed assignment in a school may request reconsideration of their assignment from the Principal/Vice-Principal in consultation with the representatives elected by the staff.</p>
        <p>iii.a list of all anticipated vacancies indicating subject area(s), and grade level(s), necessary qualifications and a summary of any special requirements for the position.</p>
        <p>b.There shall be no limitation on internal staffing realignment where a staff member&#x27;s assignment is altered in a minor way.</p>
        <p>c.Continuing appointment teachers who are filling a temporary assignment will be included in the staff re-alignment process at the school at which they last held a continuing assignment.</p>
        <p>d.When a teacher on a continuing appointment does not return to the school where they held the last continuing assignment after a maximum of one year, that teacher will be deemed to have left that school on a permanent basis and will not be included in the staff re-alignment process of that school, except for teachers assigned to specific positions agreed between the BVTU and the Board; such assignments to have a maximum duration of two years.</p>
        <p>e.Teachers shall have at least 24 hours, which will include one working day, to consider changes in assignment.</p>
        <p>E.22.3All vacancies shall be posted on the School District 54 website for a minimum of three (3) school days as soon as they are identified, and a copy shall be sent via email to the BVTU Office. Applications shall be made by email or in person.</p>
        <p>E.22.4Vacancies arising during the summer vacation shall be posted on the School District 54 website for a minimum of five (5) working days and sent via email to the BVTU office.  Applications shall be made by email or in person.</p>
        <p>E.22.5In this Agreement necessary qualifications are defined as a reasonable expectation, based on the teaching certification, training, education, experience and capability of a teacher, that that teacher will be able to perform the duties of the position in a satisfactory manner.</p>
        <p>E.22.6Where two or more teachers have equal qualifications, the teacher with the greatest seniority shall be awarded the position.</p>
        <p>E.22.7In filling vacant positions, local teachers with the necessary qualifications shall be considered before applicants from outside the District.</p>
        <p>The Board will proceed as follows:</p>
        <p>a.Teachers returning from leave of absence of 1 year or less to the same position.</p>
        <p>b.Continuing teachers applying for the vacancy, and teachers returning from leaves of more than one year.</p>
        <p>c.Board initiated transfers.</p>
        <p>d.Teachers on the recall list.</p>
        <p>e.Temporary teachers.</p>
        <p>E.22.8In filling the remaining positions the Board will give priority to applications as follows:</p>
        <p>a.Teachers-Teaching-On-Call applying for a position;</p>
        <p>b.Other applicants.</p>
        <p>E.22.9An applicant for appointment shall be entitled to rely on an offer of appointment made by the Superintendent, Assistant Superintendent or Principal/Vice-Principal and with respect to the terms of such offer.</p>
        <p>a.The Board shall confirm all offers in writing as soon as possible.</p>
        <p>b.An offer of appointment shall be deemed to have been accepted when verbally agreed to and when written acceptance has been mailed to the Board.</p>
        <p>E.22.10The parties agree that the selection and assignment of teachers is the responsibility of the Board, subject to the provisions of this Agreement.</p>
        <p>E.22.11Teachers who hold comparable positions may apply to the Superintendent to exchange their positions for a definite period of time, provided that the exchange does not constitute an increase or decrease in appointment.</p>
      </div>

      <div class="ca-article" id="article-e-23-transfers-and-assignments">
        <h3>ARTICLE E.23:TRANSFERS AND ASSIGNMENTS</h3>
        <p>E.23.1Transfers Initiated by the Board</p>
        <p>a.The Board has the right to transfer for sound educational reasons. Transfers under this article shall not be initiated for arbitrary or disciplinary reasons and the reasons for such transfers shall be stated in writing.</p>
        <p>b.If, for reasons of declining enrollment, a transfer is to be initiated from the staff of a school, and unless a more senior teacher agrees to be transferred, the transfer shall be effected in reverse order of district seniority of teachers in that school, provided that the teachers retained on the active teaching staff of the school possess the necessary qualifications for the positions available.</p>
        <p>c.If, for reasons of declining enrollment, a transfer is to be initiated which involves a change of community of residence, and unless a more senior teacher agrees to be transferred, the transfer shall be effected in reverse order of district seniority of teachers in that community of residence, provided that the teachers retained on the active teaching staff in the community of residence possess the necessary qualifications for the positions available.</p>
        <p>d.A Board official intending to recommend transfer of a teacher shall meet with and inform the teacher of the nature of the proposed transfer, and the reasons for it at least one (1) month prior to the recommendation being placed before the Board except in exceptional circumstances.</p>
        <p>e.Prior to receipt of written notification of a transfer, a teacher may request a meeting with the Superintendent or designate to discuss the transfer. The teacher may be accompanied by a member of the BVTU.</p>
        <p>f.Any Board initiated transfer that involves a substantive change of assignment involving teaching outside the general area of qualification and experience shall be considered for appropriate retraining and/or familiarization.</p>
        <p>g.Transfers initiated by the Board shall be proceeded with prior to May 31 for the next school year, except in exceptional circumstances.</p>
        <p>h.Any teacher who has been transferred without agreement shall not be subject to further transfer without agreement for three (3) school years.</p>
        <p>i.A teacher who is transferred for reasons of projected enrollment decline, position reduction or other such factor shall have the opportunity of returning to the position previously held in the event that the projected factors do not actually materialize and this is known before the start of the next school year.</p>
        <p>j.Any grievance concerning a transfer initiated by the Board shall be referred directly to Step 2 of the Grievance Procedure.</p>
        <p>k.If the transfer is Board initiated and involves a move from Houston to Smithers or Smithers to Houston, reasonable moving costs or the equivalent in mileage allowance shall be borne by the Board.</p>
      </div>

      <div class="ca-article" id="article-e-24-evaluation-of-teachers-effectiveness">
        <h3>ARTICLE E.24:EVALUATION OF TEACHERS EFFECTIVENESS</h3>
        <p>E.24.1The evaluation of teacher effectiveness shall be for the purpose of determining and improving instructional quality.</p>
        <p>E.24.2Evaluations shall be conducted on the following basis:</p>
        <p>a.A teacher evaluation may be conducted at any time but it is expected that a teacher new to the District will be evaluated in their first year of employment and other teachers at least once each five years, whenever practicable.</p>
        <p>b.The general criteria of effectiveness shall relate to those aspects of the teaching/learning situation which can reasonably be expected to be the teacher&#x27;s responsibility and over which the teacher has control.</p>
        <p>c.Availability of resources, aspects of the teaching assignment, and teacher qualifications which may impact negatively upon teacher effectiveness, shall be mentioned.</p>
        <p>d.When a report is to be written on a teacher:</p>
        <p>i.The teacher shall be given at least 2 weeks notification that a written evaluation will be conducted.</p>
        <p>ii.The administrator will review with the teacher the evaluation process and criteria to be followed.</p>
        <p>iii.The teacher shall have the opportunity to select at least half the observation times.</p>
        <p>e.Formal evaluation will not be undertaken in the first twenty teaching days in a new assignment.</p>
        <p>f.A teacher may request an alternate evaluator.</p>
        <p>g.Reports normally will be based upon a minimum of three (3) and a maximum of six (6) classroom visits unless there is agreement otherwise and shall include a review of the teacher&#x27;s performance in carrying out their teaching responsibilities. After each visit suggestions for improvement, where needed, will be given in writing.</p>
        <p>h.When the report has been prepared it shall be discussed in draft form with the teacher prior to being finalized.</p>
        <p>i.The teacher may submit a written response to the report which will be filed with all copies of the report.</p>
        <p>j.Immediately after the first less than satisfactory report, a plan of assistance will be formulated and implemented to assist the teacher in overcoming the deficiencies. A reasonable period of time for improvement of performance shall be provided.  The teacher will be advised of their right to have a BVTU representative to assist in these matters.</p>
        <p>k.All reports on the work of a teacher shall be in writing and no supplementary oral or written report shall be made.</p>
        <p>l.The content of a report shall be based solely on the personal observations of teaching by the evaluator.</p>
        <p>m.Non involvement in extra-curricular activities, participation in union activities or matters not directly related to teaching duties are outside the scope of evaluating and reporting on the work of a teacher.</p>
        <p>n.Reports shall reflect any discrepancy between the teaching assignment and professional training. Preferences of teaching subject and grades will be considered.</p>
        <p>[Note: See also Article C.3 Evaluation]</p>
      </div>

      <div class="ca-article" id="article-e-25-no-discrimination">
        <h3>ARTICLE E.25:NO DISCRIMINATION</h3>
        <p>E.25.1The employer and the BVTU will comply with the Charter of Rights and Freedoms of Canada and the Human Rights Code of British Columbia.</p>
        <p>E.25.2There will be no discrimination against any employee covered by this Agreement or against any member of the bargaining unit on the basis of age, race, creed, colour, ancestry, sex, religion, physical disability, mental disability, sexual orientation, gender identity or expression, political affiliation, national origin, marital or parental status, or because they are participating in the activities of the BVTU, or involved in any procedure to interpret or enforce the provisions of this Collective Agreement.</p>
      </div>

      <div class="ca-article" id="article-e-26-personnel-files">
        <h3>ARTICLE E.26:PERSONNEL FILES</h3>
        <p>E.26.1There shall be only one personnel file for each teacher, maintained at the district office. Any school file relating to a teacher shall be destroyed when the teacher or the principal leaves that school.</p>
        <p>E.26.2After receiving a request from a teacher, the Superintendent, with respect to the district file, or the Principal of the school, with respect to any school file, shall forthwith grant access to that teacher&#x27;s file.</p>
        <p>E.26.3An appropriate School Board official shall be present when a teacher reviews their file, and the teacher may be accompanied by an individual of their choosing.</p>
        <p>E.26.4Only factual information and material relevant to the employment of the teacher shall be maintained in the personnel or school file. Adverse material in either file will be copied to the teacher at the time of filing. Adverse material in either file will be presented to, and signed by, the teacher at the time of filing. Signing of documents will not be considered an agreement of the content but simply acknowledgement of receipt. The teacher shall have the opportunity, in response, to place a statement in their file indicating disagreement and/or rebuttal to this material.</p>
        <p>E.26.5The district personnel files shall be confidential and accessible only to appropriate officials.</p>
        <p>E.26.6A teacher may request removal of material from their personnel or school file on the basis that it is not factually correct. In the event that there is not agreement to remove the specified material, the teacher may file a grievance.</p>
        <p>E.26.7Where a letter of reprimand to a teacher is placed on file as a consequence of disciplinary action, and where there has been no recurrence of the cause of that disciplinary action, then, at the request of the teacher, the letter of reprimand shall be removed three years after the filing.</p>
        <p>E.26.8The provisions of E.26.7 above do not apply in regard to the abuse of children or serious misconduct.</p>
      </div>

      <div class="ca-article" id="article-e-27-race-relations">
        <h3>ARTICLE E.27:RACE RELATIONS</h3>
        <p>E.27.1The Board will not condone or tolerate any expression of racism. Any written allegation of racism within the district will be dealt with in the same manner as Article E.2 on Harassment.</p>
      </div>

      <div class="ca-article" id="article-e-28-student-parent-appeals">
        <h3>ARTICLE E.28:STUDENT/PARENT APPEALS</h3>
        <p>E.28.1Where a pupil or parent/guardian files an appeal under the School Act (Section 11) of a decision of a BVTU member, upon receipt of notice of appeal, the employee and the BVTU shall be notified, be provided with a copy of the notice of appeal, and have the opportunity to provide a written reply to any allegations contained in the appeal.</p>
        <p>E.28.2The teacher shall be entitled to attend any meeting in connection with the appeal where the appellant is present and shall have the right to representation by the BVTU.</p>
        <p>E.28.3The Board shall delay hearing any appeal if the student and/or parent/guardian of the student have not first discussed the decision with the employee(s) who made the decision.</p>
        <p>E.28.4The Board shall not make a decision on an appeal unless the employee concerned has had an opportunity to present their side of the issue to the Board.</p>
        <p>E.28.5Decisions of the Board relative to such appeals shall be communicated to all parties forthwith.</p>
        <p>E.28.6No decision or by-law of the Board with respect to such appeals shall abrogate any right, benefit, or process contained in this agreement.</p>
      </div>

      <div class="ca-article" id="article-e-29-falsely-accused-employee-assistance">
        <h3>ARTICLE E.29:FALSELY ACCUSED EMPLOYEE ASSISTANCE</h3>
        <p>E.29.1When a teacher has been accused of child abuse or sexual misconduct in the course of exercising their duties as an employee of the Board, and</p>
        <p>a.an investigation by the Board has concluded that the accusation is not true, or</p>
        <p>b.the teacher is acquitted of criminal charges in relation to the accusation and there is no disciplinary action by the Board, or</p>
        <p>c.an arbitrator considering discipline or dismissal of the teacher finds the accusation to be false,</p>
        <p>the teacher and the teacher&#x27;s family shall be entitled to all reasonable specialist counselling and/or medical assistance to deal with negative effects of the allegations.</p>
        <p>E.29.2The teacher shall be assisted to the fullest possible extent by the Board in assuring successful return to teaching duties. This shall include any approved period of leave of absence with pay, first priority for vacant positions requested by the teacher, for which the teacher is qualified, and where requested by the teacher, provision of factual information to parents by the Board.</p>
        <p>SECTION FPROFESSIONAL RIGHTS</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section F: Professional Development</h2>
      <div class="ca-article" id="article-f-1-professional-development-funding">
        <h3>ARTICLE F.1:PROFESSIONAL DEVELOPMENT FUNDING</h3>
        <p>[Articles F.1.1 and F.1.2 are not applicable in School District #54 (Bulkley Valley).  See Article F.21.3.]</p>
        <p>F.1.3.Upon ratification in each subsequent round of bargaining, where Article F.1.1 does not already apply, then Article F.1.2 will be implemented as part of the melding process.</p>
        <p>LOCAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-f-21-professional-development-funding-and-control">
        <h3>ARTICLE F.21:PROFESSIONAL DEVELOPMENT FUNDING AND CONTROL</h3>
        <p>F.21.1The Board and the BVTU agree that the professional development of teachers covered by this clause shall include programs, services, courses and funding which will promote and foster teacher professional growth and development.</p>
        <p>F.21.2The Board shall establish a fund which, together with funds provided by the BVTU shall be used for the purpose of promoting the professional development of teachers in the school district.</p>
        <p>F.21.3The Board&#x27;s share will be $240 per FTE.</p>
        <p>F.21.4The Board shall contribute one hundred (100) days Teacher-Teaching-On-Call time to assist with professional development, and any Teacher-Teaching-On-Call requirements beyond one hundred (100) days shall be borne by the BVTU or be a charge against the shared fund.</p>
        <p>F.21.5The professional development fund and policies shall be controlled and administered by the professional development committee.</p>
        <p>a.The financial accounts shall be open for review by the Board and the BVTU at any time.</p>
        <p>b.The professional development committee shall present an accounting of the disbursements from the fund to the Secretary Treasurer on October 7 and January 16 each year for the preceding period.</p>
        <p>c.The closing balance shall be forwarded from one fiscal year to the next.</p>
        <p>F.21.6The professional development committee shall be chaired by the BVTU&#x27;s professional development chairperson and shall comprise:</p>
        <p>a.one teacher elected representative from each school,</p>
        <p>b.one Principal/Vice-Principal, and</p>
        <p>c.the professional development chairperson and co-chairperson of the BVTU.</p>
        <p>d.the Board of Education shall be invited to have a trustee as a representative on the committee.</p>
        <p>F.21.7The Board and the BVTU shall share on a 50/50 basis the cost of up to twenty (20) teacher teaching on call days for providing release time for the professional development chairperson.</p>
        <p>F.21.8The professional development fund will not be required to finance curriculum implementation nor educational change in the district. This does not preclude a teacher&#x27;s accessing the fund for a personally initiated in-service relevant to the above.</p>
      </div>

      <div class="ca-article" id="article-f-22-professional-development-days">
        <h3>ARTICLE F.22:PROFESSIONAL DEVELOPMENT DAYS</h3>
        <p>F.22.1The number of Professional Development days shall be determined by this Agreement. The dates will be determined annually by June 15th for the following year by the Board and the Pro-D Committee.</p>
        <p>F.22.2All of the Professional Development days shall be used for the purpose of teacher professional development with the possible exception of one day which may be used for a purpose determined by the majority of the staff in a school.</p>
        <p>F.22.3Professional Development days shall be considered as instructional days for salary purposes.</p>
        <p>F.22.4The professional activities developed for Professional Development days shall be organized by either:</p>
        <p>a.the school staff or where appropriate the School Professional Development Committee in collaboration with the Principal.</p>
        <p>b.the district Professional Development Committee, or</p>
        <p>c.the Board in conjunction with the BVTU.</p>
        <p>F.22.5A teacher who has identified an alternate Professional Development activity more relevant to their own needs will identify that activity to their Principal/Vice-Principal and will normally be allowed to attend.</p>
      </div>

      <div class="ca-article" id="article-f-23-curriculum-educational-change-implementation">
        <h3>ARTICLE F.23:CURRICULUM/EDUCATIONAL CHANGE IMPLEMENTATION</h3>
        <p>F.23.1When new curriculum and/or educational change is being introduced to the School District, an Implementation Committee shall be struck. It is the function of the Committee to make recommendations to the Board. It shall consist of representatives of the Board and the BVTU with the BVTU having at least equal representation.</p>
        <p>F.23.2The Committee shall elect its own Chairperson.</p>
        <p>F.23.3The deliberations of the Committee shall include but not be limited to the following:</p>
        <p>a.In service requirements.</p>
        <p>b.Time considerations.</p>
        <p>c.Adequate resources.</p>
        <p>d.Criteria for measuring the success of the program or activity.</p>
        <p>e.Appropriate teacher retraining.</p>
        <p>F.23.4The teacher shall be recognized as the key agent of change.</p>
      </div>

      <div class="ca-article" id="article-f-24-school-assessment-and-accreditation">
        <h3>ARTICLE F.24:SCHOOL ASSESSMENT AND ACCREDITATION</h3>
        <p>F.24.1A periodic review of a school&#x27;s effectiveness in carrying out its programs is recognized by the Board and BVTU as being essential to the provision of quality educational services.</p>
        <p>F.24.2The Board and the BVTU agree that the following terms and conditions constitute the provisions under which the school accreditation process shall occur in the schools of the district.</p>
        <p>a.Within the provisions of the School Act, the accreditation process shall occur only in those elementary and middle schools where the 2/3 majority decision of the school staff wishes to undertake the accreditation.</p>
        <p>b.The purpose of school accreditation is to provide school staffs with an opportunity to develop, in cooperation with their local communities, the best possible school climate and programs.</p>
        <p>F.24.3In order that school assessments and accreditations can be carried out, it is recognized that there must be appropriate release time for teachers and increased clerical time.</p>
        <p>F.24.4Appropriate increases in resources necessary to effectively carry out these tasks shall be jointly determined by District Staff, Principal/Vice-Principal(s) and School Staff.</p>
        <p>a.If a school staff does not achieve satisfactory conditions for undertaking an assessment, the assessment shall not proceed.</p>
        <p>F.24.5The use of an existing professional development day shall be decided upon by a 2/3 majority vote of the teachers and Principal/Vice-Principal(s) of the school.</p>
        <p>F.24.6If an external review team is required, at least one teacher selected from an appropriate assignment level (E.g. elementary, secondary) shall be on such a team.</p>
        <p>F.24.7Ministry funds targeted for accreditation/assessment activities and the follow-up activities in a school shall be made available to the school.</p>
        <p>F.24.8Notwithstanding the provisions of Article F.24.2.a and F.24.4.a, the accreditation can be postponed for a maximum of two school years.</p>
      </div>

      <div class="ca-article" id="article-f-25-professional-autonomy">
        <h3>ARTICLE F.25:PROFESSIONAL AUTONOMY</h3>
        <p>F.25.1Teachers shall, within the bounds of the prescribed curriculum, and consistent with effective educational practice, have individual professional autonomy in determining the methods of instruction, and the planning and presentation of course materials in the classes of pupils to whom they are assigned.</p>
        <p>SECTION GLEAVES OF ABSENCE</p>
      </div>

      <h2 class="content-block" style="font-size:1.3rem;font-weight:700;color:var(--primary);margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);">Section G: Leaves of Absence</h2>
      <div class="ca-article" id="article-g-1-portability-of-sick-leave">
        <h3>ARTICLE G.1:PORTABILITY OF SICK LEAVE</h3>
        <p>1.The employer will accept up to sixty (60) accumulated sick leave days from other school districts in British Columbia, for employees hired to or on exchange in the district.</p>
        <p>2.An employee hired to or on exchange in the district shall accumulate and utilize sick leave credit according to the provisions of the Collective Agreement as it applies in that district.</p>
        <p>Sick Leave Verification Process</p>
        <p>The new school district shall provide the employee with the necessary verification form at the time the employee receives confirmation of employment in the school district.</p>
        <p>An employee must initiate the sick leave verification process and forward the necessary verification forms to the previous school district(s) within one hundred and twenty (120) days of commencing employment with the new school district.</p>
        <p>The previous school district(s) shall make every reasonable effort to retrieve and verify the sick leave credits which the employee seeks to port.</p>
        <p>(Note: Any provision that provides superior sick leave portability shall remain part of the Collective Agreement.)</p>
        <p>[See also Article G.22 Teacher Illness for general sick leave use and accrual.]</p>
      </div>

      <div class="ca-article" id="article-g-2-compassionate-care-leave">
        <h3>ARTICLE G.2:COMPASSIONATE CARE LEAVE</h3>
        <p>For the purposes of this article “family member” means:</p>
        <p>in relation to an employee:</p>
        <p>a member of an employee&#x27;s immediate family;</p>
        <p>an employee&#x27;s aunt or uncle, niece or nephew, current or former foster parent, ward or guardian;</p>
        <p>the spouse of an employee&#x27;s sibling or step-sibling, child or step-child, grandparent, grandchild, aunt or uncle, niece or nephew, current or former foster child or guardian;</p>
        <p>in relation to an employee&#x27;s spouse:</p>
        <p>the spouse&#x27;s parent or step-parent, sibling or step-sibling, child, grandparent, grandchild, aunt or uncle, niece or nephew, current or former foster parent, or a current or former ward; and</p>
        <p>anyone who is considered to be like a close relative regardless of whether or not they are related by blood, adoption, marriage or common law partnership.</p>
        <p>Upon request, the employer shall grant an employee Compassionate Care Leave pursuant to Part 6 of the BC Employment Standards Act for a period up to eight (8) weeks or such other period as provided by the Act. Such leave shall be taken in units of one or more weeks.</p>
        <p>Compassionate care leave supplemental employment insurance benefits:</p>
        <p>When an employee is eligible to receive employment insurance benefits, the employer shall pay the employee:</p>
        <p>one hundred percent (100%) of the employee’s current salary for the first week of the leave, and</p>
        <p>for an additional eight (8) weeks, one hundred percent (100%) of the employee’s current salary less any amount received as EI benefits.</p>
        <p>Current salary shall be calculated as 1/40 of annual salary where payment is made over ten months or 1/52 of annual salary where payment is made over twelve months.</p>
        <p>4.A medical certificate may be required to substantiate that the purpose of the leave is for providing care or support to a family member having a serious medical condition with a significant risk of death within 26 weeks.</p>
        <p>5.The employee’s benefit plans coverage will continue for the duration of the compassionate care leave on the same basis as if the employee were not on leave.</p>
        <p>6.The employer shall pay, according to the Pension Plan regulations, the employer portion of the pension contribution where the employee elects to buy back or contribute to pensionable service for part or all of the duration of the compassionate care leave.</p>
        <p>7.Seniority shall continue to accrue during the period of the compassionate care leave.</p>
        <p>8.An employee who returns to work following a leave granted under this article shall be placed in the position the employee held prior to the leave or in a comparable position.</p>
        <p>(Note: The definition of “family member” in Article G.2.1 above, shall incorporate any expanded definition of “family member” that may occur through legislative enactment.)</p>
        <p>[See also Article G.28 Illness in the Family for short term leave.]</p>
      </div>

      <div class="ca-article" id="article-g-3-employment-standards-act-leaves">
        <h3>ARTICLE G.3:EMPLOYMENT STANDARDS ACT LEAVES</h3>
        <p>In accordance with the BC Employment Standards Act (the “Act”), the Employer will grant the following leaves:</p>
        <p>Section 52Family Responsibility Leave</p>
        <p>Section 52.11Critical Illness or Injury Leave</p>
        <p>Section 52.5Leave Respecting Domestic or Sexual Violence</p>
        <p>Note: In the event that there are changes to the Employment Standards Act with respect to the Part 6 Leaves above, the legislated change provisions (A.9) will apply to make the necessary amendments to this provision.</p>
      </div>

      <div class="ca-article" id="article-g-4-bereavement-leave">
        <h3>ARTICLE G.4:BEREAVEMENT LEAVE</h3>
        <p>1.Five (5) days of paid leave shall be granted in each case of death of a member of the employee’s immediate family.</p>
        <p>For the purposes of this article “immediate family” means:</p>
        <p>a.the spouse (including common-law and same-sex partners), child and step-child (including in-law), parent (including in-law), guardian, sibling and step-siblings (including in-law), current ward, grandchild or grandparent of an employee (including in-law), and</p>
        <p>b.any person who lives with an employee as a member of the employee’s family.</p>
        <p>2.Not applicable in School District No. 54 (Bulkley Valley).  See Article G.4.5.</p>
        <p>3.In addition to leave provided in Article G.4.1 and G.4.2, the superintendent may grant unpaid leave for a family member. Additional leave shall not be unreasonably denied. For the purpose of Article G.4.3 “family member” means:</p>
        <p>a.in relation to an employee:</p>
        <p>i.a member of an employee&#x27;s immediate family;</p>
        <p>ii.an employee’s aunt or uncle, niece or nephew, current or former foster parent, former ward or guardian or their spouses;</p>
        <p>b.in relation to an employee&#x27;s spouse or common-law partner or same-sex partner:</p>
        <p>i.the spouse&#x27;s parent or step-parent, sibling or step-sibling, child, grandparent, grandchild, aunt or uncle, niece or nephew, current or former foster parent, or a current or former ward; and</p>
        <p>c.anyone who is considered to be like a close relative regardless of whether or not they are related by blood, adoption, marriage or common law partnership.</p>
        <p>4.Any and all superior provisions contained in the Previous Collective Agreement shall remain part of the Collective Agreement.</p>
        <p>Local Provisions:</p>
        <p>G.4.5.Where leave has been granted under Article G.4.1, in the event that travel arrangements or special circumstances make it necessary for the teacher to be absent for more than five (5) days, a written request for extension of leave must be made to the Superintendent. Providing the request for extension of leave is granted, up to an additional five (5) days leave shall be available and may be taken without pay or be charged to accumulated sick leave. For leave for travel arrangements, two (2) days of such additional five (5) days are provided with pay.</p>
      </div>

      <div class="ca-article" id="article-g-5-unpaid-discretionary-leave">
        <h3>ARTICLE G.5:UNPAID DISCRETIONARY LEAVE</h3>
        <p>Article G.5.1 through G.5.3 is not applicable in School District No. 54 (Bulkley Valley).</p>
        <p>Local Provisions:</p>
        <p>G.5.4.Leave of up to five (5) days shall be granted to a teacher in each school year to use at their discretion.  Such leave is without pay.  Such leave will not be cumulative and is not to be used to extend Christmas, Spring Break or Summer Holidays, but under special circumstances an application may be made to the Superintendent.</p>
        <p>[Note: See also Article G.33 Long Service Discretionary.]</p>
      </div>

      <div class="ca-article" id="article-g-6-leave-for-union-business">
        <h3>ARTICLE G.6:LEAVE FOR UNION BUSINESS</h3>
        <p>[Note: Article G.6.1.b applies for the purposes of Article A.10 only. Article G.6.1.a and G.6.2 through G.6.10 do not apply in S.D. No. 54 (Bulkley Valley). See Article A.22 BVTU Rights.]</p>
        <p>1.b.‘Full employ’ means the employer will continue to pay the full salary, benefits, pensions contributions and all other contributions they would receive as if they were not on leave. In addition, the member shall continue to be entitled to all benefits and rights under the Collective Agreement, at the cost of the employer where such costs are identified by the Collective Agreement.</p>
      </div>

      <div class="ca-article" id="article-g-7-ttocs-conducting-union-business">
        <h3>ARTICLE G.7:TTOCs CONDUCTING UNION BUSINESS</h3>
        <p>1.Where a Teacher Teaching on Call (TTOC) is authorized by the local union or BCTF to conduct union business during the work week, the TTOC shall be paid by the employer according to the Collective Agreement.</p>
        <p>2.Upon receipt, the union will reimburse the employer the salary and benefit costs associated with the time spent conducting union business.</p>
        <p>3.Time spent conducting union business will not be considered a break in service with respect to payment on scale.</p>
        <p>4.Time spent conducting union business will be recognized for the purpose of seniority and experience recognition up to a maximum of 40 days per school year.</p>
      </div>

      <div class="ca-article" id="article-g-8-ttocs-conducting-union-business-negotiating-team">
        <h3>ARTICLE G.8:TTOCs – CONDUCTING UNION BUSINESS NEGOTIATING TEAM</h3>
        <p>Time spent conducting union business on a local or provincial negotiating team will be recognized for the purpose of seniority and experience recognition.</p>
      </div>

      <div class="ca-article" id="article-g-9-temporary-principal-vice-principal-leave">
        <h3>ARTICLE G.9:TEMPORARY PRINCIPAL / VICE-PRINCIPAL LEAVE</h3>
        <p>A teacher shall be granted leave upon request to accept a position if the teacher is:</p>
        <p>replacing a Principal or Vice-Principal in the school district who is on leave or has departed unexpectedly; and,</p>
        <p>their appointment as Principal or Vice-Principal does not extend past a period of one (1) year (12 months).</p>
        <p>Upon return from leave, the employee shall be assigned to the same position or, when the position is no longer available, a similar position.</p>
        <p>The vacated teaching position will be posted as a temporary position during this period.</p>
        <p>Where there are extenuating personal circumstances that extend the leave of the Principal or Vice-Principal, the vacated teaching position may be posted as temporary for an additional year (12 months).</p>
        <p>Teachers granted leave in accordance with this Article who have a right to return to their former teaching position will not be assigned or assume the following duties:</p>
        <p>Teacher Evaluation</p>
        <p>Teacher Discipline</p>
        <p>Should a leave described above extend beyond what is set out in paragraphs 1, 3 and 4, the individual’s former teaching position will no longer be held through a temporary posting and will be filled on a continuing basis, unless a mutually agreed to extension to the leave with a right of return to a specific position is provided for in the local Collective Agreement or otherwise agreed to between the parties.</p>
      </div>

      <div class="ca-article" id="article-g-10-teachers-returning-from-parenting-and-compassionate-leaves">
        <h3>ARTICLE G.10:TEACHERS RETURNING FROM PARENTING AND COMPASSIONATE LEAVES</h3>
        <p>Teachers granted the following leaves in accordance with the Collective Agreement:</p>
        <p>Pregnancy Leave (Employment Standards Act [ESA])</p>
        <p>Parental Leave (Employment Standards Act [ESA])</p>
        <p>Extended Parental / Parenthood Leave (beyond entitlement under Employment Standards Act [ESA])</p>
        <p>Adoption Leave (beyond entitlement under Employment Standards Act [ESA])</p>
        <p>Compassionate Care Leave</p>
        <p>will be able to return to their former teaching position in the school that they were assigned to for a maximum of one (1) year (twelve months) from the time the leave of absence commenced. The teacher’s position will be posted as a temporary vacancy. Upon return from leave, the employee will be assigned to the same position or, if the position is no longer available, a similar position.</p>
      </div>

      <div class="ca-article" id="article-g-11-cultural-leave-for-aboriginal-employees">
        <h3>ARTICLE G.11:CULTURAL LEAVE FOR ABORIGINAL EMPLOYEES</h3>
        <p>The Superintendent of Schools or their designate, may grant five (5) paid days per year leave with seven (7) days written notice from the employee to participate in Aboriginal Cultural event(s). Such leave shall not be unreasonably denied.</p>
      </div>

      <div class="ca-article" id="article-g-12-maternity-pregnancy-leave-supplemental-employment-benefits">
        <h3>ARTICLE G.12:MATERNITY/PREGNANCY LEAVE SUPPLEMENTAL EMPLOYMENT BENEFITS</h3>
        <p>When an employee takes maternity leave pursuant to Part 6 of the Employment Standards Act, the employer shall pay the employee:</p>
        <p>One hundred percent (100%) of their current salary for the first week of the leave; and</p>
        <p>When the employee is in receipt of Employment Insurance (EI) maternity benefits, the difference between the amount of EI maternity benefits received by the teacher and one hundred percent (100%) of their current salary, for a further fifteen (15) weeks.</p>
        <p>[Note: In SD 54, for employees who do not qualify for EI maternity benefits, G.12.1 does not apply. See G.12.2 below.]</p>
        <p>Local Provisions:</p>
        <p>G.12.2When an employee takes maternity leave pursuant to Part 6 of the Employment Standards Act, and the employee is not in receipt of EI maternity benefits, the employer shall pay the employee 75 percent of her current salary for the first two weeks of the leave.</p>
        <p>[See also Article G.23 Maternity for leave provisions.]</p>
        <p>LOCAL ARTICLES</p>
      </div>

      <div class="ca-article" id="article-g-21-applications">
        <h3>ARTICLE G.21:APPLICATIONS</h3>
        <p>G.21.1All requests for leave-of-absence shall be made to the Superintendent of Schools or in their absence the Assistant Superintendent and shall be submitted on the Leave-of-Absence Request Form.</p>
        <p>G.21.2A teacher granted a leave for less than one school year, and returning prior to the end of that school year shall return to the position they vacated.</p>
        <p>G.21.3A teacher granted leave for a school year shall return to the position they vacated. If that position is not available in its original form, they shall return to a similar position in the same school provided such a position is available.</p>
        <p>G.21.4When a staff re-alignment under E.22.2 takes place, the teacher who will be returning from a year&#x27;s leave shall be included in that re-alignment.</p>
      </div>

      <div class="ca-article" id="article-g-22-teacher-illness">
        <h3>ARTICLE G.22:TEACHER ILLNESS</h3>
        <p>G.22.1Teachers under contract with the Board will be credited on September first of every year with the number of sick days they would be entitled to for the whole of that year plus any accumulated sick days from previous years. All of these days are available for use by the teacher at any time during the current school year, after which time absence for illness becomes leave without pay.</p>
        <p>G.22.2Fifteen (15) sick days shall be granted each year.</p>
        <p>G.22.3The number of sick days used shall not exceed one hundred and twenty (120) days in any one school year.</p>
        <p>[See also Article G.1 Portability of Sick Leave.]</p>
      </div>

      <div class="ca-article" id="article-g-23-maternity">
        <h3>ARTICLE G.23:MATERNITY</h3>
        <p>G.23.1Maternity leave shall be granted to teachers according to the provisions of the Employment Standards Act - Part 6.  Part 6 shall apply. Such leave shall be without pay except as provided for in Article G.12.</p>
        <p>[See also Article G.12 Maternity/Pregnancy Leave Supplemental Employment Benefits for provisions on supplemental employment benefits.]</p>
        <p>G.23.2Extended maternity leave shall be granted upon request for the remainder of the current school year plus a maximum of two further school years with a return to coincide with the commencement of a term or semester.</p>
        <p>G.23.3During an extended maternity leave, an employee returning to work will not lose seniority accumulated prior to the commencement of the leave, nor will seniority accumulate during the leave.</p>
        <p>G.23.4Sick leave provisions will neither be lost nor accumulated by a teacher while on an extended maternity leave.</p>
        <p>G.23.5Teachers returning to work within the same school year shall return to the position they left and teachers returning in a subsequent year shall be placed in a comparable position.</p>
        <p>G.23.6In the case of an incomplete pregnancy or death of the child, the teacher may return to duty earlier than provided in the agreed-upon leave.</p>
      </div>

      <div class="ca-article" id="article-g-24-parental-leave">
        <h3>ARTICLE G.24:PARENTAL LEAVE</h3>
        <p>G.24.1Parental Leave shall be granted upon request according with the provisions of the Employment Standards Act - Part 6 (including any provisions that the Act provides to adoptive parents). Such leave shall be without pay.</p>
        <p>G.24.2Extended Parental leave shall be granted upon request for the remainder of the current school year plus a maximum of two further school years with a return to coincide with the commencement of a term or semester.</p>
        <p>G.24.3During an extended parental leave, an employee returning to work will not lose seniority accumulated prior to the commencement of the leave, nor will seniority accumulate during the leave.</p>
        <p>G.24.4Sick leave provisions will neither be lost nor accumulated by a teacher while on an extended parental leave.</p>
        <p>G.24.5Teachers returning to work within the same school year shall return to the position they left and teachers returning in a subsequent year shall be placed in a comparable position.</p>
      </div>

      <div class="ca-article" id="article-g-25-parenthood">
        <h3>ARTICLE G.25:PARENTHOOD</h3>
        <p>G.25.1Parenthood leave shall be granted to either parent, without pay, for a stated period of time up to a maximum of twenty (20) school months.</p>
        <p>G.25.2The request for such leave shall set out the period of leave requested and the employee&#x27;s preferred return date, September 1st or January 1st, or the beginning of second semester at secondary schools.</p>
        <p>G.25.3During Parenthood leave, a teacher will accrue seniority pursuant to Article C.2.9.e.iii,</p>
        <p>G.25.4Sick leave provisions will neither be lost nor accumulated by a teacher while on a parenthood leave.</p>
      </div>

      <div class="ca-article" id="article-g-26-paternity">
        <h3>ARTICLE G.26:PATERNITY</h3>
        <p>G.26.1Paternity leave of up to two (2) days will be granted when a child is born while the partner has teaching duties. Paternity leave will be granted with pay upon application to the Superintendent.</p>
        <p>G.26.2Paternity leave is activated when the teacher has teaching duties on the day of the birth or when the mother and/or child return home. No case of paternity leave shall be granted later than two weeks after the birth of the child unless the child does not return home in the first two weeks.</p>
      </div>

      <div class="ca-article" id="article-g-27-adoption">
        <h3>ARTICLE G.27:ADOPTION</h3>
        <p>G.27.1Leave of up to four (4) days per adoption will be granted to a teacher who is adopting a child. This leave will be with pay.</p>
        <p>G.27.2Extension of this leave shall be granted to a maximum of eighteen (18) weeks if desired. Such extension of leave shall be without pay.</p>
        <p>G.27.3Dates of departure and return shall be determined or varied by mutual Agreement between the teacher and the Board.</p>
        <p>G.27.4Teachers who wish to exercise the Extended Maternity Leave options in this Agreement may do so.</p>
        <p>G.27.5Sick leave provisions will neither be lost nor accumulated by a teacher while on an extended adoption leave.</p>
        <p>G.27.6During an extended adoption leave, an employee returning to work will not lose seniority accumulated prior to the commencement of the leave, nor will seniority accumulate during the leave.</p>
      </div>

      <div class="ca-article" id="article-g-28-illness-in-the-family">
        <h3>ARTICLE G.28:ILLNESS IN THE FAMILY</h3>
        <p>G.28.1Leave of up to three (3) days may be granted to a teacher when an immediate member of the family is ill and no one other than the teacher can provide for their needs.</p>
        <p>G.28.2This leave will be charged against accumulated sick leave.</p>
        <p>[See also Article G.2 Compassionate Care Leave.]</p>
      </div>

      <div class="ca-article" id="article-g-29-medical-examination">
        <h3>ARTICLE G.29:MEDICAL EXAMINATION</h3>
        <p>G.29.1Leave of up to three (3) days may be granted to a teacher to consult a medical specialist for themselves or for an immediate family member. The leave will also be granted for a medical examination required by a Pension Board.</p>
        <p>G.29.2This leave will be charged against accumulated sick leave.</p>
        <p>G.29.3The Board may request a letter from a medical doctor stating that the presence of the teacher was/is required.</p>
        <p>G.29.4If leave in excess of three (3) days is required application may be made to the Board.</p>
      </div>

      <div class="ca-article" id="article-g-30-medical-emergency">
        <h3>ARTICLE G.30:MEDICAL EMERGENCY</h3>
        <p>G.30.1Leave of up to five (5) days may be granted to a teacher when the presence of the immediate family is required in a medical emergency.</p>
        <p>G.30.2The teacher may request that such leave be without pay or be charged to accumulated sick leave.</p>
        <p>G.30.3The Board may request a letter from a medical doctor stating that the presence of the immediate family was required.</p>
      </div>

      <div class="ca-article" id="article-g-31-jury-duty">
        <h3>ARTICLE G.31:JURY DUTY</h3>
        <p>G.31.1Leave shall be granted to a teacher summoned for Jury Duty and such leave shall be with pay.</p>
        <p>G.31.2A teacher granted leave under this provision will turn over to the Board any monies received as a result of serving on Jury Duty on the days they would normally be teaching, exclusive of travelling costs or meal allowances.</p>
      </div>

      <div class="ca-article" id="article-g-32-court-witness">
        <h3>ARTICLE G.32:COURT WITNESS</h3>
        <p>G.32.1Leave shall be granted to a teacher subpoenaed as a court witness and such leave will be with pay.</p>
        <p>G.32.2A teacher granted leave under this provision will turn over to the Board any monies received as a result of serving as a witness on the days that they would normally be teaching, exclusive of travelling costs or meal allowances.</p>
      </div>

      <div class="ca-article" id="article-g-33-long-service-discretionary">
        <h3>ARTICLE G.33:LONG SERVICE DISCRETIONARY</h3>
        <p>G.33.1Leave of up to five (5) days shall be granted to teachers whose service to the district is eight (8) or more years.</p>
        <p>G.33.2This leave may be applied for once every three (3) years.</p>
        <p>G.33.3Teachers will have salary deducted at the basic rate of 1/225 of the 0 step of their category.</p>
        <p>G.33.4Application must be made at least thirty (30) days in advance of the leave.</p>
        <p>G.33.5This leave is dependent upon specific approval of the School Principal and the Superintendent of Schools.</p>
        <p>[Note: See also Article G.5 Unpaid Discretionary Leave.]</p>
      </div>

      <div class="ca-article" id="article-g-34-extended-personal">
        <h3>ARTICLE G.34:EXTENDED PERSONAL</h3>
        <p>G.34.1Leave of up to one (1) year may be granted to teachers for personal reasons and this leave shall be without pay. Seniority will not be accumulated by a teacher while on such leave.</p>
        <p>G.34.2Applications for this leave must be received no later than April 1 for leave in the following year.</p>
        <p>G.34.3Applicants will be notified in writing within thirty (30) working days of receipt of the leave request.</p>
      </div>

      <div class="ca-article" id="article-g-35-elections">
        <h3>ARTICLE G.35:ELECTIONS</h3>
        <p>G.35.1When a teacher is nominated as a candidate and wishes to contest a Municipal, Regional, Provincial or Federal election, they shall be given leave-of-absence without pay, as required during the election campaign. Should the teacher be elected Member of Parliament or Member of the Legislative Assembly they shall be granted long-term leave of absence without pay if they so request.</p>
        <p>G.35.2Teachers who are appointed to Federal, Provincial, District or Municipal Governing Boards or Commissions; or teachers elected to Municipal or District offices shall be granted up to eight (8) days leave-of-absence without pay in any one school year.</p>
        <p>G.35.3Teachers who are appointed, elected, or volunteer for civic office who do not receive a stipend or honorarium shall be granted up to eight (8) days leave-of-absence, two (2) days with pay, and six (6) days with pay, less the cost of the teacher teaching on call, in any one school year.</p>
      </div>

      <div class="ca-article" id="article-g-36-educational">
        <h3>ARTICLE G.36:EDUCATIONAL</h3>
        <p>G.36.1Leave of up to one (1) year may be granted to teachers for educational reasons without pay.</p>
        <p>G.36.2Normally, only two (2) teachers may be granted &quot;Educational Leave&quot; in any one school year.</p>
        <p>G.36.3Teachers granted this leave must resign by April 30th if they choose not to return to their teaching position.</p>
        <p>G.36.4Teachers granted this leave shall be entitled to the same sharing arrangements for benefits as if they were in active service. The teacher must make arrangements with the Board for the payment of their share of benefit costs. In the event the teacher receiving these benefits does not return to teach in the District for at least one year, the teacher will be liable for costs of benefits paid by the Board while the teacher was on leave.</p>
      </div>

      <div class="ca-article" id="article-g-37-self-funded-plan">
        <h3>ARTICLE G.37:SELF-FUNDED PLAN</h3>
        <p>G.37.1The Board shall administer a Self-Funded Leave Plan as determined by a separate Agreement.</p>
      </div>

      <div class="ca-article" id="signatures">
        <h3>SIGNATURES</h3>
        <p>Signed at __________, British Columbia, this _______day of ___________________, 2024</p>
        <p>Michael McDiarmid,Tanya Davidson,</p>
        <p>Superintendent of SchoolsPresident</p>
        <p>School District No. 54 (Bulkley Valley)Bulkley Valley Teachers’ Union</p>
        <p>______________________________________________________________</p>
        <p>Alison Jones, Senior Manager,Clint Johnston, President</p>
        <p>Labour Relations (Collective Bargaining)British Columbia Teachers’ Federation</p>
        <p>British Columbia Public School Employers’</p>
        <p>Association</p>
        <p>LETTERS OF UNDERSTANDING</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>THE BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>AND</p>
        <p>THE BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>Re: Designation of Provincial and Local Matters</p>
        <p>Pursuant to the Public Education Labour Relations Act (PELRA), the provincial and the local parties agree to the designation of provincial and local matters as follows:</p>
        <p>Those matters contained within Appendix 1 shall be designated as provincial matters.</p>
        <p>Those matters contained within Appendix 2 shall be designated as local matters.</p>
        <p>Provincial parties’ roles will be pursuant to PELRA.</p>
        <p>Referral of impasse items to the provincial table will be pursuant to PELRA</p>
        <p>Timing and conclusion of local matters negotiations:</p>
        <p>Local negotiations will conclude at a time determined by mutual agreement of the provincial parties.</p>
        <p>Outstanding local matters may not be referred to the provincial table subsequent to the exchange of proposals by the provincial parties at the provincial table.</p>
        <p>Where no agreement is reached, local negotiations will conclude at the time a new Provincial Collective Agreement is ratified.</p>
        <p>Local and provincial ratification processes:</p>
        <p>Agreements on local matters shall be ratified by the local parties subject to verification by the provincial parties that the matters in question are local matters (Appendix 2).</p>
        <p>Agreements on provincial matters shall be ratified by the provincial parties.</p>
        <p>Effective date of local matters items:</p>
        <p>Agreements ratified by the school district and local union shall be effective upon the ratification of the new Provincial Collective Agreement unless the timelines are altered by mutual agreement of the provincial parties.</p>
        <p>Signed this 8th day of March, 2013</p>
      </div>

      <div class="ca-article" id="appendix">
        <h3>Appendix</h3>
        <p>PROVINCIAL MATTERS</p>
      </div>

      <div class="ca-article" id="appendix-1-provincial-matters">
        <h3>Appendix 1 – Provincial Matters</h3>
        <p>Housekeeping – Form Issues</p>
        <p>Common provincial provisions</p>
        <p>Common provincial terminology</p>
        <p>Cover Page of Agreement</p>
        <p>Interpretation of Teacher Contracts and School Act</p>
        <p>Section A – The Collective Bargaining Relationship</p>
        <p>Term and Renegotiation, Re-opening Agreement During Term, Bridging, Strikes, Renewal, Retroactivity</p>
        <p>Legislative Change</p>
        <p>Recognition of the Union</p>
        <p>Membership Requirement</p>
        <p>Exclusions from the Bargaining Unit</p>
        <p>Job Security including Contracting Out</p>
        <p>Deduction of BCTF Dues and Professional Fees</p>
        <p>President’s/Officer Release</p>
        <p>Management Rights and Responsibilities</p>
        <p>Pro-D Chairperson/Coordinator Release</p>
        <p>Release for Local, BCTF, CTF, Teacher Regulation Branch and Education International Business</p>
        <p>Leave for Contract Negotiations</p>
        <p>School Staff and District Committees</p>
        <p>Access to Information</p>
        <p>Copy of Agreement and melding/interfacing</p>
        <p>Grievance/Arbitration (including Expedited) Procedure and Troubleshooter</p>
        <p>Section B – Salary and Economic Benefits</p>
        <p>Determination of Salary</p>
        <p>Placement on Scale</p>
        <p>Salary Review</p>
        <p>Bonus for Education Courses, Reimbursement for Non-Credit Courses</p>
        <p>Classification of Salary for Letters of Permission</p>
        <p>New Positions, Reclassification</p>
        <p>Experience Recognition</p>
        <p>Salary Scale</p>
        <p>Category Addition</p>
        <p>Category Elimination</p>
        <p>Payment of Salary</p>
        <p>Increment Dates</p>
        <p>Withholding</p>
        <p>Error in Salary – Adjustments</p>
        <p>Part Month Payments and Deductions including Schedule</p>
        <p>Pay Periods including payment schedule</p>
        <p>Employees’ Pay and Benefits including sick leave</p>
        <p>Full time and continuing teachers</p>
        <p>Part Time and temporary or term teachers</p>
        <p>Teachers Teaching on Call</p>
        <p>Summer School and Night School Payment</p>
        <p>Associated Professionals</p>
        <p>Positions of Special Responsibility</p>
        <p>Teacher in Charge/Acting Administrators (Filling Temporarily Vacant Position)</p>
        <p>Automobile/Travel Allowance</p>
        <p>First Aid, First Aid Allowance and Training</p>
        <p>Special Allowances, i.e., Moving/Relocation, Travel, Isolation, One-Room School, Rural, Outer Island, Village Assignment, Pro-D Travel Allowance, Clothing, etc.</p>
        <p>Establishment and funding of Classroom Supply Fund or Allowance (Compensation for Funds Spent by Teachers on Class)</p>
        <p>Housing and Housing Assistance</p>
        <p>No Cuts in Salary and Benefits</p>
        <p>Payment for Work Beyond Regular Work Year</p>
        <p>Counsellors Working Outside School Calendar</p>
        <p>Night School Payments</p>
        <p>Summer School Payments</p>
        <p>Salary – Payment for Additional Days</p>
        <p>Not Regular School Days</p>
        <p>Payment of Teacher Regulation Branch and other professional fees</p>
        <p>Benefits – general information and benefits management committee</p>
        <p>Benefits – Coverage</p>
        <p>Employment Insurance/all EI rebates</p>
        <p>Continuation of Benefits</p>
        <p>Retirement Benefits and Bonuses</p>
        <p>Wellness Programs, Employee and Family Assistance Program</p>
        <p>Personal Property loss, theft, vandalism and Insurance</p>
        <p>Benefits – RRSP</p>
        <p>Section C – Employment Rights</p>
        <p>Employment on Continuing Contract</p>
        <p>Appointment on Continuing Contract</p>
        <p>Employment Rights – Temporary Teachers converting to continuing</p>
        <p>Probationary period</p>
        <p>Dismissal and Discipline for Misconduct</p>
        <p>Conduct of a Teacher (Inside and Outside School)</p>
        <p>Dismissal Based on Performance</p>
        <p>The Processes of Evaluation of Teachers’ Teaching Performance</p>
        <p>Part-Time Teachers’ Employment Rights</p>
        <p>Sick Leave and Benefits</p>
        <p>Long Services – Part Time Teaching Plan, Part Year Teachers</p>
        <p>Teacher Teaching on Call Hiring Practices</p>
        <p>Seniority</p>
        <p>Severance</p>
        <p>Retraining, Board directed education upgrading</p>
        <p>Section D – Working Conditions</p>
        <p>Teacher Workload</p>
        <p>Class Size</p>
        <p>Class Composition</p>
        <p>Inclusion</p>
        <p>Urgent Intervention Program or similar</p>
        <p>School Based Team</p>
        <p>Professional Teaching Staff Formulas including advisory committees</p>
        <p>Hours of Work</p>
        <p>Duration of School Day</p>
        <p>Instructional Time</p>
        <p>Extended Day; Alternate Calendars e.g. Four Day Week</p>
        <p>Preparation Time</p>
        <p>Regular Work Year for Teachers, School Calendar, Year Round Schools, Staggered Part Day Entries</p>
        <p>Closure of Schools for Health or Safety Reasons</p>
        <p>Supervision Duties, Duty Free Lunch Hour, Noon Hour Supervision</p>
        <p>Availability of Teacher on Call</p>
        <p>Teacher on Call Working Conditions</p>
        <p>Mentor/Beginning Teacher Program, Student Teachers, Beginning Teacher Orientation</p>
        <p>Child Care for Work Beyond Regular Hours, Day Care</p>
        <p>Home Education, Suspended Students, Hospital/Homebound Teachers</p>
        <p>Non-traditional Worksites, e.g.</p>
        <p>Distributed Learning</p>
        <p>Adult Education</p>
        <p>Storefront Schools</p>
        <p>Satellite School Programs</p>
        <p>Technological Change, Adjustment Plan – Board Introduced Change</p>
        <p>Hearing and Medical Checks, Medical Examinations, Tests, Screening for TB</p>
        <p>Teacher Reports on Students, Anecdotal Reports for Elementary Students, Parent Teacher Conference Days</p>
        <p>Section E – Personnel Practices</p>
        <p>Definition of Teachers</p>
        <p>Selection of Administrative Officers (Note: See Addendum B)</p>
        <p>Non-sexist Environment</p>
        <p>Harassment</p>
        <p>Falsely Accused Employee</p>
        <p>Violence Prevention</p>
        <p>Criminal Record Checks</p>
        <p>Resignation and Retirement</p>
        <p>Section F – Professional Rights</p>
        <p>Educational/Curriculum Change including committees</p>
        <p>Professional Development Funding (Note: see also Addendum C)</p>
        <p>Tuition Costs</p>
        <p>Professional Development Committee – as related to funding</p>
        <p>Professional Days (Non-Instructional)</p>
        <p>School Accreditation and Assessment</p>
        <p>Professional Autonomy</p>
        <p>Responsibilities – Duties of Teachers</p>
        <p>Section G – Leaves of Absence</p>
        <p>Sick Leave, Sick Leave Portability, Preauthorized Travel for Medical Services Leave</p>
        <p>Maternity and Parental Leave and Supplemental Employment Benefits Plan</p>
        <p>Short Term Paternity Leave and Adoption Leave</p>
        <p>Jury Duty and Appearances in Legal Proceedings</p>
        <p>Educational Leave and Leave for Exams</p>
        <p>Bereavement/Funeral Leave</p>
        <p>Leave for Family Illness, Care of Dependent Child or Relative, Emergency or Long Term Chronic Leave, Compassionate Care Leave</p>
        <p>Discretionary Leave, Short Term General Leave and Personal Leave</p>
        <p>Leave for Elected Office and Leave for Community Services</p>
        <p>Worker’s Compensation Leave</p>
        <p>Leave of Absence Incentive Plan</p>
        <p>Religious Holidays</p>
        <p>Leave to Attend Retirement Seminars</p>
        <p>Leave for Communicable Disease</p>
        <p>Leave for Conference Participation</p>
        <p>Leave for Competitions</p>
        <p>Leave for Teacher Exchange</p>
        <p>Secondment and Leave for external employment</p>
        <p>Leave for University Convocations, Leave for graduation, Exams</p>
        <p>Leave for Special Circumstances including: Citizenship, Marriage, Weather Leaves</p>
        <p>Leave for Blood, Tissue and Organ Donations, Leave for Bone Marrow, Cell Separation Program Participation</p>
        <p>22. Miscellaneous Leaves with cost</p>
        <p>January 22, 2021 - Provincial Matters</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="appendix">
        <h3>Appendix</h3>
        <p>LOCAL MATTERS</p>
      </div>

      <div class="ca-article" id="appendix-2-local-matters">
        <h3>Appendix 2 – Local Matters</h3>
        <p>Housekeeping – Form Issues</p>
        <p>Glossary of Terms for local matters</p>
        <p>Preamble, Introduction, Statement of Purpose</p>
        <p>Section A – The Collective Bargaining Relationship</p>
        <p>Local Negotiation Procedures</p>
        <p>Recognition of Union</p>
        <p>Access to Worksite</p>
        <p>Use of School Facilities</p>
        <p>Bulletin Board</p>
        <p>Internal Mail</p>
        <p>Access to Information</p>
        <p>Education Assistants, Aides, and Volunteers</p>
        <p>Picket Line Protection, School Closures – Re: Picket Lines (Strikes)</p>
        <p>Local Dues Deduction</p>
        <p>Staff Representatives, Lead Delegates</p>
        <p>Right to Representation, Due Process</p>
        <p>Staff Orientation</p>
        <p>Copy of Agreement</p>
        <p>Section B – Salary and Economic Benefits</p>
        <p>Purchase Plans for Equipment e.g. computer purchase</p>
        <p>Payroll, Deductions to Teachers Investment Account, Investment of Payroll – Choice of Bank Account</p>
        <p>Employee Donations for Income Tax Purposes</p>
        <p>Section C – Employment Rights</p>
        <p>Layoff-Recall, Re-Engagement</p>
        <p>Part-Time Teachers’ Employment Rights</p>
        <p>Job Sharing</p>
        <p>Offer of Appointment to District</p>
        <p>Assignments</p>
        <p>Posting &amp; Filling Vacant Positions</p>
        <p>Section D – Working Conditions</p>
        <p>Extra-curricular Activities</p>
        <p>Staff Meetings</p>
        <p>Health and Safety, including committees</p>
        <p>Student Medication and Medical Procedures</p>
        <p>Local Involvement in Board Budget Process,</p>
        <p>Committee – Finance Board Budget</p>
        <p>School Funds</p>
        <p>Teacher Involvement in Planning New Schools</p>
        <p>Space and Facilities</p>
        <p>Services to Teachers e.g. translation</p>
        <p>Inner City Schools, Use of Inner City Schools Funds</p>
        <p>Section E – Personnel Practices</p>
        <p>Posting and Filling Vacant Position</p>
        <p>Offer of Appointment to District</p>
        <p>Assignments</p>
        <p>Job Sharing</p>
        <p>Posting Procedures – Filling</p>
        <p>Posting &amp; Filling Vacant Positions – School Reorganization</p>
        <p>Transfer: Board Initiated Transfers, Transfer related to Staff Reduction</p>
        <p>Creation of New Positions</p>
        <p>Job Description</p>
        <p>Definition of Positions and Assignments</p>
        <p>Personnel Files</p>
        <p>School Act Appeals</p>
        <p>Input into Board Policy</p>
        <p>No Discrimination</p>
        <p>Multiculturalism</p>
        <p>Gender Equity</p>
        <p>Selection of Administrative Officers (Note: See Addendum B)</p>
        <p>Parental Complaints, Public Complaints</p>
        <p>Section F – Professional Rights</p>
        <p>Professional Development Committee as related to funding control (Note: see also Addendum C)</p>
        <p>Committees</p>
        <p>Professional Relations/Labour management</p>
        <p>Parent Advisory Council</p>
        <p>Joint Studies Committee</p>
        <p>Professional Development Committee (Note: see also Addendum C)</p>
        <p>Leave of Absence Committee</p>
        <p>First Nations Curriculum</p>
        <p>Women’s Studies</p>
        <p>Fund Raising</p>
        <p>Reimbursement of Classroom Expenses</p>
        <p>Section G – Leaves of Absence</p>
        <p>Long Term Personal Leave</p>
        <p>Extended Maternity/Parental Leave/Parenthood (or their equivalent)</p>
        <p>Deferred Salary/Self Funded Leave Plans</p>
        <p>Unpaid Leaves: unpaid leave not otherwise designated as a provincial matter in Appendix 1 (Provincial Matters) of the agreement, except for those elements of the clause that are provincial including: continuation of benefits, increment entitlement and matters related to pensions.</p>
        <p>January 22, 2021 - Local Matters.</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
        <p>Addendum A To</p>
      </div>

      <div class="ca-article" id="appendix-1-and">
        <h3>Appendix 1 and</h3>
        <p>Unpaid Leave In The Designation Of Provincial and Local Matters</p>
        <p>Unpaid leave shall be designated for local negotiations, except as it relates to those elements of the clause that are provincial including: continuation of benefits, increment entitlement, pension related matters, and posting and filling.</p>
        <p>Signed this 25th day of October 1995</p>
        <p>Addendum B To</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>Letter of Understanding No.</h3>
        <p>Appendices 1 and 2</p>
        <p>Concerning Selection of Administrative Officers</p>
        <p>“Selection of Administrative Officers” shall be designated as a local matter for negotiations in those districts where the Previous Local Matters Agreement contained language which dealt with this issue or its equivalent. For all other districts, “Selection of Administrative Officers” shall be deemed a provincial matter for negotiations.</p>
        <p>The issue of Administrative Officers returning to the bargaining unit does not form part of this addendum to appendices 1 and 2.</p>
        <p>For the purposes of paragraph one of this addendum, the parties acknowledge that language on the issue of “Selection of Administrative Officers” or its equivalent exists in the Previous Local Agreements for the following districts: Fernie, Nelson, Castlegar, Revelstoke, Vernon, Vancouver, Coquitlam, Nechako, Cowichan, Alberni and Stikine.</p>
        <p>The parties further acknowledge that there may be language in other Previous Local Agreements on this same issue. Where that proves to be the case, “Selection of Administrative Officers” or its equivalent shall be deemed a local matter for negotiations.</p>
        <p>Signed this 11th day of December 1996.</p>
        <p>Addendum C To</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>Letter of Understanding No.</h3>
        <p>Appendices 1 and 2</p>
        <p>Professional Development</p>
        <p>For the purposes of section 7 of part 3 of PELRA the parties agree as follows:</p>
        <p>Teacher Assistants:</p>
        <p>Teacher Assistants language shall, for all purposes, remain as a local matter pursuant to the Letter of Understanding signed between the parties as at May 31, 1995 save and except that language which concerns the use of teacher assistants as alternatives for the reduction of class size and/or the pupil/teacher ratio shall be designated as a provincial matter.</p>
        <p>Professional Development:</p>
        <p>Language concerning the date that funds for professional development are to be made available in a district, reference to a “fund” for professional development purposes and the continued entitled of an individual teacher to professional development funds and/or teacher-on-call time following a transfer shall be designated as local matters.</p>
        <p>Signed this 23rd day of April 1997.</p>
        <p>Addendum D To</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>Letter of Understanding No.</h3>
        <p>Appendices 1 and 2</p>
        <p>Re:  October 25, 1995 Letter of Understanding (“Unpaid Leave”) – Revised</p>
        <p>1.The parties agree that “unpaid leave” for the purposes of the Letter of Understanding signed between the parties on October 25, 1995 means an unpaid leave not otherwise designated as a provincial matter in Appendix 1 (Provincial Matters) of the agreement on designation of the split of issues.</p>
        <p>2.Unpaid leave as described in (1) above shall be designated for local negotiations except for provincial considerations in the article including: continuation of benefits, increment entitlement and matters related to pensions and posting and filling.</p>
        <p>Signed this 7th day of October 1997.</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN:</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Agreed Understanding of the Term Teacher Teaching on Call</p>
        <p>For the purposes of this Collective Agreement, the term Teacher Teaching on Call (TTOC) has the same meaning as Teacher on Call/Employee on Call (TOC/EOC) as found in the 2006-2011 Collective Agreement/Working Documents and is not intended to create any enhanced benefits.</p>
        <p>The parties will set up a housekeeping committee to identify the terms in the Collective Agreement/working documents that will be replaced by Teacher Teaching on Call (TTOC).</p>
        <p>Signed this 25th day of June, 2012</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no-3-a">
        <h3>LETTER OF UNDERSTANDING No. 3. a</h3>
        <p>Between</p>
        <p>THE BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>(BCTF)</p>
        <p>And</p>
        <p>THE BRITISH COLUMBIA PUBLIC SCHOOL</p>
        <p>EMPLOYERS’ ASSOCIATION</p>
        <p>(BCPSEA)</p>
        <p>Re: Section 4 of Bill 27 Education Services Collective Agreement Act</p>
        <p>Transitional Issues—Amalgamated School Districts—SD.5 (Southeast Kootenay),    SD.6 (Rocky Mountain), SD.8 (Kootenay Lake), SD.53 (Okanagan-Similkameen), SD.58 (Nicola-Similkameen), SD.79 (Cowichan Valley), SD.82 (Coast Mountains), SD.83 (North Okanagan-Shuswap), SD.91 (Nechako Lakes).</p>
        <p>[Not applicable in School District #54 (Bulkley Valley).]</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no-3-b">
        <h3>LETTER OF UNDERSTANDING No. 3.b</h3>
        <p>BETWEEN:</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Section 27.4 Education Services Collective Agreement Act</p>
        <p>[Not applicable in School District #54 (Bulkley Valley).]</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN:</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Employment Equity – Indigenous Peoples</p>
        <p>The parties recognize that Indigenous Peoples are underrepresented in the public education system. The parties are committed to redressing the under-representation of Indigenous Peoples in the workforce and therefore further agree that:</p>
        <p>They will encourage and assist boards of education, with the support of the local teachers’ unions, to make application to the Office of the Human Rights Commissioner under section 42 of the Human Rights Code to obtain approval for a “special program” that would serve to attract and retain Indigenous employees.</p>
        <p>They will encourage and assist boards of education and local teachers’ unions to include a request to grant:</p>
        <p>priority hiring rights to Indigenous applicants; and</p>
        <p>priority in the post and fill process and layoff protections for Indigenous employees</p>
        <p>in applications to the Office of the Human Rights Commissioner.</p>
        <p>The parties’ support for special program applications is not limited to positions funded by targeted Indigenous Education Funding.</p>
        <p>The provincial parties will jointly develop communications and training which will support the application for and implementation of special programs in districts. As part of the communications and training initiative, the parties will develop an Implementation Guide to be shared with boards of education and local teachers’ unions.</p>
        <p>The provincial parties will meet to initiate this work within three (3) months of ratification of this agreement (or other time period as mutually agreed to) with the goal of completing the Implementation Guide and a plan for communications and training within one (1) year.</p>
        <p>Signed this 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN:</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Teacher Supply and Demand Initiatives</p>
        <p>The BC Teachers’ Federation and the BC Public School Employer’s Association agree to support the recruitment and retention of a qualified teaching force in British Columbia.</p>
        <p>1.  Remote Recruitment &amp; Retention Allowance:</p>
        <p>a.Each full-time equivalent employee in the schools or school districts identified in Schedule A is to receive an annual recruitment allowance of $2,761 effective July 1, 2022 upon commencing employment. Each part-time equivalent employee is to receive a recruitment allowance pro-rated to their full-time equivalent position.</p>
        <p>b.All employees identified will receive the annual recruitment allowance of $2,761 effective July 1, 2022 as a retention allowance each continuous year thereafter. Each part-time employee is to receive a retention allowance pro-rated to their full-time equivalent position.</p>
        <p>c.The allowance will be paid as a monthly allowance.</p>
        <p>Joint Remote Recruitment and Retention Review Committee</p>
        <p>The parties agree to establish a committee within six (6) months of the conclusion of the 2022 provincial bargaining (or other period as mutually agreed to).</p>
        <p>The committee shall be comprised of up to three (3) representatives appointed by BCTF and up to three (3) representatives appointed by BCPSEA.</p>
        <p>The committee will review:</p>
        <p>the 2008 criteria used to establish Schedule A;</p>
        <p>current demographics and data related to implementation of LOU 5;</p>
        <p>cost implications of potential future changes to LOU 5;</p>
        <p>current data related to remote recruitment and retention;</p>
        <p>The parties agree to complete the work of the committee January 1, 2024 (or other period as mutually agreed to).</p>
        <p>Signed this 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Article C.2. – Porting of Seniority – Separate Seniority Lists</p>
        <p>This agreement was necessitated by the fact that some districts have a separate seniority list for adult education teachers, i.e., 1 seniority list for K – 12 and a second separate seniority list for adult education seniority. Consistent with Irene Holden’s previous awards on porting, implementation of this agreement is meant to be on a prospective basis and is not intended to undo any previous staffing decisions with the understanding that anomalies could be discussed and considered at labour management. There are 4 possible situations and applications:</p>
        <p>Teacher in a district with 1 list ports to a district with 1 list (1 to 1)</p>
        <p>Both K – 12 and adult education seniority are contained on a single list in both districts.</p>
        <p>Normal rules of porting apply.</p>
        <p>No more than 1 year of seniority can be credited and ported for any single school year.</p>
        <p>Maximum of 20 years can be ported.</p>
        <p>Teacher in a district with 2 separate lists ports to a district with 2 separate lists (2 to 2)</p>
        <p>Both K – 12 and adult education seniority are contained on 2 separate lists in both districts.</p>
        <p>Both lists remain separate when porting.</p>
        <p>Up to 20 years of K – 12 and up to 20 years of adult education can be ported to the corresponding lists.</p>
        <p>Although the seniority is ported from both areas, the seniority is only activated and can be used in the area in which the teacher attained the continuing appointment. The seniority remains dormant and cannot be used in the other area unless/until the employee subsequently attains a continuing appointment in that area.</p>
        <p>For example, teacher A in District A currently has 8 years of K – 12 seniority and 6 years of adult education seniority. Teacher A secures a K – 12 continuing appointment in District B. Teacher A can port 8 years of K – 12 seniority and 6 years of adult education seniority to District B. However, only the 8 years of K – 12 seniority will be activated while the 6 years of adult education seniority will remain dormant. Should teacher A achieve a continuing appointment in adult education in District B in the future, the 6 years of adult education seniority shall be activated at that time.</p>
        <p>Teacher in a district with 2 separate lists ports to a district with 1 seniority list (2 to 1)</p>
        <p>A combined total of up to 20 years of seniority can be ported.</p>
        <p>No more than 1 year of seniority can be credited for any single school year.</p>
        <p>Teacher in a district with 1 single seniority list ports to a district with 2 separate seniority lists (1 to 2)</p>
        <p>Up to 20 years of seniority could be ported to the seniority list to which the continuing appointment was received.</p>
        <p>No seniority could be ported to the other seniority list.</p>
        <p>For example, teacher A in District A currently has 24 years of seniority and attains a K – 12 position in District B which has 2 separate seniority lists. Teacher A could port 20 years of seniority to the K – 12 seniority list in District B and 0 seniority to the adult education seniority list in District B.</p>
        <p>The porting of seniority only applies to seniority accrued within the provincial BCTF bargaining unit. The porting of seniority is not applicable to adult education seniority accrued in a separate bargaining unit or in a separate BCTF bargaining unit.</p>
        <p>Signed this 26th day of March, 2020</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Article C.2 – Porting of Seniority &amp; Article G.1 Portability of Sick Leave – Simultaneously Holding Part-Time Appointments in Two Different Districts</p>
        <p>The following letter of understanding is meant to clarify the application of Article C.2.2 and G.1 of the provincial Collective Agreement with respect to the situation where a teacher simultaneously holds part-time continuing appointments in two (2) separate school districts, i.e., currently holds a part-time continuing appointment in one (1) district and then subsequently obtains a second part-time continuing appointment in a second district. Should this specific situation occur, the following application of Article C.2.2 and G.1 shall apply:</p>
        <p>The ability to port sick leave and seniority cannot occur until the employee either resigns/terminates their employment from the porting district or receives a full leave of absence from the porting district.</p>
        <p>The requirement for the teacher to initiate the sick leave verification process (90 days* from the initial date of hire) and the seniority verification process (within 90 days* of a teacher’s appointment to a continuing contract) and forward the necessary verification forms to the previous school district shall be held in abeyance pending either the date of the employee’s resignation/termination of employment from the porting district or the employee receiving a full leave of absence from the porting district.</p>
        <p>[* Note: effective November 30, 2022, initiation of sick leave and seniority verification process was increased from 90 days to 120 days.]</p>
        <p>Should a teacher port seniority under this Letter of Understanding, there will be a period of time when the employee will be accruing seniority in both districts. For this period of time (the period of time that the teacher simultaneously holds part-time continuing appointments in both districts up until the time the teacher ports), for the purpose of porting , the teacher will be limited to a maximum of 1 years seniority for each year.</p>
        <p>Should a teacher receive a full-time leave and port seniority and/or sick leave under this letter of understanding, the rules and application described in the Irene Holden award of June 7, 2007 concerning porting while on full-time leave shall then apply.</p>
        <p>Consistent with Irene Holden’s previous awards on porting, implementation of this agreement is meant to be on a prospective basis and is not intended to undo any previous staffing decision with the understanding that anomalies could be discussed and considered at labour management.</p>
        <p>The following examples are intended to provide further clarification:</p>
        <p>Example 1</p>
        <p>Part-time employee in district A has 5 years of seniority. On September 1, 2007 they also obtain a part-time assignment in district B. On June 30, 2008, the employee resigns from district A. The employee will have 90 days from June 30, 2008 to initiate the seniority and/or sick leave verification processes and forward the necessary verification forms to the previous school district for the porting of seniority and/or sick leave. No seniority and/or sick leave can be ported to district B until the employee has resigned or terminated their employment in district A. Once ported, the teacher’s seniority in district B cannot exceed a total of 1 year for the September 1, 2007 – June 30, 2008 school year.</p>
        <p>Example 2</p>
        <p>Part-time employee in district A has 5 years of seniority. On September 1, 2007 they also obtain a part-time assignment in district B. On September 1, 2008, the employee receives a leave of absence from district A for their full assignment in district A. The employee will have 90 days from September 1, 2008 to initiate the seniority and/or sick leave verification process and forward the necessary verification forms to the previous school district for the porting of seniority. The Irene Holden award dated June 7, 2007 will then apply. No seniority can be ported to district B until the employee’s leave of absence is effective. Once ported, the teacher’s seniority in district B cannot exceed a total of 1 year for the September 1, 2007 – June 30, 2008 school year.</p>
        <p>The porting of seniority and sick leave only applies to seniority and sick leave accrued with the provincial BCTF bargaining unit. The porting of seniority and sick leave is not applicable to seniority accrued in a separate bargaining unit or in a separate BCTF bargaining unit.</p>
        <p>Signed this 26th day of March, 2020</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
        <p>* Note: effective November 30, 2022, initiation of sick leave and seniority verification process was increased from 90 days to 120 days.</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Article C.2 – Porting of Seniority – Laid off Teachers who are Currently on the Recall List</p>
        <p>The following letter of understanding is meant to clarify the application of Article C.2.2 of the provincial Collective Agreement with respect to the situation where a laid off teacher on recall in district A obtains a continuing appointment in district B, i.e., while holding recall rights in one (1) district obtains a continuing appointment in a second district. Should this specific situation occur, the following application of Article C.2.2 shall apply:</p>
        <p>Laid off teacher holding recall rights in one school district may port up to twenty (20) years of seniority to a second school district when they secure a continuing appointment in that second school district.</p>
        <p>Such ported seniority must be deducted from the accumulation in the previous school district for all purposes except recall; for recall purposes only, the teacher retains the use of the ported seniority in their previous district.</p>
        <p>If the recall rights expire or are lost, the ported seniority that was deducted from the accumulation in the previous school district will become final for all purposes and would be treated the same way as if the teacher had ported their seniority under normal circumstances. No additional seniority from the previous school district may be ported.</p>
        <p>If the teacher accepts recall to a continuing appointment in the previous district, only the ported amount of seniority originally ported can be ported back, i.e., no additional seniority accumulated in the second school district can be ported to the previous school district.</p>
        <p>The ability to port while on layoff/recall is limited to a transaction between two districts and any subsequent porting to a third district can only occur if the teacher terminates all employment, including recall rights with the previous school district.</p>
        <p>Consistent with Irene Holden’s previous awards on porting, implementation of this letter of understanding is meant to be on a prospective basis and is not intended to undo any previous staffing decision with the understanding that anomalies could be discussed between the parties.</p>
        <p>This letter of understanding in no way over-rides any previous local provisions currently in effect which do not permit a teacher maintaining recall rights in one district while holding a continuing position in another school district.</p>
        <p>The following examples are intended to provide further clarification:</p>
        <p>Example 1</p>
        <p>A Teacher has 3 years of seniority in district “A” has been laid off with recall rights. While still holding recall rights in district “A”, the teacher secures a continuing appointment in district “B”. Once ported, this teacher would have 3 years seniority in district “B”, 3 years of seniority in district “A” for recall purposes only and 0 years of seniority in district “A” for any other purposes. This teacher after working 1 year in district “B” accepts recall to a continuing appointment in district “A”. Only 3 years of seniority would be ported back to district “A” and for record keeping purposes, the teacher’s seniority record in district “B” would be reduced from 4 years down to 1 year.</p>
        <p>Example 2</p>
        <p>A Teacher has 3 years of seniority in district ‘A” has been laid off with recall rights. While still holding recall rights in district “A”, the teacher secures a continuing appointment in district “B”. Once ported, this teacher would have 3 years seniority in district “B”, 3 years of seniority in district “A” for recall purposes only and 0 years of seniority in district “A” for any other purposes. After working 2 years in school district “B” this teacher’s recall rights in school district “A” are lost. No further seniority can be ported from district “A” to district “B” and for record keeping purposes, the teacher’s seniority record in district “A” would be zero for all purposes.</p>
        <p>Original signed March 26, 2020</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN:</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>Re: Provincial Extended Health Benefit Plan</p>
        <p>The Provincial Extended Health Benefit Plan as provided for under Article B.11.1 is as set out in Appendix A to this Letter of Understanding.</p>
        <p>The Provincial Extended Health Benefit Plan may only be amended or altered by agreement of BCPSEA and the BCTF.</p>
        <p>The carrier/insurer for the Provincial Extended Health Benefit Plan may only be changed with prior consultation between BCPSEA and the BCTF.</p>
        <p>The consultation process will be consistent with the 2012 process. In the event of a dispute in the selection/change of the carrier/insurer, the matter shall be referred to Mark Brown, or an agreed-upon alternative, to be dealt with on an expedited basis.</p>
        <p>This provision covers any district or local that is part of the Provincial Extended Health Benefit Plan.</p>
        <p>Any efficiencies or cost reductions achieved as a direct result of the establishment of the Provincial Extended Health Benefit Plan will be used to further enhance the Provincial Extended Health Benefit Plan.</p>
        <p>The Provincial Extended Health Benefit plan does not include a medical referral travel plan (a “MRTP”). However, any school district that elects to participate in the Provincial Extended Health Benefit Plan and currently has a MRTP will continue to provide a MRTP.</p>
        <p>Where the local union elects not to participate in the Provincial Extended Health Benefit Plan, the school district will continue to provide the existing extended health benefit plan between the parties.</p>
        <p>As of September 1, 2022, local unions representing all members in the following school districts have voted against joining the Provincial Extended Health Benefit Plan:</p>
        <p>Vancouver Teachers’ Federation [VSTA, VEAES] / SD No. 39 (Vancouver)</p>
        <p>Coquitlam Teachers’ Association / SD No. 43 (Coquitlam)</p>
        <p>The local unions representing all members in the school districts in paragraphs 7.a and 7.b may elect to join the Provincial Extended Health Benefit Plan at any time during the term of the Collective Agreement.</p>
        <p>Signed this 26th day of November, 2012</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
        <p>Appendix A to Letter of Understanding No. 9</p>
        <p>* Eye exams are subject to Pacific Blue Cross Reasonable and Customary limits.</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING No.</h3>
        <p>BETWEEN:</p>
        <p>BOUNDARY TEACHERS’ ASSOCIATION</p>
        <p>AND</p>
        <p>THE BRITISH COLUMBIA TEACHERS’ FEDERATION</p>
        <p>AND</p>
        <p>THE BOARD OF EDUCATION OF SCHOOL DISTRICT NO.51 (BOUNDARY)</p>
        <p>AND</p>
        <p>THE BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION</p>
        <p>Re: Recruitment and Retention for Teachers at Beaverdell and Big White Elementary Schools</p>
        <p>Not applicable in SD54 (Bulkley Valley)</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION (BCPSEA)</p>
        <p>AND THE</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION (BCTF)</p>
        <p>Re: Article C.4 TTOC Employment – TTOC Experience Credit Transfer within a District</p>
        <p>The purpose of this letter of understanding is to address situations within a single district where a temporary/continuing teacher is also currently a Teacher Teaching on Call (TTOC) or in the past has been a TTOC.</p>
        <p>Teachers described above accrue experience for the purpose of increment advances under two (2) separate Collective Agreement provisions (silos), i.e., within a district, the employee triggers increments under Article C.4 for TTOC experience accrued and may also trigger increments under the applicable previous local agreement increment language for temporary/continuing experience accrued.</p>
        <p>In order to allow a TTOC the opportunity to transfer, within a district, their TTOC experience earned under Article C.4 (new provision effective September 19, 2014) towards that of the applicable previous local Collective Agreement increment language for continuing and/or temporary employees, the parties agree to the following:</p>
        <p>This option can only be exercised where in a single district a temporary/continuing teacher is also currently a TTOC or in the past has been a TTOC in the same district.</p>
        <p>This agreement only applies to TTOC experience earned under Article C.4 since September 19, 2014 in that district.</p>
        <p>This agreement only applies to a transfer within a district. This agreement is in no way applicable to a transfer of experience or recognition of experience between districts.</p>
        <p>The transfer of experience credit can only be transferred one way; from that of TTOC experience earned under Article C.4 to that of the temporary/continuing previous local agreement increment provision, i.e., it cannot be transferred for any reason from that of temporary/continuing to that of a TTOC.</p>
        <p>Transfers can only be made in whole months.</p>
        <p>For the purpose of transfer, 17 FTE days of TTOC experience credit will equal/be converted to one month of experience credit.</p>
        <p>Should the teacher choose the option to transfer, transfers must be for the entire amount of TTOC experience in their Article C.4 bank on the deadline date for notice, i.e., with the exception of any leftover days remaining (1 – 16 days) after the whole month conversion calculation is made, no partial transfer of TTOC experience are permitted. (See example below).</p>
        <p>Once transferred, the previous local Collective Agreement increment provisions for temporary/continuing employees (including effective date of increment) will apply to the TTOC experience transferred.</p>
        <p>Transfers can only occur and take effect twice a year (August 31 and December 31).</p>
        <p>For a transfer to occur effective August 31st, written notice from the employee to transfer must be received by the district no later than June 30th of the preceding school year (see attached form A). This transfer would only include the TTOC experience accrued up until June 30th of the preceding school year. Once written notice is received from the teacher to transfer the TTOC experience that decision is final and under no circumstances will the experience be transferred back to C.4.</p>
        <p>For a transfer to occur effective December 31st, written notice from the employee to transfer must be received by the district no later than November 15th of the school year (see attached form B). This transfer would only include the TTOC experience accrued up until November 15th of the school year. Once written notice is received from the teacher to transfer the TTOC experience that decision is final and under no circumstances will the experience be transferred back to C.4. (See attached form B)</p>
        <p>This agreement takes effect on the signatory date signed below.</p>
        <p>Example:</p>
        <p>On June 1, 2015, Teacher A provides written notice to the district that they would like to transfer their Article C.4 TTOC experience that they will have accrued up until June 30, 2015 (in terms of closest equivalent month) to their temporary/continuing previous local agreement increment experience bank.</p>
        <p>On June 30, 2015, Teacher A has 70 TTOC days of experience accrued under Article C.4.</p>
        <p>On August 31, 2015, 4 months of experience would be transferred to their experience bank under the applicable previous local Collective Agreement increment language for continuing and/or temporary employees and 2 days of TTOC experience would remain in their TTOC bank under Article C.4. (70 divided by 17 = 4 whole months, with 2 days remaining)</p>
        <p>Effective August 31, 2015, the previous local Collective Agreement increment language for temporary/continuing employees would then apply to the 4 months of experience that was transferred.</p>
        <p>Signed this 22nd day of April, 2015</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
        <p>TEACHER NOTICE: LOU 11 – TTOC EXPERIENCE TRANSFER REQUEST – FORM A</p>
        <p>Re: August 31st transfers for TTOC experience accrued up to and including June 30th</p>
        <p>This constitutes my written notice under LOU No. 16(c) of the Collective Agreement that I, _____________________ wish to transfer my eligible TTOC experience credits earned under Article C.4  (up to and including June 30, __________) to that of the applicable previous local Collective Agreement increment language for continuing and/or temporary employees. Transfer of these experience credits shall take place and be effective August 31, ______________.</p>
        <p>I understand that once I submit this application to the employer, this decision to transfer is final and cannot be reversed.</p>
        <p>__________________________________________________</p>
        <p>Teacher SignatureDate signed</p>
        <p>__________________________________________________</p>
        <p>District Receipt ConfirmedDate of Receipt</p>
        <p>Please Note:This written notice must be provided by the teacher and received by the district no later than June 30th of the preceding school year for a transfer for TTOC experience credits earned up to and including June 30th to take effect on August 31st of the following school year.</p>
        <p>TEACHER NOTICE: LOU 11 - TTOC EXPERIENCE TRANSFER REQUEST – FORM B</p>
        <p>Re: December 31st transfers for TTOC experience accrued up to and including November 15th</p>
        <p>This constitutes my written notice under LOU No. 11 of the Collective Agreement that I, _____________________________ wish to transfer my eligible TTOC experience credits earned under Article C.4  (up to and including November 15, __________) to that of the applicable previous local Collective Agreement increment language for continuing and/or temporary employees. Transfer of these experience credits shall take place and be effective December 31, _________.</p>
        <p>I understand that once I submit this application to the employer, this decision to transfer is final and cannot be reversed.</p>
        <p>__________________________________________________</p>
        <p>Teacher SignatureDate Signed</p>
        <p>__________________________________________________</p>
        <p>District Receipt ConfirmedDate of Receipt</p>
        <p>Please Note:This written notice must be provided by the teacher and received by the district no later than November 15th of the school year for a transfer for TTOC experience credits earned up to and including November 15th to take effect on December 31st of the same school year.</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION (BCPSEA)</p>
        <p>AND THE</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION (BCTF)</p>
        <p>Re: Agreement Regarding Restoration of Class Size, Composition, Ratios and Ancillary Language</p>
        <p>WHEREAS the Parties acknowledge that, as a result of the majority of the Supreme Court of Canada, adopting Justice Donald’s conclusion that the Education Improvement Act was unconstitutional and of no force or effect, that the BCPSEA – BCTF Collective Agreement provisions that were deleted by the Public Education Flexibility and Choice Act in 2002 and again in 2012 by the Education Improvement Act are restored.</p>
        <p>AND WHEREAS the Parties further acknowledge that the Supreme Court of Canada’s decision triggered Letter of Understanding No. 17 to the 2013 – 2019 BCPSEA – BCTF Provincial Collective Agreement which required the Parties to re-open Collective Agreement negotiations regarding the Collective Agreement provisions that were restored by the Supreme Court of Canada.</p>
        <p>AND WHEREAS the Parties further acknowledge that Letter of Understanding No.17 required an agreement “regarding implementation and/or changes to the restored language”.</p>
        <p>AND WHEREAS this Letter of Understanding has been negotiated pursuant to the Letter of Understanding No. 17 fully and finally resolves all matters related to the implementation of the Supreme Court of Canada’s Decision. As such, the Parties acknowledge that the re-opener process set out in Letter of Understanding No. 17 has been completed.</p>
        <p>THEREFORE THE PARTIES AGREE THAT:</p>
        <p>IMPLEMENTATION OF THIS LETTER OF UNDERSTANDING</p>
        <p>Shared Commitment to Equitable Access to Learning</p>
        <p>All students are entitled to equitable access to learning, achievement and the pursuit of excellence in all aspects of their education. The Parties are committed to providing all students with special needs with an inclusive learning environment which provides an opportunity for meaningful participation and the promotion of interaction with others. The implementation of this Letter of Understanding shall not result in any student being denied access to a school educational program, course, or inclusive learning environment unless the decision is based on an assessment of the student’s individual needs and abilities.</p>
      </div>

      <div class="ca-article" id="schedule-a-of-all-restored-collective-agreement-provisions">
        <h3>Schedule “A” of All Restored Collective Agreement Provisions</h3>
        <p>The Parties have developed a Schedule of BCPSEA-BCTF Collective Agreement provisions that were deleted by the Public Education Flexibility and Choice Act in 2002 and again in 2012 by the Education Improvement Act (“the restored Collective Agreement provisions”) that will be implemented pursuant to this Letter of Understanding. This Schedule is attached to this Letter of Understanding as Schedule “A”.</p>
        <p>Agreement to be Implemented</p>
        <p>School staffing will be subject to the terms and this Letter of Understanding, comply with the restored Collective Agreement provisions that are set out in Schedule “A”.</p>
        <p>NON-ENROLLING TEACHER STAFFING RATIOS</p>
        <p>4.All language pertaining to learning specialists shall be implemented as follows:</p>
        <p>The minimum district ratios of learning specialists to students shall be as follows (except as provided for in paragraph 4(B) below):</p>
        <p>Teacher librarians shall be provided on a minimum pro-rated basis of at least one teacher librarian to seven hundred and two (702) students;</p>
        <p>Counsellors shall be provided on a minimum pro-rated basis of at least one counsellor to six hundred and ninety-three (693) students;</p>
        <p>Learning assistance teachers shall be provided on a minimum pro-rated basis of at least one learning assistance teacher to five hundred and four (504) students;</p>
        <p>Special education resource teachers shall be provided on a minimum pro-rated basis of at least one special education resource teacher to three hundred and forty-two (342) students;</p>
        <p>English as a second language teachers (ESL) shall be provided on a minimum pro-rated basis of at least one ESL teacher per seventy-four (74) students.</p>
        <p>For the purpose of posting and /or filling FTE, the Employer may combine the non-enrolling teacher categories set out in paragraph 4 (A) (iii) - (v) into a single category. The Employer will have been deemed to have fulfilled its obligations under paragraphs 4 (A) (iii) – (v) where the non-enrolling teacher FTE of this single category is equivalent to the sum of the teachers required from categories 4 (A) (iii)-(v).</p>
        <p>Where a local Collective Agreement provided for services, caseload limits, or ratios additional or superior to the ratios provided for in paragraph 4 (A) above – the services, caseload limits or ratios from the local Collective Agreement shall apply.  (Provisions to be identified in Schedule “A” to this Letter of Understanding).</p>
        <p>The aforementioned employee staffing ratios shall be based on the funded FTE student enrolment numbers as reported by the Ministry of Education.</p>
        <p>Where a non-enrolling teacher position remains unfilled following the completion of the applicable local post and fill processes, the local parties will meet to discuss alternatives for utilizing the FTE in another way. Following these discussions the Superintendent will make a final decision regarding how the FTE will be deployed. This provision is time limited and will remain in effect until the renewal of the 2022-2025 BCPSEA – BCTF provincial Collective Agreement. Following the expiration of this provision, neither the language of this provision nor the practice that it establishes regarding alternatives for utilizing unfilled non-enrolling teacher positions will be referred to in any future arbitration or proceeding.</p>
        <p>PROCESS AND ANCILLARY LANGUAGE</p>
        <p>Where the local parties agree they prefer to follow a process that is different than what is set out in the applicable local Collective Agreement process and ancillary provisions, they may request that the Parties enter into discussions to amend those provisions. Upon agreement of the Parties, the amended provisions would replace the process and ancillary provisions for the respective School District and local union.</p>
        <p>(Provisions to be identified in Schedule “A” to the Letter of Understanding).</p>
        <p>CLASS SIZE AND COMPOSITION</p>
        <p>PART 1: CLASS SIZE PROVISIONS</p>
        <p>The BCPSEA – BCTF Collective Agreement provisions regarding class size that were deleted by the Public Education and Flexibility and Choice Act in 2002 and again in 2012 by the Education Improvement Act will be implemented as set out below:</p>
        <p>Class Size Provisions: K - 3</p>
        <p>The size of primary classes shall be limited as follows:</p>
        <p>Kindergarten classes shall not exceed 20 students;</p>
        <p>Grade 1 classes shall not exceed 22 students;</p>
        <p>Grade 2 classes shall not exceed 22 students;</p>
        <p>Grade 3 classes shall not exceed 22 students.</p>
        <p>Where there is more than one primary grade in any class with primary students, the class size maximum for the lower grade shall apply.</p>
        <p>Where there is a combined primary/intermediate class, an average of the maximum class size of the lowest involved primary grade and the maximum class size of the lowest involved intermediate grade will apply.</p>
        <p>K-3 Superior Provisions to Apply</p>
        <p>For primary and combined primary/intermediate classes where the restored Collective Agreement provisions provide for superior class size provisions beyond those listed in paragraphs 6 through 8 above, the superior provisions shall apply. [Provisions to be identified in Schedule “A” to this Letter of Understanding].</p>
        <p>Class Size Language: 4-12</p>
        <p>The BCPSEA-BCTF Collective Agreement provisions regarding Grade 4–12 class size that were deleted by the Public Education and Flexibility and Choice Act in 2002 and again in 2012 by the Education Improvement Act will be implemented.</p>
        <p>PART II – CLASS COMPOSITION PROVISIONS</p>
        <p>Implementation of Class Composition Language</p>
        <p>The BCPSEA-BCTF Collective Agreement provisions regarding class composition that were deleted by the Public Education and Flexibility and Choice Act in 2002 and again in 2012 by the Education Improvement Act will be implemented. The Parties agree that the implementation of this language shall not result in a student being denied access to a school, educational program, course, or inclusive learning environment unless this decision is based on an assessment of the student’s individual needs and abilities.</p>
        <p>The parties agree that the August 28, 2019 Jackson Arbitration on Special Education Designations is binding on the parties and that Arbitrator Jackson maintains jurisdiction on the implementation of the award.</p>
        <p>PART III: CLASS SIZE AND COMPOSITION COMPLIANCE AND REMEDIES</p>
        <p>Efforts to Achieve Compliance: Provincial Approach</p>
        <p>The Parties agree that paragraphs 14-16 of this agreement establish a provincial approach regarding the efforts that must be made to comply with the class size and composition provisions set out in Schedule “A” to this agreement and the remedies that are available where non-compliance occurs. This provincial approach applies to all School Districts and replaces all restored Collective Agreement provisions related to compliance and remedies for class size and composition. For clarity, the restored Collective Agreement compliance and remedy provisions that are replaced by this provincial approach are identified in Schedule “A” to this Letter of Understanding. The Parties commit to reviewing this provincial approach in the 2022 round of negotiations.</p>
        <p>Best Efforts to Be Made to Achieve Compliance</p>
        <p>School Districts will make best efforts to achieve full compliance with the Collective Agreement provisions regarding class size and composition. Best efforts shall include:</p>
        <p>Re-examining existing school boundaries;</p>
        <p>Re-examining the utilization of existing space within a school or across schools that are proximate to one another;</p>
        <p>Utilizing temporary classrooms;</p>
        <p>Reorganizing the existing classes within the school to meet any class composition language, where doing so will not result in a reduction in a maximum class size by more than:</p>
        <p>five students in grades K-3;</p>
        <p>four students for secondary shop or lab classes where the local class size limits are below 30, and;</p>
        <p>six students in all other grades.</p>
        <p>These class size reductions shall not preclude a Superintendent from approving a smaller class.</p>
        <p>Note: For the following School Districts, class sizes for K-1 split classes will not be reduced below 14 students:</p>
        <p>School District 10 (Arrow Lakes)</p>
        <p>School District 35 (Langley)</p>
        <p>School District 49 (Central Coast)</p>
        <p>School District 67 (Okanagan-Skaha)</p>
        <p>School District 74 (Gold Trail)</p>
        <p>School District 82 (Coast Mountain)</p>
        <p>School District 85 (Vancouver Island North)</p>
        <p>Renegotiating the terms of existing lease or rental contracts that restrict the School District’s ability to fully comply with the restored Collective Agreement provisions regarding class size and composition;</p>
        <p>Completing the post-and-fill process for all vacant positions.</p>
        <p>Non-Compliance</p>
        <p>Notwithstanding paragraph 14, the Parties recognize that non-compliance with class size and composition language may occur. Possible reasons for non-compliance include, but are not limited to:</p>
        <p>compelling family issues;</p>
        <p>sibling attendance at the same school;</p>
        <p>the age of the affected student(s);</p>
        <p>distance to be travelled and/or available transportation;</p>
        <p>safety of the student(s);</p>
        <p>the needs and abilities of individual student(s);</p>
        <p>accessibility to special programs and services;</p>
        <p>anticipated student attrition;</p>
        <p>time of year;</p>
        <p>physical space limitations;</p>
        <p>teacher recruitment challenges.</p>
        <p>Remedies for Non-Compliance</p>
        <p>Where a School District has, as per paragraph 14 above, made best efforts to achieve full compliance with the restored Collective Agreement provisions regarding class size and composition, but has not been able to do so:</p>
        <p>For classes that start in September, the District will not be required to make further changes to the composition of classes or the organization of the school after September 30 of the applicable school year. It is recognized that existing “flex factor” language that is set out in the restored Collective Agreement provisions will continue to apply for the duration of the class.</p>
        <p>For classes that start after September, the District will not be required to make further changes to the composition of classes or the organization of schools after 21 calendar days from the start of the class. It is recognized that existing “flex factor” language that is set out in the restored Collective Agreement provisions will continue to apply for the duration of the class.</p>
        <p>Teachers of classes that do not comply with the restored class size and composition provisions will become eligible to receive a monthly remedy for non-compliance effective October 1st (or 22 calendar days from the start of the class) as follows:</p>
        <p>(V) = (180 minutes) x (P) x (S1 + S2)</p>
        <p>V = the value of the additional compensation;</p>
        <p>P = the percentage of a full-time instructional month that the teacher teaches the class;</p>
        <p>S1 = the highest number of students enrolled in the class during the month for which the calculation is made minus the maximum class size for that class;</p>
        <p>S2 = the number of students by which the class exceeds the class composition limits of the Collective Agreement during the month for which the calculation is made;</p>
        <p>Note: If there is non-compliance for any portion of a calendar month the remedy will be provided for the entire month. It is recognized that adjustments to remedies may be triggered at any point during the school year if there is a change in S1 or S2.</p>
        <p>Once the value of the remedy has been calculated, the teacher will determine which of the following remedies will be awarded:</p>
        <p>Additional preparation time for the affected teacher;</p>
        <p>Additional non-enrolling staffing added to the school specifically to work with the affected teacher’s class;</p>
        <p>Additional enrolling staffing to co-teach with the affected teacher;</p>
        <p>Other remedies that the local parties agree would be appropriate.</p>
        <p>In the event that it is not practicable to provide the affected teacher with any of these remedies during the school year, the local parties will meet to determine what alternative remedy the teacher will receive.</p>
        <p>Dated this 26th day of March 2020.</p>
        <p>Revised with housekeeping 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION (BCPSEA)</p>
        <p>AND THE</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION (BCTF)</p>
        <p>Re: Committee to Discuss Indigenous Peoples Recognition and Reconciliation</p>
        <p>The provincial parties commit to building respectful, productive, and meaningful relationships with Indigenous groups.</p>
        <p>The parties agree to establish a committee within two (2) months of the conclusion of 2022 provincial bargaining (or other period as mutually agreed to).</p>
        <p>The committee shall be comprised of up to three (3) representatives appointed by the BCTF and up to three (3) representatives appointed by BCPSEA, unless mutually agreed otherwise.</p>
        <p>Representatives from the First Nations Education Steering Committee (FNESC), and other organizations as agreed to by the parties, will be invited to participate. The scope of participation and scheduling of these representatives will be by mutual agreement of the parties.</p>
        <p>The committee will:</p>
        <p>1. Discuss ways that the parties can support:</p>
        <p>Declaration on the Rights of Indigenous Peoples Act and specifically, the education commitments of the Declaration Act Action Plan;</p>
        <p>Truth and Reconciliation Commission of Canada: Calls to Action</p>
        <p>Review the Collective Agreement to identify ways to support the recruitment and retention of Indigenous teachers. The committee may mutually recommend to the provincial parties potential changes to the Collective Agreement.</p>
        <p>Signed this 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION (BCPSEA)</p>
        <p>AND THE</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION (BCTF)</p>
        <p>Re: Cultural Leave for Aboriginal Employees</p>
        <p>Employees in School Districts No. 61 (Greater Victoria), No. 64 (Gulf Islands), No. 85 (Vancouver Island North), No. 92 (Nisga’a), and No. 93 (Conseil Scolaire Francophone de la Colombie-Britannique) who have leaves in excess of those provided for in G. 11 Cultural Leave of Aboriginal Employees shall maintain those leaves.</p>
        <p>For clarification, the new leave provisions of Article G.11 are not in addition to the current provisions contained in local Collective Agreements.</p>
        <p>Signed this 26th day of March, 2020</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION (BCPSEA)</p>
        <p>AND THE</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION (BCTF)</p>
        <p>Re: Structural Review Committees</p>
        <p>Tri-partite sub-committee to review the split-of-issues</p>
        <p>Further to Mediator Schaub’s recommendation in his June 7, 2021 Section 53 Report, the parties agree to establish a sub-committee to review the split-of-issues between Provincial Matters and Local Matters.</p>
        <p>The sub-committee will consist of equal representation from Provincial Government, BCPSEA, and BCTF. There will be no more than three (3) representatives from each party.</p>
        <p>The sub-committee will commence within three (3) months of the conclusion of the 2022 provincial bargaining process.</p>
        <p>The committee will provide their agreed to recommendations to the appropriate Ministers of the Provincial Government and their respective parties within two (2) months of their first meeting, or another period mutually agreed to.</p>
        <p>Review of local bargaining trial procedure</p>
        <p>The parties agree to review the 2022 Local Bargaining Procedure within six (6) months of the completion of the 2022 round of provincial collective bargaining, or another period as mutually agreed to by the provincial parties.</p>
        <p>The parties may make determinations about an extension of the Procedure without prejudice to either party’s ability to raise Letter of Understanding No. 1 Re: Designation of Provincial and Local Matters in provincial collective bargaining.</p>
        <p>A committee of not more than three (3) BCPSEA and three (3) BCTF representatives will complete the review. The committee will conclude its work within two (2) months of the first meeting date, or another period as mutually agreed.</p>
        <p>Signed this 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION (BCPSEA)</p>
        <p>AND THE</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION (BCTF)</p>
        <p>Re: Benefits Improvements</p>
        <p>The parties agree to benefits improvements to the standardized Provincial Extended Health Benefits Plan in the following amounts, effective January 1, 2023:</p>
        <p>add registered clinical counsellors and registered social workers to the existing Psychologist coverage and increase the combined total to $1200 per year;</p>
        <p>in Appendix A to LOU #9 (Re: Provincial Extended Health Benefit Plan), rename the grouping of “Psychologist” coverage to “Counselling Services”;</p>
        <p>include coverage for the Dexcom Continuous Glucose Monitor;</p>
        <p>increase Chiropractic coverage to $1000;</p>
        <p>increase Massage Therapist coverage to $1000;</p>
        <p>increase Physiotherapist coverage to $1000; and</p>
        <p>increase Acupuncturist coverage to $1000.</p>
        <p>The parties further agree to enter into discussion around the allocation of:</p>
        <p>Effective July 1, 2023 $1,500,000 of ongoing money</p>
        <p>Effective July 1, 2024 an additional $2,000,000 of ongoing money</p>
        <p>The allocation of benefits improvement funding may include the standardized provincial extended health plan, local dental plan provisions, and local dental plan levels of minimum coverage.</p>
        <p>The parties will conclude benefit improvement discussion by no later than April 30, 2023.</p>
        <p>Signed this 28th day of October, 2022</p>
      </div>

      <div class="ca-article" id="letter-of-understanding-no">
        <h3>LETTER OF UNDERSTANDING NO.</h3>
        <p>BETWEEN</p>
        <p>BRITISH COLUMBIA PUBLIC SCHOOL EMPLOYERS’ ASSOCIATION (BCPSEA)</p>
        <p>AND THE</p>
        <p>BRITISH COLUMBIA TEACHERS’ FEDERATION (BCTF)</p>
        <p>Re: Employment Equity – Groups That Face Disadvantage</p>
        <p>The parties support building a public education system workforce which reflects community diversity.</p>
        <p>The parties recognize that Boards of Education may identify within their workforce the need to support groups who face disadvantage as recognized by the Office of the Human Rights Commissioner (e.g. racialized people, people with disabilities/disabled people, LGBTQ2S+ people, etc.).</p>
        <p>The parties therefore agree that:</p>
        <p>They will encourage and assist boards of education, with the support of the local teachers’ unions, to make application to the Office of the Human Rights Commissioner (under section 42 of the Human Rights Code) to obtain approval for a “special program” that would serve to attract and retain employees from groups who face disadvantage.</p>
        <p>They will encourage boards of education to consult with the local teachers’ unions regarding the identification of the group(s) the special program is intended to attract and retain.</p>
        <p>They will encourage boards of education to consult with the local teachers’ unions regarding the identification of the position(s) to which the special program application should apply. The parties recognize that a special program application may be in relation to a specific position or program, or an overall hiring objective.</p>
        <p>They will encourage and assist boards of education and local teachers’ unions to include in applications to the Office of the Human Rights Commissioner a request to grant:</p>
        <p>priority hiring rights to applicants from groups who face disadvantage; and</p>
        <p>priority in the post and fill process for employees from groups who face disadvantage.</p>
        <p>In conjunction with LOU No. 4, the provincial parties will jointly:</p>
        <p>develop communications and training which will support the application for and implementation of special programs in districts; and</p>
        <p>develop an Implementation Guide to share with boards of education and local teachers’ unions.</p>
        <p>Signed this 28th day of October, 2022</p>
        <p>INDEX</p>
        <p>A</p>
        <p>Access to Information21</p>
        <p>Access to Work site20</p>
        <p>Agreed Understanding of the Term Teacher Teaching on Call111</p>
        <p>Allowances</p>
        <p>Salary Indemnity Plan30</p>
        <p>ALTERNATE SCHOOL CALENDAR62</p>
        <p>Article G.1 Portability of Sick Leave – Simultaneously Holding Part-Time Appointments in Two Different Districts120</p>
        <p>B</p>
        <p>BCTF DUES DEDUCTION10</p>
        <p>Benefits124, 127, 144</p>
        <p>Board payment of Speech Language Pathologists’ and School Psychologists’ Professional Fees36</p>
        <p>Bulletin Boards20</p>
        <p>C</p>
        <p>Class Composition and Inclusion58</p>
        <p>COMMITTEE MEMBERSHIP10</p>
        <p>COMPASSIONATE CARE LEAVE85</p>
        <p>Cultural Leave for Aboriginal Employees90</p>
        <p>Cultural Leave for Aboriginal Employees LOU142</p>
        <p>D</p>
        <p>Definition of Qualifications47</p>
        <p>Definition of Termination or Layoff47</p>
        <p>Department Head --Release Time41</p>
        <p>Designated Teacher in Charge41</p>
        <p>DUES DEDUCTION</p>
        <p>BCTF10</p>
        <p>Local10</p>
        <p>E</p>
        <p>EI REBATE28</p>
        <p>Employment Equity - groups that face disadvantage145</p>
        <p>Employment Equity – Indigenous Peoples113</p>
        <p>Evaluation46</p>
        <p>Expedited Arbitration14</p>
        <p>Experience Recognition36, 37</p>
        <p>Explanation of TQS Categories39</p>
        <p>F</p>
        <p>FAMILY RESPONSIBILITY LEAVE87</p>
        <p>G</p>
        <p>General Benefits33</p>
        <p>GRIEVANCE PROCEDURE11</p>
        <p>H</p>
        <p>HARASSMENT/SEXUAL HARASSMENT68</p>
        <p>I</p>
        <p>Increment Dates38</p>
        <p>Indigenous Peoples - Employment Equity113</p>
        <p>Initial Placement36</p>
        <p>Internal Mail20</p>
        <p>L</p>
        <p>LAYOFF, RE-ENGAGEMENT &amp; SEVERANCE PAY47</p>
        <p>LEAVE FOR PROVINCIAL CONTRACT NEGOTIATIONS16</p>
        <p>Leave for Regulatory Business as per the Teachers Act17</p>
        <p>Leave to Serve on Affiliated Organizations19</p>
        <p>Leaves of Absence</p>
        <p>Compassionate Care Leave85</p>
        <p>Cultural Leave for Aboriginal Employees90</p>
        <p>Cultural Leave for Aboriginal Employees LOU142</p>
        <p>Family Responsibility Leave87</p>
        <p>Maternity/Pregnancy Leave SEB91</p>
        <p>Porting of Seniority – Laid off Teachers who are Currently on the Recall List122</p>
        <p>Provincial Contract Negotiations16</p>
        <p>Regulatory Business per Teachers Act17</p>
        <p>Sick Leave, Portability85</p>
        <p>Teachers returning from Parenting and Compassionate Leaves90</p>
        <p>Temporary Principal / Vice-Principal Leave89</p>
        <p>LEGISLATIVE CHANGE16</p>
        <p>Letters of Permission38</p>
        <p>Letters of Understanding</p>
        <p>Agreed Understanding of the Term Teacher Teaching on Call111</p>
        <p>Agreement Regarding Restoration of Class Size, Composition, Ratios and Ancillary Language133</p>
        <p>Appendix A to LOU No. 9 (Benefits)127</p>
        <p>Article C.2. – Porting of Seniority – Separate Seniority Lists118</p>
        <p>Article C.4 – TTOC Employment - Form A Teacher Notice TTOC Experience Transfer Request131</p>
        <p>Article C.4 – TTOC Employment - Form B Teacher Notice TTOC Experience Transfer Request132</p>
        <p>Article C.4 TTOC Employment - TTOC Experience Credit Transfer within a District129</p>
        <p>Benefits Improvements144</p>
        <p>Committee to Discuss Indigenous Peoples Recognition and Reconciliation141</p>
        <p>Cultural Leave for Aboriginal Employees142</p>
        <p>Designation of Provincial and Local Matters98</p>
        <p>Employment Equity - Groups that Face Disadvantage145</p>
        <p>Employment Equity – Indigenous Peoples113</p>
        <p>Porting of Seniority &amp; Article G.1 Portability of Sick Leave – Simultaneously Holding Part-Time Appointments in Two Different Districts120</p>
        <p>Provincial Extended Health Benefit Plan124</p>
        <p>Recruitment and Retention for Teachers at Beaverdell and Big White Elementary Schools128</p>
        <p>Review of local bargaining trial procedure143</p>
        <p>Section 27.4 Education Services Collective Agreement Act112</p>
        <p>Section 4 of Bill 27 Education Services Collective Agreement Act112</p>
        <p>Structural Review Committee143</p>
        <p>Teacher Supply and Demand Initiatives114</p>
        <p>Tripartite sub-committee to review the split-of-issues143</p>
        <p>LOCAL AND BCTF DUES DEDUCTION10</p>
        <p>M</p>
        <p>Maternity/Pregnancy Leave SEB91</p>
        <p>Medical Benefits33</p>
        <p>MEMBERSHIP REQUIREMENT9</p>
        <p>MIDDLE SCHOOLS61</p>
        <p>Mid-Month Advance31</p>
        <p>N</p>
        <p>NON-SEXIST ENVIRONMENT68</p>
        <p>Notice Period47</p>
        <p>O</p>
        <p>Officers of the BVTU Release Time18</p>
        <p>P</p>
        <p>PERSONAL PROPERTY LOSS30</p>
        <p>Personally Owned Professional Material30</p>
        <p>PORTABILITY OF SICK LEAVE85</p>
        <p>Porting of Seniority – Laid off Teachers who are Currently on the Recall List122</p>
        <p>Porting of Seniority – Separate Seniority Lists118</p>
        <p>Porting of Seniority &amp; Article G.1 Portability of Sick Leave – Simultaneously Holding Part-Time Appointments in Two Different Districts120</p>
        <p>Pregnancy Supplemental Employment Benefits91</p>
        <p>Preparation Time60</p>
        <p>President Release Time18</p>
        <p>Private Vehicle Damage30</p>
        <p>Professional Development Funding81</p>
        <p>Professional Material30</p>
        <p>R</p>
        <p>RECOGNITION OF THE UNION9</p>
        <p>REIMBURSEMENT FOR PERSONAL PROPERTY LOSS30</p>
        <p>RESIGNATION43</p>
        <p>S</p>
        <p>SALARY24</p>
        <p>SALARY INDEMNITY PLAN ALLOWANCE30</p>
        <p>Salary No Cut39</p>
        <p>Salary Part Payment39</p>
        <p>School Staff Committees20</p>
        <p>School Staff Representatives20</p>
        <p>Section 27.4 Education Services Collective Agreement Act112</p>
        <p>Security of Employment based on Seniority and Qualifications47</p>
        <p>SENIORITY43</p>
        <p>Severance Pay49</p>
        <p>SEXUAL HARASSMENT68</p>
        <p>Sick Leave, Portability85</p>
        <p>T</p>
        <p>Teacher Supply and Demand Initiatives114</p>
        <p>TEACHER TEACHING ON CALL PAY AND BENEFITS26</p>
        <p>Teachers returning from Parenting and Compassionate Leaves90</p>
        <p>Teachers&#x27; Rights of Re-engagement48</p>
        <p>Temporary Principal / Vice-Principal Leave89</p>
        <p>TERM, CONTINUATION AND RENEGOTIATION8</p>
        <p>TTOC</p>
        <p>Experience Credit28</p>
        <p>Rate of Pay26</p>
        <p>Sick Leave28</p>
        <p>TTOC Employment46</p>
        <p>Experience Credit46</p>
        <p>TTOCs CONDUCTING UNION BUSINESS89</p>
        <p>TTOCs Conducting Union Business Negotiating Team89</p>
        <p>Twelve Month Salary Payments31</p>
        <p>V</p>
        <p>Vehicle Damage30</p>
      </div>

    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-grid container">
      <div>
        <h4>Bulkley Valley Teachers' Union</h4>
        <p>Local of the BC Teachers' Federation<br>School District 54 — Smithers, BC</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <ul class="footer-nav-list">
          <li><a href="about.php">About BVTU</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li><a href="members.php">Member Resources</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Smithers, BC</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
