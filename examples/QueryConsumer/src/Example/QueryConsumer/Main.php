<?php

declare(strict_types=1);

namespace Example\QueryConsumer;

use NhanAZ\BedrockQuery\Main as BedrockQueryPlugin;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase {

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if ($command->getName() !== "qtest") {
			return false;
		}

		$target = $this->parseTarget($args);
		if ($target === null) {
			$sender->sendMessage("Use /qtest [host] [port 1-65535].");
			return true;
		}

		$bedrockQuery = $this->getServer()->getPluginManager()->getPlugin("BedrockQuery");
		if (!$bedrockQuery instanceof BedrockQueryPlugin) {
			$sender->sendMessage("BedrockQuery plugin is not loaded.");
			return true;
		}

		$host = $target["host"];
		$port = $target["port"];
		$sender->sendMessage("Querying {$host}:{$port} via BedrockQuery...");

		$bedrockQuery->getApi()->query($host, $port, function (array $result) use ($sender): void {
			$this->sendResult($sender, $result);
		});

		return true;
	}

	/**
	 * @param list<string> $args
	 * @return array{host: string, port: int}|null
	 */
	private function parseTarget(array $args): ?array {
		$host = $args[0] ?? "test.pmmp.io";
		$portText = $args[1] ?? "19132";
		if (!is_string($host) || !is_string($portText) || $host === "" || !ctype_digit($portText) || (int) $portText < 1 || (int) $portText > 65535) {
			return null;
		}

		return ["host" => $host, "port" => (int) $portText];
	}

	/** @param array<string, mixed> $result */
	private function sendResult(CommandSender $sender, array $result): void {
		if (($result["ok"] ?? false) !== true) {
			$error = $result["error"] ?? "unknown error";
			$sender->sendMessage("Query failed: " . (is_string($error) ? $error : "unknown error"));

			return;
		}

		$source = $result["source"] ?? "unknown";
		$sender->sendMessage("Source: " . (is_string($source) ? $source : "unknown"));
		$data = $result["data"] ?? null;
		if (is_array($data)) {
			$this->sendUdpResult($sender, $data);

			return;
		}
		if (is_object($data)) {
			$this->sendApiResult($sender, $data);

			return;
		}

		$sender->sendMessage("Result payload received, but its data is not displayable.");
	}

	/** @param array<string, mixed> $data */
	private function sendUdpResult(CommandSender $sender, array $data): void {
		$hostName = $data["HostName"] ?? null;
		$players = $data["Players"] ?? null;
		$maxPlayers = $data["MaxPlayers"] ?? null;
		if (is_string($hostName) && is_int($players) && is_int($maxPlayers)) {
			$sender->sendMessage("HostName: {$hostName}");
			$sender->sendMessage("Players: {$players}/{$maxPlayers}");

			return;
		}

		$sender->sendMessage("UDP data was received, but common server fields were unavailable.");
	}

	private function sendApiResult(CommandSender $sender, object $data): void {
		$fields = get_object_vars($data);
		$motd = $fields["motd"] ?? null;
		$playerInfo = $fields["players"] ?? null;
		if (is_object($motd) && is_object($playerInfo)) {
			$motdFields = get_object_vars($motd);
			$playerFields = get_object_vars($playerInfo);
			$clean = $motdFields["clean"] ?? null;
			$online = $playerFields["online"] ?? null;
			$max = $playerFields["max"] ?? null;
			if (is_string($clean) && is_int($online) && is_int($max)) {
				$sender->sendMessage("MOTD: {$clean}");
				$sender->sendMessage("Online: {$online}/{$max}");

				return;
			}
		}

		$sender->sendMessage("HTTP data was received, but common server fields were unavailable.");
	}
}
