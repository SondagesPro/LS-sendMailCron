# Send token email via PHP Cli
Allow to send token email (invite or reminder) via PHP cli . This allow to use crontab or Scheduled task to send email.

## Installation

- This plugin is only tested with LimeSurvey 2.06. Tested on 2.50 build (always use latest build if possible)
- In some LimeSurvey version : you need to manually create the `./application/runtime` to use CFileLogRoute ([See LimeSurvey manual](https://manual.limesurvey.org/Cron)).

### Via GIT
- Go to your LimeSurvey Directory (version up to 2.06, build 150729)
- Clone in plugins/sendMailCron directory

### Via ZIP dowload
- Get the file at http://extensions.sondages.pro/IMG/auto/sendMailCron.zip
- Extract : `unzip sendMailCron.zip`
- Move the directory to plugins/ directory inside LimeSUrvey

## Usage

- When activated the plugin settings are updated to use the actual url for email. This can be updated at any time
- You can choose
  - Max number of email to send
  - Batch size on each command
  - Minimum delay between invitation and first reminder
  - Minimum delay between each reminders
  - Same and more in each surveys setting
  - If plugin validate the email (this allow only one email by token)
- To test the plugin you need to call it via PHP Cli `php yourlimesurveydir/application/commands/console.php plugin cron --interval=1` (remind: it send email in this way)
- This line can be added in your crontab or Task Scheduler

### Params

The plugin accept optionnal parameters in the command line

- **debug** level from 0 : nothing is shown except errors to 3 all action are shown sendMailCronDebug=3 (integer, default 1)
- **simulate** action done by the plugin : don't send any email or update token : sendMailCronSimulate=1 (boolean, default false)
- **disable** all action done by the plugin in command line : sendMailCronDisable=1 (boolean, default false)

Some example

- `php yourlimesurveydir/application/commands/console.php plugin cron endMailCronDebug=0` nothing is printed to screen except errors. By default show tested survey and action, and number of email send for each survey
- `php yourlimesurveydir/application/commands/console.php plugin cron endMailCronDebug=3 sendMailCronSimulate=1` just to see what happen before put the command in the crontab, with all the trace of the plugin

### Logging
Plugin use 2 system for logging :
- echo at console what happen : then you can use cron system to send an email, or test the plugin : error and info was echoed
- use [Yii::log](http://www.yiiframework.com/doc/guide/1.1/en/topics.logging) : 3 state : error, info and trace. Loggued as application.plugins.sendMailCron
  - The log file is, by default ./tmp/runtime/application.log or ./application/runtime/application.log before LimeSurvey version 2.57.2
  - To have more information on mail error : you must enable 'SMTP debug mode' in LimeSurvey instance global settings

#### Exemple of logging system:

  ````
      'log' => array(
          'routes' => array(
              'fileError' => array(
                  'class' => 'CFileLogRoute',
                  'levels' => 'warning, error',
              ),
              'sendMailCron' => array(
                  'class' => 'CFileLogRoute',
                  'levels' => 'info, warning, error',
                  'categories'=>'application.plugins.sendMailCron',
              ),
          )
      ),
  ````



## Home page & Copyright
- HomePage <http://extensions.sondages.pro/sendmailcron/>
- Copyright © 2016 Denis Chenu <http://sondages.pro>
- Copyright © 2016 AXA Insurance (Gulf) B.S.C. <http://www.axa-gulf.com>
- Copyright © 2016 Extract Recherche Marketing <http://www.extractmarketing.com>
- Licence : GNU Affero General Public License <https://www.gnu.org/licenses/gpl-3.0.html>

## Support
- Issues <https://git.framasoft.org/SondagePro-LimeSurvey-plugin/sendMailCron>
- Professional support <http://extensions.sondages.pro/1>
