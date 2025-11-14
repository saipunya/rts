<?php
$dirs = ['tmp','fonts'];
foreach ($dirs as $d) {
  $path = __DIR__ . '/' . $d;
  if (!is_dir($path)) {
    if (@mkdir($path, 0777, true)) {
      echo "Created: $path\n";
    } else {
      echo "Failed to create: $path\n";
    }
  } else {
    echo "Exists: $path\n";
  }
}
echo "Done.\n";
