# fiCMS Weather

fiCMS Weather manages multiple weather locations in the admin settings and renders OpenWeather forecasts through a frontend widget.

## Version 1

- OpenWeather One Call 3.0 provider
- TaskManager-managed API key support
- Multiple admin-managed locations
- Location ids generated from the location name
- Per-location cache files
- Plugin weather icon set with per-icon custom overrides
- Visitor-dependent metric/imperial output
- Forecast widget with configurable location, layout, days and visible metrics
- Cron refresh for active locations
- MCP `get` handler for current conditions and forecasts in visitor chat
- Admin MCP handlers for creating, updating and deleting weather locations

## MCP

The plugin contributes the `weather` type to the existing fiCMS `get`, `create`, `update` and `delete` tools. It does not add another MCP tool.
Its handler opts into chat discovery, so only installations with the plugin advertise weather as contextual visitor-chat data.

```json
{"type":"weather","id":"default","data":{"days":3,"units":"metric"}}
```

Use `id: "list"` to load the configured locations. Visitor calls expose active locations only and never expose provider keys or coordinates.

The TaskManager API key is never exposed in settings, frontend output, JSON responses or diagnostics. Settings only show whether the weather service is active and usable.

In a bound chat task, a successful forecast also returns `card.reference`. Use `[Weather](weather:<reference>)` to render the exact returned forecast in chat and email. Ordinary MCP reads and location lists stay JSON responses. The plugin declares this format through `rich-response/weather.php`; availability reads the active configuration, locations and service status without fetching weather.

`weather\Card` uses the shared Core `ficms\RichResponse\Snapshots` owner to preserve the canonical forecast, including its original timestamp, timezone, units, stale status, warnings and alerts. Parsing, history and email never refresh the provider. Simulation cards use the same data and rendering path with the Core simulation lifetime. Icons come from validated local weather assets; email embeds those same raster assets through the regular attachment contract.

## Widget

```html
[widgets=weather]main-location|compact|3[/widgets]
```

Block options are preferred where available. The legacy inline syntax is supported for direct layout use.

## Roadmap

See [docs/roadmap.md](docs/roadmap.md).

Missing optional measurements remain `null` from provider normalization through unit conversion and MCP snapshots. Cards omit unknown optional facts and show a localized unavailable value for unknown temperature, rain probability or temperature bounds, without drawing a range bar. Actual numeric zero remains a measured zero. OpenWeather documents absent rain/snow phenomenon amounts as no occurrence, so those amounts remain zero when absent ([provider contract](https://openweathermap.org/faq)). Existing cached forecasts or immutable archives that already normalized a missing value to zero cannot recover the original absence; they are not rewritten.
