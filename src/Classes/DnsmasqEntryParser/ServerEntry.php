<?php

namespace Src\Classes\DnsmasqEntryParser;

class ServerEntry extends BaseEntry
{
	/**
	 * @param string $domain The domain this entry sets the upstream domain name server for, if any
	 * @param ?string $serverAddress Upstream server address to query for this domain
	 * @param ?int $serverPort Upstream server port to query for this domain
	 * @param ?string $outgoingInterface Name of the interface queries to an upstream server should be sent over for this domain
	 * @param ?string $sourceIp IP address to use as the source when querying an upstream server for this domain
	 * @param ?string $sourcePort Source port to use when quering an upstream server for this domain
	 */
	public function __construct(
		string $domain,
		public ?string $serverAddress = null,
		public ?int $serverPort = null,
		public ?string $outgoingInterface = null,
		public ?string $sourceIp = null,
		public ?int $sourcePort = null
	) {
		parent::__construct($domain);
	}

	/**
	 * Parse the given dnsmasq configuration value and create Server entry objects from it
	 * @param array<string> $fields Configuration value fields from a dnsmasq.conf file
	 * @return array<self>
	 */
	protected static function parseFields(array $fields): array
	{
		$valueField = array_pop($fields);
		$serverAddress = null;
		$serverPort = null;
		$outgoingInterface = null;
		$sourceIp = null;
		$sourcePort = null;

		$valueProps = explode('@', $valueField);

		// Parse first property of the value field, which is either another domain or a <server>[#<port>] property
		if (stristr($valueProps[0], '#')) {
			// The prop of the server field contains a pound sign, must be a <server>#<port> property
			[$serverAddress, $serverPort] = explode('#', $valueProps[0]);

			if (\is_numeric($serverPort)) {
				// Parse server port value into an actual integer
				$serverPort = \intval($serverPort);
			}
		} else if (filter_var($valueProps[0], FILTER_VALIDATE_IP) !== false) {
			// The field contains a valid IP address, so it has to be a server address
			$serverAddress = $valueProps[0];
		} else if (!!$valueProps[0]) {
			// The field has to be just another domain if no other filters match, add it back to the fields array
			$fields[] = $valueProps[0];
		}

		if (\count($valueProps) > 1) {
			// The server field contains at least one @, so it has the interface property
			$outgoingInterface = $valueProps[1];
		}

		if (\count($valueProps) > 2) {
			// The server field contains two @, so it has the source IP property

			if (stristr($valueProps[2], '#')) {
				// The prop of the server field contains a pound sign, must be an <ipaddress>#<port> property
				[$sourceIp, $sourcePort] = explode('#', $valueProps[2]);

				if (\is_numeric($sourcePort)) {
					// Parse server port value into an actual integer
					$sourcePort = \intval($sourcePort);
				}
			} else if (filter_var($valueProps[2], FILTER_VALIDATE_IP) !== false) {
				// The prop field is a valid IP address, must be the source IP
				$sourceIp = $valueProps[2];
			}
		}

		return array_values(array_map(fn(string $domain) => new self(
			$domain,
			$serverAddress,
			$serverPort,
			$outgoingInterface,
			$sourceIp,
			$sourcePort
		), $fields));
	}
}
