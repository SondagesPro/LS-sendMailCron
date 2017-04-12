# Change Log

Only partial changelog, [commit history](https://framagit.org/SondagePro-LimeSurvey-plugin/sendMailCron/commits/master) show all changelog.

## Not released

### Feature
- Use Token model, allowing to use beforeTokenSave event
- Really allow to use all other plugins in Command

## [0.4.0] - 2017-03-16

### Fix

- Send after reminder tested
- message and subject updated by beforeTokenEmail event


## [0.3.1] - 2017-03-16

### Feature

- Translation via po/mo
- Control day of week when sending email by survey
- Allow to disable email validate before try to send
- Batch size by surveys
- Different batch size for reminder and invitation by surveys
- Allow debug, simulate and disable in command
- Adding cron/task type for specific send (moment)

### Fix

- stripslashes for some server configuration.
- Better simulation
- LimeSurvey 3.0 compatibility quick fix
- Add beforeTokenEmail event when send an email
- Better ordering for reminder
- Fix 1st reminder send with the good day delay

## [0.2.0] - 2016-12-14

- This version is ditributed in Affero General Public License

### Feature

- Allow different settings by survey
- Add a global batch file size

## [0.1.1] - 2016-09-17

### Fix

- trim email before validate

## [0.0.1] - 2016-05-17

### Feature
- send email by cron event
