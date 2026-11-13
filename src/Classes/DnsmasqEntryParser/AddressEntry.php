<?php

namespace Src\Classes\DnsmasqEntryParser;

class AddressEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry overrides
	 * @param array<string> $addresses Array of IPv4 or IPv6 addresses the name points to
	 */
	public function __construct(
		string $domain,
		public array $addresses
	) {
		parent::__construct($domain);
	}

	/**
	 * Parse the given dnsmasq configuration value and create Address entry objects from it
	 * @param array<string> $fields Configuration value fields from a dnsmasq.conf file
	 * @return array<self>
	 */
	protected static function parseFields(array $fields): array
	{
		$valueField = array_pop($fields);
		$addresses = [];

		if ($valueField == '#') {
			// If the last field is a pound, treat it as a nulled domain
			$addresses = ['0.0.0.0', '::'];
		} else if (filter_var($valueField, FILTER_VALIDATE_IP) !== false) {
			// If the last field is a valid IP address, it specifies the target address
			$addresses = [$valueField];
		} else if ($valueField) {
			// Otherwise, if the last field is not empty, add it back to the fields array
			$fields[] = $valueField;
		}

		return array_values(array_map(fn(string $domain) => new self($domain, $addresses), $fields));
	}
}
