<?php

namespace Src\Classes\DnsmasqEntryParser;

class HostRecordEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry creates a host record for
	 * @param ?string $ipv4Address The IPv4 address for this host record
	 * @param ?string $ipv6Address The IPv6 address for this host record
	 * @param ?int $ttl Time-to-live value for this host record
	 */
	public function __construct(
		string $domain,
		public ?string $ipv4Address,
		public ?string $ipv6Address,
		public ?int $ttl = null
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
		// A cname entry requires at least three fields, the host name and its IP addresses
		if (\count($fields) < 2) {
			error_log("Invalid amount of fields for host-record entry: " . implode(', ', $fields));
			return [];
		}

		$ipv6Address = array_pop($fields);
		$ipv4Address = array_pop($fields);
		$ttl = null;

		if (\is_numeric($ipv6Address)) {
			// If the last field is numeric, it has to be the TTL value
			$ttl = \intval($ipv6Address);

			// And the two values before it have to be the IP addresses
			$ipv6Address = $ipv4Address;
			$ipv4Address = array_pop($fields);
		}

		return array_values(array_map(fn($domain) => new self($domain, $ipv4Address ?: null, $ipv6Address ?: null, $ttl), $fields));
	}
}
