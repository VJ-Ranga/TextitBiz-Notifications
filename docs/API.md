# Textit.biz API Notes

This project is built around the Textit.biz SMS service.

## Provider

- Website: https://textit.biz/
- Basic HTTP API docs: https://textit.biz/integration_Basic_HTTP_API.php
- REST API docs: https://textit.biz/integration_REST_API.php

## Current implementation

The plugin currently uses the Basic HTTP API because it matches the simple `User ID` and `Password` settings used in the plugin UI.

### Endpoint

`https://textit.biz/sendmsg/`

### Required parameters

- `id` - Textit.biz user ID / phone number in international format
- `pw` - Textit.biz password
- `to` - recipient mobile number
- `text` - SMS content

### Example

`https://textit.biz/sendmsg/?id=94123456789&pw=1234&to=94772823050&text=Hello`

## Response handling

Textit.biz returns plain text responses similar to:

- `OK:message_id`
- error text when sending fails

The plugin treats responses starting with `OK` as successful.
