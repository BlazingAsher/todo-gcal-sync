# Microsoft To Do and Google Calendar Syncer
(Not affiliated with Microsoft whatsoever)

These PHP scripts allow you to sync Microsoft To Do with Google Calendar. Here are the steps to set stuff up.

1. Create a .env file based on .env.template
2. Save your Google App Credential JSON (OAuth 2.0 Client ID) in the project root as `google_client_credentials.json`
3. Visit `init.php` to set up the database (recommended you delete it after)
4. Visit `signin-ms.php` and grant the app access to your Microsoft account
5. Visit `signin-google.php` and grant the app access to your Google account
6. Edit the database and specify your Google Calendar ID in the `tokens` table under `google_calendar_id`
7. Visit `create-ms-sub.php` to create a subscription for push notifications from Microsoft
8. Set up a cron job to call cron.php to refresh tokens and renew subscriptions weekly.