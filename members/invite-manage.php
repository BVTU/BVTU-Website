<?php
/**
 * invite-manage.php — superseded by the Invitations tab on member-manage.php.
 * Kept so older bookmarks and links keep working. It previously had its own
 * copy of the invite logic that emailed on import; redirecting avoids that
 * behaviour diverging from the current flow.
 */
header('Location: member-manage.php?tab=invitations', true, 301);
exit;
