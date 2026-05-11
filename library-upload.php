<?php
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/library-db.php';
requireLogin();

$member  = getMember();
$error   = '';
$success = false;
$newId   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lib_upload'])) {
    $f = fn(string $k) => trim($_POST[$k] ?? '');

    $title       = $f('title');
    $description = $f('description');
    $grades      = array_filter($_POST['grades'] ?? [], fn($g) => in_array($g, LIB_GRADES));
    $subject     = in_array($f('subject'), LIB_SUBJECTS) ? $f('subject') : '';
    $type        = in_array($f('resource_type'), LIB_TYPES) ? $f('resource_type') : '';
    $bcCurric    = $f('bc_curriculum');
    $timeReq     = $f('time_required');
    $materials   = $f('materials');
    $anonymous   = isset($_POST['anonymous']);

    // Validation
    if (!$title)           $error = 'Please enter a title.';
    elseif (!$description) $error = 'Please enter a short description.';
    elseif (empty($grades))$error = 'Please select at least one grade level.';
    elseif (!$subject)     $error = 'Please select a subject.';
    elseif (!$type)        $error = 'Please select a resource type.';
    elseif (empty($_FILES['resource_file']['name'])) $error = 'Please choose a file to upload.';
    else {
        $file    = $_FILES['resource_file'];
        $origName = basename($file['name']);
        $ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK)         $error = 'Upload failed — please try again.';
        elseif (!in_array($ext, LIB_ALLOWED_EXT))     $error = 'Only PDF, DOCX, and PPTX files are accepted.';
        elseif ($file['size'] > LIB_MAX_BYTES)        $error = 'File is too large (max 20 MB).';
        else {
            // Store under year sub-directory with unique name
            $year    = date('Y');
            $dir     = LIB_UPLOAD_DIR . $year . '/';
            if (!is_dir($dir)) mkdir($dir, 0750, true);
            $stored  = $year . '/' . uniqid('lib_', true) . '.' . $ext;
            $dest    = LIB_UPLOAD_DIR . $stored;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Could not save the file. Please contact the admin.';
            } else {
                $newId = libSaveResource([
                    'uploader_email' => $member['email'],
                    'uploader_name'  => $member['name'],
                    'anonymous'      => $anonymous,
                    'title'          => $title,
                    'description'    => $description,
                    'grade_levels'   => implode(',', $grades),
                    'subject'        => $subject,
                    'resource_type'  => $type,
                    'bc_curriculum'  => $bcCurric ?: null,
                    'time_required'  => $timeReq ?: null,
                    'materials'      => $materials ?: null,
                    'file_name'      => $origName,
                    'file_path'      => $stored,
                    'file_size'      => $file['size'],
                    'file_ext'       => $ext,
                ]);
                $success = true;
            }
        }
    }
}

$loggedIn = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Upload Resource — BVTU Library</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .upload-wrap { max-width: 720px; margin: 0 auto; }
    .upload-form { display: flex; flex-direction: column; gap: 1.4rem; }
    .upload-group { display: flex; flex-direction: column; gap: .4rem; }
    .upload-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .upload-label {
      font-size: .88rem; font-weight: 600; color: var(--gray-700);
    }
    .upload-label .req { color: var(--primary); }
    .upload-label .hint { font-weight: 400; color: var(--gray-400); font-size: .78rem; margin-left: .3rem; }
    .upload-form input[type="text"],
    .upload-form input[type="file"],
    .upload-form select,
    .upload-form textarea {
      border: 1.5px solid var(--gray-200); border-radius: 8px;
      padding: .65rem .9rem; font-size: .93rem; font-family: inherit;
      width: 100%; background: #fff; color: var(--gray-800);
      transition: border-color .2s, box-shadow .2s;
    }
    .upload-form input:focus, .upload-form select:focus, .upload-form textarea:focus {
      outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .upload-form input[type="file"] { padding: .5rem .9rem; cursor: pointer; }
    .upload-form textarea { resize: vertical; }
    .grade-grid {
      display: flex; flex-wrap: wrap; gap: .5rem;
    }
    .grade-chip {
      display: flex; align-items: center; gap: .35rem;
      border: 1.5px solid var(--gray-200); border-radius: 20px;
      padding: .3rem .85rem; font-size: .85rem; font-weight: 600;
      color: var(--gray-600); cursor: pointer; transition: all .15s;
      background: #fff; user-select: none;
    }
    .grade-chip input { display: none; }
    .grade-chip:has(input:checked) {
      background: var(--primary); border-color: var(--primary); color: #fff;
    }
    .upload-section-label {
      font-size: .74rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .06em; color: var(--gray-400);
      padding-bottom: .5rem; border-bottom: 1px solid var(--gray-100);
    }
    .upload-guidelines {
      background: #f0f9f3; border: 1px solid #b3d9bf; border-radius: 8px;
      padding: 1rem 1.2rem; font-size: .88rem; color: var(--gray-700); line-height: 1.65;
    }
    .upload-guidelines strong { color: var(--primary); }
    .upload-error {
      background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
      padding: .85rem 1rem; color: #991b1b; font-size: .9rem;
    }
    .upload-success {
      background: #f0f9f3; border: 1.5px solid #b3d9bf; border-radius: 10px;
      padding: 1.5rem 1.75rem;
    }
    .upload-success h3 { color: var(--primary); margin: 0 0 .5rem; }
    .upload-success p  { margin: 0 0 1rem; color: var(--gray-700); line-height: 1.65; font-size: .93rem; }
    .anon-toggle {
      display: flex; align-items: center; gap: .6rem;
      font-size: .9rem; color: var(--gray-700); cursor: pointer;
    }
    @media (max-width: 600px) { .upload-row { grid-template-columns: 1fr; } }
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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li></ul></li>
          <li class="has-dropdown"><a href="members.php">Members</a><ul class="dropdown"><li><a href="members.php">Member Resources</a></li><li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li><li><a href="collab-grant.php">Collaboration Grant</a></li></ul></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="library.php" class="active">Library</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>" class="btn btn-primary" style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>"><?= $loggedIn ? 'My Dashboard' : 'Member Login' ?></a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Upload a Resource</h1>
      <p>Share a lesson plan, unit, rubric, or activity with fellow BVTU members.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">
      <div class="upload-wrap">

        <?php if ($success): ?>
          <div class="upload-success">
            <h3>✓ Resource uploaded successfully!</h3>
            <p>Your resource is now available in the library for other members to download.</p>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
              <a href="library-resource.php?id=<?= $newId ?>" class="btn btn-primary">View your resource</a>
              <a href="library.php" class="btn btn-outline">Back to library</a>
              <a href="library-upload.php" class="btn btn-outline">Upload another</a>
            </div>
          </div>

        <?php else: ?>

          <div class="upload-guidelines" style="margin-bottom:1.5rem;">
            <strong>Upload guidelines:</strong> Share materials you created or have permission to share.
            Do not upload copyrighted commercial resources, student work, or anything containing personal information.
            Accepted formats: <strong>PDF, DOCX, PPTX</strong> · Max file size: <strong>20 MB</strong>.
          </div>

          <?php if ($error): ?>
            <div class="upload-error" style="margin-bottom:1.25rem;"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form class="upload-form" method="post" enctype="multipart/form-data" novalidate>

            <div class="upload-section-label">About this resource</div>

            <div class="upload-group">
              <label class="upload-label" for="ul-title">Title <span class="req">*</span></label>
              <input type="text" id="ul-title" name="title" required maxlength="500"
                placeholder="e.g. Grade 4 Fractions Unit Plan"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="upload-group">
              <label class="upload-label" for="ul-desc">Short description <span class="req">*</span>
                <span class="hint">2–4 sentences — what is it, who is it for, what does it cover?</span>
              </label>
              <textarea id="ul-desc" name="description" rows="3" required maxlength="1000"
                placeholder="A 3-week unit plan covering fractions, mixed numbers, and equivalence for Grade 4. Includes daily lessons, formative checks, and a final performance task aligned to BC Math 4."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="upload-section-label">Classification</div>

            <div class="upload-group">
              <label class="upload-label">Grade level(s) <span class="req">*</span></label>
              <div class="grade-grid">
                <?php foreach (LIB_GRADES as $g): ?>
                  <label class="grade-chip">
                    <input type="checkbox" name="grades[]" value="<?= $g ?>"
                      <?= in_array($g, $_POST['grades'] ?? []) ? 'checked' : '' ?>>
                    <?= $g === 'K' ? 'Kindergarten' : 'Grade ' . $g ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="upload-row">
              <div class="upload-group">
                <label class="upload-label" for="ul-subject">Subject <span class="req">*</span></label>
                <select id="ul-subject" name="subject" required>
                  <option value="">Select a subject…</option>
                  <?php foreach (LIB_SUBJECTS as $s): ?>
                    <option value="<?= $s ?>" <?= ($_POST['subject'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="upload-group">
                <label class="upload-label" for="ul-type">Resource type <span class="req">*</span></label>
                <select id="ul-type" name="resource_type" required>
                  <option value="">Select a type…</option>
                  <?php foreach (LIB_TYPES as $t): ?>
                    <option value="<?= $t ?>" <?= ($_POST['resource_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="upload-section-label">Optional details</div>

            <div class="upload-group">
              <label class="upload-label" for="ul-bc">BC curriculum connection
                <span class="hint">e.g. Big Idea, Curricular Competency, or Content area</span>
              </label>
              <input type="text" id="ul-bc" name="bc_curriculum" maxlength="500"
                placeholder="e.g. Math 4 — Number: Fractions are a type of number..."
                value="<?= htmlspecialchars($_POST['bc_curriculum'] ?? '') ?>">
            </div>

            <div class="upload-row">
              <div class="upload-group">
                <label class="upload-label" for="ul-time">Time required
                  <span class="hint">e.g. 3 weeks, 45 min/day</span>
                </label>
                <input type="text" id="ul-time" name="time_required" maxlength="150"
                  placeholder="3 weeks (45 min/day)"
                  value="<?= htmlspecialchars($_POST['time_required'] ?? '') ?>">
              </div>
              <div class="upload-group">
                <label class="upload-label" for="ul-materials">Materials needed</label>
                <input type="text" id="ul-materials" name="materials" maxlength="500"
                  placeholder="e.g. fraction tiles, whiteboards"
                  value="<?= htmlspecialchars($_POST['materials'] ?? '') ?>">
              </div>
            </div>

            <div class="upload-section-label">File</div>

            <div class="upload-group">
              <label class="upload-label" for="ul-file">
                File <span class="req">*</span>
                <span class="hint">PDF, DOCX, or PPTX · max 20 MB</span>
              </label>
              <input type="file" id="ul-file" name="resource_file" accept=".pdf,.docx,.pptx" required>
              <p id="ul-file-info" style="font-size:.78rem;color:var(--gray-400);margin-top:.25rem;"></p>
            </div>

            <label class="anon-toggle">
              <input type="checkbox" name="anonymous" <?= isset($_POST['anonymous']) ? 'checked' : '' ?>>
              Post anonymously (your name will not appear on the resource)
            </label>

            <div>
              <button type="submit" name="lib_upload" class="btn btn-primary" style="padding:.65rem 1.6rem;">Upload resource</button>
            </div>

          </form>

        <?php endif; ?>
      </div>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers' Union</h3>
        <p>Local of the BC Teachers' Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div><h3>Contact</h3><p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p><p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p><p><a href="contact.php">Contact Us</a></p></div>
      <div><h3>Navigate</h3><ul class="footer-nav-list"><li><a href="documents.php">Documents</a></li><li><a href="members.php">Members</a></li><li><a href="library.php">Resource Library</a></li><li><a href="prod.php">PRO-D</a></li><li><a href="bctf.php">BCTF</a></li></ul></div>
      <div><h3>Connect</h3><a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a></div>
    </div>
    <div class="footer-bottom"><div class="container"><p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p></div></div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
  <script>
    // Show file size/name after selection
    document.getElementById('ul-file').addEventListener('change', function () {
      const info = document.getElementById('ul-file-info');
      if (this.files[0]) {
        const mb = (this.files[0].size / 1048576).toFixed(1);
        info.textContent = this.files[0].name + ' — ' + mb + ' MB';
        info.style.color = this.files[0].size > 20971520 ? '#dc2626' : 'var(--gray-500)';
      }
    });
  </script>
</body>
</html>
