<?php

// Auto-loads every routes/platform/*.php. Route names are prefixed automatically (see bootstrap/app.php).
foreach (glob(__DIR__.'/platform/*.php') ?: [] as $routeFile) {
    require $routeFile;
}
