<?php

declare(strict_types=1);

namespace pocketmine\command {
    class Command {}
    class CommandSender {
        /** @var list<string> */
        public array $messages = [];
        public function sendMessage(string $message): void { $this->messages[] = $message; }
    }
}

namespace pocketmine\console {
    class ConsoleCommandSender extends \pocketmine\command\CommandSender {
        public function __construct(mixed $server, mixed $language) {}
    }
}

namespace pocketmine\player {
    class Player extends \pocketmine\command\CommandSender {}
}

namespace pocketmine\plugin {
    class PluginBase {
        public function getServer(): \pocketmine\Server { return \pocketmine\Server::getInstance(); }
        public function getDescription(): object {
            return new class {
                public function getVersion(): string { return 'test'; }
            };
        }
    }
}

namespace pocketmine\scheduler {
    class AsyncTask {
        private mixed $result = null;
        public function setResult(mixed $result): void { $this->result = $result; }
        public function getResult(): mixed { return $this->result; }
    }
}

namespace pocketmine\utils {
    class Internet {
        public static string $requestedUrl = '';
        /** @var list<string> */
        public static array $headers = [];
        public static string $simpleRequestedUrl = '';
        /** @var list<string> */
        public static array $simpleRequestedUrls = [];
        /** @var array<int, mixed> */
        public static array $simpleOptions = [];
        /** @var list<array{code: int, body: string}|\Throwable> */
        public static array $simpleResponses = [];
        /** @param list<string> $headers */
        public static function getURL(string $url, int $timeout, array $headers): object {
            self::$requestedUrl = $url;
            self::$headers = $headers;
            return new class {
                public function getBody(): string { return '{"online":true,"port":19132}'; }
            };
        }
        /** @param list<string> $headers @param array<int, mixed> $options */
        public static function simpleCurl(string $url, float $timeout, array $headers, array $options): object {
            self::$simpleRequestedUrl = $url;
            self::$simpleRequestedUrls[] = $url;
            self::$simpleOptions = $options;
            $response = array_shift(self::$simpleResponses) ?? [
                'code' => 200,
                'body' => '{"name":"Fixture","protocol":2225,"version":"1.26.60","players":3,"maxPlayers":20}'
            ];
            if ($response instanceof \Throwable) {
                throw $response;
            }
            return new class($response['code'], $response['body']) {
                public function __construct(private int $code, private string $body) {}
                public function getCode(): int { return $this->code; }
                public function getBody(): string { return $this->body; }
            };
        }
    }
    class TextFormat {
        public const YELLOW = '';
        public const WHITE = '';
        public const GREEN = '';
        public const RED = '';
    }
}

namespace pocketmine {
    class AsyncPool {
        /** @var list<object> */
        public array $tasks = [];
        public function submitTask(object $task): void { $this->tasks[] = $task; }
    }
    class Server {
        private static ?self $instance = null;
        public AsyncPool $pool;
        public function __construct() { self::$instance = $this; $this->pool = new AsyncPool(); }
        public static function getInstance(): self { return self::$instance ?? throw new \LogicException(); }
        public function getLanguage(): object { return new \stdClass(); }
        public function getAsyncPool(): AsyncPool { return $this->pool; }
    }
}

namespace {
    require __DIR__ . '/../src/QueryTask.php';
    require __DIR__ . '/../src/NetherNetQueryTask.php';
    require __DIR__ . '/../src/FallbackQueryTask.php';
    require __DIR__ . '/../src/API.php';
    require __DIR__ . '/../src/Main.php';

    function check(bool $condition, string $description): void {
        if (!$condition) { throw new \RuntimeException($description); }
    }

    $server = new \pocketmine\Server();
    $plugin = new \NhanAZ\BedrockQuery\Main();
    (new \ReflectionMethod($plugin, 'onLoad'))->invoke($plugin);

    $httpTask = new \NhanAZ\BedrockQuery\QueryTask('test.pmmp.io', 'BedrockQuery fixture');
    $httpTask->onRun();
    check(\pocketmine\utils\Internet::$requestedUrl === 'https://api.mcsrvstat.us/bedrock/3/test.pmmp.io', 'HTTP task used the Java endpoint instead of the Bedrock endpoint');
    check(\pocketmine\utils\Internet::$headers === ['User-Agent: BedrockQuery fixture'], 'HTTP task omitted its User-Agent header');
    check(($httpTask->getResult()['ok'] ?? null) === true, 'HTTP task did not accept the Bedrock status response');

    \pocketmine\utils\Internet::$simpleResponses = [[
        'code' => 200,
        'body' => '{"name":"NetherNet Fixture","protocol":2225,"version":"1.26.60","players":3,"maxPlayers":20}'
    ]];
    $netherNetTask = new \NhanAZ\BedrockQuery\NetherNetQueryTask('example.org', 19132, 'BedrockQuery fixture');
    $netherNetTask->onRun();
    check(\pocketmine\utils\Internet::$simpleRequestedUrl === 'https://example.org:19132/v1/join', 'NetherNet task did not use the HTTPS signaling endpoint');
    check((\pocketmine\utils\Internet::$simpleOptions[CURLOPT_SSL_VERIFYPEER] ?? null) === true, 'NetherNet HTTPS probe did not enable certificate verification');
    check(($netherNetTask->getResult()['source'] ?? null) === 'nethernet' && ($netherNetTask->getResult()['data']->name ?? null) === 'NetherNet Fixture', 'NetherNet task did not normalize its response');

    \pocketmine\utils\Internet::$simpleRequestedUrls = [];
    \pocketmine\utils\Internet::$simpleResponses = [
        ['code' => 404, 'body' => 'not found'],
        ['code' => 200, 'body' => '{"name":"HTTP Fixture","players":4,"maxPlayers":20}']
    ];
    $httpNetherNetTask = new \NhanAZ\BedrockQuery\NetherNetQueryTask('example.org', 19132, 'BedrockQuery fixture');
    $httpNetherNetTask->onRun();
    check(\pocketmine\utils\Internet::$simpleRequestedUrls === [
        'https://example.org:19132/v1/join',
        'http://example.org:19132/v1/join'
    ], 'NetherNet task did not try plain HTTP after an HTTPS status failure');
    check(($httpNetherNetTask->getResult()['ok'] ?? false) === true && ($httpNetherNetTask->getResult()['data']->name ?? null) === 'HTTP Fixture', 'NetherNet HTTP fallback did not accept a valid response');

    $received = [];
    $id = $plugin->registerCallback(static function (array $result) use (&$received): void { $received[] = $result; });
    \NhanAZ\BedrockQuery\Main::handleQueryResult([
        'address' => 'example.org:19132', 'requestId' => $id, 'ok' => false, 'error' => 'HTTP failed'
    ]);
    check($received === [], 'HTTP failure consumed callback before UDP fallback');
    check(count($server->pool->tasks) === 1 && $server->pool->tasks[0] instanceof \NhanAZ\BedrockQuery\NetherNetQueryTask, 'NetherNet query was not queued after HTTP failure');
    \NhanAZ\BedrockQuery\Main::handleNetherNetResult([
        'requestId' => $id, 'ok' => false, 'host' => 'example.org', 'port' => 19132, 'error' => 'NetherNet unavailable'
    ]);
    check(count($server->pool->tasks) === 2 && $server->pool->tasks[1] instanceof \NhanAZ\BedrockQuery\FallbackQueryTask, 'UDP fallback not queued after NetherNet failure');
    \NhanAZ\BedrockQuery\Main::handleFallbackResult([
        'requestId' => $id, 'ok' => true, 'host' => 'example.org', 'port' => 19132,
        'data' => ['HostName' => 'Fixture'], 'query' => null
    ]);
    check(count($received) === 1 && ($received[0]['data']['HostName'] ?? null) === 'Fixture' && $received[0]['source'] === 'udp', 'UDP result not delivered with source');
    check($plugin->deliverCallback($id, []) === false, 'Callback delivered twice');

    $received = [];
    $id = $plugin->registerCallback(static function (array $result) use (&$received): void { $received[] = $result; });
    \NhanAZ\BedrockQuery\Main::handleFallbackResult([
        'requestId' => $id, 'ok' => false, 'error' => 'UDP timed out'
    ]);
    check(count($received) === 1 && $received[0]['ok'] === false && $received[0]['source'] === 'udp', 'UDP failure source not delivered');

    $received = [];
    $id = $plugin->registerCallback(static function (array $result) use (&$received): void { $received[] = $result; });
    \NhanAZ\BedrockQuery\Main::handleQueryResult([
        'address' => 'example.org', 'requestId' => $id, 'ok' => false, 'error' => 'HTTP failed'
    ]);
    check($received === [], 'No-port HTTP failure consumed callback before NetherNet fallback');
    check(count($server->pool->tasks) === 3 && $server->pool->tasks[2] instanceof \NhanAZ\BedrockQuery\NetherNetQueryTask, 'No-port HTTP failure did not queue NetherNet query');
    \NhanAZ\BedrockQuery\Main::handleNetherNetResult([
        'requestId' => $id, 'ok' => false, 'host' => 'example.org', 'port' => 19132, 'error' => 'NetherNet unavailable'
    ]);
    check(count($server->pool->tasks) === 4 && $server->pool->tasks[3] instanceof \NhanAZ\BedrockQuery\FallbackQueryTask, 'No-port NetherNet failure did not queue UDP fallback');
    check((new \ReflectionProperty($server->pool->tasks[3], 'host'))->getValue($server->pool->tasks[3]) === 'example.org', 'No-port fallback changed the host');
    check((new \ReflectionProperty($server->pool->tasks[3], 'port'))->getValue($server->pool->tasks[3]) === 19132, 'No-port fallback did not use the Bedrock default port');
    \NhanAZ\BedrockQuery\Main::handleFallbackResult([
        'requestId' => $id, 'ok' => true, 'host' => 'example.org', 'port' => 19132,
        'data' => ['HostName' => 'Default Port Fixture'], 'query' => null
    ]);
    check(count($received) === 1 && ($received[0]['data']['HostName'] ?? null) === 'Default Port Fixture', 'No-port UDP result was not delivered');

    $received = [];
    $id = $plugin->registerCallback(static function (array $result) use (&$received): void { $received[] = $result; });
    \NhanAZ\BedrockQuery\Main::handleQueryResult([
        'address' => 'example.org', 'requestId' => $id, 'ok' => true,
        'data' => (object) ['online' => false]
    ]);
    check($received === [] && count($server->pool->tasks) === 5 && $server->pool->tasks[4] instanceof \NhanAZ\BedrockQuery\NetherNetQueryTask, 'Offline HTTP status did not try NetherNet on the Bedrock default port');
    \NhanAZ\BedrockQuery\Main::handleNetherNetResult([
        'requestId' => $id, 'ok' => false, 'host' => 'example.org', 'port' => 19132, 'error' => 'NetherNet unavailable'
    ]);
    check(count($server->pool->tasks) === 6 && $server->pool->tasks[5] instanceof \NhanAZ\BedrockQuery\FallbackQueryTask, 'Offline HTTP status did not fall back to UDP');
    \NhanAZ\BedrockQuery\Main::handleFallbackResult([
        'requestId' => $id, 'ok' => false, 'error' => 'UDP timed out'
    ]);
    check(count($received) === 1 && $received[0]['ok'] === false && $received[0]['source'] === 'udp', 'Offline HTTP status did not deliver the final UDP failure');

    $received = [];
    $id = $plugin->registerCallback(static function (array $result) use (&$received): void { $received[] = $result; });
    \NhanAZ\BedrockQuery\Main::handleQueryResult([
        'address' => 'example.org:19132', 'requestId' => $id, 'ok' => true,
        'data' => (object) ['online' => true, 'ip' => '127.0.0.1', 'port' => 19132]
    ]);
    check(count($received) === 1 && $received[0]['source'] === 'api', 'HTTP success did not deliver once');
    check(count($server->pool->tasks) === 6, 'HTTP success queued redundant fallback');
    \NhanAZ\BedrockQuery\Main::handleQueryResult([
        'address' => 'example.org', 'requestId' => null, 'ok' => true,
        'data' => (object) ['online' => true, 'ip' => '127.0.0.1', 'port' => 19132]
    ]);
    check(count($server->pool->tasks) === 6, 'Successful no-port console lookup queued redundant UDP query');

    $received = [];
    $id = $plugin->registerCallback(static function (array $result) use (&$received): void { $received[] = $result; });
    \NhanAZ\BedrockQuery\Main::handleNetherNetResult([
        'requestId' => $id, 'ok' => true, 'host' => 'example.org', 'port' => 19132,
        'data' => (object) ['name' => 'NetherNet Fixture', 'players' => 3]
    ]);
    check(count($received) === 1 && $received[0]['source'] === 'nethernet' && ($received[0]['data']->name ?? null) === 'NetherNet Fixture', 'NetherNet success was not delivered once');

    check(\NhanAZ\BedrockQuery\Main::splitAddress('example.org:19132') === ['example.org', 19132], 'Valid host and port were not parsed');
    foreach (['example.org:bad', 'example.org:0', 'example.org:65536', '2001:db8::1'] as $invalid) {
        check(\NhanAZ\BedrockQuery\Main::splitAddress($invalid) === [null, null], 'Invalid host and port were accepted');
    }

    $sender = new \pocketmine\command\CommandSender();
    (new \ReflectionMethod($plugin, 'handleQuery'))->invoke($plugin, $sender, ['example.org:19132', '19133']);
    check(count($server->pool->tasks) === 7, 'Explicit port did not queue a task');
    $task = $server->pool->tasks[6];
    check($task instanceof \NhanAZ\BedrockQuery\NetherNetQueryTask, 'Explicit port queued the wrong task');
    check((new \ReflectionProperty($task, 'host'))->getValue($task) === 'example.org', 'Explicit UDP port kept the embedded port in the hostname');
    check((new \ReflectionProperty($task, 'port'))->getValue($task) === 19133, 'Explicit UDP port did not override the embedded port');

    $plugin->getApi()->query('example.org', 19134, static function (array $result): void {});
    $apiTask = $server->pool->tasks[array_key_last($server->pool->tasks)];
    check($apiTask instanceof \NhanAZ\BedrockQuery\NetherNetQueryTask, 'Public API did not start with a NetherNet query');
    check((new \ReflectionProperty($apiTask, 'host'))->getValue($apiTask) === 'example.org', 'Public API changed the NetherNet host');
    check((new \ReflectionProperty($apiTask, 'port'))->getValue($apiTask) === 19134, 'Public API did not preserve the explicit NetherNet port');

    echo "PASS: callback delivery and UDP fallback order\n";
}
