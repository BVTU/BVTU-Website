<?php
require_once __DIR__ . '/members/auth.php';
if (!isLoggedIn()) {
    header('Location: members/login.php?redirect=../library-upload.php');
    exit;
}
require_once __DIR__ . '/members/library-db.php';

$member  = getMember();
$error   = '';
$success = false;
$newId   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lib_upload'])) {
    $f = fn(string $k) => trim($_POST[$k] ?? '');

    $title       = $f('title');
    $description = $f('description');
    $grades      = array_filter($_POST['grades'] ?? [], fn($g) => in_array($g, LIB_GRADES));
    // Subject: if "Other" selected, use the custom text (title-cased via libNormaliseSubject)
    $rawSubject = $f('subject');
    if ($rawSubject === 'Other' && trim($f('subject_custom')) !== '') {
        $subject = libNormaliseSubject($f('subject_custom'));
    } elseif (in_array($rawSubject, LIB_SUBJECTS)) {
        $subject = $rawSubject;
    } else {
        $subject = '';
    }
    $type        = in_array($f('resource_type'), LIB_TYPES) ? $f('resource_type') : '';
    $bcCurric    = $f('bc_curriculum');
    $timeReq     = $f('time_required');
    $materials   = $f('materials');
    $anonymous   = isset($_POST['anonymous']);
    // Tags: normalise via DB helper (lowercase, deduplicate, length-check)
    $tags = libNormaliseTags($f('tags'));

    // Validation
    if (!$title)           $error = 'Please enter a title.';
    elseif (!$description) $error = 'Please enter a short description.';
    elseif (empty($grades))$error = 'Please select at least one grade level.';
    elseif (!$subject || $subject === 'Other') $error = 'Please select a subject (or enter a custom one if you chose Other).';
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
                    'tags'           => $tags,
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
          <li><a href="library.php" class="active">Resource Library</a></li>
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
                    <option value="<?= htmlspecialchars($s) ?>"
                      <?= ($_POST['subject'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <!-- Shown when "Other" is selected -->
                <div id="subject-custom-wrap" style="display:<?= ($_POST['subject'] ?? '') === 'Other' ? 'block' : 'none' ?>;margin-top:.5rem;">
                  <input type="text" id="ul-subject-custom" name="subject_custom"
                    placeholder="e.g. French Immersion, Foods &amp; Nutrition, Film Studies"
                    maxlength="100"
                    value="<?= htmlspecialchars($_POST['subject_custom'] ?? '') ?>"
                    style="width:100%;padding:.6rem .8rem;border:1.5px solid var(--border);border-radius:var(--radius-s);font-size:.9rem;font-family:inherit;box-sizing:border-box;">
                  <p style="font-size:.75rem;color:var(--gray-400);margin:.25rem 0 0;">Capitalisation doesn't matter — "physics" and "Physics" are treated the same.</p>
                </div>
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

            <!-- ── Tags ──────────────────────────────────────── -->
            <div class="upload-section-label">Tags <span style="font-weight:400;text-transform:none;font-size:.78rem;color:var(--gray-400)">(optional but helpful for search)</span></div>
            <div class="upload-group">
              <label class="upload-label" for="ul-tags">
                Tags
                <span class="hint">Comma-separated — e.g. fractions, hands-on, inquiry</span>
              </label>
              <div style="position:relative;">
                <input type="text" id="ul-tags" name="tags" maxlength="500"
                  placeholder="Type a tag and press comma or Enter…"
                  value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>"
                  autocomplete="off" spellcheck="true"
                  style="padding-right:2rem;">
                <div id="tag-ac-dropdown"
                     style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid var(--primary);border-top:none;border-radius:0 0 var(--radius-s) var(--radius-s);box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:50;max-height:180px;overflow-y:auto;"></div>
              </div>
              <div id="tag-chips" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.5rem;min-height:1.5rem;"></div>
              <div id="tag-suggestions" style="margin-top:.65rem;display:none;">
                <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);margin-bottom:.4rem;">Suggested tags — click to add</div>
                <div id="tag-suggestion-chips" style="display:flex;flex-wrap:wrap;gap:.3rem;"></div>
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
    // File size display
    document.getElementById('ul-file').addEventListener('change', function () {
      const info = document.getElementById('ul-file-info');
      if (this.files[0]) {
        const mb = (this.files[0].size / 1048576).toFixed(1);
        info.textContent = this.files[0].name + ' — ' + mb + ' MB';
        info.style.color = this.files[0].size > 20971520 ? '#dc2626' : 'var(--gray-500)';
      }
    });

    // ── Existing tags from DB (for autocomplete) ──────────────
    const EXISTING_TAGS = <?= json_encode(libGetAllTags()) ?>;

    // ── Tag chip input + auto-suggestions ─────────────────────
    const TAG_SUGGESTIONS = <?= json_encode(libGetTagSuggestions()) ?>;

    const tagInput  = document.getElementById('ul-tags');
    const chipWrap  = document.getElementById('tag-chips');
    const suggestWrap    = document.getElementById('tag-suggestions');
    const suggestChips   = document.getElementById('tag-suggestion-chips');

    function getTags() {
      return tagInput.value.split(',').map(t => t.trim()).filter(Boolean);
    }
    function setTags(arr) {
      tagInput.value = arr.join(', ');
      renderChips();
    }
    function addTag(tag) {
      tag = tag.trim().toLowerCase();
      if (!tag) return;
      const cur = getTags().map(t => t.toLowerCase());
      if (!cur.includes(tag)) setTags([...getTags(), tag]);
    }
    function renderChips() {
      const tags = getTags();
      chipWrap.innerHTML = tags.map(t =>
        `<span style="display:inline-flex;align-items:center;gap:.25rem;background:#0369a1;color:#fff;padding:.18rem .55rem;border-radius:100px;font-size:.72rem;font-weight:600;">
           ${t}
           <button type="button" onclick="removeTag('${t.replace(/'/g,"\\'")}'))"
             style="background:none;border:none;color:#fff;cursor:pointer;font-size:.8rem;padding:0;line-height:1;">×</button>
         </span>`
      ).join('');
    }
    window.removeTag = function(tag) {
      setTags(getTags().filter(t => t.toLowerCase() !== tag.toLowerCase()));
    };
    const acDropdown = document.getElementById('tag-ac-dropdown');

    function currentPartial() {
      const parts = tagInput.value.split(',');
      return parts[parts.length - 1].trim().toLowerCase();
    }
    function commitPartial(val) {
      const parts = tagInput.value.split(',');
      parts[parts.length - 1] = ' ' + val;
      tagInput.value = parts.join(',') + ', ';
      addTag(val);
      renderChips();
      closeAC();
    }
    function closeAC() { acDropdown.style.display = 'none'; acDropdown.innerHTML = ''; }
    function showAC(matches) {
      if (!matches.length) { closeAC(); return; }
      acDropdown.innerHTML = matches.slice(0, 8).map(m =>
        `<div class="ac-item" style="padding:.5rem .85rem;font-size:.85rem;cursor:pointer;color:var(--text);"
              onmousedown="event.preventDefault();"
              onclick="window._acPick('${m.replace(/'/g,"\\'")}')">
           ${m}
         </div>`
      ).join('');
      acDropdown.style.display = 'block';
    }
    window._acPick = function(val) { commitPartial(val); tagInput.focus(); };

    // Hover highlight for dropdown items
    acDropdown.addEventListener('mouseover', e => {
      if (e.target.classList.contains('ac-item')) {
        acDropdown.querySelectorAll('.ac-item').forEach(el => el.style.background = '');
        e.target.style.background = 'var(--accent)';
      }
    });

    tagInput.addEventListener('input', function() {
      renderChips();
      const partial = currentPartial();
      if (partial.length < 2) { closeAC(); return; }
      const matches = EXISTING_TAGS.filter(t => t.startsWith(partial) && !getTags().includes(t));
      showAC(matches);
    });
    tagInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        // If dropdown is open and has items, pick first
        const first = acDropdown.querySelector('.ac-item');
        if (first && acDropdown.style.display !== 'none') {
          window._acPick(first.textContent.trim());
        } else {
          const val = currentPartial();
          if (val) { commitPartial(val); }
        }
      }
      if (e.key === 'Escape') closeAC();
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        const items = [...acDropdown.querySelectorAll('.ac-item')];
        if (items.length) items[0].focus();
      }
    });
    tagInput.addEventListener('blur', () => setTimeout(closeAC, 150));

    function updateSuggestions() {
      const subject = document.querySelector('[name=subject]')?.value;
      const type    = document.querySelector('[name=resource_type]')?.value;
      const grades  = [...document.querySelectorAll('[name="grades[]"]:checked')].map(c => c.value);

      const seen  = new Set();
      const suggs = [];
      if (subject && TAG_SUGGESTIONS.subject[subject]) TAG_SUGGESTIONS.subject[subject].forEach(t => { if (!seen.has(t)) { seen.add(t); suggs.push(t); } });
      if (type    && TAG_SUGGESTIONS.type[type])       TAG_SUGGESTIONS.type[type]      .forEach(t => { if (!seen.has(t)) { seen.add(t); suggs.push(t); } });
      grades.slice(0,2).forEach(g => {
        if (TAG_SUGGESTIONS.grade[g]) TAG_SUGGESTIONS.grade[g].forEach(t => { if (!seen.has(t)) { seen.add(t); suggs.push(t); } });
      });

      if (suggs.length) {
        suggestChips.innerHTML = suggs.slice(0, 18).map(t =>
          `<button type="button" class="tag-sugg" onclick="addTag('${t.replace(/'/g,"\\'")}');renderChips();"
             style="background:var(--gray-100);border:1px solid var(--border);border-radius:100px;padding:.18rem .55rem;font-size:.72rem;font-weight:600;color:var(--gray-600);cursor:pointer;transition:background .12s,color .12s;"
             onmouseover="this.style.background='#e0f2fe';this.style.color='#0369a1';"
             onmouseout="this.style.background='';this.style.color='';">${t}</button>`
        ).join('');
        suggestWrap.style.display = 'block';
      } else {
        suggestWrap.style.display = 'none';
      }
    }

    // Show/hide custom subject input
    const subjectSel = document.getElementById('ul-subject');
    const customWrap = document.getElementById('subject-custom-wrap');
    subjectSel.addEventListener('change', () => {
      customWrap.style.display = subjectSel.value === 'Other' ? 'block' : 'none';
    });

    // Also pull bc_curriculum suggestions into the pool
    const bcTags = TAG_SUGGESTIONS.bc_curriculum || [];

    const _origUpdateSuggestions = updateSuggestions;
    updateSuggestions = function() {
      _origUpdateSuggestions();
      // Append BC curriculum tags not already in the list
      const existing = [...suggestChips.querySelectorAll('.tag-sugg')].map(b => b.textContent);
      const extra = bcTags.filter(t => !existing.includes(t));
      if (extra.length) {
        const div = document.createElement('div');
        div.style.cssText = 'margin-top:.4rem;padding-top:.4rem;border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:.3rem;';
        div.innerHTML = '<span style="font-size:.68rem;color:var(--gray-400);width:100%;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">BC Curriculum</span>' +
          extra.slice(0, 8).map(t =>
            `<button type="button" class="tag-sugg" onclick="addTag('${t.replace(/'/g,"\\'")}');renderChips();"
               style="background:var(--gray-100);border:1px solid var(--border);border-radius:100px;padding:.18rem .55rem;font-size:.72rem;font-weight:600;color:var(--gray-600);cursor:pointer;"
               onmouseover="this.style.background='#f0fdf4';this.style.color='#166534';"
               onmouseout="this.style.background='';this.style.color='';">${t}</button>`
          ).join('');
        suggestChips.appendChild(div);
        suggestWrap.style.display = 'block';
      }
    };

    document.querySelectorAll('[name=subject],[name=resource_type],[name="grades[]"]').forEach(el => {
      el.addEventListener('change', updateSuggestions);
    });
    // Render on load if form was re-submitted with errors
    renderChips();
    updateSuggestions();
  </script>
</body>
</html>
