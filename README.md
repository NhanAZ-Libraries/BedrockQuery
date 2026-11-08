# BedrockQuery

Query Bedrock server status from PocketMine-MP with MCSRVSTAT, NetherNet and UDP

[![CI](https://github.com/NhanAZ-Libraries/BedrockQuery/actions/workflows/build.yml/badge.svg)](https://github.com/NhanAZ-Libraries/BedrockQuery/actions/workflows/build.yml)
[![Latest release](https://img.shields.io/github/v/release/NhanAZ-Libraries/BedrockQuery)](https://github.com/NhanAZ-Libraries/BedrockQuery/releases/latest)
[![License](https://img.shields.io/github/license/NhanAZ-Libraries/BedrockQuery)](https://github.com/NhanAZ-Libraries/BedrockQuery/blob/master/LICENSE)
[![Discord](https://img.shields.io/discord/986553214889517088?label=Discord&logo=discord&logoColor=white&color=5865F2)](https://discord.gg/j2X83ujT6c)


## Requirements and compatibility

- `plugin.yml` declares PocketMine-MP API `5.0.0` and the code requires PHP 8.1 or newer.
- Static analysis targets Axolotl-PM 5 API. Runtime compatibility across the 5.x line has not yet been established. See the [build workflow](.github/workflows/build.yml) for the exact revision tested in CI.
- The NetherNet status probe uses the server's `GET /v1/join` signaling endpoint. It reads server metadata only; it does not perform the WebRTC/SDP connection handshake.
- HTTPS is attempted first for NetherNet, with plain HTTP as a compatibility fallback. HTTPS is recommended for public deployments.
- Plugins calling the API must depend on the `BedrockQuery` plugin so its instance is loaded first.

## Install and use

Download a verified PHAR from [GitHub Releases](https://github.com/NhanAZ-Libraries/BedrockQuery/releases/latest) and place it in the server's `plugins` directory. Check the release notes and SHA-256 checksum for that build. To build from source, use [DevTools](https://github.com/AxolotlBucketLabs/DevTools) and validate the generated PHAR. The console command requires the `bedrockquery.cmd` permission, which defaults to operators.

```text
/query example.org
/query example.org:19132
/query example.org 19132
```

The first two forms use the MCSRVSTAT HTTP status API. If that request fails or reports the server offline, BedrockQuery tries the NetherNet `GET /v1/join` endpoint and then falls back to legacy RakNet UDP. The form with a separate port starts with NetherNet and falls back to UDP. Query results depend on the remote server's endpoint availability, query settings and network access.

Another plugin can depend on `BedrockQuery` and call its API after both plugins load:

```php
use NhanAZ\BedrockQuery\Main;

Main::getInstance()->getApi()->query("example.org", null, function (array $result): void {
    if (($result["ok"] ?? false) !== true) {
        // Inspect $result["error"].
        return;
    }

    // $result["source"] identifies "api", "nethernet" or "udp".
    // $result["data"] is an HTTP/NetherNet object or a UDP array.
});
```

See the [API documentation](docs/api.md) for transport selection and callback payloads. The [example consumer guide](examples/QueryConsumer/README.md) explains the complete sample plugin. Its `/qtest [host] [port]` command accepts a separate host and Bedrock port for local testing. Without arguments, it uses the public test server. A callback is delivered once after success or the final failure. Keep the callback lightweight because it runs when the asynchronous task completes on the server thread.

The public API currently depends on a loaded plugin instance, so the source should not be presented as a standalone virion. No current standalone virion release is offered.

## License and upstream

The project is distributed under the root [GPL-3.0-only license](LICENSE). The bundled [libpmquery](https://github.com/jasonw4331/libpmquery) code retains its LGPL-3.0-or-later notice and separate [license text](libpmquery-LICENSE.txt), which is included in the PHAR. Composer and virion metadata describe the GPL-3.0-only project as a whole.

The [root GPL text was added in 2021](https://github.com/NhanAZ-Libraries/BedrockQuery/commit/66d5565), before the [2023 contributor changes](https://github.com/NhanAZ-Libraries/BedrockQuery/commit/7bba7be). The [bundled libpmquery code and notice](https://github.com/NhanAZ-Libraries/BedrockQuery/commit/bf78f5f) were added in 2026. The later [virion metadata change](https://github.com/NhanAZ-Libraries/BedrockQuery/commit/e117469) did not change the root license. This history is the basis for aligning the metadata, while preserving the contributor and bundled library notices.

Report issues through [GitHub Issues](https://github.com/NhanAZ-Libraries/BedrockQuery/issues) or [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
