<link href="<?php echo base_url("assets/bootstrap/vendor/fullcalendar/lib/main.css"); ?>" rel="stylesheet">
<script src="<?php echo base_url("assets/bootstrap/vendor/fullcalendar/lib/main.js"); ?>"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

<script>
$(document).ready(function () {
    //Disable full page
    $("body").on("contextmenu",function(e){
        return false;
    });

$( document ).ready(function() {
	var titulo = $('#titulo').val();
	var mensaje = $('#mensaje').val();
	var idHorario = $('#idHorario').val();
    if(titulo!='' && mensaje!=''){
    	Swal.fire({
            title: titulo,
            html: mensaje,
            icon: 'success',
            confirmButtonText: 'Aceptar',
            width: '400px'
        }).then(function() {
            $.ajax ({
		        type: 'POST',
				url: base_url + 'calendario/cargarModalReserva',
		        data: {'idHorario': idHorario},
		        cache: false,
		        success: function (data) {
		            $('#tablaDatos').html(data);
		            $('#modal').modal('toggle')
		        }
		    });
        })
    }
});
    //Disable part of page
    $("#id").on("contextmenu",function(e){
        return false;
    });
});
	
$(function(){ 
	$(".btn-danger").click(function () {	
            $.ajax ({
                type: 'POST',
				url: base_url + 'calendario/cargarModalEliminar',
				data: {'variable': 'cancelar'},
                cache: false,
                success: function (data) {
                    $('#tablaDatos').html(data);
                }
            });
	});
	$(".btn-success").click(function () {	
            $.ajax ({
                type: 'POST',
				url: base_url + 'calendario/cargarModalEliminar',
				data: {'variable': 'consultar'},
                cache: false,
                success: function (data) {
                    $('#tablaDatos').html(data);
                }
            });
	});
});
</script>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		var calendarEl = document.getElementById('calendar');
		var calendar = new FullCalendar.Calendar(calendarEl, {
			expandRows: true,
			//height: 1650,
			height: 300,
			//locale: 'esLocale',
			headerToolbar: {
				left: 'prev,next today',
				center: 'title',
				right: 'timeGridWeek,timeGridDay'
				//right: 'listDay,listWeek'
			},
			// customize the button names,
			// otherwise they'd all just say "list"
			views: {
				timeGridDay: { buttonText: 'Día' },
				timeGridWeek: { buttonText: 'Semana' }
			},
			buttonText: { today:    'Hoy' },
			noEventsText: 'No hay registros',
			firstDay: 1, //para iniciar en lunes
			initialView: 'timeGridWeek',
			navLinks: true, // can click day/week names to navigate views
			//businessHours: true, // display business hours
			editable: true,
			dayMaxEvents: true, // allow "more" link when too many events
			allDaySlot: false,
			//slotMinTime: '8:30',
			//slotMaxTime: '17:15',
			//slotDuration: '00:15:00',
			//slotLabelInterval: '00:15',
			slotMinTime: '16:00',
			slotMaxTime: '18:00',
			slotDuration: '01:00:00',
			slotLabelInterval: '00:60', 
			events: {
				url: 'calendario/consulta',
				method: 'POST',
				extraParams: {
					custom_param1: 'something',
					custom_param2: 'somethingelse'
				},
				failure: function() {
					alert('Error al cargar los eventos!');
				},
				color: 'green',   // a non-ajax option
				textColor: 'black' // a non-ajax option
			},
			eventClick: function(arg) {
				var oID = arg.event.id;
			    $.ajax ({
			        type: 'POST',
					url: base_url + 'calendario/cargarModalReserva',
			        data: {'idHorario': oID},
			        cache: false,
			        success: function (data) {
			            $('#tablaDatos').html(data);
			            $('#modal').modal('toggle')
			        }
			    });
			}
    	});
    	calendar.render();
  	});
</script>

<div id="page-wrapper">
	<br>
	<div class="row">
		<div class="col-lg-12">	
            <div class="panel panel-default">
			<input type="hidden" id="titulo" name="titulo" value="<?php echo $info?$info[0]['parametro_valor']:""; ?>"/>
			<input type="hidden" id="mensaje" name="mensaje" value="<?php echo $infoMsj?$infoMsj[0]['parametro_valor']:""; ?>"/>
			<input type="hidden" id="idHorario" name="idHorario" value="<?php echo $idHorario?$idHorario['id_horario']:""; ?>"/>
                <div class="panel-body">
                	<div class="row">
                		<div class="col-lg-6">
                			<h6 class="lead">
                				<?php echo $info[0]['parametro_valor']; ?>
                			</h6>
                		</div>
                		<div class="col-lg-6">	
                			<p class="text-right text-danger">
                				<small>Para cancelar su visita haga click en el siguiente botón</small>
								<button type="button" class="btn btn-danger btn-xs" data-toggle="modal" data-target="#modal" id="x" >
									Cancelar Reserva <span class="glyphicon glyphicon-remove" aria-hidden="true">
								</button>
                			</p>
                			<p class="text-right text-success">
                				<small>Para consultar información de su visita haga click en el siguiente botón</small>
								<button type="button" class="btn btn-success btn-xs" data-toggle="modal" data-target="#modal" id="x" >
									Consultar Reserva <span class="glyphicon glyphicon-search" aria-hidden="true">
								</button>
                			</p>
            			</div>
                	</div>
                	<!--<small>
	                    <p>Registre su visita en dos simples pasos:</p>
					    <ol>
					    <li> 
					    	<strong>Seleccione la fecha y hora: </strong>
					    	Verifique que la casilla seleccionada tenga cupo. Si está en verde es que aún no hay nadie inscrito y si está en amarillo (hay cupos). Los horarios que se ven en color rojo no tienen cupo.
					    </li>
					    <li>
					    	<strong>Ingrese sus datos: </strong>
					    	Registre los Nombres de cada uno de los asistentes (máximo 4 personas, incluyendo niños mayores de 3 años), un número celular de contacto y el correo electrónico donde recibirá el código QR de verificación. No olvide llenar el campo de comprobación (captcha) que consiste en escribir la palabra que está en colores, en el campo dispuesto para ello.
						</li>
						</ol>
						<ul>
							<li>
							Si después de tu visita al Jardín Botánico te diagnostican con Covid-19 debes reportarlo de inmediato a las autoridades de salud y al teléfono 4377060. Por tu salud y la de todos, Bogotá se sabe mover.Si después de tu visita al Jardín Botánico te diagnostican con Covid-19 debes reportarlo de inmediato a las autoridades de salud y al teléfono 4377060. Por tu salud y la de todos, Bogotá se sabe mover.
							</li>

							<li>
							Amablemente nos permitimos informar a la ciudadanía que durante la mañana del primer lunes (O martes cuando el lunes es festivo) de cada semana el Jardín Botánico de Bogotá cierra sus puertas con el fin de realizar jornadas de mantenimiento integrales.
							</li>
							<li>
							Finalmente, se recuerda que todos los recorridos guiados están sujetos a cancelación si las condiciones climáticas o de seguridad así lo determinan, con el fin de evitar riesgos y aglomeraciones. Gracias por su comprensión.
							</li>
						</ul>
					</small>-->
					<small>
						<?php echo $infoMsj[0]['parametro_valor']; ?>
					</small>
                </div>
            </div>
        </div>
	</div>
	<div class="row">
		<div class="col-lg-12">
			<div id='calendar'></div>
			<br>
		</div>
	</div>
</div>

<!--INICIO Modal para RESERVAS -->
<div class="modal fade text-center" id="modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">    
	<div class="modal-dialog" role="document">
		<div class="modal-content" id="tablaDatos">

		</div>
	</div>
</div>                       
<!--FIN Modal para RESERVAS -->