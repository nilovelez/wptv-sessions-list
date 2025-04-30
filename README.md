# WPTV Sessions List

A WordPress plugin that allows you to fetch and display sessions from WordCamp and WordPress.org events in a format ready to be copied to Google Sheets.

## Description

This plugin provides a shortcode `[wptv_sessions]` that displays a form where you can enter a WordCamp or WordPress.org event URL. After submitting the URL, it fetches the sessions and speakers data from the event's REST API and displays it in a textarea formatted for easy copy-paste into Google Sheets.

## Features

- Fetches sessions and speakers data from WordCamp and WordPress.org events
- Supports both wordcamp.org and events.wordpress.org URLs
- Automatically formats the output for Google Sheets
- One-click text selection for easy copying
- Responsive form layout

## Installation

1. Upload the `wptv-sessions-list.php` file to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the shortcode `[wptv_sessions]` in any post or page

## Usage

1. Add the shortcode `[wptv_sessions]` to your post or page
2. Enter a valid WordCamp or WordPress.org event URL in the form
3. Click "Get Sessions"
4. The sessions will be displayed in a textarea
5. Click the textarea to select all content
6. Copy and paste into Google Sheets

## Supported URL Formats

- WordCamp URLs: `https://[subdomain].wordcamp.org/[year]/`
- WordPress.org Event URLs: `https://events.wordpress.org/[subdomain]/[year]/[slug]/`

## Requirements

- WordPress 5.2 or higher
- PHP 7.2 or higher

## License

This plugin is licensed under the GPL v2 or later.

## Author

Nilo Velez 