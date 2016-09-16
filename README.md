# Send token email via PHP Cli
Allow to send token email (invite or reminder) via PHP cli . This allow to use crontab or Scheduled task to send email.

## Installation

- This plugin is only tested with LimeSurvey 2.06. Tested on some 2.50 build (always use latest build if possible)

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
  - Minimum delay between invitation and first reminder
  - Minimum delay between each reminders
- To test the plugin you need to call it via PHP Cli `php yourlimesurveydir/application/commands/console.php plugin cron --interval=1` (remind: it send email in this way)
- This line can be added in your crontab or Task Scheduler

### Logging
Plugin use 2 system for logging :
- echo at console what happen : then you can use cron system to send an email, or test the plugin : error and info was echoed
- use [Yii::log](http://www.yiiframework.com/doc/guide/1.1/en/topics.logging) : 3 state : error, info and trace. Loggued as application.plugins.sendMailCron
  - The log file is, by default ./tmp/runtime/application.log
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
- HomePage <http://extensions.sondages.pro/>
- Copyright © 2016 Denis Chenu <http://sondages.pro>
- Copyright © 2016 AXA Insurance (Gulf) B.S.C. <http://www.axa-gulf.com>
- Licence : GNU General Public License <https://www.gnu.org/licenses/gpl-3.0.html>

## Support
- Issues <https://git.framasoft.org/SondagePro-LimeSurvey-plugin/sendMailCron>
- Professional support <http://extensions.sondages.pro/1>
