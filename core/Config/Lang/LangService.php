<?php

declare(strict_types=1);

namespace Volt\Core\Config\Lang;

class LangService
{
    private const DEFAULT_LANG = 'en';

    private const SUPPORTED_LANGS = ['en', 'vi'];

    private static ?array $strings = null;

    private static ?string $currentLang = null;

    public static function load(?string $lang = null): array
    {
        if ($lang === null) {
            $lang = self::resolveLang();
        }

        if (self::$strings !== null && self::$currentLang === $lang) {
            return self::$strings;
        }

        $lang = in_array($lang, self::SUPPORTED_LANGS, true) ? $lang : self::DEFAULT_LANG;

        $path = __DIR__ . DIRECTORY_SEPARATOR . $lang . '.php';

        if (! is_file($path)) {
            $path = __DIR__ . DIRECTORY_SEPARATOR . self::DEFAULT_LANG . '.php';
        }

        self::$strings = require $path;
        self::$currentLang = $lang;

        return self::$strings;
    }

    public static function get(string $key, array $params = [], ?string $lang = null): string
    {
        $strings = self::load($lang);
        $segments = explode('.', $key);
        $value = $strings;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $key;
            }
            $value = $value[$segment];
        }

        if (! is_string($value)) {
            return $key;
        }

        if ($params !== []) {
            $value = self::interpolate($value, $params);
        }

        return $value;
    }

    public static function getLang(): string
    {
        return self::$currentLang ?? self::resolveLang();
    }

    public static function supported(): array
    {
        $list = [];
        foreach (self::SUPPORTED_LANGS as $code) {
            $path = __DIR__ . DIRECTORY_SEPARATOR . $code . '.php';
            if (is_file($path)) {
                $strings = require $path;
                $list[] = [
                    'code' => $strings['code'] ?? $code,
                    'name' => $strings['name'] ?? $code,
                ];
            }
        }
        return $list;
    }

    private static function resolveLang(): string
    {
        if (function_exists('session')) {
            $sessionLang = session()->get('volt_language');
            if (is_string($sessionLang) && $sessionLang !== '') {
                return $sessionLang;
            }
        }

        if (function_exists('service')) {
            try {
                $setting = service('voltSystemSetting');
                return $setting->get('language', self::DEFAULT_LANG);
            } catch (\Throwable) {
            }
        }

        return self::DEFAULT_LANG;
    }

    private static function interpolate(string $text, array $params): string
    {
        foreach ($params as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return $text;
    }
}
