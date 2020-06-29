# Microsoft To Do and Google Calendar Syncer
(Not affiliated with Microsoft whatsoever)

These PHP scripts allow you to sync Microsoft To Do with Google Calendar. Here are the steps to set stuff up.

0. Install dependencies with `composer install`
1. Create a .env file based on .env.template
2. Save your Google App Credential JSON (OAuth 2.0 Client ID) in the project root as `google_client_credentials.json`
3. Visit `init.php` to set up the database (recommended you delete it after)
4. Visit `signin-ms.php` and grant the app access to your Microsoft account
5. Visit `signin-google.php` and grant the app access to your Google account
6. Edit the database and specify your Google Calendar ID in the `tokens` table under `google_calendar_id`
7. Visit `create-ms-sub.php` to create a subscription for push notifications from Microsoft
8. Set up a cron job to call cron.php to refresh tokens and renew subscriptions weekly.

# Known Bugs
- If the current UTC date does not match your local date, stuff gets weird.
    - Everything I'm about to say is not based on any documentation, just my observations.
        - Google Calendar ignores timezones when setting dates (understandable).
            - If you give it a date, it looks like it takes it as if it were in your primary timezone.
        - Microsoft's Outlook API seems to return a task's due date in your current timezone, but hard codes the Timezone field in the response to UTC.
        - Because of these two factors, if you set a task to be due today, it will be created on the correct day in Google Calendar (assuming your Outlook timezone and Google Calendar timezone match).
            - However, if you leave the date blank, the CreatedDateTime is correctly formatted to UTC. However, Google ignores the timezone field, and takes it as if that date was in your timezone.
        - If I am mistaken at all and there is a fix, please open an issue. I would love to get this resolved.
        
- If you find a bug, please open an issue.