<?php

namespace Src\Controllers;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ServerRequest;
use Src\Helpers\DnsmasqOverrideParser;

class FilteredListController
{
	/**
	 * Supported list output types, how the list gets rendered
	 * @var array<string>
	 */
	private static array $supportedOutputTypes = ['text', 'list', 'dnsmasq', 'dnsmasq-list'];

	/**
	 * Get response headers common to all responses of this controller
	 * @param ServerRequest Current request data
	 * @param int $lastModified Unix timestamp of the last document modification
	 * @return array<string, string>
	 */
	private static function responseHeaders(ServerRequest $request, int $lastModified): array
	{
		return [
			'Last-Modified' => \date("D, d M Y H:i:s", $lastModified) . ' GMT',
			'ETag' => "{$lastModified}." . md5(json_encode([
				$request->getQueryParams(),
				$_ENV['DEFAULT_UPSTREAM_SERVER']
			]))
		];
	}

	/**
	 * Return all overridden domain names, optionally filtered by name, category or type
	 * @param ServerRequest $request Current request data
	 * @return HtmlResponse
	 */
	public function getFilteredList(ServerRequest $request)
	{
		$parsedData = DnsmasqOverrideParser::findUniqueDomainEntriesFromConfig();
		$lastUpdate = $parsedData->lastUpdate;
		$lastUpdateFormatted = \date("r", $parsedData->lastUpdate);
		$domainEntries = $parsedData->entries;

		// Get query parameters
		$params = $request->getQueryParams();
		$allowDomains = \array_key_exists('allowed_domains', $params) ? explode(',', $params['allowed_domains']) : null;
		$denyDomains = \array_key_exists('denied_domains', $params) ? explode(',', $params['denied_domains']) : null;
		$allowCategories = \array_key_exists('allowed_categories', $params) ? explode(',', $params['allowed_categories']) : null;
		$denyCategories = \array_key_exists('denied_categories', $params) ? explode(',', $params['denied_categories']) : null;
		$allowTypes = \array_key_exists('allowed_types', $params) ? explode(',', $params['allowed_types']) : null;
		$denyTypes = \array_key_exists('denied_types', $params) ? explode(',', $params['denied_types']) : null;
		$outputType = \array_key_exists('as', $params) ? $params['as'] : 'text';
		$upstreamServer = \array_key_exists('upstream', $params) ? $params['upstream'] : $_ENV['DEFAULT_UPSTREAM_SERVER'];

		$output = <<<EOF
		# CGHMN Overridden Domain List
		# Last Update: {$lastUpdateFormatted}
		# Last Update Timestamp: {$lastUpdate}
		EOF . "\n";

		// Ensure requested output type is supported
		if (!\in_array($outputType, self::$supportedOutputTypes)) {
			return new HtmlResponse($output .= <<<OUT
			# ERROR: Unknown output type '{$outputType}'
			# Supported output types: 
			OUT . implode(', ', self::$supportedOutputTypes) . "\n", 400, ['Content-Type' => 'text/plain']);
		}

		// Add detail info about the requested filters
		if ($allowDomains) {
			$output .= "# Allowed domains: " . implode(', ', $allowDomains) . "\n";
		}
		if ($denyDomains) {
			$output .= "# Denied domains: " . implode(', ', $denyDomains) . "\n";
		}
		if ($allowCategories) {
			$output .= "# Allowed categories: " . implode(', ', $allowCategories) . "\n";
		}
		if ($denyCategories) {
			$output .= "# Denied categories: " . implode(', ', $denyCategories) . "\n";
		}
		if ($allowTypes) {
			$output .= "# Allowed types: " . implode(', ', $allowTypes) . "\n";
		}
		if ($denyTypes) {
			$output .= "# Denied types: " . implode(', ', $denyTypes) . "\n";
		}
		$output .= "\n";

		// Clear any header text when the output is set to 'list' or '*-list'
		if ($outputType == 'list' || str_ends_with($outputType, '-list')) {
			$output = '';
		}

		foreach ($domainEntries as $entry) {
			// Apply all set filter lists
			if (
				($allowDomains && !DnsmasqOverrideParser::domainInArray($allowDomains, $entry->domain)) ||
				($denyDomains && DnsmasqOverrideParser::domainInArray($denyDomains, $entry->domain)) ||
				($allowCategories && !\in_array($entry->sourceFileName, $allowCategories)) ||
				($denyCategories && \in_array($entry->sourceFileName, $denyCategories)) ||
				($allowTypes && !\in_array($entry->sourceFileType, $allowTypes)) ||
				($denyTypes && \in_array($entry->sourceFileType, $denyTypes))
			) {
				continue;
			}

			$output .= match ($outputType) {
				'text' => <<<OUT
				# Domain:   {$entry->domain}
				# Category: {$entry->sourceFileName}
				# Type:     {$entry->sourceFileType}
				{$entry->domain}
				OUT . "\n",

				'dnsmasq' => <<<OUT
				# Domain:   {$entry->domain}
				# Category: {$entry->sourceFileName}
				# Type:     {$entry->sourceFileType}
				server = /{$entry->domain}/{$upstreamServer}
				OUT . "\n",

				'dnsmasq-list' => "server = /{$entry->domain}/{$upstreamServer}",

				'list' => $entry->domain
			} . "\n";
		}

		return new HtmlResponse($output, 200, [
			'Content-Type' => 'text/plain',
			...self::responseHeaders($request, $lastUpdate)
		]);
	}

	/**
	 * Return only the headers of a given filtered list request
	 * @param ServerRequest $request Current request data
	 * @return HtmlResponse
	 */
	public function headFilteredList(ServerRequest $request)
	{
		// Get query parameters
		$params = $request->getQueryParams();
		$outputType = \array_key_exists('as', $params) ? $params['as'] : 'text';

		// Ensure requested output type is supported
		if (!\in_array($outputType, self::$supportedOutputTypes)) {
			return new HtmlResponse('', 400, ['Content-Type' => 'text/plain']);
		}

		return new HtmlResponse('', 200, [
			'Content-Type' => 'text/plain',
			...self::responseHeaders($request, DnsmasqOverrideParser::findLastUpdateInConfig())
		]);
	}
}
