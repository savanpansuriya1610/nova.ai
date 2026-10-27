<?php

function captchaStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function captchaFormKey(string $form): string
{
    $allowed = ['contact', 'project', 'schedule_demo'];

    return in_array($form, $allowed, true) ? $form : 'contact';
}

function captchaGenerateCode(int $length = 6): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    $max = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }

    return $code;
}

function captchaStoreCode(string $form, string $code): void
{
    captchaStartSession();
    $key = 'captcha_' . captchaFormKey($form);
    $_SESSION[$key] = $code;
    $_SESSION[$key . '_time'] = time();
}

function captchaCheck(string $form, string $input): bool
{
    captchaStartSession();
    $key = 'captcha_' . captchaFormKey($form);
    $stored = $_SESSION[$key] ?? '';
    $storedTime = $_SESSION[$key . '_time'] ?? 0;

    if ($stored === '' || $storedTime === 0) {
        return false;
    }

    if (time() - $storedTime > 600) {
        captchaClearCode($form);
        return false;
    }

    return hash_equals($stored, trim($input));
}

function captchaValidate(string $form, string $input): bool
{
    $isValid = captchaCheck($form, $input);
    captchaClearCode($form);

    return $isValid;
}

function captchaClearCode(string $form): void
{
    captchaStartSession();
    $key = 'captcha_' . captchaFormKey($form);
    unset($_SESSION[$key], $_SESSION[$key . '_time']);
}
