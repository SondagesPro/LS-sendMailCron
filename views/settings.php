<div class="row">
    <div class="col-lg-12 content-right">
        <?php echo CHtml::beginForm();?>
        <h3 class="clearfix"><?php echo $title ?>
        <div class='pull-right'>
          <?php
              echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
              echo " ";
              echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-default'));
              echo " ";
              echo CHtml::link(gT('Close'),Yii::app()->createUrl('admin/survey',array('sa'=>'view','surveyid'=>$surveyId)),array('class'=>'btn btn-danger'));
          ?>
        </div>
        </h3>
        <?php if($warningString) {
            echo CHtml::tag("p",array('class'=>'alert alert-warning'),$warningString);
        } ?>
        <?php foreach($aSettings as $legend=>$settings) {
          $this->widget('ext.SettingsWidget.SettingsWidget', array(
                //'id'=>'summary',
                'title'=>$legend,
                //'prefix' => $pluginClass, This break the label (id!=name)
                'form' => false,
                'formHtmlOptions'=>array(
                    'class'=>'form-core',
                ),
                'labelWidth'=>6,
                'controlWidth'=>6,
                'settings' => $settings,
            ));
        } ?>
        <div class='row'>
          <div class='col-md-offset-6 submit-buttons'>
            <?php
              echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
              echo " ";
              echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-default'));
              echo " ";
              echo CHtml::link(gT('Close'),Yii::app()->createUrl('admin/survey',array('sa'=>'view','surveyid'=>$surveyId)),array('class'=>'btn btn-danger'));
            ?>
          </div>
        </div>
        <?php echo CHtml::endForm();?>
    </div>
</div>
<?php
  Yii:app()->clientScript->registerScriptFile($assetUrl.'/settings.js',CClientScript::POS_END);
?>
