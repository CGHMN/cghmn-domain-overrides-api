<?php

namespace Src\Controllers;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ServerRequest;
use Src\Helpers\DnsmasqOverrideParser;

class LegacyListController
{

	/**
	 * Return all overridden domain names as a formatted list
	 * @param ServerRequest $request Current request data
	 * @param 'dnsmasq'|'list' $as Format to return the list as
	 * @return HtmlResponse
	 */
	private static function getList(ServerRequest $request, string $as): HtmlResponse
	{
		try {
			$parsedData = DnsmasqOverrideParser::findUniqueDomainEntriesFromConfig();
		} catch (\Exception $exception) {
			return new HtmlResponse(
				"# Failed to fetch override data: {$exception->getMessage()}",
				500,
				['Content-Type' => 'text/plain']
			);
		}

		$lastUpdate = $parsedData->lastUpdate;
		$lastUpdateFormatted = \date("r", $parsedData->lastUpdate);
		$domainEntries = $parsedData->entries;

		$output = $as === 'dnsmasq' ? <<<EOF
			# CGHMN Overridden Domain List
			# Last Update: {$lastUpdateFormatted}
			# Last Update Timestamp: {$lastUpdate}
			EOF . "\n\n" : '';

		foreach ($domainEntries as $entry) {
			$output .= match ($as) {
				'dnsmasq' => <<<OUT
					# Domain:   {$entry->domain}
					# Category: {$entry->sourceFileName}
					# Type:     {$entry->sourceFileType}
					server = /{$entry->domain}/{$_ENV['DEFAULT_UPSTREAM_SERVER']}
					OUT . "\n",

				'list' => $entry->domain
			} . "\n";
		}

		return new HtmlResponse($output, 200, [
			'Content-Type' => 'text/plain',
			'Last-Modified' => \date("D, d M Y H:i:s", $lastUpdate) . ' GMT',
			'ETag' => "{$lastUpdate}." . md5(json_encode([
				$request->getQueryParams(),
				$_ENV['DEFAULT_UPSTREAM_SERVER']
			]))
		]);
	}

	/**
	 * Return all overridden domain names as a DNSmasq compatible list
	 * @param ServerRequest $request Current request data
	 * @return HtmlResponse
	 */
	public function getDnsmasqList(ServerRequest $request)
	{
		return self::getList($request, 'dnsmasq');
	}

	/**
	 * Return all overridden domain names as a simple string list
	 * @param ServerRequest $request Current request data
	 * @return HtmlResponse
	 */
	public function getSimpleList(ServerRequest $request)
	{
		return self::getList($request, 'list');
	}
}
