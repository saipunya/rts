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

echo "PHP version: " . PHP_VERSION . "\n";

$exts = ['mbstring', 'gd'];
$missingExts = array_filter($exts, fn($e) => !extension_loaded($e));
if ($missingExts) {
  echo "Missing PHP extensions: " . implode(', ', $missingExts) . "\n";
  echo "Hint: enable them in php.ini (e.g., C:\\xampp\\php\\php.ini) and restart Apache.\n";
} else {
  echo "Required PHP extensions loaded: " . implode(', ', $exts) . "\n";
}

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  echo "Composer autoload not found. Run: composer install\n";
}

$requiredFonts = [
  'THSarabunNew.ttf',
  'THSarabunNew-Bold.ttf',
  'THSarabunNew-Italic.ttf',
  'THSarabunNew-BoldItalic.ttf',
];
$fontsPath = __DIR__ . '/fonts';
$missing = [];
foreach ($requiredFonts as $f) {
  if (!file_exists($fontsPath . '/' . $f)) {
    $missing[] = $f;
  }
}
if ($missing) {
  echo "Missing Thai fonts in $fontsPath: " . implode(', ', $missing) . "\n";
  echo "Hint: add TH Sarabun or Noto Sans Thai TTFs into $fontsPath.\n";
} else {
  echo "Thai fonts OK in $fontsPath\n";
}

echo "Done.\n";
