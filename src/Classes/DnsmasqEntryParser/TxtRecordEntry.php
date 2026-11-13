<?php

namespace Src\Classes\DnsmasqEntryParser;

class TxtRecordEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry creates TXT record for
	 * @param array<string> $values Text values this TXT record represents
	 */
	public function __construct(
		string $domain,
		public array $values
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
		return [new self($fields[0], \array_slice($fields, 1))];
	}
}
