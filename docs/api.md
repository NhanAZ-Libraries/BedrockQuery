# BedrockQuery API

`BedrockQuery` exposes a small API for other PocketMine-MP plugins. It runs the network query asynchronously and calls your callback on the server thread when the result is ready.

## Declare the dependency

Declare `BedrockQuery` as a hard dependency in your plugin manifest so the API is available before your plugin starts.

```yaml
depend:
  - BedrockQuery
```

The API is available after both plugins have loaded.

## Run a query

Get the loaded plugin instance and call `query()` from your plugin.

```php
use NhanAZ\BedrockQuery\Main as BedrockQueryPlugin;

$bedrockQuery = $this->getServer()->getPluginManager()->getPlugin("BedrockQuery");
if (!$bedrockQuery instanceof BedrockQueryPlugin) {
    return;
}

$bedrockQuery->getApi()->query(
    "example.org",
    null,
    function (array $result): void {
        if (($result["ok"] ?? false) !== true) {
            $error = $result["error"] ?? "Unknown query error";
            return;
        }

        $source = $result["source"] ?? "unknown";
        $data = $result["data"] ?? null;
        // Handle the normalized result here.
    },
);
```

The method signature is:

```php
query(string $host, ?int $port, callable $onComplete): void
```

The callback receives one normalized array. It is called once after a successful query or after the final failure.

## Choose the transport

The port argument controls the initial transport.

| Arguments | Initial transport | Behavior |
| --- | --- | --- |
| `"example.org"`, `null` | MCSRVSTAT HTTP | Requests the Bedrock status API first. When it fails or reports the server offline, it tries NetherNet and then legacy RakNet UDP on port `19132`. |
| `"example.org:19132"`, `null` | MCSRVSTAT HTTP | The host string is passed to the status API. If that path fails or reports offline, the parsed host and port are used for NetherNet, then UDP. |
| `"example.org"`, `19132` | NetherNet | Requests `https://example.org:19132/v1/join`, tries plain HTTP if HTTPS cannot be used, and falls back to UDP if both signaling requests fail. |

An explicit port is useful when you want to query the server directly without MCSRVSTAT. The NetherNet request is a metadata/status request; it does not complete a client connection handshake.

## Callback results

Every callback result contains an `ok` field. Check it before reading the successful payload.

### Successful HTTP result

When the HTTP status API reports the server online, the callback has this shape:

```php
[
    "ok" => true,
    "source" => "api",
    "address" => "example.org",
    "data" => (object) [
        "online" => true,
        // Other fields returned by the status API.
    ],
]
```

The `data` value is the decoded status API object. Its optional fields can change with the upstream response, so check each field before using it.

### Successful UDP result

When the query uses UDP or the HTTP request falls back to UDP, the callback has this shape:

```php
[
    "ok" => true,
    "source" => "udp",
    "host" => "example.org",
    "port" => 19132,
    "data" => [
        "HostName" => "Example server",
        "Players" => 3,
        "MaxPlayers" => 20,
        // Other fields returned by libpmquery.
    ],
    "query" => [
        "raw" => [],
        "plugins" => [],
    ],
    "queryError" => null,
]
```

The basic UDP response remains useful when the optional full query request fails. In that case, `queryError` contains the full query error while `ok` can still be `true`.

### Successful NetherNet result

When the NetherNet signaling endpoint accepts `GET /v1/join`, the callback has this shape:

```php
[
    "ok" => true,
    "source" => "nethernet",
    "host" => "example.org",
    "port" => 19132,
    "data" => (object) [
        "name" => "Example server",
        "protocol" => 2225,
        "version" => "1.26.60",
        "level" => "world",
        "players" => 3,
        "maxPlayers" => 20,
        // Optional metadata fields may be returned by the server.
    ],
]
```

The `data` object is the server's NetherNet status metadata. BedrockQuery currently performs only the `GET` capability/status request; it does not submit an SDP offer to join the server.

### Failed result

When the final query fails, the callback has this shape:

```php
[
    "ok" => false,
    "source" => "api", "nethernet" or "udp",
    "error" => "A human-readable error message",
]
```

Do not assume that `data`, `query`, or `queryError` is available when `ok` is `false`.

## Thread and lifecycle notes

- The network work runs in an asynchronous task.
- The callback runs when the task completes on the server thread.
- Keep the callback short. Move expensive work to another task when necessary.
- Do not call the API before `BedrockQuery` has loaded.
- Keep the plugin dependency in `plugin.yml` instead of relying only on a runtime instance check.

## Example

The [QueryConsumer example](../examples/QueryConsumer/README.md) shows dependency declaration, argument validation, API invocation and result formatting in a small plugin.
