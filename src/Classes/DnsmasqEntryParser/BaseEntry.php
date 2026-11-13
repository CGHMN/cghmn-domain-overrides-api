<?php

namespace Src\Classes\DnsmasqEntryParser;

use Src\Enums\DnsmasqConfigValueType;

abstract class BaseEntry
{
	/**
	 * Create new dnsmasq domain override entry
	 * @param string $domain The domain this entry overrides
	 * @param ?string $sourceFileName File name of the configuration this entry originates from
	 * @param 'live'|'dead'|'tld'|null $sourceFileType Type of the source file, live domain override, dead domain override or custom TLD
	 */
	public function __construct(
		public string $domain,
		public ?string $sourceFileName = null,
		public ?string $sourceFileType = null
	) {
	}

	/**
	 * Parse the given dnsmasq configuration value fields and create
	 * a new entry object from it
	 * @param array<string> $fields Array of fields from a dnsmasq configuration value
	 * @return array<self>
	 */
	abstract protected static function parseFields(array $fields): array;

	/**
	 * Splits a value string by the given character and returns each field
	 * as an individual string in the return array
	 * @param string $key Configuration key
	 * @param string $value Configuration value string
	 * @param bool $trim If true, trims each field
	 * @return string[]
	 */
	private static function getFields(string $key, string $value, bool $trim = true): array
	{
		// Only `address`, `server` and `local` entries use a slash to delimit the fields,
		// all other known override entries use commas
		$delimChar = \in_array($key, ['address', 'server', 'local']) ? '/' : ',';

		$fields = str_getcsv($value, $delimChar, '"', '\\');

		// If the value is delimited by a slash, remove the first field as it is always empty
		if ($delimChar === '/') {
			array_splice($fields, 0, 1);
		}

		return $trim ? array_map('trim', $fields) : $fields;
	}

	/**
	 * Try to create a matching entry object from the given dnsmasq.conf line
	 * @param string $configLine The full configuration line from the dnsmasq.conf file
	 * @param ?string $sourceFileName File name of the configuration this line originates from
	 * @param 'live'|'dead'|'tld'|null $sourceFileType Type of the source file, live domain override, dead domain override or custom TLD
	 * @return array<self>|null Returns an array of entry objects for this configuration value or
	 *   null when the config line does not match any known entry types
	 */
	public static function fromConfigLine(string $configLine, ?string $sourceFileName = null, ?string $sourceFileType = null): array|null
	{
		// Ensure the line is a configuration entry
		if (strstr($configLine, '=') === false) {
			return null;
		}

		// Split the line at the equals sign and extract the key-value pair
		$splitLine = explode('=', $configLine);
		$key = strtolower(trim($splitLine[0]));
		$value = trim($splitLine[1]);

		// `local` is an alias for `server`
		if ($key === 'local') {
			$key = 'server';
		}

		/**
		 * Full class name of the parser class for this configuration type
		 * @var class-string<BaseEntry> $keyClassName
		 */
		$keyClassName = __NAMESPACE__ . '\\' . str_replace('-', '', ucwords($key, '-')) . 'Entry';

		// Test if a parser class for the given key exists and return its parseFields results,
		// otherwise return null
		if (class_exists($keyClassName)) {
			// Ensure parsed fields have at least one item
			$fields = self::getFields($key, $value);
			if (\count($fields) === 0) {
				return null;
			}

			$fields = $keyClassName::parseFields($fields);

			// Set source file name and type on all resulting entries
			foreach ($fields as $field) {
				$field->sourceFileName = $sourceFileName;
				$field->sourceFileType = $sourceFileType;
			}

			return $fields;
		} else {
			error_log("No parser found for configuration key {$key} ({$keyClassName})");
			return null;
		}
	}
}
