# Skilitsa DogMatcher

A lightweight WordPress plugin that renders a dog-matching quiz powered by a Google Sheet.

## Installation

1. Copy the `skilitsa-dogmatcher` folder into `wp-content/plugins/`.
2. Activate **Skilitsa DogMatcher** from **Plugins → Installed Plugins**.
3. Go to **Settings → Skilitsa DogMatcher** and provide the Google Sheet URL (optional).
4. Click **Refresh from Google Sheet** to sync the latest data.

## Deploy / Update plugin

- Always upload the full plugin folder (zip/extract). Do not copy/paste single files.
- Missing files will trigger admin notices instead of fatal errors so the site remains available.

## Shortcode Usage

Place the shortcode on any page:

```
[skilitsa_dogmatcher]
```

## Basic QA Checklist

- [ ] Plugin activates without PHP warnings.
- [ ] Settings page saves the sheet URL and syncs data.
- [ ] Sync shows counts and any warnings/errors.
- [ ] Shortcode renders quiz and returns a result after answering questions.
- [ ] Uninstall removes stored options.
