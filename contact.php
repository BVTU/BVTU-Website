<?php
/**
 * contact.php — Contact BVTU form
 * Sends email to the union contact address defined in members/config.php
 * Add to config.php:  define('CONTACT_EMAIL', 'your@email.com');
 */
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

// Load config for contact email
$configPath = __DIR__ . '/members/config.php';
if (file_exists($configPath)) require_once $configPath;
$contactEmail = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'president@bvtu.ca';

$success = false;
$errors  = [];
$fields  = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot — bots fill this hidden field
    if (!empty($_POST['website'])) {
        exit;
    }

    $fields['name']    = trim($_POST['name']    ?? '');
    $fields['email']   = trim($_POST['email']   ?? '');
    $fields['subject'] = trim($_POST['subject'] ?? '');
    $fields['message'] = trim($_POST['message'] ?? '');

    // Validate
    if (!$fields['name'])                          $errors[] = 'Please enter your name.';
    if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!$fields['subject'])                       $errors[] = 'Please enter a subject.';
    if (strlen($fields['message']) < 10)           $errors[] = 'Please enter a message (at least 10 characters).';

    if (empty($errors)) {
        $name    = htmlspecialchars($fields['name']);
        $email   = htmlspecialchars($fields['email']);
        $subject = htmlspecialchars($fields['subject']);
        $message = htmlspecialchars($fields['message']);

        $headers  = "From: BVTU Website <noreply@bvtu.ca>\r\n";
        $headers .= "Reply-To: {$email}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $body = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}\n\n---\nSent via bvtu.ca contact form";

        if (mail($contactEmail, "BVTU Contact: {$subject}", $body, $headers)) {
            $success = true;
            $fields  = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
        } else {
            $errors[] = 'Message could not be sent. Please try again or email us directly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Contact — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Contact the Bulkley Valley Teachers' Union. Send a message to union leadership in School District 54.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .contact-grid {
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 3.5rem;
      align-items: start;
    }
    .contact-form { display: flex; flex-direction: column; gap: 1.1rem; }
    .form-group { display: flex; flex-direction: column; gap: .4rem; }
    .form-group label {
      font-size: .88rem;
      font-weight: 600;
      color: var(--gray-700);
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
      padding: .72rem 1rem;
      font-size: .95rem;
      font-family: var(--font);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-s);
      background: var(--white);
      color: var(--text);
      transition: border-color .18s, box-shadow .18s;
      width: 100%;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(27,107,66,.12);
    }
    .form-group textarea { min-height: 160px; resize: vertical; line-height: 1.6; }
    .form-honeypot { display: none; }

    .contact-success {
      background: var(--accent);
      border: 1.5px solid var(--primary);
      border-radius: var(--radius);
      padding: 1.5rem 1.75rem;
      color: var(--primary);
    }
    .contact-success h3 { font-weight: 700; margin-bottom: .35rem; }
    .contact-success p { font-size: .93rem; opacity: .85; }

    .contact-errors {
      background: #fef2f2;
      border: 1.5px solid #fca5a5;
      border-radius: var(--radius-s);
      padding: 1rem 1.25rem;
      color: #991b1b;
      font-size: .9rem;
    }
    .contact-errors ul { margin-top: .4rem; padding-left: 1.1rem; }
    .contact-errors li { margin-bottom: .2rem; }

    .contact-info-card {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.75rem;
    }
    .contact-info-card h3 { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 1.1rem; }
    .contact-info-row {
      display: flex;
      gap: .75rem;
      align-items: flex-start;
      margin-bottom: 1rem;
      font-size: .9rem;
      color: var(--gray-700);
    }
    .contact-info-row svg { flex-shrink: 0; color: var(--primary); margin-top: .15rem; }
    .contact-info-row strong { display: block; font-weight: 600; color: var(--text); }

    @media (max-width: 768px) {
      .contact-grid { grid-template-columns: 1fr; }
      .contact-info-card { order: -1; }
    }
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
          <li class="has-dropdown">
            <a href="documents.php">Documents</a>
            <ul class="dropdown">
              <li><a href="documents.php">All Documents</a></li>
              <li><a href="collective-agreement.php">Collective Agreement</a></li>
            </ul>
          </li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
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
      <h1>Contact BVTU</h1>
      <p>Questions, concerns, or requests — send us a message and we'll get back to you.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">
      <div class="contact-grid">

        <!-- Form -->
        <div>
          <?php if ($success): ?>
            <div class="contact-success">
              <h3>Message sent!</h3>
              <p>Thank you — we'll get back to you as soon as possible.</p>
            </div>
          <?php else: ?>

            <?php if ($errors): ?>
              <div class="contact-errors">
                <strong>Please fix the following:</strong>
                <ul>
                  <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form class="contact-form" method="post" action="contact.php" novalidate>
              <!-- Honeypot -->
              <div class="form-honeypot">
                <label for="website">Leave this empty</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
              </div>

              <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($fields['name']) ?>"
                       placeholder="First and last name"
                       required autocomplete="name">
              </div>

              <div class="form-group">
                <label for="email">Your Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($fields['email']) ?>"
                       placeholder="you@example.com"
                       required autocomplete="email">
              </div>

              <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject"
                       value="<?= htmlspecialchars($fields['subject']) ?>"
                       placeholder="What is this about?"
                       required>
              </div>

              <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message"
                          placeholder="Your message…"
                          required><?= htmlspecialchars($fields['message']) ?></textarea>
              </div>

              <div>
                <button type="submit" class="btn btn-primary">Send Message</button>
              </div>
            </form>

          <?php endif; ?>
        </div>

        <!-- Info sidebar -->
        <div class="contact-info-card">
          <h3>Bulkley Valley Teachers' Union</h3>

          <div class="contact-info-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <div>
              <strong>Office</strong>
              3772-C 1st Ave<br>Smithers, BC V0J 2N0
            </div>
          </div>

          <div class="contact-info-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div>
              <strong>Response Time</strong>
              We aim to respond within 2 business days.
            </div>
          </div>

          <div class="contact-info-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <div>
              <strong>President</strong>
              Cody Lind
            </div>
          </div>

          <hr style="border:none;border-top:1px solid var(--border);margin:1.25rem 0;">

          <p style="font-size:.85rem;color:var(--gray-500);line-height:1.6;">
            For urgent matters, BCTF members can also contact the
            <a href="https://bctf.ca" target="_blank" rel="noopener">BCTF directly</a>.
          </p>
        </div>

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
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Member Resources</a></li>
          <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
          <li><a href="prod.php">PRO-D</a></li>
        </ul>
      </div>
      <div>
        <h4>Resources</h4>
        <ul class="footer-nav-list">
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="members/login.php">Member Login</a></li>
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
