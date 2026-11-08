<?php

declare(strict_types=1);

namespace NhanAZ\BedrockQuery;

use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Internet;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use function filter_var;
use function implode;
use function is_object;
use function json_decode;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use const FILTER_FLAG_IPV6;
use const JSON_THROW_ON_ERROR;

final class NetherNetQueryTask extends AsyncTask {

	public function __construct(
		private string $host,
		private int $port,
		private string $userAgent,
		private ?int $requestId = null,
		private float $timeout = 5.0
	) {
	}

	public function onRun(): void {
		$result = [
			"ok" => false,
			"source" => "nethernet",
			"host" => $this->host,
			"port" => $this->port,
			"data" => null,
			"error" => null,
			"requestId" => $this->requestId
		];

		try {
			$result["ok"] = true;
			$result["data"] = $this->query();
		} catch (\Throwable $e) {
			$result["error"] = $e->getMessage();
		}

		$this->setResult($result);
	}

	public function onCompletion(): void {
		Main::handleNetherNetResult($this->getResult());
	}

	private function query(): object {
		if ($this->port < 1 || $this->port > 65535) {
			throw new \RuntimeException("Port must be between 1 and 65535.");
		}

		$errors = [];
		foreach (["https", "http"] as $scheme) {
			try {
				$response = Internet::simpleCurl(
					$this->buildUrl($scheme),
					$this->timeout,
					[
						"Accept: application/json",
						"User-Agent: " . $this->userAgent
					],
					[
						CURLOPT_FOLLOWLOCATION => false,
						CURLOPT_SSL_VERIFYPEER => $scheme === "https",
						CURLOPT_SSL_VERIFYHOST => $scheme === "https" ? 2 : 0
					]
				);

				$statusCode = $response->getCode();
				if ($statusCode < 200 || $statusCode >= 300) {
					$errors[] = "{$scheme} returned HTTP {$statusCode}";
					continue;
				}

				$decoded = json_decode($response->getBody(), flags: JSON_THROW_ON_ERROR);
				if (!is_object($decoded)) {
					throw new \RuntimeException("NetherNet response must be a JSON object");
				}

				return $decoded;
			} catch (\Throwable $e) {
				$errors[] = "{$scheme}: " . $e->getMessage();
			}
		}

		throw new \RuntimeException("NetherNet signaling failed: " . implode("; ", $errors));
	}

	private function buildUrl(string $scheme): string {
		$host = $this->host;
		if ($host === "" || preg_match('/[\x00-\x20\/#?@]/', $host) === 1) {
			throw new \RuntimeException("Invalid query host");
		}

		if (str_starts_with($host, "[")) {
			if (strlen($host) < 2 || substr($host, -1) !== "]") {
				throw new \RuntimeException("Invalid IPv6 query host");
			}
		} elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
			$host = "[{$host}]";
		}

		return sprintf("%s://%s:%d/v1/join", $scheme, $host, $this->port);
	}
}
