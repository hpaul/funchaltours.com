#!/usr/bin/env bash
# =============================================================================
# GitHub Webhook Handler
# =============================================================================
# Simple webhook endpoint using adnanh/webhook
#
# Install: apt install webhook
#
# Create /etc/webhook.conf:
# [
#   {
#     "id": "deploy-funchaltours",
#     "execute-command": "/var/www/funchaltours.com/bin/deploy.sh",
#     "command-working-directory": "/var/www/funchaltours.com",
#     "pass-arguments-to-command": [],
#     "trigger-rule": {
#       "and": [
#         {
#           "match": {
#             "type": "payload-hmac-sha256",
#             "secret": "YOUR_WEBHOOK_SECRET_HERE",
#             "parameter": {
#               "source": "header",
#               "name": "X-Hub-Signature-256"
#             }
#           }
#         },
#         {
#           "match": {
#             "type": "value",
#             "value": "refs/heads/main",
#             "parameter": {
#               "source": "payload",
#               "name": "ref"
#             }
#           }
#         }
#       ]
#     }
#   }
# ]
#
# Add to Caddyfile (reverse proxy to webhook):
#   route /hooks/* {
#     reverse_proxy localhost:9000
#   }
#
# Start webhook:
#   webhook -hooks /etc/webhook.conf -verbose
#
# Or create a systemd service:
#   /etc/systemd/system/webhook.service
# =============================================================================

echo "This file contains setup instructions — see comments above."
echo "The actual deploy logic is in bin/deploy.sh"
