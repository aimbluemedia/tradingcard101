<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

use TradingCard101\Auth;

Auth::requireAdmin();

// The same history members see, always in full.
$AH_AREA = 'admin';
$AH_FULL = true;

require __DIR__ . '/../src/alert_history_page.php';
