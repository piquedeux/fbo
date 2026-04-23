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

if (!function_exists('array_is_list')) {
	function array_is_list(array $array): bool
	{
		$expectedKey = 0;
		foreach ($array as $key => $_value) {
			if ($key !== $expectedKey) {
				return false;
			}
			$expectedKey++;
		}
		return true;
	}
}
require __DIR__ . '/multi-tenant/core/bootstrap.php';
