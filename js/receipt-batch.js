/**
 * receipt-batch.js — many receipts in, one voucher line each.
 *
 * Used by lp-voucher-new.php and lp-voucher-edit.php, from the drop zone and
 * the "Add receipts" button. Each page supplies addRow(), firstFreeRowId(),
 * showLocalPreview() and uploadAndScan() (which returns its fetch promise).
 *
 * Why rows are reserved up front: a row only looks occupied once its scan
 * comes back and writes receipt_path. Choosing rows as each upload started
 * meant every file in a batch saw the same empty row, so receipt 1 filled the
 * line and the rest were stacked onto it as extra receipts. Each file now
 * takes its row before any upload starts, and rowIsUntouched() refuses a
 * reserved row, so the phone path cannot land on one mid-batch either.
 *
 * Scans run a few at a time rather than all at once: each is a Vision call,
 * and a folder of twenty would otherwise fire twenty together.
 */
(function () {
  var PARALLEL = 3;
  var OK_EXT = /\.(jpe?g|png|webp|gif|heic|heif|pdf)$/i;

  /* Mac browsers often give HEIC an empty type, so the name decides then. */
  function isReceiptFile(f) {
    if (f.type && (f.type.indexOf('image/') === 0 || f.type === 'application/pdf')) return true;
    return !f.type && OK_EXT.test(f.name || '');
  }

  function reserveRow() {
    var id = firstFreeRowId();
    if (!id) id = addRow();
    var tr = document.getElementById('row-' + id);
    if (tr) tr.dataset.batchReserved = '1';
    return id;
  }

  function release(id) {
    var tr = document.getElementById('row-' + id);
    if (tr) delete tr.dataset.batchReserved;
  }

  window.bvtuReceiptBatch = function (fileList) {
    var files = Array.prototype.filter.call(fileList || [], isReceiptFile);
    var skipped = (fileList ? fileList.length : 0) - files.length;
    if (!files.length) {
      if (skipped && typeof showToast === 'function') showToast('⚠ Only photos and PDFs can be read');
      return;
    }

    var jobs = files.map(function (file) {
      var id = reserveRow();
      showLocalPreview(id, file);
      return { file: file, id: id };
    });

    var next = 0, done = 0;
    function startOne() {
      if (next >= jobs.length) return;
      var job = jobs[next++];
      var p;
      try { p = uploadAndScan(job.file, job.id); } catch (e) { p = null; }
      Promise.resolve(p).catch(function () {}).then(function () {
        release(job.id);
        done++;
        if (done === jobs.length) {
          if (typeof showToast === 'function') {
            showToast('✅ ' + jobs.length + (jobs.length === 1 ? ' receipt' : ' receipts') + ' read'
                      + (skipped ? ' · ' + skipped + ' skipped (not a photo or PDF)' : ''));
          }
        } else {
          startOne();
        }
      });
    }
    for (var i = 0; i < Math.min(PARALLEL, jobs.length); i++) startOne();
  };
})();
