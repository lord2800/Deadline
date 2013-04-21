<?php

Phar::mungServer(['REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'SCRIPT_FILENAME']);
Phar::webPhar('deadline.phar', 'public/index.php', 'public/index.php', [], function () { return 'public/index.php'; });

__HALT_COMPILER();