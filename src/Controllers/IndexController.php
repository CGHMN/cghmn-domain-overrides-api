<?php

namespace Src\Controllers;

use Exception;
use Laminas\Diactoros\ServerRequest;
use MiladRahimi\PhpRouter\View\View;
use Src\Helpers\DnsmasqOverrideParser;

class IndexController
{
	public function show(ServerRequest $request, View $view)
	{
		try {
			$parsedData = DnsmasqOverrideParser::findUniqueDomainEntriesFromConfig();
			$lastUpdate = $parsedData->lastUpdate;
			$orderedEntries = array_reduce(
				$parsedData?->entries ?? [],
				function ($array, $entry) {
					if (!\array_key_exists($entry->sourceFileType, $array)) {
						$array[$entry->sourceFileType] = [];
					}

					if (!\array_key_exists($entry->sourceFileName, $array[$entry->sourceFileType])) {
						$array[$entry->sourceFileType][$entry->sourceFileName] = [];
					}

					$array[$entry->sourceFileType][$entry->sourceFileName][] = $entry;

					return $array;
				},
				[]
			);
		} catch (Exception $_) {
			$lastUpdate = 0;
			$orderedEntries = [
				'dead' => [],
				'live' => [],
				'tld' => []
			];
		}

		return $view->make('index', [
			'orderedEntries' => $orderedEntries,
			'lastUpdate' => $lastUpdate,
			'lastUpdateFormatted' => \date("r", $lastUpdate)
		], 200, [
			'Last-Modified' => \date("D, d M Y H:i:s", $lastUpdate) . ' GMT',
			'ETag' => "{$lastUpdate}." . md5($_ENV['DEFAULT_UPSTREAM_SERVER'])
		]);
	}
}
