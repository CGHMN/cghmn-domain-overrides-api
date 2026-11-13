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

		// Get query parameters
		$params = $request->getQueryParams();
		$allowDomains = \array_key_exists('allowed_domains', $params) ? explode(',', $params['allowed_domains']) : null;
		$disallowDomains = \array_key_exists('disallowed_domains', $params) ? explode(',', $params['disallowed_domains']) : null;
		$allowCategories = \array_key_exists('allowed_categories', $params) ? explode(',', $params['allowed_categories']) : null;
		$disallowCategories = \array_key_exists('disallowed_categories', $params) ? explode(',', $params['disallowed_categories']) : null;
		$allowTypes = \array_key_exists('allowed_types', $params) ? explode(',', $params['allowed_types']) : null;
		$disallowTypes = \array_key_exists('disallowed_types', $params) ? explode(',', $params['disallowed_types']) : null;
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
		if ($disallowDomains) {
			$output .= "# Disallowed domains: " . implode(', ', $disallowDomains) . "\n";
		}
		if ($allowCategories) {
			$output .= "# Allowed categories: " . implode(', ', $allowCategories) . "\n";
		}
		if ($disallowCategories) {
			$output .= "# Disallowed categories: " . implode(', ', $disallowCategories) . "\n";
		}
		if ($allowTypes) {
			$output .= "# Allowed types: " . implode(', ', $allowTypes) . "\n";
		}
		if ($disallowTypes) {
			$output .= "# Disallowed types: " . implode(', ', $disallowTypes) . "\n";
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
				($disallowDomains && DnsmasqOverrideParser::domainInArray($disallowDomains, $entry->domain)) ||
				($allowCategories && !\in_array($entry->sourceFileName, $allowCategories)) ||
				($disallowCategories && \in_array($entry->sourceFileName, $disallowCategories)) ||
				($allowTypes && !\in_array($entry->sourceFileType, $allowTypes)) ||
				($disallowTypes && \in_array($entry->sourceFileType, $disallowTypes))
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
