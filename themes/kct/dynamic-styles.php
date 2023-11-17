<?php
header('Content-Type: text/css'); // Set header for CSS

// Your dynamic colors retrieved from PHP
$primary_color = '#0178A3';
$secondary_color = '#1E3842';

// Output SCSS with PHP variables
echo <<<EOT
:root {
  --primary-color: $primary_color;
  --secondary-color: $secondary_color;
}
EOT;
?>
