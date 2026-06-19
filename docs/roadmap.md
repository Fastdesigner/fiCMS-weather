# Roadmap

## V2: Weather Warnings

- Admin tab for warning subscriptions per location
- Warning channels through fiCMS notification/mail infrastructure
- Severity filters, for example advisory, watch and warning
- Quiet hours and repeat protection
- Cron-based alert polling with last-sent state
- Optional frontend warning output for selected widgets

The warning feature must not expose provider keys. Only alert status, location, severity and delivery state may be shown in admin responses.
