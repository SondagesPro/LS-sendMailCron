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
            "disabled"=>"désactivé",
            "no limit"=>"non limité",
            "Max email to send (invitation + remind) to each participant."=>"Nombre de messages maximum à envoyer à chaque participant.",
            "0 to deactivate sending of email, empty to use default (%s)"=>"0 pour désactiver l'envoi automatique de message, vide pour la valeur par défaut (%s)",
            "Min delay between invitation and first reminder."=>"Délai minimum entre l'invitation et la première relance",
            "Min delay between reminders."=>"Délai minimum entre les relances",
            "In days, empty for default (%s)"=>"En jour, vide pour utiliser la valeur par défaut (%s)",
            "Max email to send (invitation + remind) in one batch for this survey."=>"Nombre maximum de messages à envoyer à chaque exécution",
            "Leave empty to send all available emails."=>"Laisser vide pour envoyer tous les messages possibles",
            "Leave empty to send all available emails,only limited by global batch size (%s) for all surveys."=>"Laisser vide pour envoyer tous les messages possibles, seulement limité par le paramètre global (%s) pour tous les questionnaires.",
            "Max email to send for invitation in one batch for this survey."=>"Nombre maximum d'invitation à envoyer à chaque exécution",
            "The max email setting can not be exceeded."=>"Le nombre maximum d'envoi ne pourra pas être dépassé",
            "Max email to send for reminder in one batch for this survey."=>"Nombre maximum de relance à envoyer à chaque exécution",
            "The max email setting can not be exceeded. Reminders are sent after invitation, using the remainder of sends available."=>"Le nombre maximum d'envoi est toujours pris en compte, les relances sont envoyées après les invitations en utilisant le reste des envois disponibles.",
            "Day of week for sending email"=>"Jours de la semaine pour l'envoi des message",
            "All week days"=>"Tous les jours de la semaine",
            "Monday"=>"Lundi",
            "Thursday"=>"Mardi",
            "Wednesday"=>"Mercredi",
            "Thuesday"=>"Jeudi",
            "Friday"=>"Vendredi",
            "Saturday"=>"Samedi",
            "Sunday"=>"Dimanche",
            "Send reminders to "=>"Envoi des relances à ",
            "all participants"=>"Tous les participants",
            "participants who did not started survey."=>"aux participants qui n'ont pas démarré le questionaire",
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
