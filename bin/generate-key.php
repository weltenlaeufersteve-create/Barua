<?php
// Prints a fresh APP_KEY for config.php. Run: php bin/generate-key.php
echo base64_encode(random_bytes(32)), PHP_EOL;
