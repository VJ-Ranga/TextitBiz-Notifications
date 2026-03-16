# TextitBiz SMS

WordPress plugin for sending SMS alerts through [Textit.biz](https://textit.biz/) when selected forms are submitted.

Current version: `v0.2.7`

## What it does

- Detects active supported plugins
- Lists detected forms from those plugins
- Lets you choose exactly which forms should trigger SMS
- Builds short, SMS-friendly messages from selected form fields
- Sends alerts to your admin mobile number through Textit.biz

## Supported integrations

- MetForm
- Elementor Pro Forms
- Contact Form 7
- WooCommerce checkout

## Textit.biz integration

This plugin uses the Textit.biz Basic HTTP API documented here:

- https://textit.biz/integration_Basic_HTTP_API.php

Request format used by the plugin:

- Endpoint: `https://textit.biz/sendmsg/`
- Parameters: `id`, `pw`, `to`, `text`

## Main settings

- Admin Mobile Number
- Message
- User ID
- Password
- Available Shortcodes based on the selected form

## Development structure

- `textitbiz-notifications.php` plugin bootstrap
- `includes/` core classes
- `includes/integrations/` form-specific listeners
- `docs/` project notes

## Installation for development

1. Copy this project folder into `wp-content/plugins/textitbiz-sms`
2. Activate `TextitBiz SMS`
3. Open `Settings -> TextitBiz SMS`
4. Select forms, enter Textit.biz credentials, and save

## License

GPL-2.0-or-later

## Changelog

- `v0.2.7`: preserve newline formatting in SMS content; includes prior v0.2 improvements.
