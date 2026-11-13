<?php

namespace Src\Helpers;

use Src\Classes\DnsmasqEntryParser\BaseEntry;

class DnsmasqOverrideParser
{
	/**
	 * Find all unique domain override entries from the configuration directory specified in .env
	 * @return object{
	 * 	lastUpdate: int,
	 * 	entries: array<BaseEntry>
	 * }
	 */
	public static function findUniqueDomainEntriesFromConfig(): object
	{
		$entries = [];
		$last_update = 0;

		foreach (glob($_ENV['DNSMASQ_CONF_DIRECTORY'] . '/*/') as $directory) {
			// Parse all conf files in the given directory and collect all dnsmasq override entries
			// as well as the last timestamp any of the conf files in that directory were modified
			[$last_file_mtime, $dir_entries] = array_reduce(
				glob("{$directory}/*.conf"),
				fn($array, $filename) => [
					($mtime = filemtime($filename)) > $array[0] ? $mtime : $array[0],
					[...$array[1], ...self::parseFile($filename)]
				],
				[0, []]
			);

			if (($dir_mtime = filemtime($directory)) > $last_file_mtime && $dir_mtime > $last_update) {
				// The directory was modified after any of its files, a file was likely deleted.
				// Use the directory modified timestamp as the latest update timestamp
				$last_update = $dir_mtime;
			} else if ($last_file_mtime > $last_update) {
				// Otherwise, use the latest file modified timestamp if its newer than
				// the previously recorded last update
				$last_update = $last_file_mtime;
			}

			$entries = [...$entries, ...$dir_entries];
		}

		return (object) [
			'lastUpdate' => $last_update,
			'entries' => self::getUniqueDomainEntries($entries, true)
		];
	}

	/**
	 * Find the timestamp when a file or directory was last updated within
	 * the configuration directory specified in .env
	 * @return int Last update timestamp in Unix time
	 */
	public static function findLastUpdateInConfig(): int
	{
		return array_reduce(
			glob($_ENV['DNSMASQ_CONF_DIRECTORY'] . '/*/'),
			function ($lastUpdate, $directory) {
				// Iterate over all .conf files in each directory and output the latest timestamp
				// of any file. Use each respective directory modified timestamp as the base timestamp
				// to compare against. If no file is newer than the directory, a file was likely deleted
				// within said directory.
				$lastFileUpdate = array_reduce(
					glob("{$directory}/*.conf"),
					fn($lastUpdate, $filename) => ($lastFileUpdate = filemtime($filename)) > $lastUpdate ? $lastFileUpdate : $lastUpdate,
					filemtime($directory)
				);

				return $lastFileUpdate > $lastUpdate ? $lastFileUpdate : $lastUpdate;
			},
			0
		);
	}

	/**
	 * Get all supported override entries from the given dnsmasq configuration file
	 * @param string $filename Configuration file to parse
	 * @throws \Exception when the file was not found
	 * @return array<BaseEntry> Array of all dnsmasq override entries found within the configuration file
	 */
	public static function parseFile(string $filename): array
	{
		if (!is_file($filename)) {
			throw new \Exception("File {$filename} not found for parsing");
		}

		$fh = fopen($filename, 'r');
		$entries = [];

		$fileBasename = basename($filename, '.conf');
		$fileType = match (basename(dirname($filename))) {
			'live-domains.d' => 'live',
			'dead-domains.d' => 'dead',
			'custom-tlds.d' => 'tld',
			default => null
		};

		while ($line = fgets($fh)) {
			if (str_starts_with($line, '#') || !$line) {
				continue;
			}

			if ($parsedEntries = BaseEntry::fromConfigLine($line, $fileBasename, $fileType)) {
				$entries = [...$entries, ...$parsedEntries];
			}
		}

		fclose($fh);
		return $entries;
	}

	/**
	 * Filters out unique domain entries from a list of parsed dnsmasq override entries
	 * @param array<BaseEntry> $entries Array of parsed dnsmasq override entries
	 * @return array<string, array<BaseEntry>> Unique domain override entries
	 */
	public static function getUniqueDomainEntries(array $entries, bool $filterArpa = false): array
	{
		// Nat-Sort entries by their domain name by reversing the domain during each comparison
		usort($entries, fn($a, $b) => strnatcasecmp(self::reverseDomain($a->domain), self::reverseDomain($b->domain)));

		return array_reduce($entries, function ($domains, $entry) use ($filterArpa) {
			// Ignore ARPA domains if requested
			if ($filterArpa && str_ends_with($entry->domain, '.arpa')) {
				return $domains;
			}

			// Add to unqiue domain array if the domain or its parent is not present yet
			if (!self::domainInArray(array_keys($domains), $entry->domain)) {
				$domains[$entry->domain] = $entry;
			}

			return $domains;
		}, []);
	}

	/**
	 * Tests if a domain or one of its parents is present within an array of domain names
	 * @param array $array Array to seach for the domain within
	 * @param string $domain Domain to search for
	 * @return bool True if the domain or one one of its parents is within the array, false if not
	 */
	public static function domainInArray(array $array, string $domain): bool
	{
		// Strip leading wildcard from domain, if any
		if (str_starts_with($domain, '*.')) {
			$domain = substr($domain, 2);
		}

		foreach ($array as $existingDomain) {
			if (self::isChildDomainOf($domain, $existingDomain)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Tests if one domain is a subdomain of the other or is equal to the other
	 * @param string $domain Domain to test
	 * @param string $compareTo Domain to compare to
	 * @return bool True if the first domain is a subdomain of or equal to the second domain, false if not
	 * @throws \InvalidArgumentException if the domain under test has a wildcard at any level
	 */
	public static function isChildDomainOf(string $domain, string $compareTo): bool
	{
		// Ensure the domain under test has no wildcards
		if (strstr($domain, '*')) {
			throw new \InvalidArgumentException("Cannot compare domain {$domain} containing a wildcard");
		}

		// Lower-case and trim domains
		$domain = trim(strtolower($domain));
		$compareTo = trim(strtolower($compareTo));

		// Ignore trailing dot on domains
		if (str_ends_with($domain, '.')) {
			$domain = substr($domain, 0, -1);
		}
		if (str_ends_with($compareTo, '.')) {
			$compareTo = substr($compareTo, 0, -1);
		}

		// Test domains for a direct match
		if ($domain == $compareTo) {
			return true;
		}

		// Get each level of both domains as arrays, putting the TLD at the start of each array
		$domainLevels = array_reverse(explode('.', $domain));
		$domainLevelCount = \count($domainLevels);

		$compareToLevels = array_reverse(explode('.', $compareTo));
		$compareToLevelCount = \count($compareToLevels);

		// Reject the domain to compare to if it is more specific than the domain under test, e.g.:
		// $domain    = example.org
		// $compareTo = sub.example.org
		if ($domainLevelCount < $compareToLevelCount) {
			return false;
		}

		// Walk and compare each level for a match
		for ($i = 0; $i < $domainLevelCount; $i++) {
			// If the domain to compare to is less specific than the domain under test
			// and has not mismatched within its entire depth, it is a parent of the domain under test
			if ($i >= $compareToLevelCount) {
				return true;
			}

			$domainLevel = $domainLevels[$i];
			$compareToLevel = $compareToLevels[$i];

			// A single Asterisk or empty domain part is treated a wildcard for this level, continue with next level
			if ($compareToLevel === '*' || $compareToLevel === '') {
				continue;
			}

			// Attempt to match a partial wildcard level, e.g. *example.org matching both example.org, but also e.g. otherexample.org
			if (strstr($compareTo, '*') !== false) {
				if (preg_match('/^' . str_replace('*', '.*', $compareToLevel) . '$/', $domainLevel)) {
					continue;
				} else {
					return false;
				}
			}

			// Compare both level values verbatim
			if ($domainLevel !== $compareToLevel) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Reverse the order of a domain so the TLD is at the start of the string
	 * @param string $domain Domain to revers
	 * @return string Reversed domain
	 */
	public static function reverseDomain(string $domain): string
	{
		return implode('.', array_reverse(explode('.', $domain)));
	}
}
