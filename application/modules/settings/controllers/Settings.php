<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends CI_Controller {
	
    public function __construct() {
        parent::__construct();
        $this->load->model("settings_model");
        $this->load->model("general_model");
		$this->load->helper('form');
    }
	
	/**
	 * employee List
     * @since 15/12/2016
     * @author BMOTTAG
	 */
	public function employee($state)
	{			
			$data['state'] = $state;
			if($state == 1){
				$arrParam = array("filtroState" => TRUE);
			}else{
				$arrParam = array("state" => $state);
			}
			$data['info'] = $this->general_model->get_user($arrParam);
			$data["view"] = 'employee';
			$this->load->view("layout_calendar", $data);
	}
	
    /**
     * Cargo modal - formulario Employee
     * @since 15/12/2016
     */
    public function cargarModalEmployee() 
	{
			header("Content-Type: text/plain; charset=utf-8"); //Para evitar problemas de acentos
			$data['information'] = FALSE;
			$data["idEmployee"] = $this->input->post("idEmployee");
			$arrParam = array("filtro" => TRUE);
			$data['roles'] = $this->general_model->get_roles($arrParam);
			if ($data["idEmployee"] != 'x') {
				$arrParam = array(
					"table" => "usuarios",
					"order" => "id_user",
					"column" => "id_user",
					"id" => $data["idEmployee"]
				);
				$data['information'] = $this->general_model->get_basic_search($arrParam);
			}
			$this->load->view("employee_modal", $data);
    }
	
	/**
	 * Update Employee
     * @since 15/12/2016
     * @author BMOTTAG
	 */
	public function save_employee()
	{			
			header('Content-Type: application/json');
			$data = array();
			$idUser = $this->input->post('hddId');
			$msj = "Se adicionó un nuevo Usuario!";
			if ($idUser != '') {
				$msj = "Se actualizó el Usuario!";
			}
			$log_user = $this->input->post('user');
			$email_user = $this->input->post('email');
			$result_user = false;
			$result_email = false;
			$result_ldap = false;
			//verificar si ya existe el usuario
			$arrParam = array(
				"idUser" => $idUser,
				"column" => "log_user",
				"value" => $log_user
			);
			$result_user = $this->settings_model->verifyUser($arrParam);
			//verificar si ya existe el correo
			$arrParam = array(
				"idUser" => $idUser,
				"column" => "email",
				"value" => $email_user
			);
			$result_email = $this->settings_model->verifyUser($arrParam);
			$data["state"] = $this->input->post('state');
			if ($idUser == '') {
				$data["state"] = 1;//para el direccionamiento del JS, cuando es usuario nuevo no se envia state
				$ldapuser = $this->session->userdata('logUser');
				$ldappass = ldap_escape($this->session->userdata('password'), ".,_,-,+,*,#,$,%,&,@", LDAP_ESCAPE_FILTER);
				$ds = ldap_connect("192.168.0.44", "389") or die("No es posible conectar con el directorio activo.");  // Servidor LDAP!
		        if (!$ds) {
		            echo "<br /><h4>Servidor LDAP no disponible</h4>";
		            @ldap_close($ds);
		        } else {
		            $ldapdominio = "jardin";
		            $ldapusercn = $ldapdominio . "\\" . $ldapuser;
		            $binddn = "dc=jardin, dc=local";
		            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            		ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
		            $r = @ldap_bind($ds, $ldapusercn, $ldappass);
		            if (!$r) {
		                @ldap_close($ds);
		                $data["msj"] = "Error de autenticación. Por favor revisar usuario y contraseña de red.";
		                $this->session->sess_destroy();
						$this->load->view('login', $data);
		            } else {
		            	$filter = "(&(sAMAccountName=" . $log_user . ")(mail=" . $email_user . "))";
		            	$attributes = array('sAMAccountName', 'mail');
		            	$result = @ldap_search($ds, $binddn, $filter, $attributes);
		            	if (@ldap_count_entries($ds, $result) == 1) {
		            		$result_ldap = false;
		            	} else {
		            		$result_ldap = true;
		            	}
		            }
		        }
			}
			if ($result_user || $result_email || $result_ldap)
			{
				$data["result"] = "error";
				if($result_user)
				{
					$data["mensaje"] = " Error. El Usuario ya existe.";
					$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> El Usuario ya existe.');
				}
				if($result_email)
				{
					$data["mensaje"] = " Error. El correo ya existe.";
					$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> El correo ya existe.');
				}
				if($result_user && $result_email)
				{
					$data["mensaje"] = " Error. El Usuario y el Correo ya existen.";
					$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> El Usuario y el Correo ya existen.');
				}
				if ($result_ldap) {
					$data["mensaje"] = " Error. El usuario no existe en el directorio activo.";
					$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> El usuario no esta creado en el directorio activo.');
				}
			} else {
					if ($this->settings_model->saveEmployee()) {
						$data["result"] = true;					
						$this->session->set_flashdata('retornoExito', '<strong>Correcto!</strong> ' . $msj);
					} else {
						$data["result"] = "error";					
						$this->session->set_flashdata('retornoError', '<strong>Error!</strong> Ask for help');
					}
			}
			echo json_encode($data);
    }
	
	/**
	 * Reset employee password
	 * Reset the password to '123456'
	 * And change the status to '0' to changue de password 
     * @since 11/1/2017
     * @author BMOTTAG
	 */
	public function resetPassword($idUser)
	{
			if ($this->settings_model->resetEmployeePassword($idUser)) {
				$this->session->set_flashdata('retornoExito', '<strong>Correcto!</strong> You have reset the Employee pasword to: 123456');
			} else {
				$this->session->set_flashdata('retornoError', '<strong>Error!</strong> Ask for help');
			}
			redirect("/settings/employee/",'refresh');
	}	

	/**
	 * Change password
     * @since 15/4/2017
     * @author BMOTTAG
	 */
	public function change_password($idUser)
	{
			if (empty($idUser)) {
				show_error('ERROR!!! - You are in the wrong place. The ID USER is missing.');
			}
			$arrParam = array(
				"table" => "usuarios",
				"order" => "id_user",
				"column" => "id_user",
				"id" => $idUser
			);
			$data['information'] = $this->general_model->get_basic_search($arrParam);
			$data["view"] = "form_password";
			$this->load->view("layout", $data);
	}
	
	/**
	 * Update user´s password
	 */
	public function update_password()
	{
			$data = array();			
			$newPassword = $this->input->post("inputPassword");
			$confirm = $this->input->post("inputConfirm");
			$userState = $this->input->post("hddState");
			//Para redireccionar el usuario
			if($userState!=2){
				$userState = 1;
			}
			$passwd = str_replace(array("<",">","[","]","*","^","-","'","="),"",$newPassword);
			$data['linkBack'] = "settings/employee/" . $userState;
			$data['titulo'] = "<i class='fa fa-unlock fa-fw'></i>CAMBIAR CONTRASEÑA";
			if($newPassword == $confirm)
			{					
					if ($this->settings_model->updatePassword()) {
						$data['msj'] = 'Se actualizó la contrasela del usuario.';
						$data['msj'] .= '<br>';
						$data['msj'] .= '<br><strong>Nombre Usuario: </strong>' . $this->input->post('hddUser');
						$data['msj'] .= '<br><strong>Contraseña: </strong>' . $passwd;
						$data['clase'] = 'alert-success';
					}else{
						$data['msj'] = '<strong>Error!!!</strong> Ask for help.';
						$data['clase'] = 'alert-danger';
					}
			} else {
				//definir mensaje de error
				echo "pailas no son iguales";
			}
			$data["view"] = "template/answer";
			$this->load->view("layout", $data);
	}
	
	/**
	 * Lista de horarios
     * @since 16/2/2021
     * @author BMOTTAG
	 */
	public function horarios($tipoVisita)
	{
			//eliminar imagenes de QR de mas de 15 dias
			$files = glob('images/reservas/QR/*.png'); //obtenemos el nombre de todos los ficheros
			foreach($files as $file){
			    $lastModifiedTime = filemtime($file);
			    $currentTime = time();
			    $timeDiff = abs($currentTime - $lastModifiedTime)/(60*60*24); //en dias
			    if(is_file($file) && $timeDiff > 15){
			    	unlink($file); //elimino el fichero
			    }
			}
			//eliminar imagenes de captcha
			$files = glob('images/captcha_images/*.jpg'); //obtenemos todos los nombres de los ficheros
			foreach($files as $file){
			    if(is_file($file))
			    unlink($file); //elimino el fichero
			}
			$arrParam = array(
				'from' => date('Y-m-d'),
				'tipoVisita' => $tipoVisita
			);
			$data['infoHorarios'] = $this->general_model->get_horario_info($arrParam);
			$data['tipoVisita'] = $tipoVisita;
			$data["view"] = 'horarios';
			$this->load->view("layout_calendar", $data);
	}
	
    /**
     * Cargo modal - formulario horarios
     * @since 16/2/2021
     */
    public function cargarModalHorarios() 
	{
			header("Content-Type: text/plain; charset=utf-8"); //Para evitar problemas de acentos
			$data["tipoVisita"] = $this->input->post("tipoVisita");	
			$data['information'] = FALSE;
			$this->load->view("horarios_modal", $data);
    }
	
	/**
	 * Save horarios
     * @since 16/2/2021
     * @author BMOTTAG
	 */
	public function save_horarios()
	{			
			header('Content-Type: application/json');
			$data = array();
			$idHorario = $this->input->post('hddId');
			$data["tipoVisita"] = $this->input->post('tipoVisita');
			$msj = "Se adicionaron los horarios!";
			if ($idHorario != '') {
				$msj = "Se actualizó el Proveedor!";
			}
			if ($idHorario = $this->settings_model->saveHorarios()) {
				$data["result"] = true;
				$this->session->set_flashdata('retornoExito', '<strong>Correcto!</strong> ' . $msj);
			} else {
				$data["result"] = "error";
				$this->session->set_flashdata('retornoError', '<strong>Error!</strong> Ask for help');
			}
			echo json_encode($data);	
    }

    /**
     * Cargo modal - formulario para adicionar cupos
     * @since 29/10/2021
     */
    public function cargarModalAddCupos() 
	{
			header("Content-Type: text/plain; charset=utf-8"); //Para evitar problemas de acentos
			$data['information'] = FALSE;
			$data["idHorario"] = $this->input->post("idHorario");
			if ($data["idHorario"] != 'x') {
				$arrParam = array(
					"idHorario" => $data["idHorario"]
				);
				$data['information'] = $this->general_model->get_horario_info($arrParam);
			}
			$this->load->view("horarios_cupos_modal", $data);
    }

	/**
	 * Save adicion de cupoos
     * @since 31/10/2021
     * @author BMOTTAG
	 */
	public function save_add_cupos()
	{			
			header('Content-Type: application/json');
			$data = array();
			$data["tipoVisita"] = $this->input->post('tipoVisita');
			if ($this->settings_model->saveMasCupos()) {
				$data["result"] = true;
				$this->session->set_flashdata('retornoExito', '<strong>Correcto!</strong> Se adicionaron los cupos.');
			} else {
				$data["result"] = "error";
				$this->session->set_flashdata('retornoError', '<strong>Error!</strong> Ask for help');
			}
			echo json_encode($data);	
    }

	/**
	 * Bloquear/Desbloqear horarios
     * @since 3/3/2021
     * @author BMOTTAG
	 */
	public function bloquear_horarios($tipoVisita)
	{	
			if ($this->settings_model->actualizarDisponibilidadHorarios()) {
				$data["result"] = true;
				$this->session->set_flashdata('retornoExito', "Se actualizó la disponibilidad de los horarios!!");
			} else {
				$data["result"] = "error";
				$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> Ask for help');
			}
			redirect(base_url('settings/horarios/'. $tipoVisita), 'refresh');
	}

	/**
	 * titulo
     * @since 15/08/2023
     * @author AOCUBILLOSA
	 */
	public function titulo()
	{
			//busco en la tabla parametros el valor para el titulo
			$arrParam = array(
				'table' => 'parametros',
				'order' => 'id_parametro',
				'column' => 'parametro_nombre',
				'id' => 'titulo'
			);
			$data['info'] = $this->general_model->get_basic_search($arrParam);
			$data["view"] = 'titulo';
			$this->load->view('layout_calendar', $data);
	}
	
	/**
	 * Save titulo
     * @since 15/08/2023
     * @author AOCUBILLOSA
	 */
	public function save_titulo()
	{			
			$data['linkBack'] = "settings/titulo";
			$data['titulo'] = "<i class='fa fa-thumb-tack'></i> TITULO";
			$texto =  $this->input->post('texto');
			//actualizamos el campo titulo
			$arrParam = array(
				'table' => 'parametros',
				'primaryKey' => 'parametro_nombre',
				'id' => 'titulo',
				'column' => 'parametro_valor',
				'value' => $texto
			);
			if($this->general_model->updateRecord($arrParam)){
				$data['msj'] = 'Se actualizó el campo de titulo.';
				$data['clase'] = 'alert-success';
			}else{
				$data['msj'] = '<strong>Error!!!</strong> Ask for help.';
				$data['clase'] = 'alert-danger';
			}
			$data["view"] = "template/answer";
			$this->load->view("layout", $data);
    }
	
    /**
	 * mensaje
     * @since 15/08/2023
     * @author AOCUBILLOSA
	 */
	public function mensaje()
	{
			//busco en la tabla parametros el valor para el mensaje
			$arrParam = array(
				'table' => 'parametros',
				'order' => 'id_parametro',
				'column' => 'parametro_nombre',
				'id' => 'mensaje'
			);
			$data['infoMsj'] = $this->general_model->get_basic_search($arrParam);
			$data["view"] = 'mensaje';
			$this->load->view('layout_calendar', $data);
	}
	
	/**
	 * Save mensaje
     * @since 15/08/2023
     * @author AOCUBILLOSA
	 */
	public function save_mensaje()
	{			
			$data['linkBack'] = "settings/mensaje";
			$data['titulo'] = "<i class='fa fa-thumb-tack'></i> MENSAJE";
			$texto =  $this->input->post('texto');
			//actualizamos el campo mensaje
			$arrParam = array(
				'table' => 'parametros',
				'primaryKey' => 'parametro_nombre',
				'id' => 'mensaje',
				'column' => 'parametro_valor',
				'value' => $texto
			);
			if($this->general_model->updateRecord($arrParam)){
				$data['msj'] = 'Se actualizó el campo de mensaje.';
				$data['clase'] = 'alert-success';
			}else{
				$data['msj'] = '<strong>Error!!!</strong> Ask for help.';
				$data['clase'] = 'alert-danger';
			}
			$data["view"] = "template/answer";
			$this->load->view("layout", $data);
    }
}