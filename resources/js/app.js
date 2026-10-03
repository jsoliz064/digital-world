import './bootstrap';

// El lector de codigos por camara (components/escaner-overlay). Hay uno solo,
// en el layout, y cualquier campo lo abre con:
//
//   $dispatch('abrir-escaner', { destino: <input>, modo: 'enter'|'input', continuo: bool })
//
// Al leer un codigo hace lo MISMO que la pistola USB: escribe en el campo y
// "aprieta" Enter. Asi el servidor tiene un solo camino para los dos.
//   - modo 'enter': el campo tiene su Enter, que lee $el.value y lo manda.
//   - modo 'input': el campo es un wire:model comun (ficha, busqueda de tabla):
//     se disparan input y change para que Livewire lo sincronice, y luego Enter.
//
// Lo que no es reactivo (el campo, la camara, el bucle) vive fuera de Alpine:
// un elemento del DOM envuelto en un Proxy reactivo da "Illegal invocation".
let destino = null;
let stream = null;
let bucle = null;
let audio = null;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('escaner', () => ({
        abierto: false,
        cargando: false,
        error: '',
        modo: 'enter',
        continuo: false,
        hayLinterna: false,
        linternaEncendida: false,
        ultimo: '',
        ultimoEn: 0,
        lecturas: 0,

        init() {
            // Escape cierra el lector, no el modal de fondo: el x-on:keydown.escape.window
            // del modal se registra despues, y en captura este va primero.
            window.addEventListener('keydown', (e) => {
                if (this.abierto && e.key === 'Escape') {
                    e.stopImmediatePropagation();
                    this.cerrar();
                }
            }, true);

            // La camara no queda encendida en segundo plano ni al salir.
            document.addEventListener('visibilitychange', () => document.hidden && this.cerrar());
            window.addEventListener('pagehide', () => this.cerrar());
            document.addEventListener('livewire:navigating', () => this.cerrar());
        },

        async abrir(detalle) {
            if (!detalle?.destino) {
                return;
            }

            this.cerrar();
            destino = detalle.destino;
            this.modo = detalle.modo === 'input' ? 'input' : 'enter';
            this.continuo = !!detalle.continuo;
            this.error = '';
            this.ultimo = '';
            this.lecturas = 0;
            this.abierto = true;

            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.error = 'La cámara solo funciona con HTTPS. Use la pistola o escriba el código.';
                return;
            }

            this.cargando = true;
            // El AudioContext se crea dentro del toque del usuario: iOS no deja
            // crearlo despues.
            try {
                audio ??= new (window.AudioContext || window.webkitAudioContext)();
            } catch {
                audio = null;
            }

            try {
                const { crearDetector, abrirCamara, puedeLinterna } = await import('./escaner.js');
                const [detector, s] = await Promise.all([crearDetector(), abrirCamara(this.$refs.video)]);

                if (!this.abierto) {
                    s.getTracks().forEach((t) => t.stop());
                    return;
                }

                stream = s;
                this.hayLinterna = puedeLinterna(stream);
                this.cargando = false;
                this.leer(detector);
            } catch (e) {
                this.cargando = false;
                this.error = e?.name === 'NotAllowedError'
                    ? 'No hay permiso para usar la cámara. Habilítelo en el navegador.'
                    : e?.name === 'NotFoundError'
                        ? 'Este dispositivo no tiene cámara.'
                        : 'No se pudo abrir la cámara.';
                this.apagar();
            }
        },

        leer(detector) {
            const video = this.$refs.video;

            const paso = async () => {
                if (!this.abierto || !stream) {
                    return;
                }

                if (video.readyState >= 2) {
                    try {
                        const codigos = await detector.detect(video);
                        const valor = codigos.map((c) => (c.rawValue || '').trim()).find((v) => v !== '');

                        if (valor) {
                            this.entregar(valor);
                        }
                    } catch {
                        // un cuadro que no se pudo leer: se sigue con el siguiente
                    }
                }

                if (this.abierto && stream) {
                    bucle = setTimeout(paso, 120);
                }
            };

            paso();
        },

        entregar(codigo) {
            // En modo continuo, la camara sigue viendo el mismo codigo: se
            // descarta la repeticion durante 2 s.
            if (codigo === this.ultimo && Date.now() - this.ultimoEn < 2000) {
                return;
            }

            this.ultimo = codigo;
            this.ultimoEn = Date.now();
            this.lecturas++;
            this.pitido();

            const campo = destino;

            if (!this.continuo) {
                this.cerrar();
            }

            if (!campo?.isConnected || campo.disabled) {
                return;
            }

            campo.value = codigo;

            if (this.modo === 'input') {
                campo.dispatchEvent(new Event('input', { bubbles: true }));
                campo.dispatchEvent(new Event('change', { bubbles: true }));
            }

            campo.dispatchEvent(new KeyboardEvent('keydown', {
                key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true, cancelable: true,
            }));
        },

        pitido() {
            try {
                navigator.vibrate?.(80);
            } catch {
                // sin vibracion
            }

            if (!audio) {
                return;
            }

            try {
                const osc = audio.createOscillator();
                const vol = audio.createGain();
                osc.frequency.value = 1400;
                vol.gain.value = 0.15;
                osc.connect(vol).connect(audio.destination);
                osc.start();
                osc.stop(audio.currentTime + 0.08);
            } catch {
                // sin sonido
            }
        },

        async alternarLinterna() {
            try {
                const { linterna } = await import('./escaner.js');
                await linterna(stream, !this.linternaEncendida);
                this.linternaEncendida = !this.linternaEncendida;
            } catch {
                this.hayLinterna = false;
            }
        },

        apagar() {
            clearTimeout(bucle);
            bucle = null;
            stream?.getTracks().forEach((t) => t.stop());
            stream = null;

            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }

            this.hayLinterna = false;
            this.linternaEncendida = false;
        },

        cerrar() {
            this.apagar();
            this.abierto = false;
            this.cargando = false;
            destino = null;
        },
    }));
});
