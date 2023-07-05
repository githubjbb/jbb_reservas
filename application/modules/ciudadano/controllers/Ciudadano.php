<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ciudadano extends CI_Controller {
	
    public function __construct() {
        parent::__construct();
        $this->load->model("ciudadano_model");
        $this->load->model("general_model");
        $this->load->helper('captcha');
		$this->load->helper('form');
    }

	/**
	 * Calendario
     * @since 6/1/2021
     * @author BMOTTAG
	 */
	public function index()
	{
			//busco en la tabla parametros el valor para el popup
			$arrParam = array(
				'table' => 'parametros',
				'order' => 'id_parametro',
				'column' => 'parametro_nombre',
				'id' => 'popup'
			);
			$data['infoPopup'] = $this->general_model->get_basic_search($arrParam);

			$data["view"] = 'calendario_ciudadano';
			$this->load->view("layout_calendar", $data);
	}

	/**
	 * Consulta desde el calendario
     * @since 12/2/2021
     * @author BMOTTAG
	 */
    public function consulta() 
    {
	        header("Content-Type: text/plain; charset=utf-8"); //Para evitar problemas de acentos

			$start = $this->input->post('start');
			$end = $this->input->post('end');
			$start = substr($start,0,10);
			$end = substr($end,0,10);

			//busco horarios bloqueados para revisarlos y desbloquearlos si pasaron los 5 minutos
			$arrParam = array(
				'from' => $start,
				'to' => $end,
				'disponible' => 2,
				'tipoVisita' => 2
			);
			$horarioBloqueados = $this->general_model->get_horario_info($arrParam);

			$date1 = new DateTime('now');
			if($horarioBloqueados){
				foreach ($horarioBloqueados as $data):
					$date2 = new DateTime($data['fecha_bloqueo']);
					$diff = $date1->diff($date2);
					$numeroMinutos = $diff->i;
					if($numeroMinutos > 5){
							$arrParam = array(
								'idHorario' => $data['id_horario'],
								'NumeroCuposRestantes' => $data['numero_cupos_restantes'],
								'estado' => $data['estado'],
								'disponibilidad' => 1
							);
							$this->ciudadano_model->actualizarHorarios($arrParam);
					}
				endforeach;
			}

			//buscar horarios que no esten bloqueados
			$arrParam = array(
				'from' => $start,
				'to' => $end,
				'bloqueados' => 'no esten bloqueados por el administrador'
			);
			$horarioInfo = $this->general_model->get_horario_info($arrParam);

			echo  '[';

			if($horarioInfo)
			{
				$longitud = count($horarioInfo);
				$i=1;
				foreach ($horarioInfo as $data):

					$fechaActual = strtotime(date('Y-m-d G:i:s'));
					$fechaInicial = strtotime($data['hora_inicial']);
					
					if($fechaInicial < $fechaActual){
						$color = '#f0e3e3';
					}else{
						switch ($data['estado']) {
							case 1:
								$color = '#b1eeb1';
								break;
							case 2:
								$color = '#f7f79a';
								break;
							case 3:
								$color = '#f7c0c0';
								break;
						}
					}

					echo  '{
							  "id": "' . $data['id_horario'] . '",
						      "title": "Cupos disponibles: ' . $data['numero_cupos_restantes'] . '",
						      "start": "' . $data['hora_inicial'] . '",
						      "end": "' . $data['hora_final'] . '",
						      "color": "' . $color . '"
						    }';

					if($i<$longitud){
							echo ',';
					}
					$i++;
				endforeach;
			}

			echo  ']';

    }

    /**
     * Cargo modal - formulario visitas a las cerros
     * @since 1/2/2021
     */
    public function cargarModalVisitas() 
	{
			header("Content-Type: text/plain; charset=utf-8"); //Para evitar problemas de acentos

			$flores = array('orquidea', 'cayena', 'heliconia', 'tulipan', 'brunellia', 'abarema', 'aniba', 'calatola', 'centronia', 'clusia', 'cordia', 'espeletia', 'guarea', 'herrania', 'licania', 'magnolia');

			$i = rand(0, 14);

			// Captcha
			$config = array(
				'word'		=> $flores[$i],
			    'font_size'      => 25,
			    'img_path'      => 'images/captcha_images/',
			    'img_url'       => base_url().'images/captcha_images/'
			);
			$captcha = create_captcha($config);

			// Unset previous captcha and store new captcha word
			$this->session->unset_userdata('captchaCode');
			$this->session->set_userdata('captchaCode',$captcha['word']);

			// Send captcha image to view
			$data['captchaImg'] = $captcha['image'];
			
			$data['information'] = FALSE;
			$data["idHorario"] = $this->input->post("idHorario");

			$arrParam = array(
				"idHorario" => $data["idHorario"]
			);
			$data['information'] = $this->general_model->get_horario_info_ciudadano($arrParam);

			$fechaActual = strtotime(date('Y-m-d G:i:s'));
			$fechaInicial = strtotime($data['information'][0]['hora_inicial']);
					
			if($fechaInicial < $fechaActual){
				echo '<br><p><strong>Atención:</strong><br>';
				echo 'Esta fecha se encuentra cerrada.</p>';
			}elseif($data['information'][0]['estado'] == 3)
			{
				echo '<br><p><strong>Atención:</strong><br>';
				echo 'Se completo el cupo disponible.</p>';
			}elseif($data['information'][0]['disponible'] == 2)
			{
				echo '<br><p><strong>Atención:</strong><br>';
				echo 'Esta fecha esta siendo asignada, por favor espere unos minutos.</p>';
			}elseif($data['information'][0]['numero_cupos_restantes'] <= 0){
				echo '<br><p><strong>Atención:</strong><br>';
				echo 'Se completo el cupo disponible.</p>';
			}else{
				//bloquear sala por 5 minutos mientras se realiza la reserva
				$arrParam = array(
					'idHorario' => $data['idHorario'],
					'disponibilidad' => 2
				);
				$this->ciudadano_model->habilitarHorario($arrParam);

				$this->load->view("reserva_modal", $data);
			}
    }

	/**
	 * Guardar Reserva
     * @since 12/2/2021
     * @author BMOTTAG
	 */
    public function guardarReserva()
	{			
			header('Content-Type: application/json');
			$data = array();

			$idHorario = $this->input->post('hddIdHorario');

			//busco informacion de numero de cupos y numero de reseervas para saber numero de cupos restantes
			$arrParam = array('idHorario' => $idHorario);
			$infoHorario = $this->general_model->get_horario_info_ciudadano($arrParam);
			$numeroCuposPermitidos = $infoHorario[0]['numero_cupos'];

			$arrParam['estadoReserva'] = 1;
			$infoReserva = $this->general_model->get_reserva_info_ciudadano($arrParam);
			$noVisitantes = $infoReserva?count($infoReserva):0;
			$NumeroCuposRestantes = $numeroCuposPermitidos - $noVisitantes;
			
			$usuarios = $this->input->post('name');
			$primerUsuario = $this->security->xss_clean($usuarios[0]);//limpio el primer valor

            $inputCaptcha = $email = trim($this->security->xss_clean($this->input->post('captcha')));
            $sessCaptcha = $this->session->userdata('captchaCode');

 			if($inputCaptcha !== $sessCaptcha)
            {
					$data["result"] = "error";					
					$data["mensaje"] = " Error. El captcha no coincide.";
			}else{
				if(empty(trim($primerUsuario)))
				{
						$data["result"] = "error";					
						$data["mensaje"] = " Error. Debe ingresar el nombre completo.";
						$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> No ingreso nombres');
				}else{
						$pass = $this->generaPass();//clave para colocarle al codigo QR

						if ($idReserva = $this->ciudadano_model->guardarReserva($pass)) 
						{
							//actualizar el numero de cupos restantes en la tabla horarios
							//si cumplio el numero maximo de cupos cambiar estado a cerrado
							$NumeroCuposRestantes = $NumeroCuposRestantes - 1;
							$estado = '2'; //En processo
							$disponibilidad = 1;
							if($NumeroCuposRestantes <= 0){
								$estado = '3'; //cerrado
							}
							$arrParam = array(
								'idHorario' => $idHorario,
								'NumeroCuposRestantes' => $NumeroCuposRestantes,
								'estado' => $estado,
								'disponibilidad' => $disponibilidad
							);
							$this->ciudadano_model->actualizarHorarios($arrParam);

							//genero el codigo QR y subo la imagen
							//INCIO - genero imagen con la libreria y la subo 
							$this->load->library('ciqrcode');

							$data['idRecord'] = $llave = $pass . $idReserva;
							$valorQRcode = base_url("ciudadano/registro/" . $llave);
							$rutaImagen = "images/reservas/QR/" . $llave . "_qr_code.png";
							
							$params['data'] = $valorQRcode;
							$params['level'] = 'H';
							$params['size'] = 10;
							$params['savename'] = FCPATH.$rutaImagen;
											
							$this->ciqrcode->generate($params);
							//FIN - genero imagen con la libreria y la subo

							$this->email($idReserva);

							$data["result"] = true;					
							$this->session->set_flashdata('retornoExito', 'Se guardó la información');
						} else {
							$data["result"] = "error";					
							$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> Ask for help');
						}
				}
			}

			echo json_encode($data);
    }
	
	public function generaPass()
	{
			//Se define una cadena de caractares. Te recomiendo que uses esta.
			$cadena = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
			//Obtenemos la longitud de la cadena de caracteres
			$longitudCadena=strlen($cadena);
			 
			//Se define la variable que va a contener la contraseña
			$pass = "";
			//Se define la longitud de la contraseña, en mi caso 10, pero puedes poner la longitud que quieras
			$longitudPass=20;
			 
			//Creamos la contraseña
			for($i=1 ; $i<=$longitudPass ; $i++){
				//Definimos numero aleatorio entre 0 y la longitud de la cadena de caracteres-1
				$pos=rand(0,$longitudCadena-1);
			 
				//Vamos formando la contraseña en cada iteraccion del bucle, añadiendo a la cadena $pass la letra correspondiente a la posicion $pos en la cadena de caracteres definida.
				$pass .= substr($cadena,$pos,1);
			}
			return $pass;
	}	

	/**
	 * Info del registro
     * @since 15/2/2021
     * @author BMOTTAG
	 */
	public function registro($llave)
	{
			$arrParam = array("llave" => $this->security->xss_clean($llave));
			$data['infoReserva'] = $this->general_model->get_reserva_info_ciudadano($arrParam);

			$arrParam = array("idHorario" => $data['infoReserva'][0]['fk_id_horario']);
			$data['infoHorario'] = $this->general_model->get_horario_info_ciudadano($arrParam);
		
			$data["view"] = 'info_reserva';
			$this->load->view("layout_calendar", $data);
	}


	/**
	 * Info del registro
     * @since 15/2/2021
     * @author BMOTTAG
	 */
	public function habilitar()
	{
			$idHorario = $this->input->post("idHorario");

			//desbloquear horario
			$arrParam = array(
				'idHorario' => $idHorario,
				'disponibilidad' => 1
			);
			$this->ciudadano_model->habilitarHorario($arrParam);
	}

    /**
     * Cargo modal - formulario eliminar reserva
     * @since 17/2/2021
     */
    public function cargarModalEliminar() 
	{
			header("Content-Type: text/plain; charset=utf-8"); //Para evitar problemas de acentos

			$this->load->view("eliminar_modal");
    }

	/**
	 * Cancelar Reserva
     * @since 17/2/2021
     * @author BMOTTAG
	 */
    public function cancelarRegistro()
	{			
			header('Content-Type: application/json');
			$data = array();
			
			$email = trim($this->security->xss_clean($this->input->post('email')));
			$fecha = trim($this->security->xss_clean($this->input->post('fecha')));
			$celular = trim($this->security->xss_clean($this->input->post('celular')));

			$arrParam = array(
				'email' => $email,
				'fecha' => $fecha,
				'celular' => $celular,
				'estadoReserva' => 1
			);
			$infoReserva = $this->general_model->get_reserva($arrParam);

			if(!$infoReserva)
			{
					$data["result"] = "error";					
					$data["mensaje"] = " Error. No hay reservas con esa información.";
					$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> No hay reservas con esa información.');
			}else{
					//deshaiblito reseva
					$arrParam = array(
						'idReserva' => $infoReserva[0]['id_reserva']
					);
					if ($idReserva = $this->ciudadano_model->deshabilitarReserva($arrParam)) 
					{
						$NumeroCuposRestantes = $infoReserva[0]['numero_cupos_restantes'];
						$numeroCupos = $infoReserva[0]['numero_cupos_usados'];
						//actualizar el numero de cupos restantes en la tabla horarios
						//si cumplio el numero maximo de cupos cambiar estado a cerrado
						$NumeroCuposRestantes = $NumeroCuposRestantes + $numeroCupos;
						$estado = '2'; //En processo
						$disponibilidad = 2;
						//busco el si el horario esta bloqueado por el administrado si es asi se deja bloqueado
						$arrParam = array(
							'idHorario' => $infoReserva[0]['fk_id_horario'],
							'disponible' => 3
						);
						$horarioInfo = $this->general_model->get_horario_info($arrParam);
						if($horarioInfo){
							$disponibilidad = 3;
						}

						$arrParam = array(
							'idHorario' => $infoReserva[0]['fk_id_horario'],
							'NumeroCuposRestantes' => $NumeroCuposRestantes,
							'estado' => $estado,
							'disponibilidad' => $disponibilidad
						);
						$this->ciudadano_model->actualizarHorarios($arrParam);

						$data["result"] = true;					
						$this->session->set_flashdata('retornoExito', 'Se guardó la información');
					} else {
						$data["result"] = "error";					
						$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> Ask for help');
					}
			}

			echo json_encode($data);
    }

	/**
	 * Evio de correo
     * @since 17/2/2021
     * @author BMOTTAG
	 */
	public function email($idReserva)
	{
			$arrParam = array("idReserva" => $idReserva);
			$infoReserva = $this->general_model->get_reserva_info_ciudadano($arrParam);

			$arrParam = array("idHorario" => $infoReserva[0]['fk_id_horario']);
			$infoHorario = $this->general_model->get_horario_info_ciudadano($arrParam);

			//busco datos parametricos de configuracion para envio de correo
			$arrParam = array(
				"table" => "parametros",
				"order" => "id_parametro",
				"id" => "x"
			);
			$parametric = $this->general_model->get_basic_search($arrParam);

			$paramHost = $parametric[0]["parametro_valor"];
			$paramUsername = $parametric[1]["parametro_valor"];
			$paramPassword = $parametric[2]["parametro_valor"];
			$paramFromName = $parametric[3]["parametro_valor"];
			
			$subjet = 'JBB - Visita Cerros';
			$to = $infoReserva[0]['correo_electronico'];

			//mensaje del correo
			$msj = '<p><strong>Gracias por reservar su visita, </strong></p>';

			$msj .= '<p>El Jardín Botánico José Celestino Mutis confirma su reserva ';
			$msj .= '<br><br>El Diálogo Ciudadano se realizará el <b>14 de diciembre a las 9:00 am.</b>';
			$msj .= '<ul>';
			foreach ($infoReserva as $data):
				$msj .= '<li>' . $data['nombre_completo'] . '</li>';
			endforeach;
			$msj .= '</ul>';		
			$msj .= '<p><strong>A tener en cuenta:</strong></p>';
			$msj .= '<ol>';
$msj .= '<li> La inscripción arrojará un Código el cual debe guardar y presentar al momento de ingresar a las instalaciones del Jardín Botánico.   </li>
<li> Contar con kit de bioseguridad; gel antibacterial y tapabocas.  </li>
<li> Esquema completo de vacunación para COVID-19.  </li>
<li> Para un mejor disfrute de la actividad al aire libre, asistir con ropa cómoda, tenis o botas.   </li>
<li> El ingreso de mascotas no está permitido.  </li>
<li> Contar con cadena en caso de tener bicicleta como medio de transporte. Se dejará en el biciparqueadero del Jardín.  </li>
<li> No se permite el ingreso de alimentos ni bebidas.  </li>
<li> El Jardín Botánico está comprometido con la protección de sus datos personales. Por ello, la información y datos de contacto que Usted ha comparte aquí para el registro, se acogen a la Política de Seguridad de Privacidad y Seguridad de la Información, así como la de Protección de Datos Personales, tal como lo dispone la normatividad vigente.  </li>
<li> La actividad está sujeta a cambios por situaciones climáticas o de otra índole que implique riesgo en general.  </li>
<li> Después de la visita al Jardín Botánico se diagnostica con Covid-19, debe reportarlo de inmediato a las autoridades de salud y al teléfono 4377060. Por su salud y la de quienes asistieron #BogotáSeSabeMover.   </li>';
			$msj .= '</ol>';

			$msj .= "<img src=" . base_url($infoReserva[0]['qr_code_img']) . " class='img-rounded' width='200' height='200' />";
			$msj .= '<br>';
			$msj .= '<strong>Código para el ingreso a las instalaciones</strong>';

			$mensaje = "<p>$msj</p>
						<p>Cordialmente,</p>
						<p><strong>Jardín Botánico de Bogotá</strong></p>";		

			require_once(APPPATH.'libraries/PHPMailer_5.2.4/class.phpmailer.php');
            $mail = new PHPMailer(true);

            try {
                    $mail->IsSMTP(); // set mailer to use SMTP
                    $mail->Host = $paramHost; // specif smtp server
                    $mail->SMTPSecure= "tls"; // Used instead of TLS when only POP mail is selected
                    $mail->Port = 587; // Used instead of 587 when only POP mail is selected
                    $mail->SMTPAuth = true;
					$mail->Username = $paramUsername; // SMTP username
                    $mail->Password = $paramPassword; // SMTP password
                    $mail->FromName = $paramFromName;
                    $mail->From = $paramUsername;
                    $mail->AddAddress($to, 'Usuario JJB Reservas');
                    $mail->WordWrap = 50;
                    $mail->CharSet = 'UTF-8';
                    $mail->IsHTML(true); // set email format to HTML
                    $mail->Subject = 'TERCER ESPACIO DE DIÁLOGO CIUDADANO';

                    $mail->Body = nl2br ($mensaje,false);

                    if($mail->Send()) {

                    	return TRUE;
                        $this->session->set_flashdata('retorno_exito', 'Creaci&oacute;n de usuario exitosa!. La informaci&oacute;n para activar su cuenta fu&eacute; enviada al correo registrado, recuerde aceptar los t&eacute;rminos y condiciones y cambiar su contrase&ntilde;a');
                        //redirect(base_url(), 'refresh');
                        exit;

                    }else{
                    	return TRUE;
                        $this->session->set_flashdata('retorno_error', 'Se creo la persona, sin embargo no se pudo enviar el correo electr&oacute;nico');
                       // redirect(base_url(), 'refresh');
                        exit;

                    }

                }catch (Exception $e){
                	return TRUE;
                    print_r($e->getMessage());
                    exit;
                }

	}	

    public function refresh()
    {
			$flores = array('orquidea', 'cayena', 'heliconia', 'tulipan', 'brunellia', 'abarema', 'aniba', 'calatola', 'centronia', 'clusia', 'cordia', 'espeletia', 'guarea', 'herrania', 'licania', 'magnolia');

			$i = rand(0, 14);

	        // Captcha configuration
	        $config = array(
				'word'		=> $flores[$i],
			    'font_size'      => 25,
	            'img_path'      => 'images/captcha_images/',
	            'img_url'       => base_url().'images/captcha_images/'
	        );
	        $captcha = create_captcha($config);
	  
	        // Unset previous captcha and store new captcha word
	        $this->session->unset_userdata('captchaCode');
	        $this->session->set_userdata('captchaCode',$captcha['word']);
	  
	        // Display captcha image
	        echo $captcha['image'];
    }


	/**
	 * Consultar Reserva
     * @since 3/3/2021
     * @author BMOTTAG
	 */
    public function consultarRegistro()
	{			
			header('Content-Type: application/json');
			$data = array();
			
			$email = trim($this->security->xss_clean($this->input->post('email')));
			$fecha = trim($this->security->xss_clean($this->input->post('fecha')));
			$celular = trim($this->security->xss_clean($this->input->post('celular')));

			$arrParam = array(
				'email' => $email,
				'fecha' => $fecha,
				'celular' => $celular,
				'estadoReserva' => 1
			);
			$infoReserva = $this->general_model->get_reserva($arrParam);

			if(!$infoReserva)
			{
					$data["result"] = "error";					
					$data["mensaje"] = " Error. No hay reservas con esa información.";
					$this->session->set_flashdata('retornoError', '<strong>Error!!!</strong> No hay reservas con esa información.');
			}else{
					$data['codigoReserva'] = $infoReserva[0]['qr_code_llave'];
					$data["result"] = true;					
					$this->session->set_flashdata('retornoExito', '<strong>Correcto!</strong>');
			}

			echo json_encode($data);
    }

	/**
	 * Evio de de mensaje de texto
     * @since 13/3/2021
     * @author BMOTTAG
	 */
    public function envioSMS($idReserva)
    {
			$arrParam = array("idReserva" => $idReserva);
			$infoReserva = $this->general_model->get_reserva_info($arrParam);

			$arrParam = array("idHorario" => $infoReserva[0]['fk_id_horario']);
			$infoHorario = $this->general_model->get_horario_info($arrParam);
			
			$nombreUsuario = $infoReserva[0]['nombre_completo'];
			$numeroContacto = $infoReserva[0]['numero_contacto'];
			$enlace = base_url('calendario/registro/' . $infoReserva[0]['qr_code_llave']); 

			//busco datos parametricos de configuracion para envio de mensaje de texto
			$arrParam = array(
				"table" => "parametros",
				"order" => "id_parametro",
				"id" => "x"
			);
			$parametric = $this->general_model->get_basic_search($arrParam);

			$paramAccount = $parametric[4]["parametro_valor"];
			$paramApiKey = $parametric[5]["parametro_valor"];
			$paramToken = $parametric[6]["parametro_valor"];
						
			$ch=curl_init();

			$post = array(
			'account' => $paramAccount, //número de usuario
			'apiKey' => $paramApiKey, //clave API del usuario
			'token' => $paramToken, // Token de usuario
			//'toNumber' => '573162490927', //número de destino
			'toNumber' => '57' . $numeroContacto, //número de destino
			'sms' => 'Senor(a) ' . $nombreUsuario . ' su reserva para el JBB ha sido aprobada, puede revisarla en el siguiente enlace ' . $enlace, // mensaje de texto
			'flash' => '0', //mensaje tipo flash
			'sendDate'=> time(), //fecha de envío del mensaje
			'isPriority' => 0, //mensaje prioritario
			'sc'=> '899991', //código corto para envío del mensaje de texto
			'request_dlvr_rcpt' => 0, //mensaje de texto con confirmación de entrega al celular
			);

			$url = "https://api101.hablame.co/api/sms/v2.1/send/"; //endPoint: Primario
			curl_setopt ($ch,CURLOPT_URL,$url) ;
			curl_setopt ($ch,CURLOPT_POST,1);
			curl_setopt ($ch,CURLOPT_POSTFIELDS, $post);
			curl_setopt ($ch,CURLOPT_RETURNTRANSFER, true);
			curl_setopt ($ch,CURLOPT_CONNECTTIMEOUT ,3);
			curl_setopt ($ch,CURLOPT_TIMEOUT, 20);
			$response= curl_exec($ch);
			curl_close($ch);
			$response= json_decode($response ,true) ;

			//La respuesta estará alojada en la variable $response

			if ($response["status"]== '1x000' ){
			return TRUE;
			} else {
			echo 'Ha ocurrido un error:'.$response["error_description"].'('.$response ["status" ]. ')'. PHP_EOL;
			}
			
	}
	

	
	
}