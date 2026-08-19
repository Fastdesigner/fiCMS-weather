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

## MCP

The plugin contributes the `weather` type to the existing fiCMS `get` tool. It does not add another MCP tool.
Its handler opts into chat discovery, so only installations with the plugin advertise weather as contextual visitor-chat data.

```json
{"type":"weather","id":"default","data":{"days":3,"units":"metric"}}
```

Use `id: "list"` to load the configured locations. Visitor calls expose active locations only and never expose provider keys or coordinates.

The TaskManager API key is never exposed in settings, frontend output, JSON responses or diagnostics. Settings only show whether the weather service is active and usable.

## Widget

```html
[widgets=weather]main-location|compact|3[/widgets]
```

Block options are preferred where available. The legacy inline syntax is supported for direct layout use.

## Roadmap

See [docs/roadmap.md](docs/roadmap.md).
