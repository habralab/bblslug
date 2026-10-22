#!/usr/bin/env php
<?php

require $GLOBALS['_composer_autoload_path'] ?? __DIR__ . '/vendor/autoload.php';

\Bblslug\Console\Cli::run();
