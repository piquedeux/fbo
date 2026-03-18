<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
	function str_starts_with(string $haystack, string $needle): bool
	{
		if ($needle === '') {
			return true;
		}
		return substr($haystack, 0, strlen($needle)) === $needle;
	}
}

if (!function_exists('str_contains')) {
	function str_contains(string $haystack, string $needle): bool
	{
		if ($needle === '') {
			return true;
		}
		return strpos($haystack, $needle) !== false;
	}
}
require __DIR__ . '/multi-tenant/core/bootstrap.php';
