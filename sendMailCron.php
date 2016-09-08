<?php
/**
 * sendMailCron : allow to send token email by cron or sheduled task
 * Need activate cron system in the server : php yourlimesurveydir/application/commands/console.php plugin cron --interval=X where X is interval in minutes
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>
 * @copyright 2016 AXA Insurance (Gulf) B.S.C. <http://www.axa-gulf.com>
 * @license GPL v3
 * @version 0.1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

class sendMailCron extends PluginBase
{
    protected $storage = 'DbStorage';
    static protected $description = 'Allow to send token email by cron or sheduled task';
    static protected $name = 'sendMailCron';

    private $debug= 2;// Minimum:0 (ERROR) 1: INFO, 2 : DEBUG

    protected $settings = array(
        'information' => array(
            'type' => 'info',
            'content' => 'Need activate cron system in the server : php yourlimesurveydir/application/commands/console.php plugin cron --interval=X where X is interval in minutes',
        ),
        'hostInfo' => array(
            'type' => 'string',
            'label' => 'Host info for url',
        ),
        'baseUrl' => array(
            'type' => 'string',
            'label' => 'baseUrl',
        ),
        'scriptUrl' => array(
            'type' => 'string',
            'label' => 'scriptUrl',
        ),
        'maxEmail' => array(
            'type'=>'int',
            'htmlOptions'=>array(
                'min'=>0,
            ),
            'label'=>"Max email to send (invitation + remind), set it to 0 to deactivate sending of email.",
            'default'=>2,
        ),
        'delayInvitation' => array(
            'type'=>'int',
            'htmlOptions'=>array(
                'min'=>1,
            ),
            'label'=>"Min delay between invitation and first reminder.",
            'default'=>7,
        ),
        'delayReminder' => array(
            'type'=>'int',
            'htmlOptions'=>array(
                'min'=>1,
            ),
            'label'=>"Min delay between reminders.",
            'default'=>7,
        ),
    );
    /**
    * Add function to be used in cron event
    */
    public function init()
    {
        $this->subscribe('cron','sendMailByCron');
        $this->subscribe('beforeActivate');
    }

    /**
    * set actual url when activate
    */
    public function beforeActivate()
    {
        $event = $this->getEvent();
        if(is_null($this->getSetting('hostInfo')))
        {
            if(Yii::app() instanceof CConsoleApplication)
            {
                $event->set('success', false);
                $event->set('message', 'This plugin need to be configurated before activate.');
                return;
            }
            $settings=array(
                'hostInfo'=>Yii::app()->request->getHostInfo(),
                'baseUrl'=>Yii::app()->request->getBaseUrl(),
                'scriptUrl'=>Yii::app()->request->getScriptUrl(),
            );
            $this->saveSettings($settings);
            $event->set('message', 'Default configuration for url is used.');
            App()->setFlashMessage('Default configuration for url is used.');
        }
    }

    public function sendMailByCron()
    {
        $this->setConfigs();

        $oSurveys=Survey::model()->findAll(
            "active = 'Y' AND (startdate <= :now1 OR startdate IS NULL) AND (expires >= :now2 OR expires IS NULL)",
                array(
                    ':now1' => self::dateShifted(date("Y-m-d H:i:s")),
                    ':now2' => self::dateShifted(date("Y-m-d H:i:s"))
                )
            );
        // Unsure we need whole ... to be fixed
        Yii::import('application.helpers.common_helper', true);
        Yii::import('application.helpers.surveytranslator_helper', true);
        Yii::import('application.helpers.replacements_helper', true);
        Yii::import('application.helpers.expressions.em_manager_helper', true);
        // Fix the url
        Yii::app()->request->hostInfo=$this->getSetting("hostInfo");
        // Need to parse url and test
        Yii::app()->request->baseUrl=$this->getSetting("baseUrl");
        Yii::app()->request->scriptUrl=$this->getSetting("scriptUrl");

        if($oSurveys)
        {
            foreach ($oSurveys as $oSurvey)
            {
                $iSurvey=$oSurvey->sid;
                if(tableExists("{{tokens_{$iSurvey}}}")){
                    $this->log("Send email for {$iSurvey}",1);
                    Yii::app()->setConfig('surveyID',$iSurvey);
                    // Fill some information for this
                    $this->sendEMails($iSurvey,'invite');
                    $this->sendEMails($iSurvey,'remind');
                }
            }
        }
        //~ print_r($oSurveys);
    }

    private static function dateShifted($date, $dformat="Y-m-d H:i:s")
    {
        if(Yii::app()->getConfig("timeadjust",false)===false)
        {
            $oTimeAdjust=SettingGlobal::model()->find("stg_name=:stg_name",array(":stg_name"=>'timeadjust'));
            if($oTimeAdjust)
                Yii::app()->setConfig("timeadjust",$oTimeAdjust->stg_value);
            else
                Yii::app()->setConfig("timeadjust",0);
        }
        return date($dformat, strtotime(Yii::app()->getConfig("timeadjust"), strtotime($date)));
    }

    // We need a lot of config
    private function setConfigs()
    {
        $aDefaultConfigs = require(Yii::app()->basePath. DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config-defaults.php');
        foreach($aDefaultConfigs as $sConfig=>$defaultConfig)
            Yii::app()->setConfig($sConfig,$defaultConfig);
        //~ $ls_config = require(Yii::app()->basePath. DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config-defaults.php');
        //~ $this->config = array_merge($this->config, $ls_config);
        // Fix rootdir .....
        if(!is_dir(Yii::app()->getConfig('usertemplaterootdir')))
        {
            $sRootDir=realpath(Yii::app()->basePath. DIRECTORY_SEPARATOR . "..") ;
            Yii::app()->setConfig('rootdir',$sRootDir);
            Yii::app()->setConfig('publicdir',$sRootDir);
            Yii::app()->setConfig('homedir',$sRootDir);
            Yii::app()->setConfig('tempdir',$sRootDir.DIRECTORY_SEPARATOR."tmp");
            Yii::app()->setConfig('imagedir',$sRootDir.DIRECTORY_SEPARATOR."images");
            Yii::app()->setConfig('uploaddir',$sRootDir.DIRECTORY_SEPARATOR."upload");
            Yii::app()->setConfig('standardtemplaterootdir',$sRootDir.DIRECTORY_SEPARATOR."templates");
            Yii::app()->setConfig('usertemplaterootdir',$sRootDir.DIRECTORY_SEPARATOR."upload".DIRECTORY_SEPARATOR."templates");
            Yii::app()->setConfig('styledir',$sRootDir.DIRECTORY_SEPARATOR."styledir");
        }
        if(file_exists(Yii::app()->basePath. DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php'))
        {
            $config = require(Yii::app()->basePath. DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');
            if(is_array($config['config']) && !empty($config['config']))
            {
                foreach($config['config'] as $key=>$value)
                    Yii::app()->setConfig($key,$value);
            }
        }
        $oSettings=SettingGlobal::model()->findAll();
        if (count($oSettings) > 0)
        {
            foreach ($oSettings as $oSetting)
            {
                Yii::app()->setConfig($oSetting->getAttribute('stg_name'), $oSetting->getAttribute('stg_value'));
            }
        }
    }
        private function sendEmails($iSurvey,$sType='invite')
        {
            // For the log
            $iSendedMail=$iInvalidMail=$iErrorMail=0;
            $bHtml = (getEmailFormat($iSurvey) == 'html');
            $aSurveyLangs = Survey::model()->findByPk($iSurvey)->additionalLanguages;
            $sBaseLanguage = Survey::model()->findByPk($iSurvey)->language;
            array_unshift($aSurveyLangs, $sBaseLanguage);
            foreach($aSurveyLangs as $sSurveyLanguage)
            {
                $aSurveys[$sSurveyLanguage] = getSurveyInfo($iSurvey, $sSurveyLanguage);
            }
            foreach ($aSurveyLangs as $language)
            {
                $sSubject[$language]=preg_replace("/{TOKEN:([A-Z0-9_]+)}/","{"."$1"."}",$aSurveys[$language]["surveyls_email_{$sType}_subj"]);
                $sMessage[$language]=preg_replace("/{TOKEN:([A-Z0-9_]+)}/","{"."$1"."}",$aSurveys[$language]["surveyls_email_{$sType}"]);
                if ($bHtml)
                    $sMessage[$language] = html_entity_decode($sMessage[$language], ENT_QUOTES, Yii::app()->getConfig("emailcharset"));
            }
            $dToday=dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"));
            $dYesterday=dateShift(date("Y-m-d H:i:s", time() - 86400), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"));
            $dTomorrow=dateShift(date("Y-m-d H:i:s", time() + 86400), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"));
            // Test si survey actif et avec bonne date $aSurveys[$sBaseLanguage]
            $bSurveySimulate=false;

            $sFrom = "{$aSurveys[$sBaseLanguage]['admin']} <{$aSurveys[$sBaseLanguage]['adminemail']}>";
            $sBounce=getBounceEmail($iSurvey);
            $dtToday=strtotime($dToday);
            $dFrom=$dToday;
            $maxReminder=intval($this->getSetting('maxEmail',null,null,$this->settings['maxEmail']['default']));
            $maxReminder--;
            if($sType=='remind' && $maxReminder < 1)
            {
                $this->log("Survey {$iSurvey}, {$sType} deactivated",2);// Ajoute DEBUG
                return;
            }
            $delayInvitation=$this->getSetting('delayInvitation',null,null,$this->settings['delayInvitation']['default']);
            $dayDelayReminder=$this->getSetting('delayReminder',null,null,$this->settings['delayReminder']['default']);

            //~ if($sType=='invite')
                //~ $dFrom=date("Y-m-d H:i",strtotime("-".intval($this->config['inviteafter'])." days",$dtToday));// valid is 3 day before and up
            if($sType=='remind')
                $dAfterSent=date("Y-m-d H:i",strtotime("-".intval($dayDelayReminder)." days",$dtToday));// sent is X day before and up
            $dTomorrow=dateShift(date("Y-m-d H:i", time() + 86400 ), "Y-m-d H:i", Yii::app()->getConfig("timeadjust"));// Tomorrow for validuntil
            $this->log("Survey {$iSurvey}, {$sType} Valid from {$dFrom} And Valid until {$dTomorrow} (or NULL)",2);// Ajoute DEBUG
            $oCriteria = new CDbCriteria;
            $oCriteria->select = "tid";
            $oCriteria->addCondition("emailstatus = 'OK'");
            $oCriteria->addCondition("token != ''");
            if($sType=='invite')
                $oCriteria->addCondition("(sent = 'N' OR sent = '' OR sent IS NULL)");
            if($sType=='remind')
                $oCriteria->addCondition("(sent != 'N' AND sent != '' AND sent IS NOT NULL)");
            if($sType=='remind')
                $oCriteria->addCondition("(sent < :sent)");
            if($sType=='remind')
                $oCriteria->addCondition("remindercount < :remindercount  OR remindercount = '' OR remindercount IS NULL");// No other reminder
            $oCriteria->addCondition("(completed = 'N' OR completed = '' OR completed  IS NULL)");
            $oCriteria->addCondition("usesleft>0");
            $oCriteria->addCondition("(validfrom < :validfrom OR validfrom IS NULL)");
            $oCriteria->addCondition("(validuntil > :validuntil OR validuntil IS NULL)");
            if($sType=='invite')
                    $oCriteria->params = array(':validfrom'=>$dFrom,':validuntil'=>$dTomorrow);
            if($sType=='remind')
                    $oCriteria->params = array(':validfrom'=>$dFrom,':validuntil'=>$dTomorrow,':sent'=>strval($dAfterSent),':remindercount'=>$maxReminder);
            # Send invite
            // Find all token
            $oTokens=TokenDynamic::model($iSurvey)->findAll($oCriteria);
            foreach ($oTokens as $iToken)
            {
                $oToken=TokenDynamic::model($iSurvey)->findByPk($iToken->tid);
                $this->log("Send : {$oToken->email} pour {$iSurvey}",2);
                if (filter_var($oToken->email, FILTER_VALIDATE_EMAIL)) {
                    $sLanguage = trim($oToken->language);
                    if (!in_array($sLanguage,$aSurveyLangs))
                    {
                        $sLanguage = $sBaseLanguage;
                    }
                    // Construct the mail
                    $sToken=$oToken->token;
                    $sTo = "{$oToken->firstname} {$oToken->lastname} <{$oToken->email}>";
                    $aFieldsArray=array();

                    $aFieldsArray["{SURVEYNAME}"]=$aSurveys[$sLanguage]['surveyls_title'];
                    $aFieldsArray["{SURVEYDESCRIPTION}"]=$aSurveys[$sLanguage]['surveyls_description'];
                    $aFieldsArray["{SURVEYWELCOMETEXT}"]=$aSurveys[$sLanguage]['surveyls_welcometext'];
                    $aFieldsArray["{ADMINNAME}"]=$aSurveys[$sLanguage]['admin'];
                    $aFieldsArray["{ADMINEMAIL}"]=$aSurveys[$sLanguage]['adminemail'];
                    foreach ($oToken->attributes as $attribute=>$value)
                    {
                        $aFieldsArray['{' . strtoupper($attribute) . '}'] =$value;
                    }
                    // Url Links
                    $aUrlsArray["OPTOUTURL"] = App()->createAbsoluteUrl("/optout/tokens",array('langcode'=>$sLanguage,'surveyid'=>$iSurvey,'token'=>$sToken));
                    $aUrlsArray["OPTINURL"] = App()->createAbsoluteUrl("/optin/tokens",array('langcode'=>$sLanguage,'surveyid'=>$iSurvey,'token'=>$sToken));
                    $aUrlsArray["SURVEYURL"] = App()->createAbsoluteUrl("/survey/index",array('sid'=>$iSurvey,'token'=>$sToken,'lang'=>$sLanguage));
                    foreach($aUrlsArray as $key=>$url)
                    {
                        if ($bHtml)
                            $aFieldsArray["{{$key}}"] = "<a href='{$url}'>" . htmlspecialchars($url) . '</a>';
                        else
                            $aFieldsArray["{{$key}}"] = $url;

                    }

                    $aCustomHeaders = array(
                        '1' => "X-surveyid: " . $iSurvey,
                        '2' => "X-tokenid: " . $sToken
                    );
                    $subject = Replacefields($sSubject[$sLanguage], $aFieldsArray);
                    $message = Replacefields($sMessage[$sLanguage], $aFieldsArray);
                    foreach($aUrlsArray as $key=>$url)
                    {
                        $message = str_replace("@@{$key}@@", $url, $message);
                    }
                    if(!$bSurveySimulate){
                        global $maildebug;
                        if (SendEmailMessage($message, $subject, $sTo, $sFrom, Yii::app()->getConfig("sitename"), $bHtml, $sBounce, array(), $aCustomHeaders)){
                            $iSendedMail++;
                            $oCommand=Yii::app()->db->createCommand();
                            if($sType=='invite'){
                                $oCommand->update(
                                    "{{tokens_{$iSurvey}}}",
                                    array(
                                        'sent'=>dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig("timeadjust")),
                                    ),
                                    "tid=:tid",
                                    array(":tid"=>$oToken->tid)
                                );
                            }
                            if($sType=='remind'){
                                $oCommand->update(
                                    "{{tokens_{$iSurvey}}}",
                                    array(
                                        'remindersent'=>dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig("timeadjust")),
                                        'remindercount'=>$oToken->remindercount+1,
                                    ),
                                    "tid=:tid",
                                    array(":tid"=>$oToken->tid)
                                );
                            }
                        }else{
                            if($maildebug){
                                $this->log("Unknow error when send email to {$sTo} ({$iSurvey}) : ".$maildebug);
                            }else{
                                $this->log("Unknow error when send email to {$sTo} ({$iSurvey})");// Ajoute erreur
                            }
                            $iErrorMail++;
                        }
                    }else{
                        $iSendedMail++;
                    }
                }else{
                    $iInvalidMail++;
                    $oCommand=Yii::app()->db->createCommand();
                    $oCommand->update(
                        "{{tokens_{$iSurvey}}}",
                        array(
                            'emailstatus'=>'invalid',
                        ),
                        "tid=:tid",
                        array(":tid"=>$iToken->tid)
                    );
                }
            }
            if(!$iSendedMail && !$iErrorMail && !$iInvalidMail)
                $this->log("No message to sent",1);
            if($iSendedMail)
                $this->log("{$iSendedMail} messages sent",1);
            if($iInvalidMail)
                $this->log("{$iInvalidMail} invalid email adress",1);
            if($iErrorMail)
                $this->log("{$iErrorMail} messages with unknow error",1);
        }
        /**
        * log
        */
        private function log($sLog,$bState=0,$tab=true){
            // Play with DEBUG : ERROR/LOG/DEBUG
            $sNow=date(DATE_ATOM);
            switch ($bState){
                case 0:
                    $sLevel='error';
                    $sLogLog="[ERROR] $sLog";
                    break;
                case 1:
                    $sLevel='info';
                    $sLogLog="[INFO] $sLog";
                    break;
                default:
                    $sLevel='trace';
                    $sLogLog="[DEBUG] $sLog";
                    break;
            }
            Yii::log($sLog, $sLevel,'application.plugins.sendMailCron');
            if($bState < $this->debug || $bState==0)
            {
                echo "[{$sNow}] {$sLogLog}\n";
            }
        }

        /**
        * LimeSurvey 2.06 have issue with getPluginSettings->getPluginSettings (autoloader is broken) with command
        * Then use own function
        */
        private function getSetting($sSetting,$sObject=null,$sObjectId=null,$default=null)
        {
            //~ if($sObject && $sObjectId)
            $oSetting=PluginSetting::model()->find("plugin_id=:plugin_id AND model IS NULL AND ".Yii::app()->db->quoteColumnName("key")."=:ssetting",array(":plugin_id"=>$this->id,":ssetting"=>$sSetting));
            if($oSetting && !is_null($oSetting->value))
            {
                return trim(stripslashes($oSetting->value),'"');
            }
            else
                return $default;
        }
        // Set the default to actual url when show settngs
        public function getPluginSettings($getValues=true)
        {
            if(!Yii::app() instanceof CConsoleApplication)
            {
                $this->settings['hostInfo']['default']= Yii::app()->request->getHostInfo();
                $this->settings['baseUrl']['default']= Yii::app()->request->getBaseUrl();
                $this->settings['scriptUrl']['default']= Yii::app()->request->getScriptUrl();
            }
            return parent::getPluginSettings($getValues);
        }
}
