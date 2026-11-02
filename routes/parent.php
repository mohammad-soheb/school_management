<?php

// Auto-loads every routes/parent/*.php. Route names are prefixed automatically (see bootstrap/app.php).
foreach (glob(__DIR__.'/parent/*.php') ?: [] as $routeFile) {
    require $routeFile;
}
