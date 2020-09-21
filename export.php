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
// Page navigation and URL settings.
$PAGE->set_url ( $CFG->wwwroot . '/local/encuestascdc/export.php' );
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( 'admin' );
$PAGE->set_title ('Exportación de información de encuestas');

// Require jquery for modal.
$PAGE->requires->jquery ();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );

// Parámetros necesarios para procesar datos
$categoryid = optional_param('id', 0, PARAM_RAW);

require_login();

require_capability('mod/questionnaire:manage', $context);

// The page header and heading
echo $OUTPUT->header ();
echo $OUTPUT->heading ('Exportación de información de encuestas');

// Se construye formulario
$catform = new local_encuestascdc_export_form(null,array('categoryid'=>$categoryid));
$formdata = $catform->get_data();

$catform->display();

// Mostrar sólo el formulario en primer ingreso a página
if(!$formdata) {
    echo $OUTPUT->footer();
    die();
}

// Variables necesarias para filtro por curso
$courseids = array();
if(isset($formdata->courseids)) {
    $courseids = $formdata->courseids;
}

// Variables necesarias para filtro por Profesores (con permisos de edición)
$teacherids = array();
if(isset($formdata->teacherids)) {
    $teacherids = $formdata->teacherids;
}

// Variables necesarias para filtro por coordinadores
$managerids = array();
if(isset($formdata->teacherids)) {
    $managerids = $formdata->managerids;
}

$sections = array();
if(isset($formdata->sections)) {
    $sections = $formdata->sections;
}
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
    
    // Validación de tipo de respuesta texto largo
    if(!$questiontypeessay = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_text', 'type'=>'Essay Box'))) {
        print_error('Tipo de pregunta Essay Box no instalada');
    }

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
	group_concat(rr.rankvalue order by r.userid separator '#') answers,
	group_concat(r.userid order by r.userid separator '#') respondents,
    q.position,
	qt.type,
	group_concat(ue.timeend order by ue.userid separator '#') timeends,
	group_concat(ue.timestart order by ue.userid separator '#') timestarts,
	c.category,
	qs.name surveyname
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	LEFT JOIN {questionnaire} qs ON (qs.sid = s.id)
	INNER JOIN {questionnaire_question} q ON (q.surveyid = s.id and q.type_id = ? and q.deleted = 'n')
	INNER JOIN {questionnaire_quest_choice} qc ON (qc.question_id = q.id and q.type_id = ?)
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
	LEFT JOIN {questionnaire_response} r ON (r.questionnaireid = qu.id)
	LEFT JOIN {questionnaire_response_rank} rr ON (rr.choice_id = qc.id and rr.question_id = q.id and rr.response_id = r.id)
    INNER JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'manual')
    INNER JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = r.userid)
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
	c.category,
	qs.name surveyname
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	LEFT JOIN {questionnaire} qs ON (qs.sid = s.id)
	INNER JOIN {questionnaire_question} q ON (q.surveyid = s.id and q.type_id = ? and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON (r.questionnaireid = qu.id)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
    INNER JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'manual')
    INNER JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = r.userid)    
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
	c.category,
	qs.name surveyname
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	LEFT JOIN {questionnaire} qs ON (qs.sid = s.id)
	INNER JOIN {questionnaire_question} q ON (q.surveyid = s.id and q.type_id = ? and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON (r.questionnaireid = qu.id)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
    INNER JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'manual')
    INNER JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = r.userid)   
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

    for($i=0;$i<count($inparams);$i++) {
        $params[] = $inparams[$i];
    }
    $params[] = $module->id;
    $params[] = $questiontypeessay->typeid;

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
foreach($records as $record) {
    // Filtro por cursos
    if(!array_key_exists($record->courseid, $courses)) {
        continue;
    }
    if(count($courseids) > 0 && !in_array($record->courseid,$courseids)){
        continue;
    }
    
    // Si no existe la categoría, se reporta error
    if(!$coursecat = $DB->get_record('course_categories', array('id'=>$record->category))) {
        print_error("Curso con categoría inválida");
    }
    
	// Filtro por secciones
	if(!in_array($record->seccion, $sections) && count($sections)>0) {
	    continue;
	}
    
    $context = context_course::instance($record->courseid);
    
	// Manejo editing teachers
    $teachers = get_role_users($editingteacherrole->id, $context);
	$teachersarray = array();
	
	foreach($teachers as $teacher) {
	    $teachersarray[$teacher->id] = $teacher->firstname . ' ' . $teacher->lastname;
	}
	
	// Filtro por editing teachers
	$teacherids = array_flip($teacherids);
    $intersectedteachers = array_intersect_key($teachersarray,$teacherids);
	if(count($intersectedteachers) == 0 && count($teacherids) > 0) {
	    continue;
	}

	$editingteachersstring = implode(', ',$teachersarray);
	
	// Manejo managers
	$managers = get_role_users($managerrole->id, $context);
	$managersarray = array();
	
	foreach($managers as $manager) {
	    $managersarray[$manager->id] = $manager->firstname . ' ' . $manager->lastname;
	}

    // Filtro por managers
	$managerids = array_flip($managerids);
    $intersectedmanagers = array_intersect_key($managersarray,$managerids);
	if(count($intersectedmanagers) == 0 && count($managerids) > 0) {
	    continue;
	}

	$managersstring = implode(', ', $managersarray);

    // Separamos data por usuario	  
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
        
        // Filtrar si debiese mostrar registro dependiendo de los filtros de fecha de inicio
        if($fromstart > 0) {
            if($fromstart > $timestarts[$index] || $timestarts[$index] > $tostart  ) {
                continue;
            }
        }        
        
        // Filtrar si debiese mostrar registro dependiendo de los filtros de fecha de término
        if($fromend > 0) {
            if($toend < $timeends[$index] || $timeends[$index] < $fromend) {
                continue;
            }
        }
        
        // Formato de fecha término
        if($timeends[$index] == 0) {
            $timeend = 'N/A';
        } else {
            $timeend = date("d-m-Y", $timeends[0]);
        }
        
        // Formato de fecha de inicio
        if($timestarts[$index] == 0) {
            $timestart = 'N/A';
        } else {
            $timestart = date("d-m-Y", $timestarts[0]);
        }
        
        // Agregamos data a la tabla
		$data[] = array(
		    $timestart,
			$timeend,
			$coursecat->name,
			$record->fullname,
			$record->surveyname,
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

// Construimos tabla
$table = new html_table();
        $table->head = $headers;
        $table->title = 'Exportación';
        $table->id = 'export';
        $table->data = $data;

// Dependiendo de que si existen datos o no, mostramos tabla o mensaje
if($countrows > 0) {

    echo '<input type="button" class="btn btn-primary" style="float:right;" id="exportarexcel1" name="excel" value="Exportar a Excel" onClick="exportarExcel();">';
    echo html_writer::table($table);
    echo '<input type="button" class="btn btn-primary" style="float:right;" id="exportarexcel2" name="excel" value="Exportar a Excel" onClick="exportarExcel();">';
} else {
    echo $OUTPUT->heading ('No se encontraron registros.',5);
}


// Script de exportación a Excel
echo '<script lang="javascript" src="dist/xlsx.full.min.js"></script>';
echo '
<script>

function exportarExcel() {
	/* create new workbook */
	var workbook = XLSX.utils.book_new();
    var table = document.getElementById("'.$table->id.'");
	var ws = XLSX.utils.table_to_sheet(table, {raw:true});
    XLSX.utils.book_append_sheet(workbook, ws, "'.$table->title.'");
	if( '.$countrows.' == 0) {
		alert("No se encontraron datos");
		return;
	}
	var f = new Date();
    var date = "exportacion_" + f.getHours() + ":" + f.getMinutes + ":" + f.getSeconds + "_" + f.getDate() + "-" + f.getMonth() + "-" + f.getFullYear();
	return XLSX.writeFile(workbook, "export_" + date + ".xlsx");
}


</script>
';

echo $OUTPUT->footer ();
