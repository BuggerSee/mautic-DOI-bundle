# Plugin: Email Verification and Double Opt-In (DOI) by Leuchtfeuer

Universal email verification plugin for Mautic, providing Double Opt-In (DOI) functionality with seamless form integration.

## Features

- **Form Action Management**: Separate actions for immediate submission vs. post-verification
- **Email Verification**: Built-in DOI workflow with `{doi_link}` token support
- **Follow-up Emails**: Automated reminders for unconfirmed submissions
- **Flexible Redirects**: Configurable success/error pages after verification
- **Security**: HMAC-based hash generation for verification links
- **Console Commands**: Cron-compatible follow-up email sending

## Installation

1. Extract to `plugins/MauticDoiBundle/`
2. Run `php bin/console cache:clear`
3. Run `php bin/console mautic:plugins:reload`
4. Configure plugin settings in Mautic admin

## Configuration

### Plugin Settings
- **Follow-up wait time**: Hours before sending reminder emails
- Set up cron job: `php bin/console leuchtfeuer:doi:send-followup`

### Form Configuration
1. **Actions Tab**: Configure immediate vs. post-verification actions
2. **Email Verification Tab**:
   - Enable DOI for this form
   - Verification email to send (required when DOI is enabled)
   - Follow-up email to send (optional)
   - Thank you page redirect URL (optional)
   - Verification error redirect URL (optional)

## Usage

1. Create verification email with `{doi_link}` token
2. Configure form with DOI settings
3. Form submissions trigger verification email
4. Users click verification link to confirm
5. Post-verification actions execute automatically

## Requirements

- Mautic 5.2
- PHP 8.1+
