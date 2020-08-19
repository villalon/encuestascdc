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
 * Configuración de administración de UAI Corporate.
 *
 * @package local
 * @subpackage encuestascdc
 * @author Ernesto Jaramillo <jorge.villalon@uai.cl>
 * @copyright 2020 Universidad Adolfo Ibáñez
 */
require_once ($CFG->libdir . '/formslib.php');

class local_encuestascdc_export_form extends moodleform {

    public function definition() {
        global $DB, $CFG;
        $mform = $this->_form;

        $instance = $this->_customdata;
        
        $categoryid = $instance['categoryid'];
        
        // CATEGORÍAS
        $choices = core_course_category::make_categories_list('moodle/category:manage');
        $choices[0] = get_string('select');
        ksort($choices);
        
        $mform->addElement('header','filter','Filtros');
        
        //Filtro categorías
		$mform->addElement('autocomplete', 'id', 'Categoría de los cursos', $choices);
		$mform->setType('id', PARAM_INT);
		$mform->addRule('id', 'Debe escoger una categoría', 'required');

		$choices = array(
			'all'=>"Cualquiera",
			'today'=>get_string('today'),
			'yesterday'=>'Ayer',
			'lastweek' => 'Última Semana',
			'lastmonth' => 'Último Mes',
			'last7days' => 'Últimos 7 Días',

			'last30days'=> 'Últimos 30 Días',
			'lastdate' => 'Última Fecha Fin',
			'custom' => 'Personalizado'
			);
		
		// Filtro Fecha inicio
		$mform->addElement('select','start','Fecha de inicio',$choices);
		$mform->setType('start',PARAM_RAW);

		$mform->addElement('date_selector','fromstart','Desde');
		$mform->setType('fromstart',PARAM_INT);
		$mform->hideIf('fromstart','start','neq','custom');
		$mform->setDefault('fromstart',0);

		$mform->addElement('date_selector','tostart','Hasta');
		$mform->hideIf('tostart','start','neq','custom');
		$mform->setType('tostart',PARAM_INT);
		$mform->setDefault('tostart', 0);
		
		// Filtro Fecha fin
		$mform->addElement('select','end','Fecha de fin',$choices);
		$mform->setType('end',PARAM_RAW);

		$mform->addElement('date_selector','fromend','Desde');
		$mform->hideIf('fromend','end','neq','custom');
		$mform->setType('fromend',PARAM_INT);
		$mform->setDefault('fromend', 0);

		$mform->addElement('date_selector','toend','Hasta');
		$mform->hideIf('toend','end','neq','custom');
		$mform->setType('toend',PARAM_INT);
		$mform->setDefault('toend', 0);
		
		// Filtros en segundo paso
        if($categoryid>0) {
        	// Filtro cursos
        	
        	// Buscamos todos los cursos de la categoría seleccionada en primer paso
        	$coursecategory = core_course_category::get($categoryid);
        	$courses = $coursecategory->get_courses(array('recursive'=>true));
        	$courseids = array();
        	foreach($courses as $course){
        		$courseids[$course->id] = $course->fullname;
        	}
        	$options = array(
			    'multiple' => true
			);
        	$mform->addElement('select','courseids', 'Cursos',$courseids, $options);
        	$mform->setType('courseids', PARAM_INT);
        	
        	// Filtro editing teachers de los cursos anteriores
        	$teacherids = array();
        	foreach($courses as $course) {
	        	$role = $DB->get_record('role', array('shortname' => 'editingteacher'));
				$context = context_course::instance($course->id);
				$teachers = get_role_users($role->id, $context);
				
				// Buscamos todos los editing teachers del curso
				foreach($teachers as $teacher) {
					$teacherids[$teacher->id] = $teacher->firstname . ' ' . $teacher->lastname;
				}
        	}
        	$mform->addElement('select', 'teacherids', 'Profesores', $teacherids, $options);
        	$mform->setType('teacherids', PARAM_INT);
			
			// Filtro de Managers del curso
        	$managerids = array();
        	foreach($courses as $course) {
	        	$role = $DB->get_record('role', array('shortname' => 'manager'));
				$context = context_course::instance($course->id);
				$managers = get_role_users($role->id, $context);
				
				// Buscamos todos los editing teachers del curso
				foreach($managers as $manager) {
					$managerids[$manager->id] = $manager->firstname . ' ' . $manager->lastname;
				}
        	}
        	$mform->addElement('select', 'managerids', 'Coordinadores', $managerids, $options);
        	$mform->setType('managerids', PARAM_INT);

        } 
        
        $this->add_action_buttons(false,"Obtener Reporte");
    }

    public function validation($data, $files) {
        $errors = array();
        return $errors;
    }
}