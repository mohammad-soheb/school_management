<?php

// Auto-loads every routes/school/*.php. Route names are prefixed automatically (see bootstrap/app.php).
foreach (glob(__DIR__.'/school/*.php') ?: [] as $routeFile) {
    require $routeFile;
}
