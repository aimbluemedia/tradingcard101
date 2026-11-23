<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

use TradingCard101\Auth;

Auth::requireMember();
Auth::refresh($pdo);

// Free members get a preview (latest alert, top 3); Pro sees every alert.
$AH_AREA = 'member';
$AH_FULL = Auth::isPro();

require __DIR__ . '/../src/alert_history_page.php';
