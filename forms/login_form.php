<?php
/**
 *
* @package local
* @subpackage uaio
* @copyright 2017 Universidad Adolfo Ibanez
* @author Jorge Villalon <jorge.villalon@uai.cl>
*/

require_once ($CFG->libdir . '/formslib.php');

class local_encuestascdc_login_form extends moodleform {

	public function definition() {

		$mform = $this->_form;
		$instance = $this->_customdata;

		$mform->addElement('header', 'Resumen encuestas');

		$mform->addElement('text', 'idnumber', 'RUT o Pasaporte');
		$mform->addHelpButton('idnumber', 'rut', 'local_encuestascdc');
		$mform->hideIf('idnumber', 'tipo', 'eq', 2);
		$mform->setType('idnumber', PARAM_RAW);

		$mform->addElement('text', 'username', 'Nombre de usuario');
		$mform->addHelpButton('username', 'rut', 'local_encuestascdc');
		$mform->setType('username', PARAM_RAW);
		$mform->hideIf('username', 'tipo', 'eq', 1);
		$mform->setAdvanced('username');

		$mform->addElement('password', 'pwd', 'Contraseña');
		$mform->setType('pwd', PARAM_RAW);

		$mform->addElement('select', 'tipo', 'Seleccione método de entrada', array(1 => 'RUT o Pasaporte', 2 => 'Nombre de usuario'));
		$mform->addHelpButton('tipo', 'rut', 'local_encuestascdc');
		$mform->setType('tipo', PARAM_INT);
		$mform->setAdvanced('tipo');

		$this->add_action_buttons(false, get_string('login', 'local_encuestascdc'));
	}

	public function validation($data, $files) {
		$errors = array();
		return $errors;
	}
}
