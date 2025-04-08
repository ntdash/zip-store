<?php

if (! function_exists('base_path')) {

    function base_path(string $path = ''): string
    {
        static $basepath;

        return ($basepath ??= dirname(dirname(__DIR__))).'/'.$path;
    }
}

if (! function_exists('tests_path')) {

    function tests_path(string $path = ''): string
    {
        return base_path('tests/'.$path);
    }
}
