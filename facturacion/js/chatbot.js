// js/chatbot.js - VERSIÓN COMPLETA MEJORADA PARA QUICK-ACTIONS

document.addEventListener('DOMContentLoaded', function() {
	cargarEstilosChatbot();
    
    // Esperar un momento para asegurar que todo esté cargado
    setTimeout(function() {
        crearChatbotSISFACT();
    }, 1500);
});

// Función para obtener el saludo según la hora del día con el nombre del usuario en negritas
function obtenerSaludo() {
    const hora = new Date().getHours();
    
    // Obtener el nombre desde la variable global (de PHP)
    const nombreUsuario = window.usuarioSISFACT || null;
    
    let saludoBase = "";
    if (hora >= 5 && hora < 12) {
        saludoBase = "🌅 ¡Buenos días";
    } else if (hora >= 12 && hora < 19) {
        saludoBase = "☀️ ¡Buenas tardes";
    } else {
        saludoBase = "🌙 ¡Buenas noches";
    }
    
    // Si tenemos nombre, lo incluimos en negritas
    if (nombreUsuario && nombreUsuario !== 'Usuario') {
        return `${saludoBase}, <strong style="color: #2e7d5e;">${nombreUsuario}</strong>!`;
    } else {
        return `${saludoBase}, <strong>bienvenido</strong>!`;
    }
}

// Función para conectar el botón de quick-actions (VERSIÓN SILENCIOSA)
function conectarBotonQuickActions() {
    const quickActionBtn = document.getElementById('chatbotQuickAction');
    
    if (quickActionBtn) {
        quickActionBtn.removeAttribute('onclick');
        quickActionBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleChatbot();
        });
    } else {
        const posibleBoton = document.querySelector('.action-chatbot');
        if (posibleBoton) {
            posibleBoton.id = 'chatbotQuickAction';
            conectarBotonQuickActions();
        }
        // No mostrar nada, simplemente no hay botón
    }
}

// Función para mostrar/ocultar el chatbot
function toggleChatbot() {
    const chatWindow = document.getElementById('chat-window-final');
    const robotContainer = document.getElementById('robot-asistente-container');
    const robotAvatar = document.getElementById('robot-avatar-vuelo');
    
    if (!chatWindow) {
        console.error('❌ No se encontró la ventana del chatbot');
        return;
    }
    
    const isOpen = chatWindow.style.display === 'flex';
    
    if (isOpen) {
        // Cerrar chatbot
        chatWindow.style.display = 'none';
        
        setTimeout(() => {
            if (robotAvatar && !robotAvatar.classList.contains('modo-bola')) {
                robotAvatar.style.width = '65px';
                robotAvatar.style.height = '65px';
                robotAvatar.style.borderRadius = '50%';
                robotAvatar.style.background = '#1a1a1a url("assets/logobot.png") no-repeat center center';
                robotAvatar.style.backgroundSize = 'contain';
                robotAvatar.style.border = '2px solid #2e7d5e';
                robotAvatar.style.padding = '5px';
                robotAvatar.classList.add('luz-pulsante-verde', 'modo-bola');
            }
        }, 300);
    } else {
        // Abrir chatbot - POSICIONAR JUNTO AL ROBOT
        if (robotContainer) {
            const robotRect = robotContainer.getBoundingClientRect();
            
            // Calcular posición: a la derecha del robot
            let left = robotRect.right + 10;
            let top = robotRect.top;
            
            // Obtener dimensiones del chat
            const chatWidth = 380;
            const chatHeight = 550;
            
            // Ajustar si se sale por la derecha
            if (left + chatWidth > window.innerWidth) {
                left = robotRect.left - chatWidth - 10;
            }
            
            // Ajustar si se sale por la izquierda
            if (left < 10) {
                left = 10;
            }
            
            // Ajustar si se sale por abajo
            if (top + chatHeight > window.innerHeight) {
                top = window.innerHeight - chatHeight - 10;
            }
            
            // Ajustar si se sale por arriba
            if (top < 10) {
                top = 10;
            }
            
            chatWindow.style.left = left + 'px';
            chatWindow.style.top = top + 'px';
            chatWindow.style.bottom = 'auto';
            chatWindow.style.right = 'auto';
        }
        
        chatWindow.style.display = 'flex';
        
        // Expandir el robot
        if (robotAvatar) {
            robotAvatar.style.width = '85px';
            robotAvatar.style.height = '85px';
            robotAvatar.style.borderRadius = '0';
            robotAvatar.style.background = 'url("assets/logobot.png") no-repeat center center';
            robotAvatar.style.backgroundSize = 'contain';
            robotAvatar.style.backgroundColor = 'transparent';
            robotAvatar.style.border = 'none';
            robotAvatar.style.padding = '0';
            robotAvatar.classList.remove('luz-pulsante-verde', 'modo-bola');
        }
        
        // Enfocar el input
        setTimeout(() => {
            const input = document.getElementById('chat-input-final');
            if (input) input.focus();
        }, 300);
    }
    
    // Cerrar el menú de quick-actions si está abierto
    const expandedActions = document.getElementById('quickActionsExpanded');
    const mainQuickAction = document.getElementById('mainQuickAction');
    
    if (expandedActions && expandedActions.classList.contains('show')) {
        expandedActions.classList.remove('show');
        if (mainQuickAction) {
            mainQuickAction.innerHTML = '<i class="fas fa-plus"></i>';
        }
    }
	
	    reiniciarRecordatorios();
}





// Función para autocompletar input con texto
function autocompletarInput(texto) {
    const input = document.getElementById('chat-input-final');
    if (input) {
        input.value = texto;
        input.focus();
    }
}

// Función para enviar mensaje directamente
function enviarMensajeDirecto(texto) {
    autocompletarInput(texto);
    // Pequeño delay para que se vea el autocompletado antes de enviar
    setTimeout(() => {
        const sendButton = document.getElementById('send-message-final');
        if (sendButton) {
            sendButton.click();
        }
    }, 100);
}
// =========================================================================
// FUNCIÓN DE NOTIFICACIONES - DEFINIDA ANTES DE USARSE
// =========================================================================

/**
 * Muestra una notificación temporal en la pantalla
 * @param {string} mensaje - El mensaje a mostrar
 */
function mostrarNotificacion(mensaje) {
    // Crear elemento de notificación
    const notificacion = document.createElement('div');
    notificacion.style.cssText = `
        position: fixed;
        bottom: 120px;
        left: 30px;
        background: linear-gradient(135deg, #1e3a5f 0%, #0d2b4a 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        z-index: 1000000;
        animation: slideInRight 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: 0.3px;
    `;
    
    // Icono según el tipo de mensaje
    let icono = '';
    if (mensaje.includes('✓') || mensaje.includes('copiada')) {
        icono = '<i class="fas fa-check-circle" style="color: #4caf50; font-size: 16px;"></i>';
    } else if (mensaje.includes('❌') || mensaje.includes('error')) {
        icono = '<i class="fas fa-exclamation-circle" style="color: #f44336; font-size: 16px;"></i>';
    } else if (mensaje.includes('⚠️') || mensaje.includes('advertencia')) {
        icono = '<i class="fas fa-exclamation-triangle" style="color: #ff9800; font-size: 16px;"></i>';
    } else {
        icono = '<i class="fas fa-info-circle" style="color: #0078d4; font-size: 16px;"></i>';
    }
    
    notificacion.innerHTML = `${icono} ${mensaje}`;
    
    document.body.appendChild(notificacion);
    
    // Efecto de pulso al aparecer
    notificacion.style.animation = 'pulse 2s infinite';
    
    // Eliminar después de 2.5 segundos
    setTimeout(() => {
        notificacion.style.animation = 'slideOutRight 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        notificacion.style.boxShadow = 'none';
        setTimeout(() => {
            notificacion.remove();
        }, 300);
    }, 2500);
}


// =========================================================================
// FUNCIÓN PARA MOSTRAR RECORDATORIO PERIÓDICO
// =========================================================================

let intervaloRecordatorio = null;
let recordatorioActivo = true;
let mouseEncimaDelGlobo = false;

// Función para mostrar recordatorio (mensaje en el globo)
function mostrarRecordatorio() {
    const chatWindow = document.getElementById('chat-window-final');
    const robotContainer = document.getElementById('robot-asistente-container');
    const globoTexto = document.getElementById('robot-globo-texto');
    const robotAvatar = document.getElementById('robot-avatar-vuelo');
    
    // Validaciones iniciales
    if (!chatWindow || chatWindow.style.display === 'flex') return;
    if (!robotContainer || robotContainer.style.display === 'none') return;
    if (!globoTexto || !robotAvatar) return;
    if (globoTexto.style.display === 'block' && globoTexto.style.opacity === '1') return;
    
	const mensajes = [
			{ emoji: '👋', titulo: '¡Hola!', mensaje: 'Recuerda que estoy aquí para ayudarte con SISFACT. ¿Necesitas algo?' },
			{ emoji: '🤖', titulo: '¡Sigo aquí!', mensaje: 'Estoy disponible 24/7 para consultar facturas, clientes y más.' },
			{ emoji: '💡', titulo: '¿Sabías que...', mensaje: 'Puedes preguntarme por "ingresos del mes" en cualquier momento.' },
			{ emoji: '📊', titulo: 'Reportes', mensaje: 'Escribe "reportes" para ver las estadísticas actuales del sistema.' },
			{ emoji: '🎯', titulo: 'Ayuda', mensaje: 'Escribe "ayuda" para ver todos los comandos disponibles.' },
			{ emoji: '💰', titulo: 'Pendientes', mensaje: '¿Quieres revisar si hay facturas pendientes por cobrar hoy?' },
			{ emoji: '📅', titulo: 'Cierre Mensual', mensaje: 'Estamos cerca del cierre. ¿Necesitas un reporte de los últimos días?' },
			{ emoji: '👥', titulo: 'Clientes', mensaje: 'Puedo mostrarte la lista de tus clientes más activos rápidamente.' },
			{ emoji: '🛠️', titulo: 'Servicios', mensaje: 'Pregúntame por los "servicios más solicitados" para ver tendencias.' },
			{ emoji: '✨', titulo: 'Tip Pro', mensaje: 'Escribe "abrir facturas" para ir directo a la sección de ventas.' },
			{ emoji: '📈', titulo: 'Comparativa', mensaje: 'Puedo comparar tus ingresos actuales con la meta del plan.' },
			{ emoji: '🛡️', titulo: 'Seguridad', mensaje: 'Recuerda cerrar tu sesión si terminas de trabajar por hoy.' },
			{ emoji: '⏳', titulo: 'Vencimientos', mensaje: '¿Quieres saber si hay contratos o facturas por vencer pronto?' },
			{ emoji: '😂', titulo: '¿Un descanso?', mensaje: 'Pídeme un "chiste" si necesitas un minuto de relax.' },
			{ emoji: '🚀', titulo: 'Eficiencia', mensaje: 'Puedo buscar cualquier cliente solo con escribir su nombre.' }
		];
    
    const randomMsg = mensajes[Math.floor(Math.random() * mensajes.length)];
    const contenidoOriginal = globoTexto.innerHTML;
    
    // 1. Inyectamos el HTML pero lo mantenemos invisible para medirlo
    globoTexto.innerHTML = `
        <div style="
            position: relative; 
            background: #ffffff; 
            color: #333; 
            padding: 15px; 
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.3); 
            font-size: 14px; 
            line-height: 1.5; 
            max-width: 280px; 
            border-left: 5px solid #2e7d5e;
            font-family: 'Segoe UI', Roboto, sans-serif;
            text-align: center;
        ">
            <div style="font-size: 40px; margin-bottom: 8px;">${randomMsg.emoji}</div>
            <strong style="font-size: 16px; color: #2e7d5e;">${randomMsg.titulo}</strong>
            <div style="font-size: 13px; color: black; margin-top: 8px;">${randomMsg.mensaje}</div>
            <div style="font-size: 12px; color: red; font-weight: bold; margin-top: 10px; border-top: 1px solid #eee; padding-top: 6px;">
                💡 Haz clic en mí para abrir el chat
            </div>
            
            <div id="triangulo-recordatorio" style="
                position: absolute; 
                bottom: -10px; 
                left: 30px; 
                width: 0; height: 0; 
                border-left: 12px solid transparent; 
                border-right: 12px solid transparent; 
                border-top: 12px solid white;
            "></div>
        </div>
    `;
    
    // 2. PREPARACIÓN PARA CÁLCULO
    globoTexto.style.display = 'block';
    globoTexto.style.opacity = '0';
    globoTexto.style.visibility = 'hidden'; 
    globoTexto.style.transform = 'translateY(15px) scale(0.9)';

    // Esperar un milisegundo para que el navegador renderice el tamaño
    setTimeout(() => {
        const robotRect = robotAvatar.getBoundingClientRect();
        const globoRect = globoTexto.getBoundingClientRect();
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        
        let left = robotRect.left;
        let top = robotRect.top - globoRect.height - 20; // 20px de espacio arriba
        
        // CORRECCIÓN: Si se sale por arriba, ponerlo abajo
        if (top < 10) {
            top = robotRect.bottom + 20;
            const triangulo = document.getElementById('triangulo-recordatorio');
            if (triangulo) {
                triangulo.style.top = '-10px';
                triangulo.style.bottom = 'auto';
                triangulo.style.borderTop = 'none';
                triangulo.style.borderBottom = '12px solid white';
            }
        } else {
            // Posición normal: Arriba del robot
            const triangulo = document.getElementById('triangulo-recordatorio');
            if (triangulo) {
                triangulo.style.top = 'auto';
                triangulo.style.bottom = '-10px';
                triangulo.style.borderBottom = 'none';
                triangulo.style.borderTop = '12px solid white';
            }
        }
        
        // Ajustar horizontalmente si se sale por la derecha
        if (left + globoRect.width > windowWidth - 15) {
            left = windowWidth - globoRect.width - 15;
        }
        // Ajustar horizontalmente si se sale por la izquierda
        if (left < 10) left = 10;
        
        // Aplicar posiciones calculadas
        globoTexto.style.left = left + 'px';
        globoTexto.style.top = top + 'px';
        
        // 3. MOSTRAR CON ANIMACIÓN
        globoTexto.style.visibility = 'visible';
        globoTexto.style.opacity = '1';
        globoTexto.style.transform = 'translateY(0) scale(1)';
		
		robotAvatar.classList.add('animar-brillar');
		
		 // Mostrar el globo
        globoTexto.style.display = 'block';
    }, 50);
    
// Ocultar después de 5 segundos, PERO verificar si el mouse está encima
    setTimeout(() => {
        const intentarCerrar = () => {
            // Si el mouse está encima, esperar 1 segundo más y volver a intentar
            if (mouseEncimaDelGlobo) {
                setTimeout(intentarCerrar, 1000);
            } else {
                // Si el mouse no está, procedemos a ocultar
                if (globoTexto && globoTexto.style.opacity === '1') {
                    globoTexto.style.opacity = '0';
                    globoTexto.style.transform = 'translateY(15px) scale(0.9)';
                    setTimeout(() => {
                        if (globoTexto && globoTexto.style.opacity === '0') {
                            globoTexto.style.display = 'none';
                            if (contenidoOriginal) globoTexto.innerHTML = contenidoOriginal;
                        }
                    }, 400);
                }
            }
        };
        
        intentarCerrar();
    }, 5000);
}

// Función para iniciar el intervalo de recordatorios
function iniciarRecordatorios() {
    // Limpiar intervalo existente si hay
    if (intervaloRecordatorio) {
        clearInterval(intervaloRecordatorio);
    }
    
    // Iniciar nuevo intervalo: cada 3 minutos 
    intervaloRecordatorio = setInterval(() => {
        mostrarRecordatorio();
    }, 180000); // 3 minutos 
}

// Función para detener los recordatorios
function detenerRecordatorios() {
    if (intervaloRecordatorio) {
        clearInterval(intervaloRecordatorio);
        intervaloRecordatorio = null;
    }
}

// Función para reiniciar recordatorios (útil cuando se abre/cierra el chat)
function reiniciarRecordatorios() {
    const chatWindow = document.getElementById('chat-window-final');
    const chatEstaAbierto = chatWindow && chatWindow.style.display === 'flex';
    
    if (chatEstaAbierto) {
        // Si el chat está abierto, detener recordatorios
        detenerRecordatorios();
    } else {
        // Si el chat está cerrado, iniciar recordatorios
        iniciarRecordatorios();
    }
}


// ==================== EFECTO ROBOT ASISTENTE PRO CON LUZ PULSANTE ====================

function inicializarRobotVisual() {
    // 1. Contenedor principal
    const robotContainer = document.createElement('div');
    robotContainer.id = 'robot-asistente-container';
    robotContainer.style.cssText = `
        position: fixed;
        bottom: 25px;
        left: 25px;
        z-index: 1000000;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        cursor: pointer;
        transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    `;

    // Obtener el nombre desde la variable global (de PHP)
    const nombreUsuario = window.usuarioSISFACT || null;
    const saludo = obtenerSaludo();

    // 2. Globo de texto con Botón de Cierre (X) - VERSIÓN MEJORADA CON POSICIONAMIENTO DINÁMICO
    const globoTexto = document.createElement('div');
    globoTexto.id = 'robot-globo-texto';
    globoTexto.style.cssText = `
        opacity: 0;
        display: none;
        transition: all 0.4s ease;
        transform: translateY(15px) scale(0.9);
        margin-bottom: 15px;
        position: fixed;
        z-index: 1000001;
        max-width: 320px;
        min-width: 280px;
    `;
    
    globoTexto.innerHTML = `
        <div style="
            position: relative; 
            background: #ffffff; 
            color: #333; 
            padding: 15px; 
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); 
            font-size: 14px; 
            line-height: 1.5; 
            border-left: 5px solid #2e7d5e;
            font-family: 'Segoe UI', Roboto, sans-serif;
        ">
            <!-- Botón X para cerrar globo -->
            <div id="cerrar-globo-robot" style="
                position: absolute;
                top: 8px;
                right: 12px;
                font-size: 12px;
                color: #aaa;
                cursor: pointer;
                transition: color 0.2s;
                z-index: 10;
            " onmouseenter="this.style.color='#f44336'" onmouseleave="this.style.color='#aaa'">
                <i class="fas fa-times"></i>
            </div>

            <div style="margin-right: 20px;">
                <i class="fas fa-robot" style="color: #2e7d5e; margin-right: 5px;"></i> 
                ${saludo}, soy tu <strong>Robot Asistente</strong> de <span style="color: #0078d4; font-weight: bold;">SISFACT PDL VISIONES</span>. 🚀 Pregúntame lo que quieras, estaré <span style="color: #2e7d5e; font-weight: bold;">24/7 a tu disposición</span> para ayudarte con facturas, reportes, informes, estadísticas, chistes y mucho más. Solo haz clic en mí cuando lo desees.
            </div>
            
            <!-- Triangulito del globo (apuntando hacia abajo) -->
            <div id="globo-triangulo" style="
                position: absolute; 
                bottom: -10px; 
                left: 30px; 
                width: 0; height: 0; 
                border-left: 12px solid transparent; 
                border-right: 12px solid transparent; 
                border-top: 12px solid white;
            "></div>
        </div>
    `;
	
// Detectar cuando el mouse entra o sale del globo
    globoTexto.addEventListener('mouseenter', () => {
        mouseEncimaDelGlobo = true;
    });

    globoTexto.addEventListener('mouseleave', () => {
        mouseEncimaDelGlobo = false;
        // Si el mouse sale, programamos el cierre en 2 segundos
        setTimeout(() => {
            if (!mouseEncimaDelGlobo) {
                ocultarGlobo(); // Esta es tu función que ya tienes para cerrar
            }
        }, 2000);
    });
	
    // 3. Avatar del Robot
    const robotAvatar = document.createElement('div');
    robotAvatar.id = 'robot-avatar-vuelo';
    robotAvatar.style.cssText = `
        width: 65px; 
        height: 65px; 
        border-radius: 50%;
        background: #1a1a1a url('assets/logobot.png') no-repeat center center;
        background-size: contain;
        transition: all 0.5s ease;
        filter: drop-shadow(0 8px 15px rgba(0,0,0,0.3));
        border: 2px solid #2e7d5e;
        padding: 5px; 
    `;
    
    robotAvatar.classList.add('luz-pulsante-verde', 'modo-bola');

    robotContainer.appendChild(globoTexto);
    robotContainer.appendChild(robotAvatar);
    document.body.appendChild(robotContainer);

    // Función para posicionar el globo correctamente (siempre arriba del robot)
    function posicionarGlobo() {
        const robotRect = robotAvatar.getBoundingClientRect();
        const globoRect = globoTexto.getBoundingClientRect();
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        
        // Posición inicial: arriba del robot
        let left = robotRect.left;
        let top = robotRect.top - globoRect.height - 15;
        
        // Ajustar horizontalmente si se sale por la derecha
        if (left + globoRect.width > windowWidth - 10) {
            left = windowWidth - globoRect.width - 10;
        }
        
        // Ajustar horizontalmente si se sale por la izquierda
        if (left < 10) {
            left = 10;
        }
        
        // Si no hay espacio arriba, mostrar abajo
        if (top < 10) {
            top = robotRect.bottom + 15;
            // Cambiar orientación del triángulo (apuntando hacia arriba)
            const triangulo = document.getElementById('globo-triangulo');
            if (triangulo) {
                triangulo.style.top = '-10px';
                triangulo.style.bottom = 'auto';
                triangulo.style.borderTop = 'none';
                triangulo.style.borderBottom = '12px solid white';
            }
        } else {
            // Orientación normal (triángulo abajo)
            const triangulo = document.getElementById('globo-triangulo');
            if (triangulo) {
                triangulo.style.top = 'auto';
                triangulo.style.bottom = '-10px';
                triangulo.style.borderBottom = 'none';
                triangulo.style.borderTop = '12px solid white';
            }
        }
        
        // Ajustar la posición del triángulo para que apunte al robot
        const triangulo = document.getElementById('globo-triangulo');
        if (triangulo) {
            let trianguloLeft = robotRect.left - left + (robotRect.width / 2) - 12;
            trianguloLeft = Math.max(10, Math.min(trianguloLeft, globoRect.width - 24));
            triangulo.style.left = trianguloLeft + 'px';
        }
        
        globoTexto.style.left = left + 'px';
        globoTexto.style.top = top + 'px';
    }

    // Función para mostrar el globo con posición calculada
    function mostrarGlobo() {
        // Primero hacer visible para poder medir
        globoTexto.style.display = 'block';
        globoTexto.style.opacity = '0';
        globoTexto.style.transform = 'translateY(15px) scale(0.9)';
        
        // Posicionar
        posicionarGlobo();
        
        // Animar aparición
        setTimeout(() => {
            globoTexto.style.opacity = '1';
            globoTexto.style.transform = 'translateY(0) scale(1)';
        }, 10);
    }

    // Función para ocultar globo
    function ocultarGlobo() {
        globoTexto.style.opacity = '0';
        globoTexto.style.transform = 'translateY(15px) scale(0.9)';
        setTimeout(() => {
            if (globoTexto.style.opacity === '0') {
                globoTexto.style.display = 'none';
                // Restaurar orientación del triángulo
                const triangulo = document.getElementById('globo-triangulo');
                if (triangulo) {
                    triangulo.style.top = 'auto';
                    triangulo.style.bottom = '-10px';
                    triangulo.style.borderBottom = 'none';
                    triangulo.style.borderTop = '12px solid white';
                }
            }
        }, 400);
    }

    // Función para saber si el chat está abierto
    function isChatOpen() {
        const chatWindow = document.getElementById('chat-window-final');
        return chatWindow && chatWindow.style.display === 'flex';
    }

    // Mostrar globo al pasar el mouse (SOLO si el chat está cerrado)
    robotContainer.addEventListener('mouseenter', () => {
        if (!isChatOpen()) {
            mostrarGlobo();
        }
    });

    // Ocultar globo al salir
    robotContainer.addEventListener('mouseleave', () => {
        ocultarGlobo();
    });

    // Cerrar globo desde la X
    document.getElementById('cerrar-globo-robot').addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        ocultarGlobo();
    });

    // Abrir chat al hacer clic en el robot
    robotContainer.addEventListener('click', (e) => {
        if (!e.target.closest('#cerrar-globo-robot')) {
            toggleChatbot();
            ocultarGlobo();
        }
    });

    // Escuchar cambios en el estado del chat
    const observer = new MutationObserver(() => {
        if (isChatOpen()) {
            ocultarGlobo();
        }
    });

    const checkChatInterval = setInterval(() => {
        const chatWindow = document.getElementById('chat-window-final');
        if (chatWindow) {
            observer.observe(chatWindow, { attributes: true, attributeFilter: ['style'] });
            clearInterval(checkChatInterval);
        }
    }, 500);

    // Actualizar posición del globo si la ventana cambia de tamaño
    window.addEventListener('resize', () => {
        if (globoTexto.style.display === 'block' && globoTexto.style.opacity === '1') {
            posicionarGlobo();
        }
    });

    // También actualizar si el scroll cambia
    window.addEventListener('scroll', () => {
        if (globoTexto.style.display === 'block' && globoTexto.style.opacity === '1') {
            posicionarGlobo();
        }
    });

    function ejecutarTransformacion() {
        ocultarGlobo();
        setTimeout(() => {
            robotAvatar.style.width = '65px';
            robotAvatar.style.height = '65px';
            robotAvatar.style.borderRadius = '50%';
            robotAvatar.style.backgroundColor = '#1a1a1a';
            robotAvatar.style.border = '3px solid #2e7d5e';
            robotAvatar.style.padding = '5px';
            robotAvatar.classList.add('luz-pulsante-verde', 'modo-bola');
        }, 400);
    }

    // Verificar saludo pendiente
    setTimeout(() => {
        verificarSaludoPendiente();
    }, 1000);
    
    // Actualizar posición del chat cuando se redimensiona la ventana
    window.addEventListener('resize', function() {
        actualizarPosicionChat();
    });
	
    // Iniciar recordatorios periódicos (cada 5 minutos)
    setTimeout(() => {
        iniciarRecordatorios();
    }, 10000); // Esperar 10 segundos antes del primer recordatorio
    
    // Detectar cuando se abre/cierra el chat para pausar/reanudar recordatorios
    const observerChat = new MutationObserver(() => {
        reiniciarRecordatorios();
    });
    
    const checkChat = setInterval(() => {
        const chatWindow = document.getElementById('chat-window-final');
        if (chatWindow) {
            observerChat.observe(chatWindow, { attributes: true, attributeFilter: ['style'] });
            clearInterval(checkChat);
            reiniciarRecordatorios(); // Verificar estado inicial
        }
    }, 500);
}


// Llama a la función que crea el robot visual
inicializarRobotVisual();

// Hacer que el robot sea arrastrable después de crearlo
setTimeout(() => {
    hacerArrastrableRobot();
}, 500); // Pequeño delay para asegurar que el DOM esté listo


// =========================================================================
// FUNCIÓN PARA HACER ARRASTRABLE EL ROBOT ASISTENTE (SIN PERSISTENCIA)
// =========================================================================
function hacerArrastrableRobot() {
    const robotContainer = document.getElementById('robot-asistente-container');
    if (!robotContainer) return;
    
    let offsetX, offsetY;
    let isDragging = false;
    let startX, startY;
    
    // Evento al presionar el ratón sobre el robot
    robotContainer.addEventListener('mousedown', (e) => {
        // Evitar que el arrastre interfiera con el clic para abrir el chat
        if (e.target.closest('#cerrar-globo-robot')) {
            return; // No arrastrar si se hace clic en el botón de cerrar
        }
        
        e.preventDefault();
        
        // Guardar la posición inicial del mouse
        startX = e.clientX;
        startY = e.clientY;
        
        // Obtener la posición actual del robot
        const rect = robotContainer.getBoundingClientRect();
        offsetX = startX - rect.left;
        offsetY = startY - rect.top;
        
        // Cambiar el cursor mientras se arrastra
        robotContainer.style.cursor = 'grabbing';
        robotContainer.style.transition = 'none'; // Quitar transición durante el arrastre
        
        // Marcar que estamos arrastrando
        isDragging = false; // Se pondrá true solo si hay movimiento
        
        // Cambiar opacidad para feedback visual
        robotContainer.style.opacity = '0.9';
        
        // Agregar eventos globales
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });
    
    function onMouseMove(e) {
        e.preventDefault();
        
        // Calcular el movimiento
        const moveX = Math.abs(e.clientX - startX);
        const moveY = Math.abs(e.clientY - startY);
        
        // Si se movió más de 5px, considerar como arrastre (no clic)
        if (moveX > 5 || moveY > 5) {
            isDragging = true;
        }
        
        // Calcular nueva posición
        let newX = e.clientX - offsetX;
        let newY = e.clientY - offsetY;
        
        // Limitar dentro de la ventana (con un margen)
        const maxX = window.innerWidth - robotContainer.offsetWidth;
        const maxY = window.innerHeight - robotContainer.offsetHeight;
        
        newX = Math.max(0, Math.min(newX, maxX));
        newY = Math.max(0, Math.min(newY, maxY));
        
        // Aplicar nueva posición
        robotContainer.style.left = newX + 'px';
        robotContainer.style.top = newY + 'px';
        robotContainer.style.bottom = 'auto'; // Deshabilitar bottom
        robotContainer.style.right = 'auto';
		
		 actualizarPosicionChat();
    }
    
    function onMouseUp(e) {
        e.preventDefault();
        
        // Restaurar estilos
        robotContainer.style.cursor = 'pointer';
        robotContainer.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        robotContainer.style.opacity = '1';
        
        // Eliminar eventos globales
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        
        // Si fue un arrastre, evitar que se abra el chat
        if (isDragging) {
            // Prevenir el evento click por un momento
            const preventClick = (clickEvent) => {
                clickEvent.stopPropagation();
                clickEvent.preventDefault();
                robotContainer.removeEventListener('click', preventClick);
            };
            robotContainer.addEventListener('click', preventClick);
            
            // Restablecer después de un breve tiempo
            setTimeout(() => {
                robotContainer.removeEventListener('click', preventClick);
            }, 300);
            
            isDragging = false;
        }
        
        // NOTA: NO guardamos la posición en localStorage
        // El robot volverá a su posición inicial al recargar la página
    }
    
    // Ajustar posición si la ventana cambia de tamaño
    window.addEventListener('resize', () => {
        // Solo ajustar si el robot tiene posición personalizada (left/top)
        if (robotContainer.style.left && robotContainer.style.left !== 'auto') {
            const left = parseInt(robotContainer.style.left);
            const top = parseInt(robotContainer.style.top);
            
            if (!isNaN(left) && !isNaN(top)) {
                const maxX = window.innerWidth - robotContainer.offsetWidth;
                const maxY = window.innerHeight - robotContainer.offsetHeight;
                
                let newLeft = Math.min(left, maxX);
                let newTop = Math.min(top, maxY);
                
                if (newLeft !== left || newTop !== top) {
                    robotContainer.style.left = newLeft + 'px';
                    robotContainer.style.top = newTop + 'px';
                }
            }
        }
    });
}


function crearChatbotSISFACT() {
    // Verificar si ya existe
    if (document.getElementById('chat-window-final')) {
        conectarBotonQuickActions();
        return;
    }

    // Crear SOLO la ventana del chat (SIN EL BOTÓN FLOTANTE)
    const chatWindow = document.createElement('div');
    chatWindow.id = 'chat-window-final';
    chatWindow.style.cssText = `
        position: fixed;
        bottom: 100px;
        left: 25px;
        width: 380px;
        height: 550px;
        background: #1f1f1f;
        border: 1px solid #3d3d3d;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        z-index: 999998;
        display: none;
        flex-direction: column;
        overflow: hidden;
        color: white;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    `;

    // Contenido de la ventana del chat - VERSIÓN MEJORADA
    chatWindow.innerHTML = `
<!-- Header mejorado -->
<div style="
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: linear-gradient(135deg, #2d2d2d 0%, #1a1a1a 100%);
    border-bottom: 1px solid #3d3d3d;
">
    <div style="display: flex; align-items: center; gap: 12px;">
        <img src="assets/logobot.png" alt="Logo SISFACT" style="
            width: 35px;
            height: 35px;
            object-fit: contain;
        ">
        <div>
            <h6 style="margin: 0; color: white; font-weight: 600; font-size: 14px;">
                Asistente Virtual SISFACT PDL VISIONES
            </h6>
            <div style="display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                <span style="display: inline-block; width: 6px; height: 6px; background: #4caf50; border-radius: 50%;"></span>
                <span style="font-size: 10px; color: #a6a6a6;">En línea</span>
            </div>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button id="minimize-chat" style="
            background: none;
            border: none;
            color: #a6a6a6;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
            transition: all 0.2s ease;
        " title="Minimizar">🗕</button>
        <button id="close-chat" style="
            background: none;
            border: none;
            color: #a6a6a6;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
            transition: all 0.2s ease;
        " title="Cerrar">✕</button>
    </div>
</div>

        <!-- Área de mensajes mejorada -->
        <div id="chat-messages" style="
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #0d0d0d;
        ">
            <!-- Mensaje de bienvenida mejorado -->
            <div style="
                align-self: flex-start;
                max-width: 100%;
            ">
                <div style="
                    padding: 16px 18px;
                    background: linear-gradient(135deg, #2d2d2d 0%, #1f1f1f 100%);
                    border-radius: 18px;
                    border-bottom-left-radius: 4px;
                    border: 1px solid #3d3d3d;
                    font-size: 13px;
                    color: white;
                    line-height: 1.6;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                ">
<!-- Header con avatar y logo -->
<div style="
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #3d3d3d;
">
    <img src="assets/logobot.png" alt="Logo SISFACT" style="
        width: 45px;
        height: 45px;
        object-fit: contain;
    ">
    <div>
        <div style="
            font-weight: bold;
            font-size: 15px;
            color: #0078d4;
        ">Asistente Virtual SISFACT PDL VISIONES</div>
        <div style="
            font-size: 11px;
            color: #a6a6a6;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 3px;
        ">
            <span style="
                display: inline-block;
                width: 8px;
                height: 8px;
                background: #4caf50;
                border-radius: 50%;
            "></span>
            <span>En línea · Conectado al sistema</span>
        </div>
    </div>
</div>

<!-- Mensaje de bienvenida con saludo personalizado -->
<div style="
    font-size: 14px;
    margin-bottom: 15px;
    color: #ffffff;
    background: rgba(0,120,212,0.1);
    padding: 10px;
    border-radius: 8px;
    border-left: 3px solid #0078d4;
">
    ${obtenerSaludo()} Soy tu asistente personal de <strong>SISFACT PDL Visiones</strong>. Estoy aquí para ayudarte a gestionar y consultar toda la información del sistema.
</div>

<!-- Estadísticas rápidas -->
<div style="
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #3d3d3d;
">
    <div style="
        font-size: 11px;
        color: #0078d4;
        font-weight: bold;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    ">📊 ESTADÍSTICAS RÁPIDAS (clic para consultar)</div>
    <div style="
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    ">
        <!-- Facturas hoy -->
        <div onclick="enviarMensajeDirecto('facturas hoy')" style="
            background: #2d2d2d;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
            <div style="font-size: 10px; color: #a6a6a6;">Facturas hoy</div>
            <div style="font-size: 16px; font-weight: bold; color: #0078d4;">⏳ <span style="font-size: 11px;">clic</span></div>
        </div>
        
        <!-- Ingresos mes -->
        <div onclick="enviarMensajeDirecto('ingresos del mes')" style="
            background: #2d2d2d;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
            <div style="font-size: 10px; color: #a6a6a6;">Ingresos mes</div>
            <div style="font-size: 16px; font-weight: bold; color: #4caf50;">💰 <span style="font-size: 11px;">clic</span></div>
        </div>
        
        <!-- Clientes activos -->
        <div onclick="enviarMensajeDirecto('clientes activos')" style="
            background: #2d2d2d;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
            <div style="font-size: 10px; color: #a6a6a6;">Clientes activos</div>
            <div style="font-size: 16px; font-weight: bold; color: #ffc107;">👥 <span style="font-size: 11px;">clic</span></div>
        </div>
        
        <!-- Próximo cierre -->
        <div onclick="enviarMensajeDirecto('próximo cierre')" style="
            background: #2d2d2d;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
            <div style="font-size: 10px; color: #a6a6a6;">Próximo cierre</div>
            <div style="font-size: 16px; font-weight: bold; color: #ff6b35;">📅 <span style="font-size: 11px;">clic</span></div>
        </div>
    </div>
</div>
                    <!-- CATEGORÍAS DISPONIBLES - AHORA CLICKEABLES -->
                    <div style="margin-bottom: 15px;">
                        <div style="
                            font-size: 11px;
                            color: #a6a6a6;
                            margin-bottom: 8px;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                        ">🔍 CATEGORÍAS DISPONIBLES (clic para navegar)</div>
                        <div style="
                            display: flex;
                            gap: 6px;
                            flex-wrap: wrap;
                        ">
                            <span onclick="enviarMensajeDirecto('facturas')" style="
                                background: rgba(0,120,212,0.15);
                                padding: 5px 10px;
                                border-radius: 20px;
                                font-size: 11px;
                                border: 1px solid rgba(0,120,212,0.3);
                                color: #0078d4;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            " onmouseenter="this.style.background='rgba(0,120,212,0.3)'; this.style.transform='translateY(-1px)';" 
                               onmouseleave="this.style.background='rgba(0,120,212,0.15)'; this.style.transform='translateY(0)';">📋 Facturas</span>
                            
                            <span onclick="enviarMensajeDirecto('clientes')" style="
                                background: rgba(76,175,80,0.15);
                                padding: 5px 10px;
                                border-radius: 20px;
                                font-size: 11px;
                                border: 1px solid rgba(76,175,80,0.3);
                                color: #4caf50;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            " onmouseenter="this.style.background='rgba(76,175,80,0.3)'; this.style.transform='translateY(-1px)';" 
                               onmouseleave="this.style.background='rgba(76,175,80,0.15)'; this.style.transform='translateY(0)';">👥 Clientes</span>
                            
                            <span onclick="enviarMensajeDirecto('servicios')" style="
                                background: rgba(255,193,7,0.15);
                                padding: 5px 10px;
                                border-radius: 20px;
                                font-size: 11px;
                                border: 1px solid rgba(255,193,7,0.3);
                                color: #ffc107;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            " onmouseenter="this.style.background='rgba(255,193,7,0.3)'; this.style.transform='translateY(-1px)';" 
                               onmouseleave="this.style.background='rgba(255,193,7,0.15)'; this.style.transform='translateY(0)';">🛠️ Servicios</span>
                            
                            <span onclick="enviarMensajeDirecto('categorias')" style="
                                background: rgba(255,107,53,0.15);
                                padding: 5px 10px;
                                border-radius: 20px;
                                font-size: 11px;
                                border: 1px solid rgba(255,107,53,0.3);
                                color: #ff6b35;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            " onmouseenter="this.style.background='rgba(255,107,53,0.3)'; this.style.transform='translateY(-1px)';" 
                               onmouseleave="this.style.background='rgba(255,107,53,0.15)'; this.style.transform='translateY(0)';">📁 Categorías</span>
                            
                            <span onclick="enviarMensajeDirecto('reportes')" style="
                                background: rgba(156,39,176,0.15);
                                padding: 5px 10px;
                                border-radius: 20px;
                                font-size: 11px;
                                border: 1px solid rgba(156,39,176,0.3);
                                color: #9c27b0;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            " onmouseenter="this.style.background='rgba(156,39,176,0.3)'; this.style.transform='translateY(-1px)';" 
                               onmouseleave="this.style.background='rgba(156,39,176,0.15)'; this.style.transform='translateY(0)';">📊 Reportes</span>
                            
                            <span onclick="enviarMensajeDirecto('usuarios')" style="
                                background: rgba(233,30,99,0.15);
                                padding: 5px 10px;
                                border-radius: 20px;
                                font-size: 11px;
                                border: 1px solid rgba(233,30,99,0.3);
                                color: #e91e63;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            " onmouseenter="this.style.background='rgba(233,30,99,0.3)'; this.style.transform='translateY(-1px)';" 
                               onmouseleave="this.style.background='rgba(233,30,99,0.15)'; this.style.transform='translateY(0)';">👤 Usuarios</span>
                        </div>
                    </div>

                    <!-- Ejemplos de preguntas en tarjetas clickeables -->
                    <div style="margin: 15px 0 10px 0;">
                        <div style="
                            font-size: 11px;
                            color: #a6a6a6;
                            margin-bottom: 8px;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                        ">💡 EJEMPLOS DE PREGUNTAS (clic para enviar)</div>
                        
                        <!-- Fila 1 -->
                        <div style="
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 6px;
                            margin-bottom: 6px;
                        ">
                            <div class="chat-example" onclick="enviarMensajeDirecto('¿Cuántas facturas hay?')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #0078d4;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "¿Cuantas facturas hay?"
                            </div>
                            <div class="chat-example" onclick="enviarMensajeDirecto('Últimas 5 facturas')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #4caf50;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "Ultimas 5 facturas"
                            </div>
                        </div>
                        
                        <!-- Fila 2 -->
                        <div style="
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 6px;
                            margin-bottom: 6px;
                        ">
                            <div class="chat-example" onclick="enviarMensajeDirecto('Ingresos del mes')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #ffc107;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "Ingresos del mes"
                            </div>
                            <div class="chat-example" onclick="enviarMensajeDirecto('Clientes activos')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #ff6b35;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "Clientes activos"
                            </div>
                        </div>
                        
                        <!-- Fila 3 -->
                        <div style="
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 6px;
                            margin-bottom: 6px;
                        ">
                            <div class="chat-example" onclick="enviarMensajeDirecto('Servicios más solicitados')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #9c27b0;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "Servicios más solicitados"
                            </div>
                            <div class="chat-example" onclick="enviarMensajeDirecto('Abrir facturas')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #e91e63;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "Abrir facturas"
                            </div>
                        </div>
                        
                        <!-- Fila 4 -->
                        <div style="
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 6px;
                        ">
                            <div class="chat-example" onclick="enviarMensajeDirecto('Contratos por vencer')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #00bcd4;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "Contratos por vencer"
                            </div>
                            <div class="chat-example" onclick="enviarMensajeDirecto('Comparar ingresos con plan')" style="
                                background: #2d2d2d;
                                padding: 8px 10px;
                                border-radius: 8px;
                                font-size: 11px;
                                border-left: 3px solid #ff9800;
                                cursor: pointer;
                                transition: all 0.2s ease;
                            ">
                                "Comparar con plan"
                            </div>
                        </div>
                    </div>

                    <!-- Footer con SOLO el pin de ayuda (ELIMINADOS los comandos rápidos) -->
                    <div style="
                        margin-top: 15px;
                        padding-top: 10px;
                        border-top: 1px solid #3d3d3d;
                        display: flex;
                        justify-content: flex-end;
                        align-items: center;
                        background: rgba(255,255,255,0.02);
                        padding: 8px 10px;
                        border-radius: 6px;
                    ">
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 5px;
                            font-size: 12px;
                            color: #0078d4;
                            font-weight: bold;
                            cursor: pointer;
                            padding: 5px 10px;
                            border-radius: 20px;
                            background: rgba(0,120,212,0.1);
                            transition: all 0.2s ease;
                        " onclick="enviarMensajeDirecto('ayuda')" 
                           onmouseenter="this.style.background='rgba(0,120,212,0.3)'; this.style.transform='translateY(-1px)';"
                           onmouseleave="this.style.background='rgba(0,120,212,0.1)'; this.style.transform='translateY(0)';">
                            📌 Ayuda ➔
                        </div>
                    </div>

                    <!-- Tiempo del mensaje -->
                    <div style="
                        font-size: 10px;
                        color: #a6a6a6;
                        margin: 8px 0 0 0;
                        text-align: right;
                        border-top: 1px solid #3d3d3d;
                        padding-top: 8px;
                    ">
                        🕒 ${new Date().toLocaleTimeString()}
                    </div>
                </div>
            </div>
        </div>

        <!-- Área de input mejorada -->
        <div style="
            display: flex;
            padding: 12px;
            border-top: 1px solid #3d3d3d;
            background: #1f1f1f;
            gap: 8px;
        ">
            <input type="text" id="chat-input-final" placeholder="Escribe tu pregunta aquí..." style="
                flex: 1;
                background: #2d2d2d;
                border: 1px solid #3d3d3d;
                border-radius: 20px;
                padding: 10px 15px;
                color: white;
                outline: none;
                font-size: 13px;
                transition: all 0.2s ease;
            ">
            <button id="send-message-final" style="
                background: #2e7d5e;
                border: none;
                border-radius: 50%;
                color: white;
                width: 30px;
                height: 30px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                transition: all 0.2s ease;
            " title="Enviar mensaje">🚀</button>
            <button onclick="limpiarChat()" style="
                background: #2e7d5e;
                border: none;
                border-radius: 50%;
                color: white;
                width: 30px;
                height: 30px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                transition: all 0.2s ease;
            " title="Limpiar chat">🧹</button>
        </div>
    `;

    // Agregar elementos al body (SOLO la ventana del chat)
    document.body.appendChild(chatWindow);

    // Variables de estado
    let isOpen = false;
    let isMinimized = false;

    // Obtener elementos
    const messagesContainer = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input-final');
    const sendButton = document.getElementById('send-message-final');
    const minimizeBtn = document.getElementById('minimize-chat');
    const closeBtn = document.getElementById('close-chat');

// Función para agregar mensajes (CONVERTIDOR DE MARKDOWN A HTML) CON BOTÓN FLOTANTE
function addMessage(text, sender) {
    const messageDiv = document.createElement('div');
    messageDiv.style.cssText = `
        align-self: ${sender === 'user' ? 'flex-end' : 'flex-start'};
        max-width: 85%;
        position: relative;
        margin-bottom: ${sender === 'bot' ? '25px' : '5px'};
    `;
    
// Azul claro degradado para usuario
const bubbleGradient = sender === 'user' 
    ? 'linear-gradient(135deg, #4a9eff 0%, #2b7ae0 100%)'  // Azul más claro
    : 'linear-gradient(135deg, #2e7d5e 0%, #1d5e45 100%)'; // Verde oscuro para bot

    const borderRadius = sender === 'user' ? '18px 18px 4px 18px' : '18px 18px 18px 4px';
    
const bubbleDiv = document.createElement('div');
bubbleDiv.style.cssText = `
    padding: 10px 14px;
    background: ${bubbleGradient};
    border-radius: ${borderRadius};
    border: ${sender === 'bot' ? '1px solid #3d3d3d' : 'none'};
    font-size: 13px;
    color: white;
    white-space: pre-line;
`;
    // Convertir Markdown a HTML
    let htmlText = text;
    
    // 1. Convertir enlaces [texto](url) a <a href="url">texto</a>
    htmlText = htmlText.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, linkText, url) {
        return `<a href="${url}" target="_blank" class="chatbot-link">${linkText}</a>`;
    });
    
    // 2. Convertir **texto** a <strong>texto</strong>
    htmlText = htmlText.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    
    // 3. Convertir *texto* a <em>texto</em>
    htmlText = htmlText.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    
    // 4. Convertir líneas con • a listas
    const lines = htmlText.split('\n');
    let result = [];
    let inList = false;
    
    for (let line of lines) {
        if (line.trim().startsWith('•')) {
            if (!inList) {
                result.push('<ul>');
                inList = true;
            }
            result.push('<li>' + line.substring(1).trim() + '</li>');
        } else {
            if (inList) {
                result.push('</ul>');
                inList = false;
            }
            result.push(line);
        }
    }
    
    if (inList) {
        result.push('</ul>');
    }
    
    htmlText = result.join('\n');
    
    // 5. Convertir saltos de línea
    htmlText = htmlText.replace(/\n/g, '<br>');
    
    bubbleDiv.innerHTML = htmlText;
    
    const timeDiv = document.createElement('div');
    timeDiv.style.cssText = `
        font-size: 10px;
        color: #a6a6a6;
        margin: 4px 8px 0;
        text-align: ${sender === 'user' ? 'right' : 'left'};
    `;
    timeDiv.textContent = new Date().toLocaleTimeString();
    
    messageDiv.appendChild(bubbleDiv);
    messageDiv.appendChild(timeDiv);
    
    // Si es mensaje del BOT, agregar botón flotante en la parte inferior derecha
    if (sender === 'bot') {
        const buttonContainer = document.createElement('div');
        buttonContainer.style.cssText = `
            display: flex;
            justify-content: flex-end;
            margin-top: 5px;
            position: relative;
        `;
        
        // Botón flotante
        const actionButton = document.createElement('button');
        actionButton.innerHTML = '➕ Acciones';
actionButton.style.cssText = `
    background: linear-gradient(135deg, #1e2a3a 0%, #0f1a2a 100%);
    border: none;
    border-radius: 20px;
    color: white;
    padding: 5px 12px;
    font-size: 11px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s ease;
    border: 1px solid #3d3d3d;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
`;
        
        // Menú de acciones (inicialmente oculto)
        const actionMenu = document.createElement('div');
        actionMenu.style.cssText = `
            position: absolute;
            bottom: 35px;
            right: 0;
            background: #2d2d2d;
            border: 1px solid #3d3d3d;
            border-radius: 8px;
            padding: 5px 0;
            display: none;
            flex-direction: column;
            min-width: 150px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10;
        `;
        
// Opciones del menú según el contenido del mensaje
const menuItems = [];

// Detectar si hay URLs en el mensaje
if (text.includes('facturas.php') || text.includes('clientes.php') || 
    text.includes('servicios.php') || text.includes('categorias.php') ||
    text.includes('reportes.php') || text.includes('usuarios.php')) {
    
    // Extraer URLs del texto
    const urlMatch = text.match(/\[([^\]]+)\]\(([^)]+)\)/);
    if (urlMatch) {
        menuItems.push({
            icon: '<i class="fas fa-external-link-alt" style="width: 16px;"></i>',
            text: 'Abrir enlace',
            action: () => window.open(urlMatch[2], '_blank')
        });
    }
}

// Siempre agregar opciones comunes (AHORA AUTO-ENVÍAN)
menuItems.push(
    {
        icon: '<i class="fas fa-copy" style="width: 16px;"></i>',
        text: 'Copiar respuesta',
        action: () => {
            navigator.clipboard.writeText(text.replace(/\*\*([^*]+)\*\*/g, '$1').replace(/\*([^*]+)\*/g, '$1'));
            mostrarNotificacion('👌✓ Respuesta copiada');
        }
    },
    {
        icon: '<i class="fas fa-question-circle" style="width: 16px;"></i>',
        text: 'No entendí',
        action: () => {
            const input = document.getElementById('chat-input-final');
            input.value = 'No entendí, ¿puedes explicar mejor?';
            // AUTO-ENVIAR
            setTimeout(() => {
                document.getElementById('send-message-final').click();
            }, 100);
        }
    },
    {
        icon: '<i class="fas fa-life-ring" style="width: 16px;"></i>',
        text: 'Ver ayuda',
        action: () => {
            const input = document.getElementById('chat-input-final');
            input.value = 'ayuda';
            // AUTO-ENVIAR
            setTimeout(() => {
                document.getElementById('send-message-final').click();
            }, 100);
        }
    },
    {
        icon: '<i class="fas fa-broom" style="width: 16px;"></i>',
        text: 'Limpiar chat',
        action: limpiarChat  // Esta ya limpia automáticamente
    },
	{
        icon: '<i class="fas fa-laugh-squint" style="width: 16px;"></i>',
        text: 'Contar un chiste',
        action: () => {
            const input = document.getElementById('chat-input-final');
            input.value = 'chiste';
            setTimeout(() => {
                document.getElementById('send-message-final').click();
            }, 100);
        }
	}
);

// Si el mensaje contiene números (posibles facturas), agregar opción
if (/\d+/.test(text) && text.toLowerCase().includes('factura')) {
    menuItems.push({
        icon: '<i class="fas fa-chart-line" style="width: 16px;"></i>',
        text: 'Ver detalles',
        action: () => {
            const input = document.getElementById('chat-input-final');
            input.value = 'Ver más detalles de esta factura';
            // AUTO-ENVIAR
            setTimeout(() => {
                document.getElementById('send-message-final').click();
            }, 100);
        }
    });
}

// Si el mensaje habla de clientes, agregar opción
if (text.toLowerCase().includes('cliente')) {
    menuItems.push({
        icon: '<i class="fas fa-users" style="width: 16px;"></i>',
        text: 'Buscar cliente',
        action: () => {
            const input = document.getElementById('chat-input-final');
            input.value = 'Buscar cliente ';
            // AUTO-ENVIAR (pero deja el cursor para que escriba)
            input.focus();
        }
    });
}
        
        // Construir el menú
        menuItems.forEach((item, index) => {
            const menuItem = document.createElement('div');
            menuItem.style.cssText = `
                padding: 8px 15px;
                font-size: 12px;
                color: white;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s ease;
                ${index !== menuItems.length - 1 ? 'border-bottom: 1px solid #3d3d3d;' : ''}
            `;
            menuItem.innerHTML = `${item.icon} ${item.text}`;
            
            menuItem.addEventListener('mouseenter', () => {
                menuItem.style.background = '#3d3d3d';
            });
            
            menuItem.addEventListener('mouseleave', () => {
                menuItem.style.background = 'transparent';
            });
            
            menuItem.addEventListener('click', (e) => {
                e.stopPropagation();
                item.action();
                actionMenu.style.display = 'none';
                actionButton.innerHTML = '➕ Acciones';
            });
            
            actionMenu.appendChild(menuItem);
        });
        
        // Toggle del menú
        actionButton.addEventListener('click', (e) => {
            e.stopPropagation();
            if (actionMenu.style.display === 'flex') {
                actionMenu.style.display = 'none';
                actionButton.innerHTML = '➕ Acciones';
            } else {
                // Cerrar otros menús abiertos
                document.querySelectorAll('.bot-action-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
                document.querySelectorAll('.bot-action-button').forEach(btn => {
                    btn.innerHTML = '➕ Acciones';
                });
                
                actionMenu.style.display = 'flex';
                actionButton.innerHTML = '❌ Cerrar';
            }
        });
        
        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!actionButton.contains(e.target) && !actionMenu.contains(e.target)) {
                actionMenu.style.display = 'none';
                actionButton.innerHTML = '➕ Acciones';
            }
        });
        
        actionMenu.classList.add('bot-action-menu');
        actionButton.classList.add('bot-action-button');
        
        buttonContainer.appendChild(actionButton);
        buttonContainer.appendChild(actionMenu);
        messageDiv.appendChild(buttonContainer);
    }
    
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    // Estilizar enlaces
    const links = messageDiv.querySelectorAll('a');
    links.forEach(link => {
        link.setAttribute('target', '_blank');
        link.setAttribute('rel', 'noopener noreferrer');
        link.style.display = 'inline-block';
        link.style.padding = '5px 10px';
        link.style.margin = '5px 0';
        link.style.background = '#0078d4';
        link.style.color = 'white';
        link.style.textDecoration = 'none';
        link.style.borderRadius = '15px';
        link.style.fontWeight = '500';
        link.style.fontSize = '12px';
        link.style.transition = 'all 0.2s ease';
        
        link.addEventListener('mouseenter', function() {
            this.style.background = '#005a9e';
            this.style.transform = 'translateY(-1px)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.background = '#0078d4';
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Estilizar listas
    const lists = messageDiv.querySelectorAll('ul');
    lists.forEach(list => {
        list.style.margin = '5px 0';
        list.style.paddingLeft = '20px';
    });
    
    const items = messageDiv.querySelectorAll('li');
    items.forEach(item => {
        item.style.margin = '3px 0';
    });
}



// Función para enviar mensaje al servidor (CON TIEMPO MÍNIMO DE VISUALIZACIÓN)
async function sendMessage() {
    const messageText = chatInput.value.trim();
    if (!messageText) return;

    // Mostrar mensaje del usuario
    addMessage(messageText, 'user');
    chatInput.value = '';
    chatInput.disabled = true;
    sendButton.disabled = true;

    // Mostrar indicador de escritura
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typing-indicator';
    typingDiv.style.cssText = `
        align-self: flex-start;
        max-width: 85%;
        padding: 10px 14px;
        background: #2d2d2d;
        border-radius: 18px;
        border-bottom-left-radius: 4px;
        border: 1px solid #3d3d3d;
        font-size: 13px;
        color: white;
        margin-bottom: 10px;
    `;
    typingDiv.innerHTML = `🤖 Respondiendo<span style="opacity: 0.7;">.</span><span style="opacity: 0.7;">.</span><span style="opacity: 0.7;">.</span>`;
    
    messagesContainer.appendChild(typingDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;

    // Guardar el tiempo de inicio
    const tiempoInicio = Date.now();
    
    try {
        // Enviar al servidor PHP
        const response = await fetch('includes/chatbot_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: messageText,
                session_id: 'sisfact_' + Date.now()
            })
        });

        const data = await response.json();
        
        // Calcular cuánto tiempo ha pasado
        const tiempoTranscurrido = Date.now() - tiempoInicio;
        const tiempoRestante = Math.max(0, 1500 - tiempoTranscurrido); // CORREGIDO: 1500ms = 1.5 segundos
        
        // Esperar el tiempo restante para que el indicador se vea al menos 1.5 segundos
        setTimeout(() => {
            // Eliminar indicador de escritura
            typingDiv.remove();
            
            if (data.success) {
                let mensaje = data.response;
                
                // ===== DETECCIÓN DE MARCADORES PARA EFECTOS ESPECIALES =====
                
                // Detectar si debe lanzar confetti (feliz cumpleaños)
                if (mensaje.includes('<!--CONFETTI-->')) {
                    mensaje = mensaje.replace('<!--CONFETTI-->', '');
                    setTimeout(() => {
                        if (typeof window.lanzarConfetti === 'function') {
                            window.lanzarConfetti();
                        }
                    }, 300);
                }
                
                // Detectar si debe lanzar corazones (respuestas de cariño)
                if (mensaje.includes('<!--CORAZONES-->')) {
                    mensaje = mensaje.replace('<!--CORAZONES-->', '');
                    setTimeout(() => {
                        if (typeof window.lanzarCorazones === 'function') {
                            window.lanzarCorazones();
                        }
                    }, 300);
                }
				
                // Detectar si debe lanzar corazones (respuestas de cariño)
                if (mensaje.includes('<!--estre-->')) {
                    mensaje = mensaje.replace('<!--estre-->', '');
                    setTimeout(() => {
                        if (typeof window.lanzarEstrellas === 'function') {
                            window.lanzarEstrellas();
                        }
                    }, 300);
                }

if (mensaje.includes('<!--MOSTRAR_ROBOT-->')) {
    mensaje = mensaje.replace('<!--MOSTRAR_ROBOT-->', '');
    if (typeof window.mostrarRobot === 'function') {
        window.mostrarRobot();
    }
}

// Detectar si debe ocultar el robot con SweetAlert
if (mensaje.includes('<!--OCULTAR_ROBOT-->')) {
    mensaje = mensaje.replace('<!--OCULTAR_ROBOT-->', '');
    
    // PRIMERO: Ocultar el robot y cerrar el chat
    const robotContainer = document.getElementById('robot-asistente-container');
    const chatWindow = document.getElementById('chat-window-final');
    
    if (robotContainer) {
        robotContainer.style.display = 'none';
        localStorage.setItem('robot_oculto', 'true');
    }
    
    if (chatWindow && chatWindow.style.display === 'flex') {
        chatWindow.style.display = 'none';
    }
    
    // DESPUÉS: Mostrar SweetAlert de despedida
    Swal.fire({
        icon: "info",
        title: "👋 Asistente Ocultado",
        html: `<div style="text-align: center;">
            <div style="font-size: 55px; margin-bottom: 10px;">👋🤖</div>
            <strong style="color: #ff9800; font-size: 18px;">¡Hasta luego!</strong>
            <p class="mt-2 mb-0">El asistente virtual se ha ocultado.</p>
            <p class="mt-2 mb-0 text-warning">Puedes recuperarlo desde el menú <span style="color: green;">UTILIDADES → Chatear con el Asistente</span></p>
        </div>`,
        confirmButtonText: "<i class='fas fa-check me-2'></i>Entendido",
        confirmButtonColor: "#ff9800",
        background: "var(--win-bg-secondary, #1f1f1f)",
        color: "var(--win-text-primary, #fff)",
        allowOutsideClick: false,
        allowEscapeKey: false
    });
}	
                addMessage(mensaje, 'bot');
            } else {
                addMessage('Lo siento, tuve un problema. Intenta de nuevo.', 'bot');
            }
            
            chatInput.disabled = false;
            sendButton.disabled = false;
            chatInput.focus();
        }, tiempoRestante);
        
    } catch (error) {
        console.error('Error:', error);
        
        // También esperar para mostrar el error
        setTimeout(() => {
            typingDiv.remove();
            addMessage('Error de conexión con el servidor.', 'bot');
            chatInput.disabled = false;
            sendButton.disabled = false;
            chatInput.focus();
        }, 1500);
    }
}

// Eventos - SOLO minimizar y cerrar (el botón de quick-actions controla la apertura)
// En la función crearChatbotSISFACT, asegúrate de que estos eventos estén correctos:
closeBtn.onclick = function(e) {
    e.stopPropagation();
    chatWindow.style.display = 'none';
    isOpen = false;
    // El robot volverá a modo pulse automáticamente cuando toggleChatbot sea llamado
    // desde el botón de quick-actions o desde el clic en el robot
};

minimizeBtn.onclick = function(e) {
    e.stopPropagation();
    chatWindow.style.display = 'none';
    isOpen = false;
    // El robot volverá a modo pulse automáticamente cuando toggleChatbot sea llamado
};

    sendButton.onclick = function(e) {
        e.stopPropagation();
        sendMessage();
    };

    chatInput.onkeypress = function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendMessage();
        }
    };

    // Estilos hover para botones del header
    minimizeBtn.addEventListener('mouseenter', function() {
        this.style.background = '#3d3d3d';
    });
    minimizeBtn.addEventListener('mouseleave', function() {
        this.style.background = 'none';
    });

    closeBtn.addEventListener('mouseenter', function() {
        this.style.background = '#d32f2f';
        this.style.color = 'white';
    });
    closeBtn.addEventListener('mouseleave', function() {
        this.style.background = 'none';
        this.style.color = '#a6a6a6';
    });

    // Estilos hover para ejemplos
    const examples = document.querySelectorAll('.chat-example');
    examples.forEach(example => {
        example.addEventListener('mouseenter', function() {
            this.style.background = '#3d3d3d';
            this.style.transform = 'translateX(2px)';
        });
        example.addEventListener('mouseleave', function() {
            this.style.background = '#2d2d2d';
            this.style.transform = 'translateX(0)';
        });
    });

// =========================================================================
    // CERRAR CHAT AL HACER CLIC FUERA (AGREGADO AQUÍ)
    // =========================================================================
    document.addEventListener('click', function(e) {
        const chatWindow = document.getElementById('chat-window-final');
        const robotContainer = document.getElementById('robot-asistente-container');
        const quickActionBtn = document.getElementById('chatbotQuickAction');
        
        // Si el chat está abierto
        if (chatWindow && chatWindow.style.display === 'flex') {
            
            // Verificar si el clic fue fuera del chat Y fuera del robot Y fuera del botón de quick-actions
            if (!chatWindow.contains(e.target) && 
                (!robotContainer || !robotContainer.contains(e.target)) && 
                (!quickActionBtn || !quickActionBtn.contains(e.target))) {
                
                // Cerrar el chat
                chatWindow.style.display = 'none';
                
                // Restaurar el modo pulse del robot
                const robotAvatar = document.getElementById('robot-avatar-vuelo');
                if (robotAvatar) {
                    robotAvatar.style.width = '65px';
                    robotAvatar.style.height = '65px';
                    robotAvatar.style.borderRadius = '50%';
                    robotAvatar.style.background = '#1a1a1a url("assets/logobot.png") no-repeat center center';
                    robotAvatar.style.backgroundSize = 'contain';
                    robotAvatar.style.border = '2px solid #2e7d5e';
                    robotAvatar.style.padding = '5px';
                    robotAvatar.classList.add('luz-pulsante-verde', 'modo-bola');
                }
                
                // Mostrar notificación (opcional)
                //mostrarNotificacion('👋 Chat cerrado');
            }
        }
    });
    
    // Conectar el botón de quick-actions
    conectarBotonQuickActions();
	
	
}


// =========================================================================
// CARGAR ESTILOS CSS EXTERNOS PARA EL CHATBOT
// =========================================================================
function cargarEstilosChatbot() {
    // Verificar si ya existe el enlace
    if (!document.querySelector('link[href="css/chatbot.css"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.type = 'text/css';
        link.href = 'css/chatbot.css';
        document.head.appendChild(link);
    }
}


// Función para limpiar el chat y mostrar bienvenida
function limpiarChat() {
    const messagesContainer = document.getElementById('chat-messages');
    
    // Limpiar el contenedor
    messagesContainer.innerHTML = '';
    
    // Función para mostrar mensaje de bienvenida (reutilizando el del inicio)
    function mostrarBienvenida() {
        const welcomeMessage = document.createElement('div');
        welcomeMessage.style.cssText = `
            align-self: flex-start;
            max-width: 100%;
        `;
        
        // Reutilizar el mismo HTML del mensaje de bienvenida original
        welcomeMessage.innerHTML = `
            <div style="
                padding: 16px 18px;
                background: linear-gradient(135deg, #2d2d2d 0%, #1f1f1f 100%);
                border-radius: 18px;
                border-bottom-left-radius: 4px;
                border: 1px solid #3d3d3d;
                font-size: 13px;
                color: white;
                line-height: 1.6;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            ">
                <!-- Header con avatar y logo -->
                <div style="
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 15px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #3d3d3d;
                ">
                    <img src="assets/logobot.png" alt="Logo SISFACT" style="
                        width: 45px;
                        height: 45px;
                        object-fit: contain;
                    ">
                    <div>
                        <div style="
                            font-weight: bold;
                            font-size: 15px;
                            color: #0078d4;
                        ">Asistente Virtual SISFACT PDL VISIONES</div>
                        <div style="
                            font-size: 11px;
                            color: #a6a6a6;
                            display: flex;
                            align-items: center;
                            gap: 5px;
                            margin-top: 3px;
                        ">
                            <span style="
                                display: inline-block;
                                width: 8px;
                                height: 8px;
                                background: #4caf50;
                                border-radius: 50%;
                            "></span>
                            <span>En línea · Conectado al sistema</span>
                        </div>
                    </div>
                </div>

                <!-- Mensaje de bienvenida con saludo personalizado -->
                <div style="
                    font-size: 14px;
                    margin-bottom: 15px;
                    color: #ffffff;
                    background: rgba(0,120,212,0.1);
                    padding: 10px;
                    border-radius: 8px;
                    border-left: 3px solid #0078d4;
                ">
                    ${obtenerSaludo()} Soy tu asistente personal de <strong>SISFACT PDL Visiones</strong>. Estoy aquí para ayudarte a gestionar y consultar toda la información del sistema.
                </div>

                <!-- Estadísticas rápidas clickeables -->
                <div style="
                    background: rgba(255,255,255,0.05);
                    border-radius: 10px;
                    padding: 12px;
                    margin-bottom: 15px;
                    border: 1px solid #3d3d3d;
                ">
                    <div style="
                        font-size: 11px;
                        color: #0078d4;
                        font-weight: bold;
                        margin-bottom: 8px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">📊 ESTADÍSTICAS RÁPIDAS (clic para consultar)</div>
                    <div style="
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 8px;
                    ">
                        <div onclick="enviarMensajeDirecto('facturas hoy')" style="
                            background: #2d2d2d;
                            padding: 8px;
                            border-radius: 6px;
                            text-align: center;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
                            <div style="font-size: 10px; color: #a6a6a6;">Facturas hoy</div>
                            <div style="font-size: 16px; font-weight: bold; color: #0078d4;">⏳ <span style="font-size: 11px;">clic</span></div>
                        </div>
                        
                        <div onclick="enviarMensajeDirecto('ingresos del mes')" style="
                            background: #2d2d2d;
                            padding: 8px;
                            border-radius: 6px;
                            text-align: center;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
                            <div style="font-size: 10px; color: #a6a6a6;">Ingresos mes</div>
                            <div style="font-size: 16px; font-weight: bold; color: #4caf50;">💰 <span style="font-size: 11px;">clic</span></div>
                        </div>
                        
                        <div onclick="enviarMensajeDirecto('clientes activos')" style="
                            background: #2d2d2d;
                            padding: 8px;
                            border-radius: 6px;
                            text-align: center;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
                            <div style="font-size: 10px; color: #a6a6a6;">Clientes activos</div>
                            <div style="font-size: 16px; font-weight: bold; color: #ffc107;">👥 <span style="font-size: 11px;">clic</span></div>
                        </div>
                        
                        <div onclick="enviarMensajeDirecto('próximo cierre')" style="
                            background: #2d2d2d;
                            padding: 8px;
                            border-radius: 6px;
                            text-align: center;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='#3d3d3d'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='#2d2d2d'; this.style.transform='translateY(0)';">
                            <div style="font-size: 10px; color: #a6a6a6;">Próximo cierre</div>
                            <div style="font-size: 16px; font-weight: bold; color: #ff6b35;">📅 <span style="font-size: 11px;">clic</span></div>
                        </div>
                    </div>
                </div>

                <!-- Categorías clickeables -->
                <div style="margin-bottom: 15px;">
                    <div style="
                        font-size: 11px;
                        color: #a6a6a6;
                        margin-bottom: 8px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">🔍 CATEGORÍAS DISPONIBLES (clic para navegar)</div>
                    <div style="
                        display: flex;
                        gap: 6px;
                        flex-wrap: wrap;
                    ">
                        <span onclick="enviarMensajeDirecto('facturas')" style="
                            background: rgba(0,120,212,0.15);
                            padding: 5px 10px;
                            border-radius: 20px;
                            font-size: 11px;
                            border: 1px solid rgba(0,120,212,0.3);
                            color: #0078d4;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='rgba(0,120,212,0.3)'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='rgba(0,120,212,0.15)'; this.style.transform='translateY(0)';">📋 Facturas</span>
                        
                        <span onclick="enviarMensajeDirecto('clientes')" style="
                            background: rgba(76,175,80,0.15);
                            padding: 5px 10px;
                            border-radius: 20px;
                            font-size: 11px;
                            border: 1px solid rgba(76,175,80,0.3);
                            color: #4caf50;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='rgba(76,175,80,0.3)'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='rgba(76,175,80,0.15)'; this.style.transform='translateY(0)';">👥 Clientes</span>
                        
                        <span onclick="enviarMensajeDirecto('servicios')" style="
                            background: rgba(255,193,7,0.15);
                            padding: 5px 10px;
                            border-radius: 20px;
                            font-size: 11px;
                            border: 1px solid rgba(255,193,7,0.3);
                            color: #ffc107;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='rgba(255,193,7,0.3)'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='rgba(255,193,7,0.15)'; this.style.transform='translateY(0)';">🛠️ Servicios</span>
                        
                        <span onclick="enviarMensajeDirecto('categorias')" style="
                            background: rgba(255,107,53,0.15);
                            padding: 5px 10px;
                            border-radius: 20px;
                            font-size: 11px;
                            border: 1px solid rgba(255,107,53,0.3);
                            color: #ff6b35;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='rgba(255,107,53,0.3)'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='rgba(255,107,53,0.15)'; this.style.transform='translateY(0)';">📁 Categorías</span>
                        
                        <span onclick="enviarMensajeDirecto('reportes')" style="
                            background: rgba(156,39,176,0.15);
                            padding: 5px 10px;
                            border-radius: 20px;
                            font-size: 11px;
                            border: 1px solid rgba(156,39,176,0.3);
                            color: #9c27b0;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='rgba(156,39,176,0.3)'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='rgba(156,39,176,0.15)'; this.style.transform='translateY(0)';">📊 Reportes</span>
                        
                        <span onclick="enviarMensajeDirecto('usuarios')" style="
                            background: rgba(233,30,99,0.15);
                            padding: 5px 10px;
                            border-radius: 20px;
                            font-size: 11px;
                            border: 1px solid rgba(233,30,99,0.3);
                            color: #e91e63;
                            font-weight: 500;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        " onmouseenter="this.style.background='rgba(233,30,99,0.3)'; this.style.transform='translateY(-1px)';" 
                           onmouseleave="this.style.background='rgba(233,30,99,0.15)'; this.style.transform='translateY(0)';">👤 Usuarios</span>
                    </div>
                </div>

                <!-- Ejemplos de preguntas -->
                <div style="margin: 15px 0 10px 0;">
                    <div style="
                        font-size: 11px;
                        color: #a6a6a6;
                        margin-bottom: 8px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">💡 EJEMPLOS DE PREGUNTAS (clic para enviar)</div>
                    
                    <div style="
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 6px;
                        margin-bottom: 6px;
                    ">
                        <div class="chat-example" onclick="enviarMensajeDirecto('¿Cuántas facturas hay?')" style="
                            background: #2d2d2d;
                            padding: 8px 10px;
                            border-radius: 8px;
                            font-size: 11px;
                            border-left: 3px solid #0078d4;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        ">
                            "¿Cuantas facturas hay?"
                        </div>
                        <div class="chat-example" onclick="enviarMensajeDirecto('Últimas 5 facturas')" style="
                            background: #2d2d2d;
                            padding: 8px 10px;
                            border-radius: 8px;
                            font-size: 11px;
                            border-left: 3px solid #4caf50;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        ">
                            "Ultimas 5 facturas"
                        </div>
                    </div>
                    
                    <div style="
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 6px;
                        margin-bottom: 6px;
                    ">
                        <div class="chat-example" onclick="enviarMensajeDirecto('Ingresos del mes')" style="
                            background: #2d2d2d;
                            padding: 8px 10px;
                            border-radius: 8px;
                            font-size: 11px;
                            border-left: 3px solid #ffc107;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        ">
                            "Ingresos del mes"
                        </div>
                        <div class="chat-example" onclick="enviarMensajeDirecto('Clientes activos')" style="
                            background: #2d2d2d;
                            padding: 8px 10px;
                            border-radius: 8px;
                            font-size: 11px;
                            border-left: 3px solid #ff6b35;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        ">
                            "Clientes activos"
                        </div>
                    </div>
                    
                    <div style="
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 6px;
                    ">
                        <div class="chat-example" onclick="enviarMensajeDirecto('Contratos por vencer')" style="
                            background: #2d2d2d;
                            padding: 8px 10px;
                            border-radius: 8px;
                            font-size: 11px;
                            border-left: 3px solid #00bcd4;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        ">
                            "Contratos por vencer"
                        </div>
                        <div class="chat-example" onclick="enviarMensajeDirecto('Abrir facturas')" style="
                            background: #2d2d2d;
                            padding: 8px 10px;
                            border-radius: 8px;
                            font-size: 11px;
                            border-left: 3px solid #ff9800;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        ">
                            "Abrir facturas"
                        </div>
                    </div>
                </div>

                <!-- Footer con ayuda -->
                <div style="
                    margin-top: 15px;
                    padding-top: 10px;
                    border-top: 1px solid #3d3d3d;
                    display: flex;
                    justify-content: flex-end;
                    align-items: center;
                    background: rgba(255,255,255,0.02);
                    padding: 8px 10px;
                    border-radius: 6px;
                ">
                    <div style="
                        display: flex;
                        align-items: center;
                        gap: 5px;
                        font-size: 12px;
                        color: #0078d4;
                        font-weight: bold;
                        cursor: pointer;
                        padding: 5px 10px;
                        border-radius: 20px;
                        background: rgba(0,120,212,0.1);
                        transition: all 0.2s ease;
                    " onclick="enviarMensajeDirecto('ayuda')" 
                       onmouseenter="this.style.background='rgba(0,120,212,0.3)'; this.style.transform='translateY(-1px)';"
                       onmouseleave="this.style.background='rgba(0,120,212,0.1)'; this.style.transform='translateY(0)';">
                        📌 Ayuda ➔
                    </div>
                </div>

                <!-- Tiempo del mensaje -->
                <div style="
                    font-size: 10px;
                    color: #a6a6a6;
                    margin: 8px 0 0 0;
                    text-align: right;
                    border-top: 1px solid #3d3d3d;
                    padding-top: 8px;
                ">
                    🕒 ${new Date().toLocaleTimeString()}
                </div>
            </div>
        `;
        
        messagesContainer.appendChild(welcomeMessage);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // 1. PRIMERO: Mostrar mensaje de limpieza
    const cleanMessage = document.createElement('div');
    cleanMessage.style.cssText = `
        align-self: flex-start;
        max-width: 85%;
    `;
    cleanMessage.innerHTML = `
        <div style="
            padding: 16px 18px;
            background: linear-gradient(135deg, #2d2d2d 0%, #1f1f1f 100%);
            border-radius: 18px;
            border-bottom-left-radius: 4px;
            border: 1px solid #3d3d3d;
            font-size: 13px;
            color: white;
            line-height: 1.6;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        ">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                <span style="font-size: 24px;">🧹</span>
                <span style="font-weight: bold; color: #0078d4;">CHAT LIMPIADO</span>
            </div>
            <div style="margin-bottom: 10px;">
                El chat ha sido limpiado. Puedes empezar de nuevo.
            </div>
            <div style="
                background: rgba(0,120,212,0.1);
                padding: 8px 10px;
                border-radius: 8px;
                border-left: 3px solid #0078d4;
                font-size: 12px;
            ">
                💡 Escribe <strong>ayuda</strong> para ver los comandos disponibles.
            </div>
        </div>
        <div style="
            font-size: 10px;
            color: #a6a6a6;
            margin: 4px 8px 0;
            text-align: left;
        ">${new Date().toLocaleTimeString()}</div>
    `;
    
    messagesContainer.appendChild(cleanMessage);
    
    // 2. DESPUÉS: Mostrar mensaje de bienvenida (con un pequeño delay)
    setTimeout(() => {
        mostrarBienvenida();
    }, 500); // 500ms de delay para que se vea primero el mensaje de limpieza
}
// =========================================================================
// AL FINAL DE TU ARCHIVO chatbot.js, DESPUÉS DE TODO
// =========================================================================
window.lanzarConfetti = function() {
    
    // Verificar si ya hay un canvas de confetti
    if (document.getElementById('confetti-canvas')) {
        return;
    }
    
    const canvas = document.createElement('canvas');
    canvas.id = 'confetti-canvas';
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '9999999';
    document.body.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    const colores = [
        '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3',
        '#03a9f4', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#cddc39',
        '#ffeb3b', '#ffc107', '#ff9800', '#ff5722', '#795548', '#9e9e9e'
    ];
    
    let particulas = [];
    const cantidad = 200;
    let animacionFrame;
    
    for (let i = 0; i < cantidad; i++) {
        particulas.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            tamaño: Math.random() * 10 + 3,
            color: colores[Math.floor(Math.random() * colores.length)],
            velocidadX: Math.random() * 8 - 4,
            velocidadY: Math.random() * 7 + 2,
            rotacion: Math.random() * 360,
            rotacionVel: (Math.random() - 0.5) * 0.8,
            tipo: Math.random() > 0.5 ? 'rect' : 'circle'
        });
    }
    
    let tiempoInicio = Date.now();
    const duracion = 3500;
    
    function animar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        for (let i = 0; i < particulas.length; i++) {
            const p = particulas[i];
            
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rotacion * Math.PI / 180);
            ctx.fillStyle = p.color;
            
            if (p.tipo === 'rect') {
                ctx.fillRect(-p.tamaño/2, -p.tamaño/2, p.tamaño, p.tamaño);
            } else {
                ctx.beginPath();
                ctx.arc(0, 0, p.tamaño/2, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.restore();
            
            p.x += p.velocidadX;
            p.y += p.velocidadY;
            p.rotacion += p.rotacionVel;
            p.velocidadY += 0.08;
            
            if (p.y > canvas.height + 50) {
                p.y = -20;
                p.x = Math.random() * canvas.width;
                p.velocidadY = Math.random() * 5 + 2;
                p.velocidadX = Math.random() * 6 - 3;
            }
            
            if (p.x < 0 || p.x > canvas.width) {
                p.velocidadX *= -0.8;
                p.x = Math.max(0, Math.min(p.x, canvas.width));
            }
        }
        
        if (Date.now() - tiempoInicio > duracion) {
            ctx.fillStyle = 'rgba(0,0,0,0.1)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            setTimeout(() => {
                cancelAnimationFrame(animacionFrame);
                if (canvas.parentNode) {
                    document.body.removeChild(canvas);
                }
            }, 500);
            return;
        }
        
        animacionFrame = requestAnimationFrame(animar);
    }
    
    animar();
    
    window.addEventListener('resize', function onResize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }, { once: true });
};

function lanzarCorazones() {
    // Verificar si ya hay un canvas
    if (document.getElementById('confetti-canvas')) return;
    
    const canvas = document.createElement('canvas');
    canvas.id = 'confetti-canvas';
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '9999999';
    document.body.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    // Colores románticos (rosas, rojos, morados)
    const colores = [
        '#ff69b4', // Rosa fuerte
        '#ff1493', // Rosa profundo
        '#ff3366', // Rosa-rojo
        '#ff6b6b', // Rojo claro
        '#ff4444', // Rojo
        '#ff8da1', // Rosa pastel
        '#ff5e7e', // Rosa medio
        '#ff3b5c', // Rojo-rosa
        '#ff1744', // Rojo intenso
        '#d50000', // Rojo oscuro
        '#c51162', // Rosa oscuro
        '#aa00ff', // Morado
        '#e040fb', // Morado claro
        '#7c4dff', // Morado medio
        '#536dfe'  // Azul morado
    ];
    
    // Crear muchas partículas
    let particulas = [];
    const cantidad = 300;
    
    for (let i = 0; i < cantidad; i++) {
        particulas.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height, // Empiezan desde arriba
            radio: Math.random() * 10 + 3, // Tamaño variable
            color: colores[Math.floor(Math.random() * colores.length)],
            velocidadX: Math.random() * 8 - 4, // -4 a 4
            velocidadY: Math.random() * 15 + 10, // 10-25 (MUY RÁPIDO)
            gravedad: Math.random() * 0.5 + 0.3, // 0.3-0.8
            alpha: Math.random() * 0.5 + 0.5 // 0.5-1.0 (transparencia)
        });
    }
    
    let animacionFrame;
    let tiempoInicio = Date.now();
    const duracion = 2000; // 2 segundos
    
    function animar() {
        // Limpiar canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        let activas = 0;
        
        for (let i = 0; i < particulas.length; i++) {
            const p = particulas[i];
            
            // Solo dibujar si está en pantalla
            if (p.y < canvas.height + 50) {
                activas++;
                
                // Dibujar círculo con transparencia
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.radio, 0, Math.PI * 2);
                ctx.fillStyle = p.color;
                ctx.globalAlpha = p.alpha;
                ctx.fill();
                
                // Actualizar posición
                p.x += p.velocidadX;
                p.y += p.velocidadY;
                p.velocidadY += p.gravedad;
                
                // Rebote en los bordes
                if (p.x < 0 || p.x > canvas.width) {
                    p.velocidadX *= -0.5;
                    p.x = Math.max(0, Math.min(p.x, canvas.width));
                }
            }
        }
        
        ctx.globalAlpha = 1.0; // Restaurar alpha
        
        // Terminar si ya pasó el tiempo o no hay partículas activas
        if (Date.now() - tiempoInicio > duracion || activas === 0) {
            cancelAnimationFrame(animacionFrame);
            document.body.removeChild(canvas);
            return;
        }
        
        animacionFrame = requestAnimationFrame(animar);
    }
    
    animar();
}

window.lanzarCorazones = lanzarCorazones;

function lanzarEstrellas() {
    if (document.getElementById('estrellas-canvas')) return;
    
    const canvas = document.createElement('canvas');
    canvas.id = 'estrellas-canvas';
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '9999999';
    document.body.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    const colores = ['#FFD700', '#FFE55C', '#FFD700', '#FFFACD', '#FFFFE0'];
    let estrellas = [];
    const cantidad = 100;
    
    for (let i = 0; i < cantidad; i++) {
        estrellas.push({
            x: Math.random() * canvas.width,
            y: Math.random() * -canvas.height,
            tamaño: Math.random() * 4 + 2,
            color: colores[Math.floor(Math.random() * colores.length)],
            velocidadX: Math.random() * 10 + 5,
            velocidadY: Math.random() * 3 + 1,
            angulo: Math.random() * 360
        });
    }
    
    function dibujarEstrella(x, y, tamaño, color) {
        ctx.save();
        ctx.translate(x, y);
        ctx.fillStyle = color;
        
        // Dibujar estrella de 5 puntas
        ctx.beginPath();
        for (let i = 0; i < 5; i++) {
            const angulo = (i * 72 - 90) * Math.PI / 180;
            const radioExterior = tamaño;
            const radioInterior = tamaño * 0.4;
            
            if (i === 0) {
                ctx.moveTo(Math.cos(angulo) * radioExterior, Math.sin(angulo) * radioExterior);
            } else {
                ctx.lineTo(Math.cos(angulo) * radioExterior, Math.sin(angulo) * radioExterior);
            }
            
            const anguloInterior = angulo + 36 * Math.PI / 180;
            ctx.lineTo(Math.cos(anguloInterior) * radioInterior, Math.sin(anguloInterior) * radioInterior);
        }
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }
    
    let animacionFrame;
    let tiempoInicio = Date.now();
    const duracion = 2000;
    
    function animar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        for (let e of estrellas) {
            dibujarEstrella(e.x, e.y, e.tamaño, e.color);
            e.x -= e.velocidadX;
            e.y += e.velocidadY;
            
            if (e.x < -50) {
                e.x = canvas.width + 50;
                e.y = Math.random() * canvas.height;
            }
        }
        
        if (Date.now() - tiempoInicio > duracion) {
            cancelAnimationFrame(animacionFrame);
            document.body.removeChild(canvas);
            return;
        }
        
        animacionFrame = requestAnimationFrame(animar);
    }
    
    animar();
}

window.lanzarEstrellas = lanzarEstrellas;

// =========================================================================
// SELECTOR DE EMOJIS PARA EL INPUT DEL CHAT
// =========================================================================
function agregarSelectorEmojis() {
    const inputContainer = document.querySelector('#chat-window-final > div:last-child');
    if (!inputContainer) return;
    
    // Crear contenedor para el botón de emojis
    const emojiContainer = document.createElement('div');
    emojiContainer.style.cssText = `
        position: relative;
        display: inline-block;
    `;
    
    // Botón para abrir selector de emojis
    const emojiBtn = document.createElement('button');
    emojiBtn.id = 'emoji-selector-btn';
    emojiBtn.innerHTML = '<i class="far fa-smile"></i>';
    emojiBtn.style.cssText = `
        background: transparent;
        border: none;
        color: #a6a6a6;
        width: 30px;
        height: 30px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: all 0.2s ease;
        border-radius: 50%;
    `;
    emojiBtn.addEventListener('mouseenter', () => {
        emojiBtn.style.background = '#3d3d3d';
        emojiBtn.style.color = 'white';
    });
    emojiBtn.addEventListener('mouseleave', () => {
        emojiBtn.style.background = 'transparent';
        emojiBtn.style.color = '#a6a6a6';
    });
    
    // Panel de emojis (inicialmente oculto)
    const emojiPanel = document.createElement('div');
    emojiPanel.id = 'emoji-panel';
    emojiPanel.style.cssText = `
        position: absolute;
        bottom: 45px;
        left: 0;
        background: #2d2d2d;
        border: 1px solid #3d3d3d;
        border-radius: 12px;
        padding: 10px;
        display: none;
        grid-template-columns: repeat(5, 1fr);
        gap: 5px;
        width: 300px;
        max-height: 250px;
        overflow-y: auto;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        z-index: 1000;
    `;
    
    // Lista de emojis comunes
const emojis = [
    // 😀 Smileys & Emotion
    '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰',
    '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🥸', '🤩', '🥳',
    '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤',
    '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🤭', '🤫',
    '🤥', '😶', '😐', '😑', '😬', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵',
    '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕', '🤑', '🤠', '😈', '👿', '👹', '👺', '🤡', '💩',
    '👻', '💀', '☠️', '👽', '👾', '🤖', '🎃', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾',
    
    // ❤️ Corazones
    '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖',
    '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈',
    
    // 👋 Personas y gestos
    '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🫰', '🤟', '🤘', '🤙', '🫵', '🫱',
    '🫲', '🫳', '🫴', '👈', '👉', '👆', '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏',
    '🙌', '🫶', '👐', '🤲', '🤝', '🙏', '✍️', '💅', '🤳', '💪', '🦾', '🦵', '🦿', '🦶', '👂', '🦻',
    '👃', '🧠', '🫀', '🫁', '🦷', '🦴', '👀', '👁️', '👅', '👄', '🫦', '👶', '🧒', '👦', '👧', '🧑',
    '👱', '👨', '🧔', '🧔‍♂️', '🧔‍♀️', '👨‍🦰', '👨‍🦱', '👨‍🦳', '👨‍🦲', '👩', '👩‍🦰', '🧑‍🦰', '👩‍🦱', '🧑‍🦱', '👩‍🦳', '🧑‍🦳',
    '👩‍🦲', '🧑‍🦲', '👱‍♀️', '👱‍♂️', '🧓', '👴', '👵', '🙍', '🙍‍♂️', '🙍‍♀️', '🙎', '🙎‍♂️', '🙎‍♀️', '🙅', '🙅‍♂️', '🙅‍♀️',
    '🙆', '🙆‍♂️', '🙆‍♀️', '💁', '💁‍♂️', '💁‍♀️', '🙋', '🙋‍♂️', '🙋‍♀️', '🧏', '🧏‍♂️', '🧏‍♀️', '🙇', '🙇‍♂️', '🙇‍♀️', '🤦',
    '🤦‍♂️', '🤦‍♀️', '🤷', '🤷‍♂️', '🤷‍♀️', '🧑‍⚕️', '👨‍⚕️', '👩‍⚕️', '🧑‍🎓', '👨‍🎓', '👩‍🎓', '🧑‍🏫', '👨‍🏫', '👩‍🏫', '🧑‍⚖️', '👨‍⚖️',
    '👩‍⚖️', '🧑‍🌾', '👨‍🌾', '👩‍🌾', '🧑‍🍳', '👨‍🍳', '👩‍🍳', '🧑‍🔧', '👨‍🔧', '👩‍🔧', '🧑‍🏭', '👨‍🏭', '👩‍🏭', '🧑‍💼', '👨‍💼', '👩‍💼',
    '🧑‍🔬', '👨‍🔬', '👩‍🔬', '🧑‍💻', '👨‍💻', '👩‍💻', '🧑‍🎤', '👨‍🎤', '👩‍🎤', '🧑‍🎨', '👨‍🎨', '👩‍🎨', '🧑‍✈️', '👨‍✈️', '👩‍✈️', '🧑‍🚀',
    '👨‍🚀', '👩‍🚀', '🧑‍🚒', '👨‍🚒', '👩‍🚒', '👮', '👮‍♂️', '👮‍♀️', '🕵️', '🕵️‍♂️', '🕵️‍♀️', '💂', '💂‍♂️', '💂‍♀️', '🥷', '👷',
    '👷‍♂️', '👷‍♀️', '🫅', '🤴', '👸', '👳', '👳‍♂️', '👳‍♀️', '👲', '🧕', '🤵', '🤵‍♂️', '🤵‍♀️', '👰', '👰‍♂️', '👰‍♀️',
    '🤰', '🫃', '🫄', '🤱', '👩‍🍼', '👨‍🍼', '🧑‍🍼', '👼', '🎅', '🤶', '🧑‍🎄', '🦸', '🦸‍♂️', '🦸‍♀️', '🦹', '🦹‍♂️',
    '🦹‍♀️', '🧙', '🧙‍♂️', '🧙‍♀️', '🧚', '🧚‍♂️', '🧚‍♀️', '🧛', '🧛‍♂️', '🧛‍♀️', '🧜', '🧜‍♂️', '🧜‍♀️', '🧝', '🧝‍♂️', '🧝‍♀️',
    '🧞', '🧞‍♂️', '🧞‍♀️', '🧟', '🧟‍♂️', '🧟‍♀️', '💆', '💆‍♂️', '💆‍♀️', '💇', '💇‍♂️', '💇‍♀️', '🚶', '🚶‍♂️', '🚶‍♀️', '🧍',
    '🧍‍♂️', '🧍‍♀️', '🧎', '🧎‍♂️', '🧎‍♀️', '🧑‍🦯', '👨‍🦯', '👩‍🦯', '🧑‍🦼', '👨‍🦼', '👩‍🦼', '🧑‍🦽', '👨‍🦽', '👩‍🦽', '🏃', '🏃‍♂️',
    '🏃‍♀️', '💃', '🕺', '🕴️', '👯', '👯‍♂️', '👯‍♀️', '🧖', '🧖‍♂️', '🧖‍♀️', '🧘', '🧑‍🤝‍🧑', '👭', '👫', '👬', '💏',
    '👩‍❤️‍💋‍👨', '👨‍❤️‍💋‍👨', '👩‍❤️‍💋‍👩', '💑', '👩‍❤️‍👨', '👨‍❤️‍👨', '👩‍❤️‍👩', '👪', '👨‍👩‍👦', '👨‍👩‍👧', '👨‍👩‍👧‍👦', '👨‍👩‍👦‍👦', '👨‍👩‍👧‍👧', '👨‍👨‍👦', '👨‍👨‍👧', '👨‍👨‍👧‍👦',
    
    // 🐶 Animales y naturaleza
    '🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐻‍❄️', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽', '🐸',
    '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🐺', '🐗', '🐴', '🦄', '🐝',
    '🪱', '🐛', '🦋', '🐌', '🐞', '🐜', '🪰', '🪲', '🪳', '🐜', '🕷️', '🕸️', '🦂', '🦟', '🪳', '🐢',
    '🐍', '🦎', '🦖', '🦕', '🐙', '🦑', '🦐', '🦞', '🦀', '🐡', '🐠', '🐟', '🐬', '🐳', '🐋', '🦈',
    '🐊', '🐅', '🐆', '🦓', '🦍', '🦧', '🦣', '🐘', '🦛', '🦏', '🐪', '🐫', '🦒', '🦘', '🦬', '🐃',
    '🐂', '🐄', '🐎', '🐖', '🐏', '🐑', '🦙', '🐐', '🦌', '🐕', '🐩', '🦮', '🐕‍🦺', '🐈', '🐈‍⬛', '🪶',
    '🐓', '🦃', '🦤', '🦚', '🦜', '🦢', '🦩', '🕊️', '🐇', '🦝', '🦨', '🦡', '🦫', '🦦', '🦥', '🐁',
    '🐀', '🐿️', '🦔', '🐾', '🐉', '🐲', '🌵', '🎄', '🌲', '🌳', '🌴', '🪹', '🪺', '🌱', '🌿', '☘️',
    '🍀', '🎍', '🪴', '🎋', '🍃', '🍂', '🍁', '🍄', '🌾', '💐', '🌷', '🌹', '🥀', '🌺', '🌸', '🌼',
    '🌻', '🌞', '🌝', '🌛', '🌜', '🌚', '🌕', '🌖', '🌗', '🌘', '🌑', '🌒', '🌓', '🌔', '🌙', '🌎',
    '🌍', '🌏', '🪐', '💫', '⭐', '🌟', '✨', '⚡', '☄️', '💥', '🔥', '🌪️', '🌈', '☀️', '🌤️', '⛅',
    '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '☃️', '⛄', '🌬️', '💨', '💧', '💦', '☔', '☂️',
    
    // 🍔 Comida y bebida
    '🍇', '🍈', '🍉', '🍊', '🍋', '🍌', '🍍', '🥭', '🍎', '🍏', '🍐', '🍑', '🍒', '🍓', '🫐', '🥝',
    '🍅', '🫒', '🥥', '🥑', '🍆', '🥔', '🥕', '🌽', '🌶️', '🫑', '🥒', '🥬', '🥦', '🧄', '🧅', '🍄',
    '🥜', '🫘', '🌰', '🍞', '🥐', '🥖', '🫓', '🥨', '🥯', '🥞', '🧇', '🧀', '🍖', '🍗', '🥩', '🥓',
    '🍔', '🍟', '🍕', '🌭', '🥪', '🌮', '🌯', '🫔', '🥙', '🧆', '🥚', '🍳', '🥘', '🍲', '🫕', '🥣',
    '🥗', '🍿', '🧈', '🧂', '🥫', '🍱', '🍘', '🍙', '🍚', '🍛', '🍜', '🍝', '🍠', '🍢', '🍣', '🍤',
    '🍥', '🥮', '🍡', '🥟', '🥠', '🥡', '🦀', '🦞', '🦐', '🦑', '🦪', '🍦', '🍧', '🍨', '🍩', '🍪',
    '🎂', '🍰', '🧁', '🥧', '🍫', '🍬', '🍭', '🍮', '🍯', '🍼', '🥛', '☕', '🫖', '🍵', '🍶', '🍾',
    '🍷', '🍸', '🍹', '🍺', '🍻', '🥂', '🥃', '🫗', '🥤', '🧋', '🧃', '🧉', '🧊', '🥢', '🍽️', '🍴',
    '🥄', '🔪', '🫙', '🏺',
    
    // ⚽ Deportes
    '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎳', '🏏', '🏑', '🏒', '🥍', '🏓', '🏸',
    '🥊', '🥋', '🥅', '⛳', '⛸️', '🎣', '🤿', '🎽', '🎿', '🛷', '🥌', '🎯', '🪀', '🪁', '🎱', '🔮',
    '🪄', '🧿', '🕹️', '🎰', '🎲', '🧩', '🧸', '🪅', '🪩', '🪆', '♠️', '♥️', '♦️', '♣️', '♟️', '🃏',
    '🀄', '🎴', '🎭', '🖼️', '🎨', '🧵', '🪡', '🧶', '🪢',
    
    // 🚗 Viajes y lugares
    '🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🦯', '🦽',
    '🦼', '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋',
    '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️', '🛫', '🛬', '🛩️', '💺', '🛰️',
    '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⛴️', '🚢', '⚓', '🪝', '⛽', '🚧', '🚦', '🚥',
    '🚏', '🗺️', '🗿', '🏧', '🚮', '🚰', '♿', '🚹', '🚺', '🚻', '🚼', '🚾', '🛂', '🛃', '🛄', '🛅',
    
    // ⌚ Relojes y tiempo
    '⌛', '⏳', '⌚', '⏰', '⏱️', '⏲️', '🕰️', '🕛', '🕧', '🕐', '🕜', '🕑', '🕝', '🕒', '🕞', '🕓',
    '🕟', '🕔', '🕠', '🕕', '🕡', '🕖', '🕢', '🕗', '🕣', '🕘', '🕤', '🕙', '🕥', '🕚', '🕦', '🌀',
    
    // 🌈 Símbolos
    '🌈', '🌂', '☂️', '🧵', '🎀', '🎁', '🎗️', '🎟️', '🎫', '🎖️', '🏆', '🏅', '🥇', '🥈', '🥉', '⚽',
    '⚾', '🥎', '🏀', '🏐', '🏈', '🏉', '🎾', '🥏', '🎳', '🏏', '🏑', '🏒', '🥍', '🏓', '🏸', '🥊',
    '🥋', '🥅', '⛳', '⛸️', '🎣', '🤿', '🎽', '🎿', '🛷', '🥌', '🎯', '🪀', '🪁', '🔮', '🪄', '🧿',
    '🪄', '🎮', '🕹️', '🎰', '🎲', '🧩', '🧸', '🪅', '🪩', '🪆', '♠️', '♥️', '♦️', '♣️', '♟️', '🃏',
    '🀄', '🎴', '🎭', '🖼️', '🎨', '🧵', '🪡', '🧶', '🪢',
    
    // 🔡 Símbolos adicionales
    '🔠', '🔡', '🔢', '🔣', '🔤', '🅰️', '🆎', '🅱️', '🆑', '🆒', '🆓', 'ℹ️', '🆔', 'Ⓜ️', '🆕', '🆖',
    '🆗', '🆘', '🆙', '🆚', '🈁', '🈂️', '🈷️', '🈶', '🈯', '🉐', '🈹', '🈚', '🈲', '🈸', '🈺', '🈴',
    '🈳', '㊗️', '㊙️', '🈺', '🈵', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '🟤', '⚫', '⚪', '🟥', '🟧',
    '🟨', '🟩', '🟦', '🟪', '🟫', '⬛', '⬜', '◼️', '◻️', '◾', '◽', '▪️', '▫️', '🔶', '🔷', '🔸',
    '🔹', '🔺', '🔻', '💠', '🔘', '🔲', '🔳', '⚪', '⚫', '🔴', '🔵',
    
    // 🔢 Números y letras
    '0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟', '🔢', '#️⃣', '*️⃣', '⏏️', '▶️',
    '⏸️', '⏯️', '⏹️', '⏺️', '⏭️', '⏮️', '⏩', '⏪', '⏫', '⏬', '◀️', '🔼', '🔽', '➡️', '⬅️', '⬆️',
    '⬇️', '↗️', '↘️', '↙️', '↖️', '↕️', '↔️', '🔄', '↪️', '↩️', '⤴️', '⤵️', '🔀', '🔁', '🔂', '🆒',
    
    // 🎵 Música
    '🎵', '🎶', '🎼', '🎤', '🎧', '🎸', '🎹', '🎺', '🎻', '🪕', '🥁', '🪘', '🎬', '🎥', '🎞️', '📽️',
    '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌛', '⏳', '📡', '🔋', '🔌', '💡',
    '🔦', '🪔', '🧯', '🛢️', '💸', '💵', '💴', '💶', '💷', '💰', '💳', '💎', '⚖️', '🪤', '🧰', '🪛',
    '🔧', '🔨', '⚒️', '🛠️', '⛏️', '🪚', '🔩', '⚙️', '🪤', '🧱', '⛓️', '🪝', '🔫', '💣', '🧨', '🪓',
    '🔪', '🗡️', '⚔️', '🛡️', '🚬', '⚰️', '⚱️', '🏺', '🔮', '📿', '🧿', '💈', '⚗️', '🔭', '🔬', '🕳️',
    '💊', '💉', '🩸', '🩹', '🩺', '🩻', '🚽', '🚿', '🛁', '🪥', '🪒', '🧴', '🧼', '🫧', '🧽', '🧹',
    '🧺', '🧻', '🚪', '🛏️', '🛋️', '🪑', '🚽', '🚿', '🛁', '🪥', '🪒', '🧴', '🧼', '🫧', '🧽', '🧹',
    
    // 🏳️ Banderas
    '🏳️', '🏴', '🏁', '🚩', '🏳️‍🌈', '🏳️‍⚧️', '🏴‍☠️', '🇦🇨', '🇦🇩', '🇦🇪', '🇦🇫', '🇦🇬', '🇦🇮', '🇦🇱', '🇦🇲', '🇦🇴',
    '🇦🇶', '🇦🇷', '🇦🇸', '🇦🇹', '🇦🇺', '🇦🇼', '🇦🇽', '🇦🇿', '🇧🇦', '🇧🇧', '🇧🇩', '🇧🇪', '🇧🇫', '🇧🇬', '🇧🇭', '🇧🇮',
    '🇧🇯', '🇧🇱', '🇧🇲', '🇧🇳', '🇧🇴', '🇧🇶', '🇧🇷', '🇧🇸', '🇧🇹', '🇧🇻', '🇧🇼', '🇧🇾', '🇧🇿', '🇨🇦', '🇨🇨', '🇨🇩',
    '🇨🇫', '🇨🇬', '🇨🇭', '🇨🇮', '🇨🇰', '🇨🇱', '🇨🇲', '🇨🇳', '🇨🇴', '🇨🇵', '🇨🇷', '🇨🇺', '🇨🇻', '🇨🇼', '🇨🇽', '🇨🇾',
    '🇨🇿', '🇩🇪', '🇩🇬', '🇩🇯', '🇩🇰', '🇩🇲', '🇩🇴', '🇩🇿', '🇪🇦', '🇪🇨', '🇪🇪', '🇪🇬', '🇪🇭', '🇪🇷', '🇪🇸', '🇪🇹',
    '🇪🇺', '🇫🇮', '🇫🇯', '🇫🇰', '🇫🇲', '🇫🇴', '🇫🇷', '🇬🇦', '🇬🇧', '🇬🇩', '🇬🇪', '🇬🇫', '🇬🇬', '🇬🇭', '🇬🇮', '🇬🇱',
    '🇬🇲', '🇬🇳', '🇬🇵', '🇬🇶', '🇬🇷', '🇬🇸', '🇬🇹', '🇬🇺', '🇬🇼', '🇬🇾', '🇭🇰', '🇭🇲', '🇭🇳', '🇭🇷', '🇭🇹', '🇭🇺',
    '🇮🇨', '🇮🇩', '🇮🇪', '🇮🇱', '🇮🇲', '🇮🇳', '🇮🇴', '🇮🇶', '🇮🇷', '🇮🇸', '🇮🇹', '🇯🇪', '🇯🇲', '🇯🇴', '🇯🇵'
];
    

// Al crear cada emojiItem, cambia el estilo para incluir la transición y el hover
emojis.forEach(emoji => {
    const emojiItem = document.createElement('span');
    emojiItem.textContent = emoji;
    emojiItem.style.cssText = `
        font-size: 22px;
        cursor: pointer;
        padding: 5px;
        text-align: center;
        border-radius: 8px;
        transition: transform 0.2s ease, background 0.2s ease;
        display: inline-block;
    `;
    
    // Efecto hover - agrandar el emoji
    emojiItem.addEventListener('mouseenter', () => {
        emojiItem.style.transform = 'scale(1.5)';
        emojiItem.style.background = '#3d3d3d';
        emojiItem.style.zIndex = '10';
        emojiItem.style.position = 'relative';
    });
    
    emojiItem.addEventListener('mouseleave', () => {
        emojiItem.style.transform = 'scale(1)';
        emojiItem.style.background = 'transparent';
        emojiItem.style.zIndex = 'auto';
        emojiItem.style.position = 'static';
    });
    
    emojiItem.addEventListener('click', () => {
        const input = document.getElementById('chat-input-final');
        if (input) {
            input.value += emoji;
            input.focus();
            emojiPanel.style.display = 'none';
            emojiBtn.innerHTML = '<i class="far fa-smile"></i>';
        }
    });
    
    emojiPanel.appendChild(emojiItem);
});
    
    // Toggle del panel de emojis
    emojiBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (emojiPanel.style.display === 'grid') {
            emojiPanel.style.display = 'none';
            emojiBtn.innerHTML = '<i class="far fa-smile"></i>';
        } else {
            emojiPanel.style.display = 'grid';
            emojiBtn.innerHTML = '<i class="fas fa-times"></i>';
        }
    });
    
    // Cerrar panel al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!emojiContainer.contains(e.target)) {
            emojiPanel.style.display = 'none';
            emojiBtn.innerHTML = '<i class="far fa-smile"></i>';
        }
    });
    
    emojiContainer.appendChild(emojiBtn);
    emojiContainer.appendChild(emojiPanel);
    
    // Insertar el botón antes del input
    inputContainer.insertBefore(emojiContainer, inputContainer.firstChild);
    
    // Ajustar el ancho del input
    const input = document.getElementById('chat-input-final');
    if (input) {
        input.style.flex = '1';
    }
}

// Llamar a la función después de crear el chat
setTimeout(() => {
    if (document.getElementById('chat-window-final')) {
        agregarSelectorEmojis();
    }
}, 2000);


// =========================================================================
// MENÚ CONTEXTUAL PARA EL ROBOT ASISTENTE (CLIC DERECHO) - CON ANIMACIONES
// =========================================================================

function agregarMenuContextualRobot() {
    const robotContainer = document.getElementById('robot-asistente-container');
    if (!robotContainer) {
        console.warn('⚠️ No se encontró el contenedor del robot para agregar menú contextual');
        return;
    }

    // Prevenir el menú contextual por defecto del navegador
    robotContainer.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Eliminar menú existente si hay alguno
        const menuExistente = document.getElementById('robot-context-menu');
        if (menuExistente) {
            menuExistente.remove();
        }
        
        // Crear el menú contextual
        const menu = document.createElement('div');
        menu.id = 'robot-context-menu';
        
        // Estilos base
        menu.style.cssText = `
            position: fixed;
            background: #2d2d2d;
            border: 2px solid #4caf50;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            z-index: 10000000;
            min-width: 200px;
            overflow: hidden;
            animation: menuFadeIn 0.2s ease;
        `;
        
        // Estilo para los items del menú
        const itemBaseStyle = `
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #e0e0e0;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #4a4a4a;
            background: #2d2d2d;
        `;
        
        // Opción 1: Restaurar posición
        const restaurarItem = document.createElement('div');
        restaurarItem.innerHTML = '<span style="font-size: 18px;">📍</span> <strong>Restaurar posición</strong>';
        restaurarItem.style.cssText = itemBaseStyle;
        restaurarItem.addEventListener('mouseenter', () => {
            restaurarItem.style.background = '#3d6b3d';
            restaurarItem.style.color = 'white';
            restaurarItem.style.paddingLeft = '24px';
        });
        restaurarItem.addEventListener('mouseleave', () => {
            restaurarItem.style.background = '#2d2d2d';
            restaurarItem.style.color = '#e0e0e0';
            restaurarItem.style.paddingLeft = '20px';
        });
        restaurarItem.addEventListener('click', (event) => {
            event.stopPropagation();
            restaurarPosicionRobot();
            menu.remove();
        });
        
        // Opci�n 2: ANIMAR (NUEVA)
        const animarItem = document.createElement('div');
        animarItem.innerHTML = '<span style="font-size: 18px;">✨</span> <strong>Animar</strong>';
        animarItem.style.cssText = itemBaseStyle;
        animarItem.addEventListener('mouseenter', () => {
            animarItem.style.background = '#9c27b0';
            animarItem.style.color = 'white';
            animarItem.style.paddingLeft = '24px';
        });
        animarItem.addEventListener('mouseleave', () => {
            animarItem.style.background = '#2d2d2d';
            animarItem.style.color = '#e0e0e0';
            animarItem.style.paddingLeft = '20px';
        });
        animarItem.addEventListener('click', (event) => {
            event.stopPropagation();
            menu.remove();
            animarRobot();
        });
        
        // Opción 3: Contar chiste
        const chisteItem = document.createElement('div');
        chisteItem.innerHTML = '<span style="font-size: 18px;">😄</span> <strong>Contar chiste</strong>';
        chisteItem.style.cssText = itemBaseStyle;
        chisteItem.addEventListener('mouseenter', () => {
            chisteItem.style.background = '#ff9800';
            chisteItem.style.color = 'white';
            chisteItem.style.paddingLeft = '24px';
        });
        chisteItem.addEventListener('mouseleave', () => {
            chisteItem.style.background = '#2d2d2d';
            chisteItem.style.color = '#e0e0e0';
            chisteItem.style.paddingLeft = '20px';
        });
        chisteItem.addEventListener('click', (event) => {
            event.stopPropagation();
            menu.remove();
            
            // Enviar comando de chiste al chatbot
            const input = document.getElementById('chat-input-final');
            if (input) {
                input.value = 'chiste';
                const chatWindow = document.getElementById('chat-window-final');
                if (chatWindow && chatWindow.style.display !== 'flex') {
                    chatWindow.style.display = 'flex';
                    setTimeout(() => {
                        const sendButton = document.getElementById('send-message-final');
                        if (sendButton) sendButton.click();
                    }, 300);
                } else {
                    setTimeout(() => {
                        const sendButton = document.getElementById('send-message-final');
                        if (sendButton) sendButton.click();
                    }, 100);
                }
            } else {
                mostrarNotificacion('😄 Abre el chat para escuchar el chiste');
            }
        });
        
        // Opción 4: Esconder
        const esconderItem = document.createElement('div');
        esconderItem.innerHTML = '<span style="font-size: 18px;">👻</span> <strong>Esconder</strong>';
        esconderItem.style.cssText = itemBaseStyle;
        esconderItem.style.borderBottom = 'none';
        esconderItem.addEventListener('mouseenter', () => {
            esconderItem.style.background = '#9e3a3a';
            esconderItem.style.color = 'white';
            esconderItem.style.paddingLeft = '24px';
        });
        esconderItem.addEventListener('mouseleave', () => {
            esconderItem.style.background = '#2d2d2d';
            esconderItem.style.color = '#e0e0e0';
            esconderItem.style.paddingLeft = '20px';
        });
        esconderItem.addEventListener('click', (event) => {
            event.stopPropagation();
            esconderRobot();
            menu.remove();
        });
        
        menu.appendChild(restaurarItem);
        menu.appendChild(animarItem);
        menu.appendChild(chisteItem);
        menu.appendChild(esconderItem);
        
        // Agregar el menú al body para poder medir su tamaño
        document.body.appendChild(menu);
        
        // =========================================================
        // CALCULAR Y AJUSTAR LA POSICIÓN PARA QUE NO SE SALGA DE LA PANTALLA
        // =========================================================
        const menuRect = menu.getBoundingClientRect();
        const menuWidth = menuRect.width;
        const menuHeight = menuRect.height;
        
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        
        let left = e.clientX;
        let top = e.clientY;
        
        if (left + menuWidth > windowWidth) {
            left = windowWidth - menuWidth - 10;
        }
        if (left < 10) {
            left = 10;
        }
        if (top + menuHeight > windowHeight) {
            top = windowHeight - menuHeight - 10;
        }
        if (top < 10) {
            top = 10;
        }
        
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        
        // Cerrar menú al hacer clic fuera
        const cerrarMenu = function(event) {
            const menuActual = document.getElementById('robot-context-menu');
            if (menuActual && !menuActual.contains(event.target)) {
                menuActual.remove();
                document.removeEventListener('click', cerrarMenu);
                document.removeEventListener('contextmenu', cerrarMenu);
            }
        };
        
        setTimeout(() => {
            document.addEventListener('click', cerrarMenu);
            document.addEventListener('contextmenu', cerrarMenu);
        }, 100);
        
        const cerrarConEscape = function(e) {
            if (e.key === 'Escape') {
                const menuActual = document.getElementById('robot-context-menu');
                if (menuActual) {
                    menuActual.remove();
                    document.removeEventListener('keydown', cerrarConEscape);
                    document.removeEventListener('click', cerrarMenu);
                    document.removeEventListener('contextmenu', cerrarMenu);
                }
            }
        };
        document.addEventListener('keydown', cerrarConEscape);
    });
}

// =========================================================================
// FUNCIÓN DE ANIMACIONES ALEATORIAS PARA EL ROBOT
// =========================================================================

function animarRobot() {
    const robot = document.getElementById('robot-avatar-vuelo');
    const robotContainer = document.getElementById('robot-asistente-container');
    
    if (!robot) return;
    
    // Guardar el estado original para restaurarlo después
    const teniaClasePulsante = robot.classList.contains('luz-pulsante-verde');
    const teniaModoBola = robot.classList.contains('modo-bola');
    
    // Lista de animaciones disponibles
    const animaciones = [
        'sacudir',
        'bailar',
        'saltar',
        'girar',
        'vibrar',
        'flotar',
        'rebotar',
        'brillar',
        'saludar',
        'estrellas',
		'prominente'
    ];
    
    // Seleccionar animación aleatoria
    const animacionSeleccionada = animaciones[Math.floor(Math.random() * animaciones.length)];
    
    // Eliminar cualquier clase de animaci�n previa
    robot.classList.remove('animar-sacudir', 'animar-bailar', 'animar-saltar', 'animar-girar', 'animar-vibrar', 'animar-flotar', 'animar-rebotar', 'animar-brillar', 'animar-saludar', 'animar-estrellas', 'animar-prominente');

	
	
    // Aplicar animación según la seleccionada
    switch(animacionSeleccionada) {
        case 'sacudir':
            robot.classList.add('animar-sacudir');
            //mostrarNotificacion('🤖 ¡Sacudiéndome!');
            break;
        case 'bailar':
            robot.classList.add('animar-bailar');
            //mostrarNotificacion('🕺 ¡A bailar!');
            break;
        case 'saltar':
            robot.classList.add('animar-saltar');
            //mostrarNotificacion('🤸‍♂️ ¡Salto!');
            break;
        case 'girar':
            robot.classList.add('animar-girar');
            //mostrarNotificacion('🌀 ¡Girando como trompo!');
            break;
        case 'vibrar':
            robot.classList.add('animar-vibrar');
            //mostrarNotificacion('📳 *vibración*');
            break;
        case 'flotar':
            robot.classList.add('animar-flotar');
            //mostrarNotificacion('🎈 ¡Flotando en el aire!');
            break;
        case 'rebotar':
            robot.classList.add('animar-rebotar');
            //mostrarNotificacion('⚽ ¡Boing boing!');
            break;
        case 'brillar':
            robot.classList.add('animar-brillar');
            robotContainer.classList.add('animar-zoom');
            //mostrarNotificacion('✨ ¡Brillando como estrella!');
            break;
        case 'saludar':
            robot.classList.add('animar-saludar');
            //mostrarNotificacion('👋 ¡Hola!');
            
            const globoTexto = document.getElementById('robot-globo-texto');
            if (globoTexto) {
                const contenidoOriginal = globoTexto.innerHTML;
                globoTexto.innerHTML = `
                    <div style="
                        position: relative; 
                        background: #ffffff; 
                        color: #333; 
                        padding: 15px; 
                        border-radius: 20px; 
                        box-shadow: 0 10px 25px rgba(0,0,0,0.2); 
                        font-size: 16px; 
                        line-height: 1.5; 
                        max-width: 250px; 
                        border-left: 5px solid #2e7d5e;
                        font-family: 'Segoe UI', Roboto, sans-serif;
                        text-align: center;
                    ">
                        <div style="font-size: 48px; margin-bottom: 5px;">👋</div>
                        <strong style="font-size: 18px; color: #2e7d5e;">¡HOLA!</strong>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">¿Cómo estás?</div>
                        <div style="
                            position: absolute; 
                            bottom: -10px; 
                            left: 30px; 
                            width: 0; height: 0; 
                            border-left: 12px solid transparent; 
                            border-right: 12px solid transparent; 
                            border-top: 12px solid white;
                        "></div>
                    </div>
                `;
                globoTexto.style.display = 'block';
                globoTexto.style.opacity = '1';
                globoTexto.style.transform = 'translateY(0) scale(1)';
                
                setTimeout(() => {
                    if (globoTexto) {
                        globoTexto.innerHTML = contenidoOriginal;
                        globoTexto.style.opacity = '0';
                        globoTexto.style.transform = 'translateY(15px) scale(0.9)';
                        setTimeout(() => {
                            if (globoTexto && globoTexto.style.opacity === '0') {
                                globoTexto.style.display = 'none';
                            }
                        }, 400);
                    }
                }, 3000);
            }
            break;
        case 'estrellas':
            robot.classList.add('animar-estrellas');
            lanzarEstrellasAlrededor(robot);
            
            const originalBorder = robot.style.border;
            const originalBoxShadow = robot.style.boxShadow;
            robot.style.border = '3px solid #ffd700';
            robot.style.boxShadow = '0 0 20px #ffaa00';
            
            setTimeout(() => {
                robot.style.border = originalBorder;
                robot.style.boxShadow = originalBoxShadow;
            }, 800);
            break;
case 'prominente':
            // Llamar a la funci�n de animaci�n prominente ya definida
            if (typeof ejecutarAnimacionRobotProminente === 'function') {
                ejecutarAnimacionRobotProminente(true);
            } else {
                robot.classList.add('animar-sacudir');
                setTimeout(() => {
                    robot.classList.remove('animar-sacudir');
                    restaurarEstadoRobot(robot, robotContainer);
                }, 1500);
            }
            return; // Salir porque la animaci�n prominente ya maneja su propio setTimeout
    }
    
    // Eliminar la animación después de 1.5 segundos y RESTAURAR EL EFECTO PULSANTE
    setTimeout(() => {
        robot.classList.remove('animar-sacudir', 'animar-bailar', 'animar-saltar', 'animar-girar', 'animar-vibrar', 'animar-flotar', 'animar-rebotar', 'animar-brillar', 'animar-saludar', 'animar-estrellas');
        robotContainer.classList.remove('animar-zoom', 'animar-brillo');
        
        // 👇 RESTAURAR EL EFECTO DE LUZ PULSANTE SI EL CHAT ESTÁ CERRADO
        const chatWindow = document.getElementById('chat-window-final');
        const chatEstaAbierto = chatWindow && chatWindow.style.display === 'flex';
        
        // Solo restaurar el efecto pulsante si el chat está CERRADO
        if (!chatEstaAbierto) {
            // Restaurar estilo original del robot
            robot.style.width = '65px';
            robot.style.height = '65px';
            robot.style.borderRadius = '50%';
            robot.style.background = '#1a1a1a url("assets/logobot.png") no-repeat center center';
            robot.style.backgroundSize = 'contain';
            robot.style.border = '2px solid #2e7d5e';
            robot.style.padding = '5px';
            
            // Agregar las clases del efecto pulsante si no las tiene
            if (!robot.classList.contains('luz-pulsante-verde')) {
                robot.classList.add('luz-pulsante-verde');
            }
            if (!robot.classList.contains('modo-bola')) {
                robot.classList.add('modo-bola');
            }
        } else {
            // Si el chat está abierto, mantener el estado expandido
            robot.style.width = '85px';
            robot.style.height = '85px';
            robot.style.borderRadius = '0';
            robot.style.background = 'url("assets/logobot.png") no-repeat center center';
            robot.style.backgroundSize = 'contain';
            robot.style.backgroundColor = 'transparent';
            robot.style.border = 'none';
            robot.style.padding = '0';
            robot.classList.remove('luz-pulsante-verde', 'modo-bola');
        }
    }, 1500);
}

// Función para restaurar la posición original del robot
function restaurarPosicionRobot() {
    const robotContainer = document.getElementById('robot-asistente-container');
    const chatWindow = document.getElementById('chat-window-final');
    
    if (!robotContainer) return;
    
    // Restaurar posición original (esquina inferior izquierda)
    robotContainer.style.left = '25px';
    robotContainer.style.top = '';
    robotContainer.style.bottom = '25px';
    robotContainer.style.right = '';
    
    // Guardar en localStorage que la posición fue restaurada
    localStorage.removeItem('robot_position_x');
    localStorage.removeItem('robot_position_y');
    
    // Mostrar notificación
    mostrarNotificacion('📍 Posición del asistente restaurada');
    
    // Si el chat estaba oculto por "esconder", mostrarlo de nuevo
    const robotAvatar = document.getElementById('robot-avatar-vuelo');
    if (robotAvatar && robotAvatar.style.display === 'none') {
        robotContainer.style.display = 'flex';
        mostrarNotificacion('🤖 Asistente visible nuevamente');
    }
}

// Función para esconder completamente el robot y el chat
function esconderRobot() {
    const robotContainer = document.getElementById('robot-asistente-container');
    const chatWindow = document.getElementById('chat-window-final');
    
    if (robotContainer) {
        robotContainer.style.display = 'none';
    }
    
    if (chatWindow && chatWindow.style.display === 'flex') {
        chatWindow.style.display = 'none';
    }
    
    // Guardar en localStorage que el robot está oculto
    localStorage.setItem('robot_oculto', 'true');
    // Guardar también que debe saludar al mostrarse
    localStorage.setItem('robot_pendiente_saludo', 'true');
    
    mostrarNotificacion('👻 Asistente ocultado. Para mostrarlo nuevamente,<br>vaya al Menú UTILIDADES "Chatear con el Asistente');
}

// Función para mostrar el robot (cuando se solicita por chat o desde el dropdown)
function mostrarRobot() {
    const robotContainer = document.getElementById('robot-asistente-container');
    const robot = document.getElementById('robot-avatar-vuelo');
    
    if (robotContainer) {
        // Mostrar el robot
        robotContainer.style.display = 'flex';
        localStorage.removeItem('robot_oculto');
        
        // RESTAURAR POSICIÓN INICIAL
        robotContainer.style.left = '25px';
        robotContainer.style.top = '';
        robotContainer.style.bottom = '25px';
        robotContainer.style.right = '';
        
        // Limpiar posición guardada en localStorage
        localStorage.removeItem('robot_position_x');
        localStorage.removeItem('robot_position_y');
        
        mostrarNotificacion('🤖 Asistente visible en posición inicial');
        
        // EJECUTAR ANIMACIÓN DE SALUDAR AL MOSTRARSE
        setTimeout(() => {
            ejecutarAnimacionSaludar();
        }, 300);
    }
}

// Agregar la función al objeto window para que pueda ser llamada desde el chat
window.mostrarRobot = mostrarRobot;
window.restaurarPosicionRobot = restaurarPosicionRobot;
window.esconderRobot = esconderRobot;

// Agregar animación para el menú
const styleMenu = document.createElement('style');
styleMenu.textContent = `
    @keyframes menuFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
`;
document.head.appendChild(styleMenu);

// Inicializar el menú contextual cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', agregarMenuContextualRobot);
} else {
    agregarMenuContextualRobot();
}

// Función específica para ejecutar la animación de saludar
function ejecutarAnimacionSaludar() {
    const robot = document.getElementById('robot-avatar-vuelo');
    const robotContainer = document.getElementById('robot-asistente-container');
    
    if (!robot) return;
    
    // Guardar el estado original
    const teniaClasePulsante = robot.classList.contains('luz-pulsante-verde');
    const teniaModoBola = robot.classList.contains('modo-bola');
    
    // Eliminar cualquier clase de animación previa
    robot.classList.remove('animar-sacudir', 'animar-bailar', 'animar-saltar', 'animar-girar', 'animar-vibrar', 'animar-flotar', 'animar-rebotar', 'animar-brillar', 'animar-saludar', 'animar-estrellas');
    
    // Aplicar animación de saludar
    robot.classList.add('animar-saludar');
    
    // Mostrar mensaje en el globo del robot
    const globoTexto = document.getElementById('robot-globo-texto');
    if (globoTexto) {
        const contenidoOriginal = globoTexto.innerHTML;
        
        globoTexto.innerHTML = `
            <div style="
                position: relative; 
                background: #ffffff; 
                color: #333; 
                padding: 15px; 
                border-radius: 20px; 
                box-shadow: 0 10px 25px rgba(0,0,0,0.2); 
                font-size: 16px; 
                line-height: 1.5; 
                max-width: 250px; 
                border-left: 5px solid #2e7d5e;
                font-family: 'Segoe UI', Roboto, sans-serif;
                text-align: center;
            ">
                <div style="font-size: 48px; margin-bottom: 5px;">👋</div>
                <strong style="font-size: 18px; color: #2e7d5e;">¡HOLA!</strong>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">¡Qué bueno verte de nuevo!</div>
                <div style="
                    position: absolute; 
                    bottom: -10px; 
                    left: 30px; 
                    width: 0; height: 0; 
                    border-left: 12px solid transparent; 
                    border-right: 12px solid transparent; 
                    border-top: 12px solid white;
                "></div>
            </div>
        `;
        globoTexto.style.display = 'block';
        globoTexto.style.opacity = '1';
        globoTexto.style.transform = 'translateY(0) scale(1)';
        
        setTimeout(() => {
            if (globoTexto) {
                globoTexto.innerHTML = contenidoOriginal;
                globoTexto.style.opacity = '0';
                globoTexto.style.transform = 'translateY(15px) scale(0.9)';
                setTimeout(() => {
                    if (globoTexto && globoTexto.style.opacity === '0') {
                        globoTexto.style.display = 'none';
                    }
                }, 400);
            }
        }, 3500);
    }
    
    // Eliminar la animación después de 1.5 segundos y RESTAURAR EFECTO PULSANTE
    setTimeout(() => {
        robot.classList.remove('animar-saludar');
        
        // Verificar si el chat está abierto
        const chatWindow = document.getElementById('chat-window-final');
        const chatEstaAbierto = chatWindow && chatWindow.style.display === 'flex';
        
        // Solo restaurar el efecto pulsante si el chat está CERRADO
        if (!chatEstaAbierto) {
            // Restaurar estilo original del robot
            robot.style.width = '65px';
            robot.style.height = '65px';
            robot.style.borderRadius = '50%';
            robot.style.background = '#1a1a1a url("assets/logobot.png") no-repeat center center';
            robot.style.backgroundSize = 'contain';
            robot.style.border = '2px solid #2e7d5e';
            robot.style.padding = '5px';
            
            // Agregar las clases del efecto pulsante
            if (!robot.classList.contains('luz-pulsante-verde')) {
                robot.classList.add('luz-pulsante-verde');
            }
            if (!robot.classList.contains('modo-bola')) {
                robot.classList.add('modo-bola');
            }
        }
    }, 1500);
}

// Verificar si el robot estaba oculto y debe saludar al cargar
function verificarSaludoPendiente() {
    const robotOculto = localStorage.getItem('robot_oculto');
    const saludoPendiente = localStorage.getItem('robot_pendiente_saludo');
    
    if (robotOculto === 'true') {
        // El robot está oculto, no mostrar saludo
        localStorage.removeItem('robot_pendiente_saludo');
        return;
    }
    
    if (saludoPendiente === 'true') {
        // Limpiar la bandera
        localStorage.removeItem('robot_pendiente_saludo');
        // Ejecutar saludo después de un pequeño retraso
        setTimeout(() => {
            ejecutarAnimacionSaludar();
        }, 500);
    }
}
// Función para actualizar la posición del chat cuando se mueve el robot
function actualizarPosicionChat() {
    const chatWindow = document.getElementById('chat-window-final');
    const robotContainer = document.getElementById('robot-asistente-container');
    
    // Solo actualizar si el chat está abierto
    if (!chatWindow || chatWindow.style.display !== 'flex') return;
    if (!robotContainer) return;
    
    const robotRect = robotContainer.getBoundingClientRect();
    const chatWidth = 380;
    const chatHeight = 550;
    
    // Calcular posición: a la derecha del robot
    let left = robotRect.right + 10;
    let top = robotRect.top;
    
    // Ajustar si se sale por la derecha
    if (left + chatWidth > window.innerWidth) {
        left = robotRect.left - chatWidth - 10;
    }
    
    // Ajustar si se sale por la izquierda
    if (left < 10) {
        left = 10;
    }
    
    // Ajustar si se sale por abajo
    if (top + chatHeight > window.innerHeight) {
        top = window.innerHeight - chatHeight - 10;
    }
    
    // Ajustar si se sale por arriba
    if (top < 10) {
        top = 10;
    }
    
    chatWindow.style.left = left + 'px';
    chatWindow.style.top = top + 'px';
    chatWindow.style.bottom = 'auto';
    chatWindow.style.right = 'auto';
}
// Función para lanzar estrellas alrededor del robot
function lanzarEstrellasAlrededor(robotElement) {
    if (!robotElement) return;
    
    const rect = robotElement.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    
    // Crear canvas para las partículas
    const canvas = document.createElement('canvas');
    canvas.id = 'estrellas-canvas-temporal';
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '9999998';
    document.body.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    // Crear partículas (estrellas)
    const particulas = [];
    const cantidad = 60;
    const colores = ['#FFD700', '#FFE55C', '#FFFACD', '#FFFFE0', '#FFC107', '#FFA500'];
    
    for (let i = 0; i < cantidad; i++) {
        const angulo = Math.random() * Math.PI * 2;
        const distancia = Math.random() * 80 + 20;
        const velocidad = Math.random() * 8 + 4;
        
        particulas.push({
            x: centerX,
            y: centerY,
            destinoX: centerX + Math.cos(angulo) * distancia,
            destinoY: centerY + Math.sin(angulo) * distancia,
            velocidad: velocidad,
            tamaño: Math.random() * 8 + 4,
            color: colores[Math.floor(Math.random() * colores.length)],
            angulo: Math.random() * 360,
            progreso: 0
        });
    }
    
    // También agregar algunas estrellas que caen
    for (let i = 0; i < 30; i++) {
        particulas.push({
            x: Math.random() * canvas.width,
            y: -50,
            destinoX: Math.random() * canvas.width,
            destinoY: canvas.height + 50,
            velocidad: Math.random() * 5 + 3,
            tamaño: Math.random() * 6 + 3,
            color: colores[Math.floor(Math.random() * colores.length)],
            angulo: Math.random() * 360,
            progreso: 0,
            cayendo: true
        });
    }
    
    let animacionFrame;
    let tiempoInicio = Date.now();
    const duracion = 1500;
    
    function dibujarEstrella(x, y, tamaño, color, angulo) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(angulo * Math.PI / 180);
        ctx.fillStyle = color;
        
        // Dibujar estrella de 5 puntas
        ctx.beginPath();
        for (let i = 0; i < 5; i++) {
            const anguloPunta = (i * 72 - 90) * Math.PI / 180;
            const radioExterior = tamaño;
            const radioInterior = tamaño * 0.4;
            
            if (i === 0) {
                ctx.moveTo(Math.cos(anguloPunta) * radioExterior, Math.sin(anguloPunta) * radioExterior);
            } else {
                ctx.lineTo(Math.cos(anguloPunta) * radioExterior, Math.sin(anguloPunta) * radioExterior);
            }
            
            const anguloInterior = anguloPunta + 36 * Math.PI / 180;
            ctx.lineTo(Math.cos(anguloInterior) * radioInterior, Math.sin(anguloInterior) * radioInterior);
        }
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }
    
    function animar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        const tiempoTranscurrido = Date.now() - tiempoInicio;
        const factor = Math.min(1, tiempoTranscurrido / duracion);
        
        for (let p of particulas) {
            if (p.cayendo) {
                p.y += p.velocidad;
                p.x += (Math.random() - 0.5) * 2;
                if (p.y < canvas.height + 100) {
                    dibujarEstrella(p.x, p.y, p.tamaño, p.color, p.angulo);
                }
            } else {
                const progreso = p.progreso + p.velocidad / 100;
                p.progreso = Math.min(1, progreso);
                
                const x = p.x + (p.destinoX - p.x) * p.progreso;
                const y = p.y + (p.destinoY - p.y) * p.progreso;
                
                const escala = 1 - p.progreso * 0.5;
                const tamañoEstrella = p.tamaño * escala;
                
                dibujarEstrella(x, y, tamañoEstrella, p.color, p.angulo + tiempoTranscurrido * 0.005);
            }
        }
        
        // También agregar un brillo alrededor del robot
        const brillo = 0.5 - Math.abs(factor - 0.5);
        ctx.beginPath();
        ctx.arc(centerX, centerY, 30 + brillo * 20, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255, 215, 0, ${0.3 - brillo * 0.2})`;
        ctx.fill();
        
        if (tiempoTranscurrido < duracion) {
            animacionFrame = requestAnimationFrame(animar);
        } else {
            cancelAnimationFrame(animacionFrame);
            document.body.removeChild(canvas);
        }
    }
    
    animar();
    
    // Ajustar canvas al redimensionar
    window.addEventListener('resize', function onResize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }, { once: true });
}
// =========================================================================
// FUNCI�N DE ANIMACI�N PROMINENTE PARA EL ROBOT (EXPORTABLE)
// =========================================================================

/**
 * Ejecuta una animaci�n prominente en el robot asistente
 * Esta animaci�n incluye: escala extrema, rotaci�n, part�culas brillantes, ondas de choque y explosi�n final
 * @param {boolean} restaurarPulsante - Si debe restaurar la clase pulsante despu�s de la animaci�n (default: true)
 */
function ejecutarAnimacionRobotProminente(restaurarPulsante = true) {
    const robot = document.getElementById("robot-avatar-vuelo");
    const robotContainer = document.getElementById("robot-asistente-container");
    
    if (!robot) {
        return;
    }
    
    
    // Guardar estilos originales
    const originalTransition = robot.style.transition;
    const originalBoxShadow = robot.style.boxShadow;
    const originalBorder = robot.style.border;
    const originalZIndex = robotContainer ? robotContainer.style.zIndex : "";
    const teniaClasePulsante = robot.classList.contains("luz-pulsante-verde");
    
    // Elevar el z-index para que la animaci�n est� por encima de todo
    if (robotContainer) {
        robotContainer.style.zIndex = "10000000";
    }
    
    // Remover clase pulsante temporalmente
    robot.classList.remove("luz-pulsante-verde");
    
    // Aplicar transici�n m�s larga y el�stica
    robot.style.transition = "all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1)";
    
    // Secuencia de animaci�n m�s agresiva
    const animSteps = [
        { scale: 1.5, rotate: 0, shadow: "0 0 30px 15px rgba(255, 215, 0, 1)", border: "5px solid #ffd700" },
        { scale: 1.9, rotate: 25, shadow: "0 0 60px 25px rgba(255, 215, 0, 1)", border: "5px solid #ffaa00" },
        { scale: 1.6, rotate: -20, shadow: "0 0 50px 22px rgba(255, 165, 0, 1)", border: "5px solid #ffaa44" },
        { scale: 1.8, rotate: 12, shadow: "0 0 55px 25px rgba(255, 215, 0, 1)", border: "5px solid #ffcc44" },
        { scale: 1.4, rotate: -8, shadow: "0 0 40px 18px rgba(255, 200, 0, 0.9)", border: "4px solid #ffcc66" },
        { scale: 1.2, rotate: 5, shadow: "0 0 30px 12px rgba(46, 125, 94, 0.9)", border: "3px solid #2e7d5e" },
        { scale: 1.0, rotate: 0, shadow: "0 0 15px 5px rgba(46, 125, 94, 0.6)", border: "2px solid #2e7d5e" }
    ];
    
    let stepIndex = 0;
    
    // Crear efecto de part�culas brillantes alrededor del robot
    function crearParticulasBrillantes() {
        const rect = robot.getBoundingClientRect();
        const centroX = rect.left + rect.width / 2;
        const centroY = rect.top + rect.height / 2;
        
        for (let i = 0; i < 40; i++) {
            const particula = document.createElement("div");
            const angulo = Math.random() * Math.PI * 2;
            const distancia = Math.random() * 60 + 20;
            const destinoX = centroX + Math.cos(angulo) * distancia;
            const destinoY = centroY + Math.sin(angulo) * distancia;
            
            particula.style.cssText = `
                position: fixed;
                width: ${Math.random() * 8 + 4}px;
                height: ${Math.random() * 8 + 4}px;
                background: radial-gradient(circle, #ffd700, #ffaa00);
                border-radius: 50%;
                pointer-events: none;
                z-index: 9999999;
                left: ${centroX}px;
                top: ${centroY}px;
                box-shadow: 0 0 8px 2px rgba(255,215,0,0.8);
                transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            `;
            
            document.body.appendChild(particula);
            
            setTimeout(() => {
                particula.style.left = `${destinoX}px`;
                particula.style.top = `${destinoY}px`;
                particula.style.opacity = "0";
                particula.style.transform = "scale(0)";
            }, 10);
            
            setTimeout(() => {
                particula.remove();
            }, 700);
        }
    }
    
    // Crear ondas de choque
    function crearOndaChoque() {
        const rect = robot.getBoundingClientRect();
        const centroX = rect.left + rect.width / 2;
        const centroY = rect.top + rect.height / 2;
        
        for (let i = 0; i < 5; i++) {
            setTimeout(() => {
                const onda = document.createElement("div");
                onda.style.cssText = `
                    position: fixed;
                    pointer-events: none;
                    z-index: 9999998;
                    left: ${centroX - 40}px;
                    top: ${centroY - 40}px;
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    border: 3px solid rgba(255,215,0,0.9);
                    box-shadow: 0 0 20px rgba(255,215,0,0.8);
                    animation: ondaExpansionGrande 0.8s ease-out forwards;
                `;
                document.body.appendChild(onda);
                setTimeout(() => onda.remove(), 800);
            }, i * 80);
        }
    }
    
    function ejecutarPaso() {
        if (stepIndex >= animSteps.length) {
            // Restaurar estado final con efecto de "rebote final"
            robot.style.transform = "scale(1) rotate(0deg)";
            robot.style.boxShadow = originalBoxShadow;
            robot.style.border = originalBorder;
            robot.style.transition = originalTransition;
            
            setTimeout(() => {
                if (robotContainer) {
                    robotContainer.style.zIndex = originalZIndex;
                }
            }, 300);
            
            // Restaurar clase pulsante si se solicita y el chat est� cerrado
            if (restaurarPulsante) {
                const chatWindow = document.getElementById("chat-window-final");
                if (chatWindow && chatWindow.style.display !== "flex") {
                    robot.classList.add("luz-pulsante-verde");
                }
            }
            
            // Crear efecto de chispa final
            const rect = robot.getBoundingClientRect();
            const spark = document.createElement("div");
            spark.style.cssText = `
                position: fixed;
                left: ${rect.left + rect.width/2 - 30}px;
                top: ${rect.top + rect.height/2 - 30}px;
                width: 60px;
                height: 60px;
                pointer-events: none;
                z-index: 9999999;
                background: radial-gradient(circle, rgba(255,215,0,0.9) 0%, rgba(255,215,0,0) 70%);
                border-radius: 50%;
                animation: sparkExplosion 0.5s ease-out forwards;
            `;
            document.body.appendChild(spark);
            setTimeout(() => spark.remove(), 500);
            
            return;
        }
        
        const step = animSteps[stepIndex];
        robot.style.transform = `scale(${step.scale}) rotate(${step.rotate}deg)`;
        robot.style.boxShadow = step.shadow;
        robot.style.border = step.border;
        
        // Crear part�culas en los pasos m�s grandes (escala > 1.5)
        if (step.scale > 1.5) {
            crearParticulasBrillantes();
        }
        
        // Crear onda de choque en el paso m�s grande
        if (stepIndex === 1) {
            crearOndaChoque();
        }
        
        stepIndex++;
        setTimeout(ejecutarPaso, 120);
    }
    
    ejecutarPaso();
}

// Agregar estilos de animaci�n si no existen
if (!document.getElementById("animacion-robot-styles")) {
    const style = document.createElement("style");
    style.id = "animacion-robot-styles";
    style.textContent = `
        @keyframes ondaExpansionGrande {
            0% {
                transform: scale(0.5);
                opacity: 0.9;
                border-width: 4px;
            }
            100% {
                transform: scale(5);
                opacity: 0;
                border-width: 1px;
            }
        }
        @keyframes sparkExplosion {
            0% {
                transform: scale(0);
                opacity: 1;
            }
            50% {
                transform: scale(1.5);
                opacity: 0.8;
            }
            100% {
                transform: scale(2.5);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// ============================================================
// EXPOSICIÓN GLOBAL CON REINTENTO AUTOMÁTICO
// ============================================================

// Variables para almacenar comandos pendientes
let comandosPendientes = [];

// Función para ejecutar comandos pendientes
function ejecutarComandosPendientes() {
    if (comandosPendientes.length > 0) {
        console.log("Ejecutando comandos pendientes:", comandosPendientes.length);
        comandosPendientes.forEach(cmd => {
            if (cmd.tipo === 'abrir') {
                window.abrirChatSisfact();
            } else if (cmd.tipo === 'enviar') {
                window.enviarMensajeSisfact(cmd.texto);
            }
        });
        comandosPendientes = [];
    }
}

// Función para abrir chat (con auto-reintento)
window.abrirChatSisfact = function() {
    const chat = document.getElementById('chat-window-final');
    console.log("abrirChatSisfact llamado, chat existe:", !!chat);
    
    if (chat) {
        if (chat.style.display === 'flex') {
            chat.style.display = 'none';
            console.log("Chat cerrado");
        } else {
            // FORZAR TODOS LOS ESTILOS Y POSICIÓN VISIBLE
            chat.style.display = 'flex';
            chat.style.visibility = 'visible';
            chat.style.opacity = '1';
            chat.style.zIndex = '9999999';
            chat.style.position = 'fixed';
            chat.style.bottom = '100px';
            chat.style.left = '25px';
            chat.style.right = 'auto';
            chat.style.top = 'auto';
            chat.style.width = '380px';
            chat.style.height = '550px';
            console.log("✅ Chat abierto y posicionado");
            
            setTimeout(() => {
                const input = document.getElementById('chat-input-final');
                if (input) input.focus();
            }, 200);
        }
    } else {
        console.log("❌ Chat no encontrado");
    }
};

// Función para enviar mensaje (con auto-reintento)
window.enviarMensajeSisfact = function(texto) {
    console.log("enviarMensajeSisfact llamado con:", texto);
    
    // 1. PRIMERO: Abrir el chat
    const chat = document.getElementById('chat-window-final');
    if (chat && chat.style.display !== 'flex') {
        chat.style.display = 'flex';
        chat.style.visibility = 'visible';
        chat.style.opacity = '1';
        chat.style.zIndex = '9999999';
        console.log("Chat abierto desde enviarMensajeSisfact");
    }
    
    // 2. SEGUNDO: Enviar el mensaje
    function enviar() {
        const input = document.getElementById('chat-input-final');
        if (input) {
            input.value = texto;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            
            const sendBtn = document.getElementById('send-message-final');
            if (sendBtn) {
                sendBtn.click();
            } else {
                input.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter', bubbles: true }));
            }
            console.log("✅ Mensaje enviado:", texto);
            return true;
        }
        return false;
    }
    
    // Si el input no existe, esperar un poco
    if (!document.getElementById('chat-input-final')) {
        setTimeout(enviar, 300);
    } else {
        enviar();
    }
};

// Función para verificar si el chat ya está creado y ejecutar pendientes
function verificarChatYEjecutar() {
    const chat = document.getElementById('chat-window-final');
    if (chat) {
        console.log("✅ Chat encontrado, ejecutando comandos pendientes");
        ejecutarComandosPendientes();
    } else {
        console.log("⏳ Chat no encontrado, reintentando en 500ms...");
        setTimeout(verificarChatYEjecutar, 2000);
    }
}

// Esperar a que el chat se cree (el chat se crea después de 1500ms en crearChatbotSISFACT)
// Empezamos a verificar después de 2 segundos para asegurar que ya se creó
setTimeout(verificarChatYEjecutar, 2500);