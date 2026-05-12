<?php
require_once __DIR__ . '/members/auth.php';
if (!isLoggedIn()) {
    header('Location: members/login.php?redirect=../library-edit.php?id=' . (int)($_GET['id'] ?? 0));
    exit;
}
require_once __DIR__ . '/members/library-db.php';

$member  = getMember();
$isAdmin = libIsAdmin($member['email']);

$id       = (int)($_GET['id'] ?? 0);
$resource = $id ? libGetResource($id) : null;

// Must exist, be published (or admin), and belong to this member (or admin)
if (!$resource
    || ($resource['status'] !== 'published' && !$isAdmin)
    || ($resource['uploader_email'] !== $member['email'] && !$isAdmin)) {
    http_response_code(403);
    // Redirect with a polite message rather than a blank 403
    header('Location: library.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lib_edit'])) {
    $f = fn(string $k) => trim($_POST[$k] ?? '');

    $title       = $f('title');
    $description = $f('description');
    $grades      = array_filter($_POST['grades'] ?? [], fn($g) => in_array($g, LIB_GRADES));
    $rawSubject  = $f('subject');
    if ($rawSubject === 'Other' && trim($f('subject_custom')) !== '') {
        $subject = libNormaliseSubject($f('subject_custom'));
    } elseif (in_array($rawSubject, LIB_SUBJECTS)) {
        $subject = $rawSubject;
    } else {
        $subject = '';
    }
    $type      = in_array($f('resource_type'), LIB_TYPES) ? $f('resource_type') : '';
    $bcCurric  = $f('bc_curriculum');
    $timeReq   = $f('time_required');
    $materials = $f('materials');
    $anonymous = isset($_POST['anonymous']);
    $tags      = libNormaliseTags($f('tags'));

    // Basic validation
    if (!$title)           $error = 'Please enter a title.';
    elseif (!$description) $error = 'Please enter a short description.';
    elseif (empty($grades))$error = 'Please select at least one grade level.';
    elseif (!$subject || $subject === 'Other') $error = 'Please select a subject (or enter a custom one).';
    elseif (!$type)        $error = 'Please select a resource type.';

    if (!$error) {
        // ── Thumbnail handling ─────────────────────────────────────
        // thumb_action: 'keep' | 'remove' | 'replace'
        $thumbAction = $_POST['thumb_action'] ?? 'keep';
        $updateData  = [
            'title'         => $title,
            'description'   => $description,
            'grade_levels'  => implode(',', $grades),
            'subject'       => $subject,
            'resource_type' => $type,
            'bc_curriculum' => $bcCurric ?: null,
            'time_required' => $timeReq ?: null,
            'materials'     => $materials ?: null,
            'tags'          => $tags,
            'anonymous'     => $anonymous,
            'preview_pages' => (int)($_POST['preview_pages'] ?? 3),
        ];

        if ($thumbAction === 'remove') {
            // Delete existing thumbnail file and clear the column
            if (!empty($resource['thumbnail_path'])) {
                $tp = LIB_THUMB_DIR . basename($resource['thumbnail_path']);
                if (file_exists($tp)) @unlink($tp);
            }
            $updateData['thumbnail_path'] = '';

        } elseif ($thumbAction === 'replace') {
            $tErr = (int)($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($tErr === UPLOAD_ERR_OK) {
                $tOrig = basename($_FILES['thumbnail']['name'] ?? '');
                $tExt  = strtolower(pathinfo($tOrig, PATHINFO_EXTENSION));
                if ($tExt === 'jpeg') $tExt = 'jpg';
                $tSize = (int)($_FILES['thumbnail']['size'] ?? 0);
                $tTmp  = $_FILES['thumbnail']['tmp_name'] ?? '';

                if (!in_array($tExt, LIB_THUMB_ALLOWED_EXT)) {
                    $error = 'Thumbnail must be JPG, PNG, or WebP.';
                } elseif ($tSize > LIB_THUMB_MAX_BYTES) {
                    $error = 'Thumbnail must be 2 MB or smaller.';
                } elseif (!@getimagesize($tTmp)) {
                    $error = 'Thumbnail does not appear to be a valid image.';
                } else {
                    if (!is_dir(LIB_THUMB_DIR)) mkdir(LIB_THUMB_DIR, 0755, true);
                    $tStored = 'thumb_' . uniqid('', true) . '.' . $tExt;
                    if (!move_uploaded_file($tTmp, LIB_THUMB_DIR . $tStored)) {
                        $error = 'Could not save the thumbnail. Please try again.';
                    } else {
                        // Delete old thumbnail before replacing
                        if (!empty($resource['thumbnail_path'])) {
                            $tp = LIB_THUMB_DIR . basename($resource['thumbnail_path']);
                            if (file_exists($tp)) @unlink($tp);
                        }
                        $updateData['thumbnail_path'] = $tStored;
                    }
                }
            }
            // If no file was actually selected despite action=replace, treat as keep
        }
        // 'keep' → don't include thumbnail_path in $updateData, libUpdateResource ignores it

        if (!$error) {
            libUpdateResource($id, $updateData);
            // Reload the resource so the page reflects saved values
            $resource = libGetResource($id);
            $success  = true;
        }
    }
}

$loggedIn = true; // we already auth-checked above
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Edit Resource — BVTU Library</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .upload-wrap  { max-width: 720px; margin: 0 auto; }
    .upload-form  { display: flex; flex-direction: column; gap: 1.4rem; }
    .upload-group { display: flex; flex-direction: column; gap: .4rem; }
    .upload-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .upload-label {
      font-size: .88rem; font-weight: 600; color: var(--gray-700);
    }
    .upload-label .req  { color: var(--primary); }
    .upload-label .hint { font-weight: 400; color: var(--gray-400); font-size: .78rem; margin-left: .3rem; }
    .upload-form input[type="text"],
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
    .upload-form textarea { resize: vertical; }
    .grade-grid { display: flex; flex-wrap: wrap; gap: .5rem; }
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
    .upload-error {
      background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
      padding: .85rem 1rem; color: #991b1b; font-size: .9rem;
    }
    .upload-success {
      background: #f0f9f3; border: 1.5px solid #b3d9bf; border-radius: 10px;
      padding: 1rem 1.25rem; font-size: .92rem; color: #166534; margin-bottom: 1rem;
      display: flex; align-items: center; gap: .6rem;
    }
    .anon-toggle {
      display: flex; align-items: center; gap: .6rem;
      font-size: .9rem; color: var(--gray-700); cursor: pointer;
    }
    /* Thumbnail state buttons */
    .thumb-action-bar {
      display: flex; gap: .5rem; margin-top: .5rem;
    }
    .thumb-action-bar button {
      font-size: .8rem; font-weight: 600; padding: .3rem .75rem;
      border-radius: 6px; border: 1.5px solid var(--border);
      background: #fff; cursor: pointer; transition: border-color .15s, color .15s;
    }
    .thumb-action-bar .btn-replace { color: var(--primary); border-color: var(--primary); }
    .thumb-action-bar .btn-remove  { color: #dc2626; border-color: #dc2626; }
    /* File info bar */
    .edit-file-bar {
      display: flex; align-items: center; gap: .75rem;
      background: var(--gray-100); border: 1px solid var(--border);
      border-radius: 8px; padding: .65rem 1rem; font-size: .85rem;
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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li></ul></li>
          <li class="has-dropdown"><a href="members.php">Members</a><ul class="dropdown"><li><a href="members.php">Member Resources</a></li><li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li><li><a href="collab-grant.php">Collaboration Grant</a></li></ul></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li><a href="library.php" class="active">Resource Library</a></li>
          <li><a href="newsletter-archive.php">Newsletters</a></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li><a href="/members/dashboard.php" class="btn btn-primary" style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;background:#1a6b35;border-color:#1a6b35;">My Dashboard</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Edit Resource</h1>
      <p>Update the details for <strong><?= htmlspecialchars($resource['title']) ?></strong></p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">
      <div class="upload-wrap">

        <?php if ($success): ?>
          <div class="upload-success">
            ✓ Changes saved successfully!
            <a href="library-resource.php?id=<?= $id ?>" style="margin-left:auto;color:var(--primary);font-weight:700;text-decoration:none;font-size:.88rem;white-space:nowrap;">View resource →</a>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="upload-error" style="margin-bottom:1.25rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="upload-form" method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="lib_edit" value="1">
          <input type="hidden" name="thumb_action" id="thumb_action" value="keep">

          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;padding:.75rem 1rem;background:var(--gray-100);border-radius:8px;font-size:.85rem;color:var(--gray-600);">
            <span>
              <?= libTypeIcon($resource['resource_type']) ?>
              <strong style="color:var(--text);"><?= htmlspecialchars($resource['file_name']) ?></strong>
              <span style="color:var(--gray-400);"><?= libFormatSize($resource['file_size']) ?> · <?= strtoupper($resource['file_ext']) ?></span>
            </span>
            <span style="font-size:.75rem;color:var(--gray-400);">File cannot be changed — delete and re-upload to replace.</span>
          </div>

          <div class="upload-section-label">About this resource</div>

          <div class="upload-group">
            <label class="upload-label" for="ul-title">Title <span class="req">*</span></label>
            <input type="text" id="ul-title" name="title" required maxlength="500"
              value="<?= htmlspecialchars($resource['title']) ?>">
          </div>

          <div class="upload-group">
            <label class="upload-label" for="ul-desc">Short description <span class="req">*</span>
              <span class="hint">2–4 sentences</span>
            </label>
            <textarea id="ul-desc" name="description" rows="3" required maxlength="1000"><?= htmlspecialchars($resource['description']) ?></textarea>
          </div>

          <div class="upload-section-label">Classification</div>

          <div class="upload-group">
            <label class="upload-label">Grade level(s) <span class="req">*</span></label>
            <div class="grade-grid">
              <?php
                $savedGrades = $resource['grade_levels'] ? explode(',', $resource['grade_levels']) : [];
                foreach (LIB_GRADES as $g): ?>
                <label class="grade-chip">
                  <input type="checkbox" name="grades[]" value="<?= $g ?>"
                    <?= in_array($g, $savedGrades) ? 'checked' : '' ?>>
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
                    <?= $resource['subject'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
                <?php
                  // If the saved subject is a custom value not in LIB_SUBJECTS, select "Other"
                  $isCustomSubject = $resource['subject'] && !in_array($resource['subject'], LIB_SUBJECTS);
                  if ($isCustomSubject): ?>
                  <option value="Other" selected>Other</option>
                <?php endif; ?>
              </select>
              <div id="subject-custom-wrap" style="display:<?= $isCustomSubject ? 'block' : 'none' ?>;margin-top:.5rem;">
                <input type="text" id="ul-subject-custom" name="subject_custom"
                  placeholder="e.g. French Immersion, Foods &amp; Nutrition"
                  maxlength="100"
                  value="<?= $isCustomSubject ? htmlspecialchars($resource['subject']) : '' ?>"
                  style="width:100%;padding:.6rem .8rem;border:1.5px solid var(--border);border-radius:var(--radius-s);font-size:.9rem;font-family:inherit;box-sizing:border-box;">
              </div>
            </div>
            <div class="upload-group">
              <label class="upload-label" for="ul-type">Resource type <span class="req">*</span></label>
              <select id="ul-type" name="resource_type" required>
                <option value="">Select a type…</option>
                <?php foreach (LIB_TYPES as $t): ?>
                  <option value="<?= $t ?>" <?= $resource['resource_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="upload-section-label">Optional details</div>

          <div class="upload-group">
            <label class="upload-label" for="ul-bc">BC curriculum connection
              <span class="hint">Big Idea, Curricular Competency, or Content area</span>
            </label>
            <input type="text" id="ul-bc" name="bc_curriculum" maxlength="500"
              value="<?= htmlspecialchars($resource['bc_curriculum'] ?? '') ?>">
          </div>

          <div class="upload-row">
            <div class="upload-group">
              <label class="upload-label" for="ul-time">Time required
                <span class="hint">e.g. 3 weeks, 45 min/day</span>
              </label>
              <input type="text" id="ul-time" name="time_required" maxlength="150"
                value="<?= htmlspecialchars($resource['time_required'] ?? '') ?>">
            </div>
            <div class="upload-group">
              <label class="upload-label" for="ul-materials">Materials needed</label>
              <input type="text" id="ul-materials" name="materials" maxlength="500"
                value="<?= htmlspecialchars($resource['materials'] ?? '') ?>">
            </div>
          </div>

          <!-- ── Tags ──────────────────────────────────────────── -->
          <div class="upload-section-label">Tags <span style="font-weight:400;text-transform:none;font-size:.78rem;color:var(--gray-400)">(optional but helpful for search)</span></div>
          <div class="upload-group">
            <label class="upload-label" for="ul-tags">
              Tags
              <span class="hint">Comma-separated — e.g. fractions, hands-on, inquiry</span>
            </label>
            <div style="position:relative;">
              <input type="text" id="ul-tags" name="tags" maxlength="500"
                value="<?= htmlspecialchars($resource['tags'] ?? '') ?>"
                autocomplete="off" spellcheck="true" style="padding-right:2rem;">
              <div id="tag-ac-dropdown"
                   style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid var(--primary);border-top:none;border-radius:0 0 var(--radius-s) var(--radius-s);box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:50;max-height:180px;overflow-y:auto;"></div>
            </div>
            <div id="tag-chips" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.5rem;min-height:1.5rem;"></div>
          </div>

          <?php if ($resource['file_ext'] === 'pdf'): ?>
          <div class="upload-section-label">Preview pages</div>
          <div class="upload-group">
            <label class="upload-label" for="ul-preview-pages">
              Pages to preview
              <span class="hint">How many pages visitors can see before downloading</span>
            </label>
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
              <select id="ul-preview-pages" name="preview_pages"
                      style="width:auto;border:1.5px solid var(--gray-200);border-radius:8px;padding:.55rem .85rem;font-size:.93rem;font-family:inherit;background:#fff;">
                <?php for ($p = 1; $p <= 10; $p++): ?>
                  <option value="<?= $p ?>" <?= (int)$resource['preview_pages'] === $p ? 'selected' : '' ?>>
                    <?= $p ?> page<?= $p > 1 ? 's' : '' ?>
                  </option>
                <?php endfor; ?>
              </select>
              <span style="font-size:.82rem;color:var(--gray-400);">
                <?php
                  // We don't know total pages here (server-side) — just show current setting
                  $pp = (int)$resource['preview_pages'];
                  echo "Currently showing the first $pp page" . ($pp > 1 ? 's' : '') . " as a preview";
                ?>
              </span>
            </div>
          </div>
          <?php endif; ?>

          <!-- ── Cover image ────────────────────────────────────── -->
          <div class="upload-section-label">
            Cover Image
            <span style="font-weight:400;text-transform:none;font-size:.78rem;color:var(--gray-400);">(optional)</span>
          </div>
          <div class="upload-group">
            <label class="upload-label">Thumbnail
              <span class="hint">JPG, PNG, or WebP · max 2 MB · shown on library cards</span>
            </label>

            <?php if ($resource['file_ext'] === 'pdf'): ?>
            <!-- For PDFs: offer to capture the first-page preview as thumbnail -->
            <div id="pdf-thumb-capture-bar" style="display:none;background:#f0f9f3;border:1px solid #b3d9bf;border-radius:8px;padding:.7rem 1rem;font-size:.85rem;color:#166534;display:none;align-items:center;gap:.75rem;margin-bottom:.6rem;">
              <canvas id="pdf-thumb-canvas" style="display:none;"></canvas>
              <span style="flex:1;">PDF preview is ready — use page 1 as the cover image?</span>
              <button type="button" id="pdf-capture-btn"
                      onclick="capturePdfThumb()"
                      style="font-size:.8rem;font-weight:700;background:var(--primary);color:#fff;border:none;border-radius:6px;padding:.3rem .8rem;cursor:pointer;white-space:nowrap;">
                Use as thumbnail
              </button>
            </div>
            <?php endif; ?>

            <?php if (!empty($resource['thumbnail_path'])): ?>
            <!-- Current thumbnail — show with Replace / Remove options -->
            <div id="thumb-current">
              <div style="position:relative;border-radius:8px;overflow:hidden;background:var(--gray-100);margin-bottom:.5rem;">
                <img id="thumb-current-img"
                     src="<?= htmlspecialchars(LIB_THUMB_URL . basename($resource['thumbnail_path'])) ?>"
                     alt="Current thumbnail"
                     style="width:100%;max-height:180px;object-fit:cover;display:block;border-radius:8px;">
                <div style="position:absolute;top:.4rem;left:.5rem;background:rgba(0,0,0,.45);color:#fff;font-size:.68rem;font-weight:700;padding:.15rem .45rem;border-radius:4px;letter-spacing:.04em;text-transform:uppercase;">Current</div>
              </div>
              <div class="thumb-action-bar">
                <button type="button" class="btn-replace" onclick="startReplace()">↺ Replace</button>
                <button type="button" class="btn-remove"  onclick="removeThumbnail()">✕ Remove</button>
              </div>
            </div>
            <!-- Removed state -->
            <div id="thumb-removed" style="display:none;background:#fef2f2;border:1.5px dashed #fca5a5;border-radius:8px;padding:.85rem 1rem;font-size:.85rem;color:#991b1b;align-items:center;gap:.75rem;">
              <span style="flex:1;">Cover image will be removed when you save.</span>
              <button type="button" onclick="undoRemove()" style="background:none;border:none;color:#991b1b;font-weight:700;cursor:pointer;font-size:.82rem;text-decoration:underline;">Undo</button>
            </div>
            <?php else: ?>
            <!-- No thumbnail currently — show upload zone -->
            <div id="thumb-current" style="display:none;"></div>
            <div id="thumb-removed" style="display:none;"></div>
            <?php endif; ?>

            <!-- Upload zone (hidden until Replace is clicked or no thumb exists) -->
            <div id="thumb-zone" style="<?= empty($resource['thumbnail_path']) ? '' : 'display:none;' ?>border:2px dashed var(--gray-200);border-radius:10px;padding:1.25rem;text-align:center;cursor:pointer;background:#fafafa;position:relative;margin-top:<?= empty($resource['thumbnail_path']) ? '0' : '.5rem' ?>;transition:border-color .2s,background .2s;"
                 onmouseover="this.style.borderColor='var(--primary)';this.style.background='#f0f9f3'"
                 onmouseout="this.style.background='';">
              <input type="file" id="ul-thumb" name="thumbnail"
                     accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                     style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;border:none;padding:0;">
              <div style="font-size:1.5rem;line-height:1;margin-bottom:.35rem;">🖼️</div>
              <p style="font-size:.88rem;font-weight:600;color:var(--gray-700);margin:0 0 .2rem;"><?= empty($resource['thumbnail_path']) ? 'Add a cover image' : 'Choose a new image' ?></p>
              <p style="font-size:.75rem;color:var(--gray-400);margin:0;">or <strong style="color:var(--primary)">click to browse</strong> — JPG, PNG, WebP · max 2 MB</p>
            </div>

            <!-- New image preview -->
            <div id="thumb-new-preview" style="display:none;margin-top:.5rem;position:relative;border-radius:8px;overflow:hidden;background:var(--gray-100);">
              <img id="thumb-new-img" src="" alt="New thumbnail preview"
                   style="width:100%;max-height:180px;object-fit:cover;display:block;border-radius:8px;">
              <div style="position:absolute;top:.4rem;left:.5rem;background:rgba(26,107,53,.75);color:#fff;font-size:.68rem;font-weight:700;padding:.15rem .45rem;border-radius:4px;letter-spacing:.04em;text-transform:uppercase;">New</div>
              <button type="button" onclick="cancelReplace()"
                      style="position:absolute;top:.4rem;right:.4rem;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:24px;height:24px;font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                      title="Cancel replacement">✕</button>
              <div id="thumb-new-name" style="font-size:.75rem;color:var(--gray-500);padding:.3rem .5rem;"></div>
            </div>
          </div>

          <label class="anon-toggle">
            <input type="checkbox" name="anonymous" <?= $resource['anonymous'] ? 'checked' : '' ?>>
            Post anonymously (your name will not appear on the resource)
          </label>

          <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <button type="submit" name="lib_edit" class="btn btn-primary" style="padding:.65rem 1.6rem;">Save changes</button>
            <a href="library-resource.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
          </div>

        </form>
      </div>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div><h3>Bulkley Valley Teachers' Union</h3><p>Local of the BC Teachers' Federation</p><p>Representing educators in<br>Houston, Telkwa, and Smithers</p></div>
      <div><h3>Contact</h3><p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p><p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p><p><a href="contact.php">Contact Us</a></p></div>
      <div><h3>Navigate</h3><ul class="footer-nav-list"><li><a href="documents.php">Documents</a></li><li><a href="members.php">Members</a></li><li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li><li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li></ul></div>
      <div><h3>Connect</h3><a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a></div>
    </div>
    <div class="footer-bottom"><div class="container"><p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p></div></div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
  <script>
    // ── Subject custom field toggle ──────────────────────────
    const subjectSel = document.getElementById('ul-subject');
    const customWrap = document.getElementById('subject-custom-wrap');
    subjectSel.addEventListener('change', () => {
      customWrap.style.display = subjectSel.value === 'Other' ? 'block' : 'none';
    });

    // ── Tag chip input ───────────────────────────────────────
    const EXISTING_TAGS = <?= json_encode(libGetAllTags()) ?>;
    const tagInput  = document.getElementById('ul-tags');
    const chipWrap  = document.getElementById('tag-chips');
    const acDropdown = document.getElementById('tag-ac-dropdown');

    function getTags() {
      return tagInput.value.split(',').map(t => t.trim()).filter(Boolean);
    }
    function setTags(arr) { tagInput.value = arr.join(', '); renderChips(); }
    function addTag(tag) {
      tag = tag.trim().toLowerCase();
      if (!tag) return;
      const cur = getTags().map(t => t.toLowerCase());
      if (!cur.includes(tag)) setTags([...getTags(), tag]);
    }
    function renderChips() {
      chipWrap.innerHTML = getTags().map(t =>
        `<span style="display:inline-flex;align-items:center;gap:.25rem;background:#0369a1;color:#fff;padding:.18rem .55rem;border-radius:100px;font-size:.72rem;font-weight:600;">
           ${t}
           <button type="button" onclick="removeTag('${t.replace(/'/g,"\\'")}')"
             style="background:none;border:none;color:#fff;cursor:pointer;font-size:.85rem;padding:0 0 0 .15rem;line-height:1;opacity:.75;"
             onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.75'">×</button>
         </span>`
      ).join('');
    }
    window.removeTag = function(tag) {
      setTags(getTags().filter(t => t.toLowerCase() !== tag.toLowerCase()));
    };
    function currentPartial() {
      const parts = tagInput.value.split(',');
      return parts[parts.length - 1].trim().toLowerCase();
    }
    function commitPartial(val) {
      const parts = tagInput.value.split(',');
      parts[parts.length - 1] = ' ' + val;
      tagInput.value = parts.join(',') + ', ';
      addTag(val); renderChips(); closeAC();
    }
    function closeAC() { acDropdown.style.display = 'none'; acDropdown.innerHTML = ''; }
    function showAC(matches) {
      if (!matches.length) { closeAC(); return; }
      acDropdown.innerHTML = matches.slice(0,8).map(m =>
        `<div class="ac-item" style="padding:.5rem .85rem;font-size:.85rem;cursor:pointer;color:var(--text);"
              onmousedown="event.preventDefault();"
              onclick="window._acPick('${m.replace(/'/g,"\\'")}')">
           ${m}
         </div>`
      ).join('');
      acDropdown.style.display = 'block';
    }
    window._acPick = function(val) { commitPartial(val); tagInput.focus(); };
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
      showAC(EXISTING_TAGS.filter(t => t.startsWith(partial) && !getTags().includes(t)));
    });
    tagInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const first = acDropdown.querySelector('.ac-item');
        if (first && acDropdown.style.display !== 'none') {
          window._acPick(first.textContent.trim());
        } else {
          const val = currentPartial();
          if (val) commitPartial(val);
        }
      }
      if (e.key === 'Escape') closeAC();
    });
    tagInput.addEventListener('blur', () => setTimeout(closeAC, 150));
    renderChips(); // initialise from pre-filled value

    // ── Thumbnail state machine ──────────────────────────────
    const thumbActionInput = document.getElementById('thumb_action');
    const thumbCurrent     = document.getElementById('thumb-current');
    const thumbRemoved     = document.getElementById('thumb-removed');
    const thumbZone        = document.getElementById('thumb-zone');
    const thumbNewPreview  = document.getElementById('thumb-new-preview');
    const thumbNewImg      = document.getElementById('thumb-new-img');
    const thumbNewName     = document.getElementById('thumb-new-name');
    const thumbFileInput   = document.getElementById('ul-thumb');

    // State: 'keep' | 'remove' | 'replace'

    window.startReplace = function() {
      thumbCurrent.style.display    = 'none';
      thumbZone.style.display       = 'block';
      thumbActionInput.value        = 'replace';
      thumbFileInput.click();
    };

    window.removeThumbnail = function() {
      thumbCurrent.style.display    = 'none';
      thumbRemoved.style.display    = 'flex';
      thumbActionInput.value        = 'remove';
    };

    window.undoRemove = function() {
      thumbRemoved.style.display    = 'none';
      thumbCurrent.style.display    = 'block';
      thumbActionInput.value        = 'keep';
    };

    window.cancelReplace = function() {
      thumbFileInput.value          = '';
      thumbNewPreview.style.display = 'none';
      // Go back to showing current (if one exists) or the upload zone
      const hasCurrent = thumbCurrent && thumbCurrent.querySelector('img');
      if (hasCurrent) {
        thumbZone.style.display    = 'none';
        thumbCurrent.style.display = 'block';
      }
      thumbActionInput.value = 'keep';
    };

    if (thumbFileInput) {
      thumbFileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        thumbActionInput.value = 'replace';
        const reader = new FileReader();
        reader.onload = e => {
          thumbNewImg.src            = e.target.result;
          thumbNewName.textContent   = file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB)';
          thumbZone.style.display    = 'none';
          thumbNewPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    }

    <?php if ($resource['file_ext'] === 'pdf'): ?>
    // ── PDF preview → thumbnail capture ─────────────────────
    const pdfCaptureBar = document.getElementById('pdf-thumb-capture-bar');
    const pdfThumbCanvas = document.getElementById('pdf-thumb-canvas');
    const pdfCaptureBtn  = document.getElementById('pdf-capture-btn');

    window.capturePdfThumb = function() {
      if (!pdfThumbCanvas.width) return;
      pdfCaptureBtn.textContent = 'Saving…';
      pdfCaptureBtn.disabled    = true;
      const dataUrl = pdfThumbCanvas.toDataURL('image/jpeg', 0.88);
      const fd = new FormData();
      fd.append('id',    <?= $id ?>);
      fd.append('image', dataUrl);
      fetch('library-thumb-capture.php', { method:'POST', credentials:'same-origin', body:fd })
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            pdfCaptureBar.innerHTML = '<span style="flex:1;">✓ Saved! Reload the page to see it, or continue editing.</span>';
            // Show a live preview of the captured image
            const img = document.createElement('img');
            img.src   = data.path + '?t=' + Date.now();
            img.style.cssText = 'width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-top:.5rem;display:block;';
            pdfCaptureBar.after(img);
          } else {
            pdfCaptureBtn.textContent = 'Try again';
            pdfCaptureBtn.disabled    = false;
          }
        })
        .catch(() => { pdfCaptureBtn.textContent = 'Try again'; pdfCaptureBtn.disabled = false; });
    };

    // Load PDF.js and render page 1 off-screen
    const pdfScript = document.createElement('script');
    pdfScript.src   = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
    pdfScript.onload = function() {
      pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
      pdfjsLib.getDocument('members/library-serve.php?id=<?= $id ?>&preview=1')
        .promise.then(pdf => pdf.getPage(1))
        .then(page => {
          const vp0   = page.getViewport({ scale: 1 });
          const scale = Math.min(1.2, 600 / vp0.width);
          const vp    = page.getViewport({ scale });
          pdfThumbCanvas.width  = vp.width;
          pdfThumbCanvas.height = vp.height;
          return page.render({ canvasContext: pdfThumbCanvas.getContext('2d'), viewport: vp }).promise;
        })
        .then(() => {
          // Show the capture bar
          pdfCaptureBar.style.display = 'flex';
        })
        .catch(() => {}); // silently ignore if preview unavailable
    };
    document.head.appendChild(pdfScript);
    <?php endif; ?>
  </script>
</body>
</html>
