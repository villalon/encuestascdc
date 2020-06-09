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
require_once ('forms/password_form.php');
require_once ('../../mod/questionnaire/lib.php');
require_once ('../../mod/questionnaire/locallib.php');

// Contexto página principal
$frontpagecontext = context_course::instance(SITEID);
// Editar la página principal solo lo pueden hacer gestores y administradores (esto permite filtrar a gestores)
// require_capability('moodle/course:update', $frontpagecontext);
// Contexto de sistema
$context = context_system::instance ();

// Page navigation and URL settings.
$PAGE->set_url ( $CFG->wwwroot . '/local/encuestascdc/qrcode.php' );
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( 'admin' );
$PAGE->set_title ('Códigos QR para ingreso');
// Require jquery for modal.
$PAGE->requires->jquery ();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );

// The page header and heading
echo $OUTPUT->header ();
echo $OUTPUT->heading ('Códigos QR para acceso rápido a encuestas');

echo '
<style>
.qrcodes {
	display: flex;
}
.qr {
    margin-left: auto;
    margin-right: auto;
    text-align: center;
}
</style>';

$title = 'QR acceso encuestas UAI Online';
$url = 'https://online.uai.cl/local/encuestascdc/login.php';
$imgurl = $CFG->wwwroot . '/local/encuestascdc/img/uol-qr-encuestas.png';

echo html_writer::start_div('qr');
echo $OUTPUT->heading($title, 3);
echo '<a href="'.$url.'"><img src="' . $imgurl . '" /></a>';
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer ();
