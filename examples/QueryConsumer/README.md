# QueryConsumer example

This example plugin shows how another PocketMine-MP plugin can depend on `BedrockQuery` and call its public API.

## What it demonstrates

- A hard `BedrockQuery` dependency in `plugin.yml`.
- A small `/qtest [host] [port]` command.
- Basic host and port validation.
- An asynchronous API callback.
- Handling HTTP/NetherNet-style objects and UDP-style arrays.

## Run the example

Build `BedrockQuery` first, then build this example with the same DevTools workflow used by CI. Install both PHAR files in the server's `plugins` directory.

Run the command from the console or in game:

```text
/qtest
/qtest example.org 19132
```

Without arguments, the example queries `test.pmmp.io` through NetherNet first and then legacy UDP on port `19132`. Passing a host and port uses the same direct NetherNet-then-UDP path because the example supplies an explicit port to the API.

For the MCSRVSTAT status API with NetherNet and UDP fallback, pass `null` as the port when calling `API::query()`. See the [API documentation](../../docs/api.md) for the transport rules and callback payloads.

## Result handling

The example checks `ok` first. A successful result includes `source` and `data`. The MCSRVSTAT response uses an object with fields such as `motd` and `players`; the NetherNet response uses fields such as `name`, `version`, `players`, `maxPlayers` and `level`; the UDP response uses an array with fields such as `HostName`, `Players` and `MaxPlayers`.

The sample only displays a few common fields. A real plugin should check each optional field before using it and keep the callback lightweight.
