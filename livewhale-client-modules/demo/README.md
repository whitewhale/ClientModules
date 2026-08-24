# Demo Module

Seeds and refreshes a rolling window of realistic sample calendar events for demoing and testing the Events module. Everything it creates lives in a dedicated **Sample Content** group and is tagged internally so the module only ever touches what it created itself — hand-made events in that group are never touched.

## What happens after install

When the module loads for the first time on an install:

- A **Sample Content** group is created (if one doesn't already exist).
- A weekly cron job (`demo_weekly`) is registered in the scheduler, set to run every 7 days.

That's it — no events are created at install time. The first batch of sample events appears the first time the weekly job runs, or as soon as someone triggers a manual refresh (see below).

## How events get refreshed

Every time the module runs (weekly, or on a manual refresh), it:

1. Deletes every event it previously created (identified by an invisible `demo_generated` custom field — never a hand-made event).
2. Re-reads `data/sample_events.csv`.
3. Tiles that CSV pattern repeatedly across a rolling date window, so the sample calendar always looks current relative to today, rather than sitting on whatever fixed dates are typed into the CSV.
4. Re-creates all of the resulting event instances, with their images, categories, tags, and any bundled RSVPs.

The window size is controlled in `global.application.demo.php`, under `REGISTERED_APPS['demo']['custom']`:

```php
'weeks_back'  => 4, // keep sample events populated starting this many weeks in the past
'weeks_ahead' => 8, // populate sample events out to this many weeks in the future
```

Increase either number if you want a longer runway of sample events visible at once.

### Triggering a refresh manually

Visit `/live/demo/regenerate` while logged in as a LiveWhale user. It runs synchronously and responds with a plain-text summary, e.g.:

```
172 sample event(s) created.
```

or, if anything failed:

```
57 sample event(s) created.
2 failed:
Failed to create sample event "..." on 03/14/2026 — The item could not be saved.
```

This is the fastest way to see the effect of a CSV edit without waiting for the weekly cron to fire.

## Editing the sample data

The sample events live in `data/sample_events.csv` — a plain CSV you can open in Excel, Numbers, or Google Sheets. Each row is one event definition; the module tiles that pattern across the rolling window, so the exact calendar dates in the CSV don't matter much, but the **relative spacing between rows does** (whatever span the earliest-to-latest dates cover becomes one repeating cycle, rounded up to a full number of weeks).

A couple of things worth knowing before editing:

- Keep the earliest and latest dates in the CSV aligned to full weeks (Monday–Sunday) if you want the weekly rhythm to look natural — the module doesn't enforce this, but odd cycle lengths can make the tiled calendar feel lopsided.
- Rows don't need to stay in date order, but it's much easier to maintain the file if they do.
- The current CSV spans a 12-week cycle (loosely following a spring-semester arc: welcome events, regular-season athletics and lectures, then reading period, finals, and commencement toward the end). Any identity-based or support-group-style events in the sample data are deliberately generic (e.g. "Peer Support Circle," "Interfaith Reflection Gathering") rather than naming specific groups.

### Column reference

| Column | Notes |
| --- | --- |
| `title` | Event title. |
| `start_date`, `start_time` | `MM/DD/YYYY` and (optional) `H:MM AM/PM`. Leave `start_time` blank for an all-day event. |
| `end_date`, `end_time` | Optional. Leave both blank for a single-instant event with no end. |
| `is_all_day` | `1` for all-day; leave blank otherwise. |
| `summary`, `description` | `description` supports HTML. |
| `location` | Free text. |
| `tags` | Pipe-separated, e.g. `Lecture\|Panel`. Any tag not already in the system gets created automatically. |
| `categories` | Pipe-separated, e.g. `Open to the Public\|Academic Events`. Same auto-create behavior as tags. |
| `image`, `image_alt` | `image` is a filename from `data/images/` (see below); leave blank for no image. |
| `is_starred` | `1` to feature the event. |
| `status` | Event visibility: `1` = published, `2` = hidden. Leave blank to default to published. |
| `is_canceled` | `1` marks the event canceled; leave blank otherwise. |
| `is_online`, `online_type`, `online_url`, `online_button_label`, `online_instructions` | Online/hybrid event fields. `online_type`: `1` = online only, `2` = hybrid. |
| `cost` | Free text (e.g. `Free`, `$10`). |
| `related_content` | Optional related URL. |
| `has_registration` | `1` to turn on RSVPs for this event. |
| `registrations_received` | Optional JSON array of sample RSVPs bundled onto the event — see below. |

Currently-used categories and tags (for reference — this list isn't enforced by the module, it's just what the sample data happens to use today):

| Category | Tags in use |
| --- | --- |
| Open to the Public | Alumni, Commencement, Community, Conference, Exhibit, Film, Lecture, Men's Sports, Music, Orientation, Panel, Reception, Service, Student Event, Undergraduate, Volunteering, Wellness, Women's Sports |
| Academic Calendar | Commencement, Community, Graduate, Orientation, Undergraduate, Wellness |
| Academic Events | Colloquium, Conference, Exhibit, Info Session, Lecture, Panel, Reception, Seminar, Study Abroad, Symposium, Thesis Defense |
| Athletics | Alumni, Basketball, Cross Country, Equestrian, Football, Ice Hockey, Lacrosse, Men's Sports, Rugby, Sailing, Soccer, Swimming & Diving, Tennis, Track & Field, Volleyball, Water Polo, Women's Sports |
| Community Outreach | Alumni, Blood Drive, Community, Panel, Service, Student Event, Volunteering, Wellness |
| Dates & Deadlines | (none) |
| Performances | Film, Music |
| Student Organizations | Community, Orientation, Student Event, Undergraduate, Wellness |

### Images

Source images live in `data/images/` as plain `.jpg` files. Reference one by filename in the `image` column (e.g. `braden-collum-9HI8UJMSdZA-unsplash.jpg`).

On first use, an image is uploaded into the LiveWhale image library under the Sample Content group. From then on, the module tracks which library image came from which source filename (an invisible `demo_source_image` custom field on the image), so every later refresh — and every repeated instance of that row across the tiled calendar — reuses the same library image instead of uploading a fresh duplicate copy each time. If you add a new image file, just reference its filename in a CSV row; it'll get uploaded automatically on the next refresh.

Leave `image` blank for events that shouldn't have a photo (a few Academic Calendar / Dates & Deadlines rows do this deliberately, since not every deadline needs stock art).

### Maps

A handful of recurring `location` values (Fieldhouse Arena, Main Quad, Student Union Ballroom, and a dozen or so others — see the `$location_presets` array near the top of `populateEvents()` in `global.application.demo.php`) are wired up to a built-in map. The first time one of these locations is used, the module creates a Saved Location (a map preset, scoped to the Sample Content group) with a placeholder set of coordinates and attaches it to the event; every later instance of that same location string — this run or a future regeneration — reuses the same Saved Location instead of creating a new pin each time.

The coordinates are illustrative placeholders, not real addresses for these venue names — that's intentional, since the point is to demo the map feature rather than plot an accurate campus. Swap in real coordinates in `$location_presets` if that ever matters, or add more entries to cover additional locations. Any `location` value not in that list is left as plain text with no map, same as before.

### Bundled sample RSVPs

For an event with `has_registration=1`, `registrations_received` can hold a JSON array of sample registrants, e.g.:

```json
[
  {"firstname": "Jane", "lastname": "Doe", "email": "jane@example.com", "attending": 1, "comments_by_registrant": "Looking forward to it!"},
  {"firstname": "Sam", "lastname": "Rivera", "email": "sam@example.com", "attending": 1, "is_waitlisted": 1}
]
```

A couple of conventions to follow when adding more:

- Leave `status` out entirely for most registrants — it's meant to stay unset (no attendance has been recorded yet). Only set `"status": 1` ("Attended") sparingly, and only for events dated in the past, to show what a post-event attendance record looks like.
- `comments_by_registrant` is a nice touch on a few registrants to make the sample data feel less uniform — not required on every one.

## Troubleshooting

- Every run logs a `Demo:` prefixed line via `logDebug()` — check the LiveWhale error/debug log first if a refresh isn't behaving as expected.
- A manual refresh reports created/failed counts directly in the response — if you see failures, the message includes the specific event title and LiveWhale's own save error.
- If an image referenced in the CSV doesn't show up, double check the filename in `data/images/` — matching is exact and case-sensitive.
- Don't hand-edit the `demo_generated` or `demo_source_image` custom fields on any item — the module relies on them to know what it owns and which images it's already uploaded.
