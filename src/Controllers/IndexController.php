<?php

namespace Src\Controllers;

use Laminas\Diactoros\ServerRequest;
use MiladRahimi\PhpRouter\View\View;
use Src\Helpers\DnsmasqOverrideParser;

class IndexController
{
	public function show(ServerRequest $request, View $view)
	{
		$parsedData = DnsmasqOverrideParser::findUniqueDomainEntriesFromConfig();

		$orderedEntries = array_reduce(
			$parsedData->entries,
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

		return $view->make('index', [
			'orderedEntries' => $orderedEntries,
			'lastUpdate' => $parsedData->lastUpdate,
			'lastUpdateFormatted' => \date("r", $parsedData->lastUpdate)
		], 200, [
			'Last-Modified' => \date("D, d M Y H:i:s", $parsedData->lastUpdate) . ' GMT',
			'ETag' => "{$parsedData->lastUpdate}." . md5($_ENV['DEFAULT_UPSTREAM_SERVER'])
		]);
	}
}
