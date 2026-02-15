<?php
/**
 * =========================================================================
 * CHATBOT AGENTE DE VENTAS - MUDANZAS ZARAGONJG
 * =========================================================================
 * Bot inteligente con:
 * - Menú principal con FAQ y solicitud de servicios
 * - Cálculo automático de precios de mudanza
 * - Integración con Stripe para pago de reserva (50€)
 * - Notificaciones automáticas vía WhatsApp (Whapi.cloud)
 * - Generación y envío de facturas PDF
 * 
 * CONFIGURACIÓN REQUERIDA EN wp-config.php:
 * define('ZARAGONJG_WHAPI_TOKEN', 'tu_token_de_whapi');
 * define('ZARAGONJG_WHAPI_PHONE', '34625835262');
 * define('ZARAGONJG_STRIPE_PUBLIC_KEY', 'pk_test_...');
 * define('ZARAGONJG_STRIPE_SECRET_KEY', 'sk_test_...');
 * =========================================================================
 */

// =========================================================================
// MODIFICACIÓN 3: SHORTCODES WORDPRESS
// =========================================================================

/**
 * Shortcode: Botón para abrir chatbot
 * Uso: [zaragonjg_bot_button text="Texto" color="#4caf50"]
 */
add_shortcode('zaragonjg_bot_button', 'zaragonjg_bot_button_shortcode');

function zaragonjg_bot_button_shortcode($atts) {
    $atts = shortcode_atts([
        'text' => 'Solicita tu presupuesto',
        'color' => '#4caf50'
    ], $atts);
    
    $text = esc_html($atts['text']);
    $color = esc_attr($atts['color']);
    
    return '<button class="zaragonjg-shortcode-btn" style="background: ' . $color . ';" onclick="document.getElementById(\'zaragonjg-open-chat\').click();">' . $text . '</button>';
}

/**
 * Shortcode: Link para abrir chatbot
 * Uso: [zaragonjg_bot_link text="Texto"]
 */
add_shortcode('zaragonjg_bot_link', 'zaragonjg_bot_link_shortcode');

function zaragonjg_bot_link_shortcode($atts) {
    $atts = shortcode_atts([
        'text' => 'Abrir asistente de mudanzas'
    ], $atts);
    
    $text = esc_html($atts['text']);
    
    return '<a href="#" class="zaragonjg-shortcode-link" onclick="event.preventDefault(); document.getElementById(\'zaragonjg-open-chat\').click();">' . $text . '</a>';
}

/**
 * CSS para shortcodes
 */
add_action('wp_head', 'zaragonjg_shortcode_styles');

function zaragonjg_shortcode_styles() {
    echo <<<'SHORTCODE_CSS'
<style>
	.zaragonjg-shortcode-btn {
		display: inline-block;
		padding: 12px 24px;
		color: white;
		border: none;
		border-radius: 8px;
		font-size: 1rem;
		font-weight: bold;
		cursor: pointer;
		transition: all 0.3s ease;
		box-shadow: 0 4px 12px rgba(0,0,0,0.15);
		font-family: inherit;
	}
	.zaragonjg-shortcode-btn:hover {
		transform: translateY(-2px);
		box-shadow: 0 6px 20px rgba(0,0,0,0.2);
		opacity: 0.9;
	}
	.zaragonjg-shortcode-link {
		color: #00BCD4;
		text-decoration: none;
		font-weight: 600;
		border-bottom: 2px solid transparent;
		transition: all 0.3s ease;
		cursor: pointer;
	}
	.zaragonjg-shortcode-link:hover {
		color: #FF7043;
		border-bottom-color: #FF7043;
	}
</style>
SHORTCODE_CSS;
}

// =========================================================================
// PARTE 1: INYECTAR EL CHATBOT EN EL FOOTER
// =========================================================================
add_action('wp_footer', 'zaragonjg_inyectar_chatbot_ventas');

function zaragonjg_inyectar_chatbot_ventas() {
    
    $ajax_url = admin_url('admin-ajax.php');
    $security_nonce = wp_create_nonce("zaragonjg_bot_nonce");
    
    // Obtener las claves de Stripe
    $stripe_public_key = defined('ZARAGONJG_STRIPE_PUBLIC_KEY') ? ZARAGONJG_STRIPE_PUBLIC_KEY : '';

    // HTML y CSS del bot
    echo <<<'HTML_CSS'
<div id="zaragonjg-service-widget">
    <button id="zaragonjg-open-chat" class="zaragonjg-fab" aria-label="Abrir asistente de servicios">
        <img src="https://mudanzaszaragonjg.com/wp-content/uploads/2025/11/Zeta-Bot.svg" alt="Asistente" />
    </button>

    <div id="serviceModal" class="modal-widget">
        <div class="modal-content-widget">
            <div class="modal-header-widget">
                <h2>
                    <img src="https://mudanzaszaragonjg.com/wp-content/uploads/2025/11/Zeta-Bot.svg" alt="Asistente Virtual" class="assistant-avatar-widget">
                    Asistente de Ventas Zeta
<script>
(function() {
    'use strict';

    // Efecto ripple en el botón
    document.addEventListener("DOMContentLoaded", function() {
        const button = document.getElementById("zaragonjg-open-chat");
        if (button) {
            button.addEventListener("click", function(e) {
                const existingRipples = button.querySelectorAll(".ripple");
                existingRipples.forEach(r => r.remove());
                
                const ripple = document.createElement("span");
                ripple.classList.add("ripple");
                
                const rect = button.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = `${size}px`;
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;
                
                button.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        }
    });

    // =========================================================================
    // LÓGICA PRINCIPAL DEL BOT
    // =========================================================================
    document.addEventListener('DOMContentLoaded', () => {
        const widgetContainer = document.getElementById('zaragonjg-service-widget');
        if (!widgetContainer) return;

        const openModalBtn = widgetContainer.querySelector('#zaragonjg-open-chat');
        const closeModalBtn = widgetContainer.querySelector('#closeModalBtn');
        const serviceModal = widgetContainer.querySelector('#serviceModal');
        const messageContainer = widgetContainer.querySelector('#messageContainer');
        const chatContainer = widgetContainer.querySelector('#chatContainer');

        // Inicializar Stripe
        const stripe = zaragonjg_stripe_public_key ? Stripe(zaragonjg_stripe_public_key) : null;

        let formData = {};
        let calculatedPrice = 0;
        let priceBeforeTaxes = 0;

        // Función para calcular precio final con impuestos españoles
        function calculateFinalPrice(basePrice) {
            const iva = basePrice * 0.21;
            const irpf = basePrice * -0.01;
            return {
                subtotal: basePrice,
                iva: Math.round(iva * 100) / 100,
                irpf: Math.round(irpf * 100) / 100,
                total: Math.round((basePrice + iva + irpf) * 100) / 100
            };
        }
        
        // Contadores de muebles y cajas
        let furnitureCount = {
            camas: 0,
            armarios: 0,
            modulares: 0
        };
        let extraBoxes = 0;
        const includedBoxes = 15;

        // Base de datos de precios
        const pricingData = {
            porte: { min: 100, max: 300 },
            local: {
                estudio: { min: 300, max: 450, volume: '5-10' },
                hab2: { min: 400, max: 650, volume: '10-15' },
                hab3: { min: 600, max: 900, volume: '15-25' },
                hab4: { min: 800, max: 1200, volume: '25-30' },
                chalet: { min: 1000, max: 1500, volume: '30+' }
            },
            provincial: { min: 450, max: 850 },
            valencia: { min: 500, max: 850 },
            madrid: { min: 600, max: 1000 },
            barcelona: { min: 600, max: 1100 },
            nacional: { min: 1200, max: 2800 },
            servicios: {
                embalaje: { min: 150, max: 350 },
                montaje: { min: 150, max: 450 },
                guardamuebles: { min: 100, max: 250 }
            }
        };

        // FAQ
        const faqData = [
            { 
                q: "¿Cómo se calcula una mudanza?", 
                a: "El precio se basa en: volumen de objetos (m³), distancia entre origen y destino, mano de obra necesaria, accesos del inmueble y servicios adicionales contratados." 
            },
            { 
                q: "¿Qué incluye el servicio?", 
                a: "Incluye: transporte, carga y descarga, operarios profesionales, protección básica del mobiliario y seguro básico de transporte." 
            },
            { 
                q: "¿Cuánto cuesta una mudanza local?", 
                a: "En Zaragoza capital: desde 300€ para estudios hasta 1.500€ para chalets. El precio varía según tamaño de vivienda y servicios adicionales." 
            },
            { 
                q: "¿Ofrecen servicio de embalaje?", 
                a: "Sí, ofrecemos embalaje profesional con cajas reforzadas y protección de objetos frágiles. Precio: 50-350€ según volumen." 
            },
            { 
                q: "¿Tienen guardamuebles?", 
                a: "Sí, contamos con almacenaje seguro y vigilado desde 100€/mes. Incluye seguimiento 24/7 y acceso cuando lo necesites." 
            },
            { 
                q: "¿Cómo reservo el servicio?", 
                a: "Puedes solicitar presupuesto aquí mismo. Para mudanzas, realizamos un cargo de 50€ que confirma tu reserva. Contáctanos para más información." 
            }
        ];

        // Funciones auxiliares
        function resetForm() {
            formData = {
                serviceType: '',
                fullName: '',
                phone: '',
                serviceDate: '',
                mudanzaType: '',
                roomCount: '',
                originAddress: '',
                destinationAddress: '',
                destination: '',
                additionalServices: [],
                observations: ''
            };
            calculatedPrice = 0;
            furnitureCount = { camas: 0, armarios: 0, modulares: 0 };
            extraBoxes = 0;
            messageContainer.innerHTML = '';
        }

        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message-widget', sender + '-widget');
            
            if (sender === 'assistant') {
                const avatar = document.createElement('img');
                avatar.src = 'https://mudanzaszaragonjg.com/wp-content/uploads/2025/11/Zeta-Bot.svg';
                avatar.alt = 'Zeta';
                avatar.className = 'assistant-avatar-widget';
                messageDiv.appendChild(avatar);
            }
            
            const messageText = document.createElement('div');
            messageText.innerHTML = text;
            messageDiv.appendChild(messageText);
            messageContainer.appendChild(messageDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function showTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'typing-indicator-widget';
            typingDiv.id = 'typingIndicator';
            for (let i = 0; i < 3; i++) {
                const dot = document.createElement('div');
                dot.className = 'typing-dot-widget';
                typingDiv.appendChild(dot);
            }
            messageContainer.appendChild(typingDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function hideTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) indicator.remove();
        }

        function simulateTyping(callback, delay = 1000) {
            showTypingIndicator();
            setTimeout(() => {
                hideTypingIndicator();
                callback();
            }, delay);
        }

        function addOptions(options, callback, allowMultiple = false) {
            const optionsDiv = document.createElement('div');
            optionsDiv.classList.add('options-container-widget');
            
            if (allowMultiple) {
                const selected = [];
                options.forEach(option => {
                    const btn = document.createElement('button');
                    btn.classList.add('option-btn-widget');
                    btn.textContent = option.name;
                    btn.onclick = () => {
                        if (selected.includes(option.id)) {
                            selected.splice(selected.indexOf(option.id), 1);
                            btn.style.background = '#e8f5e9';
                        } else {
                            selected.push(option.id);
                            btn.style.background = '#c8e6c9';
                        }
                    };
                    optionsDiv.appendChild(btn);
                });
                
                const confirmBtn = document.createElement('button');
                confirmBtn.classList.add('option-btn-widget');
                confirmBtn.textContent = 'Confirmar';
                confirmBtn.style.cssText = 'background: #4caf50; color: white;';
                confirmBtn.onclick = () => {
                    optionsDiv.remove();
                    callback(selected);
                };
                optionsDiv.appendChild(confirmBtn);
            } else {
                options.forEach(option => {
                    const btn = document.createElement('button');
                    btn.classList.add('option-btn-widget');
                    btn.textContent = option.name;
                    btn.onclick = () => {
                        optionsDiv.remove();
                        callback(option.id);
                    };
                    optionsDiv.appendChild(btn);
                });
            }
            
            messageContainer.appendChild(optionsDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function addInput(placeholder, callback, type = 'text') {
            const inputDiv = document.createElement('div');
            inputDiv.classList.add('input-container-widget');
            
            const input = document.createElement('input');
            input.type = type;
            input.placeholder = placeholder;
            
            const btn = document.createElement('button');
            btn.textContent = 'Enviar';
            
            const submit = () => {
                if (input.value.trim() !== '') {
                    inputDiv.remove();
                    callback(input.value.trim());
                }
            };
            
            btn.onclick = submit;
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') submit();
            });
            
            inputDiv.appendChild(input);
            inputDiv.appendChild(btn);
            messageContainer.appendChild(inputDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
            input.focus();
        }

        function addDateSelector(callback) {
            const container = document.createElement('div');
            container.classList.add('date-selector-container');
            
            const dateInput = document.createElement('input');
            dateInput.type = 'date';
            dateInput.min = new Date().toISOString().split('T')[0];
            container.appendChild(dateInput);
            
            const btn = document.createElement('button');
            btn.textContent = 'Confirmar';
            container.appendChild(btn);
            
            messageContainer.appendChild(container);
            chatContainer.scrollTop = chatContainer.scrollHeight;
            dateInput.focus();
            
            btn.onclick = () => {
                if (!dateInput.value) return;
                container.remove();
                const formattedDate = new Date(dateInput.value + 'T00:00:00')
                    .toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
                callback(formattedDate);
            };
        }

        // =========================================================================
        // FLUJOS DEL BOT - MENÚ PRINCIPAL
        // =========================================================================

        function showMainMenu() {
            simulateTyping(() => {
                addMessage("¿Qué necesitas?", 'assistant');
                setTimeout(() => {
                    addOptions([
                        { id: 'porte', name: '1️⃣ Porte pequeño' },
                        { id: 'local', name: '2️⃣ Mudanza local Zaragoza' },
                        { id: 'provincial', name: '3️⃣ Mudanza provincial/nacional' },
                        { id: 'guardamuebles', name: '4️⃣ Guardamuebles' },
                        { id: 'faq', name: '❓ Preguntas Frecuentes' }
                    ], (choice) => {
                        if (choice === 'faq') {
                            showFAQ();
                        } else if (choice === 'porte') {
                            addMessage('1️⃣ Porte pequeño', 'user');
                            setTimeout(() => startPorteFlow(), 800);
                        } else if (choice === 'local') {
                            addMessage('2️⃣ Mudanza local Zaragoza', 'user');
                            setTimeout(() => startLocalFlow(), 800);
                        } else if (choice === 'provincial') {
                            addMessage('3️⃣ Mudanza provincial/nacional', 'user');
                            setTimeout(() => startProvincialNacionalFlow(), 800);
                        } else if (choice === 'guardamuebles') {
                            addMessage('4️⃣ Guardamuebles', 'user');
                            setTimeout(() => startGuardamueblesFlow(), 800);
                        }
                    });
                }, 500);
            });
        }

        // FAQ
        function showFAQ() {
            addMessage('❓ Preguntas Frecuentes', 'user');
            simulateTyping(() => {
                addMessage("Estas son las preguntas más frecuentes:", 'assistant');
                setTimeout(() => {
                    const faqOptions = faqData.map((item, idx) => ({ id: idx, name: item.q }));
                    faqOptions.push({ id: 'menu', name: '🏠 Volver al Menú' });
                    
                    addOptions(faqOptions, (idx) => {
                        if (idx === 'menu') {
                            addMessage('🏠 Volver al Menú', 'user');
                            setTimeout(() => showMainMenu(), 800);
                        } else {
                            addMessage(faqData[idx].q, 'user');
                            simulateTyping(() => {
                                addMessage(faqData[idx].a, 'assistant');
                                setTimeout(() => {
                                    simulateTyping(() => {
                                        addMessage("¿Necesitas algo más?", 'assistant');
                                        setTimeout(() => {
                                            addOptions([
                                                { id: 'menu', name: '🏠 Menú Principal' },
                                                { id: 'mudanza', name: '🚚 Solicitar Mudanza' }
                                            ], (choice) => {
                                                if (choice === 'menu') {
                                                    addMessage('🏠 Volver al Menú', 'user');
                                                    setTimeout(() => showMainMenu(), 800);
                                                } else {
                                                    addMessage('🚚 Solicitar Mudanza', 'user');
                                                    setTimeout(() => startLocalFlow(), 800);
                                                }
                                            });
                                        }, 500);
                                    }, 1000);
                                }, 1500);
                            }, 1000);
                        }
                    });
                }, 500);
            });
        }

        // FLUJO 1: PORTE PEQUEÑO
        function startPorteFlow() {
            formData.serviceType = 'mudanza';
            formData.mudanzaType = 'porte';
            
            simulateTyping(() => {
                addMessage("Para pequeños transportes trabajamos desde:", 'assistant');
                setTimeout(() => {
                    const summaryDiv = document.createElement('div');
                    summaryDiv.className = 'price-summary-widget';
                    summaryDiv.innerHTML = `
                        <div><strong>Hasta 5 m³ → 100 - 300 €</strong></div>
                        <div style="margin-top: 8px; font-size: 0.8rem;">
                            ✓ Transporte<br>
                            ✓ Carga y descarga<br>
                            ✓ Seguro básico
                        </div>
                    `;
                    messageContainer.appendChild(summaryDiv);
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    
                    setTimeout(() => {
                        addMessage("👉 ¿Es solo un porte pequeño o es una mudanza completa?", 'assistant');
                        setTimeout(() => {
                            addOptions([
                                { id: 'porte', name: 'Solo porte pequeño' },
                                { id: 'mudanza', name: 'Es una mudanza completa' }
                            ], (choice) => {
                                if (choice === 'porte') {
                                    addMessage('Solo porte pequeño', 'user');
                                    calculatedPrice = 200;
                                    setTimeout(() => askFullName(), 800);
                                } else {
                                    addMessage('Es una mudanza completa', 'user');
                                    setTimeout(() => {
                                        addMessage("Perfecto, déjame calcular el precio exacto para tu mudanza completa.", 'assistant');
                                        setTimeout(() => startLocalFlow(), 1000);
                                    }, 800);
                                }
                            });
                        }, 500);
                    }, 1500);
                }, 1000);
            });
        }

        // FLUJO 2: MUDANZA LOCAL ZARAGOZA
        function startLocalFlow() {
            formData.serviceType = 'mudanza';
            formData.mudanzaType = 'local';
            
            simulateTyping(() => {
                addMessage("Perfecto 👍", 'assistant');
                setTimeout(() => {
                    addMessage("¿Qué tipo de vivienda es?", 'assistant');
                    setTimeout(() => {
                        addOptions([
                            { id: 'estudio', name: 'Estudio (300-450€)' },
                            { id: 'hab2', name: '2 habitaciones (400-650€)' },
                            { id: 'hab3', name: '3 habitaciones (600-900€)' },
                            { id: 'hab4', name: '4 habitaciones (800-1.200€)' },
                            { id: 'chalet', name: 'Chalet +30m³ (1.000-1.500€)' },
                            { id: 'menu', name: '🏠 Volver al Menú' }
                        ], (size) => {
                            if (size === 'menu') {
                                addMessage('🏠 Volver al Menú', 'user');
                                setTimeout(() => showMainMenu(), 800);
                            } else {
                                formData.roomCount = size;
                                const sizeData = {
                                    estudio: { name: 'Estudio', min: 300, max: 450 },
                                    hab2: { name: '2 habitaciones', min: 400, max: 650 },
                                    hab3: { name: '3 habitaciones', min: 600, max: 900 },
                                    hab4: { name: '4 habitaciones', min: 800, max: 1200 },
                                    chalet: { name: 'Chalet', min: 1000, max: 1500 }
                                };
                                
                                const selected = sizeData[size];
                                addMessage(selected.name, 'user');
                                calculatedPrice = Math.round((selected.min + selected.max) / 2);
                                
                                setTimeout(() => {
                                    addMessage("Ese rango depende principalmente del volumen real y accesos.", 'assistant');
                                    setTimeout(() => {
                                        addMessage(`La mayoría de clientes con esa vivienda suelen estar en la <strong>parte media del rango</strong> (aprox. ${calculatedPrice}€).`, 'assistant');
                                        setTimeout(() => askAdditionalServicesWithPsychology(), 1500);
                                    }, 1200);
                                }, 800);
                            }
                        });
                    }, 500);
                }, 800);
            });
        }

        // FLUJO 3: MUDANZA PROVINCIAL/NACIONAL
        function startProvincialNacionalFlow() {
            formData.serviceType = 'mudanza';
            formData.mudanzaType = 'nacional';
            
            simulateTyping(() => {
                addMessage("Selecciona el destino:", 'assistant');
                setTimeout(() => {
                    addOptions([
                        { id: 'provincia', name: 'Provincia Zaragoza (450-850€)' },
                        { id: 'valencia', name: 'Valencia (500-850€)' },
                        { id: 'madrid', name: 'Madrid (600-1.000€)' },
                        { id: 'barcelona', name: 'Barcelona (600-1.100€)' },
                        { id: 'larga', name: 'Nacional larga +25m³ (1.200-2.800€)' },
                        { id: 'menu', name: '🏠 Volver al Menú' }
                    ], (dest) => {
                        if (dest === 'menu') {
                            addMessage('🏠 Volver al Menú', 'user');
                            setTimeout(() => showMainMenu(), 800);
                        } else {
                            const destData = {
                                provincia: { name: 'Provincia Zaragoza', min: 450, max: 850 },
                                valencia: { name: 'Valencia', min: 500, max: 850 },
                                madrid: { name: 'Madrid', min: 600, max: 1000 },
                                barcelona: { name: 'Barcelona', min: 600, max: 1100 },
                                larga: { name: 'Nacional larga', min: 1200, max: 2800 }
                            };
                            
                            const selected = destData[dest];
                            formData.destination = selected.name;
                            addMessage(selected.name, 'user');
                            calculatedPrice = Math.round((selected.min + selected.max) / 2);
                            
                            setTimeout(() => {
                                addMessage("En trayectos largos <strong>recomendamos montaje profesional</strong> (200€ aprox.).", 'assistant');
                                setTimeout(() => {
                                    addMessage("Reduce incidencias y evita reclamaciones posteriores.", 'assistant');
                                    setTimeout(() => askMontajeForLongDistance(), 1500);
                                }, 1200);
                            }, 800);
                        }
                    });
                }, 500);
            });
        }

        function askMontajeForLongDistance() {
            simulateTyping(() => {
                addMessage("¿Incluimos montaje y desmontaje profesional?", 'assistant');
                setTimeout(() => {
                    addOptions([
                        { id: 'si', name: 'Sí, incluir montaje (+200€)' },
                        { id: 'no', name: 'No, lo haré yo mismo' }
                    ], (choice) => {
                        if (choice === 'si') {
                            addMessage('Sí, incluir montaje', 'user');
                            calculatedPrice += 200;
                            formData.additionalServices.push('montaje');
                        } else {
                            addMessage('No, lo haré yo mismo', 'user');
                        }
                        setTimeout(() => askAdditionalServicesWithPsychology(), 800);
                    });
                }, 500);
            });
        }

        // FLUJO 4: GUARDAMUEBLES
        function startGuardamueblesFlow() {
            formData.serviceType = 'guardamuebles';

            simulateTyping(() => {
                addMessage("Perfecto, te ayudaré con el guardamuebles.", 'assistant');
                setTimeout(() => {
                    addMessage("¿Qué artículos necesitas almacenar?", 'assistant');
                    setTimeout(() => {
                        addInput('Ejemplo: Muebles de 2 habitaciones, cajas...', (items) => {
                            formData.storageItems = items;
                            addMessage(items, 'user');
                            setTimeout(() => askStorageDuration(), 800);
                        });
                    }, 500);
                }, 1000);
            });
        }

        function askStorageDuration() {
            simulateTyping(() => {
                addMessage("¿Por cuánto tiempo aproximadamente?", 'assistant');
                setTimeout(() => {
                    addInput('Ejemplo: 3 meses, 6 meses, 1 año...', (duration) => {
                        formData.storageDuration = duration;
                        addMessage(duration, 'user');
                        setTimeout(() => askStoragePickupRequired(), 800);
                    });
                }, 500);
            });
        }

        function askStoragePickupRequired() {
            simulateTyping(() => {
                addMessage("¿Necesitas que recojamos tus cosas?", 'assistant');
                setTimeout(() => {
                    addOptions([
                        { id: 'si', name: 'Sí, necesito recogida' },
                        { id: 'no', name: 'No, yo las llevo' }
                    ], (pickup) => {
                        formData.pickupRequired = pickup;
                        addMessage(pickup === 'si' ? 'Sí, necesito recogida' : 'No, yo las llevo', 'user');

                        if (pickup === 'si') {
                            setTimeout(() => askStoragePickupAddress(), 800);
                        } else {
                            formData.pickupAddressStorage = 'El cliente lo lleva';
                            setTimeout(() => calculateGuardamueblesPrice(), 800);
                        }
                    });
                }, 500);
            });
        }

        function askStoragePickupAddress() {
            simulateTyping(() => {
                addMessage("¿Desde qué dirección recogemos?", 'assistant');
                setTimeout(() => {
                    addInput('Dirección completa de recogida', (address) => {
                        formData.pickupAddressStorage = address;
                        addMessage(address, 'user');
                        setTimeout(() => calculateGuardamueblesPrice(), 800);
                    });
                }, 500);
            });
        }

        function calculateGuardamueblesPrice() {
            simulateTyping(() => {
                const monthlyRate = 150;
                addMessage("El servicio de guardamuebles tiene un costo de:", 'assistant');

                setTimeout(() => {
                    const priceInfo = document.createElement('div');
                    priceInfo.style.cssText = 'background: #e8f5e9; padding: 15px; border-radius: 10px; margin: 10px 0;';
                    priceInfo.innerHTML = `
                        <div style="text-align: center;">
                            <div style="font-size: 1.4rem; font-weight: bold; color: #2e7d32;">150€/mes</div>
                            <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">Almacenamiento seguro y vigilado</div>
                            <div style="font-size: 0.8rem; color: #999; margin-top: 8px;">
                                ✓ Vigilancia 24/7<br>
                                ✓ Acceso cuando necesites<br>
                                ✓ Seguro incluido
                            </div>
                        </div>
                    `;
                    messageContainer.appendChild(priceInfo);
                    chatContainer.scrollTop = chatContainer.scrollHeight;

                    priceBeforeTaxes = monthlyRate;
                    const pricing = calculateFinalPrice(monthlyRate);
                    calculatedPrice = pricing.total;

                    setTimeout(() => {
                        const taxBreakdown = document.createElement('div');
                        taxBreakdown.style.cssText = 'background: #f8f9fa; padding: 12px; border-radius: 8px; margin: 10px 0; font-size: 0.85rem;';
                        taxBreakdown.innerHTML = `
                            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                                <span>Subtotal:</span>
                                <span style="font-weight: bold;">${pricing.subtotal.toFixed(2)}€</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 5px 0; color: #4caf50;">
                                <span>IVA (21%):</span>
                                <span style="font-weight: bold;">+${pricing.iva.toFixed(2)}€</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 5px 0; color: #f44336;">
                                <span>IRPF (-1%):</span>
                                <span style="font-weight: bold;">${pricing.irpf.toFixed(2)}€</span>
                            </div>
                            <div style="border-top: 2px solid #ddd; margin-top: 8px; padding-top: 8px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="font-weight: bold;">TOTAL/MES:</span>
                                    <span style="font-weight: bold; color: #2e7d32; font-size: 1.1rem;">${pricing.total.toFixed(2)}€</span>
                                </div>
                            </div>
                        `;
                        messageContainer.appendChild(taxBreakdown);
                        chatContainer.scrollTop = chatContainer.scrollHeight;

                        setTimeout(() => showGuardamueblesPaymentOptions(), 1500);
                    }, 1000);
                }, 800);
            });
        }

        function showGuardamueblesPaymentOptions() {
            simulateTyping(() => {
                addMessage("Para <strong>reservar tu espacio</strong> en el guardamuebles:", 'assistant');

                setTimeout(() => {
                    const benefitsDiv = document.createElement('div');
                    benefitsDiv.style.cssText = 'background: #e8f5e9; padding: 15px; border-radius: 10px; margin: 10px 0; font-size: 0.9rem;';
                    benefitsDiv.innerHTML = `
                        <div style="font-weight: bold; margin-bottom: 10px; color: #2e7d32;">💳 Anticipo de 50€</div>
                        <div style="line-height: 1.6;">
                            ✓ Se descuenta del primer mes<br>
                            ✓ Garantiza tu espacio<br>
                            ✓ Te asegura el precio acordado<br>
                            <div style="margin-top: 8px; font-style: italic; color: #666;">Pago 100% seguro con tarjeta</div>
                        </div>
                    `;
                    messageContainer.appendChild(benefitsDiv);
                    chatContainer.scrollTop = chatContainer.scrollHeight;

                    setTimeout(() => {
                        addOptions([
                            { id: 'pagar', name: '💳 Pagar 50€ ahora' },
                            { id: 'contacto', name: '📞 Prefiero que me contacten' }
                        ], (option) => {
                            if (option === 'pagar') {
                                addMessage('💳 Pagar 50€ ahora', 'user');
                                setTimeout(() => askGuardamueblesFullName(), 800);
                            } else {
                                addMessage('📞 Prefiero que me contacten', 'user');
                                setTimeout(() => {
                                    addMessage("Perfecto, te contactaremos pronto para coordinar.", 'assistant');
                                    setTimeout(() => askGuardamueblesContactName(), 1000);
                                }, 800);
                            }
                        });
                    }, 800);
                }, 1000);
            });
        }

        function askGuardamueblesFullName() {
            simulateTyping(() => {
                addMessage("¿Cuál es tu nombre completo?", 'assistant');
                setTimeout(() => {
                    addInput('Tu nombre y apellidos', (name) => {
                        formData.fullName = name;
                        addMessage(name, 'user');
                        setTimeout(() => askGuardamueblesPhone(), 800);
                    });
                }, 500);
            });
        }

        function askGuardamueblesPhone() {
            simulateTyping(() => {
                addMessage(`Perfecto, ${formData.fullName.split(' ')[0]}. ¿Me das tu teléfono?`, 'assistant');
                setTimeout(() => {
                    addInput('Ej: 612 345 678', (phone) => {
                        const phoneRegex = /^(?:6|7)\d{8}$/;
                        const cleanPhone = phone.replace(/\s/g, '');

                        if (phoneRegex.test(cleanPhone)) {
                            formData.phone = cleanPhone.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
                            addMessage(formData.phone, 'user');
                            setTimeout(() => askGuardamueblesObservations(), 800);
                        } else {
                            addMessage("Lo siento, ese número no es válido. Usa un móvil español de 9 dígitos.", 'assistant');
                            setTimeout(() => askGuardamueblesPhone(), 1000);
                        }
                    });
                }, 500);
            });
        }

        function askGuardamueblesObservations() {
            simulateTyping(() => {
                addMessage("¿Alguna observación adicional?", 'assistant');
                setTimeout(() => {
                    const container = document.createElement('div');
                    const inputDiv = document.createElement('div');
                    inputDiv.classList.add('input-container-widget');

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Observaciones (opcional)';

                    const sendBtn = document.createElement('button');
                    sendBtn.textContent = 'Enviar';

                    inputDiv.appendChild(input);
                    inputDiv.appendChild(sendBtn);

                    const optionsDiv = document.createElement('div');
                    optionsDiv.classList.add('options-container-widget');
                    optionsDiv.style.marginTop = '10px';

                    const noBtn = document.createElement('button');
                    noBtn.classList.add('option-btn-widget');
                    noBtn.textContent = 'Sin observaciones';
                    noBtn.style.width = '100%';
                    optionsDiv.appendChild(noBtn);

                    container.appendChild(inputDiv);
                    container.appendChild(optionsDiv);
                    messageContainer.appendChild(container);
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    input.focus();

                    const handleSubmit = (obs) => {
                        formData.observations = obs;
                        formData.serviceDate = 'A coordinar';
                        addMessage(obs, 'user');
                        container.remove();
                        setTimeout(() => showPaymentOption(), 1000);
                    };

                    sendBtn.onclick = () => {
                        if (input.value.trim() !== '') handleSubmit(input.value.trim());
                    };

                    input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter' && input.value.trim() !== '') sendBtn.click();
                    });

                    noBtn.onclick = () => handleSubmit('Sin observaciones');
                }, 500);
            });
        }

        function askGuardamueblesContactName() {
            simulateTyping(() => {
                addMessage("Para que podamos contactarte, ¿cuál es tu nombre?", 'assistant');
                setTimeout(() => {
                    addInput('Tu nombre completo', (name) => {
                        formData.fullName = name;
                        addMessage(name, 'user');
                        setTimeout(() => askGuardamueblesContactPhone(), 800);
                    });
                }, 500);
            });
        }

        function askGuardamueblesContactPhone() {
            simulateTyping(() => {
                addMessage("¿Y tu número de teléfono?", 'assistant');
                setTimeout(() => {
                    addInput('Ej: 612 345 678', (phone) => {
                        const phoneRegex = /^(?:6|7)\d{8}$/;
                        const cleanPhone = phone.replace(/\s/g, '');

                        if (phoneRegex.test(cleanPhone)) {
                            formData.phone = cleanPhone.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
                            addMessage(formData.phone, 'user');
                            setTimeout(() => sendGuardamueblesToWhatsApp(), 800);
                        } else {
                            addMessage("Lo siento, ese número no es válido. Usa un móvil español.", 'assistant');
                            setTimeout(() => askGuardamueblesContactPhone(), 1000);
                        }
                    });
                }, 500);
            });
        }

        function sendGuardamueblesToWhatsApp() {
            addMessage("Enviando tu solicitud...", 'assistant');
            showTypingIndicator();

            const pricing = calculateFinalPrice(150);

            const message = `*📦 SOLICITUD GUARDAMUEBLES*\n\n` +
                           `*Cliente:* ${formData.fullName || 'No especificado'}\n` +
                           `*Teléfono:* ${formData.phone || 'No especificado'}\n\n` +
                           `*📦 DETALLES:*\n` +
                           `Artículos: ${formData.storageItems || 'No especificado'}\n` +
                           `Duración: ${formData.storageDuration || 'No especificado'}\n` +
                           `Recogida: ${formData.pickupRequired === 'si' ? 'Sí' : 'No'}\n` +
                           (formData.pickupRequired === 'si' ? `Dirección recogida: ${formData.pickupAddressStorage || 'Pendiente'}\n` : '') +
                           `\n*💰 PRECIO MENSUAL:*\n` +
                           `Subtotal: ${pricing.subtotal.toFixed(2)}€\n` +
                           `IVA (21%): +${pricing.iva.toFixed(2)}€\n` +
                           `IRPF (-1%): ${pricing.irpf.toFixed(2)}€\n` +
                           `TOTAL: *${pricing.total.toFixed(2)}€/mes*\n\n` +
                           `💳 *Estado pago:* Pendiente\n\n` +
                           `⚠️ *ACCIÓN:* Contactar cliente para confirmar.`;

            const formDataWhatsApp = new FormData();
            formDataWhatsApp.append('action', 'zaragonjg_enviar_whatsapp');
            formDataWhatsApp.append('security', zaragonjg_security_nonce);
            formDataWhatsApp.append('form_data', message);

            fetch(zaragonjg_ajax_url, {
                method: 'POST',
                body: formDataWhatsApp
            })
            .then(response => response.json())
            .then(data => {
                hideTypingIndicator();
                if (data.success) {
                    addMessage("✅ ¡Solicitud enviada exitosamente!", 'assistant');
                    addMessage("Te contactaremos pronto para confirmar los detalles del guardamuebles.", 'assistant');
                } else {
                    addMessage("❌ Error al enviar. Por favor, llámanos al 625 83 52 62", 'assistant');
                }
            })
            .catch(error => {
                hideTypingIndicator();
                addMessage("❌ Error de conexión. Llámanos al 625 83 52 62", 'assistant');
                console.error('Error:', error);
            });
        }

        // Servicios adicionales con psicología de ventas
        function askAdditionalServicesWithPsychology() {
            simulateTyping(() => {
                addMessage("¿Deseas <strong>desmontaje / montaje de muebles</strong>?", 'assistant');
                setTimeout(() => {
                    showFurnitureSelector();
                }, 800);
            });
        }

        function showFurnitureSelector() {
            const selectorDiv = document.createElement('div');
            selectorDiv.style.cssText = 'background: #f8f9fa; padding: 12px; border-radius: 10px; margin: 10px 0;';
            
            const title = document.createElement('div');
            title.style.cssText = 'font-weight: bold; margin-bottom: 10px; font-size: 0.9rem;';
            title.textContent = 'Selecciona los muebles:';
            selectorDiv.appendChild(title);
            
            const furniture = [
                { id: 'camas', name: 'Cama', price: 50 },
                { id: 'armarios', name: 'Armario', price: 100 },
                { id: 'modulares', name: 'Modular/Estantería', price: 125 }
            ];
            
            furniture.forEach(item => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 8px; background: white; border-radius: 8px; margin-bottom: 8px;';
                
                const info = document.createElement('div');
                info.style.cssText = 'flex: 1;';
                info.innerHTML = `<strong>${item.name}</strong><br><span style="color: #666; font-size: 0.85rem;">${item.price}€/unidad</span>`;
                
                const controls = document.createElement('div');
                controls.style.cssText = 'display: flex; align-items: center; gap: 10px;';
                
                const minusBtn = document.createElement('button');
                minusBtn.textContent = '-';
                minusBtn.style.cssText = 'width: 30px; height: 30px; border-radius: 50%; background: #f44336; color: white; border: none; cursor: pointer; font-weight: bold;';
                minusBtn.onclick = () => {
                    if (furnitureCount[item.id] > 0) {
                        furnitureCount[item.id]--;
                        updateFurnitureDisplay(item.id, counter, item.price);
                    }
                };
                
                const counter = document.createElement('span');
                counter.id = `counter-${item.id}`;
                counter.style.cssText = 'min-width: 30px; text-align: center; font-weight: bold; font-size: 1.1rem;';
                counter.textContent = '0';
                
                const plusBtn = document.createElement('button');
                plusBtn.textContent = '+';
                plusBtn.style.cssText = 'width: 30px; height: 30px; border-radius: 50%; background: #4caf50; color: white; border: none; cursor: pointer; font-weight: bold;';
                plusBtn.onclick = () => {
                    furnitureCount[item.id]++;
                    updateFurnitureDisplay(item.id, counter, item.price);
                };
                
                controls.appendChild(minusBtn);
                controls.appendChild(counter);
                controls.appendChild(plusBtn);
                
                row.appendChild(info);
                row.appendChild(controls);
                selectorDiv.appendChild(row);
            });
            
            const subtotalDiv = document.createElement('div');
            subtotalDiv.id = 'furniture-subtotal';
            subtotalDiv.style.cssText = 'margin-top: 10px; padding: 10px; background: #e8f5e9; border-radius: 8px; font-weight: bold; text-align: right;';
            subtotalDiv.textContent = 'Subtotal muebles: 0€';
            selectorDiv.appendChild(subtotalDiv);
            
            const continueBtn = document.createElement('button');
            continueBtn.className = 'option-btn-widget';
            continueBtn.textContent = 'Continuar';
            continueBtn.style.cssText = 'background: #4caf50; color: white; width: 100%; margin-top: 10px; padding: 10px;';
            continueBtn.onclick = () => {
                const totalFurniture = (furnitureCount.camas * 50) + (furnitureCount.armarios * 100) + (furnitureCount.modulares * 125);
                calculatedPrice += totalFurniture;
                
                const summary = [];
                if (furnitureCount.camas > 0) summary.push(`${furnitureCount.camas} cama(s)`);
                if (furnitureCount.armarios > 0) summary.push(`${furnitureCount.armarios} armario(s)`);
                if (furnitureCount.modulares > 0) summary.push(`${furnitureCount.modulares} modular(es)`);
                
                addMessage(summary.length > 0 ? summary.join(', ') : 'Sin muebles', 'user');
                selectorDiv.remove();
                
                if (totalFurniture > 0) {
                    formData.additionalServices.push('montaje');
                }
                
                setTimeout(() => askBoxesService(), 800);
            };
            selectorDiv.appendChild(continueBtn);
            
            messageContainer.appendChild(selectorDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        
        function updateFurnitureDisplay(id, counterElement, price) {
            counterElement.textContent = furnitureCount[id];
            const total = (furnitureCount.camas * 50) + (furnitureCount.armarios * 100) + (furnitureCount.modulares * 125);
            const subtotalDiv = document.getElementById('furniture-subtotal');
            if (subtotalDiv) {
                subtotalDiv.textContent = `Subtotal muebles: ${total}€`;
            }
        }
        
        function askBoxesService() {
            simulateTyping(() => {
                addMessage("¿Quieres <strong>embalaje profesional</strong>?", 'assistant');
                setTimeout(() => {
                    addMessage("✓ Incluye hasta 15 cajas<br>✓ Cada caja extra: 5€", 'assistant');
                    setTimeout(() => {
                        showBoxesSelector();
                    }, 500);
                }, 800);
            });
        }
        
        function showBoxesSelector() {
            const selectorDiv = document.createElement('div');
            selectorDiv.style.cssText = 'background: #f8f9fa; padding: 12px; border-radius: 10px; margin: 10px 0;';
            
            const info = document.createElement('div');
            info.style.cssText = 'margin-bottom: 10px; font-size: 0.9rem;';
            info.innerHTML = `<strong>Embalaje profesional</strong><br>15 cajas incluidas en el servicio<br>Cajas adicionales: 5€/unidad`;
            selectorDiv.appendChild(info);
            
            const row = document.createElement('div');
            row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; background: white; border-radius: 8px; margin: 10px 0;';
            
            const label = document.createElement('div');
            label.style.cssText = 'font-weight: bold;';
            label.textContent = 'Cajas extra:';
            
            const controls = document.createElement('div');
            controls.style.cssText = 'display: flex; align-items: center; gap: 10px;';
            
            const minusBtn = document.createElement('button');
            minusBtn.textContent = '-';
            minusBtn.style.cssText = 'width: 30px; height: 30px; border-radius: 50%; background: #f44336; color: white; border: none; cursor: pointer; font-weight: bold;';
            minusBtn.onclick = () => {
                if (extraBoxes > 0) {
                    extraBoxes--;
                    updateBoxesDisplay(counter);
                }
            };
            
            const counter = document.createElement('span');
            counter.id = 'boxes-counter';
            counter.style.cssText = 'min-width: 30px; text-align: center; font-weight: bold; font-size: 1.1rem;';
            counter.textContent = '0';
            
            const plusBtn = document.createElement('button');
            plusBtn.textContent = '+';
            plusBtn.style.cssText = 'width: 30px; height: 30px; border-radius: 50%; background: #4caf50; color: white; border: none; cursor: pointer; font-weight: bold;';
            plusBtn.onclick = () => {
                extraBoxes++;
                updateBoxesDisplay(counter);
            };
            
            controls.appendChild(minusBtn);
            controls.appendChild(counter);
            controls.appendChild(plusBtn);
            
            row.appendChild(label);
            row.appendChild(controls);
            selectorDiv.appendChild(row);
            
            const subtotalDiv = document.createElement('div');
            subtotalDiv.id = 'boxes-subtotal';
            subtotalDiv.style.cssText = 'margin-top: 10px; padding: 10px; background: #e8f5e9; border-radius: 8px; font-weight: bold; text-align: right;';
            subtotalDiv.textContent = 'Cajas extra: 0€';
            selectorDiv.appendChild(subtotalDiv);
            
            const continueBtn = document.createElement('button');
            continueBtn.className = 'option-btn-widget';
            continueBtn.textContent = 'Continuar';
            continueBtn.style.cssText = 'background: #4caf50; color: white; width: 100%; margin-top: 10px; padding: 10px;';
            continueBtn.onclick = () => {
                const totalBoxes = extraBoxes * 5;
                calculatedPrice += totalBoxes;
                
                addMessage(extraBoxes > 0 ? `${extraBoxes} cajas extra` : 'Sin cajas extra', 'user');
                selectorDiv.remove();
                
                if (extraBoxes > 0) {
                    formData.additionalServices.push('embalaje');
                }
                
                setTimeout(() => showPriceSummaryWithPsychology(), 1000);
            };
            selectorDiv.appendChild(continueBtn);
            
            messageContainer.appendChild(selectorDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        
        function updateBoxesDisplay(counterElement) {
            counterElement.textContent = extraBoxes;
            const total = extraBoxes * 5;
            const subtotalDiv = document.getElementById('boxes-subtotal');
            if (subtotalDiv) {
                subtotalDiv.textContent = `Cajas extra: ${total}€`;
            }
        }

        function showPriceSummaryWithPsychology() {
            simulateTyping(() => {
                const summaryDiv = document.createElement('div');
                summaryDiv.className = 'price-summary-widget';
                summaryDiv.style.cssText = 'background: #fff; border: 2px solid #4caf50; border-radius: 12px; padding: 15px; margin: 10px 0;';
                
                let summaryHTML = `<div style="text-align: center; margin-bottom: 15px;"><strong style="font-size: 1.1rem;">📋 RESUMEN DE TU MUDANZA</strong></div>`;
                summaryHTML += `<div style="font-size: 0.85rem;">`;
                
                let basePrice = calculatedPrice;
                if (furnitureCount.camas > 0 || furnitureCount.armarios > 0 || furnitureCount.modulares > 0) {
                    basePrice -= ((furnitureCount.camas * 50) + (furnitureCount.armarios * 100) + (furnitureCount.modulares * 125));
                }
                if (extraBoxes > 0) {
                    basePrice -= (extraBoxes * 5);
                }
                
                summaryHTML += `<div style="padding: 8px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px;">`;
                summaryHTML += `<strong>Servicio de mudanza:</strong><br>`;
                summaryHTML += `Tipo: ${formData.mudanzaType || 'Local'}<br>`;
                if (formData.roomCount) summaryHTML += `Vivienda: ${formData.roomCount}<br>`;
                if (formData.destination) summaryHTML += `Destino: ${formData.destination}<br>`;
                summaryHTML += `<div style="text-align: right; font-weight: bold; color: #2e7d32;">${basePrice}€</div>`;
                summaryHTML += `</div>`;
                
                if (furnitureCount.camas > 0 || furnitureCount.armarios > 0 || furnitureCount.modulares > 0) {
                    const totalMuebles = (furnitureCount.camas * 50) + (furnitureCount.armarios * 100) + (furnitureCount.modulares * 125);
                    summaryHTML += `<div style="padding: 8px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px;">`;
                    summaryHTML += `<strong>Montaje/Desmontaje:</strong><br>`;
                    if (furnitureCount.camas > 0) summaryHTML += `${furnitureCount.camas} cama(s) × 50€<br>`;
                    if (furnitureCount.armarios > 0) summaryHTML += `${furnitureCount.armarios} armario(s) × 100€<br>`;
                    if (furnitureCount.modulares > 0) summaryHTML += `${furnitureCount.modulares} modular(es) × 125€<br>`;
                    summaryHTML += `<div style="text-align: right; font-weight: bold; color: #2e7d32;">+${totalMuebles}€</div>`;
                    summaryHTML += `</div>`;
                }
                
                if (extraBoxes > 0) {
                    const totalCajas = extraBoxes * 5;
                    summaryHTML += `<div style="padding: 8px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px;">`;
                    summaryHTML += `<strong>Embalaje profesional:</strong><br>`;
                    summaryHTML += `15 cajas incluidas<br>`;
                    summaryHTML += `${extraBoxes} cajas extra × 5€<br>`;
                    summaryHTML += `<div style="text-align: right; font-weight: bold; color: #2e7d32;">+${totalCajas}€</div>`;
                    summaryHTML += `</div>`;
                }
                
                summaryHTML += `</div>`;
                
                const pricing = calculateFinalPrice(calculatedPrice);

                summaryHTML += `<div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 0.85rem;">`;
                summaryHTML += `<div style="display: flex; justify-content: space-between; padding: 5px 0;">`;
                summaryHTML += `<span>Subtotal:</span><span style="font-weight: bold;">${pricing.subtotal.toFixed(2)}€</span>`;
                summaryHTML += `</div>`;
                summaryHTML += `<div style="display: flex; justify-content: space-between; padding: 5px 0; color: #4caf50;">`;
                summaryHTML += `<span>IVA (21%):</span><span style="font-weight: bold;">+${pricing.iva.toFixed(2)}€</span>`;
                summaryHTML += `</div>`;
                summaryHTML += `<div style="display: flex; justify-content: space-between; padding: 5px 0; color: #f44336;">`;
                summaryHTML += `<span>IRPF (-1%):</span><span style="font-weight: bold;">${pricing.irpf.toFixed(2)}€</span>`;
                summaryHTML += `</div>`;
                summaryHTML += `</div>`;

                summaryHTML += `<div style="text-align: center; margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); border-radius: 10px; color: white;">`;
                summaryHTML += `<div style="font-size: 0.9rem;">PRECIO TOTAL (IVA incluido)</div>`;
                summaryHTML += `<div style="font-size: 1.8rem; font-weight: bold; margin: 5px 0;">${pricing.total.toFixed(2)}€</div>`;
                summaryHTML += `<div style="font-size: 0.75rem; opacity: 0.9;">Subtotal: ${pricing.subtotal.toFixed(2)}€ + IVA ${pricing.iva.toFixed(2)}€ + IRPF ${pricing.irpf.toFixed(2)}€</div>`;
                summaryHTML += `</div>`;
                
                summaryHTML += `<div style="margin-top: 15px; font-size: 0.8rem; padding: 10px; background: #e8f5e9; border-radius: 8px;">`;
                summaryHTML += `<strong>✓ Incluye:</strong><br>`;
                summaryHTML += `• Transporte profesional<br>`;
                summaryHTML += `• Operarios especializados<br>`;
                summaryHTML += `• Protección básica<br>`;
                summaryHTML += `• Seguro de transporte`;
                summaryHTML += `</div>`;
                
                summaryDiv.innerHTML = summaryHTML;
                messageContainer.appendChild(summaryDiv);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                
                setTimeout(() => {
                    const actionsDiv = document.createElement('div');
                    actionsDiv.style.cssText = 'display: flex; gap: 10px; margin-top: 10px;';
                    
                    const confirmBtn = document.createElement('button');
                    confirmBtn.className = 'option-btn-widget';
                    confirmBtn.textContent = '✓ Confirmar';
                    confirmBtn.style.cssText = 'background: #4caf50; color: white; flex: 1; padding: 12px; font-size: 0.9rem;';
                    confirmBtn.onclick = () => {
                        actionsDiv.remove();
                        addMessage('✓ Confirmar presupuesto', 'user');
                        setTimeout(() => askFullName(), 1000);
                    };
                    
                    const modifyBtn = document.createElement('button');
                    modifyBtn.className = 'option-btn-widget';
                    modifyBtn.textContent = '✏️ Modificar';
                    modifyBtn.style.cssText = 'background: #ff9800; color: white; flex: 1; padding: 12px; font-size: 0.9rem;';
                    modifyBtn.onclick = () => {
                        addMessage('✏️ Modificar opciones', 'user');
                        actionsDiv.remove();
                        summaryDiv.remove();
                        setTimeout(() => {
                            addMessage("¿Qué deseas modificar?", 'assistant');
                            setTimeout(() => {
                                addOptions([
                                    { id: 'muebles', name: 'Muebles a montar' },
                                    { id: 'cajas', name: 'Cajas adicionales' },
                                    { id: 'todo', name: 'Empezar de nuevo' }
                                ], (option) => {
                                    if (option === 'muebles') {
                                        addMessage('Muebles a montar', 'user');
                                        const oldFurniture = (furnitureCount.camas * 50) + (furnitureCount.armarios * 100) + (furnitureCount.modulares * 125);
                                        calculatedPrice -= oldFurniture;
                                        furnitureCount = { camas: 0, armarios: 0, modulares: 0 };
                                        setTimeout(() => showFurnitureSelector(), 800);
                                    } else if (option === 'cajas') {
                                        addMessage('Cajas adicionales', 'user');
                                        calculatedPrice -= (extraBoxes * 5);
                                        extraBoxes = 0;
                                        setTimeout(() => askBoxesService(), 800);
                                    } else {
                                        addMessage('Empezar de nuevo', 'user');
                                        setTimeout(() => {
                                            resetForm();
                                            showMainMenu();
                                        }, 800);
                                    }
                                });
                            }, 500);
                        }, 1000);
                    };
                    
                    actionsDiv.appendChild(confirmBtn);
                    actionsDiv.appendChild(modifyBtn);
                    messageContainer.appendChild(actionsDiv);
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }, 500);
            }, 800);
        }

        // Datos del cliente
        function askFullName() {
            simulateTyping(() => {
                addMessage("¿Cuál es tu nombre completo?", 'assistant');
                setTimeout(() => {
                    addInput('Nombre y apellidos', (name) => {
                        formData.fullName = name;
                        addMessage(name, 'user');
                        setTimeout(() => askPhone(), 800);
                    });
                }, 500);
            });
        }

        function askPhone() {
            simulateTyping(() => {
                addMessage("¿Tu número de teléfono?", 'assistant');
                setTimeout(() => {
                    addInput('Ej: 612 345 678', (phone) => {
                        const phoneRegex = /^(?:6|7)\d{8}$/;
                        const cleanPhone = phone.replace(/\s/g, '');
                        
                        if (phoneRegex.test(cleanPhone)) {
                            formData.phone = cleanPhone.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
                            addMessage(formData.phone, 'user');
                            
                            if (formData.serviceType === 'mudanza') {
                                setTimeout(() => askMudanzaDate(), 800);
                            } else {
                                setTimeout(() => askServiceDate(), 800);
                            }
                        } else {
                            addMessage("Número incorrecto. Debe ser un móvil español válido.", 'assistant');
                            setTimeout(() => askPhone(), 1000);
                        }
                    });
                }, 500);
            });
        }

        function askMudanzaDate() {
            simulateTyping(() => {
                addMessage("¿Para qué fecha? (Servicio desde las 9:00h)", 'assistant');
                setTimeout(() => {
                    addDateSelector((date) => {
                        formData.serviceDate = date + " (desde las 9:00h)";
                        addMessage(date, 'user');
                        setTimeout(() => askOriginAddress(), 800);
                    });
                }, 500);
            });
        }

        function askServiceDate() {
            simulateTyping(() => {
                addMessage("¿Para qué fecha necesitas el servicio?", 'assistant');
                setTimeout(() => {
                    addDateSelector((date) => {
                        formData.serviceDate = date;
                        addMessage(date, 'user');
                        setTimeout(() => askOriginAddress(), 800);
                    });
                }, 500);
            });
        }

        function askOriginAddress() {
            simulateTyping(() => {
                addMessage("¿Dirección de origen/recogida?", 'assistant');
                setTimeout(() => {
                    addInput('Dirección completa', (address) => {
                        formData.originAddress = address;
                        addMessage(address, 'user');
                        setTimeout(() => askDestinationAddress(), 800);
                    });
                }, 500);
            });
        }

        function askDestinationAddress() {
            simulateTyping(() => {
                addMessage("¿Dirección de destino/entrega?", 'assistant');
                setTimeout(() => {
                    addInput('Dirección completa', (address) => {
                        formData.destinationAddress = address;
                        addMessage(address, 'user');
                        setTimeout(() => askObservations(), 800);
                    });
                }, 500);
            });
        }

        function askObservations() {
            simulateTyping(() => {
                addMessage("¿Alguna observación adicional?", 'assistant');
                setTimeout(() => {
                    const container = document.createElement('div');
                    const inputDiv = document.createElement('div');
                    inputDiv.classList.add('input-container-widget');
                    
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Observaciones (opcional)';
                    
                    const sendBtn = document.createElement('button');
                    sendBtn.textContent = 'Enviar';
                    
                    inputDiv.appendChild(input);
                    inputDiv.appendChild(sendBtn);
                    
                    const optionsDiv = document.createElement('div');
                    optionsDiv.classList.add('options-container-widget');
                    optionsDiv.style.marginTop = '10px';
                    
                    const noBtn = document.createElement('button');
                    noBtn.classList.add('option-btn-widget');
                    noBtn.textContent = 'Sin Observaciones';
                    noBtn.style.width = '100%';
                    optionsDiv.appendChild(noBtn);
                    
                    container.appendChild(inputDiv);
                    container.appendChild(optionsDiv);
                    messageContainer.appendChild(container);
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    input.focus();
                    
                    const handleSubmit = (obs) => {
                        formData.observations = obs;
                        addMessage(obs, 'user');
                        container.remove();
                        
                        if (formData.serviceType === 'mudanza') {
                            setTimeout(() => showPaymentOption(), 1000);
                        } else {
                            setTimeout(() => sendToWhatsApp(), 1000);
                        }
                    };
                    
                    sendBtn.onclick = () => {
                        if (input.value.trim() !== '') handleSubmit(input.value.trim());
                    };
                    
                    input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter' && input.value.trim() !== '') sendBtn.click();
                    });
                    
                    noBtn.onclick = () => handleSubmit('Sin observaciones');
                }, 500);
            });
        }

        // =========================================================================
        // PAGO CON STRIPE
        // =========================================================================
        function showPaymentOption() {
            simulateTyping(() => {
                addMessage("Para <strong>bloquear tu fecha</strong> solo necesitamos una reserva de <strong>50€</strong>.", 'assistant');
                
                setTimeout(() => {
                    const benefitsDiv = document.createElement('div');
                    benefitsDiv.style.cssText = 'background: #e8f5e9; padding: 12px; border-radius: 10px; margin: 10px 0; font-size: 0.85rem;';
                    benefitsDiv.innerHTML = `
                        <div style="font-weight: bold; margin-bottom: 8px; color: #2e7d32;">✅ Beneficios de reservar ahora:</div>
                        <div>✓ Se descuenta del total (${calculatedPrice}€)</div>
                        <div>✓ Garantiza disponibilidad</div>
                        <div>✓ Te asegura el precio acordado</div>
                        <div style="margin-top: 8px; font-style: italic; color: #666;">Pago 100% seguro con tarjeta</div>
                    `;
                    messageContainer.appendChild(benefitsDiv);
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    
                    setTimeout(() => {
                        const payBtn = document.createElement('button');
                        payBtn.className = 'payment-btn-widget';
                        payBtn.textContent = '👉 Reservar fecha ahora (Pago 50€)';
                        payBtn.onclick = () => processPayment();
                        
                        messageContainer.appendChild(payBtn);
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                    }, 800);
                }, 1000);
            });
        }

        function processPayment() {
            if (!stripe) {
                addMessage("❌ Error: Stripe no está configurado correctamente.", 'assistant');
                console.error('Stripe no inicializado');
                return;
            }

            addMessage("Procesando pago...", 'assistant');
            showTypingIndicator();

            const formDataPayment = new FormData();
            formDataPayment.append('action', 'zaragonjg_create_payment');
            formDataPayment.append('security', zaragonjg_security_nonce);
            formDataPayment.append('amount', 5000);
            
            const finalPricing = calculateFinalPrice(calculatedPrice);

            formDataPayment.append('customer_data', JSON.stringify({
                name: formData.fullName,
                phone: formData.phone,
                service: 'Mudanza',
                date: formData.serviceDate,
                subtotal: finalPricing.subtotal,
                iva: finalPricing.iva,
                irpf: finalPricing.irpf,
                price: finalPricing.total
            }));

            fetch(zaragonjg_ajax_url, {
                method: 'POST',
                body: formDataPayment
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                hideTypingIndicator();
                
                if (data.success && data.data.sessionId) {
                    stripe.redirectToCheckout({ sessionId: data.data.sessionId })
                        .then(result => {
                            if (result.error) {
                                addMessage("❌ " + result.error.message, 'assistant');
                            }
                        });
                } else {
                    let errorMsg = "Error al procesar el pago.";
                    if (data.data?.message) {
                        errorMsg = data.data.message;
                    }
                    addMessage("❌ " + errorMsg, 'assistant');
                    addMessage("Por favor, contacta con nosotros si el problema persiste.", 'assistant');
                }
            })
            .catch(error => {
                hideTypingIndicator();
                addMessage("❌ Error de conexión. Por favor, verifica tu internet e intenta nuevamente.", 'assistant');
                console.error('Error:', error);
            });
        }

        // =========================================================================
        // ENVÍO A WHATSAPP (SERVICIOS SIN PAGO)
        // =========================================================================
        function sendToWhatsApp() {
            simulateTyping(() => {
                addMessage("Enviando tu solicitud al equipo...", 'assistant');
                
                const message = formatMessageForWhatsApp();
                const formDataWhatsApp = new FormData();
                formDataWhatsApp.append('action', 'zaragonjg_enviar_whatsapp');
                formDataWhatsApp.append('form_data', message);
                formDataWhatsApp.append('security', zaragonjg_security_nonce);

                fetch(zaragonjg_ajax_url, {
                    method: 'POST',
                    body: formDataWhatsApp
                })
                .then(response => response.json())
                .then(data => {
                    hideTypingIndicator();
                    
                    if (data.success) {
                        addMessage("✅ ¡Listo! Tu solicitud ha sido enviada. Te contactaremos pronto por WhatsApp.", 'assistant');
                    } else {
                        addMessage("❌ Error al enviar. Contáctanos directamente: 625 83 52 62", 'assistant');
                    }
                })
                .catch(error => {
                    hideTypingIndicator();
                    addMessage("❌ Error de conexión. Llámanos al: 625 83 52 62", 'assistant');
                    console.error('Error:', error);
                });
            }, 1000);
        }

        function formatMessageForWhatsApp() {
            let msg = `*🤖 NUEVA RESERVA CONFIRMADA - PAGO RECIBIDO*\n\n`;
            msg += `*Cliente:* ${formData.fullName}\n`;
            msg += `*Teléfono:* ${formData.phone}\n`;
            msg += `*Servicio:* ${formData.serviceType}\n`;
            
            if (formData.serviceType === 'mudanza') {
                msg += `*Tipo:* ${formData.mudanzaType}\n`;
                if (formData.roomCount) msg += `*Vivienda:* ${formData.roomCount}\n`;
                if (formData.destination) msg += `*Destino:* ${formData.destination}\n`;
            }
            
            msg += `*Fecha:* ${formData.serviceDate}\n`;
            msg += `*Origen:* ${formData.originAddress}\n`;
            msg += `*Destino:* ${formData.destinationAddress}\n`;
            
            msg += `\n*💰 PRECIO ESTIMADO TOTAL:* ${calculatedPrice}€\n`;
            msg += `*💳 ANTICIPO PAGADO:* 50€\n`;
            msg += `*📊 SALDO PENDIENTE:* ${calculatedPrice - 50}€\n\n`;
            
            if (formData.additionalServices.length > 0) {
                msg += `*Servicios adicionales:*\n`;
                formData.additionalServices.forEach(s => {
                    msg += `  ✓ ${s.charAt(0).toUpperCase() + s.slice(1)}\n`;
                });
                msg += `\n`;
            }
            
            if (formData.observations && formData.observations !== 'Sin observaciones') {
                msg += `*Observaciones:* ${formData.observations}\n\n`;
            }
            
            msg += `⚠️ *ACCIÓN REQUERIDA:* Contactar al cliente para confirmar detalles finales.`;
            
            return msg;
        }

        // =========================================================================
        // EVENTOS DEL MODAL
        // =========================================================================
        openModalBtn.addEventListener('click', () => {
            resetForm();
            serviceModal.style.display = 'flex';
            serviceModal.classList.add('active');
            addMessage("👋 ¡Hola!", 'assistant');
            setTimeout(() => {
                addMessage("Podemos hacer tu mudanza <strong>desde 100€</strong>.", 'assistant');
                setTimeout(() => {
                    addMessage("Te calculo el precio exacto en menos de 1 minuto. 😊", 'assistant');
                    setTimeout(() => showMainMenu(), 800);
                }, 1000);
            }, 800);
        });

        closeModalBtn.addEventListener('click', () => {
            serviceModal.style.display = 'none';
            serviceModal.classList.remove('active');
            resetForm();
        });

        serviceModal.addEventListener('click', (e) => {
            if (e.target === serviceModal) {
                serviceModal.style.display = 'none';
                serviceModal.classList.remove('active');
                resetForm();
            }
        });
    });
})();
</script>
                </h2>
            </div>
            <div class="chat-container-widget" id="chatContainer">
                <div class="message-container-widget" id="messageContainer"></div>
            </div>
            <div class="modal-footer-widget">
                <button id="closeModalBtn" class="close-btn-widget">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --zaragonjg-fab-bottom-offset: 100px; 
    }
    
    #zaragonjg-open-chat {
        position: fixed;
        bottom: var(--zaragonjg-fab-bottom-offset);
        right: 10px;
        z-index: 1000;
        width: 120px;
        height: 120px;
        background: transparent;
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        overflow: hidden;
        animation: zaragonjg-bobbing 2.5s infinite ease-in-out;
        box-shadow: none !important;
    }

    #zaragonjg-open-chat:hover {
        transform: scale(1.1);
        animation-play-state: paused;
        box-shadow: none !important;
    }

    #zaragonjg-open-chat img {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        object-fit: cover;
    }

    @keyframes zaragonjg-bobbing {
        0% { transform: translateY(0) scale(1); box-shadow: none !important; }
        50% { transform: translateY(-6px) scale(1); box-shadow: none !important; }
        100% { transform: translateY(0) scale(1); box-shadow: none !important; }
    }

    #zaragonjg-open-chat .ripple {
        position: absolute;
        border-radius: 50%;
        transform: scale(0);
        animation: zaragonjg-ripple-click 0.6s linear;
        background-color: rgba(255, 255, 255, 0.7);
        z-index: -1;
    }

    @keyframes zaragonjg-ripple-click {
        to { transform: scale(4); opacity: 0; }
    }

    #zaragonjg-service-widget {
        all: revert;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    #zaragonjg-service-widget *,
    #zaragonjg-service-widget *::before,
    #zaragonjg-service-widget *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: inherit;
    }

    #zaragonjg-service-widget .modal-widget {
        display: none; 
        position: fixed;
        z-index: 99999; 
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: transparent;
        pointer-events: none;
    }

    #zaragonjg-service-widget .modal-widget.active {
        pointer-events: auto;
    }

    #zaragonjg-service-widget .modal-content-widget {
        position: fixed;
        right: 20px;
        bottom: 20px;
        background: white;
        padding: 0;
        border-radius: 16px;
        width: 380px;
        height: 600px;
        max-height: calc(100vh - 40px);
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        animation: zaragonjg-modalAppear 0.4s ease-out;
        pointer-events: auto;
    }

    @keyframes zaragonjg-modalAppear {
        from { opacity: 0; transform: translateY(50px); }
        to { opacity: 1; transform: translateY(0); }
    }

    #zaragonjg-service-widget .modal-header-widget {
        background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
        color: white;
        padding: 12px 15px;
        text-align: center;
    }

    #zaragonjg-service-widget .modal-header-widget h2 {
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: white;
    }

    #zaragonjg-service-widget .assistant-avatar-widget {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    #zaragonjg-service-widget .chat-container-widget {
        overflow-y: auto;
        padding: 12px;
        background: #f8f9fa;
        flex-grow: 1; 
    }

    #zaragonjg-service-widget .message-widget {
        margin-bottom: 10px;
        padding: 8px 12px;
        border-radius: 14px;
        max-width: 85%;
        word-wrap: break-word;
        animation: zaragonjg-fadeIn 0.3s ease;
        line-height: 1.3;
        font-size: 0.9rem;
    }

    @keyframes zaragonjg-fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    #zaragonjg-service-widget .assistant-widget {
        background: #e3f2fd;
        color: #0d47a1;
        align-self: flex-start;
        border-bottom-left-radius: 5px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    #zaragonjg-service-widget .user-widget {
        background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 5px;
        margin-left: auto;
    }

    #zaragonjg-service-widget .price-summary-widget {
        background: #fff3cd;
        border: 2px solid #ffc107;
        border-radius: 10px;
        padding: 10px;
        margin: 10px 0;
        font-weight: bold;
        font-size: 0.85rem;
    }

    #zaragonjg-service-widget .price-summary-widget .total {
        font-size: 1.1rem;
        color: #ff6f00;
        margin-top: 8px;
    }

    #zaragonjg-service-widget .options-container-widget {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    #zaragonjg-service-widget .option-btn-widget {
        background: #e8f5e9;
        border: 2px solid #c8e6c9;
        color: #2e7d32;
        padding: 8px 12px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
        flex: 1 1 100px; 
        font-weight: bold;
        font-size: 0.85rem;
    }

    #zaragonjg-service-widget .option-btn-widget:hover {
        background: #c8e6c9;
        transform: translateY(-2px);
    }

    #zaragonjg-service-widget .input-container-widget {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    #zaragonjg-service-widget .input-container-widget input {
        flex: 1;
        padding: 8px 10px;
        border: 2px solid #ddd;
        border-radius: 10px;
        font-size: 0.9rem;
    }

    #zaragonjg-service-widget .input-container-widget button {
        background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
        color: white;
        border: none;
        padding: 0 15px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: bold;
        font-size: 0.85rem;
    }

    #zaragonjg-service-widget .modal-footer-widget {
        padding: 12px;
        text-align: center;
        background: white;
        border-top: 1px solid #eee;
    }

    #zaragonjg-service-widget .close-btn-widget {
        background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: bold;
    }

    #zaragonjg-service-widget .message-container-widget {
        display: flex;
        flex-direction: column;
    }

    #zaragonjg-service-widget .typing-indicator-widget {
        background: #e3f2fd;
        color: #0d47a1;
        padding: 8px 12px;
        border-radius: 14px;
        max-width: 80%;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    #zaragonjg-service-widget .typing-dot-widget {
        width: 6px;
        height: 6px;
        background: #0d47a1;
        border-radius: 50%;
        animation: zaragonjg-typing 1.4s infinite ease-in-out;
    }

    #zaragonjg-service-widget .typing-dot-widget:nth-child(2) { animation-delay: 0.2s; }
    #zaragonjg-service-widget .typing-dot-widget:nth-child(3) { animation-delay: 0.4s; }

    @keyframes zaragonjg-typing {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-5px); }
    }

    #zaragonjg-service-widget .payment-btn-widget {
        background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: bold;
        font-size: 0.95rem;
        width: 100%;
        margin-top: 10px;
    }

    #zaragonjg-service-widget .payment-btn-widget:hover {
        background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
    }

    #zaragonjg-service-widget .date-selector-container {
        display: flex;
        flex-wrap: wrap; 
        gap: 8px;
        margin-top: 10px;
    }

    #zaragonjg-service-widget .date-selector-container input[type="date"],
    #zaragonjg-service-widget .date-selector-container input[type="time"] {
        padding: 8px 10px;
        border: 2px solid #ddd;
        border-radius: 10px;
        font-size: 0.9rem;
        flex-grow: 1; 
    }

    #zaragonjg-service-widget .date-selector-container button {
        background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: bold;
        font-size: 0.85rem;
    }

    @media (max-width: 768px) {
		#zaragonjg-service-widget .modal-widget {
			display: none;
			background-color: rgba(0, 0, 0, 0.5);
			align-items: center;
			justify-content: center;
		}

		#zaragonjg-service-widget .modal-content-widget {
			position: relative;
			bottom: auto;
			right: auto;
			left: auto;
			width: 100%;
			height: 100%;
			max-height: 100%;
			border-radius: 0;
			animation: zaragonjg-modalAppearMobile 0.4s ease-out;
		}

		@keyframes zaragonjg-modalAppearMobile {
			from { opacity: 0; transform: translateY(-50px); }
			to { opacity: 1; transform: translateY(0); }
		}

		#zaragonjg-service-widget .message-widget {
			max-width: 95%;
		}

		#zaragonjg-open-chat {
			width: 100px;
			height: 100px;
			bottom: 175px;
			right: 5px;
		}

		#zaragonjg-open-chat img {
			width: 90px;
			height: 90px;
			border-radius: 50%;
			object-fit: cover;
		}
	}

	@media (min-width: 769px) and (max-width: 1024px) {
		#zaragonjg-service-widget .modal-content-widget {
			width: 400px;
			height: 650px;
		}
	}
</style>
HTML_CSS;

    // Inyectar variables de PHP a JavaScript
    echo "<script type='text/javascript'>
        const zaragonjg_ajax_url = '" . esc_js($ajax_url) . "';
        const zaragonjg_security_nonce = '" . esc_js($security_nonce) . "';
        const zaragonjg_stripe_public_key = '" . esc_js($stripe_public_key) . "';
    </script>";

    // Script de Stripe
    echo '<script src="https://js.stripe.com/v3/"></script>';

    // JavaScript principal del bot
    $javascript_content = file_get_contents(dirname(__FILE__) . '/chatbot-javascript.php');
    echo $javascript_content;
}

// =========================================================================
// PARTE 2: CREAR SESIÓN DE PAGO CON STRIPE
// =========================================================================
add_action('wp_ajax_zaragonjg_create_payment', 'zaragonjg_create_stripe_payment');
add_action('wp_ajax_nopriv_zaragonjg_create_payment', 'zaragonjg_create_stripe_payment');

function zaragonjg_create_stripe_payment() {
    check_ajax_referer('zaragonjg_bot_nonce', 'security');

    $stripe_secret = defined('ZARAGONJG_STRIPE_SECRET_KEY') ? ZARAGONJG_STRIPE_SECRET_KEY : '';
    
    if (empty($stripe_secret)) {
        wp_send_json_error([
            'message' => 'Stripe no configurado en wp-config.php',
            'debug' => 'Falta define(\'ZARAGONJG_STRIPE_SECRET_KEY\', \'sk_test_...\');'
        ]);
        wp_die();
    }

    $stripe_lib = ABSPATH . 'wp-content/plugins/stripe-php/init.php';
    
    if (!file_exists($stripe_lib)) {
        wp_send_json_error([
            'message' => 'Librería Stripe no encontrada',
            'debug' => 'Descarga Stripe PHP de: https://github.com/stripe/stripe-php/releases',
            'path' => 'Debe estar en: ' . $stripe_lib
        ]);
        wp_die();
    }

    try {
        require_once($stripe_lib);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Error al cargar Stripe',
            'debug' => $e->getMessage()
        ]);
        wp_die();
    }

    try {
        \Stripe\Stripe::setApiKey($stripe_secret);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Error al configurar Stripe API',
            'debug' => $e->getMessage()
        ]);
        wp_die();
    }

    $amount = intval($_POST['amount'] ?? 0);
    $customer_data_raw = stripslashes($_POST['customer_data'] ?? '{}');
    $customer_data = json_decode($customer_data_raw, true);

    if ($amount <= 0 || empty($customer_data['name'])) {
        wp_send_json_error([
            'message' => 'Datos de pago incompletos',
            'debug' => 'amount: ' . $amount . ', customer_data: ' . print_r($customer_data, true)
        ]);
        wp_die();
    }

    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Reserva de Mudanza - ' . $customer_data['name'],
                        'description' => 'Fecha: ' . ($customer_data['date'] ?? 'Por definir') . ' | Precio estimado: ' . ($customer_data['price'] ?? '0') . '€',
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => home_url('/?payment=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/?payment=cancel'),
            'metadata' => [
                'customer_name' => $customer_data['name'] ?? '',
                'customer_phone' => $customer_data['phone'] ?? '',
                'service_date' => $customer_data['date'] ?? '',
                'service_type' => $customer_data['service'] ?? 'Mudanza',
                'subtotal' => $customer_data['subtotal'] ?? 0,
                'iva' => $customer_data['iva'] ?? 0,
                'irpf' => $customer_data['irpf'] ?? 0,
                'estimated_price' => $customer_data['price'] ?? 0
            ]
        ]);

        wp_send_json_success([
            'sessionId' => $session->id,
            'message' => 'Sesión creada correctamente'
        ]);

    } catch (\Stripe\Exception\ApiErrorException $e) {
        wp_send_json_error([
            'message' => 'Error de Stripe API',
            'debug' => $e->getMessage(),
            'type' => get_class($e)
        ]);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Error inesperado al crear sesión',
            'debug' => $e->getMessage()
        ]);
    }

    wp_die();
}

// =========================================================================
// MODIFICACIÓN 6: GENERACIÓN DE FACTURA PDF
// =========================================================================

/**
 * Generar factura PDF con TCPDF
 */
function zaragonjg_generate_invoice_pdf($invoice_data) {
    // Verificar si TCPDF está instalado
    $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
    
    if (!file_exists($tcpdf_path)) {
        error_log('TCPDF no encontrado en: ' . $tcpdf_path);
        return false;
    }
    
    require_once($tcpdf_path);
    
    // Crear instancia de TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuración del documento
    $pdf->SetCreator('Mudanzas Zaragonjg');
    $pdf->SetAuthor('Mudanzas Zaragonjg');
    $pdf->SetTitle('Factura de Reserva');
    $pdf->SetSubject('Factura de Servicio');
    
    // Eliminar header y footer predeterminados
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configuración de márgenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Agregar página
    $pdf->AddPage();
    
    // Logo de la empresa (opcional)
    $logo_path = ABSPATH . 'wp-content/uploads/2025/11/logo-zaragonjg.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 15, 15, 40, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }
    
    // Título
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(13, 71, 161);
    $pdf->Cell(0, 10, 'FACTURA DE RESERVA', 0, 1, 'R');
    
    $pdf->Ln(5);
    
    // Información de la empresa
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'MUDANZAS ZARAGONJG', 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'CIF: B99999999', 0, 1);
    $pdf->Cell(0, 5, 'Dirección: Calle Principal, 123', 0, 1);
    $pdf->Cell(0, 5, 'Zaragoza, 50001', 0, 1);
    $pdf->Cell(0, 5, 'Teléfono: 625 83 52 62', 0, 1);
    $pdf->Cell(0, 5, 'Email: info@zaragonjg.com', 0, 1);
    
    $pdf->Ln(10);
    
    // Línea separadora
    $pdf->SetDrawColor(13, 71, 161);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    
    $pdf->Ln(10);
    
    // Datos del cliente
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 5, 'DATOS DEL CLIENTE', 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 5, 'Nombre:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, $invoice_data['customer_name'], 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 5, 'Teléfono:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, $invoice_data['customer_phone'], 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 5, 'Fecha de servicio:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, $invoice_data['service_date'], 0, 1);
    
    $pdf->Ln(5);
    
    // Datos de la factura
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 5, 'DATOS DE LA FACTURA', 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 5, 'Número de factura:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, $invoice_data['invoice_number'], 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(40, 5, 'Fecha de emisión:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, date('d/m/Y'), 0, 1);
    
    $pdf->Ln(10);
    
    // Tabla de conceptos
    $pdf->SetFillColor(13, 71, 161);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(120, 8, 'CONCEPTO', 1, 0, 'L', true);
    $pdf->Cell(30, 8, 'CANTIDAD', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'IMPORTE', 1, 1, 'R', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    // Anticipo de reserva
    $pdf->Cell(120, 7, 'Anticipo de Reserva - ' . $invoice_data['service_type'], 1, 0, 'L');
    $pdf->Cell(30, 7, '1', 1, 0, 'C');
    $pdf->Cell(30, 7, '50,00 €', 1, 1, 'R');
    
    $pdf->Ln(5);
    
    // Desglose de impuestos
    $pdf->SetFont('helvetica', '', 9);
    
    $pdf->Cell(150, 6, 'Subtotal servicio completo:', 0, 0, 'R');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 6, number_format($invoice_data['subtotal'], 2, ',', '.') . ' €', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(150, 6, 'IVA (21%):', 0, 0, 'R');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(76, 175, 80);
    $pdf->Cell(30, 6, '+' . number_format($invoice_data['iva'], 2, ',', '.') . ' €', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(150, 6, 'IRPF (-1%):', 0, 0, 'R');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(244, 67, 54);
    $pdf->Cell(30, 6, number_format($invoice_data['irpf'], 2, ',', '.') . ' €', 0, 1, 'R');
    
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Ln(2);
    
    // Total estimado
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(232, 245, 233);
    $pdf->Cell(150, 8, 'TOTAL ESTIMADO (IVA incluido):', 1, 0, 'R', true);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(46, 125, 50);
    $pdf->Cell(30, 8, number_format($invoice_data['total'], 2, ',', '.') . ' €', 1, 1, 'R', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);
    
    // Anticipo pagado
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(255, 243, 205);
    $pdf->Cell(150, 7, 'Anticipo pagado:', 1, 0, 'R', true);
    $pdf->SetTextColor(255, 111, 0);
    $pdf->Cell(30, 7, '50,00 €', 1, 1, 'R', true);
    
    $pdf->SetTextColor(0, 0, 0);
    
    // Saldo pendiente
    $balance = $invoice_data['total'] - 50;
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(255, 235, 238);
    $pdf->Cell(150, 7, 'Saldo pendiente:', 1, 0, 'R', true);
    $pdf->SetTextColor(211, 47, 47);
    $pdf->Cell(30, 7, number_format($balance, 2, ',', '.') . ' €', 1, 1, 'R', true);
    
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Ln(10);
    
    // Notas
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 4, "Notas:\n- Esta factura es un comprobante de anticipo de reserva.\n- El saldo pendiente se abonará al finalizar el servicio.\n- El precio final puede variar según servicios adicionales solicitados.\n- Forma de pago del saldo: Efectivo o transferencia bancaria.", 0, 'L');
    
    $pdf->Ln(5);
    
    // Footer
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5, 'Gracias por confiar en Mudanzas Zaragonjg', 0, 1, 'C');
    $pdf->Cell(0, 5, 'www.mudanzaszaragonjg.com', 0, 1, 'C');
    
    // Generar nombre de archivo único
    $upload_dir = wp_upload_dir();
    $pdf_filename = 'factura_' . $invoice_data['invoice_number'] . '_' . time() . '.pdf';
    $pdf_path = $upload_dir['path'] . '/' . $pdf_filename;
    
    // Guardar PDF
    $pdf->Output($pdf_path, 'F');
    
    return [
        'path' => $pdf_path,
        'url' => $upload_dir['url'] . '/' . $pdf_filename,
        'filename' => $pdf_filename
    ];
}

// =========================================================================
// MODIFICACIÓN 4: PÁGINA DE ÉXITO CON DESCARGA DE PDF
// =========================================================================

add_action('template_redirect', 'zaragonjg_payment_success_page');

function zaragonjg_payment_success_page() {
    if (!isset($_GET['payment']) || $_GET['payment'] != 'success') {
        return;
    }

    $session_id = sanitize_text_field($_GET['session_id'] ?? 'N/A');

    // Obtener URL de descarga de PDF si existe (puede venir del webhook)
    $pdf_url = get_transient('zaragonjg_pdf_' . $session_id);

    // Si no hay PDF aún (el webhook no ha disparado), generarlo directamente
    if (!$pdf_url && $session_id !== 'N/A') {
        $already_processed = get_transient('zaragonjg_processed_' . $session_id);

        if (!$already_processed) {
            $stripe_secret = defined('ZARAGONJG_STRIPE_SECRET_KEY') ? ZARAGONJG_STRIPE_SECRET_KEY : '';
            $stripe_lib = ABSPATH . 'wp-content/plugins/stripe-php/init.php';

            if (!empty($stripe_secret) && file_exists($stripe_lib)) {
                try {
                    require_once($stripe_lib);
                    \Stripe\Stripe::setApiKey($stripe_secret);

                    $session = \Stripe\Checkout\Session::retrieve($session_id);

                    if ($session && $session->payment_status === 'paid') {
                        $invoice_number = 'FAC-' . date('Ymd') . '-' . substr($session->id, -6);

                        $invoice_data = [
                            'invoice_number' => $invoice_number,
                            'customer_name' => $session->metadata->customer_name ?? 'Cliente',
                            'customer_phone' => $session->metadata->customer_phone ?? '',
                            'service_date' => $session->metadata->service_date ?? '',
                            'service_type' => $session->metadata->service_type ?? 'Mudanza',
                            'subtotal' => floatval($session->metadata->subtotal ?? 0),
                            'iva' => floatval($session->metadata->iva ?? 0),
                            'irpf' => floatval($session->metadata->irpf ?? 0),
                            'total' => floatval($session->metadata->estimated_price ?? 0)
                        ];

                        $pdf_result = zaragonjg_generate_invoice_pdf($invoice_data);

                        if ($pdf_result) {
                            $pdf_url = $pdf_result['url'];
                            set_transient('zaragonjg_pdf_' . $session->id, $pdf_url, 3600);

                            // Enviar notificación con PDF a WhatsApp
                            zaragonjg_send_payment_confirmation_whatsapp($session, $pdf_result);
                        }

                        // Marcar como procesado para evitar duplicados en recargas
                        set_transient('zaragonjg_processed_' . $session_id, true, 3600);
                    }
                } catch (Exception $e) {
                    error_log('Error generando PDF en página de éxito: ' . $e->getMessage());
                }
            }
        }
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>¡Pago Exitoso! - Mudanzas Zaragonjg</title>
        <style>
            *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                padding: 20px;
                overflow-y: auto;
            }
            .zaragonjg-success-container {
                background: white;
                border-radius: 20px;
                padding: 40px;
                max-width: 600px;
                width: 100%;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                animation: zaragonjg-slideUp 0.5s ease-out;
                margin: auto;
            }
            @media (max-width: 480px) {
                body {
                    padding: 10px;
                }
                .zaragonjg-success-container {
                    padding: 24px 16px;
                    border-radius: 12px;
                }
                .zaragonjg-success-container h1 {
                    font-size: 1.8rem;
                }
            }
            @keyframes zaragonjg-slideUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .zaragonjg-success-icon {
                width: 100px;
                height: 100px;
                background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 30px;
                animation: zaragonjg-scaleIn 0.6s ease-out 0.2s both;
            }
            @keyframes zaragonjg-scaleIn {
                from { transform: scale(0); }
                to { transform: scale(1); }
            }
            .zaragonjg-success-icon::before {
                content: "\2713";
                font-size: 60px;
                color: white;
                font-weight: bold;
            }
            .zaragonjg-success-container h1 {
                color: #2C3E50;
                font-size: 2.5rem;
                margin-bottom: 15px;
            }
            .zaragonjg-subtitle {
                color: #666;
                font-size: 1.1rem;
                margin-bottom: 30px;
            }
            .zaragonjg-details-box {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 25px;
                margin: 30px 0;
                text-align: left;
            }
            .zaragonjg-detail-row {
                display: flex;
                justify-content: space-between;
                padding: 12px 0;
                border-bottom: 1px solid #e0e0e0;
            }
            .zaragonjg-detail-row:last-child {
                border-bottom: none;
            }
            .zaragonjg-btn {
                display: inline-block;
                padding: 15px 30px;
                margin: 10px;
                border: none;
                border-radius: 10px;
                font-size: 1rem;
                font-weight: bold;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            .zaragonjg-btn-primary {
                background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
                color: white;
            }
            .zaragonjg-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
            }
            .zaragonjg-btn-pdf {
                background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
                color: white;
            }
            .zaragonjg-btn-pdf:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(255, 152, 0, 0.4);
            }
            .zaragonjg-whatsapp-notice {
                background: #e8f5e9;
                border-left: 4px solid #4caf50;
                padding: 15px;
                margin-top: 25px;
                border-radius: 8px;
                text-align: left;
            }
            .zaragonjg-confetti {
                position: fixed;
                top: -10px;
                width: 10px;
                height: 10px;
                border-radius: 2px;
                animation: zaragonjg-confetti-fall 3s linear infinite;
            }
            @keyframes zaragonjg-confetti-fall {
                0% {
                    transform: translateY(0) rotate(0deg);
                    opacity: 1;
                }
                100% {
                    transform: translateY(calc(100vh + 20px)) rotate(720deg);
                    opacity: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="zaragonjg-success-container">
            <div class="zaragonjg-success-icon"></div>
            <h1>¡Pago Confirmado!</h1>
            <p class="zaragonjg-subtitle">Tu reserva ha sido procesada exitosamente</p>

            <div class="zaragonjg-details-box">
                <div class="zaragonjg-detail-row">
                    <span>Número de transacción:</span>
                    <span><strong>#<?php echo substr($session_id, -8); ?></strong></span>
                </div>
                <div class="zaragonjg-detail-row">
                    <span>Anticipo pagado:</span>
                    <span><strong>50,00€</strong></span>
                </div>
                <div class="zaragonjg-detail-row">
                    <span>Estado:</span>
                    <span style="color: #4caf50;"><strong>✓ Confirmado</strong></span>
                </div>
            </div>

            <div class="zaragonjg-whatsapp-notice">
                <strong>📱 WhatsApp Enviado</strong><br>
                Hemos enviado tu confirmación con la factura por WhatsApp. Revisa tu teléfono en unos momentos.
            </div>

            <?php if ($pdf_url): ?>
            <a href="<?php echo esc_url($pdf_url); ?>" class="zaragonjg-btn zaragonjg-btn-pdf" download>
                📄 Descargar Factura PDF
            </a>
            <?php endif; ?>

            <a href="<?php echo home_url(); ?>" class="zaragonjg-btn zaragonjg-btn-primary">
                🏠 Volver al Inicio
            </a>
        </div>

        <script>
            const colors = ['#4caf50', '#00BCD4', '#FF7043', '#FFC107', '#E91E63'];
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'zaragonjg-confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                document.body.appendChild(confetti);
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// =========================================================================
// PARTE 3: WEBHOOK DE STRIPE PARA CONFIRMAR PAGO Y ENVIAR PDF
// =========================================================================
add_action('init', 'zaragonjg_stripe_webhook_handler');

function zaragonjg_stripe_webhook_handler() {
    if (!isset($_GET['zaragonjg_stripe_webhook'])) {
        return;
    }

    $stripe_secret = defined('ZARAGONJG_STRIPE_SECRET_KEY') ? ZARAGONJG_STRIPE_SECRET_KEY : '';
    
    if (empty($stripe_secret)) {
        http_response_code(400);
        exit;
    }

    require_once(ABSPATH . 'wp-content/plugins/stripe-php/init.php');
    \Stripe\Stripe::setApiKey($stripe_secret);

    $payload = @file_get_contents('php://input');
    $event = null;

    try {
        $event = \Stripe\Event::constructFrom(json_decode($payload, true));
    } catch(\UnexpectedValueException $e) {
        http_response_code(400);
        exit;
    }

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        
        // Generar factura PDF
        $invoice_number = 'FAC-' . date('Ymd') . '-' . substr($session->id, -6);
        
        $invoice_data = [
            'invoice_number' => $invoice_number,
            'customer_name' => $session->metadata->customer_name ?? 'Cliente',
            'customer_phone' => $session->metadata->customer_phone ?? '',
            'service_date' => $session->metadata->service_date ?? '',
            'service_type' => $session->metadata->service_type ?? 'Mudanza',
            'subtotal' => floatval($session->metadata->subtotal ?? 0),
            'iva' => floatval($session->metadata->iva ?? 0),
            'irpf' => floatval($session->metadata->irpf ?? 0),
            'total' => floatval($session->metadata->estimated_price ?? 0)
        ];
        
        $pdf_result = zaragonjg_generate_invoice_pdf($invoice_data);
        
        if ($pdf_result) {
            // Guardar URL del PDF para la página de éxito
            set_transient('zaragonjg_pdf_' . $session->id, $pdf_result['url'], 3600);
            
            // Enviar notificación con PDF a WhatsApp
            zaragonjg_send_payment_confirmation_whatsapp($session, $pdf_result);
        }
    }

    http_response_code(200);
    exit;
}

// =========================================================================
// MODIFICACIÓN 7: ENVÍO A WHATSAPP CON PDF ADJUNTO
// =========================================================================

function zaragonjg_send_payment_confirmation_whatsapp($session, $pdf_info = null) {
    $whapi_token = defined('ZARAGONJG_WHAPI_TOKEN') ? ZARAGONJG_WHAPI_TOKEN : '';
    $whapi_phone = defined('ZARAGONJG_WHAPI_PHONE') ? ZARAGONJG_WHAPI_PHONE : '';
    
    if (empty($whapi_token) || empty($whapi_phone)) {
        error_log('Whapi no configurado correctamente');
        return;
    }
    
    $metadata = $session->metadata;
    $customer_name = $metadata->customer_name ?? 'Cliente';
    $customer_phone = $metadata->customer_phone ?? '';
    $service_date = $metadata->service_date ?? '';
    $subtotal = floatval($metadata->subtotal ?? 0);
    $iva = floatval($metadata->iva ?? 0);
    $irpf = floatval($metadata->irpf ?? 0);
    $total = floatval($metadata->estimated_price ?? 0);
    $deposit = 50.00;
    $balance = $total - $deposit;
    
    // ========== MENSAJE A LA EMPRESA ==========
    $company_message = "*🔔 NUEVA RESERVA CONFIRMADA*\n\n";
    $company_message .= "*Cliente:* {$customer_name}\n";
    $company_message .= "*Teléfono:* {$customer_phone}\n";
    $company_message .= "*Fecha servicio:* {$service_date}\n\n";
    $company_message .= "*💰 IMPORTE:*\n";
    $company_message .= "Subtotal: " . number_format($subtotal, 2, ',', '.') . "€\n";
    $company_message .= "IVA (21%): +" . number_format($iva, 2, ',', '.') . "€\n";
    $company_message .= "IRPF (-1%): " . number_format($irpf, 2, ',', '.') . "€\n";
    $company_message .= "TOTAL: *" . number_format($total, 2, ',', '.') . "€*\n\n";
    $company_message .= "*💳 PAGO:*\n";
    $company_message .= "Anticipo: ✅ " . number_format($deposit, 2, ',', '.') . "€\n";
    $company_message .= "Pendiente: " . number_format($balance, 2, ',', '.') . "€\n\n";
    $company_message .= "📄 *Factura PDF adjunta*\n\n";
    $company_message .= "⚠️ *ACCIÓN:* Contactar cliente para confirmar detalles.";
    
    // Enviar mensaje a la empresa
    $company_response = zaragonjg_send_whapi_message($whapi_phone, $company_message, $whapi_token);
    
    // Si hay PDF, enviarlo a la empresa
    if ($pdf_info && $company_response) {
        zaragonjg_send_whapi_document($whapi_phone, $pdf_info['path'], $pdf_info['filename'], $whapi_token);
    }
    
    // ========== MENSAJE AL CLIENTE ==========
    if (!empty($customer_phone)) {
        $client_message = "*✅ ¡PAGO CONFIRMADO!*\n\n";
        $client_message .= "Hola {$customer_name},\n\n";
        $client_message .= "Tu reserva ha sido confirmada exitosamente.\n\n";
        $client_message .= "*📋 RESUMEN:*\n";
        $client_message .= "Fecha: {$service_date}\n";
        $client_message .= "Total: " . number_format($total, 2, ',', '.') . "€\n";
        $client_message .= "Anticipo: ✅ " . number_format($deposit, 2, ',', '.') . "€\n";
        $client_message .= "Pendiente: " . number_format($balance, 2, ',', '.') . "€\n\n";
        $client_message .= "📄 *Adjuntamos tu factura*\n\n";
        $client_message .= "¿Dudas? Contáctanos:\n";
        $client_message .= "📞 625 83 52 62\n";
        $client_message .= "📧 info@zaragonjg.com\n\n";
        $client_message .= "¡Gracias por confiar en Zaragonjg!";
        
        // Formatear teléfono cliente
        $formatted_phone = preg_replace('/[^0-9]/', '', $customer_phone);
        if (substr($formatted_phone, 0, 2) !== '34') {
            $formatted_phone = '34' . $formatted_phone;
        }
        
        // Enviar mensaje al cliente
        $client_response = zaragonjg_send_whapi_message($formatted_phone, $client_message, $whapi_token);
        
        // Si hay PDF, enviarlo al cliente
        if ($pdf_info && $client_response) {
            zaragonjg_send_whapi_document($formatted_phone, $pdf_info['path'], $pdf_info['filename'], $whapi_token);
        }
    }
}

/**
 * Enviar mensaje de texto via Whapi
 */
function zaragonjg_send_whapi_message($phone, $message, $token) {
    $response = wp_remote_post('https://gate.whapi.cloud/messages/text', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'to' => $phone,
            'body' => $message
        ]),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        error_log('Error enviando mensaje Whapi: ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code >= 200 && $response_code < 300) {
        return true;
    }
    
    error_log('Whapi respuesta inesperada: ' . wp_remote_retrieve_body($response));
    return false;
}

/**
 * Enviar documento PDF via Whapi
 */
function zaragonjg_send_whapi_document($phone, $file_path, $filename, $token) {
    if (!file_exists($file_path)) {
        error_log('Archivo PDF no encontrado: ' . $file_path);
        return false;
    }
    
    // Leer el archivo y convertirlo a base64
    $file_content = file_get_contents($file_path);
    $base64_content = base64_encode($file_content);
    
    $response = wp_remote_post('https://gate.whapi.cloud/messages/document', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'to' => $phone,
            'media' => [
                'mimetype' => 'application/pdf',
                'filename' => $filename,
                'body' => $base64_content
            ],
            'caption' => '📄 Factura de reserva adjunta'
        ]),
        'timeout' => 60
    ]);
    
    if (is_wp_error($response)) {
        error_log('Error enviando PDF Whapi: ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code >= 200 && $response_code < 300) {
        return true;
    }
    
    error_log('Whapi PDF respuesta inesperada: ' . wp_remote_retrieve_body($response));
    return false;
}

// =========================================================================
// PARTE 4: ENVÍO A WHATSAPP (SERVICIOS SIN PAGO) - OPTIMIZADO
// =========================================================================
add_action('wp_ajax_zaragonjg_enviar_whatsapp', 'zaragonjg_enviar_whatsapp_whapi');
add_action('wp_ajax_nopriv_zaragonjg_enviar_whatsapp', 'zaragonjg_enviar_whatsapp_whapi');

function zaragonjg_enviar_whatsapp_whapi() {
    check_ajax_referer('zaragonjg_bot_nonce', 'security');

    $whapi_token = defined('ZARAGONJG_WHAPI_TOKEN') ? ZARAGONJG_WHAPI_TOKEN : '';
    $whapi_phone = defined('ZARAGONJG_WHAPI_PHONE') ? ZARAGONJG_WHAPI_PHONE : '';

    if (empty($whapi_token) || empty($whapi_phone)) {
        wp_send_json_error(['message' => 'Whapi no configurado correctamente']);
        wp_die();
    }

    $message_body = sanitize_textarea_field($_POST['form_data']);
    
    $success = zaragonjg_send_whapi_message($whapi_phone, $message_body, $whapi_token);
    
    if ($success) {
        wp_send_json_success(['message' => 'Mensaje enviado vía Whapi']);
    } else {
        wp_send_json_error(['message' => 'Error al enviar mensaje']);
    }

    wp_die();
}
