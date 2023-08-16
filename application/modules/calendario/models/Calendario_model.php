<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

	class Calendario_model extends CI_Model {
		
		/**
		 * Guardar la informacion de la reserva
		 * @since 13/2/2021
		 */
		public function guardarReserva($pass) 
		{
				$email = trim($this->security->xss_clean($this->input->post('email')));
				$fecha = trim($this->security->xss_clean($this->input->post('fecha')));
				$celular = trim($this->security->xss_clean($this->input->post('celular')));
				$contrato = trim($this->security->xss_clean($this->input->post('contrato')));
				$dependencia = trim($this->security->xss_clean($this->input->post('idDependencia')));
				$data = array(
					'fk_id_horario' => $this->input->post('hddIdHorario'),
					'correo_electronico' => $email,
					'numero_contacto' => $celular,
					'numero_contrato' => $contrato,
					'id_dependencia' => $dependencia
				);
				$query = $this->db->insert('reservas', $data);
				$idReserva = $this->db->insert_id();
				//actualizo la url del codigo QR
				$path = $pass . $idReserva;
				$rutaQRcode = "images/reservas/QR/" . $path . "_qr_code.png";
				//actualizo campo con el path encriptado
				$sql = "UPDATE reservas SET qr_code_llave = '$path', qr_code_img = '$rutaQRcode' WHERE id_reserva = $idReserva";
				$query = $this->db->query($sql);
				if ($query) {
					return $idReserva;
				} else {
					return false;
				}
		}

		/**
		 * Guardar usuarios
		 * @since 13/2/2021
		 */
		public function guardarUsuarios($idReserva) 
		{
				$usuarios = $this->input->post('name');
				$numeroCupos=0;
				foreach ($usuarios as $valor) 
				{
					$cleanName = trim($this->security->xss_clean($valor));
					if($cleanName!= '')
					{
							$data = array(
								'fk_id_reserva' => $idReserva,
								'nombre_completo' => $cleanName
							);
							$query = $this->db->insert('reservas_usuarios', $data);
							$numeroCupos++;
					}
				}
				if ($query) {
					return $numeroCupos;
				} else {
					return false;
				}
		}

		/**
		 * Actualizar el numero de cupos de la reserva
		 * @since 13/2/2021
		 */
		public function actualizarReserva($arrData) 
		{				
				$data = array(
					'numero_cupos_usados' => $arrData['numeroCupos']
				);
				$this->db->where('id_reserva',  $arrData['idReserva']);
				$query = $this->db->update('reservas', $data);
				if ($query) {
					return true;
				} else {
					return false;
				}
		}

		/**
		 * Actualizar el numero de cupos restante del horario
		 * @since 13/2/2021
		 */
		public function actualizarHorarios($arrData) 
		{				
				$data = array(
					'numero_cupos_restantes' => $arrData['NumeroCuposRestantes'],
					'estado' => $arrData['estado'],
					'disponible' => $arrData['disponibilidad']
				);
				$this->db->where('id_horario',  $arrData['idHorario']);
				$query = $this->db->update('horarios', $data);
				if ($query) {
					return true;
				} else {
					return false;
				}
		}

		/**
		 * Habilitar o desahilitar horaio
		 * @since 13/2/2021
		 */
		public function habilitarHorario($arrData) 
		{				
				$data = array(
					'disponible' => $arrData['disponibilidad'],
					'fecha_bloqueo' => date("Y-m-d G:i:s")
				);
				$this->db->where('id_horario',  $arrData['idHorario']);
				$query = $this->db->update('horarios', $data);
				if ($query) {
					return true;
				} else {
					return false;
				}
		}

		/**
		 * Actualizar estado de a reserva
		 * @since 17/2/2021
		 */
		public function deshabilitarReserva($arrData) 
		{				
				$data = array(
					'estado_reserva' => 2,
					'fecha_cancelacion' => date("Y-m-d G:i:s")
				);
				$this->db->where('id_reserva',  $arrData['idReserva']);
				$query = $this->db->update('reservas', $data);
				if ($query) {
					return true;
				} else {
					return false;
				}
		}

		/**
		 * Obtener el ultimo horario
		 * @since 15/08/2023
		 */
		public function get_horarioDisponible() 
		{
				$fecha_actual = date('Y-m-d H:m:s');
				$this->db->select_min('id_horario');
				$this->db->where('hora_inicial >=', $fecha_actual);
				$this->db->where('numero_cupos_restantes >', 0);
				$query = $this->db->get('horarios');
				if ($query->num_rows() > 0) {
					return $query->row_array();
				} else {
					return false;
				}
		}
	}