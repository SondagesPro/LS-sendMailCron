<?php
/**
 * A class for language
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.0.1
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

class sendMailCronTranslate
{
    /**
     * @var array[] translation text : 1st key is string, second key is lang
     */
    private $aTranslation=array(
        'fr'=>array(
            "Max email to send (invitation + remind) to each particpant."=>"Nombre de messages maximum à envoyer à chaque participant.",
            "0 to deactivate sending of email, empty to use default"=>"0 pour désactiver l'envoi automatique de message, vide pour la valeur par défaut",
            "Min delay between invitation and first reminder."=>"Délais minimum entre l'invitation et la première relance",
            "Empty for default"=>"Vide pour utiliser la veur par défaut",
            "Min delay between reminders."=>"Délais minimum entre les relances",
            "Max email to send (invitation + remind) in one batch for this survey."=>"Nombre maximum de messages à envoyer à chaque passage",
            "Leave empty to use only global batch size. In any condition, global batch size is take in account"=>"Laisser vide pour n'utiliser que la paramètre global, celui-ci est toujours pris en compte",
            "Max email to send for invitation in one batch for this survey."=>"Nombre maximum d'invitation à envoyer à chaque passage",
            "The max email setting is always taken into account"=>"Le nombre maximum d'envoi est toujours pris en compte",
            "Max email to send for reminder in one batch for this survey."=>"Nombre maximum de relance à envoyer à chaque passage",
            "The max email setting is always taken into account, reminders are sent after all new invitation"=>"Le nombre maximum d'envoi est toujours pris en compte, les relances sont envoyées après les invitations",
            "Day of week for sending email"=>"Jours de la semaine pour l'envoi des message",
            "All week days"=>"Tous les jours de la semaine",
            "Monday"=>"Lundi",
            "Thursday"=>"Mardi",
            "Wednesday"=>"Mercredi",
            "Thuesday"=>"Jeudi",
            "Friday"=>"Vendredi",
            "Saturday"=>"Samedi",
            "Sunday"=>"Dimanche",
            "Send reminders to "=>"Envoi des relance à ",
            "all participants"=>"Tous les participants",
            "participants who did not started survey."=>"aux particpants qui n'ont pas démarré le questionaire",
            "participants who started survey."=>"aux participants qui ont démarré le questionnaire",
        ),
    );
    /**
     * Quick translate function
     */
    public function gT($string)
    {
        if(isset($this->aTranslation[App()->language][$string])){
            return $this->aTranslation[App()->language][$string];
        }
        return gT($string);
    }
}
