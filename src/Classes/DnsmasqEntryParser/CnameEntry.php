<?php

namespace Src\Classes\DnsmasqEntryParser;

class CnameEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry creates a CNAME record for
	 * @param string $target The target domain this entry points towards
	 * @param ?int $ttl Time-to-live value for this CNAME entry
	 */
	public function __construct(
		string $domain,
		public string $target,
		public ?int $ttl = null
	) {
		parent::__construct($domain);
	}

	/**
	 * Parse the given dnsmasq configuration value and create Cname entry objects from it
	 * @param array<string> $fields Configuration value fields from a dnsmasq.conf file
	 * @return array<self>
	 */
	protected static function parseFields(array $fields): array
	{
		// A cname entry requires at least two fields, the domain name and where it points towards
		if (\count($fields) < 2) {
			error_log("Invalid amount of fields for cname entry: " . implode(', ', $fields));
			return [];
		}

		$target = array_pop($fields);
		$ttl = null;

		if (\is_numeric($target)) {
			// If the last field is numeric, it has to be the TTL value
			$ttl = \intval($target);

			// And the value before it has to be the target domain
			$target = array_pop($fields);
		}

		return array_values(array_map(fn($domain) => new self($domain, $target, $ttl), $fields));
	}
}
