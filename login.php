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
require_once ('forms/login_form.php');
require_once ('../../mod/questionnaire/lib.php');
require_once ('../../mod/questionnaire/locallib.php');

$context = context_system::instance ();

$qid = optional_param ( 'qid', 0, PARAM_INT );

$url = new moodle_url('/local/encuestascdc/login.php');

// Page navigation and URL settings.
$PAGE->set_url ($url);
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( 'print' );
$PAGE->set_title ( get_string ( 'login', 'local_encuestascdc' ) );
// Require jquery for modal.
$PAGE->requires->jquery ();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );

$mform = new local_encuestascdc_login_form (NULL);

$loginerror = false;
if ($mform->get_data ()) {
	// Datos del formulario
    $tipo = $mform->get_data()->tipo;
    $username = isset($mform->get_data()->username) ? $mform->get_data()->username : '';
    $idnumber = isset($mform->get_data()->idnumber) ? $mform->get_data()->idnumber : '';
    $pwd = $mform->get_data()->pwd;

	// Reemplazar puntos en el RUT
    $idnumber = strlen($idnumber) > 0 ? str_replace(".", "", $idnumber) : '';
	$idnumbersindigito = strpos($idnumber, '-') > 0 ? intval(explode("-", $idnumber)[0]) : $idnumber;
	
	$user = NULL;
	if($tipo == 2) {
		$params = array ('username' => $username, 'deleted' => 0);
	} else if($tipo == 1) {
		$params = array ('idnumber' => $idnumber, 'deleted' => 0);
	}
	$users = $DB->get_records ('user', $params);
	if(count($users) > 1) {
		$loginerror = 'Hay más de un estudiante con ese RUT o pasaporte. Contacte a su coordinadora.';
	} else {
		foreach($users as $u) {
			$user = $u;
		}
	}

	// Primero verificamos que el usuario exista
	if(!$user) {
	    $loginerror = $loginerror ? $loginerror : 'Estudiante no encontrado';
	} else if($user->suspended == 1) {
	    $loginerror = 'Estudiante suspendido';
	} else {
		// Si el usuario existe, autenticamos con la clave provista
		$result = authenticate_user_login ($user->username, $pwd);
		// Si no funciona su clave, se busca una clave temporal habilitada para contestar encuestas
		if(!$result) {
			$now = time();
			$passwords = $DB->get_records_sql("SELECT * FROM {encuestascdc_passwords} ep WHERE timecreated < :now AND timecreated + (duration * 60) > :now2 AND status = 0 AND password = :password",
				array('now'=>$now, 'now2'=>$now, 'password'=>$pwd));
			if(!$passwords) {
				$loginerror = 'Contraseña incorrecta';
			}
		}
		if(!$loginerror) {
			complete_user_login($user);
		}
	}
}

if($qid > 0 && isloggedin()) {
	if(!$cm = $DB->get_record('course_modules', array('id'=>$qid))) {
		print_error('Módulo de curso inválido');
	}
	if(!$course = $DB->get_record('course', array('id'=>$cm->course)))	{
		print_error('Curso inválido');
	}
	if(!$coursecat = $DB->get_record('course_categories', array('id'=>$course->category))) {
		print_error('Categoría de cursos inválida');
	}
	$context = context_module::instance($cm->id);
	if(!has_capability('mod/questionnaire:submit', $context, $USER)) {
		print_error('Estudiante no tiene permisos para contestar encuesta');
	}
	$url = new moodle_url('/mod/questionnaire/complete.php', array('id'=>$qid));
	redirect($url);
	die();
}

// The page header and heading
echo $OUTPUT->header ();
echo html_writer::img($CFG->wwwroot.'/local/encuestascdc/img/logo-uai-corporate-no-transparente2.png', 'UAI Corporate', array('class'=>'img-fluid'));
echo $OUTPUT->heading ('Resumen encuestas');


echo "
<style>
.questionnaire {
    border: 1px solid #999;
    border-radius: 5px;
    margin-bottom: 2em;
    padding: 1em;
}
.status, .status .singlebutton, .status button, input {
    width: 100%;
    text-align: center;
	border-radius: 3px;
    margin-top: 6px;
}
.status button {
	background-color: #ff4c00;
	color: #fff;
	font-weight: bold;
	font-size: 1.5em;
	border: 0px;
}
.status i {
	font-size: 1.5em;
}
img {
	margin-bottom: 5px;
}
.status-contestada, .status-cerrada {
    font-size: 1.5em;
    font-weight: bold;
    padding: 5px !important;
}
</style>";

if(!isloggedin()) {
	if($loginerror) {
		echo $OUTPUT->notification($loginerror, 'notifyproblem');
	}
	$mform->display ();
	echo $OUTPUT->footer ();
	die();
}

echo $OUTPUT->heading ($USER->firstname . ' ' . $USER->lastname, 4);
$html = array();
$courses = enrol_get_users_courses($USER->id);
if(!$courses) {
    echo $OUTPUT->notification('Estudiante no tiene cursos', 'notify-error');
    echo $OUTPUT->single_button($url, 'Volver');
} else {
	$questionnaires = get_all_instances_in_courses('questionnaire', $courses, $USER->id, true);
	if(!$questionnaires) {
	    echo $OUTPUT->notification('Estudiante no tiene encuestas en sus cursos', 'notify-error');
	    echo $OUTPUT->single_button($url, 'Volver');
	} else {
	    $htmlquestionnaires = array();
		$teacherrole = $DB->get_record_sql('SELECT * FROM {role} WHERE archetype = :archetype ORDER BY id ASC LIMIT 1', array('archetype'=>'editingteacher'));
		foreach($questionnaires as $questionnaire) {
		    $url = new moodle_url("/local/encuestascdc/login.php", array("qid"=>$questionnaire->coursemodule, "uid"=>$USER->id));
		    $html = '';
		    $course = $courses[intval($questionnaire->course)];
		    $coursecontext = context_course::instance($course->id);
		    $teachers = get_users_from_role_on_context($teacherrole, $coursecontext);
		    $html .= html_writer::start_tag('h5', array('class'=>'font-weight-bold'));
		    $html .= $questionnaire->name;
		    $html .= html_writer::end_tag('h5');
		    $html .= html_writer::div($course->fullname, 'font-weight-bold');
		    $html .= html_writer::start_tag('div');
		    if(count($teachers) == 1) {
		    	$html .= "Profesor: ";
		    } elseif(count($teachers) > 1) {
		    	$html .= "Profesores: ";
		    }
		    foreach($teachers as $teacher) {
		    	$t = $DB->get_record('user', array('id'=>$teacher->userid));
		    	$html .= $t->firstname . " " . $t->lastname . " - ";
		    }
		    $html .= html_writer::end_tag('div');
		    $iscomplete = 'n';
	        if ($responses = questionnaire_get_user_responses($questionnaire->id, $USER->id, false)) {
	            foreach ($responses as $response) {
	                if ($response->complete == 'y') {
	                    $html .= $OUTPUT->box('<i class="fa fa-check" aria-hidden="true"></i> Contestada', 'alert-success status status-contestada');
	                    $iscomplete = 'y';
	                    break;
	                } else {
	                    $html .=  $OUTPUT->box($OUTPUT->single_button($url, 'Continuar'), 'status');
	                }
	            }
	        } elseif(intval($questionnaire->closedate) > 0 && intval($questionnaire->closedate) < time()) {
	            $iscomplete = 'z';
	            $html .= $OUTPUT->box('<i class="fa fa-ban" aria-hidden="true"></i> Cerrada', 'alert-warning status status-cerrada');
	        } else {
	            $html .= $OUTPUT->single_button($url, 'Contestar', 'post', array('class'=>'status'));
	        }
		    $htmlquestionnaires[$iscomplete . $questionnaire->closedate .'-' . $questionnaire->id] = $OUTPUT->box($html, 'questionnaire');
		}
		ksort($htmlquestionnaires);
		echo implode('', $htmlquestionnaires);
	}
}
echo $OUTPUT->footer ();