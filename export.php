<?php
/**
 *
* @package local
* @subpackage encuestascdc
* @copyright 2017-onwards Universidad Adolfo Ibanez
* @author Jorge Villalon <jorge.villalon@uai.cl>
*/
require_once (dirname (dirname ( dirname ( __FILE__ ) ) ). '/config.php');
require_once ($CFG->libdir . '/adminlib.php');
require_once ($CFG->libdir . '/moodlelib.php');
require_once ('locallib.php');

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
	qt.type
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
    qt.type
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = ? and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON ($responseonclause)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
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
    qt.type
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = ? and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON ($responseonclause)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
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


$records = $DB->get_recordset_sql($sql,$params);

$headers = array(
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


foreach($records as $record){
	$answers = explode('#',$record->answers);
	foreach($answers as $answer){

	    if(strpos($record->type,'Box')){
	        $textrecord = $DB->get_record('questionnaire_response_text',array('id' => $answer));
	        $answerdata = $textrecord->response;
	    } else {
	        $answerdata = $answer;
	    }

		$data[] = array(
			'Fecha-placeholder',
			'Categoría-placeholder',
			$record->courseid.' (Courseid)',
			$record->fullname,
			$record->seccion,
			$record->opcion,
			$answerdata,
			'Programa-placeholder',
			'Profesor-placeholder',
			'Coordinadora-placeholder',

			);
	}
}

$table = new html_table();
        $table->head = $headers;
        $table->title = 'Exportación';
        $table->id = 'export';
        $table->data = $data;

        echo html_writer::table($table);


echo $OUTPUT->footer ();
