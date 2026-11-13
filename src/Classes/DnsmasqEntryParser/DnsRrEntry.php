<?php

namespace Src\Classes\DnsmasqEntryParser;

class DnsRrEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry creates an arbitrary resource record for
	 * @param int $type The numeric DNS RR type in the IN class
	 * @param ?string $value Binary string representation of the RR value
	 */
	public function __construct(
		string $domain,
		public int $type,
		public ?string $value = null
	) {
		parent::__construct($domain);
	}

	/**
	 * Parse the given dnsmasq configuration value and create DnsRr entry objects from it
	 * @param array<string> $fields Configuration value fields from a dnsmasq.conf file
	 * @return array<self>
	 */
	protected static function parseFields(array $fields): array
	{
		// A dns-rr entry requires at least two fields, the domain name and its type
		if (\count($fields) < 2) {
			error_log("Invalid amount of fields for dns-rr entry: " . implode(', ', $fields));
			return [];
		} else if (!\is_numeric($fields[1])) {
			error_log("Invalid non-numeric dns-rr type: " . implode(', ', $fields));
			return [];
		}

		$domain = $fields[0];
		$type = \intval($fields[1]);
		$value = null;

		// If value is specified, ensure it is a valid HEX string
		if (\count($fields) === 3) {
			$trimmedValue = preg_replace('/[^a-fA-F0-9]/', '', $fields[2]);

			if (!!$trimmedValue && \strlen($trimmedValue) % 2 === 0) {
				$value = hex2bin($trimmedValue);
			} else {
				error_log("Invalid dns-rr value hex: " . implode(', ', $fields));
				return [];
			}
		}

		return [new self($domain, $type, $value)];
	}
}
