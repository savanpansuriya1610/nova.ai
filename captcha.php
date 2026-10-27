<?php

require __DIR__ . '/captcha_helper.php';

if (!extension_loaded('gd')) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'GD extension is required for captcha generation.';
    exit;
}

$form = $_GET['form'] ?? 'contact';
$code = captchaGenerateCode(6);
captchaStoreCode($form, $code);

$width = 200;
$height = 55;
$image = imagecreatetruecolor($width, $height);

$bgColor = imagecolorallocate($image, 240, 245, 252);
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

$textColors = [
    imagecolorallocate($image, 220, 53, 69),
    imagecolorallocate($image, 108, 117, 125),
    imagecolorallocate($image, 40, 167, 69),
    imagecolorallocate($image, 111, 66, 193),
    imagecolorallocate($image, 184, 134, 11),
    imagecolorallocate($image, 23, 162, 184),
];

$lineColors = [
    imagecolorallocate($image, 180, 190, 210),
    imagecolorallocate($image, 120, 170, 200),
    imagecolorallocate($image, 150, 180, 140),
];

for ($i = 0; $i < 6; $i++) {
    imageline(
        $image,
        random_int(0, $width),
        random_int(0, $height),
        random_int(0, $width),
        random_int(0, $height),
        $lineColors[array_rand($lineColors)]
    );
}

for ($i = 0; $i < 40; $i++) {
    imagesetpixel(
        $image,
        random_int(0, $width - 1),
        random_int(0, $height - 1),
        $textColors[array_rand($textColors)]
    );
}

$fontCandidates = [
    __DIR__ . '/assets/fonts/captcha.ttf',
    'C:/Windows/Fonts/arial.ttf',
    'C:/Windows/Fonts/segoeui.ttf',
];
$fontFile = '';
foreach ($fontCandidates as $candidate) {
    if (file_exists($candidate)) {
        $fontFile = $candidate;
        break;
    }
}
$useTtf = $fontFile !== '';
$charCount = strlen($code);
$spacing = $width / ($charCount + 1);

for ($i = 0; $i < $charCount; $i++) {
    $char = $code[$i];
    $color = $textColors[$i % count($textColors)];
    $angle = random_int(-18, 18);
    $fontSize = random_int(18, 22);
    $x = (int) ($spacing * ($i + 0.6));
    $y = random_int(33, 42);

    if ($useTtf) {
        imagettftext($image, $fontSize, $angle, $x, $y, $color, $fontFile, $char);
    } else {
        $builtinY = random_int(16, 22);
        imagestring($image, 5, $x, $builtinY, $char, $color);
    }
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

imagepng($image);
imagedestroy($image);
