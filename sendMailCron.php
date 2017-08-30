<?php
/**
 * sendMailCron : allow to send token email by cron or sheduled task
 * Need activate cron system in the server : php yourlimesurveydir/application/commands/console.php plugin cron --interval=X where X is interval in minutes
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016-2017 Denis Chenu <https://www.sondages.pro>
 * @copyright 2016 AXA Insurance (Gulf) B.S.C. <http://www.axa-gulf.com> for initial version
 * @copyright 2016-2017 Extract Recherche Marketing for cronTypes and BatchSize
 * @license AGPL v3
 * @version 2.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

class sendMailCron extends \ls\pluginmanager\PluginBase
{
    protected $storage = 'DbStorage';
    static protected $description = 'Allow to send token email by cron or sheduled task';
    static protected $name = 'sendMailCron';

    /**
     * @var int debug (for echoing on terminal) 0=>ERROR,1=>INFO, 2=>DEBUG
     */
    private $debug= 1;
    /**
     * @var boolean simulate sending (for log)
     */
    private $simulate=false;
    /**
     * @var boolean disable plugin (via command line)
     */
    private $disable=false;
    /**
     * @var string actual cron type
     */
    private $cronType;

    /**
     * @var int currentBatchSize Count for this batch
     */
    private $currentBatchSize=0;
    /**
     * @var int SurveyBatchSize Count for this batch of actual survey
     */
    private $currentSurveyBatchSize;

    /**
     * @var array[] the settings
     */
    protected $settings = array(
        'information' => array(
            'type' => 'info',
            'content' => 'You need activate cron system in the server : <code>php yourlimesurveydir/application/commands/console.php plugin cron --interval=1</code>. This plugin don\'t use interval, all email of all surveys are tested when cron happen.',
        ),
        'maxBatchSize' => array(
            'type'=>'int',
            'htmlOptions'=>array(
                'min'=>1,
            ),
            'label'=>"Max email to send globally (max batch size).",
            'help'=>'Leave empty to send all available email on each cron',
            'default'=>'',
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
            'label'=>"Min delay between each reminders.",
            'default'=>7,
        ),
        'validateEmail' => array(
            'type'=>'checkbox',
            'value'=>1,
            'htmlOption'=>array(
                'uncheckValue'=>0
            ),
            'label'=>"Validate email before try to send it.",
            'default'=>0,
        ),
        'cronTypes' => array(
            'type'=>'text',
            'label'=>"Cron moment type.",
            'help'=>"One moment by line, use only alphanumeric caracter, you can use <code>,</code> or <code>;</code> as separator.",
            'default'=>'',
        ),
        'cronTypeNone' => array(
            'type'=>'checkbox',
            'value'=>1,
            'htmlOption'=>array(
                'uncheckValue'=>0
            ),
            'label'=>"Survey without moment use empty moment or moment not set only.",
            'help'=>"If a survey don't have type: you can choose if it sended for each cron or only if no moment is set",
            'default'=>1,
        ),
    );
    /**
    * Add function to be used in cron event
    * @see parent::init()
    */
    public function init()
    {
        /* Action on cron */
        $this->subscribe('cron','sendMailByCron');
        /* Needed config */
        $this->subscribe('beforeActivate');

        /* Add the link in menu */
        $this->subscribe('beforeToolsMenuRender');
        /* The survey seeting */
        $this->subscribe('beforeSurveySettings');

        /* Update Yii config */
        $this->subscribe('afterPluginLoad');
    }

    /**
    * set actual url when activate
    * @see parent::saveSettings()
    */
    public function saveSettings($settings)
    {
        if(isset($settings['cronTypes']))
        {
            $cronTypes = preg_split('/\r\n|\r|\n|,|;/', $settings['cronTypes']);
            $cronTypes = array_map(
                    function($cronType) { return preg_replace("/[^0-9a-zA-Z]/i", '', $cronType); },
                    $cronTypes
            );
            $cronTypes = array_filter($cronTypes);
            $settings['cronTypes']=implode( "|",$cronTypes);
        }

        parent::saveSettings($settings);
    }
    /**
    * set actual url when activate
    * @see parent::beforeActivate()
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

    /**
     * @see parent::beforeSurveySettings()
     */
    public function beforeSurveySettings()
    {
        $event = $this->event;
        // Must control if token table exist
        $iSurveyId=$event->get('survey');
        if(tableExists("{{tokens_{$iSurveyId}}}"))
        {
            $event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => array(
                    'gotosettings'=>array(
                    'type'=>'link',
                    'label'=>$this->_translate('Mail by cron settings'),
                    'htmlOptions'=>array(
                        'title'=>gt('This open another page'),
                    ),
                    'help'=>$this->_translate('Remind to save your settings before'),
                    'text'=>$this->_translate('Edit email by cron settings'),
                    'class'=>'btn btn-link',
                    'link'=>Yii::app()->createUrl('admin/pluginhelper',array('sa' => 'sidebody','plugin' => get_class($this),'method' => 'actionSettings','surveyId' => $iSurveyId)),
                    ),
                )
            ));
        }
    }

    /**
     * The action of sending cron when needed
     * @return void
     */
    public function sendMailByCron()
    {
        $this->_LsCommandFix();
        $this->_setConfigs();
        $this->setArgs();
        if($this->disable){
            return;
        }
        $maxBatchSize=$this->getSetting('maxBatchSize',null,null,'');

        $oSurveys=Survey::model()->findAll(
            "active = 'Y' AND (startdate <= :now1 OR startdate IS NULL) AND (expires >= :now2 OR expires IS NULL)",
                array(
                    ':now1' => self::_dateShifted(date("Y-m-d H:i:s")),
                    ':now2' => self::_dateShifted(date("Y-m-d H:i:s"))
                )
            );
        // Unsure we need whole ... to be fixed
        Yii::import('application.helpers.common_helper', true);
        Yii::import('application.helpers.surveytranslator_helper', true);
        Yii::import('application.helpers.replacements_helper', true);
        Yii::import('application.helpers.expressions.em_manager_helper', true);
        // Fix the url @todo parse url and validate
        Yii::app()->request->hostInfo=$this->getSetting("hostInfo");
        Yii::app()->request->baseUrl=$this->getSetting("baseUrl");
        Yii::app()->request->scriptUrl=$this->getSetting("scriptUrl");
        if($oSurveys)
        {
            foreach ($oSurveys as $oSurvey)
            {
                $iSurvey=$oSurvey->sid;
                if(tableExists("{{tokens_{$iSurvey}}}"))
                {
                    /* By maxEmail */
                    $maxEmail=$this->getSetting('maxEmail','survey',$iSurvey,"");
                    if($maxEmail===""){
                        $maxEmail=$this->getSetting('maxEmail',null,null,$this->settings['maxEmail']['default']);
                    }
                    if($maxEmail < 1){
                        $this->sendMailCronLog("sendMailByCron for {$iSurvey} deactivated",2);
                        continue;
                    }
                    /* By day of week */
                    $dayOfWeekToSend=$this->getSetting('dayOfWeekToSend','Survey', $iSurvey,"[]");
                    $aDayOfWeekToSend=array_filter(json_decode($dayOfWeekToSend));
                    $todayNum=(string)self::_dateShifted(date("Y-m-d H:i:s"),"N");
                    if(!empty($aDayOfWeekToSend) && !in_array($todayNum,$aDayOfWeekToSend)){
                        $this->sendMailCronLog("sendMailByCron deactivated for {$iSurvey} for today",1);
                        continue;
                    }
                    /* By cron type */
                    $availCronTypes=$this->getSetting('cronTypes',null,null,"");
                    $availCronTypes=explode('|', $availCronTypes);
                    if(!empty($availCronTypes)){
                        $surveyCronTypes=$this->getSetting('cronTypes','Survey',$iSurvey,"");
                        if($surveyCronTypes){
                            $surveyCronTypes=json_decode($surveyCronTypes);
                        } else {
                            $surveyCronTypes=array();
                            if(!$this->getSetting('cronTypeNone',null,null,$this->settings['cronTypeNone']['default']) && $this->cronType){
                                $surveyCronTypes=array($this->cronType);
                            } else {
                                $surveyCronTypes=array();
                            }
                        }
                        $surveyCronTypes=array_intersect($surveyCronTypes,$availCronTypes);
                        if($this->cronType){
                            if(!in_array($this->cronType,$surveyCronTypes)){
                                $this->sendMailCronLog("sendMailByCron deactivated for {$iSurvey} for this cron type ({$this->cronType})",1);
                                continue;
                            }
                        } else {
                            if(!empty($surveyCronTypes)){
                                $this->sendMailCronLog("sendMailByCron deactivated for {$iSurvey} for no cron type",1);
                                continue;
                            }
                        }

                    }
                    $this->sendMailCronLog("sendMailByCron for {$iSurvey}",1);
                    if($maxBatchSize && $maxBatchSize<=$this->currentBatchSize){
                        $this->sendMailCronLog("sendMailByCron deactivated for {$iSurvey} due to batch size",1);
                        continue;
                    }

                    Yii::app()->setConfig('surveyID',$iSurvey);
                    // Fill some information for this
                    $this->currentSurveyBatchSize=0;
                    $this->sendEmails($iSurvey,'invite');
                    if($maxEmail < 2){
                        $this->sendMailCronLog("sendMailByCron reminder for {$iSurvey} deactivated",2);
                        continue;
                    }
                    if(!$maxBatchSize || $maxBatchSize>=$this->currentBatchSize){
                        if(!$this->getSetting('maxSurveyBatchSize','Survey', $iSurvey) || $this->getSetting('maxSurveyBatchSize','Survey', $iSurvey)>=$this->currentBatchSize){
                            $this->sendEmails($iSurvey,'remind');
                        }else{
                            $this->sendMailCronLog("sendMailByCron reminder deactivated for {$iSurvey} due to survey batch size",1);
                        }
                    }else{
                        $this->sendMailCronLog("sendMailByCron reminder deactivated for {$iSurvey} due to batch size",1);
                    }
                }
            }
        }
    }

    /**
     * Return the date fixed by config (SQL format)
     * @param string $date in SQL format
     * @param string $dformat in SQL format
     * @return string
     */
    private static function _dateShifted($date, $dformat="Y-m-d H:i:s")
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

    /**
     * Set wholme LimeSurvey config (not needed in new LS version)
     * @return void
     */
    private function _setConfigs()
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

    /**
     * Send eamis for a survey and a type
     * @param int $iSurvey
     * @param string $sType
     * @return void
     */
    private function sendEmails($iSurvey,$sType='invite')
    {
        // For the log
        $aCountMail=array(
            'total'=>0,
            'sent'=>0,
            'invalid'=>0,
            'error'=>0,
            'started'=>0,
            'notstarted'=>0,
        );
        /* Get information */
        $bHtml = (getEmailFormat($iSurvey) == 'html');
        $oSurvey=Survey::model()->findByPk($iSurvey);
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

        $sFrom = "{$aSurveys[$sBaseLanguage]['admin']} <{$aSurveys[$sBaseLanguage]['adminemail']}>";
        $sBounce=getBounceEmail($iSurvey);
        $dtToday=strtotime($dToday);
        $dFrom=$dToday;
        $maxReminder=$this->getSetting('maxEmail','survey',$iSurvey,"");
        if($maxReminder===""){
            $maxReminder=$this->getSetting('maxEmail',null,null,$this->settings['maxEmail']['default']);
        }
        $maxReminder=intval($maxReminder);
        $maxReminder--;
        if($sType=='invite' && $maxReminder < 0)
        {
            $this->sendMailCronLog("Survey {$iSurvey}, {$sType} deactivated",2);
            return;
        }
        if($sType=='remind' && $maxReminder < 1)
        {
            $this->sendMailCronLog("Survey {$iSurvey}, {$sType} deactivated",2);
            return;
        }
        $delayInvitation=$this->getSetting('delayInvitation','survey',$iSurvey,"");
        if($delayInvitation==""){
            $delayInvitation=$this->getSetting('delayInvitation',null,null,$this->settings['delayInvitation']['default']);
        }
        $delayInvitation=intval($delayInvitation);
        $dayDelayReminder=$this->getSetting('delayReminder','survey',$iSurvey,"");
        if($dayDelayReminder==""){
            $dayDelayReminder=$this->getSetting('delayReminder',null,null,$this->settings['delayReminder']['default']);
        }
        $dayDelayReminder=intval($dayDelayReminder);
        if($sType=='remind' && ($delayInvitation < 1|| $dayDelayReminder < 1))
        {
            $this->sendMailCronLog("Survey {$iSurvey}, {$sType} deactivated due to bad value in delays",0);
            return;
        }
        $isNotAnonymous=$oSurvey->anonymized!="Y";
        $reminderOnlyTo=$this->getSetting('reminderOnlyTo', 'Survey', $iSurvey,'');
        if($reminderOnlyTo && $isNotAnonymous){
            $controlResponse=$reminderOnlyTo;
        }else{
            $controlResponse='';
        }
        //~ if($sType=='invite')
            //~ $dFrom=date("Y-m-d H:i",strtotime("-".intval($this->config['inviteafter'])." days",$dtToday));// valid is 3 day before and up
        if($sType=='remind')
        {
            $dAfterSentRemind=date("Y-m-d H:i",strtotime("-".intval($dayDelayReminder)." days",$dtToday));// sent is X day before and up
            $dAfterSent=date("Y-m-d H:i",strtotime("-".intval($delayInvitation)." days",$dtToday));// sent is X day before and up
        }
        $dTomorrow=dateShift(date("Y-m-d H:i", time() + 86400 ), "Y-m-d H:i", Yii::app()->getConfig("timeadjust"));// Tomorrow for validuntil
        $this->sendMailCronLog("Survey {$iSurvey}, {$sType} Valid from {$dFrom} And Valid until {$dTomorrow} (or NULL)",3);
        $oCriteria = new CDbCriteria;
        $oCriteria->select = "tid";
        /* Always needed condition */
        $oCriteria->addCondition("emailstatus = 'OK'");
        $oCriteria->addCondition("email != '' and email IS NOT NULL");
        $oCriteria->addCondition("token != ''");
        $oCriteria->addCondition("(completed = 'N' OR completed = '' OR completed  IS NULL)");
        $oCriteria->addCondition("usesleft>0");
        $oCriteria->addCondition("(validfrom < :validfrom OR validfrom IS NULL)");
        $oCriteria->addCondition("(validuntil > :validuntil OR validuntil IS NULL)");
        $aParams=array(
            ':validfrom'=>$dFrom,
            ':validuntil'=>$dTomorrow,
        );
        switch ($sType) {
            case 'invite':
                /* Not sent condition */
                $oCriteria->addCondition("(sent = 'N' OR sent = '' OR sent IS NULL)");
                break;
            case 'remind':
                /* sent condition */
                $oCriteria->addCondition("(sent != 'N' AND sent != '' AND sent IS NOT NULL)");
                /* Delay by settings condition */
                $oCriteria->addCondition("(
                    remindersent < :remindersent AND (remindercount != '' AND remindercount IS NOT NULL AND remindercount > 0)
                ) OR (
                    sent < :sent AND (remindercount = '' OR remindercount IS NULL OR remindercount < 1)
                )");
                /* Max by settings condition */
                $oCriteria->addCondition("remindercount < :remindercount  OR remindercount = '' OR remindercount IS NULL");
                $aParams=array_merge($aParams,array(
                    ':remindersent'=>strval($dAfterSentRemind),
                    ':sent'=>strval($dAfterSent),
                    ':remindercount'=>$maxReminder,
                ));
                break;
            default:
                /* Break for invalid */
                throw new Exception("Invalid email type {$sType} in sendMailCron.");
                return 1;
        }
        $oCriteria->params=$aParams;
        # Send email
        // Find all token
        $oTokens=Token::model($iSurvey)->findAll($oCriteria);
        $aCountMail['total']=count($oTokens);
        $this->sendMailCronLog("Sending $sType",1);
        foreach ($oTokens as $iToken)
        {
            /* Test actual sended */
            if($this->stopSendMailAction($aCountMail,$iSurvey,$sType)){
                return;
            }
            $oToken=Token::model($iSurvey)->findByPk($iToken->tid);
            /* Find if need to already started or not */
            if($controlResponse){
                $oResponse=SurveyDynamic::model($iSurvey)->find("token=:token",array(":token"=>$oToken->token));
                if(!$oResponse && $controlResponse=='notstarted'){
                    $this->sendMailCronLog("Not started survey : {$oToken->email} ({$oToken->tid}) for {$iSurvey}",3);
                    $aCountMail['notstarted']++;
                    continue;
                }
                if($oResponse && $controlResponse=='started'){
                    $this->sendMailCronLog("Already started survey : {$oToken->email} ({$oToken->tid}) for {$iSurvey}",3);
                    $aCountMail['started']++;
                    continue;
                }

            }
            if ($this->getSetting('validateEmail',null,null,$this->settings['validateEmail']['default']) && !filter_var(trim($oToken->email), FILTER_VALIDATE_EMAIL)) {
                $this->sendMailCronLog("invalid email : '{$oToken->email}' ({$oToken->tid}) for {$iSurvey}",2);
                $aCountMail['invalid']++;
                $oCommand=Yii::app()->db->createCommand();
                $oCommand->update(
                    "{{tokens_{$iSurvey}}}",
                    array(
                        'emailstatus'=>'invalid',
                    ),
                    "tid=:tid",
                    array(":tid"=>$iToken->tid)
                );
                continue;
            }
            $this->sendMailCronLog("Send : {$oToken->email} ({$oToken->tid}) for {$iSurvey}",3);
            $sLanguage = trim($oToken->language);
            if (!in_array($sLanguage,$aSurveyLangs))
            {
                $sLanguage = $sBaseLanguage;
            }

            $sToken=$oToken->token;
            /* Array of email adresses */
            $aTo=array();
            $aEmailaddresses = preg_split( "/(,|;)/", $oToken->email );
            foreach ($aEmailaddresses as $sEmailaddress)
            {
                $aTo[] = "{$oToken->firstname} {$oToken->lastname} <{$sEmailaddress}>";
            }
            /* Construct the mail content */
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
            if($this->simulate){
                $success=true;
            }else{
                /* Add the event 'beforeSendEmail */
                $beforeTokenEmailEvent = new PluginEvent('beforeTokenEmail');
                $beforeTokenEmailEvent->set('survey', $iSurvey);
                $beforeTokenEmailEvent->set('type', $sType);
                $beforeTokenEmailEvent->set('model', ($sType=='remind'?'reminder':'invitation'));
                $beforeTokenEmailEvent->set('subject', $subject);
                $beforeTokenEmailEvent->set('to',$aTo);
                $beforeTokenEmailEvent->set('body', $message);
                $beforeTokenEmailEvent->set('from', $sFrom);
                $beforeTokenEmailEvent->set('bounce', $sBounce);
                $beforeTokenEmailEvent->set('token', $oToken->attributes);
                App()->getPluginManager()->dispatchEvent($beforeTokenEmailEvent);
                $subject = $beforeTokenEmailEvent->get('subject');
                $message = $beforeTokenEmailEvent->get('body');
                $aTo = $beforeTokenEmailEvent->get('to');
                $sFrom = $beforeTokenEmailEvent->get('from');
                $sBounce = $beforeTokenEmailEvent->get('bounce');
                /* send the email */
                global $maildebug;
                if ($beforeTokenEmailEvent->get('send', true) == false)
                {
                    $maildebug = $beforeTokenEmailEvent->get('error', $maildebug);
                    $success = $beforeTokenEmailEvent->get('error') == null;
                }
                else
                {
                    $success = SendEmailMessage($message, $subject, $aTo, $sFrom, Yii::app()->getConfig("sitename"), $bHtml, $sBounce, array(), $aCustomHeaders);
                }
            }
            /* action if email is sent */
            if ($success){
                $aCountMail['sent']++;
                $this->currentBatchSize++;
                $this->currentSurveyBatchSize++;
                if(!$this->simulate){
                    if($sType=='invite'){
                        $oToken->sent=dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig("timeadjust"));
                        $txtLog="sent set to ".$oToken->sent;
                    }
                    if($sType=='remind'){
                        $oToken->remindersent=dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig("timeadjust"));
                        $oToken->remindercount++;
                        $txtLog="remindersent set to ".$oToken->remindersent." count:".$oToken->remindercount;
                    }
                    if($oToken->save()) {
                        $this->sendMailCronLog("Email {$oToken->email} : $txtLog",3);
                    } else {
                        $error=CVarDumper::dumpAsString($oToken->getErrors(), 3, false);
                        $this->sendMailCronLog("Email {$oToken->email} error: $error");
                    }
                }
            }else{
                $sTo=implode(";",$aTo);
                if($maildebug){
                    $this->sendMailCronLog("Unknow error when send email to {$oToken->email} ({$iSurvey}) : ".$maildebug);
                }else{
                    $this->sendMailCronLog("Unknow error when send email to {$oToken->email} ({$iSurvey})");
                }
                $aCountMail['error']++;
            }
        }
        $this->sendMailCronFinalLog($aCountMail);
    }

    /**
    * log an event
    * @param string $sLog
    * @param int $state
    * retrun @void
    */
    private function sendMailCronLog($sLog,$state=0){
        // Play with DEBUG : ERROR/LOG/DEBUG
        $sNow=date(DATE_ATOM);
        switch ($state){
            case 0:
                $sLevel='error';
                $sLogLog="[ERROR] $sLog";
                break;
            case 1:
                $sLevel='info';
                $sLogLog="[INFO] $sLog";
                break;
            case 2:
                $sLevel='info';
                $sLogLog="[DEBUG] $sLog";
                break;
            default:
                $sLevel='trace';
                $sLogLog="[TRACE] $sLog";
                break;
        }
        Yii::log($sLog, $sLevel,'application.plugins.sendMailCron');
        if($state <= $this->debug || $state==0)
        {
            echo "[{$sNow}] {$sLogLog}\n";
        }
    }

    /**
     * LimeSurvey 2.06 have issue with getPluginSettings->getPluginSettings (autoloader is broken) with command
     * Then use own function
     * @param string $sSetting
     * @param string $sObject
     * @param string $sObjectId
     * @param string $default
     * @return string
     */
    private function getSetting($sSetting,$sObject=null,$sObjectId=null,$default=null)
    {
        if($sObject && $sObjectId){
            $oSetting=PluginSetting::model()->find(
                "plugin_id=:plugin_id AND model=:model AND model_id=:model_id AND ".Yii::app()->db->quoteColumnName("key")."=:ssetting",
                array(
                    ":plugin_id"=>$this->id,
                    ":model"=>$sObject,
                    ":model_id"=>$sObjectId,
                    ":ssetting"=>$sSetting
                )
            );
        }else{
            $oSetting=PluginSetting::model()->find("plugin_id=:plugin_id AND model IS NULL AND ".Yii::app()->db->quoteColumnName("key")."=:ssetting",array(":plugin_id"=>$this->id,":ssetting"=>$sSetting));
        }
        if($oSetting && !is_null($oSetting->value))
        {
            return trim(stripslashes($oSetting->value),'"');
        }
        else
            return $default;
    }
    /**
     * @see parent:getPluginSettings
     */
    public function getPluginSettings($getValues=true)
    {
        if(!Yii::app() instanceof CConsoleApplication)
        {
            $this->settings['hostInfo']['default']= Yii::app()->request->getHostInfo();
            $this->settings['baseUrl']['default']= Yii::app()->request->getBaseUrl();
            $this->settings['scriptUrl']['default']= Yii::app()->request->getScriptUrl();
        }
        $pluginSettings= parent::getPluginSettings($getValues);
        if($getValues){
            if($pluginSettings['cronTypes']){
                $pluginSettings['cronTypes']['current']=implode("\n",explode("|",$pluginSettings['cronTypes']['current']));
            }
        }
        return $pluginSettings;
    }

    /**
     * Set args by argument parameters, can not use named options (broke command console, then use anonymous with fixed string)
     * @use $_SERVER
     * @return void
     */
    private function setArgs()
    {
        $args=isset($_SERVER['argv']) ? $_SERVER['argv']:array();
        foreach($args as $arg){
            if(substr($arg, 0, strlen("sendMailCronDebug="))=="sendMailCronDebug="){
                $this->debug=intval(substr($arg,strlen("sendMailCronDebug=")));
            }
            if(substr($arg, 0, strlen("sendMailCronSimulate="))=="sendMailCronSimulate="){
                $this->simulate=boolval(substr($arg,strlen("sendMailCronSimulate=")));
            }
            if(substr($arg, 0, strlen("sendMailCronDisable="))=="sendMailCronDisable="){
                $this->disable=boolval(substr($arg,strlen("sendMailCronDisable=")));
            }

            if(substr($arg, 0, strlen("sendMailCronType="))=="sendMailCronType="){
                $cronType=trim(substr($arg,strlen("sendMailCronType=")));
                $availCronTypes=$this->getSetting('cronTypes',null,null,"");
                $availCronTypes=explode('|', $availCronTypes);
                if($cronType && !in_array($cronType,$availCronTypes)){
                    $this->sendMailCronLog("invalid cronType : $cronType, plugin is disable",0);
                    $this->disable=true;
                } else {
                    $this->cronType=$cronType;
                }
            }
        }
    }

    /**
     * Validate a send action : dis we muist stop action
     * @param int[] $aCountMail array for email count
     * @param int $iSurvey survey id
     * @param string $sType
     * @return boolean
     *
     */
    private function stopSendMailAction($aCountMail,$iSurvey,$sType)
    {
        $maxThisType=$this->getSetting('maxSurveyBatchSize_'.$sType,'survey',$iSurvey,"");
        if( $maxThisType!=='' && $aCountMail['sent'] >= $maxThisType){
            $stillToSend=$aCountMail['total']*2-array_sum($aCountMail);
            $this->sendMailCronLog("Survey {$iSurvey}, {$sType} survey batch size achieved. {$stillToSend} email to send at next batch.",1);
            return true;
        }

        if($this->getSetting('maxBatchSize') && $this->getSetting('maxBatchSize') <= $this->currentBatchSize){
            $stillToSend=$aCountMail['total']*2-array_sum($aCountMail);
            $this->sendMailCronLog("Batch size achieved for {$iSurvey} during {$sType}. {$stillToSend} email to send at next batch.",1);
            $this->sendMailCronFinalLog($aCountMail);
            return true;
        }

        if($this->getSetting('maxSurveyBatchSize','Survey', $iSurvey) && $this->getSetting('maxSurveyBatchSize','Survey', $iSurvey) <= $this->currentSurveyBatchSize){
            $stillToSend=$aCountMail['total']*2-array_sum($aCountMail);
            $this->sendMailCronLog("Batch survey size achieved for {$iSurvey} during {$sType}. {$stillToSend} email to send at next batch.",1);
            $this->sendMailCronFinalLog($aCountMail);
            return true;
        }

        return false;
    }

    /**
     * Ending and return information according to sended after a send session
     * @param int[] $aCountMail array for email count
     * @return @void
     */
    private function sendMailCronFinalLog($aCountMail)
    {
        if((array_sum($aCountMail)-$aCountMail['total'])==0){
            $this->sendMailCronLog("No message to sent",1);
        }else{
            if($aCountMail['sent']){
                $this->sendMailCronLog("{$aCountMail['sent']} messages sent",1);
            }
            if($aCountMail['invalid']){
                $this->sendMailCronLog("{$aCountMail['invalid']} invalid email address",1);
            }
            if($aCountMail['started']){
                $this->sendMailCronLog("{$aCountMail['started']} already started survey",2);
            }
            if($aCountMail['notstarted']){
                $this->sendMailCronLog("{$aCountMail['started']} not started survey",2);
            }
            if($aCountMail['error']){
                $this->sendMailCronLog("{$aCountMail['error']} messages with unknow error",1);
            }
        }
    }

    /** Menu and settings part */
    /**
     * see beforeToolsMenuRender event
     *
     * @return void
     */

    public function beforeToolsMenuRender()
    {
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');

        $href = Yii::app()->createUrl(
            'admin/pluginhelper',
            array(
                'sa' => 'sidebody',
                'plugin' => get_class($this),
                'method' => 'actionSettings',
                'surveyId' => $surveyId
            )
        );

        $menuItem = new \ls\menu\MenuItem(
            array(
                'label' => $this->_translate('Mail cron'),
                'iconClass' => 'fa fa-envelope-square',
                'href' => $href
            )
        );
        $event->append('menuItems', array($menuItem));
    }

    /**
     * Main function
     * @param int $surveyId Survey id
     *
     * @return string
     */
    public function actionSettings($surveyId)
    {
        if(!Permission::model()->hasSurveyPermission($surveyId,'surveysettings','update')){
            throw new CHttpException(401);
        }
        if(App()->getRequest()->getPost('save'.get_class($this))) {
            $this->set('maxEmail', App()->getRequest()->getPost('maxEmail'), 'Survey', $surveyId);
            $this->set('delayInvitation', App()->getRequest()->getPost('delayInvitation'), 'Survey', $surveyId);
            $this->set('delayReminder', App()->getRequest()->getPost('delayReminder'), 'Survey', $surveyId);
            $this->set('maxSurveyBatchSize', App()->getRequest()->getPost('maxSurveyBatchSize'), 'Survey', $surveyId);
            $this->set('maxSurveyBatchSize_invite', App()->getRequest()->getPost('maxSurveyBatchSize_invite'), 'Survey', $surveyId);
            $this->set('maxSurveyBatchSize_remind', App()->getRequest()->getPost('maxSurveyBatchSize_remind'), 'Survey', $surveyId);
            $this->set('dayOfWeekToSend', App()->getRequest()->getPost('dayOfWeekToSend'), 'Survey', $surveyId);
            $this->set('cronTypes', App()->getRequest()->getPost('cronTypes'), 'Survey', $surveyId);
            if(App()->getRequest()->getPost('save'.get_class($this)=='redirect')) {
                Yii::app()->getController()->redirect(Yii::app()->getController()->createUrl('admin/survey',array('sa'=>'view','surveyid'=>$surveyId)));
            }
        }
        /* get the default settings */
        $defaultMaxEmail=$this->get('maxEmail', null,null,$this->settings['maxEmail']['default']);
        if(!$defaultMaxEmail){
            $defaultMaxEmail=$this->_translate("disabled");
        }
        $defaultDelayInvitation=$this->get('delayInvitation', null,null,$this->settings['delayInvitation']['default']);
        $defaultDelayReminder=$this->get('delayReminder', null,null,$this->settings['delayReminder']['default']);
        $maxBatchSize=$this->get('maxBatchSize', null,null,$this->settings['maxBatchSize']['default']);
        if(!$maxBatchSize){
            $maxBatchHelp=$this->_translate("Leave empty to send all available emails.");
        }else{
            $maxBatchHelp=sprintf($this->_translate("Leave empty to send all available emails,only limited by global batch size (%s) for all surveys."),$maxBatchSize);
        }
        $oSurvey=Survey::model()->findByPk($surveyId);

        $aData=array();
        /* An alert if survey don't have token */
        $aData['warningString'] = !Survey::model()->hasTokens($surveyId) ? "This survey didn't have token currently, this settings are not used until participant are not activated." : null;

        $aSettings=array();
        
        $aSettings[$this->_translate('Basic settings')]=array(
            'maxEmail' => array(
                'type'=>'int',
                'htmlOptions'=>array(
                    'min'=>0,
                ),
                'label'=>$this->_translate("Max email to send (invitation + remind) to each participant."),
                'help'=>sprintf($this->_translate("0 to deactivate sending of email, empty to use default (%s)"),$defaultMaxEmail),
                'current'=>$this->get('maxEmail', 'Survey', $surveyId,""),
            ),
            'delayInvitation' => array(
                'type'=>'int',
                'htmlOptions'=>array(
                    'min'=>1,
                ),
                'label'=>$this->_translate("Min delay between invitation and first reminder."),
                'help'=>sprintf($this->_translate("In days, empty for default (%s)"),$defaultDelayInvitation),
                'current'=>$this->get('delayInvitation', 'Survey', $surveyId,""),
            ),
            'delayReminder' => array(
                'type'=>'int',
                'htmlOptions'=>array(
                    'min'=>1,
                ),
                'label'=>$this->_translate("Min delay between reminders."),
                'help'=>sprintf($this->_translate("In days, empty for default (%s)"),$defaultDelayReminder),
                'current'=>$this->get('delayReminder', 'Survey', $surveyId,""),
            ),
        );
        $aSettings[$this->_translate('Batch size')]=array(
            'maxSurveyBatchSize'=>array(
                'type'=>'int',
                'htmlOptions'=>array(
                    'min'=>1,
                ),
                'label'=>$this->_translate("Max email to send (invitation + remind) in one batch for this survey."),
                'help'=>$maxBatchHelp,
                'current'=>$this->get('maxSurveyBatchSize', 'Survey', $surveyId,""),
            ),
            'maxSurveyBatchSize_invite'=>array(
                'type'=>'int',
                'htmlOptions'=>array(
                    'min'=>0,
                ),
                'label'=>$this->_translate("Max email to send for invitation in one batch for this survey."),
                'help'=>$this->_translate("The max email setting can not be exceeded."),
                'current'=>$this->get('maxSurveyBatchSize_invite', 'Survey', $surveyId,""),
            ),
            'maxSurveyBatchSize_remind'=>array(
                'type'=>'int',
                'htmlOptions'=>array(
                    'min'=>0,
                ),
                'label'=>$this->_translate("Max email to send for reminder in one batch for this survey."),
                'help'=>$this->_translate("The max email setting can not be exceeded. Reminders are sent after invitation."),
                'current'=>$this->get('maxSurveyBatchSize_remind', 'Survey', $surveyId,""),
            ),
        );
        $whenSettings=array(
            'dayOfWeekToSend'=>array(
                'type'=>'select',
                'htmlOptions'=>array(
                    'multiple'=>'multiple',
                    'empty'=>$this->_translate("All week days"),
                ),
                'selectOptions'=>array(
                    'placeholder' =>$this->_translate("All week days"),
                    'allowClear'=> true,
                    'width'=>'100%',
                ),
                'controlOptions'=>array(
                    'class'=>'search-100',
                ),
                'label'=>$this->_translate("Day of week for sending email"),
                'options'=>array(
                    1=>$this->_translate("Monday"),
                    2=>$this->_translate("Thursday"),
                    3=>$this->_translate("Wednesday"),
                    4=>$this->_translate("Thuesday"),
                    5=>$this->_translate("Friday"),
                    6=>$this->_translate("Saturday"),
                    7=>$this->_translate("Sunday"),
                ),
                'current'=>$this->get('dayOfWeekToSend', 'Survey', $surveyId,array()),
            ),
        );
        if($this->get('cronTypes', null, null)){
            $availCronTypes=$this->get('cronTypes',null,null,$this->settings['cronTypes']['default']);
            $availCronTypes=explode('|', $availCronTypes);
            $cronTypesOtions=array();
            foreach($availCronTypes as $cronType){
                $cronTypesOtions[$cronType]=$cronType;
            }
            if(!empty($cronTypesOtions)) {
                $emptyLabel=$this->get('cronTypeNone',null,null,$this->settings['cronTypeNone']['default']) ? $this->_translate("Only if no moment is set") : $this->_translate("At all moment");
                $whenSettings['cronTypes']=array(
                    'type'=>'select',
                    'htmlOptions'=>array(
                        'multiple'=>'multiple',
                        'empty'=>$emptyLabel,
                    ),
                    'selectOptions'=>array(
                        'placeholder' =>$emptyLabel,
                        'allowClear'=> true,
                        'width'=>'100%',
                    ),
                    'controlOptions'=>array(
                        'class'=>'search-100',
                    ),
                    'label'=>$this->_translate("Moment for emailing"),
                    'options'=>$cronTypesOtions,
                    'current'=>$this->get('cronTypes', 'Survey', $surveyId,array()),
                );
            }
        }
        $aSettings[$this->_translate('When email must be sent')]=$whenSettings;
        $aData['pluginClass']=get_class($this);
        $aData['surveyId']=$surveyId;
        $aData['title']=$this->_translate("Send email by cron settings");
        $aData['aSettings']=$aSettings;
        $aData['assetUrl']=Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/');
        $content = $this->renderPartial('settings', $aData, true);
        return $content;
    }
    /**
     * Fix LimeSurvey command function
     * @todo : find way to control API
     * OR if another plugin already fix it
     */
    private function _LsCommandFix()
    {
        /* Bad autoloading in command */
        include_once(dirname(__FILE__)."/DbStorage.php");
        // These take care of dynamically creating a class for each token / response table.
        Yii::import('application.helpers.ClassFactory');
        ClassFactory::registerClass('Token_', 'Token');
        ClassFactory::registerClass('Response_', 'Response');
    }
    /**
     * get translation
     * @param string
     * @return string
     */
    private function _translate($string){
        return Yii::t('',$string,array(),get_class($this));
    }
    /**
     * Add this translation just after loaded all plugins
     * @see event afterPluginLoad
     */
    public function afterPluginLoad(){
        // messageSource for this plugin:
        $messageSource=array(
            'class' => 'CGettextMessageSource',
            'cacheID' => get_class($this).'Lang',
            'cachingDuration'=>3600,
            'forceTranslation' => true,
            'useMoFile' => true,
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR.'locale',
            'catalog'=>'messages',// default from Yii
        );
        Yii::app()->setComponent(get_class($this),$messageSource);
    }

}
