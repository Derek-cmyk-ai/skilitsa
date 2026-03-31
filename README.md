# Skilitsa DogMatcher

A lightweight, highly customizable WordPress plugin that renders a dog-matching quiz powered by a Google Sheet.

## Installation

1. Copy the `skilitsa-dogmatcher` folder into `wp-content/plugins/`.
2. Activate **Skilitsa DogMatcher** from **Plugins → Installed Plugins**.
3. Go to **Settings → Skilitsa DogMatcher** and provide the Google Sheet URL (optional if using a hardcoded default master sheet).
4. Click **Refresh from Google Sheet** to sync the latest data.

## Backend (Admin)

The backend is entirely managed via the **Settings → Skilitsa DogMatcher** page and remains strictly in English.

*   **Google Sheet Sync:** The plugin uses a Google Sheet as its database. It reads tabs like `SETTINGS`, `QUESTIONS`, `TRAITS`, `BREEDS`, and `UI_TEXTS`. Ensure the sheet is shared as "Anyone with the link: Viewer".
*   **Caching:** Data fetched from the Google Sheet is cached for **1 week** (604,800 seconds) to ensure your website remains fast and isn't constantly hitting the Google Sheets API.
*   **Manual Refresh:** You can bypass the cache and force an immediate update of your quiz data by clicking the **Refresh from Google Sheet** button on the settings page.
*   **Troubleshooting:** The settings page displays helpful debugging information, warnings (like missing images or URLs for breeds), and direct links to test the raw CSV data.

## Frontend (User Experience)

The frontend delivers an interactive, responsive quiz using plain JavaScript and CSS.

*   **Shortcode:** Display the quiz anywhere using the shortcode: `[skilitsa_dogmatcher]`.
*   **UI/UX:** The interface is styled with modern rounded corners and pill-shaped interactive elements (buttons, progress bars) for a soft, friendly aesthetic.
*   **Interactive Questions:** Users answer questions on a 1-5 scale. Questions can optionally feature static images, pulse animations, or side-by-side interactive sliders for comparison.
*   **Dealbreakers:** Users can toggle questions as "Dealbreakers" or "Ignore", dynamically adjusting the matching algorithm.
*   **Results Calculation:** Once completed, the plugin calculates the closest matches based on the traits mapped in the Google Sheet. It displays "Best Matches" cards (with images and match percentages) and a broader list of alternative matches.
*   **Localization (Translations):** The frontend supports localization. While the default strings and sheet content can be adjusted in the `UI_TEXTS` tab, the plugin provides a `languages/skilitsa_dogmatcher.pot` template. Translators can use Poedit (or plugins like Loco Translate) to generate `.po`/`.mo` files to translate hardcoded UI elements like the "Next" and "Back" buttons.

## Deploy / Update plugin

- Always upload the full plugin folder (zip/extract). Do not copy/paste single files.
- Missing files will trigger admin notices instead of fatal errors so the site remains available.

## Basic QA Checklist

- [ ] Plugin activates without PHP warnings.
- [ ] Settings page saves the sheet URL and syncs data.
- [ ] Sync shows counts and any warnings/errors.
- [ ] Shortcode renders quiz and returns a result after answering questions.
- [ ] Uninstall removes stored options.
