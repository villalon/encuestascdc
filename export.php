<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
* @package local
* @subpackage encuestascdc
* @copyright 2020-onwards Universidad Adolfo Ibanez
* @author Ernesto Jaramillo <ernesto.jaramillo@uai.cl>
*/
require_once (dirname (dirname ( dirname ( __FILE__ ) ) ). '/config.php');
require_once ($CFG->libdir . '/adminlib.php');
require_once ($CFG->libdir . '/moodlelib.php');
require_once ('locallib.php');
require_once ('forms/export_form.php');

// Contexto página principal
$frontpagecontext = context_course::instance(SITEID);
// Editar la página principal solo lo pueden hacer gestores y administradores (esto permite filtrar a gestores)
// require_capability('moodle/course:update', $frontpagecontext);
// Contexto de sistema
$context = context_system::instance ();
ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');

// Page navigation and URL settings.
$PAGE->set_url ( $CFG->wwwroot . '/local/encuestascdc/export.php' );
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( 'admin' );
$PAGE->set_title ('Exportación de información de encuestas');
// Require jquery for modal.
$PAGE->requires->jquery ();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );

// The page header and heading
echo $OUTPUT->header ();
echo $OUTPUT->heading ('Exportaión de información de encuestas');

$catform = new local_encuestascdc_export_form();
$formdata = $catform->get_data();

$catform->display();

if(!$formdata) {
    echo $OUTPUT->footer();
    die();
}

$groupid = 0;

$questionnaires = $DB->get_records('questionnaire');

    // Validación de instalación del módulo questionnaire
    if(!$module = $DB->get_record('modules', array('name'=>'questionnaire'))) {
        print_error('Módulo questionnaire no está instalado');
    }

    // Validación de tipo de respuesta rank
    if(!$questiontype = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_rank'))) {
    	print_error('Tipo de pregunta rank no instalada');
    }

    // Validación de tipo de respuesta texto
    if(!$questiontypetext = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_text', 'type'=>'Text Box'))) {
        print_error('Tipo de pregunta Text Box no instalada');
    }

    if(!$questiontypeessay = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_text', 'type'=>'Essay Box'))) {
        print_error('Tipo de pregunta Essay Box no instalada');
    }

    $pluginversion = intval(get_config('mod_questionnaire', 'version'));
    $rankfield = 'value'; // $pluginversion > 2018050109 ? '' : 'value';
    $surveyfield = 'surveyid'; // $pluginversion > 2018050109 ? 'survey_id' : 'surveyid';
    $responseonclause =  'r.questionnaireid = qu.id'; //  $pluginversion > 2018050109 ? 'r.survey_id = s.id' : 'r.questionnaireid = qu.id';
    $groupsql = $groupid > 0 ? "LEFT JOIN {groups_members} gm ON (gm.groupid = :groupid AND gm.userid = r.userid)
WHERE gm.groupid is not null" : "";
    $groupsql2 = $groupid > 0 ? "LEFT JOIN {groups_members} gm ON (gm.groupid = :groupid2 AND gm.userid = r.userid)
WHERE gm.groupid is not null" : "";

    $questionnairesql = array();
    foreach($questionnaires as $questionnaire) {
        $questionnairesql[] = $questionnaire->id;
    }

    list($insql, $inparams) = $DB->get_in_or_equal($questionnairesql);
// Query para respuestas
    $sql="
SELECT qu.id,
    c.id courseid,
	c.fullname,
	s.id surveyid,
	s.title nombre,
	q.name seccion,
	q.content pregunta,
	qc.content opcion,
	q.length,
	group_concat(rr.rank$rankfield order by r.userid separator '#') answers,
	group_concat(r.userid order by r.userid separator '#') respondents,
    q.position,
	qt.type,
	group_concat(ue.timeend order by ue.userid separator '#') timeends,
	group_concat(ue.timestart order by ue.userid separator '#') timestarts,
	c.category
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = ? and q.deleted = 'n')
	INNER JOIN {questionnaire_quest_choice} qc ON (qc.question_id = q.id and q.type_id = ?)
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
	LEFT JOIN {questionnaire_response} r ON ($responseonclause)
	LEFT JOIN {questionnaire_response_rank} rr ON (rr.choice_id = qc.id and rr.question_id = q.id and rr.response_id = r.id)
    INNER JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'manual')
    INNER JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = r.userid)
    $groupsql
GROUP BY qu.id,c.id,s.id, q.id, qc.id
UNION ALL
SELECT qu.id,
    c.id courseid,
	c.fullname,
	s.id surveyid,
	s.title nombre,
	q.name seccion,
	q.content pregunta,
    '' opcion,
    '' length,
    group_concat(rt.id order by r.userid separator '#') answers,
    group_concat(r.userid order by r.userid separator '#') respondents,
    q.position,
    qt.type,
	group_concat(ue.timeend order by ue.userid separator '#') timeends,
	group_concat(ue.timestart order by ue.userid separator '#') timestarts,
	c.category
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = ? and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON ($responseonclause)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
    INNER JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'manual')
    INNER JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = r.userid)    
    $groupsql2
GROUP BY qu.id,c.id,s.id, q.id
UNION ALL
SELECT qu.id,
    c.id courseid,
	c.fullname,
	s.id surveyid,
	s.title nombre,
	q.name seccion,
	q.content pregunta,
    '' opcion,
    '' length,
    group_concat(rt.id order by r.userid separator '#') answers,
    group_concat(r.userid order by r.userid separator '#') respondents,
    q.position,
    qt.type,
	group_concat(ue.timeend order by ue.userid separator '#') timeends,
	group_concat(ue.timestart order by ue.userid separator '#') timestarts,	
	c.category
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = ? and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON ($responseonclause)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
    INNER JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'manual')
    INNER JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = r.userid)   
    $groupsql2
GROUP BY qu.id,c.id,s.id, q.id
ORDER BY position";

    $params = $inparams;
    $params[] = $module->id;
    $params[] = $questiontype->typeid;
    $params[] = $questiontype->typeid;
    for($i=0;$i<count($inparams);$i++) {
        $params[] = $inparams[$i];
    }
    $params[] = $module->id;
    $params[] = $questiontypetext->typeid;

    if($groupid > 0) {
        $params['groupid'] = $groupid;
        $params['groupid2'] = $groupid;
    }
    for($i=0;$i<count($inparams);$i++) {
        $params[] = $inparams[$i];
    }
    $params[] = $module->id;
    $params[] = $questiontypeessay->typeid;

    if($groupid > 0) {
        $params['groupid'] = $groupid;
        $params['groupid2'] = $groupid;
    }
// Obtenemos los datos de la query
$records = $DB->get_recordset_sql($sql,$params);

// Construimos el header para la tabla
$headers = array(
    'Fecha inicio',
	'Fecha cierre',
	'Categoría',
	'Curso',
	'Encuesta',
	'Categoría encuesta',
	'Pregunta',
	'Respuesta',
	'Programa',
	'Profesor',
	'Coordinadora'
	);

$data = array();

// Buscamos cuales son los IDs de los roles
$editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
$managerrole = $DB->get_record('role', array('shortname' => 'manager'));

// Creamos string para nombrar a profesores/as
$editingteachersstring = '';
$managersstring = '';

$countrows = 0;
// Revisamos y creamos variables para los distintos filtros

// Primero, de los cursos de la categoría
$coursecategory = core_course_category::get($formdata->id);
if(!$coursecategory) {
    print_error('Categoría no existe o usuario no tiene permisos ' . $id);
}
$coursecategory->resort_courses("fullname");
$courses = $coursecategory->get_courses(array('recursive'=>true));
// Luego, las fechas
list($fromstart, $tostart) = encuestascdc_obtiene_fechas_periodo($formdata->start, $formdata->fromstart, $formdata->tostart);
list($fromend, $toend) = encuestascdc_obtiene_fechas_periodo($formdata->end, $formdata->fromend, $formdata->toend);

// Recorremos el arreglo con toda la data obtenida de la query de la info a exportar
foreach($records as $record){
    if(!array_key_exists($record->courseid, $courses)) {
        continue;
    }
    
    if(!$coursecat = $DB->get_record('course_categories', array('id'=>$record->category))) {
        print_error("Curso con categoría inválida");
    }
    
    $context = context_course::instance($record->courseid);
    
    $teachers = get_role_users($editingteacherrole->id, $context);
	$teachersarray = array();
	
	foreach($teachers as $teacher) {
	    $teachersarray[] = $teacher->firstname . ' ' . $teacher->lastname;
	}
	
	$editingteachersstring = implode(', ',$teachersarray);
	
	$managers = get_role_users($managerrole->id, $context);
	$managersarray = array();
	
	foreach($managers as $manager) {
	    $managersarray[] = $manager->firstname . ' ' . $manager->lastname;
	}
	
	$managersstring = implode(', ',$managersarray);
	  
	$answers = explode('#',$record->answers);
	$timeends = explode('#', $record->timeends);
	$timestarts = explode('#', $record->timestarts);
	// Índice para poder  saber en cual usuario estamos, y así homologar data
	// TODO: Cambiar lo del índice a algo más elegante.
	$index = 0;
	foreach($answers as $answer){
        
	    if(strpos($record->type,'Box')){
	        $textrecord = $DB->get_record('questionnaire_response_text',array('id' => $answer));
	        $answerdata = $textrecord->response;
	    } else {
	        $answerdata = $answer;
	    }
        // Filtrar si debiese mostrar registro dependiendo de los filtros de fecha
        if($fromstart > 0) {
            if($fromstart > $timestarts[$index] || $timestarts[$index] > $tostart  ) {
                continue;
            }
        }        
        
        if($fromend > 0) {
            if($toend < $timeends[$index] || $timeends[$index] < $fromend) {
                continue;
            }
        }
        if($timeends[$index] == 0) {
            $timeend = 'N/A';
        } else {
            $timeend = date("d-m-Y", $timeends[0]);
        }
        
        if($timestarts[$index] == 0) {
            $timestart = 'N/A';
        } else {
            $timestart = date("d-m-Y", $timestarts[0]);
        }
        
		$data[] = array(
		    $timestart,
			$timeend,
			$coursecat->name,
			$record->fullname,
			$record->fullname,
			$record->seccion,
			$record->opcion,
			$answerdata,
			$coursecat->name,
			$editingteachersstring,
			$managersstring
			);
		$countrows += count($data);
	}
}

$table = new html_table();
        $table->head = $headers;
        $table->title = 'Exportación';
        $table->id = 'export';
        $table->data = $data;
        
echo '<input type="button" class="btn btn-primary" style="float:right;" id="exportarexcel" name="excel" value="Exportar a Excel" onClick="exportarExcel();">';
echo html_writer::table($table);
echo '<input type="button" class="btn btn-primary" style="float:right;" id="exportarexcel2" name="excel" value="Exportar a Excel" onClick="exportarExcel();">';


echo "
<script lang='javascript' src='dist/xlsx.full.min.js'></script>
<script>
function exportarExcel() {
	/* create new workbook */
	var workbook = XLSX.utils.book_new();
    var table = document.getElementById('export');
	var ws = XLSX.utils.table_to_sheet(table, {raw:true});
    XLSX.utils.book_append_sheet(workbook, ws, '$table->title');
	if( $countrows == 0) {
		alert('No se encontraron datos');
		return;
	}
	var f = new Date();
    var date = f.getHours() + ':' + f.getMinutes + ':' + f.getSeconds + ' ' + f.getDate() + '-' + f.getMonth() + '-' + f.getFullYear();
	return XLSX.writeFile(workbook, 'export_' + date + '.xlsx');
}
</script>
";


echo $OUTPUT->footer ();
