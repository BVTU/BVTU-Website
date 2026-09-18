<?php
/**
 * lp-review.php — merged into approvals.php, which now renders both queues on
 * one page. Kept as a redirect so existing links and bookmarks keep working,
 * rather than leaving a second copy of the markup to drift out of step.
 */
header('Location: approvals.php', true, 301);
exit;
