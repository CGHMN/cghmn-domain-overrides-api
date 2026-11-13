<?php

namespace Src\Classes\DnsmasqEntryParser;

class MxHostEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry creates an MX record for
	 * @param ?string $hostname Hostname the MX record points towards
	 * @param ?int $preference The MX preference of this record
	 */
	public function __construct(
		string $domain,
		public ?string $hostname = null,
		public ?int $preference = null
	) {
		parent::__construct($domain);
	}

	/**
	 * Parse the given dnsmasq configuration value and create MxHost entry objects from it
	 * @param array<string> $fields Configuration value fields from a dnsmasq.conf file
	 * @return array<self>
	 */
	protected static function parseFields(array $fields): array
	{
		return [
			new self(
				$fields[0],
				$fields[1] ?? null,
				\is_numeric($fields[2] ?? null) ? \intval($fields[2]) : null
			)
		];
	}
}
