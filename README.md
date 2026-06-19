# fiCMS Weather

fiCMS Weather manages multiple weather locations in the admin settings and renders OpenWeather forecasts through a frontend widget.

## Version 1

- OpenWeather One Call 3.0 provider
- TaskManager-managed API key support
- Multiple admin-managed locations
- Per-location cache files
- Forecast widget with configurable location, layout, days and visible metrics
- Cron refresh for active locations

The TaskManager API key is never exposed in settings, frontend output, JSON responses or diagnostics. Settings only show whether the weather service is active and usable.

## Widget

```html
[widgets=weather]main-location|compact|3[/widgets]
```

Block options are preferred where available. The legacy inline syntax is supported for direct layout use.

## Roadmap

See [docs/roadmap.md](docs/roadmap.md).
