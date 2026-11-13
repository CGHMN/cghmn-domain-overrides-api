<?php

namespace Src\Classes\DnsmasqEntryParser;

class SrvHostEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry creates an SRV record for
	 * @param ?string $target The target domain this entry points towards
	 * @param ?int $port The target port this entry points towards
	 * @param ?int $priority The SRV priority for this entry
	 * @param ?int $weight The SRV weight for this entry
	 */
	public function __construct(
		string $domain,
		public ?string $target = null,
		public ?int $port = null,
		public ?int $priority = null,
		public ?int $weight = null
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
		return [
			new self(
				$fields[0],
				$fields[1] ?? null,
				\is_numeric($fields[2] ?? null) ? \intval($fields[2]) : null,
				\is_numeric($fields[3] ?? null) ? \intval($fields[3]) : null,
				\is_numeric($fields[4] ?? null) ? \intval($fields[4]) : null,
			)
		];
	}
}
